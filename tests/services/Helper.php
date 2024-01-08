<?php

/*
 * This file is part of the YesWiki Extension hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * Helper for tests
 */

namespace YesWiki\Test\Hpf\Service;

use DateTime;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Wiki;

class Helper
{
    private const FORM_ID = 'HpfTestForm';
    private const LIST_ID = 'ListeHpfTestUniqIdListe';
    public const CHOICELIST_ID = 'ListeHpfTestUniqId2Liste';
    public const ENTRY_ID = 'HpfTestUniqIdEntry';
    public const ENTRY_EMAIL = 'test@oui-wiki.pro';
    public const DEFAULT_PAYMENT_ID = '13245768A';
    public const OTHER_PAYMENT_ID = '13245768B';

    /**
     * update a list
     * @param bool $install
     * @param Wiki $wiki
     */
    public static function updateList(bool $install, Wiki $wiki)
    {
        self::updateListInternal(self::LIST_ID,$install,function(){
            $values = [];
            $currentYear = (new DateTime())->format('Y');
            $values[strval($currentYear-1)] = strval($currentYear-1);
            $values[strval($currentYear)] = strval($currentYear);
            return $values;
        },$wiki);
        
        self::updateListInternal(self::CHOICELIST_ID,$install,function(){
            return [
                'standard' => 'Standard',
                'soutient' => 'Soutient',
                'libre' => 'Montant libre',
            ];
        },$wiki);
    }

    /**
     * update list internal
     * @param string $id
     * @param bool $install
     * @param callable $getValues
     * @param Wiki $wiki
     */
    protected static function updateListInternal(string $id,bool $install,$getValues, Wiki $wiki)
    {
        // needed for bazar.funct.php
        $GLOBALS['wiki'] = $wiki;

        $listManager = $wiki->services->get(ListManager::class);
        $list = $listManager->getOne($id);
        if ($install && empty($list)){
            self::actAsAdmin(function() use($listManager,$id,$getValues){
                $listManager->create(substr($id,5),$getValues());
            },$wiki);
        } elseif (!$install && !empty($list)){
            self::actAsAdmin(function() use($listManager,$id){
                $listManager->delete($id);
            },$wiki);
        }
    }


    /**
     * update a form
     * @param bool $install
     * @param Wiki $wiki
     * @param string $currentFormId
     * @return string $currentFormId
     */
    public static function updateForm(bool $install, Wiki $wiki,string $currentFormId): string
    {
        $GLOBALS['wiki'] = $wiki;
        $formManager = $wiki->services->get(FormManager::class);

        $id = $currentFormId ?? '';
        $form = null;
        if (!empty($id)){
            $form = $formManager->getOne($id);
        }
        if ($install && empty($form)){
            if (empty($currentFormId)){
                $newId = $formManager->findNewId();
                $currentFormId = $newId;
            }
            $name = self::FORM_ID;
            $currentYear = strval((new DateTime())->format('Y'));
            $previousYear = strval(intval($currentYear)-1);
            $listId = self::LIST_ID;
            $choiceListId = self::CHOICELIST_ID;
            $template = <<<TXT
            texte***bf_titre***Nom*** *** *** *** ***text***1*** *** *** * *** * *** *** *** ***
            texte***bf_prenom***Prénom*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
            texte***bf_nom***Nom*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
            texte***bf_adresse***Adresse*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
            texte***bf_code_postal***Code postal*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
            texte***bf_ville***Ville*** *** *** *** ***text***0*** *** *** * *** * *** *** *** ***
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
            calc***bf_don_a_payer***Don brut*** ***{value} €***(bf_montant_don_ponctuel_libre + abs(bf_montant_don_ponctuel_libre))/2*** *** *** *** *** *** * *** *** *** *** ***
            calc***bf_calc***Reste à payer*** ***{value} €***bf_adhesion_a_payer+bf_adhesion_groupe_a_payer+bf_don_a_payer*** *** *** *** *** *** * *** *** *** *** ***
            payments***bf_payments***Liste des paiements*** *** *** *** *** *** *** *** *** *** *** *** *** ***
            TXT;
            $formManager->create([
                'bn_id_nature' => $currentFormId,
                'bn_label_nature' => $name,
                'bn_template' => $template,
                'bn_description' => 'template de test',
                'bn_sem_context' => false,
                'bn_sem_type' => '',
                'bn_condition' => '',
            ]);
        } elseif (!$install && !empty($form) && !empty($id)){
            $formManager->delete($id);
            $currentFormId = '';
        }
        return $currentFormId;
    }

    /**
     * act as admin
     * @param callable $callback
     * @param Wiki $wiki
     */
    protected static function actAsAdmin($callback,Wiki $wiki)
    {
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
     * @param Wiki $wiki
     * @param string $currentFormId
     */
    public static function updateEntry(bool $install, array $data, Wiki $wiki,string $currentFormId)
    {
        $GLOBALS['wiki'] = $wiki;
        $GLOBALS['_BAZAR_'] = []; // reset cache

        $entryManager = $wiki->services->get(EntryManager::class);

        $id = self::ENTRY_ID;
        $entry = $entryManager->getOne($id, false, null, false, true); // no cache
        if ($install && empty($entry)){
            if (!empty($currentFormId)){
                $entryManager->create(
                    $currentFormId,
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
            },$wiki);
        }
    }
}
