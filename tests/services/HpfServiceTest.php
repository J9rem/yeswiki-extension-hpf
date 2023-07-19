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

use DateTime;
use Throwable;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Hpf\Service\HpfService;
use YesWiki\Shop\Entity\Payment;
use YesWiki\Shop\Entity\User;
use YesWiki\Shop\HelloAssoPayments;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

class HpfServiceTest extends YesWikiTestCase
{
    private static $cache;
    private static $myWiki;
    private const FORM_ID = 'HpfTestForm';
    private const LIST_ID = 'ListeHpfTestUniqIdListe';
    private const CHOICELIST_ID = 'ListeHpfTestUniqId2Liste';
    private const ENTRY_ID = 'HpfTestUniqIdEntry';
    private const ENTRY_EMAIL = 'test@oui-wiki.pro';
    private const DEFAULT_PAYMENT_ID = '13245768A';

    /**
     * @covers HpfService::__construct
     * @return array ['wiki'=> $wiki,'hpfService' => $hpfService]
     */
    public function testHpfServiceExisting(): array
    {
        $wiki = $this->getWiki();
        self::$myWiki = $wiki;
        $this->assertTrue($wiki->services->has(HpfService::class));
        return ['wiki' => $wiki,'hpfService' => $wiki->services->get(HpfService::class)];
    }


    /**
     * @depends testHpfServiceExisting
     * @covers HpfService::getHpfParams
     * @param array $services [$wiki,$hpfService]
     * @return array ['wiki'=> $wiki,'hpfService' => $hpfService, 'wakkaConfig'=>$wakkaConfig]
     */
    public function testGetHpfParams(
        array $services
    ) 
    {
        $thrown = false;
        $configurationService = $services['wiki']->services->get(ConfigurationService::class);
        $wakkaConfig = $this->getConfigValuesFromFile($configurationService);
        try{
            $params = $services['hpfService']->getHpfParams();
        } catch (Throwable $th){
            $thrown = true;
        }
        $this->assertSame(is_null($wakkaConfig),$thrown,'\'hpf\' was not in same state in config than in service');
        if (!$thrown){
            $this->assertIsArray($params);
        }
        return array_merge($services,compact(['wakkaConfig']));
    }

    protected function getConfigValuesFromFile(ConfigurationService $configurationService): ?array
    {
        $config = $configurationService->getConfiguration('wakka.config.php');
        $config->load();
        return $config['hpf'] ?? null;
    }
    
    /**
     * @depends testGetHpfParams
     * @covers HpfService::getCurrentPaymentsFormIds
     * @param array $services [$wiki,$hpfService,$wakkaConfig]
     * @return array [$wiki,$hpfService,$wakkaConfig,$hpfParamdefined]
     */
    public function testGetCurrentPaymentsFormIds(
        array $services
    ) 
    {
        $thrown = false;
        try{
            $formIds = $services['hpfService']->getCurrentPaymentsFormIds();
        } catch (Throwable $th){
            $thrown = true;
        }
        $this->assertSame(is_null($services['wakkaConfig']),$thrown,'\'hpf\' was not in same state in config than in service');
        if (!$thrown){
            $this->assertIsArray($formIds);
        }
        return array_merge($services,['hpfParamdefined'=>!$thrown]);
    }

    /**
     * @depends testGetCurrentPaymentsFormIds
     * @param array $services [$wiki,$hpfService,$wakkaConfig,$hpfParamdefined]
     * @return array [$wiki,$hpfService,$wakkaConfig,$hpfParamdefined]
     */
    public function testHpfParamDefined(
        array $services
    ) 
    {
        $GLOBALS['wiki'] = $services['wiki'];
        $this->assertTrue($services['hpfParamdefined'],"'hpf' param must be defined !");
        self::$cache['canSetList'] = true;
        $paymentsFormIds = $services['hpfService']->getCurrentPaymentsFormIds();
        $formManager = $services['wiki']->services->get(FormManager::class);
        foreach($paymentsFormIds as $id){
            $form = $formManager->getOne($id);
            if (!empty($id) && empty($form)){
                self::$cache['currentFormId'] = strval($id);
                break;
            }
        }
        if (empty(self::$cache['currentFormId'])){
            self::$cache['currentFormId'] = $formManager->findNewId();
            $configurationService = $services['wiki']->services->get(ConfigurationService::class);
            $config = $configurationService->getConfiguration('wakka.config.php');
            $config->load();
            $newValues = $paymentsFormIds;
            $newValues[] = self::$cache['currentFormId'];
            $config['hpf'] = array_merge(
                $config['hpf'] ?? [],
                [
                    'contribFormIds' => implode(',',$newValues)
                ]
            );
            $configurationService->write($config);
            $this->assertTrue(false,'all contrib form ids are used => RESTART TESTS');
        }
        return $services;
    }

