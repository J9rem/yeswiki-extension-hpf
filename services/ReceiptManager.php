<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-receipts-creation
 * 
 * This class is the manager of receipts. It creates, associates receipts to
 * entries and furnish all things neededfor api.
 */

namespace YesWiki\Hpf\Service;

use attach;
use Exception;
use Throwable;
use Mpdf\Mpdf;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Hpf\Service\HpfService;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

require_once 'tools/attach/libs/attach.lib.php';

class ReceiptManager
{

    public const RECEIPT_UNIQ_ID_HPF_RESOURCE = 'ReceiptUniqId';
    public const RECEIPT_UNIQ_ID_HPF_PROPERTY = 'https://www.habitatparticipatif-france.fr/ReceiptUniqId';
    public const LOCALIZATION = 'private/receipts/';
    public const LOCALIZATION_CACHE = 'private/receipts/cache';
    public const NB_CHARS = 12;

    protected $aclService;
    protected $attach;
    protected $entryManager;
    protected $formManager;
    protected $hpfService;
    protected $securityController;
    protected $tripleStore;
    protected $wiki;

    public function __construct(
        AclService $aclService,
        EntryManager $entryManager,
        FormManager $formManager,
        HpfService $hpfService,
        SecurityController $securityController,
        TripleStore $tripleStore,
        Wiki $wiki
    ) {
        $this->aclService = $aclService;
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->hpfService = $hpfService;
        $this->securityController = $securityController;
        $this->tripleStore = $tripleStore;
        $this->wiki = $wiki;
        $this->attach = new attach($wiki);
    }

    /**
     * get next uniq ID on seven digits
     * @return string
     */
    public function getNextUniqId():string
    {
        $nextUniqId = $this->getNextUniqIdFromDatabase();
        if (empty($nextUniqId)){
            $nextUniqId = $this->getNextUniqIdFromFiles();
        }
        return empty($nextUniqId) ? $this->convertUniqIdFromInt(1) : $nextUniqId;
    }

    /**
     * get existing receipts for an entry
     * @param string $entryId
     * @param array $existingPayments
     * @return array $receipts [$number => array $data]
     */
    public function getExistingReceiptsForEntryId(string $entryId,array $existingPayments):array
    {
        $sanitizedEntryId = $this->attach->sanitizeFilename($entryId);
        $receipts = array_map(
            function($result){
                return [
                    'date' => $result['match'][1],
                    'uniqId' => $result['match'][2],
                    'origin' => $result['match'][3],
                    'paymentId' => $result['match'][4],
                    'md5' => $result['match'][5],
                    'filePath' => $result['filePath']
                ];
            },
            array_filter(
                $this->extractListOfFiles("$sanitizedEntryId/*-*-*-*-*",'/([^-]+)-([0-9]+)-([^-]+)-([^-]+)-([0-9A-Fa-f]+)/'),
                function($result){
                    return !empty($result['match'][1])
                        && !empty($result['match'][2])
                        && !empty($result['match'][3])
                        && !empty($result['match'][4])
                        && !empty($result['match'][5]);
                }
            )
        );
        $results = [];
        foreach ($receipts as $result) {
            if (array_key_exists($result['paymentId'],$existingPayments)){
                $waitedMD5 = $this->calculateShortMd5($existingPayments[$result['paymentId']]);
                $results[$result['paymentId']] = $result;
                $results[$result['paymentId']]['md5Match'] = ($result['md5'] == $waitedMD5);
            }
        }
        return $results;
    }

    /**
     * calculate short md5 from payment data
     * @param array $paymentData
     * @return string $shortMd5
     */
    public function calculateShortMd5(array $paymentData): string
    {
        return substr(md5(serialize($paymentData)),0,10);
    }

    /**
     * get existing receipt for an entry and a payment number
     * @param string $entryId
     * @param string $paymentId
     * @param array $existingPayments
     * @param bool $forceNotSameMd5
     * @return array [string $receiptPath,string $shortMD5]
     */
    public function getExistingReceiptForEntryIdAndNumber(string $entryId,string $paymentId,array $existingPayments,bool $forceNotSameMd5 = false):array
    {
        $receipts = $this->getExistingReceiptsForEntryId($entryId,$existingPayments);
        return (
            empty($receipts[$paymentId]['filePath'])
            || (
                !$forceNotSameMd5 && 
                !$receipts[$paymentId]['md5Match']
            )
        )
        ? ['','']
        : [
            $receipts[$paymentId]['filePath'],
            $receipts[$paymentId]['md5']
        ];
    }

