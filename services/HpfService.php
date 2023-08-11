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

use Configuration;
use DateTime;
use DateInterval;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\CalcField;
use YesWiki\Bazar\Field\CheckboxField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\AssetsManager;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Hpf\Field\PaymentsField;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Shop\Entity\Payment;
use YesWiki\Shop\Entity\Event;
use YesWiki\Shop\Entity\HelloAssoPayments;
use YesWiki\Shop\Service\EventDispatcher;
use YesWiki\Shop\Service\HelloAssoService;
use YesWiki\Wiki;

class HpfService
{
    public const PAYMENTS_FIELDNAME = "bf_payments";
    public const TYPE_CONTRIB = [
        'fieldName' => "bf_type_contributeur",
        'keys' => [
            "membership" => "adhesion",
            "group_membership" => "adhesion_groupe",
            "donation" => "don",
        ],
    ];
    public const PAYED_FIELDNAMES = [
        "membership" => "bf_adhesion_payee_{year}",
        "group_membership" => "bf_adhesion_groupe_payee_{year}",
        "donation" => "bf_dons_payes_{year}",
        "years" => [
            "membership" => "bf_annees_adhesions_payees",
            "group_membership" => "bf_annees_adhesions_groupe_payees",
            "donation" => "bf_annees_dons_payes",
        ]
    ];
    public const CALC_FIELDNAMES = [
        "membership" => "bf_adhesion_a_payer",
        "group_membership" => "bf_adhesion_groupe_a_payer",
        "donation" => "bf_don_a_payer",
        "total" => "bf_calc"
    ];

    public const HELLOASSO_HPF_PROPERTY = 'https://www.habitatparticipatif-france.fr/HelloAssoLog';
    public const HELLOASSO_API_PROPERTY = 'https://www.habitatparticipatif-france.fr/HelloAssoApiLog';

    protected $aclService;
    protected $assetsManager;
    protected $dbService;
    protected $debug;
    protected $entryManager;
    protected $formManager;
    protected $helloAssoService;
    protected $hpfParams;
    protected $pageManager;
    protected $params;
    protected $paymentForm;
    protected $securityController;
    protected $tripleStore;
    protected $userManager;
    protected $wiki;

    public function __construct(
        AclService $aclService,
        AssetsManager $assetsManager,
        DbService $dbService,
        EntryManager $entryManager,
        FormManager $formManager,
        HelloAssoService $helloAssoService,
        PageManager $pageManager,
        ParameterBagInterface $params,
        TripleStore $tripleStore,
        SecurityController $securityController,
        UserManager $userManager,
        Wiki $wiki
    ) {
        $this->aclService = $aclService;
        $this->assetsManager = $assetsManager;
        $this->dbService = $dbService;
        $this->debug = null;
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->helloAssoService = $helloAssoService;
        $this->hpfParams = null;
        $this->pageManager = $pageManager;
        $this->params = $params;
        $this->paymentForm = null;
        $this->securityController = $securityController;
        $this->tripleStore = $tripleStore;
        $this->userManager = $userManager;
        $this->wiki = $wiki;
        if ($this->wiki->services->has(EventDispatcher::class)) {
            $eventDispatcher = $this->wiki->services->get(EventDispatcher::class);
            $eventDispatcher->addListener('shop.helloasso.api.called', [$this,'processTrigger']);
        }
    }

    /**
     * get the contribution entry for selected user
     * @param string $formId
     * @param string $email
     * @param string $preferedEntryId
     * @param string $preferedUserName
     * @return array $entries
     * @throws Exception
     */
    public function getCurrentContribEntries(string $formId, string $email = "", string $preferedEntryId = "", string $preferedUserName = ''): array
    {
        try {
            if (!empty($email)) {
                $contribFormIds = $this->getCurrentPaymentsFormIds();
                if (!in_array($formId, $contribFormIds)) {
                    throw new Exception("formId should be in \$contribFormIds!");
                }
                $form = $this->formManager->getOne($formId);
                if (empty($form)) {
                    throw new Exception("hpf['formId'] do not correspond to an existing form!");
                }
                // delete cache
                $GLOBALS['_BAZAR_'] = array_filter(
                    $GLOBALS['_BAZAR_'] ?? [],
                    function($k){
                        return substr($k,0,strlen('bazar-search-')) !== 'bazar-search-';
                    },
                    ARRAY_FILTER_USE_KEY
                );
                $entries = $this->entryManager->search([
                    'formsIds' => [$form['bn_id_nature']],
                    'queries' => [
                        'bf_mail' => $email
                    ]
                ]);
                if (empty($entries)) {
                    return [];
                } else {
                    $sameIds = empty($preferedEntryId)
                        ? []
                        : array_filter($entries,function($entry) use ($preferedEntryId){
                        return !empty($entry['id_fiche']) && $entry['id_fiche'] == $preferedEntryId;
                    });
                    $sameOwner = (empty($sameIds) && !empty($preferedUserName))
                        ? array_filter($entries,function($entry) use ($preferedUserName){
                            return !empty($entry['owner']) && $entry['owner'] == $preferedUserName;
                        })
                        : [];
                    $ids = !empty($sameIds)
                        ? $sameIds
                        : (
                            !empty($sameOwner)
                            ? $sameOwner
                            : $entries
                        );
                    if (!empty($ids) && $this->wiki->UserIsAdmin()){
                        $ids = [$ids[array_key_first($ids)]];
                    }
                    return array_map(function($id){
                        return $this->entryManager->getOne($id['id_fiche'], false, null, false, true);
                    },$ids);
                }
            }
        } catch (Throwable $th) {
            if ($this->isDebug() && $this->wiki->UserIsAdmin()) {
                throw $th;
            }
        }
        return [];
    }

