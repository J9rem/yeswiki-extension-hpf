<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-import-payments
 */

namespace YesWiki\Hpf\Controller;

use Exception;
use Throwable;
use YesWiki\Alternativeupdatej9rem\Field\CustomSendMailField;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\CheckboxField;
use YesWiki\Bazar\Field\EmailField;
use YesWiki\Bazar\Field\EnumField;
use YesWiki\Bazar\Field\RadioListField;
use YesWiki\Bazar\Field\SelectEntryField;
use YesWiki\Bazar\Field\SelectListField;
use YesWiki\Bazar\Field\TextField;
use YesWiki\Bazar\Field\TitleField;
use YesWiki\Bazar\Field\UserField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\Service\EventDispatcher;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Hpf\Service\AreaManager;
use YesWiki\Hpf\Service\HpfService;
use YesWiki\Wiki;

class HpfImportController extends YesWikiController
{
    protected $areaManager;
    protected $csrfTokenController;
    protected $entryManager;
    protected $eventDispatcher;
    protected $formManager;
    protected $hpfService;
    protected $userManager;

    public function __construct(
        AreaManager $areaManager,
        CsrfTokenController $csrfTokenController,
        EntryManager $entryManager,
        EventDispatcher $eventDispatcher,
        FormManager $formManager,
        HpfService $hpfService,
        UserManager $userManager,
        Wiki $wiki
    ) {
        $this->areaManager = $areaManager;
        $this->csrfTokenController = $csrfTokenController;
        $this->entryManager = $entryManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->formManager = $formManager;
        $this->hpfService = $hpfService;
        $this->userManager = $userManager;
        $this->wiki = $wiki;
    }
    
    /**
     * create antry or append
     * @param string $mode
     * @param string $type
     * @param string $formId
     * @return ApiResponse
     * @throws Exception
     */
    public function createEntryOrAppendPaymentForMemberShip(string $mode,string $type,string $formId): ApiResponse
    {
        $this->csrfTokenController->checkToken('hpf-import', 'POST', 'anti-csrf-token',false);

        if(empty($_POST['data'])
            || !is_array($_POST['data'])) {
            throw new Exception("\"\$_POST['data']\" should be an array !");
        }

        if (!in_array($mode,['createEntry','appendPayment'],true)){
            throw new Exception("Mode \"$mode\" is not supported");
        }
        $appendMode = ($mode === 'appendPayment');

        if (!in_array($type,['college1','college2'],true)){
            throw new Exception("Mode \"$type\" is not supported");
        }
        $isGroup = ($type === 'college2');

        if (empty($formId)){
            throw new Exception("\"$formId\" should not be empty");
        }

        $form = $this->formManager->getOne($formId);
        if (empty($form['prepared'])){
            throw new Exception("form not found");
        }

        $data = $_POST['data'];
        if (!$appendMode){
            try {
                $newEntry = $this->addEntryIfPossible($data,$form,$isGroup);
                if (empty($newEntry) || !is_array($newEntry)){
                    throw new Exception("entry not created");
                }
                $data['associatedEntryId'] = $newEntry['id_fiche'];
            } catch (Throwable $th) {
                return new ApiResponse(
                    ['error' => $th->getMessage()],
                    400
                );
            }
        }
        
        $updatedEntry = $this->appendPaymentIfPossible($data,$form);
        if (empty($updatedEntry) || !is_array($updatedEntry)){
            if ($appendMode){
                throw new Exception("entry not updated");
            } else {
                $updatedEntry = $newEntry ?? [];
            }
        }
        return new ApiResponse(
            $updatedEntry,
            200
        );
    }

