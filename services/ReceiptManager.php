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
    public const NB_CHARS = 9;

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
     * @return array $receipts [$number => $path]
     */
    public function getExistingReceiptsForEntryId(string $entryId):array
    {
        $sanitizedEntryId = $this->attach->sanitizeFilename($entryId);
        $receipts = array_map(
            function($result){
                return [
                    'date' => $result['match'][1],
                    'uniqId' => $result['match'][2],
                    'origin' => $result['match'][3],
                    'paymentId' => $result['match'][4],
                    'filePath' => $result['filePath']
                ];
            },
            array_filter(
                $this->extractListOfFiles("$sanitizedEntryId/*-*-*-*",'/([^-]+)-([0-9]+)-([^-]+)-([^-]+)/'),
                function($result){
                    return !empty($result['match'][1]) && !empty($result['match'][2]) && !empty($result['match'][3]) && !empty($result['match'][4]);
                }
            )
        );
        $results = [];
        foreach ($receipts as $result) {
            $results[$result['paymentId']] = $result;
        }
        return $results;
    }

    /**
     * get existing receipt for an entry and a payment number
     * @param string $entryId
     * @param string $paymentId
     * @return string $receiptPath
     */
    public function getExistingReceiptForEntryIdAndNumber(string $entryId,string $paymentId):string
    {
        $receipts = $this->getExistingReceiptsForEntryId($entryId);
        return empty($receipts[$paymentId]['filePath']) ? '' : $receipts[$paymentId]['filePath'];
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
        $existingPayments = $this->hpfService->convertStringToPayments($entry[HpfService::PAYMENTS_FIELDNAME] ?? '');
        if (!array_key_exists($paymentId,$existingPayments)){
            return ['','not found payment\'s id'];
        }
        // check if receipt is existing
        $existingReceiptPath = $this->getExistingReceiptForEntryIdAndNumber($entryId,$paymentId);
        if (!empty($existingReceiptPath)){
            return [$existingReceiptPath,'receipt already existing !'];
        }
        $payment = $existingPayments[$paymentId];
        if ($payment['origin'] == 'structure'){
            return ['','It is not possible to generate a receipt for structure\'s payment !'];
        }
        // get uniqId
        $uniqId = $this->getNextUniqId();
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
        // use Mpdf to render pdf and save it
        $filePath = $this->getFilePath(
            $entryId,
            $uniqId,
            $paymentId,
            $payment
         );
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
        return $value <= 0 ? '' : str_pad(strval($value),self::NB_CHARS,'0',STR_PAD_LEFT);
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
                $this->extractListOfFiles('*/*-*-*-*','/([^-]+)-([0-9]{9,$nbChars})-.+/'),
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
        return self::LOCALIZATION
            ."$sanitizedEntryId/$date-$uniqId-$sanitizedOrigin-$sanitizedpaymentId.pdf";
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