    public function getCurrentPaymentsFormIds(): array
    {
        $this->getHpfParams();
        if (empty($this->hpfParams['contribFormIds'])) {
            throw new Exception("hpf['contribFormIds'] param not defined");
        }
        if (!is_scalar($this->hpfParams['contribFormIds'])) {
            throw new Exception("hpf['contribFormIds'] param should be string");
        }
        $formIds = explode(',', strval($this->hpfParams['contribFormIds']));
        foreach ($formIds as $id) {
            if (strval($id) != strval(intval($id))) {
                throw new Exception("hpf['contribFormIds'] param should be numbers separated by coma");
            }
        }

        return $formIds;
    }

    /**
     * search CalcFields in $contribForm (filtered on $names optionnally)
     * @param string $formId
     * @param array $names
     * @return CalcField[] $fields
     */
    public function getContribCalcFields(string $formId, array $names = []): array
    {
        $contribFormIds = $this->getCurrentPaymentsFormIds();
        if (!in_array($formId, $contribFormIds)) {
            return [];
        }
        $form = $this->formManager->getOne($formId);
        if (empty($form['prepared'])) {
            throw new Exception("\$form['prepared'] should not be empty in getContribCalcFields!");
        }
        $fields = [];
        if (empty($names)) {
            $fields = [];
            foreach ($form['prepared'] as $field) {
                if ($field instanceof CalcField) {
                    $fields[] = $field;
                }
            }
        } else {
            foreach ($names as $name) {
                $field = $this->formManager->findFieldFromNameOrPropertyName($name, $formId);
                if (!empty($field) && $field instanceof CalcField) {
                    $fields[] = $field;
                }
            }
        }
        if (empty($fields)) {
            throw new Exception("No CalcField found in \$form['prepared'] (form {$form['bn_label_nature']} - {$form['bn_id_nature']})!");
        }
        return $fields;
    }

    /**
     * update CalcFields in $entry
     * @param array $entry
     * @param array $names
     * @return array $entry
     */
    public function updateCalcFields(array $entry, array $names = []): array
    {
        $fields = $this->getContribCalcFields($entry['id_typeannonce'], $names);
        foreach ($fields as $field) {
            $newCalcValue = $field->formatValuesBeforeSave($entry);
            $entry[$field->getPropertyName()] = $newCalcValue[$field->getPropertyName()] ?? "";
        }
        return $entry;
    }

    public function getHpfParams(): array
    {
        if (is_null($this->hpfParams)) {
            if ($this->params->has('hpf')) {
                $this->hpfParams = $this->params->get('hpf');
            } else {
                throw new Exception("hpf param not defined");
            }
        }
        return $this->hpfParams;
    }

    protected function isDebug(): bool
    {
        if (is_null($this->debug)) {
            $this->debug = ($this->wiki->GetConfigValue('debug') =='yes');
        }
        return $this->debug;
    }

    public function getPaymentFormUrl(string $formId = ""): string
    {
        $this->getHpfParams();
        if (empty($this->hpfParams['paymentsFormUrls'])) {
            throw new Exception("hpf['paymentsFormUrls'] param not defined");
        }
        $urls = [];
        foreach (explode(',', $this->hpfParams['paymentsFormUrls']) as $idx => $url) {
            if (substr($url, 0, strlen('https://www.helloasso.com')) != 'https://www.helloasso.com') {
                throw new Exception("hpf['paymentsFormUrls'] should begin by 'https://www.helloasso.com'");
            }
            $url = preg_replace("/\/(widget|widget-button)$/", "", $url);
            if (substr($url, -1) != '/') {
                $url .= "/";
            }
            $urls[] = $url;
        }
        $formsIds = $this->getCurrentPaymentsFormIds();
        if (!empty($formId)) {
            foreach ($formsIds as $idx => $formIdToCompare) {
                if ($formIdToCompare == $formId && isset($urls[$idx])) {
                    return $urls[$idx];
                }
            }
        }
        return $urls[0];
    }

