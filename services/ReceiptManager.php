<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Hpf\Service;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Hpf\Service\HpfService;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

class ReceiptManager
{

    public const RECEIPT_UNIQ_ID_HPF_RESOURCE = 'ReceiptUniqId';
    public const RECEIPT_UNIQ_ID_HPF_PROPERTY = 'https://www.habitatparticipatif-france.fr/ReceiptUniqId';
    public const LOCALIZATION = 'private/receipts/';

    protected $aclService;
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
        $this->securityController = $securityController;
        $this->tripleStore = $tripleStore;
        $this->wiki = $wiki;
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
        return empty($nextUniqId) ? '0000001' : $nextUniqId;
    }

    /**
     * get existing receipts for an entry
     * @param string $entryId
     * @return array $receipts [$number => $path]
     */
    public function getExistingReceiptsForEntryId(string $entryId):array
    {
        return [];
    }

    /**
     * get existing receipt for an entry and a payment number
     * @param string $entryId
     * @param string $paymentId
     * @return string $receiptPath
     */
    public function getExistingReceiptForEntryIdAndNumber(string $entryId,string $paymentId):array
    {
        return '';
    }

    /**
     * generate a receipt
     * @param string $entryId
     * @param string $paymentId
     * @return string $receiptPath
     */
    public function generateReceiptForEntryIdAndNumber(string $entryId, string $paymentId):array
    {
        $hpfParams = $this->hpfService->getHpfParams();
        if (empty($hpfParams['hpfData']) || empty($entryId) || empty($paymentId)){
            return '';
        }
        // TODO define in config.yaml params and import here
        // extract data from entry
        // check paymentId existing
        // get uniqId
        // render via twig
        // use Mpdf to render pdf TODO clean font to keep only needed
        // save file
        // save UniqId
        return '';
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
        return $value <= 0 ? '' : str_pad(strval($value),7,'0',STR_PAD_LEFT);
    }

    /**
     * retrieve next Id from files (long)
     * @return string
     */
    protected function getNextUniqIdFromFiles(): string
    {
        $this->prepareDirectory();
        $files = glob(self::LOCALIZATION.'*/*-[0-9][0-9][0-9][0-9][0-9][0-9][0-9]-*.pdf');
        $ids = [];
        $quotedBasePath = preg_quote(self::LOCALIZATION,'/');
        $quotedSeparator = preg_quote('/','/');
        $pregSearch = "/.*$quotedBasePath(.+)$quotedSeparator([^]+)-([0-9]{7})-.+\\.pdf/";
        foreach ($files as $filePath) {
            $filename = pathinfo($filePath)['filename'];
            $maches = [];
            if (preg_match($pregSearch,$filename,$matches) && !empty($matches[2])){
                $ids[] = intval($matches[2]);
            }
        }
        return empty($ids) ? '' : $this->convertUniqIdFromInt(max($ids)+1);
    }

    /**
     * prepare directory
     */
    protected function prepareDirectory()
    {
        if (!file_exists(self::LOCALIZATION)){
            mkdir(self::LOCALIZATION);
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
}
