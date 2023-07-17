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
use YesWiki\Hpf\Service\HpfService;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

class HpfServiceTest extends YesWikiTestCase
{
    private static $cache;
    private const FORM_ID = 'HpfTestForm';
    private const LIST_ID = 'ListeHpfTestUniqIdListe';
    private const ENTRY_ID = 'HpfTestUniqIdEntry';

    /**
     * @covers HpfService::__construct
     * @return array ['wiki'=> $wiki,'hpfService' => $hpfService]
     */
    public function testHpfServiceExisting(): array
    {
        $wiki = $this->getWiki();
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
     * @return array [$wiki,$hpfService,$wakkaConfig,$hpfParamdefined]
     */
    public function testHpfParamDefined(
        array $services
    ) 
    {
        $this->assertTrue($services['hpfParamdefined'],"'hpf' param must be defined !");
        self::$cache['canSetList'] = true;
        return $services;
    }

    /**
     * @depends testHpfParamDefined
     * @dataProvider bfCalcProvider
     * @param string $bf_value
     * @param string $bf_value_groupe
     * @param string $bf_value_don
     * @param string $waited_bf_adhesion_a_payer
     * @param string $waited_bf_adhesion_groupe_a_payer
     * @param string $waited_bf_don_a_payer
     * @param string $waited_bf_calc
     * @return array [$wiki,$hpfService,$wakkaConfig,$hpfParamdefined]
     */
    public function testBfCalc(
        string $bf_value,
        string $bf_value_groupe,
        string $bf_value_don,
        string $waited_bf_adhesion_a_payer,
        string $waited_bf_adhesion_groupe_a_payer,
        string $waited_bf_don_a_payer,
        string $waited_bf_calc,
        array $services
    ) 
    {
        // create an entry
        $this->updateEntry(true,compact([
            'bf_value',
            'bf_value_groupe',
            'bf_value_don',
            'waited_bf_adhesion_a_payer',
            'waited_bf_adhesion_groupe_a_payer',
            'waited_bf_don_a_payer',
            'waited_bf_calc',
        ]));
        
        $entryManager = $services['wiki']->services->get(EntryManager::class);
        $entry = $entryManager->getOne(self::ENTRY_ID);

        // delete the entry
        $this->updateEntry(false,[]);

        // tests
        $this->assertNotEmpty($entry,'entry should not be empty');
        $this->assertIsArray($entry,'entry should be array');
        foreach([
            'bf_value',
            'bf_value_groupe',
            'bf_value_don',
            'bf_adhesion_a_payer',
            'bf_adhesion_groupe_a_payer',
            'bf_don_a_payer',
            'bf_calc'
        ] as $key){
            $this->assertArrayHasKey($key,$entry,"entry should contain key '$key'");
        }
        foreach([
            'bf_value',
            'bf_value_groupe',
            'bf_value_don'
        ] as $key){
            $this->assertSame($entry[$key],$$key,"entry['$key'] should by {$$key}");
        }
        foreach([
            'bf_adhesion_a_payer',
            'bf_adhesion_groupe_a_payer',
            'bf_don_a_payer',
            'bf_calc'
        ] as $key){
            $waitedName = "waited_$key";
            $this->assertSame($entry[$key],$$waitedName,"entry['$key'] should by {$$waitedName}");
        }
        return $services;
    }

    /**
     * provide list of sets to test bf calc
     */
    public function bfCalcProvider(): array
    {
        return [
            'empty' => [
                'bf_value' => '',
                'bf_value_groupe' => '',
                'bf_value_don' => '',
                'waited_bf_adhesion_a_payer' => '0',
                'waited_bf_adhesion_groupe_a_payer' => '0',
                'waited_bf_don_a_payer' => '0',
                'waited_bf_calc' => '0',
            ],
            'only adhesion' => [
                'bf_value' => '10.0',
                'bf_value_groupe' => '',
                'bf_value_don' => '',
                'waited_bf_adhesion_a_payer' => '10',
                'waited_bf_adhesion_groupe_a_payer' => '0',
                'waited_bf_don_a_payer' => '0',
                'waited_bf_calc' => '10',
            ],
            'only adhesion group' => [
                'bf_value' => '',
                'bf_value_groupe' => '11.0',
                'bf_value_don' => '',
                'waited_bf_adhesion_a_payer' => '0',
                'waited_bf_adhesion_groupe_a_payer' => '11',
                'waited_bf_don_a_payer' => '0',
                'waited_bf_calc' => '11',
            ],
            'only donation' => [
                'bf_value' => '',
                'bf_value_groupe' => '',
                'bf_value_don' => '12.1',
                'waited_bf_adhesion_a_payer' => '0',
                'waited_bf_adhesion_groupe_a_payer' => '0',
                'waited_bf_don_a_payer' => '12.1',
                'waited_bf_calc' => '12.1',
            ],
            'two adhesions' => [
                'bf_value' => '5.6',
                'bf_value_groupe' => '6.7',
                'bf_value_don' => '',
                'waited_bf_adhesion_a_payer' => '5.6',
                'waited_bf_adhesion_groupe_a_payer' => '6.7',
                'waited_bf_don_a_payer' => '0',
                'waited_bf_calc' => '12.3',
            ],
            'two adhesions and 0 donation' => [
                'bf_value' => '5.6',
                'bf_value_groupe' => '6.7',
                'bf_value_don' => '0',
                'waited_bf_adhesion_a_payer' => '5.6',
                'waited_bf_adhesion_groupe_a_payer' => '6.7',
                'waited_bf_don_a_payer' => '0',
                'waited_bf_calc' => '12.3',
            ],
            'two adhesions and donation' => [
                'bf_value' => '5.6',
                'bf_value_groupe' => '6.7',
                'bf_value_don' => '2.1',
                'waited_bf_adhesion_a_payer' => '5.6',
                'waited_bf_adhesion_groupe_a_payer' => '6.7',
                'waited_bf_don_a_payer' => '2.1',
                'waited_bf_calc' => '14.4',
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
            $this->updateList(true);
            // create Form
            $this->updateForm(true);
        }
    }
    
    /**
     * remove list and form for other tests
     */
    protected function tearDown(): void
    {
        // remove List
        $this->updateList(false);
        // remove Form
        $this->updateForm(false);
    }

    /**
     * update a list
     * @param bool $install
     */
    protected function updateList(bool $install)
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;

        $listManager = $wiki->services->get(ListManager::class);

        $id = self::LIST_ID;
        $list = $listManager->getOne($id);
        if ($install && empty($list)){
            $values = [];
            $currentYear = (new DateTime())->format('Y');
            $values[strval($currentYear-1)] = strval($currentYear-1);
            $values[strval($currentYear)] = strval($currentYear);
            $listManager->create('HpfTestUniqIdListe',$values);
        } elseif (!$install && !empty($list)){
            $this->actAsAdmin(function() use($listManager,$id){
                $listManager->delete($id);
            });
        }
    }

    /**
     * update a form
     * @param bool $install
     */
    protected function updateForm(bool $install)
    {
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;

        $formManager = $wiki->services->get(FormManager::class);

        $name = self::FORM_ID;
        $currentYear = strval((new DateTime())->format('Y'));
        $previousYear = strval(intval($currentYear)-1);
        $listId = self::LIST_ID;
        $template = <<<TXT
        texte***bf_titre***Nom*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***
        texte***bf_value***Valeur souhaitée*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***
        texte***bf_value_groupe***Valeur groupe souhaitée*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***
        texte***bf_value_don***Valeur don souhaité*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***
        texte***bf_adhesion_payee_$previousYear***Adhésion payée en $previousYear*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
        texte***bf_adhesion_payee_$currentYear***Adhésion payée en $currentYear*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
        texte***bf_adhesion_groupe_payee_$previousYear***Adhésion groupe payée en $previousYear*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
        texte***bf_adhesion_groupe_payee_$currentYear***Adhésion groupe payée en $currentYear*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
        texte***bf_dons_payes_$previousYear***Dons payé en $previousYear*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
        texte***bf_dons_payes_$currentYear***Dons payé en $currentYear*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
        checkbox***$listId***Années adhésions payées*** *** *** ***bf_annees_adhesions_payees*** ***0*** *** *** * *** * *** *** *** ***
        checkbox***$listId***Années adhésions groupe payées*** *** *** ***bf_annees_adhesions_groupe_payees*** ***0*** *** *** * *** * *** *** *** ***
        checkbox***$listId***Années dons payés*** *** *** ***bf_annees_dons_payes*** ***0*** *** *** * *** * *** *** *** ***
        calc***bf_adhesion_a_payer***Adhésion brute*** ***{value} €***(abs(bf_value) - abs(bf_adhesion_payee_$currentYear) + abs(abs(bf_value) - abs(bf_adhesion_payee_$currentYear)))/2*** *** *** *** *** *** * *** *** *** *** ***
        calc***bf_adhesion_groupe_a_payer***Adhésion groupe brute*** ***{value} €***(abs(bf_value_groupe) - abs(bf_adhesion_groupe_payee_$currentYear) + abs(abs(bf_value_groupe) - abs(bf_adhesion_groupe_payee_$currentYear)))/2*** *** *** *** *** *** * *** *** *** *** ***
        calc***bf_don_a_payer***Don brut*** ***{value} €***(abs(bf_value_don) - abs(bf_dons_payes_$currentYear) + abs(abs(bf_value_don) - abs(bf_dons_payes_$currentYear)))/2*** *** *** *** *** *** * *** *** *** *** ***
        calc***bf_calc***Reste à payer*** ***{value} €***bf_adhesion_a_payer+bf_adhesion_groupe_a_payer+bf_don_a_payer*** *** *** *** *** *** * *** *** *** *** ***
        TXT;
        $id = self::$cache['currentFormId'] ?? '';
        $form = null;
        if (!empty($id)){
            $form = $formManager->getOne($id);
            if (empty($form)){
                self::$cache['currentFormId'] = '';
            }
        }
        if ($install && empty($form)){
            $newId = $formManager->findNewId();
            self::$cache['currentFormId'] = $newId;
            $formManager->create([
                'bn_id_nature' => $newId,
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
    protected function actAsAdmin($callback)
    {
        $wiki = $this->getWiki();
        $authController = $wiki->services->get(AuthController::class);
        
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
                        'id_fiche' => $id
                        ]
                    )
                );

            }
        } elseif (!$install && !empty($entry)){
            $this->actAsAdmin(function() use($entryManager,$id){
                $entryManager->delete($id);
            });
        }
    }
}