    public function getPaymentFormButtonHtml(string $formId = ""): string
    {
        $url = $this->getPaymentFormUrl($formId);
        return "<iframe id=\"haWidgetButton\" src=\"{$url}widget-bouton\" style=\"border: none;\"></iframe>";
    }

    public function getPaymentFormIframeHtml(string $formId = ""): string
    {
        $url = $this->getPaymentFormUrl($formId);
        return "<iframe id=\"haWidget\" src=\"{$url}widget\" style=\"width: 100%; height: 800px; border: none;\" scrolling=\"auto\"></iframe>";
    }

    public function refreshPaymentsInfo(string $formId, string $email = "", string $preferedEntryId = '', ?HelloAssoPayments $payments = null)
    {
        $form = $this->getPaymentForm($formId);
        try {
            $payments = !empty($payments)
                ? $payments
                :$this->helloAssoService->getPayments([
                    'email' => $email,
                    'formType' => $form['formType'],
                    'formSlug' => $form['formSlug']
                ]);
        } catch (Throwable $th) {
            // do nothing (remove warnings)
            if ($this->isDebug() && $this->wiki->UserIsAdmin()) {
                throw $th;
            } else {
                try {
                    $pageTag = 'HelloAssoApiLog';
                    $this->tripleStore->create($pageTag,self::HELLOASSO_API_PROPERTY,json_encode([
                        'date' => (new DateTime())->format("Y-m-d H:i:s.v"),
                        'account' => (empty($_SESSION['user']['name']) || !is_string($_SESSION['user']['name'])) ? '' : $_SESSION['user']['name'],
                        'throwableToString' => "Exception (code {$th->getCode()}): {$th->getMessage()} in ".basename($th->getFile()).":{$th->getLine()}"
                    ]),'','');
                } catch (Throwable $th) {
                }
            }

            $payments = null;
        }
        if (!empty($payments)) {
            $this->checkContribFormsHavePaymentsField();

            $cacheEntries = [];

            foreach ($payments as $payment) {
                // open entry based on email from payment
                $paymentEmail = $payment->payer->email;
                if (!isset($cacheEntries[$paymentEmail])) {
                    $cacheEntries[$paymentEmail] = [];
                }
                if (!isset($cacheEntries[$paymentEmail]['entry'])) {
                    $entries = $this->getCurrentContribEntries($formId, $paymentEmail,$preferedEntryId);
                    if (!empty($entries)) {
                        $cacheEntries[$paymentEmail]['entry'] = $entries[array_key_first($entries)];
                        $cacheEntries[$paymentEmail]['previousTotal'] = $cacheEntries[$paymentEmail]['entry'][self::CALC_FIELDNAMES['total']] ?? "";
                        $cacheEntries[$paymentEmail]['previousPayments'] = $cacheEntries[$paymentEmail]['entry'][self::PAYMENTS_FIELDNAME] ?? "";
                    }
                }
                if (!empty($cacheEntries[$paymentEmail]['entry'])) {
                    // check if payments are saved
                    if (!$this->isAlreadyRegisteredPayment($cacheEntries[$paymentEmail]['entry'], $payment)) {
                        $cacheEntries[$paymentEmail]['entry'] = $this->updateEntryWithPayment($cacheEntries[$paymentEmail]['entry'], $payment);
                    }
                }
            }

            foreach ($cacheEntries as $data) {
                if (!empty($data) && ($data['previousTotal'] != $data['entry'][self::CALC_FIELDNAMES['total']] ||
                    $data['previousPayments'] != $data['entry'][self::PAYMENTS_FIELDNAME])) {
                    $this->updateEntry($data['entry']);
                }
            }
        }
    }

