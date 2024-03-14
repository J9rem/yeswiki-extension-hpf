<?php

/*
 * This file is part of the YesWiki Extension hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-direct-payment-helloasso
 */

namespace YesWiki\Hpf;

use Exception;
use Throwable;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Hpf\Exception\ExceptionWithMessage;
use YesWiki\Hpf\Service\HpfService;

class DirectPaymentHandler extends YesWikiHandler
{
    public const KEY_IN_GET_FOR_AMOUNT = 'amountInCents';
    public const KEY_IN_GET_FOR_STATUS = 'status';

    protected $aclService;
    protected $authController;
    protected $entryManager;
    protected $formManager;
    protected $hpfService;

    public function run()
    {
        // get services
        $this->aclService = $this->getService(AclService::class);
        $this->authController = $this->getService(AuthController::class);
        $this->entryManager = $this->getService(EntryManager::class);
        $this->formManager = $this->getService(FormManager::class);
        $this->hpfService = $this->getService(HpfService::class);

        try {
            list(
                'entry' => $entry,
                'formId' => $formId
            ) = $this->getEntryAndFormIdSecured();

            $data = $this->extractData($entry);
            $data = $this->appendDataForPayment($entry, $data);

            $headersData = $this->extractHeadersFromGet($_GET ?? [], $entry, $data);

            if ($headersData['checkData']) {
                $this->checkData($data, true);
            }

            $output = $headersData['headerContent']
                .$this->callAction('helloassodirectpayment', $data);

        } catch (ExceptionWithMessage $ex) {
            $output = $this->render('@templates/alert-message.twig', [
                'type' => $ex->getTypeForMessage(),
                'message' => $ex->getMessage()
            ]);
        }

        return $this->finalRender($output);
    }

    protected function finalRender(string $content, bool $includePage = true): string
    {
        $output = $includePage
            ? <<<HTML
            <div class="page">
                $content
            </div>
            HTML
            : $content;
        return $this->wiki->Header().$content.$this->wiki->Footer() ;
    }

