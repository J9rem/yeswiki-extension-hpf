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
use Exception;
use Throwable;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\CalcField;
use YesWiki\Bazar\Field\CheckboxField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\AssetsManager;
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
    public const TYPE_CONTRIB_FIELDNAME = "bf_type_contributeur";
    public const TYPE_CONTRIB_MEMBERSHIP_KEY = "adhesion";
    public const TYPE_CONTRIB_GROUP_MEMBERSHIP_KEY = "adhesion_groupe";
    public const TYPE_CONTRIB_DONATION_KEY = "don";
    public const PAYED_MEMBERSHIP_FIELDNAME = "bf_adhesion_payee_{year}";
    public const PAYED_GROUP_MEMBERSHIP_FIELDNAME = "bf_adhesion_groupe_payee_{year}";
    public const PAYED_DONATION_FIELDNAME = "bf_don_paye_{year}";
    public const PAYED_MEMBERSHIP_YEAR_FIELDNAME = "bf_annee_adhesions_payees";
    public const PAYED_GROUP_MEMBERSHIP_YEAR_FIELDNAME = "bf_annee_adhesions_groupe_payees";
    public const PAYED_DONATION_YEAR_FIELDNAME = "bf_annee_dons_payes";

    protected $aclService;
    protected $assetsManager;
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
                return empty($entries) ? [] : $entries[array_keys($entries)[0]];
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

    public function getFirstCalcField(array $form): CalcField
    {
        if (empty($form['prepared'])) {
            throw new Exception("\$form['prepared'] should not be empty in getFirstCalcField!");
        }
        foreach ($form['prepared'] as $field) {
            if ($field instanceof CalcField) {
                return $field;
            }
        }
        throw new Exception("No CalcField found in \$form['prepared'] (form {$form['bn_label_nature']} - {$form['bn_id_nature']})!");
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
                $cacheEntries[$paymentEmail] = $this->getCurrentContribEntry($paymentEmail);
            }
            if (!empty($cacheEntries[$paymentEmail])) {
                // check if payments are saved
                $bfPayments = $cacheEntries[$paymentEmail][self::PAYMENTS_FIELDNAME ] ?? "";
                $paymentsRegistered = explode(',', $bfPayments);
                if (!in_array($payment->id, $paymentsRegistered)) {
                    $cacheEntries[$paymentEmail] = $this->updateEntryWithPayment($cacheEntries[$paymentEmail], $payment);
                }
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
        $typeContribField = $this->formManager->findFieldFromNameOrPropertyName(self::TYPE_CONTRIB_FIELDNAME, $contribFormId);
        if (empty($typeContribField)) {
            throw new Exception(self::TYPE_CONTRIB_FIELDNAME." is not defined in form {$contribFormId}");
        }
        if (!($typeContribField instanceof CheckboxField)) {
            throw new Exception(self::TYPE_CONTRIB_FIELDNAME." is not an instance of CheckboxField in form {$contribFormId}");
        }
        $contribTypes = $typeContribField->getValues($entry);
        $isMember = in_array(self::TYPE_CONTRIB_MEMBERSHIP_KEY, $contribTypes);
        $isGroupMember = in_array(self::TYPE_CONTRIB_GROUP_MEMBERSHIP_KEY, $contribTypes);
        $isDonating = in_array(self::TYPE_CONTRIB_DONATION_KEY, $contribTypes);

        // update payment list
        $bfPayments = $entry[self::PAYMENTS_FIELDNAME] ?? "";
        $paymentsRegistered = explode(',', $bfPayments);
        $paymentsRegistered[] = $payment->id;

        // save entry
        $entry[self::PAYMENTS_FIELDNAME] = implode(",", array_filter($paymentsRegistered));

        $form = $this->formManager->getOne($contribFormId);
        $calcField = $this->getFirstCalcField($form);
        $newCalcValue = $calcField->formatValuesBeforeSave($entry);
        $entry[$calcField->getPropertyName()] = $newCalcValue[$calcField->getPropertyName()] ?? "";

        if ($this->securityController->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $data = $entry;

        $this->entryManager->validate(array_merge($data, ['antispam' => 1]));
        
        $data['date_maj_fiche'] = date('Y-m-d H:i:s', time());

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

        // on encode en utf-8 pour reussir a encoder en json
        if (YW_CHARSET != 'UTF-8') {
            $data = array_map('utf8_encode', $data);
        }

        $this->pageManager->save($data['id_fiche'], json_encode($data), '');

        $updatedEntry = $this->entryManager->getOne($entry['id_fiche'], false, null, false, true);

        return $updatedEntry;
    }
}