    public function getPaymentForm(string $formId): array
    {
        if (is_null($this->paymentForm)) {
            $this->paymentForm = [];
        }

        if (!isset($this->paymentForm[$formId])) {
            $this->getHpfParams();
            $formUrl = $this->getPaymentFormUrl($formId);
            if (!empty($this->hpfParams['paymentForm'])
                && isset($this->hpfParams['paymentForm'][$formUrl])
                && is_array($this->hpfParams['paymentForm'][$formUrl])) {
                $this->paymentForm[$formId] = array_merge($this->hpfParams['paymentForm'][$formUrl], ['url' => substr($formUrl, 0, -1)]);
            } else {
                $forms = $this->helloAssoService->getForms();
                $form = array_filter($forms, function ($formData) use ($formUrl) {
                    return ($formData['url']."/") == $formUrl;
                });
                if (empty($form)) {
                    throw new Exception("PaymentForm not found with its urls on api !");
                }
                $this->paymentForm[$formId] = $form[array_key_first($form)];
                $this->saveFormDaraInParams([
                    $formUrl => [
                        'title' => $this->paymentForm[$formId]['title'],
                        'formType' => $this->paymentForm[$formId]['formType'],
                        'formSlug' => $this->paymentForm[$formId]['formSlug'],
                    ]
                ]);
            }
        }

        return $this->paymentForm[$formId];
    }

    private function saveFormDaraInParams(array $data)
    {
        include_once 'tools/templates/libs/Configuration.php';
        $config = new Configuration('wakka.config.php');
        $config->load();

        $baseKey = 'hpf';
        $tmp = isset($config->$baseKey) ? $config->$baseKey : [];
        if (empty($tmp['paymentForm']) || !is_array($tmp['paymentForm'])) {
            $tmp['paymentForm'] = $data;
        } else {
            $tmp['paymentForm'] = array_merge($tmp['paymentForm'], $data);
        }
        $config->$baseKey = $tmp;
        $config->write();
        unset($config);
    }

    private function checkContribFormsHavePaymentsField()
    {
        $contribFormIds = $this->getCurrentPaymentsFormIds();
        foreach ($contribFormIds as $contribFormId) {
            $paymentField = $this->formManager->findFieldFromNameOrPropertyName(self::PAYMENTS_FIELDNAME, $contribFormId);
            if (is_null($paymentField)) {
                $form = $this->formManager->getOne($contribFormId);
                if (!$this->wiki->UserIsAdmin()) {
                    throw new Exception(self::PAYMENTS_FIELDNAME." is not defined in form {$form['bn_label_nature']} ({$form['bn_id_nature']})");
                }
                if (empty($form['bn_template'])) {
                    throw new Exception("\$form['bn_template'] is not defined in form {$form['bn_label_nature']} ({$form['bn_id_nature']})");
                }
                // add field
                $formTemplate = $form['bn_template'];
                if (substr($formTemplate, -strlen("\n")) != "\n") {
                    $formTemplate .= "\n";
                }
                $formTemplate .= "payments***".self::PAYMENTS_FIELDNAME."***Liste des paiements*** *** *** *** *** *** *** *** ***@admins***@admins*** *** *** ***\n";
    
                $newForm = $form;
                $newForm['bn_template'] = $formTemplate;
                $this->formManager->update($newForm);
            } elseif (!($paymentField instanceof PaymentsField)) {
                throw new Exception(self::PAYMENTS_FIELDNAME." is not a PaymentField in form ({$contribFormId})");
            }
        }
    }

    /**
     * refresh entry from HelloAsso then reload entry and gives calcValue
     * @param array $entry
     * @param string $email
     * @return array $entry
     */
    public function refreshEntryFromHelloAsso(array $entry, string $email)
    {
        // refresh payments from HelloASso
        $this->refreshPaymentsInfo($entry['id_typeannonce'], $email, $entry['id_fiche'] ?? '');

        // reload entry
        $entries = $this->getCurrentContribEntries($entry['id_typeannonce'], $email, $entry['id_fiche'] ?? '');

        return !empty($entries) ? $entries[array_key_first($entries)] : [];
    }

