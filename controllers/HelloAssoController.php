<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Hpf\Controller;

use Configuration;
use DateTime;
use DateInterval;
use Exception;
use Throwable;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\CalcField;
use YesWiki\Bazar\Field\CheckboxField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\AssetsManager;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Hpf\Field\PaymentsField;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Shop\Entity\Payment;
use YesWiki\Shop\Service\HelloAssoService;

class HelloAssoController extends YesWikiController
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
    protected $userManager;

    public function __construct(
        AclService $aclService,
        AssetsManager $assetsManager,
        DbService $dbService,
        EntryManager $entryManager,
        FormManager $formManager,
        HelloAssoService $helloAssoService,
        PageManager $pageManager,
        ParameterBagInterface $params,
        SecurityController $securityController,
        UserManager $userManager
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
        $this->userManager = $userManager;
    }

    /**
     * get the contribution entry for selected user
     * @param string $email
     * @return array $entry or []
     */
    public function getCurrentContribEntry(string $email = ""): array
    {
        try {
            if (!empty($email)) {
                $contribFormId = $this->getCurrentContribFormId();
                $form = $this->formManager->getOne($contribFormId);
                if (empty($form)) {
                    throw new Exception("hpf['contribFormId'] do not correspond to an existing form!");
                }
                $entries = $this->entryManager->search([
                    'formsIds' => [$form['bn_id_nature']],
                    'queries' => [
                        'bf_mail' => $email
                    ]
                ]);
                if (empty($entries)) {
                    return [];
                } else {
                    $idFiche = $entries[array_key_first($entries)]['id_fiche'];
                    return $this->entryManager->getOne($idFiche, false, null, false, true);
                }
            }
        } catch (Throwable $th) {
            if ($this->isDebug() && $this->wiki->UserIsAdmin()) {
                throw $th;
            }
        }
        return [];
    }

    public function getCurrentContribFormId(): string
    {
        $this->getHpfParams();
        if (empty($this->hpfParams['contribFormId'])) {
            throw new Exception("hpf['contribFormId'] param not defined");
        }
        if (!is_scalar($this->hpfParams['contribFormId'])) {
            throw new Exception("hpf['contribFormId'] param should be string");
        }
        if (strval($this->hpfParams['contribFormId']) != strval(intval($this->hpfParams['contribFormId']))) {
            throw new Exception("hpf['contribFormId'] param should be a number");
        }

        return strval($this->hpfParams['contribFormId']);
    }

    /**
     * search CalcFields in $contribForm (filtered on $anmes optionnally)
     * @param array $names
     * @return CalcField[] $fields
     */
    public function getContribCalcFields(array $names = []): array
    {
        $contribFormId = $this->getCurrentContribFormId();
        $form = $this->formManager->getOne($contribFormId);
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
                $field = $this->formManager->findFieldFromNameOrPropertyName($name, $contribFormId);
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
        $fields = $this->getContribCalcFields($names);
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

    public function getPaymentFormUrl(): string
    {
        $this->getHpfParams();
        if (empty($this->hpfParams['paymentFormUrl'])) {
            throw new Exception("hpf['paymentFormUrl'] param not defined");
        }
        if (substr($this->hpfParams['paymentFormUrl'], 0, strlen('https://www.helloasso.com')) != 'https://www.helloasso.com') {
            throw new Exception("hpf['paymentFormUrl'] should begin by 'https://www.helloasso.com'");
        }
        $url = preg_replace("/\/(widget|widget-button)$/", "", $this->hpfParams['paymentFormUrl']);
        if (substr($url, -1) != '/') {
            $url .= "/";
        }
        return $url;
    }

    public function getPaymentFormButtonHtml(): string
    {
        $url = $this->getPaymentFormUrl();
        return "<iframe id=\"haWidgetButton\" src=\"{$url}widget-bouton\" style=\"border: none;\"></iframe>";
    }

    public function getPaymentFormIframeHtml(): string
    {
        $url = $this->getPaymentFormUrl();
        return "<iframe id=\"haWidget\" src=\"{$url}widget\" style=\"width: 100%; height: 800px; border: none;\" scrolling=\"auto\"></iframe>";
    }

    public function refreshPaymentsInfo(string $email = "")
    {
        $form = $this->getPaymentForm();
        $payments = $this->helloAssoService->getPayments([
            'email' => $email,
            'formType' => $form['formType'],
            'formSlug' => $form['formSlug']
        ]);
        $this->checkContribFormHasPaymentsField();

        $cacheEntries = [];

        foreach ($payments as $payment) {
            // open entry based on email from payment
            $paymentEmail = $payment->payer->email;
            if (!isset($cacheEntries[$paymentEmail])) {
                $cacheEntries[$paymentEmail] = [];
            }
            if (!isset($cacheEntries[$paymentEmail]['entry'])) {
                $cacheEntries[$paymentEmail]['entry'] = $this->getCurrentContribEntry($paymentEmail);
                $cacheEntries[$paymentEmail]['previousTotal'] = $cacheEntries[$paymentEmail]['entry'][self::CALC_FIELDNAMES['total']] ?? "";
                $cacheEntries[$paymentEmail]['previousPayments'] = $cacheEntries[$paymentEmail]['entry'][self::PAYMENTS_FIELDNAME] ?? "";
            }
            if (!empty($cacheEntries[$paymentEmail]['entry'])) {
                // check if payments are saved
                $bfPayments = $cacheEntries[$paymentEmail]['entry'][self::PAYMENTS_FIELDNAME] ?? "";
                $paymentsRegistered = explode(',', $bfPayments);
                if (!in_array($payment->id, $paymentsRegistered)) {
                    $cacheEntries[$paymentEmail]['entry'] = $this->updateEntryWithPayment($cacheEntries[$paymentEmail]['entry'], $payment);
                }
            }
        }

        foreach ($cacheEntries as $data) {
            if ($data['previousTotal'] != $data['entry'][self::CALC_FIELDNAMES['total']] ||
                $data['previousPayments'] != $data['entry'][self::PAYMENTS_FIELDNAME]) {
                $this->updateEntry($data['entry']);
            }
        }
    }

    public function getPaymentForm(): array
    {
        if (is_null($this->paymentForm)) {
            $this->getHpfParams();
            $formUrl = $this->getPaymentFormUrl();
            if (!empty($this->hpfParams['paymentForm'])
                && isset($this->hpfParams['paymentForm'][$formUrl])
                && is_array($this->hpfParams['paymentForm'][$formUrl])) {
                $this->paymentForm = array_merge($this->hpfParams['paymentForm'][$formUrl], ['url' => substr($formUrl, 0, -1)]);
            } else {
                $forms = $this->helloAssoService->getForms();
                $form = array_filter($forms, function ($formData) use ($formUrl) {
                    return ($formData['url']."/") == $formUrl;
                });
                if (empty($form)) {
                    throw new Exception("PaymentForm not found with its urls on api !");
                }
                $this->paymentForm = $form[array_key_first($form)];
                $this->saveFormDaraInParams([
                    $formUrl => [
                        'title' => $this->paymentForm['title'],
                        'formType' => $this->paymentForm['formType'],
                        'formSlug' => $this->paymentForm['formSlug'],
                    ]
                ]);
            }
        }

        return $this->paymentForm;
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

    private function checkContribFormHasPaymentsField()
    {
        $contribFormId = $this->getCurrentContribFormId();
        $paymentField = $this->formManager->findFieldFromNameOrPropertyName(self::PAYMENTS_FIELDNAME, $contribFormId);
        if (is_null($paymentField)) {
            $form = $this->formManager->getOne($contribFormId);
            if (!$this->wiki->UserIsAdmin()) {
                throw new Exception(self::PAYMENTS_FIELDNAME." is not defined in form {$form['bn_label_nature']} ({$form['bn_id_nature']})");
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
            throw new Exception(self::PAYMENTS_FIELDNAME." is not a PaymentField in form {$form['bn_label_nature']} ({$form['bn_id_nature']})");
        }
    }

    /* === THE MOST IMPORTANT FUNCTION === */
    /**
     * update entry with payments info
     * @param array $entry
     * @param Payment $payment
     * @return array $updatedEntry
     */
    private function updateEntryWithPayment(array $entry, Payment $payment):array
    {
        $contribFormId = $this->getCurrentContribFormId();
        $typeContribField = $this->formManager->findFieldFromNameOrPropertyName(self::TYPE_CONTRIB['fieldName'], $contribFormId);
        if (empty($typeContribField)) {
            throw new Exception(self::TYPE_CONTRIB['fieldName']." is not defined in form {$contribFormId}");
        }
        if (!($typeContribField instanceof CheckboxField)) {
            throw new Exception(self::TYPE_CONTRIB['fieldName']." is not an instance of CheckboxField in form {$contribFormId}");
        }
        $contribTypes = $typeContribField->getValues($entry);
        $isMember = in_array(self::TYPE_CONTRIB['keys']['membership'], $contribTypes);
        $isGroupMember = in_array(self::TYPE_CONTRIB['keys']['group_membership'], $contribTypes);

        // get Year
        $paymentDate = new DateTime($payment->date);
        $year = $paymentDate->format("Y");

        // membership
        $memberShipToPay = $entry[self::CALC_FIELDNAMES["membership"]] ?? 0;
        $sameYearPropertyName = str_replace("{year}", $year, self::PAYED_FIELDNAMES["membership"]);
        $sameYearField = $this->formManager->findFieldFromNameOrPropertyName($sameYearPropertyName, $contribFormId);
        $nextYearPropertyName = str_replace("{year}", $year +1, self::PAYED_FIELDNAMES["membership"]);
        $nextYearField = $this->formManager->findFieldFromNameOrPropertyName($nextYearPropertyName, $contribFormId);

        if (!empty($nextYearField)) {
            $field = $nextYearField;
            $memberShipYear = $year +1;
        } else {
            $field = $sameYearField;
            $memberShipYear = $year ;
        }
        $payedMemberShip = !empty($field) && isset($entry[$field->getPropertyName()])
            ? $entry[$field->getPropertyName()]
            : 0;

        $diff = floatval($memberShipToPay) - floatval($payedMemberShip);
        if (!empty($field) && $diff >= 0) {
            if (floatval($payment->amount) <= $diff) {
                // only affect $memberShip
                $entry[$field->getPropertyName()] = strval(floatval($payedMemberShip) + floatval($payment->amount));
                $entry = $this->updateYear($entry, self::PAYED_FIELDNAMES["years"]["membership"], $memberShipYear);
                $restToAffect = 0;
            } else {
                $entry[$field->getPropertyName()] = strval(floatval($payedMemberShip) + $diff);
                $entry = $this->updateYear($entry, self::PAYED_FIELDNAMES["years"]["membership"], $memberShipYear);
                $restToAffect = floatval($payment->amount) - $diff;
            }
        } else {
            $restToAffect = floatval($payment->amount);
        }

        if ($restToAffect > 0) {
            // group membership
            $groupMemberShipToPay = $entry[self::CALC_FIELDNAMES["group_membership"]] ?? 0;
            $sameYearPropertyName = str_replace("{year}", $year, self::PAYED_FIELDNAMES["group_membership"]);
            $sameYearField = $this->formManager->findFieldFromNameOrPropertyName($sameYearPropertyName, $contribFormId);
            $nextYearPropertyName = str_replace("{year}", $year +1, self::PAYED_FIELDNAMES["group_membership"]);
            $nextYearField = $this->formManager->findFieldFromNameOrPropertyName($nextYearPropertyName, $contribFormId);
    
            if (!empty($nextYearField)) {
                $field = $nextYearField;
                $groupMemberShipYear = $year +1;
            } else {
                $field = $sameYearField;
                $groupMemberShipYear = $year ;
            }
            $payedGroupMemberShip = !empty($field) && isset($entry[$field->getPropertyName()])
                ? $entry[$field->getPropertyName()]
                : 0;
    
            $diff = floatval($groupMemberShipToPay) - floatval($payedGroupMemberShip);
            if (!empty($field) && $diff >= 0) {
                if ($restToAffect <= $diff) {
                    // only affect $groupMemberShip
                    $entry[$field->getPropertyName()] = straval(floatval($payedGroupMemberShip) + $restToAffect);
                    $entry = $this->updateYear($entry, self::PAYED_FIELDNAMES["years"]["group_membership"], $groupMemberShipYear);
                    $restToAffect = 0;
                } else {
                    $entry[$field->getPropertyName()] = strval(floatval($payedGroupMemberShip) + $diff);
                    $entry = $this->updateYear($entry, self::PAYED_FIELDNAMES["years"]["group_membership"], $groupMemberShipYear);
                    $restToAffect = $restToAffect - $diff;
                }
            }
            if ($restToAffect > 0) {
                // donation
                $sameYearPropertyName = str_replace("{year}", $year, self::PAYED_FIELDNAMES["donation"]);
                $sameYearField = $this->formManager->findFieldFromNameOrPropertyName($sameYearPropertyName, $contribFormId);
                
                $payedDonation = !empty($sameYearField) && isset($entry[$sameYearField->getPropertyName()])
                    ? $entry[$sameYearField->getPropertyName()]
                    : 0;
                if (!empty($sameYearField)) {
                    $entry[$sameYearField->getPropertyName()] = strval(floatval($payedDonation) + $restToAffect);
                    $entry = $this->updateYear($entry, self::PAYED_FIELDNAMES["years"]["donation"], $year);
                }
            }
        }

        // update payment list
        $bfPayments = $entry[self::PAYMENTS_FIELDNAME] ?? "";
        $paymentsRegistered = explode(',', $bfPayments);
        $paymentsRegistered[] = $payment->id;

        // save entry
        $entry[self::PAYMENTS_FIELDNAME] = implode(",", array_filter($paymentsRegistered));
        
        $entry = $this->updateCalcFields($entry, HelloAssoController::CALC_FIELDNAMES);

        return $entry;
    }

    private function updateYear(array $entry, string $name, string $year): array
    {
        $values = explode(",", $entry[$name] ?? "");
        if (in_array($year, $values)) {
            $values[] = $year;
        }
        $entry[$name] = implode(",", array_filter($values));
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

        return $updatedEntry;
    }

    public function processTrigger(array $postNotSanitized, int $index)
    {
        $this->wiki->AppendContentToPage(json_encode([
             date("Y-m-d H:i:s.v") => $postNotSanitized
            ]), 'HelloAssoLog', true);
        return [
            $index => "saved"
        ];
    }
}