    /**
     * @depends testHpfParamDefined
     * @dataProvider bfCalcProvider
     * @covers HpfService::getCurrentContribEntries
     * @param array $data
     * @param array $services [$wiki,$hpfService,$wakkaConfig,$hpfParamdefined]
     * @return array [$wiki,$hpfService,$wakkaConfig,$hpfParamdefined]
     */
    public function testBfCalc(
        array $data,
        array $services
    ) 
    {
        // create an entry
        $this->updateEntry(true,array_intersect_key(
            $data,
            array_fill_keys([
                'bf_montant_adhesion_mixte_college_1_libre',
                'bf_montant_adhesion_mixte_college_2_libre',
                'bf_montant_don_ponctuel_libre'
            ],1)
        ));
        
        $entries = $services['hpfService']->getCurrentContribEntries(
            self::$cache['currentFormId'], 
            self::ENTRY_EMAIL,
            self::ENTRY_ID);

        $entry = !empty($entries) ? $entries[array_key_first($entries)] : [];

        // delete the entry
        $this->updateEntry(false,[]);

        // tests
        $this->assertNotEmpty($entry,'entry should not be empty');
        $this->assertIsArray($entry,'entry should be array');
        $this->assertArrayHasKey('bf_mail',$entry,"entry should contain key 'bf_mail'");
        $this->assertSame(self::ENTRY_EMAIL,$entry['bf_mail'],"entry['bf_mail'] should be ".self::ENTRY_EMAIL);
        foreach([
            'bf_montant_adhesion_mixte_college_1_libre',
            'bf_montant_adhesion_mixte_college_2_libre',
            'bf_montant_don_ponctuel_libre',
            'bf_adhesion_a_payer',
            'bf_adhesion_groupe_a_payer',
            'bf_don_a_payer',
            'bf_calc'
        ] as $key){
            $this->assertArrayHasKey($key,$entry,"entry should contain key '$key'");
        }
        foreach([
            'bf_montant_adhesion_mixte_college_1_libre',
            'bf_montant_adhesion_mixte_college_2_libre',
            'bf_montant_don_ponctuel_libre'
        ] as $key){
            $this->assertSame($data[$key],$entry[$key],"entry['$key'] should be {$data[$key]}");
        }
        foreach([
            'bf_adhesion_a_payer',
            'bf_adhesion_groupe_a_payer',
            'bf_don_a_payer',
            'bf_calc'
        ] as $key){
            $this->assertSame($data['waited'][$key],$entry[$key],"entry['$key'] should by {$data['waited'][$key]}");
        }
    }