    /* === THE MOST IMPORTANT FUNCTION === */
    /**
     * update entry with payments info
     * @param array $entry
     * @param Payment $payment
     * @return array $updatedEntry
     */
    public function updateEntryWithPayment(array $entry, Payment $payment):array
    {
        $contribFormIds = $this->getCurrentPaymentsFormIds();
        if (!in_array($entry['id_typeannonce'], $contribFormIds)) {
            return $entry;
        }
        $contribFormId = $entry['id_typeannonce'];
        // $typeContribField = $this->formManager->findFieldFromNameOrPropertyName(self::TYPE_CONTRIB['fieldName'], $contribFormId);
        // if (empty($typeContribField)) {
        //     throw new Exception(self::TYPE_CONTRIB['fieldName']." is not defined in form {$contribFormId}");
        // }
        // if (!($typeContribField instanceof CheckboxField)) {
        //     throw new Exception(self::TYPE_CONTRIB['fieldName']." is not an instance of CheckboxField in form {$contribFormId}");
        // }
        // $contribTypes = $typeContribField->getValues($entry);
        // $isMember = in_array(self::TYPE_CONTRIB['keys']['membership'], $contribTypes);
        // $isGroupMember = in_array(self::TYPE_CONTRIB['keys']['group_membership'], $contribTypes);

        // get Year
        $paymentDate = new DateTime($payment->date);
        $paymentYear = $paymentDate->format("Y");
        $currentYear = (new DateTime())->format("Y");

        $restToAffect =  floatval($payment->amount);
        $paymentParams = 
        [
            'payment' => $payment,
            'origin' => "helloasso:$contribFormId"
        ];
        if (intval($paymentYear) > intval($currentYear)) {
            // error
            return $entry;
        } elseif (intval($paymentYear) == intval($currentYear)) {
            list('isOpenedNextYear' => $isOpenedNextYear, 'field' => $field) = $this->getPayedField($contribFormId, $currentYear, "membership");
            if ($isOpenedNextYear) {
                // membership for next year
                list('entry' => $entry, 'restToAffect' => $restToAffect, 'affected'=>$affected) =
                    $this->registerPaymentForYear($entry, $contribFormId, $field, $restToAffect, strval(intval($paymentYear)+1), "membership");
                if (!empty($affected)){
                    $paymentParams['annee_adhesion'] = strval(intval($paymentYear)+1);
                    $paymentParams['valeur_adhesion'] = strval($affected);
                }
            } else {
                // membership for current year
                list('entry' => $entry, 'restToAffect' => $restToAffect,'affected'=>$affected) =
                    $this->registerPaymentForYear($entry, $contribFormId, $field, $restToAffect, $paymentYear, "membership");
                if (!empty($affected)){
                    $paymentParams['annee_adhesion'] = strval($paymentYear);
                    $paymentParams['valeur_adhesion'] = strval($affected);
                }
        }
            if ($restToAffect > 0) {
                list('isOpenedNextYear' => $isOpenedNextYear, 'field' => $field) = $this->getPayedField($contribFormId, $currentYear, "group_membership");
                if ($isOpenedNextYear) {
                    // membership for next year
                    list('entry' => $entry, 'restToAffect' => $restToAffect,'affected'=>$affected) =
                        $this->registerPaymentForYear($entry, $contribFormId, $field, $restToAffect, strval(intval($paymentYear)+1), "group_membership");
                    if (!empty($affected)){
                        $paymentParams['annee_adhesion_groupe'] = strval(intval($paymentYear)+1);
                        $paymentParams['valeur_adhesion_groupe'] = strval($affected);
                    }
                } else {
                    // membership for current year
                    list('entry' => $entry, 'restToAffect' => $restToAffect,'affected'=>$affected) =
                        $this->registerPaymentForYear($entry, $contribFormId, $field, $restToAffect, $paymentYear, "group_membership");
                    if (!empty($affected)){
                        $paymentParams['annee_adhesion_groupe'] = strval($paymentYear);
                        $paymentParams['valeur_adhesion_groupe'] = strval($affected);
                    }
                }
            }
        }

        if ($restToAffect > 0) {
            // donation
            list('isOpenedNextYear' => $isOpenedNextYear, 'field' => $field) = $this->getPayedField($contribFormId, $paymentYear, "donation");
            list('entry' => $entry, 'restToAffect' => $restToAffect,'affected'=>$affected) = 
                $this->registerPaymentForYear($entry, $contribFormId, $field, $restToAffect, $paymentYear, "donation");
            if (!empty($affected)){
                $paymentParams['annee_don'] = strval($paymentYear);
                $paymentParams['valeur_don'] = strval($affected);
            }
        }

        // update payment in entry
        $entry[self::PAYMENTS_FIELDNAME] = $this->appendFormatPaymentForField(
            $entry[self::PAYMENTS_FIELDNAME] ?? "",
            $paymentParams
        );
        
        $entry = $this->updateCalcFields($entry);

        return $entry;
    }

    private function getPayedField(string $contribFormId, string $currentYear, string $name):array
    {
        if ($name != "donation") {
            $nextYearName = str_replace(
                "{year}",
                strval(intval($currentYear) +1),
                self::PAYED_FIELDNAMES[$name]
            );
            $nextYearField = $this->formManager->findFieldFromNameOrPropertyName($nextYearName, $contribFormId);
            if (!empty($nextYearField)) {
                return [
                    'isOpenedNextYear' => true,
                    'field' => $nextYearField,
                ];
            }
        }
        $currentYearName = str_replace(
            "{year}",
            $currentYear,
            self::PAYED_FIELDNAMES[$name]
        );
        $currentYearField = $this->formManager->findFieldFromNameOrPropertyName($currentYearName, $contribFormId);
        if (!empty($currentYearField)) {
            return [
                'isOpenedNextYear' => false,
                'field' => $currentYearField,
            ];
        }
        return [
            'isOpenedNextYear' => false,
            'field' => null,
        ];
    }

