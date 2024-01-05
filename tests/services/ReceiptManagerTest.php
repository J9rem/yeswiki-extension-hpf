<?php

/*
 * This file is part of the YesWiki Extension hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-receipts-creation
 */

namespace YesWiki\Test\Hpf\Service;

use Throwable;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Hpf\Controller\HpfController;
use YesWiki\Hpf\Service\HpfService;
use YesWiki\Hpf\Service\ReceiptManager;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\Hpf\Service\Helper;

require_once 'tests/YesWikiTestCase.php';
require_once 'tools/hpf/tests/services/Helper.php';

class ReceiptManagerTest extends YesWikiTestCase
{
    private static $cache;
    private static $myWiki;
    private static $previousValue;

    /**
     * @covers ReceiptManager::__construct
     * @return array ['wiki'=> $wiki,'receiptManager' => $receiptManager,'hpfController'=>$hpfController]
     */
    public function testReceiptManagerExisting(): array
    {
        $wiki = $this->getWiki();
        self::$myWiki = $wiki;
        self::$cache = [];
        $this->assertTrue($wiki->services->has(ReceiptManager::class));
        self::cleanFiles();
        return [
            'wiki' => $wiki,
            'receiptManager' => $wiki->services->get(ReceiptManager::class),
            'hpfController' => $wiki->services->get(HpfController::class)
        ];
    }

    /**
     * @depends testReceiptManagerExisting
     * @dataProvider convertUniqIdFromIntProvider
     * @covers ReceiptManager::convertUniqIdFromInt
     * @param int $value
     * @param string $waited
     * @param array $services [$wiki,$receiptManager,$hpfController]
     * @return array ['wiki'=> $wiki,'receiptManager' => $receiptManager,'hpfController'=>$hpfController]
     */
    public function testConvertUniqIdFromInt(
        int $value,
        string $waited,
        array $services
    ) {
        $thrown = false;
        try{
            $testingValue = $services['receiptManager']->convertUniqIdFromInt($value);
        } catch (Throwable $th){
            $thrown = true;
        }
        $this->assertFalse($thrown,'An exception has been thrown !');
        $this->assertIsString($testingValue,'testingValue should be a string');
        $this->assertEquals($waited,$testingValue,'The conversion is not right !');
        return $services;
    }

    
    /**
     * provide list of sets to test ReceiptManager::convertUniqIdFromInt
     */
    public function convertUniqIdFromIntProvider(): array
    {
        $sets = [];

        $defaults = [
            'value' => -1,
            'waited' => ''
        ];

        // negative number
        $sets[] = $defaults; // append

        // 0
        $set = $defaults; // copy
        // update
        $set['value'] = 0;
        $set['waited'] = '000000000000';
        $sets[] = $set; // append

        // 1
        $set = $defaults; // copy
        // update
        $set['value'] = 1;
        $set['waited'] = '000000000001';
        $sets[] = $set; // append

        // 666060
        $set = $defaults; // copy
        // update
        $set['value'] = 666060;
        $set['waited'] = '000000666060';
        $sets[] = $set; // append
        
        return $sets;
    }

    /**
     * reset value
     * @param array $services
     * @param null|string $oldValue
     */
    protected static function resetTripleValue(array $services,?string $oldValue)
    {
        $defaultValue = $services['receiptManager']->convertUniqIdFromInt(0);
        self::setTripleValue($services,empty($oldValue) ? $defaultValue : $oldValue);
    }

    /**
     * set value
     * @param array $services
     * @param string $value
     */
    protected static function setTripleValue(array $services,string $value)
    {
        $tripleStore = $services['wiki']->services->get(TripleStore::class);
        $currentValue = self::getCurrentUniqId($services);
        if (empty($currentValue)){
            $tripleStore->create(ReceiptManager::RECEIPT_UNIQ_ID_HPF_RESOURCE,ReceiptManager::RECEIPT_UNIQ_ID_HPF_PROPERTY,$value,'','');
        } else {
            $tripleStore->update(ReceiptManager::RECEIPT_UNIQ_ID_HPF_RESOURCE,ReceiptManager::RECEIPT_UNIQ_ID_HPF_PROPERTY,$currentValue,$value,'','');
        }
    }