    /**
     * provide list of sets to test bf calc
     */
    public function bfCalcProvider(): array
    {
        return [
            'empty' => [
                [
                    'bf_montant_adhesion_mixte_college_1_libre' => '',
                    'bf_montant_adhesion_mixte_college_2_libre' => '',
                    'bf_montant_don_ponctuel_libre' => '',
                    'waited' => [
                        'bf_adhesion_a_payer' => '0',
                        'bf_adhesion_groupe_a_payer' => '0',
                        'bf_don_a_payer' => '0',
                        'bf_calc' => '0'
                    ]
                ]
            ],
            'only adhesion' => [
                [
                  'bf_montant_adhesion_mixte_college_1_libre' => '10.0',
                  'bf_montant_adhesion_mixte_college_2_libre' => '',
                  'bf_montant_don_ponctuel_libre' => '',
                  'waited' => [
                      'bf_adhesion_a_payer' => '10',
                      'bf_adhesion_groupe_a_payer' => '0',
                      'bf_don_a_payer' => '0',
                      'bf_calc' => '10',
                  ]
                ]
            ],
            'only adhesion group' => [
                [
                  'bf_montant_adhesion_mixte_college_1_libre' => '',
                  'bf_montant_adhesion_mixte_college_2_libre' => '11.0',
                  'bf_montant_don_ponctuel_libre' => '',
                  'waited' => [
                      'bf_adhesion_a_payer' => '0',
                      'bf_adhesion_groupe_a_payer' => '11',
                      'bf_don_a_payer' => '0',
                      'bf_calc' => '11',
                  ]
                ]
            ],
            'only donation' => [
                [
                  'bf_montant_adhesion_mixte_college_1_libre' => '',
                  'bf_montant_adhesion_mixte_college_2_libre' => '',
                  'bf_montant_don_ponctuel_libre' => '12.1',
                  'waited' => [
                      'bf_adhesion_a_payer' => '0',
                      'bf_adhesion_groupe_a_payer' => '0',
                      'bf_don_a_payer' => '12.1',
                      'bf_calc' => '12.1',
                  ]
                ]
            ],
            'two adhesions' => [
                [
                  'bf_montant_adhesion_mixte_college_1_libre' => '5.6',
                  'bf_montant_adhesion_mixte_college_2_libre' => '6.7',
                  'bf_montant_don_ponctuel_libre' => '',
                  'waited' => [
                      'bf_adhesion_a_payer' => '5.6',
                      'bf_adhesion_groupe_a_payer' => '6.7',
                      'bf_don_a_payer' => '0',
                      'bf_calc' => '12.3',
                  ]
                ]
            ],
            'two adhesions and 0 donation' => [
                [
                  'bf_montant_adhesion_mixte_college_1_libre' => '5.6',
                  'bf_montant_adhesion_mixte_college_2_libre' => '6.7',
                  'bf_montant_don_ponctuel_libre' => '0',
                  'waited' => [
                      'bf_adhesion_a_payer' => '5.6',
                      'bf_adhesion_groupe_a_payer' => '6.7',
                      'bf_don_a_payer' => '0',
                      'bf_calc' => '12.3',
                  ]
                ]
            ],
            'two adhesions and donation' => [
                [
                  'bf_montant_adhesion_mixte_college_1_libre' => '5.6',
                  'bf_montant_adhesion_mixte_college_2_libre' => '6.7',
                  'bf_montant_don_ponctuel_libre' => '2.1',
                  'waited' => [
                      'bf_adhesion_a_payer' => '5.6',
                      'bf_adhesion_groupe_a_payer' => '6.7',
                      'bf_don_a_payer' => '2.1',
                      'bf_calc' => '14.4',
                  ]
                ]
            ]
        ];
    }