    private function registerPaymentForYear(
        array $entry,
        string $contribFormId,
        ?BazarField $field,
        float $restToAffect,
        string $paymentYear,
        string $name
    ):array {
        $affected = 0;
        if (!empty($field)) {
            $isDonation = ($name == "donation");
            $payedValue = floatval($entry[$field->getPropertyName()] ?? 0);
            $toPayFieldName = self::CALC_FIELDNAMES[$name];
            $valueToPay = floatval((
                empty($toPayFieldName) ||
                    !isset($entry[$toPayFieldName])
            ) ? 0 : $entry[$toPayFieldName]);

            if ($isDonation || ($valueToPay > 0)) {
                if ($isDonation || $restToAffect <= $valueToPay) {
                    // only affect current field
                    $entry[$field->getPropertyName()] = strval($payedValue + $restToAffect);
                    $entry = $this->updateYear($entry, self::PAYED_FIELDNAMES["years"][$name], $paymentYear);
                    if ($isDonation && $valueToPay > 0){
                        $this->updateWantedDonation($entry,$valueToPay,$restToAffect);
                    }
                    $affected = $restToAffect;
                    $restToAffect = 0;
                } else {
                    $entry[$field->getPropertyName()] = strval($valueToPay+$payedValue);
                    $entry = $this->updateYear($entry, self::PAYED_FIELDNAMES["years"][$name], $paymentYear);
                    $restToAffect = $restToAffect - $valueToPay;
                    $affected = $valueToPay;
                }
            }
        }
        return compact(['entry','restToAffect','affected']);
    }

    private function updateWantedDonation(array &$entry,$valueToPay,$restToAffect)
    {
        $field = $this->formManager->findFieldFromNameOrPropertyName('bf_montant_don_ponctuel',$entry['id_typeannonce'] ?? null);
        if (!empty($field)) {
            $propertyName = $field->getPropertyName();
            if (!empty($propertyName)){
                $entry[$propertyName] = 'libre';
            }
        }
        $entry['bf_montant_don_ponctuel_libre'] = strval(max(0,$valueToPay-$restToAffect));
    }

    private function updateYear(array $entry, string $name, string $year): array
    {
        $field = $this->formManager->findFieldFromNameOrPropertyName($name, $entry['id_typeannonce']);
        if (empty($field)) {
            return $entry;
        } else {
            $propertyName = $field->getPropertyName();
        }
        $values = explode(",", $entry[$propertyName] ?? "");
        if (!in_array($year, $values)) {
            $values[] = $year;
        }
        $entry[$propertyName] = implode(",", array_filter($values, function ($value) {
            return !empty($value);
        }));
        return $entry;
    }

    private function extractCurrentValue(CalcField $calcField, string $fieldName, string $value)
    {
        $formula = $calcField->getCalcFormula();
        if (preg_match("/test\($fieldName,$value\)\*(\d*(?:\.\d*)?)/", $formula, $matches)) {
            return floatval($matches[1]);
        }
        return 0;
    }

    public function updateEntry($data): array
    {
        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $this->entryManager->validate(array_merge($data, ['antispam' => 1]));
        
        $data['date_maj_fiche'] = empty($data['date_maj_fiche'])
            ? date('Y-m-d H:i:s', time())
            : (new DateTime($data['date_maj_fiche']))->add(new DateInterval("PT1S"))->format('Y-m-d H:i:s');

        // on enleve les champs hidden pas necessaires a la fiche
        unset($data['valider']);
        unset($data['MAX_FILE_SIZE']);
        unset($data['antispam']);
        unset($data['mot_de_passe_wikini']);
        unset($data['mot_de_passe_repete_wikini']);
        unset($data['html_data']);
        unset($data['url']);

        // on nettoie le champ owner qui n'est pas sauvegardé (champ owner de la page)
        if (isset($data['owner'])) {
            unset($data['owner']);
        }

        if (isset($data['sendmail'])) {
            unset($data['sendmail']);
        }

        // on encode en utf-8 pour reussir a encoder en json
        if (YW_CHARSET != 'UTF-8') {
            $data = array_map('utf8_encode', $data);
        }

        $oldPage = $this->pageManager->getOne($data['id_fiche']);
        $owner = $oldPage['owner'] ?? '';
        $user = $oldPage['user'] ?? '';

        // set all other revisions to old
        $this->dbService->query("UPDATE {$this->dbService->prefixTable('pages')} SET `latest` = 'N' WHERE `tag` = '{$this->dbService->escape($data['id_fiche'])}'");

        // add new revision
        $this->dbService->query("INSERT INTO {$this->dbService->prefixTable('pages')} SET ".
            "`tag` = '{$this->dbService->escape($data['id_fiche'])}', ".
            "`time` = '{$this->dbService->escape($data['date_maj_fiche'])}', ".
            "`owner` = '{$this->dbService->escape($owner)}', ".
            "`user` = '{$this->dbService->escape($user)}', ".
            "`latest` = 'Y', ".
            "`body` = '" . $this->dbService->escape(json_encode($data)) . "', ".
            "`body_r` = ''");

        $updatedEntry = $this->entryManager->getOne($data['id_fiche'], false, null, false, true);

        // reset page Manager cache
        $this->pageManager->cache(array_merge($oldPage,[
            'tag' => $data['id_fiche'],
            'time' => $data['date_maj_fiche'],
            'latest' => 'Y',
            'body' => json_encode($data),
        ]),$data['id_fiche']);

        return $updatedEntry;
    }