    /**
     * @depends testReceiptManagerExisting
     * @covers ReceiptManager::getNextUniqId
     * @param array $services [$wiki,$receiptManager,$hpfController]
     * @return array ['wiki'=> $wiki,'receiptManager' => $receiptManager,'hpfController'=>$hpfController]
     */
    public function testGetNextUniqId(
        array $services
    ) {
        $thrown = false;
        $testingValue = $services['receiptManager']->convertUniqIdFromInt(30004);
        $uniqId = '';
        try{
            $uniqId = $services['receiptManager']->getNextUniqId();
            $value = self::getCurrentUniqId($services);
            self::setTripleValue($services,$testingValue);
            $uniqId2 = $services['receiptManager']->getNextUniqId();
            self::resetTripleValue($services,$value);
        } catch (Throwable $th){
            $thrown = true;
        }
        $this->assertFalse($thrown,'An exception has been thrown !');
        $this->assertIsString($uniqId,'uniqId should be a string');
        $this->assertMatchesRegularExpression('/^[0-9]{'.ReceiptManager::NB_CHARS.'}$/',$uniqId,'uniqId should be a string of '.ReceiptManager::NB_CHARS.' digits');
        $this->assertIsString($uniqId2,'uniqId2 should be a string');
        $this->assertMatchesRegularExpression('/^[0-9]{'.ReceiptManager::NB_CHARS.'}$/',$uniqId2,'uniqId2 should be a string of '.ReceiptManager::NB_CHARS.' digits');
        $this->assertEquals(intval($testingValue)+1,intval($uniqId2),'bad calculation of uniqId');
        return $services;
    }

    
    /**
     * @depends testGetNextUniqId
     * @covers ReceiptManager::saveLastestUniqId
     * @param array $services [$wiki,$receiptManager,$hpfController]
     * @return array ['wiki'=> $wiki,'receiptManager' => $receiptManager,'hpfController'=>$hpfController]
     */
    public function testSaveLastestUniqId(
        array $services
    ) {
        $thrown = false;
        $uniqId = '';
        try{
            $value = self::getCurrentUniqId($services);
            $uniqId = $services['receiptManager']->getNextUniqId();
            $wrongResult = $services['receiptManager']->saveLastestUniqId($services['receiptManager']->convertUniqIdFromInt(intval($uniqId) + 30));
            $uniqId2 = $services['receiptManager']->getNextUniqId();
            $goodResult = $services['receiptManager']->saveLastestUniqId($uniqId);
            $uniqId3 = $services['receiptManager']->getNextUniqId();
            self::resetTripleValue($services,$value);
        } catch (Throwable $th){
            $thrown = true;
        }
        $this->assertFalse($wrongResult,'Should not save not following value !');
        $this->assertEquals($uniqId,$uniqId2,'Should have same values !');
        $this->assertTrue($goodResult,'Should save the following value !');
        $this->assertEquals($uniqId2+1,$uniqId3,'Should save the same values !');
        return $services;
    }