    /**
     * generate a receipt
     * @param string $entryId
     * @param string $paymentId
     * @return array [string $receiptPath,string $errorMsg]
     * @throws Exception
     */
    public function generateReceiptForEntryIdAndNumber(string $entryId, string $paymentId):array
    {
        $hpfParams = $this->hpfService->getHpfParams();
        if (empty($hpfParams)){
            return ['','empty HpfParams'];
        }
        if (empty($entryId)){
            return ['','entryId should not be empty'];
        }
        if (empty($paymentId)){
            return ['','paymentId should not be empty'];
        }
        $structureInfo = $this->hpfService->getHpfStructureInfo();
        // extract data from entry
        $entry = $this->entryManager->getOne($entryId,false,null,false,true); // no cache, bypass acls for payments
        if (empty($entry)){
            return ['','not found entry'];
        }
        // check paymentId existing
        $existingPayments = $this->getPaymentsFromEntry($entry);
        if (!array_key_exists($paymentId,$existingPayments)){
            return ['','not found payment\'s id'];
        }
        $payment = $existingPayments[$paymentId];
        if ($payment['origin'] == 'structure'){
            return ['','It is not possible to generate a receipt for structure\'s payment !'];
        }
        // check if receipt is existing
        list($existingReceiptPath,$existingReceiptMd5) = $this->getExistingReceiptForEntryIdAndNumber($entryId,$paymentId,$existingPayments,true);
        if (!empty($existingReceiptPath)){
            $currentMD5 = $this->calculateShortMd5($payment);
            if ($currentMD5 == $existingReceiptMd5){
                return [$existingReceiptPath,'receipt already existing !'];
            } else {
                $currentDir = dirname($existingReceiptPath);
                $fileName = basename($existingReceiptPath);
                if (file_exists("$currentDir/archives/$fileName")){
                    unlink("$currentDir/archives/$fileName");
                }
                if (!is_dir("$currentDir/archives")){
                    mkdir("$currentDir/archives");
                }
                rename($existingReceiptPath,"$currentDir/archives/$fileName");
            }
        }
        // get uniqId
        $uniqId = $this->getNextUniqId();
        $filePath = $this->getFilePath(
            $entryId,
            $uniqId,
            $paymentId,
            $payment
        );
        $archiveFilePath = dirname($filePath).'/archives/'.basename($filePath);
        if (file_exists($archiveFilePath)){
            rename($archiveFilePath,$filePath);
            return [$filePath,'receipt already existing from archive !'];
        }
        // render via twig
        $html = $this->wiki->render('@hpf/hpf-receipts.twig',compact([
            'entry',
            'uniqId',
            'structureInfo',
            'paymentId',
            'payment'
        ]));
        if (empty($html)){
            throw new Exception('error when generating html !');
        }
        // next line only for tests
        // file_put_contents(str_replace('.pdf','.html',$filePath),$html);
        // use Mpdf to render pdf and save it
        $this->generatePdfFromHtml($html,$filePath);
        if (!is_file($filePath)){
            ['','The pdf was not generated'];
        }
        // save UniqId
        if ($this->saveLastestUniqId($uniqId)){
            return [$filePath,''];
        }
        if (is_file($filePath)){
            unlink($filePath);
        }
        return ['','not possible to save the new uniq Id'];
    }

    /**
     * extract payments from entry
     * @param array $entry
     * @return array $payments
     */
    public function getPaymentsFromEntry(array $entry): array
    {
        return $this->hpfService->convertStringToPayments($entry[HpfService::PAYMENTS_FIELDNAME] ?? '');
    }

    /**
     * retrieve next Id from database
     * @return string
     */
    protected function getNextUniqIdFromDatabase(): string
    {
        $value = $this->tripleStore->getOne(self::RECEIPT_UNIQ_ID_HPF_RESOURCE,self::RECEIPT_UNIQ_ID_HPF_PROPERTY,'','');
        return (empty($value) || !is_string($value)) ? '' : $this->convertUniqIdFromInt(intval($value)+1);
    }