    /**
     * add Entry
     * @param array $data
     * @param array $form
     * @param bool $isGroup
     * @return array $entry
     * @throws Exception
     */
    protected function addEntryIfPossible(array $data, array $form, bool $isGroup): array
    {
        if (empty($data['email'])){
            throw new Exception("\$data['email'] should not be empty !");
        }
        if (!is_string($data['email'])){
            throw new Exception("\$data['email'] should be a string !");
        }
        if (!$this->canAddEntryInForm($form,$data['email'])){
            throw new Exception("An entry already exists for email '{$data['email']}'");
        }
        
        // clean $_POST and $_REQUEST
        $_POST = [];
        $_REQUEST = [];

        // set antispam
        $entry = [
            'antispam' => 1,
            'bf_titre' => ''
        ];

        $params = [
            [ //title
                function ($field) {return $field instanceof TitleField;},
                function ($field) {return $field->getName();},
            ],
            [ //email
                function ($field) {return $field instanceof EmailField;},
                $data['email'],
            ],
        ];


        // extract some data
        $memberShipValueType =
            (
                !empty($data['membershipType'])
                && in_array(
                    $data['membershipType'],
                    $isGroup 
                        ? ['standard','soutien','libre','ajuste']
                        : ['standard','soutien','libre'],
                    true)
            )
            ? $data['membershipType']
            : 'libre' ;
        $value = strval($data['value'] ?? 0);
        $postalcode = strval($data['postalcode'] ?? '');
        $town = strval($data['town'] ?? '');
            
        if (!empty($postalcode)){
            $deptcode = $this->areaManager->extractAreaFromPostalCode([
                $this->areaManager->getPostalCodeFieldName() => $postalcode
            ]);
            if (!empty($deptcode)){
                $associations = $this->areaManager->getAssociations();
                $areacode = $associations['depts'][$deptcode][0] ?? '';
            }
        }

        if ($isGroup){

            // extract some other data
            $groupName = strval($data['groupName'] ?? '');
            if (empty($groupName)){
                throw new Exception('groupName should not be empty !');
            }

        } else {

            // extract some other data
            $firstName = strval($data['firstname'] ?? '');
            $name = strval($data['name'] ?? '');
            if (empty($name)){
                throw new Exception('Name should not be empty !');
            }

            // membership
            $params[] = [
                function ($field) {return $field instanceof CheckboxField
                    && $field->getName() === 'bf_type_contributeur_copy';},
                'adhesion',
            ];
        }

        // membership type
        $params[] = [
            function ($field) use($isGroup) {return $field instanceof EnumField
                && $field->getName() === ($isGroup ? 'bf_type_adhesion_groupe' : 'bf_type_adhesion');},
            'territoriale',
        ];
                
        // membership value type
        $params[] = [
            function ($field) use($isGroup) {return $field instanceof SelectListField
                && $field->getName() === ($isGroup ? 'bf_montant_adhesion_college_2' : 'bf_montant_adhesion_college_1');},
            $memberShipValueType,
        ];
            
        if ($memberShipValueType === 'libre'){
            $params[] = [
                function ($field) use($isGroup) {return $field instanceof TextField
                    && $field->getName() === ($isGroup ? 'bf_montant_adhesion_college_2_libre' : 'bf_montant_adhesion_college_1_libre');},
                $value,
            ];
        }
            
        if ($isGroup && $memberShipValueType === 'ajuste'){
            $params[] = [
                function ($field) {return $field instanceof TextField
                    && $field->getName() === 'bf_montant_adhesion_college_2_ajuste';},
                $value,
            ];
        }

        // no donation by default
        $params[] = [
            function ($field) use($isGroup) {return $field instanceof RadioListField
                && $field->getLinkedObjectName() === 'ListeOuinon'
                && $field->getName() == ($isGroup ? 'bf_don_complementaire_groupe' : 'bf_don_complementaire');},
            'non',
        ];


        // area
        if (!empty($areacode)){
            $params[] = [
                function ($field) use($isGroup) {return $field instanceof SelectListField
                    && $field->getName() === ($isGroup ? 'bf_region_adhesion_groupe' :'bf_region_adhesion');},
                $areacode,
            ];
            // structure
            $params[] = [
                function ($field) use($isGroup) {return $field instanceof SelectEntryField
                    && $field->getName() === ($isGroup ? 'bf_structure_regionale_adhesion_groupe' :'bf_structure_regionale_adhesion');},
                function ($field) use($areacode){
                    $options = $field->getOptions();
                    $entries = array_map(
                        function($entryId){
                            return $this->entryManager->getOne($entryId);
                        },
                        array_keys($options)
                    );
                    $entries = array_filter(
                        $entries,
                        function($e) use ($areacode){
                            return !empty($e['checkboxListeRegionsFrancaisesApresNouveauDecoupabf_region'])
                                && in_array($areacode,explode(',',$e['checkboxListeRegionsFrancaisesApresNouveauDecoupabf_region']));
                        }
                    );
                    if (empty($entries) || count($entries) > 1) {
                        return '';
                    }
                    $e = array_pop($entries);
                    return $e['id_fiche'];
                },
            ];
            if (!empty($deptcode)){
                $params[] = [
                    function ($field) use($isGroup) {return $field instanceof SelectListField
                        && $field->getName() === ($isGroup ? 'bf_departement_adhesion_groupe' :'bf_departement_adhesion');},
                    $deptcode,
                ];
                
                // structure
                $params[] = [
                    function ($field) use($isGroup) {return $field instanceof SelectEntryField
                        && $field->getName() === ($isGroup ? 'bf_structure_locale_adhesion_groupe' :'bf_structure_locale_adhesion');},
                    function ($field) use($deptcode){
                        $options = $field->getOptions();
                        $entries = array_map(
                            function($entryId){
                                return $this->entryManager->getOne($entryId);
                            },
                            array_keys($options)
                        );
                        $entries = array_filter(
                            $entries,
                            function($e) use ($deptcode){
                                return !empty($e['checkboxListeDepartementsFrancais'])
                                    && in_array($deptcode,explode(',',$e['checkboxListeDepartementsFrancais']));
                            }
                        );
                        if (empty($entries) || count($entries) > 1) {
                            return '';
                        }
                        $e = array_pop($entries);
                        return $e['id_fiche'];
                    },
                ];
            }
        }
            
        // type of payment
        $params[] = [
            function ($field) {return $field instanceof RadioListField
                && $field->getLinkedObjectName() === 'ListeMoyenDePaiement';},
            'cheque',
        ];

        if ($isGroup){
            // name
            if (!empty($groupName)){
                $params[] = [
                    function ($field) {return $field instanceof TextField
                        && $field->getName() === 'bf_nom';},
                    $groupName,
                ];
            }

        } else {
            // first name
            if (!empty($firstName)){
                $params[] = [
                    function ($field) {return $field instanceof TextField
                        && $field->getName() === 'bf_prenom';},
                    $firstName,
                ];
            }
            // name
            if (!empty($name)){
                $params[] = [
                    function ($field) {return $field instanceof TextField
                        && $field->getName() === 'bf_nom';},
                    $name,
                ];
            }
        }
        // custom sendmail
        $params[] = [
            function ($field) {return $field instanceof CustomSendMailField;},
            'yes'
        ];
        $params[] = [
            function ($field) {return $field instanceof CustomSendMailField;},
            'no',
            function ($field) {
                return $field->getPropertyName().'_option';
            }
        ];
        // nom wiki
        $entry['nom_wiki_force_label'] = 1;
        $randomPwd = $this->wiki->generateRandomString(30);
        $entry['mot_de_passe_wikini'] = $randomPwd;
        $entry['mot_de_passe_repete_wikini'] = $randomPwd;

        // postal code
        $params[] = [
            function ($field) {return $field instanceof TextField
                && $field->getName() === 'bf_code_postal';},
            $postalcode
        ];
        // town
        $params[] = [
            function ($field) {return $field instanceof TextField
                && $field->getName() === 'bf_ville';},
            $town
        ];
        // newsletter
        $params[] = [
            function ($field) {return $field instanceof SelectListField
                && $field->getLinkedObjectName() === 'ListeListeAccordLettreDInfo';},
            'non',
        ];

            
        // manage area from postal code
        $this->appendConcernedFieldsInData($entry,$form,$params);

        // check title to force save
        if (empty($entry['bf_titre'])){
            $entry['bf_titre'] = $data['name'];
        }

        // update post and request

        foreach ($entry as $key => $value) {
            $_POST[$key] = $value;
            $_REQUEST[$key] = $value;
        }

        // create entry
        $createdEntry = $this->entryManager->create($form['bn_id_nature'], $entry);
        $this->eventDispatcher->yesWikiDispatch('entry.created', [
            'id' => $createdEntry['id_fiche'],
            'data' => $createdEntry
        ]);

        return (empty($createdEntry) || !is_array($createdEntry)) ? [] : $createdEntry;
    }