    /**
     * extract params from $_GET and set message if needed
     * @param array $get
     * @param array $entry
     * @param array $data
     * @return array [
     *  'checkData' => boolean,
     *  'headerContent' => string
     * ]
     * @throws ExceptionWithMessage
     */
    protected function extractHeadersFromGet(array $get, array $entry, array $data): array
    {
        $headersData = [
                'checkData' => true,
                'headerContent' => ''
            ];
        if (!empty($get[self::KEY_IN_GET_FOR_STATUS])
            && in_array($get[self::KEY_IN_GET_FOR_STATUS], ['success', 'error', 'cancel'], true)) {

            $headersData['checkData'] = false;
            $this->checkData($data, false);

            $amountInCents = (
                empty($get[self::KEY_IN_GET_FOR_AMOUNT])
                    || !is_scalar($get[self::KEY_IN_GET_FOR_AMOUNT])
                    || (intval($get[self::KEY_IN_GET_FOR_AMOUNT]) <= 0)
            ) ? 0
                : intval($get[self::KEY_IN_GET_FOR_AMOUNT]);

            if ($amountInCents == 0) {
                if ($data['totalInCents'] > 0) {
                    $amountInCents = $data['totalInCents'];
                } else {
                    $amountInCents = $this->getLastPaymentFromEntry($entry);
                }
            }
            $entryLinkTxt = _t('HPF_DIRECT_PAYMENT_LINK_TO_ENTRY', [
                'title' => $entry['bf_titre']
            ]);
            $entryLink = $this->callAction('button', [
                'class' => "btn-primary btn-xs",
                'title' => $entryLinkTxt,
                'text' => $entryLinkTxt,
                'link' => $entry['id_fiche'],
                'icon' => 'fa fa-eye'
            ]);

            switch ($get[self::KEY_IN_GET_FOR_STATUS]) {
                case 'success':
                    $type = 'success';
                    $message = _t('HPF_DIRECT_PAYMENT_SUCCESS', [
                        'ofAmount' => ($amountInCents != 0) ? $this->amountToStr($amountInCents) : '',
                        'warningMessage' => ($data['totalInCents'] > 0)
                            ? ('<b>' ._t('HPF_DIRECT_PAYMENT_SUCCESS_WARNING'). '</b>')
                            : '',
                        'entryLink' => $entryLink
                    ]);
                    $isDirect = true;
                    break;
                case 'cancel':
                    $type = 'info';
                    $message = _t('HPF_DIRECT_PAYMENT_CANCEL', [
                        'specificMessage' => ($data['totalInCents'] > 0)
                            ? ('<b>' ._t('HPF_DIRECT_PAYMENT_CANCEL_REDO', [
                                'ofAmount' => $this->amountToStr($data['totalInCents']),
                            ]). '</b>')
                            : _t('HPF_DIRECT_PAYMENT_CANCEL_NOTHING_TO_PAY'),
                        'entryLink' => $entryLink
                    ]);
                    $isDirect = ($amountInCents == 0);
                    break;
                    
                case 'error':
                default:
                    $type = 'warning';
                    $message = _t('HPF_DIRECT_PAYMENT_ERROR', [
                        'ofAmount' => ($amountInCents != 0) ? $this->amountToStr($amountInCents) : '',
                        'specificMessage' => ($data['totalInCents'] > 0)
                            ? ('<b>' ._t('HPF_DIRECT_PAYMENT_CANCEL_REDO', [
                                'ofAmount' => $this->amountToStr($data['totalInCents']),
                            ]). '</b>')
                            : _t('HPF_DIRECT_PAYMENT_CANCEL_NOTHING_TO_PAY'),
                        'entryLink' => $entryLink
                    ]);
                    $isDirect = ($amountInCents == 0);
                    break;
            }
            $message = str_replace("\n", '<br>', $message);

            if ($isDirect) {
                throw new ExceptionWithMessage($message, $type);
            } else {
                $headersData['headerContent'] = $this->render('@templates/alert-message.twig', [
                    'type' => $type,
                    'message' => $message
                ]);
            }
        }
        return $headersData;
    }

    /**
     * transform integer value of amount in string with 'of' at begining
     * @param int $amountInCents
     * @return string
     */
    protected function amountToStr(int $amountInCents): string
    {
        return _t('HPF_DIRECT_PAYMENT_OF') . ' '
            .floor($amountInCents/100).','
            . (($amountInCents % 100) < 10 ? '0' : '')
            . ($amountInCents % 100)
            .' € ';
    }

    /**
     * get entry and FormId secured
     * @return array [
     *  'entry' => array,
     *  'formId' => string
     * ]
     * @throws ExceptionWithMessage
     */
    protected function getEntryAndFormIdSecured(): array
    {
        $output = [
            'entry' => [],
            'formId' => ''
        ];
        // check current user is owner
        if (!$this->aclService->hasAccess('read')) {
            throw new ExceptionWithMessage(_t('DENY_READ'));
        }
        // check if current page is an entry
        $tag = $this->wiki->GetPageTag();
        if (empty($tag) || !$this->entryManager->isEntry($tag)) {
            throw new ExceptionWithMessage(_t('HPF_SHOULD_BE_AN_ENTRY'));
        }
        // check current user is owner
        if (!$this->wiki->UserIsOwner($tag)) {
            throw new ExceptionWithMessage(_t('DELETEPAGE_NOT_OWNER'));
        }
        // prepare for action
        $output['entry'] = $this->entryManager->getOne($tag);
        $output['formId'] = $output['entry']['id_typeannonce'] ?? '';

        $contribFormIds = $this->hpfService->getCurrentPaymentsFormIds();
        if (!in_array($output['formId'], $contribFormIds)) {
            throw new ExceptionWithMessage(_t('HPF_SHOULD_BE_AN_ENTRY_FOR_PAYMENT'));
        }

        $form = $this->formManager->getOne($output['formId']);
        if (empty($form['bn_only_one_entry']) || $form['bn_only_one_entry'] !== 'Y') {
            throw new ExceptionWithMessage(_t('HPF_SHOULD_BE_AN_ENTRY_FOR_FORM_WITH_UNIQ_ENTRY_BY_USER'));
        }

        return $output;
    }