    /**
     * @depends testSaveLastestUniqId
     * @covers ReceiptManager::getExistingReceiptsForEntryId
     * @param array $services [$wiki,$receiptManager,$hpfController]
     * @return array ['wiki'=> $wiki,'receiptManager' => $receiptManager,'hpfController'=>$hpfController]
     */
    public function testGetExistingReceiptsForEntryId(
        array $services
    ) {
        $thrown = false;
        self::cleanFiles();
        try{
            // create 2 payments
            $entry = $this->getExistingEntry($services,'virement',true);
            // generate receipts
            list($receipt1Path) = $services['receiptManager']->generateReceiptForEntryIdAndNumber($entry['id_fiche'],Helper::DEFAULT_PAYMENT_ID);
            list($receipt2Path) = $services['receiptManager']->generateReceiptForEntryIdAndNumber($entry['id_fiche'],Helper::OTHER_PAYMENT_ID);
            // get results
            $results = $services['receiptManager']->getExistingReceiptsForEntryId($entry['id_fiche']);
        } catch (Throwable $th){
            $thrown = true;
        }
        self::cleanFiles();
        $this->assertFalse($thrown,'An exception has been thrown and it is not waited !');
        $this->assertIsArray($results);
        $this->assertCount(2,$results);
        $this->assertArrayHasKey(Helper::DEFAULT_PAYMENT_ID,$results);
        $this->assertArrayHasKey(Helper::OTHER_PAYMENT_ID,$results);
        $this->assertArrayHasKey('filePath',$results[Helper::DEFAULT_PAYMENT_ID]);
        $this->assertArrayHasKey('filePath',$results[Helper::OTHER_PAYMENT_ID]);
        $this->assertIsString($results[Helper::DEFAULT_PAYMENT_ID]['filePath']);
        $this->assertIsString($results[Helper::OTHER_PAYMENT_ID]['filePath']);
        $this->assertEquals($receipt1Path,$results[Helper::DEFAULT_PAYMENT_ID]['filePath']);
        $this->assertEquals($receipt2Path,$results[Helper::OTHER_PAYMENT_ID]['filePath']);
        return $services;
    }

    /**
     * @depends testGetExistingReceiptsForEntryId
     * @covers ReceiptManager::getExistingReceiptForEntryIdAndNumber
     * @param array $services [$wiki,$receiptManager,$hpfController]
     * @return array ['wiki'=> $wiki,'receiptManager' => $receiptManager,'hpfController'=>$hpfController]
     */
    public function testGetExistingReceiptForEntryIdAndNumber(
        array $services
    ) {
        $thrown = false;
        self::cleanFiles();
        try{
            // create 1 payment
            $entry = $this->getExistingEntry($services,'virement',false);
            // generate receipts
            list($receiptPath) = $services['receiptManager']->generateReceiptForEntryIdAndNumber($entry['id_fiche'],Helper::DEFAULT_PAYMENT_ID);
            // get result
            $result = $services['receiptManager']->getExistingReceiptForEntryIdAndNumber($entry['id_fiche'],Helper::DEFAULT_PAYMENT_ID);
        } catch (Throwable $th){
            $thrown = true;
        }
        self::cleanFiles();
        $this->assertFalse($thrown,'An exception has been thrown and it is not waited !');
        $this->assertIsString($result);
        $this->assertEquals($receiptPath,$result);
        return $services;
    }

    /**
     * @depends testSaveLastestUniqId
     * @dataProvider generateReceiptForEntryIdAndNumberProvider
     * @covers ReceiptManager::generateReceiptForEntryIdAndNumber
     * @param string $entryId
     * @param string $paymentId
     * @param string $originOfPayment
     * @param bool $waitedThrown
     * @param string $errorMsgRegExp
     * @param array $services [$wiki,$receiptManager,$hpfController]
     * @return array ['wiki'=> $wiki,'receiptManager' => $receiptManager,'hpfController'=>$hpfController]
     */
    public function testGenerateReceiptForEntryIdAndNumber(
        string $entryId,
        string $paymentId,
        string $originOfPayment,
        bool $waitedThrown,
        string $errorMsgRegExp,
        array $services
    ) {
        $thrown = false;
        $thString = '';
        $uniqId = ''; 
        if ($entryId === Helper::ENTRY_ID){
            $entry = $this->getExistingEntry($services,$originOfPayment);
            $entryId = $entry['id_fiche'] ?? '';
        }
        try{
            $results = $services['receiptManager']->generateReceiptForEntryIdAndNumber($entryId,$paymentId);
        } catch (Throwable $th){
            $thrown = true;
            $thString = $services['hpfController']->formatThrowableStringForExport($th);
        }
        if ($waitedThrown){
            $this->assertTrue($thrown,'An exception has not been thrown and it is not waited !');
        } else {
            $this->assertFalse($thrown,"An exception has been thrown and it is not waited ! : $thString");
            // format of response
            $this->assertIsArray($results);
            $this->assertCount(2,$results);
            if (!empty($errorMsgRegExp)){
                $this->assertNotEmpty($results[1],'Error message should not be empty');
                $this->assertIsString($results[1],'Error message should be a string');
                $this->assertMatchesRegularExpression($errorMsgRegExp,$results[1],'Wrong error message !');
            } else {
                $this->assertEmpty($results[1],'Error message should be empty');
                $this->assertNotEmpty($results[0],'Receipt Path should not be empty');
                $this->assertIsString($results[0],'Receipt Path should be a string');
                $this->assertFileExists($results[0]);
            }
        }
        return $services;
    }