    /**
     * check if can add entry
     * inspired from EntryController::checkIfOnlyOneEntry
     * Feature UUID : hpf-import-payments
     * @param array $form
     * @param string $email
     * @return bool
     * @throws Exception
     */
    protected function canAddEntryInForm(array $form,string $email): bool
    {
        if (!isset($form['bn_only_one_entry']) || $form['bn_only_one_entry'] !== "Y") {
            return true;
        }

        $user = $this->userManager->getOneByEmail($email);

        if (empty($user['name']) || empty($user['email'])){
            return true;
        }

        $emailFields = array_filter($form['prepared'], function ($field) {
            return $field instanceof EmailField;
        });

        if (empty($emailFields)){
            throw new Exception("Email field not found");
        }
        $firstEmailField = array_shift($emailFields);
        if (empty($firstEmailField->getPropertyName())){
            throw new Exception("Email field has not property name");
        }
        
        $entries = $this->entryManager->search([
            'formsIds' => [$form['bn_id_nature']],
            'queries' => [
                $firstEmailField->getPropertyName() => $user['email']
            ]
        ]);

        return empty($entries);
    }

    /**
     * append concerned fields in data
     * @param array &$data
     * @param array $form
     * @param array $params [[callable $criterion,callable|scalar $getValue,null|callable $getPropNamee]]
     */
    protected function appendConcernedFieldsInData(array &$data, array $form,array $params)
    {
        $fields = $this->findFieldsInForm($form,$params);
        foreach ($fields as $key => $field) {
            if (!empty($field)){
                $propName = (!empty($params[$key][2]) && is_callable($params[$key][2]))
                    ? ($params[$key][2])($field)
                    : $field->getPropertyName();
                if (!empty($propName)){
                    $getValue = $params[$key][1] ?? '';
                    $data[$propName] = is_callable($getValue) ? $getValue($field) : (
                        is_scalar($getValue)
                        ? $getValue
                        : ''
                    );
                }
            }
        }
    }