    public function processTrigger(Event $event)
    {
        $postNotSanitized = $event->getData();
        if (empty($postNotSanitized) ||
            !is_array($postNotSanitized) ||
            empty($postNotSanitized['post']) ||
            !is_array($postNotSanitized['post']) ||
            empty($postNotSanitized['post']['data']) ||
            empty($postNotSanitized['post']['eventType']) ||
            ($postNotSanitized['post']['eventType'] != "Payment") ||
            !is_array($postNotSanitized['post']['data']) ||
            empty($postNotSanitized['post']['data']['state']) ||
            empty($postNotSanitized['post']['data']['payer']) ||
            empty($postNotSanitized['post']['data']['payer']['email']) ||
            empty($postNotSanitized['post']['data']['order'])
            ) {
            // only save not payments data
            $this->appendToHelloAssoLog($postNotSanitized);
        } else {
            // update payments info
            $email = $postNotSanitized['post']['data']['payer']['email'];
            
            $contribFormIds = $this->getCurrentPaymentsFormIds();
            $done = false;
            foreach ($contribFormIds as $formId) {
                $form = $this->getPaymentForm($formId);
                $formType = $postNotSanitized['post']['data']['order']['formType'];
                $formSlug = $postNotSanitized['post']['data']['order']['formSlug'];
                if ($form['formType'] == $formType && $form['formSlug'] == $formSlug) {
                    $payments = new HelloAssoPayments(
                        $this->helloAssoService->convertToPayments(['data'=>[$postNotSanitized['post']['data']]]),
                        []
                    );
                    $this->refreshPaymentsInfo(
                        $formId,
                        $email,
                        '',
                        $payments
                    );
                    $done = true;
                    break;
                }
            }
            if (!$done) {
                $this->appendToHelloAssoLog($postNotSanitized);
            }
        }
    }

    private function appendToHelloAssoLog($postNotSanitized)
    {
        if (empty($postNotSanitized['post']['eventType']) ||
            !is_string($postNotSanitized['post']['eventType']) ||
            !in_array($postNotSanitized['post']['eventType'],['Order','Form'])){
            $pageTag = 'HelloAssoLog';
            try {
                $data = json_decode(json_encode($postNotSanitized),true);
            } catch (Throwable $th) {
                $data = '';
            }
            $this->tripleStore->create($pageTag,self::HELLOASSO_HPF_PROPERTY,json_encode([
                'date' => (new DateTime())->format("Y-m-d H:i:s.v"),
                'account' => (empty($_SESSION['user']['name']) || !is_string($_SESSION['user']['name'])) ? '' : $_SESSION['user']['name'],
                'data' => $data
            ]),'','');
        }
    }

    public function getPaymentMessageEntry(): array
    {
        $this->getHpfParams();
        if (!empty($this->hpfParams['paymentMessageEntry']) && is_string($this->hpfParams['paymentMessageEntry'])) {
            $entry = $this->entryManager->getOne($this->hpfParams['paymentMessageEntry']);
            if (!empty($entry)) {
                return $entry;
            }
        }
        return [];
    }

    /**
     * append formatted format a payment to registre it into a field
     * @param string $paymentContent
     * @param array $params
     * @return string $paymentContent
     * @throws Exception
     */
    public function appendFormatPaymentForField(string $paymentContent,array $params): string
    {
        $newPayment = $this->formatPaymentForField($params);
        $formattedPayments = $this->convertStringToPayments($paymentContent);
        foreach($newPayment as $k => $v){
            $formattedPayments[$k] = $v;
        }
        return json_encode($formattedPayments);
    }