    /**
     * convert uniq Id from int
     * @param int $value
     * @return string
     */
    public function convertUniqIdFromInt(int $value):string
    {
        return $value < 0 ? '' : str_pad(strval($value),self::NB_CHARS,'0',STR_PAD_LEFT);
    }

    /**
     * retrieve next Id from files (long)
     * @return string
     */
    protected function getNextUniqIdFromFiles(): string
    {
        $nbChars = self::NB_CHARS;
        $ids = array_map(
            function($result){
                return intval($result['match'][2]);
            },
            array_filter(
                $this->extractListOfFiles('*/*-*-*-*','/([^-]+)-([0-9]{12,$nbChars})-.+/'),
                function($result){
                    return !empty($result['match'][2]);
                }
            )
        );
        return empty($ids) ? '' : $this->convertUniqIdFromInt(max($ids)+1);
    }

    /**
     * extract list of files
     * @param string $globFilter
     * @param string $regexp
     * @return array $results
     */
    protected function extractListOfFiles(string $globFilter,string $regexp):array
    {
        $this->prepareDirectory();
        $files = glob(self::LOCALIZATION."$globFilter.pdf");
        $results = [];
        foreach ($files as $filePath) {
            $filename = pathinfo($filePath)['filename'];
            $match = [];
            if (preg_match($regexp,$filename,$match)){
                $results[] = compact(['match','filePath']);
            }
        }
        return $results;
    }

    /**
     * get file path
     * @param string $entryId
     * @param string $uniqId
     * @param string $paymentId
     * @param array $paymentData
     * @return string $filePath
     */
    protected function getFilePath(
        string $entryId,
        string $uniqId,
        string $paymentId,
        array $paymentData
    ):string
    {
        $sanitizedEntryId = $this->attach->sanitizeFilename($entryId);
        if (!file_exists(self::LOCALIZATION.$sanitizedEntryId)){
            mkdir(self::LOCALIZATION.$sanitizedEntryId);
        }
        $sanitizedOrigin = $this->attach->sanitizeFilename($paymentData['origin']);
        $date = $this->attach->sanitizeFilename(substr($paymentData['date'],0,10));
        $sanitizedpaymentId = $this->attach->sanitizeFilename($paymentId);
        $shortMd5 = $this->calculateShortMd5($paymentData);
        return self::LOCALIZATION
            ."$sanitizedEntryId/$date-$uniqId-$sanitizedOrigin-$sanitizedpaymentId-$shortMd5.pdf";
    }

    /**
     * prepare directory
     */
    protected function prepareDirectory()
    {
        if (!file_exists(self::LOCALIZATION)){
            mkdir(self::LOCALIZATION);
        }
        if (!file_exists(self::LOCALIZATION_CACHE)){
            mkdir(self::LOCALIZATION_CACHE);
        }
    }

    /**
     * save next uniq Id as lastest uniq ID 
     * @param string $nextUniqId
     * @return bool
     */
    public function saveLastestUniqId(string $nextUniqId): bool
    {
        $nextExpected = $this->getNextUniqId();
        if (intval($nextExpected) != intval($nextUniqId)){
            return false;
        }
        $value = $this->tripleStore->getOne(self::RECEIPT_UNIQ_ID_HPF_RESOURCE,self::RECEIPT_UNIQ_ID_HPF_PROPERTY,'','');
        
        return $this->tripleStore->update(self::RECEIPT_UNIQ_ID_HPF_RESOURCE,self::RECEIPT_UNIQ_ID_HPF_PROPERTY,$value,$nextExpected,'','') === 0;
    }

    /**
     * generate pdf from Html in filePath
     * @param string $html
     * @param string $filePath
     */
    protected function generatePdfFromHtml(string $html,string $filePath)
    {
        $this->prepareDirectory();
        $mpdf = new Mpdf([
            'tempDir' => self::LOCALIZATION_CACHE
        ]);
        $mpdf->WriteHTML($html);
        $mpdf->OutputFile($filePath);
    }
}