    /**
     * @depends testHpfParamDefined
     * @depends testBfCalc
     * @covers HpfService::updateEntryWithPayment
     * @dataProvider updateEntryWithPaymentProvider
     * @param array $data
     * @param array $services [$wiki,$hpfService,$wakkaConfig,$hpfParamdefined]
     * @return array [$wiki,$hpfService,$wakkaConfig,$hpfParamdefined]
     */
    public function testUpdateEntryWithPayment(
        array $data,
        array $services
    ) 
    {
        // create an entry
        $this->updateEntry(true,array_intersect_key(
            $data,
            array_fill_keys([
                'bf_montant_adhesion_mixte_college_1_libre',
                'bf_montant_adhesion_mixte_college_2_libre',
                'bf_montant_don_ponctuel_libre'
            ],1)
        ));

        $user = new user();
        $user->email = self::ENTRY_EMAIL;
        $payment = new Payment([
            'id' => $data['paymentId'],
            'payer' => $user,
            'amount' => $data['paymentAmount'],
            'date' => $data['paymentDate']
        ]);
        // $payments = new HelloAssoPayments(
        //     [$payment], //payments
        //     [] // $otherData
        // );

        $entryManager = $services['wiki']->services->get(EntryManager::class);
        $entry = $entryManager->getOne(self::ENTRY_ID);

        $entry = $services['hpfService']->updateEntryWithPayment($entry,$payment);

        // delete the entry
        $this->updateEntry(false,[]);

        
        // tests
        $this->assertNotEmpty($entry,'entry should not be empty');
        $this->assertIsArray($entry,'entry should be array');
        $this->assertArrayHasKey('bf_payments',$entry,"entry should contain key 'bf_payments'");
        $this->assertSame($data['paymentId'],$entry['bf_payments'],"entry['bf_payments'] should be {$data['paymentId']}");
        foreach([
            'bf_montant_adhesion_mixte_college_1_libre',
            'bf_montant_adhesion_mixte_college_2_libre',
            'bf_montant_don_ponctuel_libre',
            'bf_adhesion_a_payer',
            'bf_adhesion_groupe_a_payer',
            'bf_don_a_payer',
            'bf_calc'
        ] as $key){
            $this->assertArrayHasKey($key,$entry,"entry should contain key '$key'");
            $this->assertSame($data['waited'][$key],$entry[$key],"entry['$key'] should be '{$data['waited'][$key]}'");
        }
        $currentYear = (new DateTime())->format("Y");
        foreach ([
            'bf_adhesion_payee',
            'bf_adhesion_groupe_payee',
            'bf_dons_payes',
        ] as $key) {
            $this->assertArrayHasKey("{$key}_$currentYear",$entry,"entry should contain key '{$key}_$currentYear'");
            $this->assertSame($data["{$key}_$currentYear"],$entry["{$key}_$currentYear"],"entry['{$key}_$currentYear'] should be '{$data["{$key}_$currentYear"]}'");
        }

        return $services;
    }
    
