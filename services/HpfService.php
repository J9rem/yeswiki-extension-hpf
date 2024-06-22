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
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Throwable;
use YesWiki\Alternativeupdatej9rem\Service\CacheService;
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
use YesWiki\Shop\Entity\Event;
use YesWiki\Shop\Entity\Payment;
use YesWiki\Shop\Entity\HelloAssoPayments;
use YesWiki\Shop\Service\EventDispatcher;
use YesWiki\Shop\Service\HelloAssoService;
use YesWiki\Wiki;

class HpfService
{
    public const PAYMENTS_FIELDNAME = "bf_payments";
    public const TYPE_PAYMENT_FIELDNAME = "bf_moyen_paiement";
    public const CB_TYPE_PAYMENT_FIELDVALUE = "cb";
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

    public const HELLOASSO_API_PROPERTY = 'https://www.habitatparticipatif-france.fr/HelloAssoApiLog';
    public const HELLOASSO_HPF_PROPERTY = 'https://www.habitatparticipatif-france.fr/HelloAssoLog';
    public const HELLOASSO_HPF_PAYMENTS_CACHE_PROPERTY = 'https://www.habitatparticipatif-france.fr/PaymentsCache';
    public const HELLOASSO_HPF_PAYMENTS_BY_CAT_CACHE_PROPERTY = 'https://www.habitatparticipatif-france.fr/PaymentsCacheByCat';

    public const AREAS = [
        "ARA" => [1,3,7,15,26,38,42,43,63,69,73,74],
        "BFC" => [21,25,39,58,70,71,89,90],
        "BRE" => [22,29,35,56],
        "CVL" => [18,28,36,37,41,45],
        "COR" => [20],
        "GES" => [8,10,51,52,54,55,57,67,68,88],
        "HDF" => [2,59,60,62,80],
        "IDF" => [75,77,78,91,92,93,94,95],
        "NOR" => [14,27,50,61,76],
        "NAQ" => [16,17,19,23,24,33,40,47,64,79,86,87],
        "OCC" => [9,11,12,30,31,32,34,46,48,65,66,81,82],
        "PDL" => [44,49,53,72,85],
        "PAC" => [4,5,6,13,83,84],
        "GP" => [971],
        "GF" => [973],
        "RE" => [974],
        "MQ" => [972],
        "YT" => [976],
        "ETR" => [99],
        "sans" => [0]
    ];

    public const AREA_FIELDNAMES = [
        "membership" => ['bf_region_adhesion','bf_departement_adhesion'],
        "group_membership" => ['bf_region_adhesion_groupe','bf_departement_adhesion_groupe'],
        "backupfor3" => ['bf_region','bf_departements_valides']
    ];

    protected const DEFAULT_BASE_PAYMENT = [
        'v' => [0,0],
        'h' => [0,0],
        'c' => [0,0],
        'e' => [0,0],
        's' => [0,0],
        'i' => [0,0]
    ];

    protected const PAYMENT_TYPE_ASSOCIATION = [
        '/^cb$/' => 'h',
        '/^virement$/' => 'v',
        '/^cheque$/' => 'c',
        '/^helloasso.*$/' => 'h',
        '/.*/' => 'i',
    ];

    protected $aclService;
    protected $assetsManager;
    protected $cacheService;
    protected $csrfTokenManager;
    protected $dbService;
    protected $debug;
    protected $entryManager;
    protected $formManager;
    protected $helloAssoService;
    protected $hpfParams;
    protected $pageManager;
    protected $params;
    protected $paymentForms;
    protected $securityController;
    protected $tripleStore;
    protected $userManager;
    protected $wiki;

    public function __construct(
        AclService $aclService,
        AssetsManager $assetsManager,
        CacheService $cacheService,
        CsrfTokenManager $csrfTokenManager,
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
        $this->cacheService = $cacheService;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->dbService = $dbService;
        $this->debug = null;
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->helloAssoService = $helloAssoService;
        $this->hpfParams = null;
        $this->pageManager = $pageManager;
        $this->params = $params;
        $this->paymentForms = null;
        $this->securityController = $securityController;
        $this->tripleStore = $tripleStore;
        $this->userManager = $userManager;
        $this->wiki = $wiki;
        /* === Feature UUID : hpf-api-helloasso-token-triggered === */
        if ($this->wiki->services->has(EventDispatcher::class)) {
            $eventDispatcher = $this->wiki->services->get(EventDispatcher::class);
            $eventDispatcher->addListener('shop.helloasso.api.called', [$this,'processTrigger']);
        }
        /* === end of Feature UUID : hpf-api-helloasso-token-triggered === */
    }

    /**
     * get the contribution entry for selected user
     * @param string $formId
     * @param string $email
     * @param string $preferedEntryId
     * @param string $preferedUserName
     * @return array $entries
     * @throws Exception
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-api-helloasso-token-triggered
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
                    function ($k) {
                        return substr($k, 0, strlen('bazar-search-')) !== 'bazar-search-';
                    },
                    ARRAY_FILTER_USE_KEY
                );
                $query = <<<SQL
                SELECT `tag`,`owner` FROM {$this->dbService->prefixTable('pages')}
                  WHERE `latest` = 'Y'
                    AND `comment_on` = ''
                    AND `body` LIKE '%"id_typeannonce":"{$this->dbService->escape($form['bn_id_nature'])}"%'
                    AND `tag` IN (
                        SELECT DISTINCT `resource` FROM {$this->dbService->prefixTable('triples')}
                            WHERE `value` = 'fiche_bazar'
                            AND `property` = 'http://outils-reseaux.org/_vocabulary/type'
                        ORDER BY resource ASC 
                    )
                    AND (
                        `body` LIKE '%"bf_mail":"{$this->dbService->escape($email)}"%'
                        OR `owner` = '{$this->dbService->escape($preferedUserName)}'
                    )
                SQL;
                $entries = $this->dbService->loadAll($query);
                $entries = empty($entries) ? [] : $entries;
                if (empty($entries)) {
                    return [];
                } else {
                    $sameIds = empty($preferedEntryId)
                        ? []
                        : array_filter($entries, function ($entry) use ($preferedEntryId) {
                            return !empty($entry['tag']) && $entry['tag'] == $preferedEntryId;
                        });
                    $sameOwner = (empty($sameIds) && !empty($preferedUserName))
                        ? array_filter($entries, function ($entry) use ($preferedUserName) {
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
                    if (!empty($ids) && $this->wiki->UserIsAdmin()) {
                        $ids = [$ids[array_key_first($ids)]];
                    }
                    return array_map(function ($id) {
                        return $this->entryManager->getOne($id['tag'], false, null, false, true);
                    }, $ids);
                }
            }
        } catch (Throwable $th) {
            if ($this->isDebug() && $this->wiki->UserIsAdmin()) {
                throw $th;
            }
        }
        return [];
    }

    /**
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-payments-field
     * Feature UUID : hpf-api-helloasso-token-triggered
     */
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
     * get Hpf Structure Info but throws exception if format is not correct
     * Feature UUID : hpf-receipts-creation
     * @return array
     * @throws Exception
     */
    public function getHpfStructureInfo(): array
    {
        $this->getHpfParams();
        foreach([
            'name' => true,
            'address' => true,
            'addressComplement' => false,
            'postalCode' => true,
            'town' => true,
            'email' => true,
            'website' => true
        ] as $key => $testIfEmpty) {
            if ($testIfEmpty && empty($this->hpfParams['structureInfo'][$key])) {
                throw new Exception("hpf['structureInfo']['$key'] param should be defined !");
            }
            if (!empty($this->hpfParams['structureInfo'][$key])
                && !is_string($this->hpfParams['structureInfo'][$key])) {
                throw new Exception("hpf['structureInfo']['$key'] param should be a string !");
            }
        }

        if (empty(filter_var($this->hpfParams['structureInfo']['email'], FILTER_VALIDATE_EMAIL, FILTER_NULL_ON_FAILURE))) {
            throw new Exception("hpf['structureInfo']['email'] param should be an email !");
        }
        if (empty(filter_var($this->hpfParams['structureInfo']['website'], FILTER_VALIDATE_URL, FILTER_NULL_ON_FAILURE)) ||
            !preg_match('/^https?:\/\/.*/', $this->hpfParams['structureInfo']['website'])) {
            throw new Exception("hpf['structureInfo']['website'] param should be an url !");
        }

        return $this->hpfParams['structureInfo'];
    }

