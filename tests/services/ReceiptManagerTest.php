<?php

/*
 * This file is part of the YesWiki Extension hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Test\Hpf\Service;

use YesWiki\Core\Service\TripleStore;
use YesWiki\Hpf\Service\ReceiptManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class ReceiptManagerTest extends YesWikiTestCase
{
    /**
     * @covers ReceiptManager::__construct
     * @return array ['wiki'=> $wiki,'receiptManager' => $receiptManager]
     */
    public function testReceiptManagerExisting(): array
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(ReceiptManager::class));
        return ['wiki' => $wiki,'receiptManager' => $wiki->services->get(ReceiptManager::class)];
    }

    /**
     * @depends testReceiptManagerExisting
     * @covers ReceiptManager::getNextUniqId
     * @param array $services [$wiki,$receiptManager]
     * @return array ['wiki'=> $wiki,'receiptManager' => $receiptManager]
     */
    public function testGetNextUniqId(
        array $services
    ) {
        $thrown = false;
        $uniqId = '';
        $tripleStore = $services['wiki']->services->get(TripleStore::class);
        try{
            $uniqId = $services['receiptManager']->getNextUniqId();
            $value = $tripleStore->getOne(ReceiptManager::RECEIPT_UNIQ_ID_HPF_RESOURCE,ReceiptManager::RECEIPT_UNIQ_ID_HPF_PROPERTY,'','');
            if (empty($value)){
                $tripleStore->create(ReceiptManager::RECEIPT_UNIQ_ID_HPF_RESOURCE,ReceiptManager::RECEIPT_UNIQ_ID_HPF_PROPERTY,'0030004','','');
            } else {
                $tripleStore->update(ReceiptManager::RECEIPT_UNIQ_ID_HPF_RESOURCE,ReceiptManager::RECEIPT_UNIQ_ID_HPF_PROPERTY,$value,'0030004','','');
            }
            $uniqId2 = $services['receiptManager']->getNextUniqId();
            $tripleStore->update(ReceiptManager::RECEIPT_UNIQ_ID_HPF_RESOURCE,ReceiptManager::RECEIPT_UNIQ_ID_HPF_PROPERTY,'0030004',empty($value) ? '0000000' : $value,'','');
        } catch (Throwable $th){
            $thrown = true;
        }
        $this->assertFalse($thrown,'An exception has benn thrown !');
        $this->assertIsString($uniqId,'uniqId should be a string');
        $this->assertMatchesRegularExpression('/^[0-9]{7}$/',$uniqId,'uniqId should be a string of 7 digits');
        $this->assertIsString($uniqId2,'uniqId2 should be a string');
        $this->assertMatchesRegularExpression('/^[0-9]{7}$/',$uniqId2,'uniqId2 should be a string of 7 digits');
        $this->assertEquals(30005,intval($uniqId2),'bad calculation of uniqId');
        return $services;
    }

    
    /**
     * @depends testGetNextUniqId
     * @covers ReceiptManager::saveLastestUniqId
     * @param array $services [$wiki,$receiptManager]
     * @return array ['wiki'=> $wiki,'receiptManager' => $receiptManager]
     */
    public function testSaveLastestUniqId(
        array $services
    ) {
        $thrown = false;
        $uniqId = '';
        $tripleStore = $services['wiki']->services->get(TripleStore::class);
        try{
            $value = $tripleStore->getOne(ReceiptManager::RECEIPT_UNIQ_ID_HPF_RESOURCE,ReceiptManager::RECEIPT_UNIQ_ID_HPF_PROPERTY,'','');
            $uniqId = $services['receiptManager']->getNextUniqId();
            $wrongResult = $services['receiptManager']->saveLastestUniqId($services['receiptManager']->convertUniqIdFromInt(intval($uniqId) + 30));
            $uniqId2 = $services['receiptManager']->getNextUniqId();
            $goodResult = $services['receiptManager']->saveLastestUniqId($uniqId);
            $uniqId3 = $services['receiptManager']->getNextUniqId();
            $tripleStore->update(ReceiptManager::RECEIPT_UNIQ_ID_HPF_RESOURCE,ReceiptManager::RECEIPT_UNIQ_ID_HPF_PROPERTY,$uniqId,empty($value) ? '0000000' : $value,'','');
        } catch (Throwable $th){
            $thrown = true;
        }
        $this->assertFalse($wrongResult,'Should not save not following value !');
        $this->assertEquals($uniqId,$uniqId2,'Should have same values !');
        $this->assertTrue($goodResult,'Should save the following value !');
        $this->assertEquals($uniqId2+1,$uniqId3,'Should save the same values !');
        return $services;
    }
}