    /**
     * find concerned fields in form
     * @param array $form
     * @param array $params [[callable $criterion,$value]]
     * @return array [null|BazarField]
     */
    protected function findFieldsInForm(array $form,array $params): array
    {
        // init fields
        $fields = array_map(
            function($data){
                return (empty($data[0]) || !is_callable($data[0])) ? null : [];
            },
            $params
        );
        // sweep on fields
        foreach ($form['prepared'] as $field) {
            if ($field instanceof BazarField){
                foreach($fields as $key => $result){
                    if (
                            !is_null($result)
                            && empty($result)
                            && ($params[$key][0])($field)
                        ){
                        $result = $field;
                        $fields[$key] = $field;
                    }
                }
            }
        }
        // return results
        return array_map(
            function($f){
                return empty($f) ? null : $f;
            },
            $fields
        );
    }
    
    /**
     * append payment if possible
     * @param array $data
     * @param array $form
     * @return array $entry
     * @throws Exception
     */
    protected function appendPaymentIfPossible(array $data, array $form): array
    {
        if (empty($data['associatedEntryId']) || !is_string($data['associatedEntryId'])){
            throw new Exception('"$_POST[\'data\'][\'associatedEntryId\']" should not be empty and should be a string!');
        }
        // get entry
        $entry = $this->entryManager->getOne($data['associatedEntryId'],false,null,false); // no cache
        if (empty($entry)){
            throw new Exception('"associatedEntryId" does not correspond to an existing entry !');
        }
        // check if payment is existing
        if (empty($data['number']) || !is_scalar($data['number'])){
            throw new Exception('"number" should be a not empty string or number !');
        }
        $proposedPaymentId = trim(strval($data['number']));
        if (empty($proposedPaymentId)){
            throw new Exception('"number" should be a not empty string or number !');
        }
        // append payment
        $paymentContent = $entry[HpfService::PAYMENTS_FIELDNAME] ?? '';
        if (!empty($paymentContent)){
            $currentPayments = $this->hpfService->convertStringToPayments($paymentContent);
            if (array_key_exists($proposedPaymentId,$currentPayments)){
                throw new Exception('Payment already defined !');
            }
        }
        foreach (['date','value','year'] as $key) {
            if (empty($data[$key]) || !is_string($data[$key])){
                throw new Exception("\"$key\" should be a not empty string !");
            }
        }
        $date = $data['date'];
        $match = [];
        if (preg_match('/([0-9]{2})\\/([0-9]{2})\\/([0-9]{4})/',$date,$match)){
            $date = "{$match[3]}{$match[2]}{$match[1]}";
        }

        list('updatedEntry' => $updatedEntry) = $this->hpfService->addPaymentInEntry(
            $data['associatedEntryId'],
            $date,
            $data['value'],
            'structure',
            $data['number'],
            $data['year']
        );
        
        return $updatedEntry;
    }
}