    /**
     * provide list of sets to test updateEntryWithPayment
     */
    public function updateEntryWithPaymentProvider(): array
    {
        $date = new DateTime();
        $currentDate = $date->format("Y-m-d H:i:s");
        $currentYear = $date->format("Y");
        return [
            'empty' => [
                 [
                    'bf_montant_adhesion_mixte_college_1_libre' => '',
                    'bf_montant_adhesion_mixte_college_2_libre' => '',
                    'bf_montant_don_ponctuel_libre' => '',
                    'paymentId' => self::DEFAULT_PAYMENT_ID,
                    'paymentAmount' => '100',
                    'paymentDate' => $currentDate,
                    'waited' => [
                        'bf_montant_adhesion_mixte_college_1_libre' => '',
                        'bf_montant_adhesion_mixte_college_2_libre' => '',
                        'bf_montant_don_ponctuel_libre' => '',
                        'bf_adhesion_a_payer' => '0',
                        'bf_adhesion_groupe_a_payer' => '0',
                        'bf_don_a_payer' => '0',
                        'bf_calc' => '0',
                    ],
                    "bf_adhesion_payee_$currentYear" => '',
                    "bf_adhesion_groupe_payee_$currentYear" => '',
                    "bf_dons_payes_$currentYear" => '100',
                ]
            ],
            'partial adhesion only' => [
                 [
                    'bf_montant_adhesion_mixte_college_1_libre' => '20',
                    'bf_montant_adhesion_mixte_college_2_libre' => '',
                    'bf_montant_don_ponctuel_libre' => '',
                    'paymentId' => self::DEFAULT_PAYMENT_ID,
                    'paymentAmount' => '10',
                    'paymentDate' => $currentDate,
                    'waited' => [
                        'bf_montant_adhesion_mixte_college_1_libre' => '20',
                        'bf_montant_adhesion_mixte_college_2_libre' => '',
                        'bf_montant_don_ponctuel_libre' => '',
                        'bf_adhesion_a_payer' => '10',
                        'bf_adhesion_groupe_a_payer' => '0',
                        'bf_don_a_payer' => '0',
                        'bf_calc' => '10',
                    ],
                    "bf_adhesion_payee_$currentYear" => '10',
                    "bf_adhesion_groupe_payee_$currentYear" => '',
                    "bf_dons_payes_$currentYear" => '',
                ]
            ],
            'full adhesion only' => [
                 [
                    'bf_montant_adhesion_mixte_college_1_libre' => '20',
                    'bf_montant_adhesion_mixte_college_2_libre' => '',
                    'bf_montant_don_ponctuel_libre' => '',
                    'paymentId' => self::DEFAULT_PAYMENT_ID,
                    'paymentAmount' => '20',
                    'paymentDate' => $currentDate,
                    'waited' => [
                        'bf_montant_adhesion_mixte_college_1_libre' => '20',
                        'bf_montant_adhesion_mixte_college_2_libre' => '',
                        'bf_montant_don_ponctuel_libre' => '',
                        'bf_adhesion_a_payer' => '0',
                        'bf_adhesion_groupe_a_payer' => '0',
                        'bf_don_a_payer' => '0',
                        'bf_calc' => '0',
                    ],
                    "bf_adhesion_payee_$currentYear" => '20',
                    "bf_adhesion_groupe_payee_$currentYear" => '',
                    "bf_dons_payes_$currentYear" => '',
                ]
            ],
            'more than adhesion only' => [
                 [
                    'bf_montant_adhesion_mixte_college_1_libre' => '20',
                    'bf_montant_adhesion_mixte_college_2_libre' => '',
                    'bf_montant_don_ponctuel_libre' => '',
                    'paymentId' => self::DEFAULT_PAYMENT_ID,
                    'paymentAmount' => '30.4',
                    'paymentDate' => $currentDate,
                    'waited' => [
                        'bf_montant_adhesion_mixte_college_1_libre' => '20',
                        'bf_montant_adhesion_mixte_college_2_libre' => '',
                        'bf_montant_don_ponctuel_libre' => '',
                        'bf_adhesion_a_payer' => '0',
                        'bf_adhesion_groupe_a_payer' => '0',
                        'bf_don_a_payer' => '0',
                        'bf_calc' => '0',
                    ],
                    "bf_adhesion_payee_$currentYear" => '20',
                    "bf_adhesion_groupe_payee_$currentYear" => '',
                    "bf_dons_payes_$currentYear" => '10.4',
                ]
            ],
            'partial adhesion groupe only' => [
                 [
                    'bf_montant_adhesion_mixte_college_1_libre' => '',
                    'bf_montant_adhesion_mixte_college_2_libre' => '23',
                    'bf_montant_don_ponctuel_libre' => '',
                    'paymentId' => self::DEFAULT_PAYMENT_ID,
                    'paymentAmount' => '10',
                    'paymentDate' => $currentDate,
                    'waited' => [
                        'bf_montant_adhesion_mixte_college_1_libre' => '',
                        'bf_montant_adhesion_mixte_college_2_libre' => '23',
                        'bf_montant_don_ponctuel_libre' => '',
                        'bf_adhesion_a_payer' => '0',
                        'bf_adhesion_groupe_a_payer' => '13',
                        'bf_don_a_payer' => '0',
                        'bf_calc' => '13',
                    ],
                    "bf_adhesion_payee_$currentYear" => '',
                    "bf_adhesion_groupe_payee_$currentYear" => '10',
                    "bf_dons_payes_$currentYear" => '',
                ]
            ],
            'full adhesion groupe only' => [
                 [
                    'bf_montant_adhesion_mixte_college_1_libre' => '',
                    'bf_montant_adhesion_mixte_college_2_libre' => '24',
                    'bf_montant_don_ponctuel_libre' => '',
                    'paymentId' => self::DEFAULT_PAYMENT_ID,
                    'paymentAmount' => '24',
                    'paymentDate' => $currentDate,
                    'waited' => [
                        'bf_montant_adhesion_mixte_college_1_libre' => '',
                        'bf_montant_adhesion_mixte_college_2_libre' => '24',
                        'bf_montant_don_ponctuel_libre' => '',
                        'bf_adhesion_a_payer' => '0',
                        'bf_adhesion_groupe_a_payer' => '0',
                        'bf_don_a_payer' => '0',
                        'bf_calc' => '0',
                    ],
                    "bf_adhesion_payee_$currentYear" => '',
                    "bf_adhesion_groupe_payee_$currentYear" => '24',
                    "bf_dons_payes_$currentYear" => '',
                ]
            ],
            'more than adhesion groupe only' => [
                 [
                    'bf_montant_adhesion_mixte_college_1_libre' => '',
                    'bf_montant_adhesion_mixte_college_2_libre' => '25',
                    'bf_montant_don_ponctuel_libre' => '',
                    'paymentId' => self::DEFAULT_PAYMENT_ID,
                    'paymentAmount' => '30.4',
                    'paymentDate' => $currentDate,
                    'waited' => [
                        'bf_montant_adhesion_mixte_college_1_libre' => '',
                        'bf_montant_adhesion_mixte_college_2_libre' => '25',
                        'bf_montant_don_ponctuel_libre' => '',
                        'bf_adhesion_a_payer' => '0',
                        'bf_adhesion_groupe_a_payer' => '0',
                        'bf_don_a_payer' => '0',
                        'bf_calc' => '0',
                    ],
                    "bf_adhesion_payee_$currentYear" => '',
                    "bf_adhesion_groupe_payee_$currentYear" => '25',
                    "bf_dons_payes_$currentYear" => '5.4',
                ]
            ],
            'partial don only' => [
                 [
                    'bf_montant_adhesion_mixte_college_1_libre' => '',
                    'bf_montant_adhesion_mixte_college_2_libre' => '',
                    'bf_montant_don_ponctuel_libre' => '17',
                    'paymentId' => self::DEFAULT_PAYMENT_ID,
                    'paymentAmount' => '10',
                    'paymentDate' => $currentDate,
                    'waited' => [
                        'bf_montant_adhesion_mixte_college_1_libre' => '',
                        'bf_montant_adhesion_mixte_college_2_libre' => '',
                        'bf_montant_don_ponctuel_libre' => '7',
                        'bf_adhesion_a_payer' => '0',
                        'bf_adhesion_groupe_a_payer' => '0',
                        'bf_don_a_payer' => '7',
                        'bf_calc' => '7',
                    ],
                    "bf_adhesion_payee_$currentYear" => '',
                    "bf_adhesion_groupe_payee_$currentYear" => '',
                    "bf_dons_payes_$currentYear" => '10',
                ]
            ],
            'full don only' => [
                 [
                    'bf_montant_adhesion_mixte_college_1_libre' => '',
                    'bf_montant_adhesion_mixte_college_2_libre' => '',
                    'bf_montant_don_ponctuel_libre' => '18',
                    'paymentId' => self::DEFAULT_PAYMENT_ID,
                    'paymentAmount' => '18',
                    'paymentDate' => $currentDate,
                    'waited' => [
                        'bf_montant_adhesion_mixte_college_1_libre' => '',
                        'bf_montant_adhesion_mixte_college_2_libre' => '',
                        'bf_montant_don_ponctuel_libre' => '0',
                        'bf_adhesion_a_payer' => '0',
                        'bf_adhesion_groupe_a_payer' => '0',
                        'bf_don_a_payer' => '0',
                        'bf_calc' => '0',
                    ],
                    "bf_adhesion_payee_$currentYear" => '',
                    "bf_adhesion_groupe_payee_$currentYear" => '',
                    "bf_dons_payes_$currentYear" => '18',
                ]
            ],
            'more than don only' => [
                 [
                    'bf_montant_adhesion_mixte_college_1_libre' => '',
                    'bf_montant_adhesion_mixte_college_2_libre' => '',
                    'bf_montant_don_ponctuel_libre' => '17',
                    'paymentId' => self::DEFAULT_PAYMENT_ID,
                    'paymentAmount' => '30.9',
                    'paymentDate' => $currentDate,
                    'waited' => [
                        'bf_montant_adhesion_mixte_college_1_libre' => '',
                        'bf_montant_adhesion_mixte_college_2_libre' => '',
                        'bf_montant_don_ponctuel_libre' => '0',
                        'bf_adhesion_a_payer' => '0',
                        'bf_adhesion_groupe_a_payer' => '0',
                        'bf_don_a_payer' => '0',
                        'bf_calc' => '0',
                    ],
                    "bf_adhesion_payee_$currentYear" => '',
                    "bf_adhesion_groupe_payee_$currentYear" => '',
                    "bf_dons_payes_$currentYear" => '30.9',
                ]
            ]
        ];
    }