    /**
     * search CalcFields in $contribForm (filtered on $names optionnally)
     * @param string $formId
     * @param array $names
     * @return CalcField[] $fields
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-register-payment-action
     * Feature UUID : hpf-api-helloasso-token-triggered
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
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-register-payment-action
     * Feature UUID : hpf-api-helloasso-token-triggered
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
            $this->debug = ($this->wiki->GetConfigValue('debug') == 'yes');
        }
        return $this->debug;
    }

    /**
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-api-helloasso-token-triggered
     * @param bool $returnGlobalForms
     * @return array $urls
     */
    protected function getPaymentFormsUrl(bool $returnGlobalForms = false): array
    {
        $this->getHpfParams();
        $key = $returnGlobalForms ? 'globalFormUrls' : 'paymentsFormUrls';
        if (empty($this->hpfParams[$key])) {
            if ($returnGlobalForms) {
                return [];
            } else {
                throw new Exception("hpf['paymentsFormUrls'] param not defined");
            }
        }
        $urls = [];
        foreach (explode(',', $this->hpfParams[$key]) as $idx => $url) {
            if (substr($url, 0, strlen('https://www.helloasso.com')) != 'https://www.helloasso.com') {
                throw new Exception("hpf['$key'] should begin by 'https://www.helloasso.com'");
            }
            $url = preg_replace("/\/(widget|widget-button)$/", "", $url);
            if (substr($url, -1) != '/') {
                $url .= "/";
            }
            $urls[] = $url;
        }
        return $urls;
    }

    /**
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-api-helloasso-token-triggered
     * @return array $urls
     */
    protected function getPaymentAllFormsUrl(): array
    {
        return array_merge(
            array_values($this->getPaymentFormsUrl()),
            array_values($this->getPaymentFormsUrl(true))
        );
    }

    /**
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-api-helloasso-token-triggered
     */
    protected function getPaymentFormUrls(string $formId): array
    {
        $foundUrls = [];
        if (!empty($formId)) {
            $urls = $this->getPaymentFormsUrl();
            $formsIds = $this->getCurrentPaymentsFormIds();
            $foundUrls = [];
            foreach ($formsIds as $idx => $formIdToCompare) {
                if ($formIdToCompare == $formId && isset($urls[$idx])) {
                    $foundUrls[] = $urls[$idx];
                }
            }
        }
        return array_merge(
            $foundUrls,
            array_values($this->getPaymentFormsUrl(true))
        );
    }

    /**
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-api-helloasso-token-triggered
     */
    public function getPaymentFormUrl(string $formId = ""): string
    {
        $urls = empty($formId)
            ? $this->getPaymentAllFormsUrl()
            : $this->getPaymentFormUrls($formId);
        return $urls[0];
    }

    /**
     * Feature UUID : hpf-payment-status-action
     */
    public function getPaymentFormButtonHtml(string $formId = ""): string
    {
        $url = $this->getPaymentFormUrl($formId);
        return "<iframe id=\"haWidgetButton\" src=\"{$url}widget-bouton\" style=\"border: none;\"></iframe>";
    }

    /**
     * Feature UUID : hpf-payment-status-action
     */
    public function getPaymentFormIframeHtml(string $formId = ""): string
    {
        $url = $this->getPaymentFormUrl($formId);
        return "<iframe id=\"haWidget\" src=\"{$url}widget\" style=\"width: 100%; height: 800px; border: none;\" scrolling=\"auto\"></iframe>";
    }