    /**
     * provide list of sets to test ReceiptManager::generateReceiptForEntryIdAndNumber
     */
    public function generateReceiptForEntryIdAndNumberProvider(): array
    {
        $sets = [];

        $defaults = [
            'entryId' => 'unknown entryId',
            'paymentId' => 'unknown paymentId',
            'originOfPayment' => 'virement',
            'waitedThrown' => false,
            'errorMsgRegExp' => ''
        ];

        // empty entryId
        $set = $defaults; // copy
        // update
        $set['entryId'] = '';
        $set['errorMsgRegExp'] = '/entryId should not be empty/';
        $sets[] = $set; // append

        // empty paymentId
        $set = $defaults; // copy
        // update
        $set['paymentId'] = '';
        $set['errorMsgRegExp'] = '/paymentId should not be empty/';
        $sets[] = $set; // append

        // unknown entryId
        $set = $defaults; // copy
        // update
        $set['errorMsgRegExp'] = '/not found entry/';
        $sets[] = $set; // append

        // unknown paymentId
        $set = $defaults; // copy
        // update
        $set['entryId'] = Helper::ENTRY_ID;
        $set['errorMsgRegExp'] = '/not found payment\'s id/';
        $sets[] = $set; // append

        // payment via structure
        $set = $defaults; // copy
        // update
        $set['entryId'] = Helper::ENTRY_ID;
        $set['paymentId'] = Helper::DEFAULT_PAYMENT_ID;
        $set['originOfPayment'] = 'structure';
        $set['errorMsgRegExp'] = '/It is not possible to generate a receipt for structure\'s payment !/';
        $sets[] = $set; // append

        // OK
        $set = $defaults; // copy
        // update
        $set['entryId'] = Helper::ENTRY_ID;
        $set['paymentId'] = Helper::DEFAULT_PAYMENT_ID;
        $set['errorMsgRegExp'] = '';
        $sets[] = $set; // append

        // already existing
        $set = $defaults; // copy
        // update
        $set['entryId'] = Helper::ENTRY_ID;
        $set['paymentId'] = Helper::DEFAULT_PAYMENT_ID;
        $set['errorMsgRegExp'] = '/receipt already existing !/';
        $sets[] = $set; // append
        
        return $sets;
    }