    /**
     * setup a list and form for other tests
     */
    protected function setUp(): void
    {
        if ((self::$cache['canSetList'] ?? false) === true){
            // create List
            self::updateList(true);
            // create Form
            self::updateForm(true);
        }
    }
    
    /**
     * remove list and form for other tests
     */
    public static function tearDownAfterClass(): void
    {
        // remove List
        self::updateList(false);
        // remove Form
        self::updateForm(false);
    }

    /**
     * update a list
     * @param bool $install
     */
    public static function updateList(bool $install)
    {
        self::updateListInternal(self::LIST_ID,$install,function(){
            $values = [];
            $currentYear = (new DateTime())->format('Y');
            $values[strval($currentYear-1)] = strval($currentYear-1);
            $values[strval($currentYear)] = strval($currentYear);
            return $values;
        });
        
        self::updateListInternal(self::CHOICELIST_ID,$install,function(){
            return [
                'standard' => 'Standard',
                'soutient' => 'Soutient',
                'libre' => 'Montant libre',
            ];
        });
    }

    public static function updateListInternal(string $id,bool $install,$getValues)
    {
        if (empty(self::$myWiki)){
            return;
        }
        $GLOBALS['wiki'] = self::$myWiki;

        $listManager = self::$myWiki->services->get(ListManager::class);
        $list = $listManager->getOne($id);
        if ($install && empty($list)){
            $listManager->create(substr($id,5),$getValues());
        } elseif (!$install && !empty($list)){
            self::actAsAdmin(function() use($listManager,$id){
                $listManager->delete($id);
            });
        }
    }

