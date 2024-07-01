<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-payment-status-action
 * Feature UUID : hpf-direct-payment-helloasso
 */

namespace YesWiki\Hpf;

use Throwable;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\AssetsManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Hpf\Service\HpfService;

class HPFPaymentStatusAction extends YesWikiAction
{
    protected $assetsManager;
    protected $debug;
    protected $entryManager;
    protected $formManager;
    protected $hpfService;
    protected $userManager;

    public function formatArguments($arg)
    {
        return([
            'empty_message' => $arg['empty_message'] ?? "",
            'pay_button_title' => $arg['pay_button_title'] ?? _t('HPF_PAY'),
            'nothing_to_pay_message' => $arg['nothing_to_pay_message'] ?? "",
            'view' => (empty($arg['view']) || !in_array($arg['view'], [
                'buttonHelloAsso',
                'buttonYW',
                'iframe',
                'handler' // Feature UUID : hpf-direct-payment-helloasso
            ], true)) ? "buttonHelloAsso" : $arg['view'],
            'entry_id' => isset($arg['entry_id']) && is_string($arg['entry_id']) ? $arg['entry_id'] : "",
            'formid' => isset($arg['formid']) && is_string($arg['formid']) && (strval($arg['formid']) == strval(intval($arg['formid']))) ? strval($arg['formid']) : "",
        ]);
    }

    public function run()
    {
        $this->debug = ($this->wiki->GetConfigValue('debug') == 'yes');
        // get Services
        $this->assetsManager = $this->getService(AssetsManager::class);
        $this->entryManager = $this->getService(EntryManager::class);
        $this->formManager = $this->getService(FormManager::class);
        $this->hpfService = $this->getService(HpfService::class);
        $this->userManager = $this->getService(UserManager::class);

        $user = $this->userManager->getLoggedUser();
        if (empty($user)) {
            return "";
        }

        $contribFormIds = $this->hpfService->getCurrentPaymentsFormIds();
        $contribFormId = (in_array($this->arguments['formid'], $contribFormIds))
            ? $this->arguments['formid']
            : $contribFormIds[0]
        ;
        $contribEntries = $this->hpfService->getCurrentContribEntries($contribFormId, $user['email'], $this->arguments['entry_id'], $user['name']);
        if (empty($contribEntries)) {
            $output = "";
            if (!empty($this->arguments['entry_id'])) {
                $output .= $this->updateOtherEntry($contribFormId);
            }
            $output .= empty($this->arguments['empty_message']) ? "" : $this->render("@templates/alert-message.twig", [
                'type' => 'warning',
                'message' => $this->arguments['empty_message']
            ]);
            return $output;
        }
        $contribEntry = $contribEntries[array_key_first($contribEntries)];
        if (!empty($this->arguments['entry_id']) && !in_array($this->arguments['entry_id'], array_map(function ($e) {
            return $e['id_fiche'] ?? '';
        }, $contribEntries))) {
            return $this->updateOtherEntry($contribEntry['id_typeannonce']);
        }
        $output = '';
        foreach ($contribEntries as $contribEntryInt) {
            $output .= $this->renderOneEntry($contribEntryInt, $contribFormId, $user);
        }
        return $output;

    }
    /**
     * render one entry
     * @param array $contribEntry
     * @param string $contribFormId
     * @param User|array $user
     * @return string
     */
    protected function renderOneEntry(array $contribEntry, string $contribFormId, $user): string
    {
        $previousCalcValue = $contribEntry[HpfService::CALC_FIELDNAMES["total"]] ?? 0;
        $calcValue = $this->updatePaymentsForEntry($contribEntry);

        $changedValueMsg = ($previousCalcValue == $calcValue) ? "" : $this->render(
            '@templates/alert-message.twig',
            [
                'type' => 'info',
                'message' => nl2br(str_replace(
                    '{titre}',
                    $contribEntry['bf_titre'] ?? $contribEntry['id_fiche'],
                    _t('HPF_UPDATED_ENTRY')
                )),
            ]
        );

        if (empty($calcValue) || intval($calcValue) == 0) {
            return (empty($this->arguments['nothing_to_pay_message']) ? "" :
                $this->render("@templates/alert-message.twig", [
                    'type' => 'success',
                    'message' => $this->arguments['nothing_to_pay_message']
                ])) . $changedValueMsg;
        }

        try {
            switch ($this->arguments['view']) {
                case 'buttonYW':
                    $url = $this->hpfService->getPaymentFormUrl($contribFormId);
                    $instruction = _t('HPF_CLICK_BUTTON_BOTTOM');
                    break;
                case 'iframe':
                    $html = $this->hpfService->getPaymentFormIframeHtml();
                    $instruction = _t('HPF_IFRAME_INSTRUCTION');
                    break;
                case 'handler': // Feature UUID : hpf-direct-payment-helloasso
                    $url = $this->wiki->href('directpayment', $contribEntry['id_fiche']);
                    $instruction = _t('HPF_CLICK_BUTTON_BOTTOM');
                    break;
                case 'buttonHelloASso':
                default:
                    $html = $this->hpfService->getPaymentFormButtonHtml();
                    $instruction = _t('HPF_CLICK_BUTTON_BOTTOM');
                    break;
            }
            list('paymentMessage' => $paymentMessage, 'linkToHelloAsso' => $linkToHelloAsso) =
                $this->getPaymentMessage($contribEntry, $calcValue, $user['email'], $instruction);
            if ($linkToHelloAsso) {
                switch ($this->arguments['view']) {
                    case 'buttonYW':
                    case 'handler': // Feature UUID : hpf-direct-payment-helloasso
                        return $paymentMessage . $this->callAction('button', [
                            'class' => 'btn-primary new-window',
                            'icon' => 'far fa-credit-card',
                            'link' => $url,
                            'text' => $this->arguments['pay_button_title'],
                            'title' => $this->arguments['pay_button_title'],
                        ]) . $changedValueMsg;
                    case 'iframe':
                    case 'buttonHelloASso':
                    default:
                        return $paymentMessage . $html . $changedValueMsg;
                }
            } else {
                return $paymentMessage . $changedValueMsg;
            }
        } catch (Throwable $th) {
            return empty($this->arguments['nothing_to_pay_message']) ? "" :
                $this->render("@templates/alert-message.twig", [
                    'type' => 'danger',
                    'message' => str_replace(
                        ["{file}","{line}","{message}"],
                        [$th->getFile(),$th->getLine(),$th->getMessage()],
                        _t('HPF_GET_URL_ERROR')
                    )
                ]);
        }
    }