    /**
     * get an existing entry from a contrib form
     * @param array $services [$wiki,$receiptManager,$hpfController]
     * @param string $originOfPayment
     * @param bool $twoPayments
     * @return array $entry
     */
    public function getExistingEntry(array $services,string $originOfPayment, bool $twoPayments = false): array
    {
        // get services
        $entryManager = $services['wiki']->services->get(EntryManager::class);
        // set GLOBALS for bazar.fonct.php:44
        $GLOBALS['wiki'] = $services['wiki'];

        // get entry 
        $rawentry = $entryManager->getOne(Helper::ENTRY_ID, false, null, false, true); // no cache
        if (!empty($rawentry[HpfService::PAYMENTS_FIELDNAME])
            && strpos($rawentry[HpfService::PAYMENTS_FIELDNAME],"\"origin\":\"$originOfPayment\"") === false){
            // delete previous
            Helper::updateEntry(false,[],$services['wiki'],self::$cache['currentFormId'] ?? '');
            $rawentry = [];
        }
        if (empty($rawentry)){
            // create entry
            $data = (!$twoPayments) 
                ? [
                    HpfService::PAYMENTS_FIELDNAME => json_encode([
                        Helper::DEFAULT_PAYMENT_ID => [
                            'origin' => $originOfPayment,
                            'total' => '50',
                            'date' => '2024-01-01',
                            'don' => ['2024' => '50']
                        ]
                    ]),
                    'bf_dons_payes_2024' => '50',
                    'checkboxListeHpfTestUniqIdListebf_annees_dons_payes' => '2024'
                ] :
                [
                    HpfService::PAYMENTS_FIELDNAME => json_encode([
                        Helper::DEFAULT_PAYMENT_ID => [
                            'origin' => $originOfPayment,
                            'total' => '50',
                            'date' => '2024-01-01',
                            'don' => ['2024' => '50']
                        ],
                        Helper::OTHER_PAYMENT_ID => [
                            'origin' => $originOfPayment,
                            'total' => '50',
                            'date' => '2023-12-01',
                            'don' => ['2023' => '50']
                        ]
                    ]),
                    'bf_dons_payes_2024' => '50',
                    'bf_dons_payes_2023' => '50',
                    'checkboxListeHpfTestUniqIdListebf_annees_dons_payes' => '2023,2024'
                ];
            Helper::updateEntry(true,$data,$services['wiki'],self::$cache['currentFormId'] ?? '');
            $rawentry = $entryManager->getOne(Helper::ENTRY_ID, false, null, false, true); // no cache
        }
        return empty($rawentry) ? [] : $rawentry;
    }

    /**
     * get current uniqId Value
     * @param array $services [$wiki,$receiptManager,$hpfController]
     * @return string $uniqId
     */
    protected static function getCurrentUniqId(array $services): string
    {
        $tripleStore = $services['wiki']->services->get(TripleStore::class);
        $value = $tripleStore->getOne(ReceiptManager::RECEIPT_UNIQ_ID_HPF_RESOURCE,ReceiptManager::RECEIPT_UNIQ_ID_HPF_PROPERTY,'','');
        return empty($value) ? '' : $value;
    }

    /**
     * setup a list and form for other tests
     */
    protected function setUp(): void
    {
        if (empty(self::$myWiki)){
            return;
        }
        // save previous value
        if (empty(self::$previousValue) && self::$previousValue !== ''){
            self::$previousValue = self::getCurrentUniqId(['wiki'=>self::$myWiki]);
        }
        // create List
        Helper::updateList(true,self::$myWiki);
        // create Form
        self::$cache['currentFormId'] = Helper::updateForm(true,self::$myWiki,self::$cache['currentFormId'] ?? '');
    }

    /**
     * remove list and form for other tests
     */
    public static function tearDownAfterClass(): void
    {
        if (empty(self::$myWiki)){
            return;
        }
        // remove List
        Helper::updateList(false,self::$myWiki);
        // remove Form
        self::$cache['currentFormId'] = Helper::updateForm(false,self::$myWiki,self::$cache['currentFormId'] ?? '');
        // self::cleanFiles();
        // reset previous value
        $services = [
            'wiki'=>self::$myWiki,
            'receiptManager' => self::$myWiki->services->get(ReceiptManager::class)
        ];
        self::resetTripleValue($services,is_string(self::$previousValue) ? self::$previousValue : '');
    }


    /**
     * clean files
     */
    protected static function cleanFiles()
    {
        if (is_dir(ReceiptManager::LOCALIZATION.Helper::ENTRY_ID)){
            array_map('unlink',glob(ReceiptManager::LOCALIZATION.Helper::ENTRY_ID.'/*.pdf'));
            try {
                rmdir(ReceiptManager::LOCALIZATION.Helper::ENTRY_ID);
            } catch (Throwable $th) {
            }
        }
    }
}