    /**
     * update a form
     * @param bool $install
     */
    public static function updateForm(bool $install)
    {
        if (empty(self::$myWiki)){
            return;
        }
        $GLOBALS['wiki'] = self::$myWiki;
        $formManager = self::$myWiki->services->get(FormManager::class);

        $id = self::$cache['currentFormId'] ?? '';
        $form = null;
        if (!empty($id)){
            $form = $formManager->getOne($id);
        }
        if ($install && empty($form)){
            if (empty(self::$cache['currentFormId'])){
                $newId = $formManager->findNewId();
                self::$cache['currentFormId'] = $newId;
            }
            $name = self::FORM_ID;
            $currentYear = strval((new DateTime())->format('Y'));
            $previousYear = strval(intval($currentYear)-1);
            $listId = self::LIST_ID;
            $choiceListId = self::CHOICELIST_ID;
            $template = <<<TXT
            texte***bf_titre***Nom*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***
            champs_mail***bf_mail***Email*** *** * *** ***form*** ***1***0*** *** * *** * *** *** *** ***
            liste***$choiceListId***Montant de mon adhésion*** *** *** ***bf_montant_adhesion_college_1*** ***0*** *** *** * *** * *** *** *** ***
            texte***bf_montant_adhesion_mixte_college_1_libre***Montant libre*** *** *** *** ***number***1*** *** *** * *** * *** *** *** ***
            liste***$choiceListId***Montant de l'adhésion de mon groupe*** *** *** ***bf_montant_adhesion_college_2*** ***0*** *** *** * *** * *** *** *** ***
            texte***bf_montant_adhesion_mixte_college_2_libre***Montant libre*** *** *** *** ***number***1*** *** *** * *** * *** *** *** ***
            liste***$choiceListId***Montant de mon don ponctuel*** *** *** ***bf_montant_don_ponctuel*** ***0*** *** *** * *** * *** *** *** ***
            texte***bf_montant_don_ponctuel_libre***Montant libre*** *** *** *** ***number***1*** *** *** * *** * *** *** *** ***
            texte***bf_adhesion_payee_$previousYear***Adhésion payée en $previousYear*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
            texte***bf_adhesion_payee_$currentYear***Adhésion payée en $currentYear*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
            texte***bf_adhesion_groupe_payee_$previousYear***Adhésion groupe payée en $previousYear*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
            texte***bf_adhesion_groupe_payee_$currentYear***Adhésion groupe payée en $currentYear*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
            texte***bf_dons_payes_$previousYear***Dons payé en $previousYear*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
            texte***bf_dons_payes_$currentYear***Dons payé en $currentYear*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
            checkbox***$listId***Années adhésions payées*** *** *** ***bf_annees_adhesions_payees*** ***0*** *** *** * *** * *** *** *** ***
            checkbox***$listId***Années adhésions groupe payées*** *** *** ***bf_annees_adhesions_groupe_payees*** ***0*** *** *** * *** * *** *** *** ***
            checkbox***$listId***Années dons payés*** *** *** ***bf_annees_dons_payes*** ***0*** *** *** * *** * *** *** *** ***
            calc***bf_adhesion_a_payer***Adhésion brute*** ***{value} €***(abs(bf_montant_adhesion_mixte_college_1_libre) - abs(bf_adhesion_payee_$currentYear) + abs(abs(bf_montant_adhesion_mixte_college_1_libre) - abs(bf_adhesion_payee_$currentYear)))/2*** *** *** *** *** *** * *** *** *** *** ***
            calc***bf_adhesion_groupe_a_payer***Adhésion groupe brute*** ***{value} €***(abs(bf_montant_adhesion_mixte_college_2_libre) - abs(bf_adhesion_groupe_payee_$currentYear) + abs(abs(bf_montant_adhesion_mixte_college_2_libre) - abs(bf_adhesion_groupe_payee_$currentYear)))/2*** *** *** *** *** *** * *** *** *** *** ***
            calc***bf_don_a_payer***Don brut*** ***{value} €***(abs(bf_montant_don_ponctuel_libre) - abs(bf_dons_payes_$currentYear) + abs(abs(bf_montant_don_ponctuel_libre) - abs(bf_dons_payes_$currentYear)))/2*** *** *** *** *** *** * *** *** *** *** ***
            calc***bf_calc***Reste à payer*** ***{value} €***bf_adhesion_a_payer+bf_adhesion_groupe_a_payer+bf_don_a_payer*** *** *** *** *** *** * *** *** *** *** ***
            payments***bf_payments***Liste des paiements*** *** *** *** *** *** *** *** *** *** *** *** *** ***
            TXT;
            $formManager->create([
                'bn_id_nature' => self::$cache['currentFormId'],
                'bn_label_nature' => $name,
                'bn_template' => $template,
                'bn_description' => 'template de test',
                'bn_sem_context' => false,
                'bn_sem_type' => '',
                'bn_condition' => '',
            ]);
        } elseif (!$install && !empty($form) && !empty($id)){
            $formManager->delete($id);
            self::$cache['currentFormId'] = '';
        }
    }

