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
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Hpf\Service\HpfService;
use YesWiki\Shop\Entity\Payment;
use YesWiki\Shop\Entity\User;
use YesWiki\Shop\Entity\HelloAssoPayments;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\Hpf\Service\Helper;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';
require_once 'tools/hpf/tests/services/Helper.php';

class HpfServiceTest extends YesWikiTestCase
{
    private static $cache;
    private static $myWiki;

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
    ) {
        $thrown = false;
        $configurationService = $services['wiki']->services->get(ConfigurationService::class);
        $wakkaConfig = $this->getConfigValuesFromFile($configurationService);
        try {
            $params = $services['hpfService']->getHpfParams();
        } catch (Throwable $th) {
            $thrown = true;
        }
        $this->assertSame(is_null($wakkaConfig), $thrown, '\'hpf\' was not in same state in config than in service');
        if (!$thrown) {
            $this->assertIsArray($params);
        }
        return array_merge($services, compact(['wakkaConfig']));
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
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-payments-field
     */
    public function testGetCurrentPaymentsFormIds(
        array $services
    ) {
        $thrown = false;
        try {
            $formIds = $services['hpfService']->getCurrentPaymentsFormIds();
        } catch (Throwable $th) {
            $thrown = true;
        }
        $this->assertSame(is_null($services['wakkaConfig']), $thrown, '\'hpf\' was not in same state in config than in service');
        if (!$thrown) {
            $this->assertIsArray($formIds);
        }
        return array_merge($services, ['hpfParamdefined'=>!$thrown]);
    }

    /**
     * @depends testGetCurrentPaymentsFormIds
     * @param array $services [$wiki,$hpfService,$wakkaConfig,$hpfParamdefined]
     * @return array [$wiki,$hpfService,$wakkaConfig,$hpfParamdefined]
     */
    public function testHpfParamDefined(
        array $services
    ) {
        $GLOBALS['wiki'] = $services['wiki'];
        $this->assertTrue($services['hpfParamdefined'], "'hpf' param must be defined !");
        self::$cache['canSetList'] = true;
        $paymentsFormIds = $services['hpfService']->getCurrentPaymentsFormIds();
        $formManager = $services['wiki']->services->get(FormManager::class);
        foreach($paymentsFormIds as $id) {
            $form = $formManager->getOne($id);
            if (!empty($id) && empty($form)) {
                self::$cache['currentFormId'] = strval($id);
                break;
            }
        }
        if (empty(self::$cache['currentFormId'])) {
            self::$cache['currentFormId'] = $formManager->findNewId();
            $configurationService = $services['wiki']->services->get(ConfigurationService::class);
            $config = $configurationService->getConfiguration('wakka.config.php');
            $config->load();
            $newValues = $paymentsFormIds;
            $newValues[] = self::$cache['currentFormId'];
            $config['hpf'] = array_merge(
                $config['hpf'] ?? [],
                [
                    'contribFormIds' => implode(',', $newValues)
                ]
            );
            $configurationService->write($config);
            $this->assertTrue(false, 'all contrib form ids are used => RESTART TESTS');
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
     * Feature UUID : hpf-payment-status-action
     */
    public function testBfCalc(
        array $data,
        array $services
    ) {
        // create an entry
        Helper::updateEntry(true, array_intersect_key(
            $data,
            array_fill_keys([
                'bf_montant_adhesion_mixte_college_1_libre',
                'bf_montant_adhesion_mixte_college_2_libre',
                'bf_montant_don_ponctuel_libre',
                'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_1',
                'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_2',
                'liste'.Helper::CHOICELIST_ID.'bf_montant_don_ponctuel'
            ], 1)
        ), $services['wiki'], self::$cache['currentFormId'] ?? '');
        
        $entries = $services['hpfService']->getCurrentContribEntries(
            self::$cache['currentFormId'],
            Helper::ENTRY_EMAIL,
            Helper::ENTRY_ID
        );

        $entry = !empty($entries) ? $entries[array_key_first($entries)] : [];

        // delete the entry
        Helper::updateEntry(false, [], $services['wiki'], self::$cache['currentFormId'] ?? '');

        // tests
        $this->assertNotEmpty($entry, 'entry should not be empty');
        $this->assertIsArray($entry, 'entry should be array');
        $this->assertArrayHasKey('bf_mail', $entry, "entry should contain key 'bf_mail'");
        $this->assertSame(Helper::ENTRY_EMAIL, $entry['bf_mail'], "entry['bf_mail'] should be ".Helper::ENTRY_EMAIL);
        foreach([
            'bf_montant_adhesion_mixte_college_1_libre',
            'bf_montant_adhesion_mixte_college_2_libre',
            'bf_montant_don_ponctuel_libre',
            'bf_adhesion_a_payer',
            'bf_adhesion_groupe_a_payer',
            'bf_don_a_payer',
            'bf_calc',
            'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_1',
            'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_2',
            'liste'.Helper::CHOICELIST_ID.'bf_montant_don_ponctuel'
        ] as $key) {
            $this->assertArrayHasKey($key, $entry, "entry should contain key '$key'");
        }
        foreach([
            'bf_montant_adhesion_mixte_college_1_libre',
            'bf_montant_adhesion_mixte_college_2_libre',
            'bf_montant_don_ponctuel_libre'
        ] as $key) {
            $this->assertSame($data[$key], $entry[$key], "entry['$key'] should be {$data[$key]}");
        }
        foreach([
            'bf_adhesion_a_payer',
            'bf_adhesion_groupe_a_payer',
            'bf_don_a_payer',
            'bf_calc',
            'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_1',
            'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_2',
            'liste'.Helper::CHOICELIST_ID.'bf_montant_don_ponctuel'
        ] as $key) {
            $this->assertSame($data['waited'][$key], $entry[$key], "entry['$key'] should by {$data['waited'][$key]}");
        }
    }

    /**
     * provide list of sets to test bf calc
     */
    public function bfCalcProvider(): array
    {
        $default = [
            'bf_montant_adhesion_mixte_college_1_libre' => '',
            'bf_montant_adhesion_mixte_college_2_libre' => '',
            'bf_montant_don_ponctuel_libre' => '',
            'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_1' => 'standard',
            'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_2' => 'standard',
            'liste'.Helper::CHOICELIST_ID.'bf_montant_don_ponctuel' => 'standard',
            'waited' => [
                'bf_adhesion_a_payer' => '0',
                'bf_adhesion_groupe_a_payer' => '0',
                'bf_don_a_payer' => '0',
                'bf_calc' => '0',
                'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_1' => 'standard',
                'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_2' => 'standard',
                'liste'.Helper::CHOICELIST_ID.'bf_montant_don_ponctuel' => 'standard',
            ]
        ];
        return [
            'empty' => [$default],
            'only adhesion' => [array_replace_recursive($default, [
                'bf_montant_adhesion_mixte_college_1_libre' => '10.0',
                'waited' => [
                    'bf_adhesion_a_payer' => '10',
                    'bf_calc' => '10',
                ]
            ])],
            'only adhesion group' => [array_replace_recursive($default, [
                'bf_montant_adhesion_mixte_college_2_libre' => '11.0',
                'waited' => [
                    'bf_adhesion_groupe_a_payer' => '11',
                    'bf_calc' => '11',
                ]
            ])],
            'only donation' => [array_replace_recursive($default, [
                'bf_montant_don_ponctuel_libre' => '12.1',
                'waited' => [
                    'bf_don_a_payer' => '12.1',
                    'bf_calc' => '12.1',
                ]
            ])],
            'two adhesions' => [array_replace_recursive($default, [
                'bf_montant_adhesion_mixte_college_1_libre' => '5.6',
                'bf_montant_adhesion_mixte_college_2_libre' => '6.7',
                'waited' => [
                    'bf_adhesion_a_payer' => '5.6',
                    'bf_adhesion_groupe_a_payer' => '6.7',
                    'bf_calc' => '12.3',
                ]
            ])],
            'two adhesions and 0 donation' => [array_replace_recursive($default, [
                'bf_montant_adhesion_mixte_college_1_libre' => '5.6',
                'bf_montant_adhesion_mixte_college_2_libre' => '6.7',
                'bf_montant_don_ponctuel_libre' => '0',
                'waited' => [
                    'bf_adhesion_a_payer' => '5.6',
                    'bf_adhesion_groupe_a_payer' => '6.7',
                    'bf_don_a_payer' => '0',
                    'bf_calc' => '12.3',
                ]
            ])],
            'two adhesions and donation' => [array_replace_recursive($default, [
                'bf_montant_adhesion_mixte_college_1_libre' => '5.6',
                'bf_montant_adhesion_mixte_college_2_libre' => '6.7',
                'bf_montant_don_ponctuel_libre' => '2.1',
                'waited' => [
                    'bf_adhesion_a_payer' => '5.6',
                    'bf_adhesion_groupe_a_payer' => '6.7',
                    'bf_don_a_payer' => '2.1',
                    'bf_calc' => '14.4',
                ]
            ])]
        ];
    }

    /**
     * @depends testHpfParamDefined
     * @depends testBfCalc
     * @covers HpfService::updateEntryWithPayment
     * @covers HpfService::refreshPaymentsInfo
     * @dataProvider updateEntryWithPaymentProvider
     * @param array $data
     * @param array $services [$wiki,$hpfService,$wakkaConfig,$hpfParamdefined]
     * @return array [$wiki,$hpfService,$wakkaConfig,$hpfParamdefined]
     */
    public function testUpdateEntryWithPayment(
        array $data,
        array $services
    ) {
        $entriesToCheck = $this->prepareEntriesToTest($data, $services);
        
        // tests
        foreach ($entriesToCheck as $type => $entry) {
            $this->testEntry($data, $entry, $type);
        }

        // payment already defined
        
        $entriesToCheck = $this->prepareEntriesToTest(array_replace_recursive($data, [
            'bf_payments' => $data['paymentId']
        ]), $services);

        $this->testEntry($data, $entriesToCheck['calc'], 'calc payment existing');
        $currentYear = (new DateTime($data['paymentDate']))->format("Y");
        $this->testEntry(
            array_replace_recursive($data, [
                'waited' => array_merge(array_intersect_key(
                    $data,
                    array_fill_keys([
                        'bf_montant_adhesion_mixte_college_1_libre',
                        'bf_montant_adhesion_mixte_college_2_libre',
                        'bf_montant_don_ponctuel_libre',
                        'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_1',
                        'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_2',
                        'liste'.Helper::CHOICELIST_ID.'bf_montant_don_ponctuel'
                    ], 1)
                ), [
                    'bf_adhesion_a_payer' => strval(floatval($data['bf_montant_adhesion_mixte_college_1_libre'])),
                    'bf_adhesion_groupe_a_payer' => strval(floatval($data['bf_montant_adhesion_mixte_college_2_libre'])),
                    'bf_don_a_payer' => strval(floatval($data['bf_montant_don_ponctuel_libre'])),
                    'bf_calc' => strval(floatval($data['bf_montant_adhesion_mixte_college_1_libre'])
                        +floatval($data['bf_montant_adhesion_mixte_college_2_libre'])
                        +floatval($data['bf_montant_don_ponctuel_libre'])),
                ]),
                "bf_adhesion_payee_$currentYear" => '',
                "bf_adhesion_groupe_payee_$currentYear" => '',
                "bf_dons_payes_$currentYear" => '',
            ]),
            $entriesToCheck['updated'],
            'updated payment existing',
            function ($entry) use ($data) {
                $this->assertSame($data['paymentId'], $entry['bf_payments'], "entry['bf_payments'] (updated payment existing) should be same as '{$data['paymentId']}'");
                return;
            }
        );

        // other payment already defined
        
        $entriesToCheck = $this->prepareEntriesToTest(array_replace_recursive($data, [
            'bf_payments' => Helper::OTHER_PAYMENT_ID
        ]), $services);

        foreach ($entriesToCheck as $type => $entry) {
            $this->testEntry($data, $entry, "$type other payment existing");
            $jsonDecoded = json_decode($entry['bf_payments'], true);
            $this->assertArrayHasKey(Helper::OTHER_PAYMENT_ID, $jsonDecoded, "entry['bf_payments'] ($type other payment existing) should be array with key '".Helper::OTHER_PAYMENT_ID."'");
            $this->assertIsArray($jsonDecoded[Helper::OTHER_PAYMENT_ID], "entry['bf_payments'] ($type other payment existing) should be array of array json encoded");
            foreach([
                'date' => '',
                'origin' => 'helloasso',
                'total' => ''
            ] as $key => $waitedValue) {
                $this->assertArrayHasKey($key, $jsonDecoded[Helper::OTHER_PAYMENT_ID], "entry['bf_payments'] ($type other payment existing) should be array json_encoded with key '$key'");
                $this->assertSame($waitedValue, $jsonDecoded[Helper::OTHER_PAYMENT_ID][$key], "entry['bf_payments']['$key'] ($type other payment existing) should be same as '".json_encode($waitedValue)."'");
            }
        }

        return $services;
    }

    /**
     * prepare entries to test
     * @param array $data
     * @param array $services
     * @return array $entriesToTest [$calc,$update]
     */
    protected function prepareEntriesToTest(array $data, array $services): array
    {
        // create an entry
        Helper::updateEntry(true, array_intersect_key(
            $data,
            array_fill_keys([
                'bf_montant_adhesion_mixte_college_1_libre',
                'bf_montant_adhesion_mixte_college_2_libre',
                'bf_montant_don_ponctuel_libre',
                'bf_payments',
                'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_1',
                'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_2',
                'liste'.Helper::CHOICELIST_ID.'bf_montant_don_ponctuel'
            ], 1)
        ), $services['wiki'], self::$cache['currentFormId'] ?? '');

        $user = new user();
        $user->email = Helper::ENTRY_EMAIL;
        $payment = new Payment([
            'id' => $data['paymentId'],
            'payer' => $user,
            'amount' => $data['paymentAmount'],
            'date' => $data['paymentDate'],
            'status' => 'Authorized',
            'description' => ''
        ]);
        $payments = new HelloAssoPayments(
            [$payment], //payments
            [] // $otherData
        );

        $entryManager = $services['wiki']->services->get(EntryManager::class);
        $rawentry = $entryManager->getOne(Helper::ENTRY_ID, false, null, false, true); // no cache

        $entriesToCheck = [];
        $entriesToCheck['calc'] = $services['hpfService']->updateEntryWithPayment($rawentry, $payment);

        // test refreshPaymentsInfo
        $services['hpfService']->refreshPaymentsInfo(
            self::$cache['currentFormId'],
            Helper::ENTRY_EMAIL,
            Helper::ENTRY_ID,
            $payments
        );

        // get bypassing pageManager cache
        $entriesToCheck['updated'] = $entryManager->getOne(Helper::ENTRY_ID, false, null, false, true);

        // delete the entry
        Helper::updateEntry(false, [], $services['wiki'], self::$cache['currentFormId'] ?? '');
        return $entriesToCheck;
    }

    /**
     * test entry
     * @param array $data
     * @param array $entry
     * @param string $type
     * @param callable $testPayment
     */
    protected function testEntry(array $data, array $entry, string $type, $testPayment = null)
    {
        $this->assertNotEmpty($entry, "entry ($type) should not be empty");
        $this->assertIsArray($entry, "entry ($type) should be array");
        $this->assertArrayHasKey('bf_payments', $entry, "entry ($type) should contain key 'bf_payments'");
        $this->assertIsString($entry['bf_payments'], "entry ($type) should be a string");
        if (!is_callable($testPayment)) {
            $jsonDecoded = json_decode($entry['bf_payments'], true);
            $this->assertIsArray($jsonDecoded, "entry['bf_payments'] ($type) should be array json encoded");
            $this->assertArrayHasKey($data['paymentId'], $jsonDecoded, "entry['bf_payments'] ($type) should be array with key '{$data['paymentId']}'");
            $this->assertIsArray($jsonDecoded[$data['paymentId']], "entry['bf_payments'] ($type) should be array of array json encoded");
            foreach($data['waited']['bf_payment'] as $key => $waitedValue) {
                $this->assertArrayHasKey($key, $jsonDecoded[$data['paymentId']], "entry['bf_payments'] ($type) should be array json_encoded with key '$key'");
                $w = is_string($waitedValue) ? str_replace('{formId}', self::$cache['currentFormId'], $waitedValue) : $waitedValue;
                $this->assertSame($w, $jsonDecoded[$data['paymentId']][$key], "entry['bf_payments']['$key'] ($type) should be same as '".json_encode($w)."'");
            }
        } else {
            $testPayment($entry);
        }
        foreach([
            'bf_montant_adhesion_mixte_college_1_libre',
            'bf_montant_adhesion_mixte_college_2_libre',
            'bf_montant_don_ponctuel_libre',
            'bf_adhesion_a_payer',
            'bf_adhesion_groupe_a_payer',
            'bf_don_a_payer',
            'bf_calc',
            'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_1',
            'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_2',
            'liste'.Helper::CHOICELIST_ID.'bf_montant_don_ponctuel'
        ] as $key) {
            $this->assertArrayHasKey($key, $entry, "entry ($type) should contain key '$key'");
            $this->assertSame($data['waited'][$key], $entry[$key], "entry['$key'] ($type) should be '{$data['waited'][$key]}'");
        }
        $currentYear = (new DateTime())->format("Y");
        foreach ([
            'bf_adhesion_payee',
            'bf_adhesion_groupe_payee',
            'bf_dons_payes',
        ] as $key) {
            $this->assertArrayHasKey("{$key}_$currentYear", $entry, "entry ($type) should contain key '{$key}_$currentYear'");
            $this->assertSame($data["{$key}_$currentYear"], $entry["{$key}_$currentYear"], "entry['{$key}_$currentYear'] ($type) should be '{$data["{$key}_$currentYear"]}'");
        }
    }

    /**
     * get default data
     * @return array
     */
    public function getDefaultTestData():array
    {
        $date = new DateTime();
        $currentDate = $date->format("Y-m-d H:i:s");
        $currentYear = $date->format("Y");
        return [
            'bf_montant_adhesion_mixte_college_1_libre' => '',
            'bf_montant_adhesion_mixte_college_2_libre' => '',
            'bf_montant_don_ponctuel_libre' => '',
            'bf_payments' => '',
            'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_1' => 'standard',
            'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_2' => 'standard',
            'liste'.Helper::CHOICELIST_ID.'bf_montant_don_ponctuel' => 'standard',
            'paymentId' => Helper::DEFAULT_PAYMENT_ID,
            'paymentAmount' => '0',
            'paymentDate' => $currentDate,
            'waited' => [
                'bf_montant_adhesion_mixte_college_1_libre' => '',
                'bf_montant_adhesion_mixte_college_2_libre' => '',
                'bf_montant_don_ponctuel_libre' => '',
                'bf_adhesion_a_payer' => '0',
                'bf_adhesion_groupe_a_payer' => '0',
                'bf_don_a_payer' => '0',
                'bf_calc' => '0',
                'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_1' => 'standard',
                'liste'.Helper::CHOICELIST_ID.'bf_montant_adhesion_college_2' => 'standard',
                'liste'.Helper::CHOICELIST_ID.'bf_montant_don_ponctuel' => 'standard',
                'bf_payment' => [
                    'date' => $currentDate,
                    'origin' => "helloasso:{formId}",
                    'total' => '0'
                ]
            ],
            "bf_adhesion_payee_$currentYear" => '',
            "bf_adhesion_groupe_payee_$currentYear" => '',
            "bf_dons_payes_$currentYear" => '',
        ];
    }
    
    /**
     * provide list of sets to test updateEntryWithPayment
     */
    public function updateEntryWithPaymentProvider(): array
    {
        $date = new DateTime();
        $currentYear = $date->format("Y");
        $default = $this->getDefaultTestData();
        return [
            'empty' => [array_replace_recursive($default, [
                'paymentAmount' => '100',
                "bf_dons_payes_$currentYear" => '100',
                'waited' => [
                    'bf_payment' => [
                        'total' => '100',
                        'don' => [
                            "$currentYear" => '100'
                        ]
                    ]
                ],
            ])],
            'partial adhesion only' => [array_replace_recursive($default, [
                'bf_montant_adhesion_mixte_college_1_libre' => '20',
                'paymentAmount' => '10',
                'waited' => [
                    'bf_montant_adhesion_mixte_college_1_libre' => '20',
                    'bf_adhesion_a_payer' => '10',
                    'bf_calc' => '10',
                    'bf_payment' => [
                        'total' => '10',
                        'adhesion' => [
                            "$currentYear" => '10'
                        ]
                    ]
                ],
                "bf_adhesion_payee_$currentYear" => '10'
            ])],
            'full adhesion only' => [array_replace_recursive($default, [
                'bf_montant_adhesion_mixte_college_1_libre' => '20',
                'paymentAmount' => '20',
                'waited' => [
                    'bf_montant_adhesion_mixte_college_1_libre' => '20',
                    'bf_payment' => [
                        'total' => '20',
                        'adhesion' => [
                            "$currentYear" => '20'
                        ]
                    ]
                ],
                "bf_adhesion_payee_$currentYear" => '20'
            ])],
            'more than adhesion only' => [array_replace_recursive($default, [
                'bf_montant_adhesion_mixte_college_1_libre' => '20',
                'paymentAmount' => '30.4',
                'waited' => [
                    'bf_montant_adhesion_mixte_college_1_libre' => '20',
                    'bf_payment' => [
                        'total' => '30.4',
                        'adhesion' => [
                            "$currentYear" => '20'
                        ],
                        'don' => [
                            "$currentYear" => '10.4'
                        ]
                    ]
                ],
                "bf_adhesion_payee_$currentYear" => '20',
                "bf_dons_payes_$currentYear" => '10.4'
            ])],
            'partial adhesion groupe only' => [array_replace_recursive($default, [
                'bf_montant_adhesion_mixte_college_2_libre' => '23',
                'paymentAmount' => '10',
                'waited' => [
                    'bf_montant_adhesion_mixte_college_2_libre' => '23',
                    'bf_adhesion_groupe_a_payer' => '13',
                    'bf_calc' => '13',
                    'bf_payment' => [
                        'total' => '10',
                        'adhesion_groupe' => [
                            "$currentYear" => '10'
                        ]
                    ]
                ],
                "bf_adhesion_groupe_payee_$currentYear" => '10',
            ])],
            'full adhesion groupe only' => [array_replace_recursive($default, [
                'bf_montant_adhesion_mixte_college_2_libre' => '24',
                'paymentAmount' => '24',
                'waited' => [
                    'bf_montant_adhesion_mixte_college_2_libre' => '24',
                    'bf_payment' => [
                        'total' => '24',
                        'adhesion_groupe' => [
                            "$currentYear" => '24'
                        ]
                    ]
                ],
                "bf_adhesion_groupe_payee_$currentYear" => '24',
            ])],
            'more than adhesion groupe only' => [array_replace_recursive($default, [
                'bf_montant_adhesion_mixte_college_2_libre' => '25',
                'paymentAmount' => '30.4',
                'waited' => [
                    'bf_montant_adhesion_mixte_college_2_libre' => '25',
                    'bf_payment' => [
                        'total' => '30.4',
                        'adhesion_groupe' => [
                            "$currentYear" => '25'
                        ],
                        'don' => [
                            "$currentYear" => '5.4'
                        ]
                    ]
                ],
                "bf_adhesion_groupe_payee_$currentYear" => '25',
                "bf_dons_payes_$currentYear" => '5.4',
            ])],
            'partial don only' => [array_replace_recursive($default, [
                'bf_montant_don_ponctuel_libre' => '17',
                'paymentAmount' => '10',
                'waited' => [
                    'bf_montant_don_ponctuel_libre' => '7',
                    'bf_don_a_payer' => '7',
                    'bf_calc' => '7',
                    'liste'.Helper::CHOICELIST_ID.'bf_montant_don_ponctuel' => 'libre',
                    'bf_payment' => [
                        'total' => '10',
                        'don' => [
                            "$currentYear" => '10'
                        ]
                    ]
                ],
                "bf_dons_payes_$currentYear" => '10',
            ])],
            'full don only' => [array_replace_recursive($default, [
                'bf_montant_don_ponctuel_libre' => '18',
                'paymentAmount' => '18',
                'waited' => [
                    'bf_montant_don_ponctuel_libre' => '0',
                    'liste'.Helper::CHOICELIST_ID.'bf_montant_don_ponctuel' => 'libre',
                    'bf_payment' => [
                        'total' => '18',
                        'don' => [
                            "$currentYear" => '18'
                        ]
                    ]
                ],
                "bf_dons_payes_$currentYear" => '18',
            ])],
            'more than don only' => [array_replace_recursive($default, [
                'bf_montant_don_ponctuel_libre' => '17',
                'paymentAmount' => '30.9',
                'waited' => [
                    'bf_montant_don_ponctuel_libre' => '0',
                    'liste'.Helper::CHOICELIST_ID.'bf_montant_don_ponctuel' => 'libre',
                    'bf_payment' => [
                        'total' => '30.9',
                        'don' => [
                            "$currentYear" => '30.9'
                        ]
                    ]
                ],
                "bf_dons_payes_$currentYear" => '30.9',
            ])]
        ];
    }

    /**
     * setup a list and form for other tests
     */
    protected function setUp(): void
    {
        if (empty(self::$myWiki)) {
            return;
        }
        if ((self::$cache['canSetList'] ?? false) === true) {
            // create List
            Helper::updateList(true, self::$myWiki);
            // create Form
            self::$cache['currentFormId'] = Helper::updateForm(true, self::$myWiki, self::$cache['currentFormId'] ?? '');
        }
    }
    
    /**
     * remove list and form for other tests
     */
    public static function tearDownAfterClass(): void
    {
        if (empty(self::$myWiki)) {
            return;
        }
        // remove List
        Helper::updateList(false, self::$myWiki);
        // remove Form
        self::$cache['currentFormId'] = Helper::updateForm(false, self::$myWiki, self::$cache['currentFormId'] ?? '');
    }


}