    /**
     * @param array $entry
     * @return string|floatval $calcValue
     */
    private function updatePaymentsForEntry(array $entry)
    {
        // update CalcField before check
        $newEntry = $this->hpfService->updateCalcFields($entry);
        $previousValue = $entry[HpfService::CALC_FIELDNAMES["total"]] ?? 0;
        $newValue = $entry[HpfService::CALC_FIELDNAMES["total"]] ?? 0;
        if ($previousValue != $newValue) {
            $entry = $this->hpfService->updateEntry($newEntry);
        }

        $calcValue = $entry[HpfService::CALC_FIELDNAMES["total"]] ?? 0;

        return $calcValue;
    }

    private function updateOtherEntry(string $contribFormId): string
    {
        $output = "";
        if ($this->wiki->UserIsAdmin()) {
            $entry = $this->entryManager->getOne($this->arguments['entry_id']);
            if (!empty($entry) && !empty($entry['id_typeannonce']) && !empty($entry['bf_mail']) && $entry['id_typeannonce'] == $contribFormId) {
                $previousCalcValue = $entry[HpfService::CALC_FIELDNAMES["total"]] ?? 0;
                $newCalcValue = $this->updatePaymentsForEntry($entry);
                if ($previousCalcValue != $newCalcValue) {
                    $output .= $this->render(
                        '@templates/alert-message.twig',
                        [
                            'type' => 'info',
                            'message' => nl2br(str_replace(
                                '{titre}',
                                $entry['bf_titre'] ?? $entry['id_fiche'],
                                _t('HPF_UPDATED_ENTRY')
                            )),
                        ]
                    );
                }
            }
        }
        return $output;
    }

    protected function getPaymentMessage(array $entry, string $calcValue, string $email, string $instruction): array
    {
        $messageEntry = $this->hpfService->getPaymentMessageEntry();
        $field = $this->formManager->findFieldFromNameOrPropertyName('bf_moyen_paiement', $entry['id_typeannonce'] ?? "");
        $propertyName = (empty($field) || empty($field->getPropertyName())) ? 'bf_moyen_paiement' : $field->getPropertyName();
        $paymentMode = !empty($entry[$propertyName]) ? strval($entry[$propertyName]) : '';
        switch ($paymentMode) {
            case 'virement':
                $paymentMessage = empty($messageEntry['bf_message_virement']) ? _t('HPF_PAYMENT_MESSAGE_VIREMENT') : $messageEntry['bf_message_virement'];
                $linkToHelloAsso = false;
                break;
            case 'cheque':
                $paymentMessage = empty($messageEntry['bf_message_cheque']) ? _t('HPF_PAYMENT_MESSAGE_CHEQUE') : $messageEntry['bf_message_cheque'];
                $linkToHelloAsso = false;
                break;
            case 'cb':
                $paymentMessage = empty($messageEntry['bf_message_cb']) ? _t('HPF_PAYMENT_MESSAGE_CB') : $messageEntry['bf_message_cb'];
                $linkToHelloAsso = true;
                break;
            default:
                $paymentMessage = empty($messageEntry['bf_message_default']) ? _t('HPF_PAYMENT_MESSAGE') : $messageEntry['bf_message_default'];
                $linkToHelloAsso = true;
                break;
        }
        $hereLinkStart = <<<HTML
        <a href="{$this->wiki->Href('', "api/hpf/refresh-payment/{$entry['id_fiche']}")}" class="hpf-here-link">
        HTML;
        $hereLinkEnd = '</a>';
        $form = $this->formManager->getOne($entry['id_typeannonce']);
        $entryLink = $this->wiki->format("[[{$entry['id_fiche']} {$entry['id_fiche']}]]" . (
            !empty($form['bn_label_nature'])
            ? " ({$form['bn_label_nature']})"
            : ''
        ));
        $paymentMessage = $this->render("@templates/alert-message.twig", [
            'type' => 'secondary-2',
            'message' => str_replace(
                ['{sum}','{email}',"{instruction}","{hereLinkStart}","{hereLinkEnd}",'{entryLink}',"\n"],
                [$calcValue,$email,$instruction,$hereLinkStart,$hereLinkEnd,$entryLink,"<br/>"],
                $paymentMessage
            )
        ]);
        $this->assetsManager->AddJavascriptFile('tools/hpf/javascripts/refresh-link.js');
        return compact(['paymentMessage','linkToHelloAsso']);
    }
}