    /**
     * check if all is right in data
     * @param array $data
     * @param bool $checkIfEmpty
     * @throws ExceptionWithMessage
     */
    protected function checkData(array $data, bool $checkIfEmpty)
    {
        if ($checkIfEmpty && $data['totalInCents'] <= 0) {
            throw new ExceptionWithMessage(_t('HPF_NOTHING_TO_PAY'), 'info');
        }

        $user = $this->authController->getLoggedUser();
        if (
            empty($user['email'])
            || empty($data['email'])
            || $user['email'] != $data['email']
        ) {
            throw new ExceptionWithMessage(_t('HPF_CURRENT_USER_SHOULD_HAVE_SAME_EMAIL_AS_ENTRY'));
        }

        // all is Right
    }

    /**
     * extract useful data from entry
     * @param array $entry
     * @return array [
     *  'firstName' => string,
     *  'lastName' => string,
     *  'zipCode' => string,
     *  'city' => string,
     *  'email' => string,
     *  'totalInCents' => integer,
     *  'membershipInCents' => integer,
     *  'groupmembershipInCents' => integer,
     *  'donationInCents' => integer
     * ]
     */
    protected function extractData(array $entry): array
    {
        $associations = [
            'firstName' => 'bf_prenom',
            'lastName' => 'bf_nom',
            'zipCode' => 'bf_code_postal',
            'city' => 'bf_ville',
            'email' => 'bf_mail'
        ];
        $data = [];
        foreach ($associations as $key => $wantedFieldName) {
            $data[$key] = empty($entry[$wantedFieldName])
                ? ''
                : $entry[$wantedFieldName];
        }

        // payment
        foreach (['total','membership','donation','group_membership'] as $key) {
            $keyInData = str_replace('_', '', $key). 'InCents';
            $data[$keyInData] = intval(
                round(
                    floatval($entry[HpfService::CALC_FIELDNAMES[$key]] ?? 0) * 100
                )
            );
        }

        return $data;
    }

    /**
     * append data for payment
     * @param array $data
     * @param array $entry
     * @return array $dataUpdated
     */
    protected function appendDataForPayment(array $entry, array $data): array
    {
        $dataUpdated = $data;
        $dataUpdated['totalAmount'] = $data['totalInCents'];
        $dataUpdated['itemName'] = _t('HPF_DIRECT_PAYMENT_TITLE', [
            'entryId' => $entry['id_fiche']
        ]);
        $dataUpdated['containsDonation'] = ($data['donationInCents'] != 0);
        foreach ([
            'shop backUrl' => 'cancel',
            'shop errorUrl' => 'error',
            'shop returnUrl' => 'success'
        ] as $key => $status) {
            $dataUpdated[$key] = $this->wiki->Href(
                'directpayment',
                $entry['id_fiche'],
                [
                    self::KEY_IN_GET_FOR_STATUS => $status,
                    self::KEY_IN_GET_FOR_AMOUNT => $data['totalInCents']
                ],
                false
            );
        }

        $dataUpdated['meta'] = json_encode([
            'title' => $entry['bf_titre'],
            'membershipInCents' => $data['membershipInCents'],
            'groupmembershipInCents' => $data['groupmembershipInCents'],
            'donationInCents' => $data['donationInCents'],
        ]);

        return $dataUpdated;
    }

    /**
     * get last payment amount for entry
     * @param array $entry
     * @return int $amountInCents
     */
    protected function getLastPaymentFromEntry(array $entry): int
    {
        $amountInCents = 0;
        return $amountInCents;
    }
}