    /**
     * convert a payment raw field to payments array
     * @param string $paymentContent
     * @return array $payments
     * @throws Exception if badly formatted payment
     */
    public function convertStringToPayments(string $paymentContent):array
    {
        $formattedPayments = [];
        if (!empty($paymentContent)){
            try {
                $jsonDecoded = json_decode($paymentContent,true);
                if (empty($jsonDecoded)){
                    throw new Exception('paymentfied is not json encoded');
                } else {
                    foreach($jsonDecoded as $id => $data){
                        if (is_array($data)){
                            $formattedPayments[$id] = $data;
                        }
                    }
                }
            } catch (Throwable $th) {
                foreach(explode(',',$paymentContent) as $paymentRaw){
                    $rawField = $this->formatPaymentForField([
                        'id' => $paymentRaw,
                        'origin' => 'helloasso',
                        'total' => ''
                    ]);
                    foreach($rawField as $k => $v){
                        $formattedPayments[$k] = $v;
                    }
                }
            }
        }
        return $formattedPayments;
    }

    /**
     * format a payment to registre it into a field
     * @param array $params
     * @return array $payment
     * @throws Exception
     */
    public function formatPaymentForField(array $params): array
    {
        if (empty($params['origin'])){
            throw new Exception('$params[\'origin\'] can not be empty');
        }
        return $this->formatPaymentForFieldInternal(
            $params['payment'] ?? null,
            $params['origin'] ?? '',
            $params['annee_adhesion'] ?? '',
            $params['valeur_adhesion'] ?? '',
            $params['annee_adhesion_groupe'] ?? '',
            $params['valeur_adhesion_groupe'] ?? '',
            $params['annee_don'] ?? '',
            $params['valeur_don'] ?? '',
            empty($params['payment']) ? ($params['id'] ?? '') : '',
            empty($params['payment']) ? ($params['date'] ?? '') : '',
            empty($params['payment']) ? ($params['total'] ?? '') : '',
        );
    }

    /**
     * internal format a payment to registre it into a field
     * @param ?Payment $payment
     * @param string $origin
     * @param string $annee_adhesion
     * @param string $valeur_adhesion
     * @param string $annee_adhesion_groupe
     * @param string $valeur_adhesion_groupe
     * @param string $annee_don
     * @param string $valeur_don
     * @param string $id (if no payment)
     * @param string $date (if no payment)
     * @param string $total (if no payment)
     * @return array $formattedPayment
     */
    private function formatPaymentForFieldInternal(
        ?Payment $payment,
        string $origin,
        string $annee_adhesion,
        string $valeur_adhesion,
        string $annee_adhesion_groupe,
        string $valeur_adhesion_groupe,
        string $annee_don,
        string $valeur_don,
        string $id,
        string $date,
        string $total
    ): array
    {
        if (!empty($payment)){
            $id = $payment->id;
            $date = $payment->date;
            $total = strval($payment->amount);
        }
        $formattedPayment = [
            $id => [
                'date' => $date,
                'origin' => $origin,
                'total' => $total,
            ]
        ];
        if (!empty($annee_adhesion) && !empty($valeur_adhesion)){
            $formattedPayment[$id]['adhesion'][$annee_adhesion] = $valeur_adhesion;
        }
        if (!empty($annee_adhesion_groupe) && !empty($annee_adhesion_groupe)){
            $formattedPayment[$id]['adhesion_groupe'][$annee_adhesion_groupe] = $valeur_adhesion_groupe;
        }
        if (!empty($annee_don) && !empty($valeur_don)){
            $formattedPayment[$id]['don'][$annee_don] = $valeur_don;
        }
        return $formattedPayment;
    }

    protected function isAlreadyRegisteredPayment(array &$entry,Payment $payment): bool
    {
        $payments = $this->convertStringToPayments($entry[self::PAYMENTS_FIELDNAME] ?? '');
        return array_key_exists($payment->id,$payments);
    }

    public function getPaymentInfos(string $id): array
    {
        if (empty($id)){
            throw new Exception("id should not be empty");
        }
        $data = [
            'found' => false,
            'id' => $id
        ];
        $payment = $this->helloAssoService->getPayment($id);
        if (!empty($payment) && $payment instanceof Payment){
            $data['found'] = true;
            $data = array_merge($data,$payment->jsonSerialize());
            if (!empty($data['formSlug'])){
                $sameSlugForms = array_filter(
                    $this->getCurrentPaymentsFormIds(),
                    function($formId) use ($data){
                        $form = $this->getPaymentForm($formId);
                        return $form['formSlug'] == $data['formSlug'];
                    }
                );
                if (!empty($sameSlugForms)){
                    $data['form'] = $sameSlugForms[0];
                }
            }
        }

        return $data;
    }
}