    /**
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-api-helloasso-token-triggered
     * @return bool $done
     */
    public function refreshPaymentsInfo(string $formId, string $email = "", string $preferedEntryId = '', ?HelloAssoPayments $payments = null): bool
    {
        $form = $this->getPaymentForm($formId);
        try {
            $localPayments = empty($payments)
                ? $this->getPaymentsFromHelloAsso($email, $form)
                : $payments;
        } catch (Throwable $th) {
            // do nothing (remove warnings)
            if ($this->isDebug() && $this->wiki->UserIsAdmin()) {
                throw $th;
            } else {
                try {
                    $pageTag = 'HelloAssoApiLog';
                    $this->tripleStore->create($pageTag, self::HELLOASSO_API_PROPERTY, json_encode([
                        'date' => (new DateTime())->format("Y-m-d H:i:s.v"),
                        'account' => (empty($_SESSION['user']['name']) || !is_string($_SESSION['user']['name'])) ? '' : $_SESSION['user']['name'],
                        'throwableToString' => "Exception (code {$th->getCode()}): {$th->getMessage()} in " . basename($th->getFile()) . ":{$th->getLine()}"
                    ]), '', '');
                } catch (Throwable $th) {
                }
            }

            $localPayments = null;
        }
        $done = false;
        if (!empty($localPayments)) {
            $this->checkContribFormsHavePaymentsField();

            $cacheEntries = [];
            $cacheEntriesWithSamePaymentId = [];

            foreach ($localPayments as $payment) {
                if (empty($this->lazyGetEntriesWithSamePaymentId($cacheEntriesWithSamePaymentId, $payment))) {
                    // open entry based on email from payment
                    $paymentEmail = $payment->payer->email;
                    if (!isset($cacheEntries[$paymentEmail])) {
                        $cacheEntries[$paymentEmail] = [];
                    }
                    if (!isset($cacheEntries[$paymentEmail]['entry'])) {
                        if (empty($preferedEntryId)) {
                            $preferedEntryId = $this->extractAssociatedEntryName($payment);
                        }
                        $entries = $this->getCurrentContribEntries($formId, $paymentEmail, $preferedEntryId);
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
                            $done = true;
                        }
                    }
                }
            }

            foreach ($cacheEntries as $data) {
                if (!empty($data) &&
                    (
                        $data['previousTotal'] != $data['entry'][self::CALC_FIELDNAMES['total']]
                        || $data['previousPayments'] != $data['entry'][self::PAYMENTS_FIELDNAME]
                    )
                ) {
                    $this->updateEntry($data['entry']);
                    $done = true;
                }
            }
        }
        return $done;
    }

    /**
     * get payments from HelloAsso
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-api-helloasso-token-triggered
     * @param string $email
     * @param array $form
     * @return null|HelloAssoPayments
     * @throws Exception
     */
    protected function getPaymentsFromHelloAsso(string $email, array $form): ?HelloAssoPayments
    {
        $firstSearch = $this->helloAssoService->getPayments([
            'email' => $email,
            'formType' => $form['formType'],
            'formSlug' => $form['formSlug']
        ], false);
        // $firstSearch = [];
        // specific for direct checkout
        $secondSearch = $this->helloAssoService->getPayments([
            'email' => $email,
            'formType' => 'Checkout',
            'formSlug' => 'default'
        ], true);
        if (empty($firstSearch)) {
            $payments = $secondSearch;
        } elseif (empty($secondSearch)) {
            $payments = $firstSearch;
        } else {
            $paymentsAsArray = [];
            foreach ($firstSearch as $payment) {
                $paymentsAsArray[] = $payment;
            }
            foreach ($secondSearch as $payment) {
                $paymentsAsArray[] = $payment;
            }
            $payments = new HelloAssoPayments($paymentsAsArray, []);
        }
        return $payments;
    }

    /**
     * lazy get entries with this payment id
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-api-helloasso-token-triggered
     * @param array $cache
     * @param Payment $payment
     * @return array
     */
    protected function lazyGetEntriesWithSamePaymentId(array &$cache, Payment $payment): array
    {
        if (!array_key_exists($payment->id, $cache)) {
            $cache[$payment->id] = $this->findEntriesWithSamePayment($payment->id);
        }
        return $cache[$payment->id];
    }

    /**
     * return all HelloAsso forms corresponding to this form
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-api-helloasso-token-triggered
     * @param string $formId
     * @return array
     */
    public function getPaymentForms(string $formId): array
    {
        if (is_null($this->paymentForms)) {
            $this->paymentForms = [];
        }

        if (!isset($this->paymentForms[$formId])) {
            $this->getHpfParams();
            $formUrls = $this->getPaymentFormUrls($formId);
            $paymentForms = [];
            $hAFormsCache = null;
            foreach ($formUrls as $formUrl) {
                if (!empty($this->hpfParams['paymentForm'])
                    && isset($this->hpfParams['paymentForm'][$formUrl])
                    && is_array($this->hpfParams['paymentForm'][$formUrl])) {
                    $paymentForms[] = array_merge($this->hpfParams['paymentForm'][$formUrl], ['url' => substr($formUrl, 0, -1)]);
                } else {
                    $hAFormsCache = ($hAFormsCache === null)
                        ? $this->helloAssoService->getForms()
                        : $hAFormsCache;
                    $filteredForms = array_filter($hAFormsCache, function ($formData) use ($formUrl) {
                        return ($formData['url'] . "/") == $formUrl;
                    });
                    if (empty($filteredForms)) {
                        throw new Exception("PaymentForm not found with its urls on api !");
                    }
                    $form = $filteredForms[array_key_first($filteredForms)];
                    $paymentForms[] = $form;
                    $this->saveFormDataInParams([
                        $formUrl => [
                            'title' => $form['title'],
                            'formType' => $form['formType'],
                            'formSlug' => $form['formSlug'],
                        ]
                    ]);
                }
            }
            $this->paymentForms[$formId] = $paymentForms;
        }

        return $this->paymentForms[$formId];
    }

    /**
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-api-helloasso-token-triggered
     */
    public function getPaymentForm(string $formId): array
    {
        $forms = $this->getPaymentForms($formId);
        return empty($forms) ? [] : $forms[array_key_first($forms)];
    }

    /**
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-api-helloasso-token-triggered
     * @param string $formSlug
     * @param string $formType
     * @return array ['paymentForm'=>array,'formsIds'=>array]
     */
    protected function searchPaymentFormsFormSlugAndType(string $formSlug, string $formType): array
    {
        $contribFormIds = $this->getCurrentPaymentsFormIds();
        $results = [];
        foreach ($contribFormIds as $formId) {
            $associatedForms = $this->getPaymentForms($formId);
            foreach ($associatedForms as $paymentForm) {
                if ($paymentForm['formSlug'] == $formSlug && $paymentForm['formType'] == $formType) {
                    if (empty($results['paymentForm'])) {
                        $results['paymentForm'] = $paymentForm;
                        $results['formsIds'] = [$formId];
                    } elseif (!in_array($formId, $results['formsIds'])) {
                        $results['formsIds'][] = $formId;
                    }
                }
            }
        }
        return $results;
    }

    private function saveFormDataInParams(array $data)
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
                    throw new Exception(self::PAYMENTS_FIELDNAME . " is not defined in form {$form['bn_label_nature']} ({$form['bn_id_nature']})");
                }
                if (empty($form['bn_template'])) {
                    throw new Exception("\$form['bn_template'] is not defined in form {$form['bn_label_nature']} ({$form['bn_id_nature']})");
                }
                // add field
                $formTemplate = $form['bn_template'];
                if (substr($formTemplate, -strlen("\n")) != "\n") {
                    $formTemplate .= "\n";
                }
                $formTemplate .= "payments***" . self::PAYMENTS_FIELDNAME . "***Liste des paiements*** *** *** *** *** *** *** *** ***@admins***@admins*** *** *** ***\n";

                $newForm = $form;
                $newForm['bn_template'] = $formTemplate;
                $this->formManager->update($newForm);
            } elseif (!($paymentField instanceof PaymentsField)) {
                throw new Exception(self::PAYMENTS_FIELDNAME . " is not a PaymentField in form ({$contribFormId})");
            }
        }
    }

    /**
     * refresh entry from HelloAsso then reload entry and gives calcValue
     * @param array $entry
     * @param string $email
     * @return array $entry
     * Feature UUID : hpf-payment-status-action
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
     * @param string $forceOrigin
     * @param string $forceYear
     * @return array $updatedEntry
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-api-helloasso-token-triggered
     */
    public function updateEntryWithPayment(array $entry, Payment $payment, string $forceOrigin = '', string $forceYear = ''): array
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

        $restToAffect =  floatval($payment->status === 'Authorized' ? $payment->amount : 0);
        $paymentParams =
        [
            'payment' => $payment,
            'origin' => !empty($forceOrigin)
                ? $forceOrigin
                : (
                    $this->isDonationFormType($payment->formType)
                    ? 'helloassoDon'
                    : "helloasso:$contribFormId"
                )
        ];
        if (intval($paymentYear) > intval($currentYear)) {
            // error
            return $entry;
        } elseif (!empty($forceYear) || intval($paymentYear) == intval($currentYear)) {
            $wantedYear = (empty($forceYear) || intval($forceYear) > (intval($currentYear) + 1))
                ? $paymentYear
                : $forceYear;
            $this->manipulatePayment($restToAffect, $contribFormId, 'membership', 'adhesion', $wantedYear, $forceYear, $entry, $paymentParams);
            $this->manipulatePayment($restToAffect, $contribFormId, 'group_membership', 'adhesion_groupe', $wantedYear, $forceYear, $entry, $paymentParams);
        }
        $this->manipulatePayment($restToAffect, $contribFormId, 'donation', 'don', $paymentYear, '', $entry, $paymentParams);

        // update payment in entry
        $entry[self::PAYMENTS_FIELDNAME] = $this->appendFormatPaymentForField(
            $entry[self::PAYMENTS_FIELDNAME] ?? "",
            $paymentParams
        );

        $entry = $this->updateCalcFields($entry);

        return $entry;
    }

    /**
     * check if formType is Donation
     * @param string $formType
     * @return bool
     */
    protected function isDonationFormType(string $formType): bool
    {
        return in_array($formType, ['Donation','Checkout'], true);
    }

    /**
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : ???
     */
    private function manipulatePayment(
        &$restToAffect,
        string $contribFormId,
        string $key,
        string $partKey,
        string $wantedYear,
        string $forceYear,
        array &$entry,
        array &$paymentParams
    ) {
        if ($restToAffect > 0) {
            list('isOpenedNextYear' => $isOpenedNextYear, 'field' => $field) =
                $this->getPayedField($contribFormId, $wantedYear, $key, !empty($forceYear));

            $aimedYear = $isOpenedNextYear
                ? strval(intval($wantedYear) + 1)
                : $wantedYear;

            list('entry' => $entry, 'restToAffect' => $restToAffect, 'affected' => $affected) =
                $this->registerPaymentForYear($entry, $contribFormId, $field, $restToAffect, $aimedYear, $key);
            if (!empty($affected)) {
                $paymentParams["annee_$partKey"] = strval($aimedYear);
                $paymentParams["valeur_$partKey"] = strval($affected);
            }
        }
    }

    public function getPayedField(string $contribFormId, string $searchedYear, string $name, bool $forced = false): array
    {
        if (!$forced && $name != "donation") {
            $nextYearName = str_replace(
                "{year}",
                strval(intval($searchedYear) + 1),
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
        $searchedYearName = str_replace(
            "{year}",
            $searchedYear,
            self::PAYED_FIELDNAMES[$name]
        );
        $searchYearField = $this->formManager->findFieldFromNameOrPropertyName($searchedYearName, $contribFormId);
        if (!empty($searchYearField)) {
            return [
                'isOpenedNextYear' => false,
                'field' => $searchYearField,
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
    ): array {
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
                    if ($isDonation && $valueToPay > 0) {
                        $this->updateWantedDonation($entry, $valueToPay, $restToAffect);
                    }
                    $affected = $restToAffect;
                    $restToAffect = 0;
                } else {
                    $entry[$field->getPropertyName()] = strval($valueToPay + $payedValue);
                    $entry = $this->updateYear($entry, self::PAYED_FIELDNAMES["years"][$name], $paymentYear);
                    $restToAffect = $restToAffect - $valueToPay;
                    $affected = $valueToPay;
                }
            }
        }
        return compact(['entry','restToAffect','affected']);
    }

    private function updateWantedDonation(array &$entry, $valueToPay, $restToAffect)
    {
        $field = $this->formManager->findFieldFromNameOrPropertyName('bf_montant_don_ponctuel', $entry['id_typeannonce'] ?? null);
        if (!empty($field)) {
            $propertyName = $field->getPropertyName();
            if (!empty($propertyName)) {
                $entry[$propertyName] = 'libre';
            }
        }
        $entry['bf_montant_don_ponctuel_libre'] = strval(max(0, $valueToPay - $restToAffect));
    }

    public function updateYear(array $entry, string $name, string $year, bool $append = true): array
    {
        $field = $this->formManager->findFieldFromNameOrPropertyName($name, $entry['id_typeannonce']);
        if (empty($field)) {
            return $entry;
        } else {
            $propertyName = $field->getPropertyName();
        }
        $values = explode(",", $entry[$propertyName] ?? "");
        if ($append) {
            if (!in_array($year, $values)) {
                $values[] = $year;
            }
        } else {
            $values = array_filter($values, function ($v) use ($year) {
                return $v != $year;
            });
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

    /**
     * Feature UUID : hpf-register-payment-action
     */
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
        $this->dbService->query("INSERT INTO {$this->dbService->prefixTable('pages')} SET " .
            "`tag` = '{$this->dbService->escape($data['id_fiche'])}', " .
            "`time` = '{$this->dbService->escape($data['date_maj_fiche'])}', " .
            "`owner` = '{$this->dbService->escape($owner)}', " .
            "`user` = '{$this->dbService->escape($user)}', " .
            "`latest` = 'Y', " .
            "`body` = '" . $this->dbService->escape(json_encode($data)) . "', " .
            "`body_r` = ''");

        $updatedEntry = $this->entryManager->getOne($data['id_fiche'], false, null, false, true);

        // reset page Manager cache
        $this->pageManager->cache(array_merge($oldPage, [
            'tag' => $data['id_fiche'],
            'time' => $data['date_maj_fiche'],
            'latest' => 'Y',
            'body' => json_encode($data),
        ]), $data['id_fiche']);
        // reset cache
        if (!empty($data['id_typeannonce']) && !empty(strval($data['id_typeannonce']))) {
            $this->cacheService->updateFormIdTimestamp(strval($data['id_typeannonce']));
        }

        return $updatedEntry;
    }

    /**
     * Feature UUID : hpf-api-helloasso-token-triggered
     */
    public function processTrigger(Event $event)
    {
        $postNotSanitized = $event->getData();
        if (empty($postNotSanitized)
            || !is_array($postNotSanitized)
            || empty($postNotSanitized['post'])
            || !is_array($postNotSanitized['post'])) {
            $this->appendToHelloAssoLog(
                $postNotSanitized,
                'post is not an array'
            );
        } elseif (!$this->checkIfDataIsPayment($postNotSanitized['post'])) {
            $this->appendToHelloAssoLog(
                $postNotSanitized['post'],
                'post doest not represent a payment'
            );
        } else {
            $post = $postNotSanitized['post'];
            $data = $post['data'];
            // check if already registered
            $paymentId = $data['id'];
            $entries = $this->findEntriesWithSamePayment($paymentId);
            if (empty($entries)) {
                // update payments info
                $email = $data['payer']['email'];

                $contribFormIds = $this->getCurrentPaymentsFormIds();
                if (empty($contribFormIds)) {
                    $this->appendToHelloAssoLog($post, 'No contribFormIds !');
                } else {
                    try {
                        $done = false;
                        $payments = new HelloAssoPayments(
                            $this->helloAssoService->convertToPayments(['data' => [$data]]),
                            []
                        );
                        $payment = $payments->getPayments()[0];
                        $preferedEntryId = $this->extractAssociatedEntryName($payment);
                        if (!empty($preferedEntryId)) {
                            $preferedEntry = $this->entryManager->getOne($preferedEntryId);
                            if (!empty($preferedEntry['id_typeannonce'])
                                && in_array($preferedEntry['id_typeannonce'], $contribFormIds)) {
                                $this->refreshPaymentsInfo(
                                    $preferedEntry['id_typeannonce'],
                                    $email,
                                    $preferedEntryId,
                                    $payments
                                );
                                $done = true;
                            }
                        }
                        if (!$done) {
                            $formType = $data['order']['formType'];
                            $formSlug = $data['order']['formSlug'];
                            $data = $this->searchPaymentFormsFormSlugAndType($formSlug, $formType);
                            if (!empty($data['formsIds'])) {
                                $formsIds = $data['formsIds'];
                            } elseif ($this->isDonationFormType($formType)) {
                                $formsIds = array_values(array_unique($contribFormIds));
                            }

                            foreach ($formsIds as $formId) {
                                if ($this->refreshPaymentsInfo(
                                    $formId,
                                    $email,
                                    '',
                                    $payments
                                )) {
                                    $done = true;
                                    break;
                                }
                            }
                        }
                        if (!$done) {
                            $this->appendToHelloAssoLog($post, 'payment not registered !');
                        }
                    } catch (Throwable $th) {
                        $this->appendToHelloAssoLog($post, "Error when registering payment : {$th->getMessage()} in " .
                            basename($th->getFile()) . ", line {$th->getLine()}");
                    }
                }
            } else {
                try {
                    $ids = implode(',', array_column($entries, 'id_fiche'));
                } catch (Throwable $th) {
                    $ids = 'Not extracted ids !';
                }
                $this->appendToHelloAssoLog($post, "Payment already registered in entries : $ids");
            }
        }
    }

    /**
     * check if data corresponds to a payment
     * @param array $post
     * @return bool
     */
    protected function checkIfDataIsPayment(array $post): bool
    {
        return !empty($post['data']) && is_array($post['data'])
            && !empty($post['eventType']) && $post['eventType'] === "Payment"
            && !empty($post['data']['id'])
            && !empty($post['data']['state'])
            && !empty($post['data']['payer'])
            && !empty($post['data']['payer']['email'])
            && !empty($post['data']['order']);
    }

    /**
     * Feature UUID : hpf-api-helloasso-token-triggered
     * @param array $post
     * @param string $reason
     * @return void
     */
    private function appendToHelloAssoLog($post, string $reason)
    {
        try {
            if (empty($post['eventType'])
                || !is_string($post['eventType'])) {
                throw new Exception('Post has not eventType !');
            }
            // only follow payment event
            if ($post['eventType'] === "Payment") {

                if (empty($post['data']['order']['formSlug'])
                    || !is_string($post['data']['order']['formSlug'])) {
                    throw new Exception('Post[\'data\'] has not formSlug !');
                }

                // no register known formSlug
                if (!in_array($post['data']['order']['formSlug'], [
                    'billeterie-rnhp-grand-public',
                    'rnhp-2024',
                    'guide-vieillir-en-habitat-participatif',
                    'rnhp-2024-reservation-des-dejeuners',
                    'visites-d-habitats-participatifs'
                ])) {
                    throw new Exception('Not understood error !');
                }
            }
        } catch (Throwable $th) {
            try {
                $data = json_decode(json_encode($post), true);
            } catch (Throwable $th) {
                $data = 'Error formatting json representation !';
            }


            $pageTag = 'HelloAssoLog';
            $this->tripleStore->create($pageTag, self::HELLOASSO_HPF_PROPERTY, json_encode([
                'date' => (new DateTime())->format("Y-m-d H:i:s.v"),
                'account' => (empty($_SESSION['user']['name']) || !is_string($_SESSION['user']['name'])) ? '' : $_SESSION['user']['name'],
                'data' => $data,
                'reason' => $reason,
                'error' => $th->getMessage()
            ]), '', '');
        }
    }

    /**
     * Feature UUID : hpf-payment-status-action
     */
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
     * Feature UUID : hpf-payments-field
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-api-helloasso-token-triggered
     */
    public function appendFormatPaymentForField(string $paymentContent, array $params): string
    {
        $newPayment = $this->formatPaymentForField($params);
        $formattedPayments = $this->convertStringToPayments($paymentContent);
        foreach($newPayment as $k => $v) {
            $formattedPayments[$k] = $v;
        }
        return json_encode($formattedPayments);
    }

    /**
     * convert a payment raw field to payments array
     * @param string $paymentContent
     * @return array $payments
     * @throws Exception if badly formatted payment
     * Feature UUID : hpf-helloasso-payments-table
     * Feature UUID : hpf-payments-by-cat-table
     * Feature UUID : hpf-register-payment-action
     * Feature UUID : hpf-payments-field
     */
    public function convertStringToPayments(string $paymentContent): array
    {
        $formattedPayments = [];
        if (!empty($paymentContent)) {
            try {
                $jsonDecoded = json_decode($paymentContent, true);
                if (empty($jsonDecoded) || !is_array($jsonDecoded)) {
                    throw new Exception('paymentfied is not json encoded');
                } else {
                    foreach($jsonDecoded as $id => $data) {
                        if (is_array($data)) {
                            $formattedPayments[$id] = $data;
                        }
                    }
                }
            } catch (Throwable $th) {
                foreach(explode(',', $paymentContent) as $paymentRaw) {
                    $rawField = $this->formatPaymentForField([
                        'id' => $paymentRaw,
                        'origin' => 'helloasso',
                        'total' => ''
                    ]);
                    foreach($rawField as $k => $v) {
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
     * Feature UUID : hpf-helloasso-payments-table
     * Feature UUID : hpf-payments-by-cat-table
     * Feature UUID : hpf-register-payment-action
     * Feature UUID : hpf-payments-field
     * Feature UUID : hpf-api-helloasso-token-triggered
     */
    public function formatPaymentForField(array $params): array
    {
        if (empty($params['origin'])) {
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
     * Feature UUID : hpf-helloasso-payments-table
     * Feature UUID : hpf-payments-by-cat-table
     * Feature UUID : hpf-register-payment-action
     * Feature UUID : hpf-payments-field
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
    ): array {
        if (!empty($payment)) {
            $id = $payment->id;
            $date = $payment->date;
            $total = ($payment->status === 'Authorized') ? strval($payment->amount) : '0';
        }
        $formattedPayment = [
            $id => [
                'date' => $date,
                'origin' => $origin,
                'total' => $total,
            ]
        ];
        if (!empty($annee_adhesion) && !empty($valeur_adhesion)) {
            $formattedPayment[$id]['adhesion'][$annee_adhesion] = $valeur_adhesion;
        }
        if (!empty($annee_adhesion_groupe) && !empty($annee_adhesion_groupe)) {
            $formattedPayment[$id]['adhesion_groupe'][$annee_adhesion_groupe] = $valeur_adhesion_groupe;
        }
        if (!empty($annee_don) && !empty($valeur_don)) {
            $formattedPayment[$id]['don'][$annee_don] = $valeur_don;
        }
        return $formattedPayment;
    }

    /**
     * Feature UUID : hpf-payment-status-action
     * Feature UUID : hpf-api-helloasso-token-triggered
     */
    protected function isAlreadyRegisteredPayment(array &$entry, Payment $payment): bool
    {
        $payments = $this->convertStringToPayments($entry[self::PAYMENTS_FIELDNAME] ?? '');
        return array_key_exists($payment->id, $payments);
    }

    /**
     * Feature UUID : hpf-payments-field
     */
    public function getPaymentInfos(string $id): array
    {
        if (empty($id)) {
            throw new Exception("id should not be empty");
        }
        $data = [
            'found' => false,
            'id' => $id
        ];
        try {
            $payment = $this->helloAssoService->getPayment($id);
            if (!empty($payment) && $payment instanceof Payment) {
                $data['found'] = true;
                $data = array_merge($data, $payment->jsonSerialize());
                if (!empty($data['formSlug'])) {
                    $sameSlugForms = array_filter(
                        $this->getCurrentPaymentsFormIds(),
                        function ($formId) use ($data) {
                            $form = $this->getPaymentForm($formId);
                            return $form['formSlug'] == $data['formSlug'];
                        }
                    );
                    if (!empty($sameSlugForms)) {
                        $data['form'] = $sameSlugForms[0];
                    } elseif ($this->isDonationFormType($data['formType'] ?? '')) {
                        $data['form'] = 'donation';
                    }
                }
            }
        } catch (Throwable $th) {
        }

        return $data;
    }

    /**
     * Feature UUID : hpf-payments-field
     */
    public function getPaymentsViaEmail(string $email): array
    {
        if (empty($email)) {
            throw new Exception("email should not be empty");
        }
        $results = $this->helloAssoService->getPayments([
            'email' => $email,
        ]);
        return empty($results) ? [] : array_map(
            [$this,'extractAssociatedEntries'],
            $results->getPayments()
        );
    }

    /**
     * Feature UUID : hpf-payments-field
     * @param Payment $payment
     * @return array
     */
    protected function extractAssociatedEntries(Payment $payment): array
    {
        $extractedPayment = $payment->jsonSerialize();
        $extractedPayment['associatedEntry'] = $this->extractAssociatedEntryName($payment);
        return $extractedPayment;
    }

    /**
     * Feature UUID : hpf-payments-field
     * @param Payment $payment
     * @return string
     */
    protected function extractAssociatedEntryName(Payment $payment): string
    {
        $match = [];
        return (
            !empty($payment->description)
            && preg_match(
                '/^' . str_replace(
                    'AAAAAEntryId',
                    '(.+)',
                    preg_quote(_t('HPF_DIRECT_PAYMENT_TITLE', ['entryId' => 'AAAAAEntryId']), '/')
                ) . '$/',
                $payment->description,
                $match
            )
        )
            ? $match[1]
            : '';
    }

    /**
     * Feature UUID : hpf-helloasso-payments-table
     * Feature UUID : hpf-payments-by-cat-table
     */
    public function refreshPaymentCache(
        array $formsIds,
        string $college3to4fieldname,
        bool $byCat = false
    ): array {
        $code = 200;
        $output = [];
        $payments = [];
        $currentYear = intval((new DateTime())->format('Y'));
        for ($y = 2022; $y <= ($currentYear + 1) ; $y++) {
            $payments[strval($y)] = $byCat ? $this->getDefaultPaymentsByCat() : $this->getDefaultPayments();
        }
        $fieldCache = [];
        foreach($formsIds as $college => $formId) {
            $entries = $this->entryManager->search([
                'formsIds' => [$formId]
            ]);
            if (!empty($entries)) {
                foreach ($entries as $entry) {
                    if ($byCat) {
                        $this->updatePaymentsByCat($payments, $college, $entry, $fieldCache, $college3to4fieldname);
                    } else {
                        $this->updatePayments($payments, $college, $entry, $fieldCache, $college3to4fieldname);
                    }
                }
            }
        }
        $this->registerCache($payments, $byCat);
        $output['message'] = 'ok';
        $output['newtoken'] = $this->csrfTokenManager->refreshToken($byCat
            ? 'refresh-payments-by-cat-cache-token'
            : 'refresh-payment-cache-token')->getValue();
        return compact(['code','output']);
    }

    /**
     * Feature UUID : hpf-payments-by-cat-table
     */
    protected function getDefaultPaymentsByCat(): array
    {
        $defaultPayment = [];
        for ($i = 1; $i <= 4; $i++) {
            $defaultPayment["$i"] = self::DEFAULT_BASE_PAYMENT;
        }
        $defaultPayment['d'] = self::DEFAULT_BASE_PAYMENT;
        $defaultPayment['p'] = self::DEFAULT_BASE_PAYMENT;

        $defaultPayments = [];
        foreach (self::AREAS as $areaCode => $depts) {
            $defaultPayments[$areaCode] = $defaultPayment;
            foreach($depts as $dept) {
                $defaultPayments[$dept] = $defaultPayment;
            }
        }
        return $defaultPayments;
    }

    /**
     * Feature UUID : hpf-helloasso-payments-table
     */
    protected function getDefaultPayments(): array
    {
        $defaultPayment = [];
        for ($i = 1; $i <= 12; $i++) {
            $defaultPayment["$i"] = [
                'v' => 0,
                'e' => []
            ];
        }
        $defaultPayment['o'] = [
            'v' => 0,
            'e' => []
        ];
        return [
            "1" => $defaultPayment,
            "2" => $defaultPayment,
            "3" => $defaultPayment,
            "4" => $defaultPayment,
            "5" => $defaultPayment,
            "d" => $defaultPayment
        ];
    }

    /**
     * Feature UUID : hpf-helloasso-payments-table
     */
    protected function updatePayments(array &$payments, string $college, array $entry, array &$fieldCache, string $college3to4fieldname)
    {
        if (empty($entry['id_typeannonce'])) {
            return;
        }
        try {
            $isCb = $this->checkIfIsCB($entry, $fieldCache);
        } catch (Throwable $th) {
            return ;
        }
        $data = $this->getFirstData(
            $entry,
            $fieldCache,
            $payments,
            $this->getDefaultPayments()['1'],
            function ($subpartData, $value, $fieldName) {
                $subpartData['o']['v'] = $value;
                return $subpartData;
            },
            $isCb
        );

        $associations = $this->getAssociations($entry, $fieldCache, $college, $college3to4fieldname);

        $this->extractPayments(
            $entry,
            $fieldCache,
            $data,
            $associations,
            function ($paymentOrigin) {
                return substr($paymentOrigin, 0, strlen('helloasso')) === 'helloasso';
            },
            function ($subPartData, $date, $year, $paymentOrigin, $value, $fieldName) {
                $month = strval(intval($date->format('m')));
                $paymentYear = $date->format('Y');
                if (array_key_exists($paymentYear, $subPartData)) {
                    $subPartData[$paymentYear][$month]['v'] = $subPartData[$paymentYear][$month]['v'] + $value;
                }
                if (array_key_exists($year, $subPartData)) {
                    $subPartData[$year]['o']['v'] = max(0, $subPartData[$year]['o']['v'] - $value);
                }
                return $subPartData;
            }
        );

        // append in dedicated date
        foreach($associations as $fieldName => $destinationKey) {
            foreach($data[$fieldName] as $year => $values) {
                foreach($values as $month => $value) {
                    $payments[$year][$destinationKey][$month]['v'] = $payments[$year][$destinationKey][$month]['v'] + $value['v'];
                    if (!empty($value['v']) && !empty($entry['id_fiche']) && !in_array($entry['id_fiche'], $payments[$year][$destinationKey][$month]['e'])) {
                        $payments[$year][$destinationKey][$month]['e'][] = $entry['id_fiche'];
                    }
                }
            }
        }
    }

    /**
     * Feature UUID : hpf-payments-by-cat-table
     */
    protected function updatePaymentsByCat(array &$payments, string $college, array $entry, array &$fieldCache, string $college3to4fieldname)
    {
        if (empty($entry['id_typeannonce'])) {
            return;
        }
        try {
            $keyForPaymentType = $this->getdefaultPaymentType($entry, $fieldCache);
        } catch (Throwable $th) {
            return;
        }


        $associations = array_map(
            function ($assoc) {
                return $assoc === 'partner' ? 'p' : $assoc;
            },
            $this->getAssociations($entry, $fieldCache, $college, $college3to4fieldname)
        );

        $areaData = $this->prepareAreaData($entry, $fieldCache, $college);

        $data = $this->getFirstData(
            $entry,
            $fieldCache,
            $payments,
            $this->getDefaultPaymentsByCat(),
            function ($subpartData, $value, $fieldName) use ($college, $associations, $keyForPaymentType, $areaData) {
                $type = $this->getTypeForCat($fieldName, $college, $associations[$fieldName]);
                if (!isset($subpartData['default'])) {
                    $subpartData['default'] = $this->getDefaultPaymentsByCat()['sans'];
                }
                $subpartData['default'][$type][$keyForPaymentType][0] = $value;
                $subpartData['default'][$type][$keyForPaymentType][1] = ($value == 0) ? 0 : 1;
                return $subpartData;
            },
            true
        );

        $this->extractPayments(
            $entry,
            $fieldCache,
            $data,
            $associations,
            function ($paymentOrigin) {
                return true;
            },
            function ($subPartData, $date, $year, $paymentOrigin, $value, $fieldName) use ($associations, $college, $keyForPaymentType) {
                if (!isset($subpartData['default'])) {
                    $subpartData['default'] = $this->getDefaultPaymentsByCat()['sans'];
                }
                $paymentYear = $date->format('Y');
                $type = $this->getTypeForCat($fieldName, $college, $associations[$fieldName]);
                $paymentType = $this->formatPaymentType($paymentOrigin);
                if ($value != 0) {
                    // remove previous registered from default
                    if (array_key_exists($year, $subPartData)) {
                        $subPartData[$year]['default'][$type][$keyForPaymentType][0] = max($subPartData[$year]['default'][$type][$keyForPaymentType][0] - $value, 0);
                        $subPartData[$year]['default'][$type][$keyForPaymentType][1] = max($subPartData[$year]['default'][$type][$keyForPaymentType][1] - 1, 0);
                    }
                    if (array_key_exists($paymentYear, $subPartData)) {
                        // register on right payment type
                        $subPartData[$paymentYear]['default'][$type][$paymentType][0] = $subPartData[$paymentYear]['default'][$type][$paymentType][0] + $value;
                        $subPartData[$paymentYear]['default'][$type][$paymentType][1] = $subPartData[$paymentYear]['default'][$type][$paymentType][1] + 1;
                        if ($paymentType != $keyForPaymentType) {
                            $subPartData[$paymentYear]['default'][$type][$keyForPaymentType][0] = max($subPartData[$paymentYear]['default'][$type][$keyForPaymentType][0] - $value, 0);
                            $subPartData[$paymentYear]['default'][$type][$keyForPaymentType][1] = max($subPartData[$paymentYear]['default'][$type][$keyForPaymentType][1] - 1, 0);
                        }
                    }
                }
                return $subPartData;
            }
        );

        // append in dedicated area
        foreach($associations as $fieldName => $destinationKey) {
            foreach($data[$fieldName] as $year => $values) {
                foreach($values as $zoneCode => $value) {
                    $type = $this->getTypeForCat($fieldName, $college, $destinationKey);
                    if (isset($value[$type])) {
                        foreach($value[$type] as $paymentType => $v) {
                            $localZoneCode = ($zoneCode !== 'default') ? $zoneCode : $areaData[$fieldName]['area'];
                            $payments[$year][$localZoneCode][$type][$paymentType][0] = $payments[$year][$localZoneCode][$type][$paymentType][0] + $v[0];
                            $payments[$year][$localZoneCode][$type][$paymentType][1] = $payments[$year][$localZoneCode][$type][$paymentType][1] + $v[1];
                            if($zoneCode === 'default' && $areaData[$fieldName]['dept'] !== '') {
                                $localZoneCode = $areaData[$fieldName]['dept'];
                                $payments[$year][$localZoneCode][$type][$paymentType][0] = $payments[$year][$localZoneCode][$type][$paymentType][0] + $v[0];
                                $payments[$year][$localZoneCode][$type][$paymentType][1] = $payments[$year][$localZoneCode][$type][$paymentType][1] + $v[1];
                            }
                        }
                    }
                }
            }
        }
    }


    /**
     * prepare area data
     * @param array $entry
     * @param array &$fieldCache
     * @param string $college
     * @return array [$fieldname => [string $area,string $dept]]
     * Feature UUID : hpf-payments-by-cat-table
     */
    protected function prepareAreaData(array $entry, array &$fieldCache, string $college): array
    {
        $keys = ['membership','group_membership','donation'];
        return array_combine(
            ['membership','group_membership','donation'],
            array_map(
                function ($fieldname) use ($entry, &$fieldCache, $college) {
                    return $this->extractArea($entry, $fieldCache, $fieldname, $college);
                },
                ['membership','group_membership','donation']
            )
        );
    }
    /**
     * extract area
     * @param array $entry
     * @param array &$fieldCache
     * @param string $fieldName
     * @param string $college
     * @return array [string $area,string $dept]
     * Feature UUID : hpf-payments-by-cat-table
     */
    protected function extractArea(array $entry, array &$fieldCache, string $fieldName, string $college): array
    {
        $area = 'sans';
        $dept = 0;
        if (in_array($fieldName, ['donation','membership','group_membership'], true)) {

            // special case 'donation'
            if ($fieldName === 'donation') {
                $data = $this->extractArea($entry, $fieldCache, 'membership', $college);
                if (!empty($data['area']) && $data['area'] !== 'sans') {
                    return $data;
                }
                // backup on group
                return $this->extractArea($entry, $fieldCache, 'group_membership', $college);
            }

            $defaultPayments = $this->getDefaultPaymentsByCat();

            if ($college != '3') {
                $areaFieldName = self::AREA_FIELDNAMES[$fieldName][0];
                $deptFieldName = self::AREA_FIELDNAMES[$fieldName][1];
            } else {
                $areaFieldName = self::AREA_FIELDNAMES['backupfor3'][0];
                $deptFieldName = self::AREA_FIELDNAMES['backupfor3'][1];
            }
            $deptPropertyName = '';
            try {
                $deptPropertyName = $this->getPropertyNameFromFormOrCache($entry['id_typeannonce'], $fieldCache, $deptFieldName);
            } catch (Throwable $th) {
            }
            if (!empty($deptPropertyName)
                && !empty($entry[$deptPropertyName])
                && (
                    substr($deptPropertyName, 0, 10) === 'listeListe'
                    || substr($deptPropertyName, 0, 13) === 'checkboxListe'
                )) {
                $deptTMP = explode(',', $entry[$deptPropertyName])[0];
                if (array_key_exists($deptTMP, $defaultPayments)) {
                    $dept = $deptTMP;
                    foreach (self::AREAS as $areaCode => $depts) {
                        if ($area === 'sans' && in_array($dept, $depts)) {
                            $area = $areaCode;
                        }
                    }
                    if ($area === 'sans') {
                        $dept = 0;
                    }
                }
            } else {
                $areaPropertyName = '';
                try {
                    $areaPropertyName = $this->getPropertyNameFromFormOrCache($entry['id_typeannonce'], $fieldCache, $areaFieldName);
                } catch (Throwable $th) {
                }
                if (!empty($areaPropertyName)
                    && substr($areaPropertyName, 0, 10) === 'listeListe'
                    && isset($entry[$areaPropertyName])
                    && array_key_exists($entry[$areaPropertyName], $defaultPayments)) {
                    $area = $entry[$areaPropertyName];
                    $dept = '';
                }
            }

        }
        return compact(['area','dept']);
    }

    /**
     * convertfieldName and college to type
     * @param string $fieldName
     * @param string $college
     * @param string $destinationKey
     * @return string
     * Feature UUID : hpf-payments-by-cat-table
     */
    protected function getTypeForCat(string $fieldName, string $college, string $destinationKey): string
    {
        return ($fieldName === 'donation')
            ? 'd'
            : (
                strval($college) === '5'
                ? 'p'
                : $destinationKey
            );
    }

    /**
     * get defaultPaymentType key from entry
     * @param array $entry
     * @param array &$fieldCache
     * @return string
     * @throws Exception if payment field not found
     * Feature UUID : hpf-payments-by-cat-table
     */
    protected function getdefaultPaymentType(array $entry, array &$fieldCache): string
    {
        $paymentType = $this->extractPaymentType($entry, $fieldCache);
        return $this->formatPaymentType($paymentType);
    }

    /**
     * format Payment Type
     * @param string $paymentType
     * @return string
     * Feature UUID : hpf-payments-by-cat-table
     */
    protected function formatPaymentType(string $paymentType): string
    {
        $keyForPaymentType = 'i';
        foreach (self::PAYMENT_TYPE_ASSOCIATION as $searchRegExp => $newKey) {
            if ($keyForPaymentType === 'i' && preg_match($searchRegExp, $paymentType)) {
                $keyForPaymentType = $newKey;
            }
        }
        return $keyForPaymentType;
    }

    /**
     * check if entry is payed by CB
     * @param array $entry
     * @param array &$fieldCache
     * @return bool
     * @throws Exception if payment field not found
     * Feature UUID : hpf-helloasso-payments-table
     */
    protected function checkIfIsCB(array $entry, array &$fieldCache): bool
    {
        $paymentType = $this->extractPaymentType($entry, $fieldCache);
        return (!empty($paymentType) && $paymentType == self::CB_TYPE_PAYMENT_FIELDVALUE);
    }


    /**
     * extract payment type from entry
     * @param array $entry
     * @param array &$fieldCache
     * @return string
     * @throws Exception if payment field not found
     * Feature UUID : hpf-helloasso-payments-table
     * Feature UUID : hpf-payments-by-cat-table
     */
    protected function extractPaymentType(array $entry, array &$fieldCache): string
    {
        $paymentTypePropertyName = $this->getPropertyNameFromFormOrCache($entry['id_typeannonce'], $fieldCache, self::TYPE_PAYMENT_FIELDNAME);
        if (empty($paymentTypePropertyName)) {
            throw new Exception('PaymentTypeField not found');
        }
        return !is_string($entry[$paymentTypePropertyName]) ? '' : $entry[$paymentTypePropertyName];
    }

    /**
     * extract first data from entry
     * @param array $entry
     * @param array &$fieldCache
     * @param array $payments
     * @param array $defaultPayment
     * @param callable $setdata
     * @param bool $shouldExtractData
     * @return array
     * Feature UUID : hpf-helloasso-payments-table
     * Feature UUID : hpf-payments-by-cat-table
     */
    protected function getFirstData(array $entry, array &$fieldCache, array $payments, array $defaultPayment, $setdata, bool $shouldExtractData = true): array
    {
        $years = array_keys($payments);

        $data = [];
        foreach(['membership','group_membership','donation'] as $fieldName) {
            // init data
            $data[$fieldName] = array_fill_keys($years, $defaultPayment);
            if ($shouldExtractData) {
                foreach($years as $year) {
                    $fullFieldName = str_replace('{year}', $year, self::PAYED_FIELDNAMES[$fieldName]);
                    try {
                        $propertyName = $this->getPropertyNameFromFormOrCache($entry['id_typeannonce'], $fieldCache, $fullFieldName);
                        if (is_callable($setdata)) {
                            $data[$fieldName][$year] = $setdata($data[$fieldName][$year], floatval($entry[$propertyName] ?? 0), $fieldName);
                        }
                    } catch (Throwable $th) {
                    }
                }
            }
        }
        return $data;
    }

    /**
     * get Associations to right college
     * @param array $entry
     * @param array &$fieldCache
     * @param string $college
     * @param string $college3to4fieldname
     * @return array
     * Feature UUID : hpf-helloasso-payments-table
     * Feature UUID : hpf-payments-by-cat-table
     */
    protected function getAssociations(array $entry, array &$fieldCache, string $college, string $college3to4fieldname): array
    {
        // update college
        try {
            $collegeAdhesionpropertyName = empty($college3to4fieldname)
                ? ''
                : $this->getPropertyNameFromFormOrCache($entry['id_typeannonce'], $fieldCache, $college3to4fieldname);
        } catch (Throwable $th) {
            $collegeAdhesionpropertyName = '';
        }

        $updatedCollege = (
            $college == '3'
                && !empty($collegeAdhesionpropertyName)
                && !empty($entry[$collegeAdhesionpropertyName])
                && is_scalar($entry[$collegeAdhesionpropertyName])
                && $entry[$collegeAdhesionpropertyName] == '4'
        )
            ? '4'
            : $college;

        return [
            'membership' => $updatedCollege,
            'group_membership' => $college == '1' ? '2' : $updatedCollege,
            'donation' => 'd'
        ];
    }

    /**
     * extract payments
     * @param array $entry
     * @param array &$fieldCache
     * @param array &$data
     * @param array $associations
     * @param callable $isRightPayment
     * @param callable $affectValue
     * Feature UUID : hpf-helloasso-payments-table
     * Feature UUID : hpf-payments-by-cat-table
     */
    protected function extractPayments(
        array $entry,
        array &$fieldCache,
        array &$data,
        array $associations,
        $isRightPayment = null,
        $affectValue = null
    ) {
        // extract payments
        try {
            $propertyName = $this->getPropertyNameFromFormOrCache($entry['id_typeannonce'], $fieldCache, self::PAYMENTS_FIELDNAME);
        } catch (Throwable $th) {
            $propertyName = '';
        }
        if(!empty($propertyName) && !empty($entry[$propertyName])) {
            $paymentsFromField = $this->convertStringToPayments($entry[$propertyName]);
            foreach($paymentsFromField as $id => $payment) {
                if (!empty($payment['date'])
                    && !empty($payment['total'])
                    && !empty($payment['origin'])
                    && (
                        is_null($isRightPayment)
                        || !is_callable($isRightPayment)
                        || $isRightPayment($payment['origin'])
                    )) {
                    $date = new DateTime($payment['date']);
                    if (!empty($date)) {
                        foreach($associations as $fieldName => $destinationKey) {
                            switch ($fieldName) {
                                case 'membership':
                                    $localFieldName = 'adhesion';
                                    break;
                                case 'group_membership':
                                    $localFieldName = 'adhesion_groupe';
                                    break;
                                case 'donation':
                                    $localFieldName = 'don';
                                    break;
                                default:
                                    $localFieldName = '';
                                    break;
                            }
                            if(!empty($payment[$localFieldName])) {
                                foreach($payment[$localFieldName] as $year => $value) {
                                    if (is_callable($affectValue)) {
                                        $data[$fieldName] = $affectValue(
                                            $data[$fieldName],
                                            $date,
                                            $year,
                                            $payment['origin'],
                                            floatval($value),
                                            $fieldName
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

    }

    /**
     * Feature UUID : hpf-helloasso-payments-table
     * Feature UUID : hpf-payments-by-cat-table
     */
    protected function getPropertyNameFromFormOrCache(string $formId, array &$fieldCache, string $name): string
    {
        if (empty($fieldCache[$formId])) {
            $fieldCache[$formId] = [];
        }
        if (empty($fieldCache[$formId][$name])) {
            $fieldCache[$formId][$name] = $this->formManager->findFieldFromNameOrPropertyName(
                $name,
                $formId
            );
        }
        if (empty($fieldCache[$formId][$name])) {
            throw new Exception("Not found field '$name' for form '$formId'");
        }
        $paymentTypePropertyName = $fieldCache[$formId][$name]->getPropertyName();

        if (empty($paymentTypePropertyName)) {
            throw new Exception("Empty property name");
        }
        return $paymentTypePropertyName;
    }

    /**
     * Feature UUID : hpf-helloasso-payments-table
     * Feature UUID : hpf-payments-by-cat-table
     */
    protected function registerCache(array $payments, bool $byCat = false)
    {
        $date = (new DateTime())->format('d-m-Y H:i:s');
        $prop = $byCat
            ? self::HELLOASSO_HPF_PAYMENTS_BY_CAT_CACHE_PROPERTY
            : self::HELLOASSO_HPF_PAYMENTS_CACHE_PROPERTY;
        $previousTriples = $this->tripleStore->getMatching(
            null,
            $prop,
            null
        );
        $triples = [];
        if (!empty($previousTriples)) {
            // clean cache if not present in payments
            foreach($previousTriples as $triple) {
                if (!array_key_exists($triple['resource'], $payments)) {
                    try {
                        $this->tripleStore->delete(
                            $triple['resource'],
                            $triple['property']
                        );
                    } catch (Throwable $th) {
                        //throw $th;
                    }
                } else {
                    $triples[strval($triple['resource'])] = $triple;
                }
            }
        }
        foreach($payments as $year => $values) {
            $newValue = json_encode([
                'date' => $date,
                'values' => $values
            ]);
            try {
                if (array_key_exists($year, $triples)) {
                    $this->tripleStore->update(
                        $triples[$year]['resource'],
                        $triples[$year]['property'],
                        $triples[$year]['value'],
                        $newValue,
                        '',
                        ''
                    );
                } else {
                    $this->tripleStore->create(
                        $year,
                        $prop,
                        $newValue,
                        '',
                        ''
                    );
                }
            } catch(Throwable $th) {
            }
        }
    }

    /**
     * find other entries with payment already registered
     * Feature UUID : hpf-payments-field
     * Feature UUID : hpf-api-helloasso-token-triggered
     * @param string $paymentId
     * @return array
     */
    public function findEntriesWithSamePayment(string $paymentId): array
    {
        if (empty($paymentId)) {
            return [];
        }
        $entries = $this->entryManager->search([
            'queries' => [
                self::PAYMENTS_FIELDNAME => ".*$paymentId.*"
            ]
        ]);
        $fieldsCache = [];
        return array_filter(
            $entries,
            function ($entry) use (&$fieldsCache, $paymentId) {
                return $this->isPaymentInEntry($paymentId, $entry, $fieldsCache);
            }
        );
    }

    /**
     * find other entries with payment already registered
     * Feature UUID : hpf-payments-field
     * Feature UUID : hpf-api-helloasso-token-triggered
     * @param string $paymentId
     * @param array $entry
     * @param array $fieldsCache
     * @return bool
     */
    protected function isPaymentInEntry(
        string $paymentId,
        array $entry,
        array &$fieldsCache
    ): bool {
        if (!empty($entry['id_typeannonce']) && !empty($entry[self::PAYMENTS_FIELDNAME])) {
            if (!array_key_exists($entry['id_typeannonce'], $fieldsCache)) {
                $fieldsCache[$entry['id_typeannonce']] = $this->formManager->findFieldFromNameOrPropertyName(self::PAYMENTS_FIELDNAME, $entry['id_typeannonce']);
            }
            $field = $fieldsCache[$entry['id_typeannonce']];
            if (!empty($field) && $field instanceof PaymentsField) {
                $payments = $this->convertStringToPayments($entry[self::PAYMENTS_FIELDNAME]);
                if (array_key_exists($paymentId, $payments)) {
                    return true;
                }
            }
        }
        return false;
    }
}