    /**
     * act as admin
     * @param callable $callback
     */
    public static function actAsAdmin($callback)
    {
        if (empty(self::$myWiki)){
            return;
        }
        $authController = self::$myWiki->services->get(AuthController::class);
        
        $previousUser = $authController->getLoggedUser();
        if (!empty($previousUser['name'])){
            $authController->logout();
        }
        $firstAdmin = $authController->connectFirstAdmin();
        $callback();
        $authController->logout();
        if (!empty($previousUser['name'])){
            $authController->logout();
            $authController->login($previousUser);
        }
    }

    /**
     * update an entry
     * @param bool $install
     * @param array $data
     */
    protected function updateEntry(bool $install, array $data)
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $GLOBALS['_BAZAR_'] = []; // reset cache

        $entryManager = $wiki->services->get(EntryManager::class);

        $id = self::ENTRY_ID;
        $entry = $entryManager->getOne($id);
        if ($install && empty($list)){
            if (!empty(self::$cache['currentFormId'])){
                $entryManager->create(
                    self::$cache['currentFormId'],
                    array_merge(
                        $data,
                        [
                        'antispam' => 1,
                        'bf_titre' => $id,
                        'id_fiche' => $id,
                        'bf_mail' => self::ENTRY_EMAIL
                        ]
                    )
                );

            }
        } elseif (!$install && !empty($entry)){
            self::actAsAdmin(function() use($entryManager,$id){
                $entryManager->delete($id);
            });
        }
    }
}
