<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Hpf;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\UserManager;
use YesWiki\Hpf\Controller\HelloAssoController;

class HPFPaymentStatusAction extends YesWikiAction
{
    protected $debug;
    protected $entryManager;
    protected $formManager;
    protected $helloAssoController;

    public function formatArguments($arg)
    {
        return([
            'empty_message' => $arg['empty_message'] ?? "",
            'pay_button_title' => $arg['pay_button_title'] ?? _t('HPF_PAY'),
            'nothing_to_pay_message' => $arg['nothing_to_pay_message'] ?? "",
            'view' => (empty($arg['view']) || !in_array($arg['view'], ['buttonHelloAsso','buttonYW','iframe'], true)) ? "buttonHelloAsso" : $arg['view'],
            'entry_id' => isset($arg['entry_id']) && is_string($arg['entry_id']) ? $arg['entry_id'] : "",
            'formid' => isset($arg['formid']) && is_string($arg['formid']) && (strval($arg['formid']) == strval(intval($arg['formid']))) ? strval($arg['formid']) : "",
        ]);
    }

    public function run()
    {
        $this->debug = ($this->wiki->GetConfigValue('debug') =='yes');
        // get Services
        $this->entryManager = $this->getService(EntryManager::class);
        $this->formManager = $this->getService(FormManager::class);
        $this->helloAssoController = $this->getService(HelloAssoController::class);
        $this->userManager = $this->getService(UserManager::class);

        $user = $this->userManager->getLoggedUser();
        if (empty($user)) {
            return "";
        }

        $contribFormIds = $this->helloAssoController->getCurrentPaymentsFormIds();
        $contribFormId = (in_array($this->arguments['formid'], $contribFormIds))
            ? $this->arguments['formid']
            : $contribFormIds[0]
            ;
        $contribEntry = $this->helloAssoController->getCurrentContribEntry($contribFormId, $user['email']);
        if (empty($contribEntry)) {
            $output = "";
            if (!empty($this->arguments['entry_id'])) {
                $output .= $this->updateOtherEntry($contribFormId);
            }
            $output .= empty($this->arguments['empty_message']) ? "" : $this->render("@templates/alert-message.twig", [
                'type' => 'warning',
                'message' => $this->arguments['empty_message']
            ]);
            return $output;
        } elseif (!empty($this->arguments['entry_id']) && $this->arguments['entry_id'] != $contribEntry['id_fiche']) {
            return $this->updateOtherEntry($contribEntry['id_typeannonce']);
        }
        
        $previousCalcValue = $contribEntry[HelloAssoController::CALC_FIELDNAMES["total"]] ?? 0;
        $calcValue = $this->updatePaymentsForEntry($contribEntry, $user['email']);

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
                ])).$changedValueMsg;
        }

        try {
            switch ($this->arguments['view']) {
                case 'buttonYW':
                    $url = $this->helloAssoController->getPaymentFormUrl($contribFormId);
                    $instruction = _t('HPF_CLICK_BUTTON_BOTTOM');
                    break;
                case 'iframe':
                    $html = $this->helloAssoController->getPaymentFormIframeHtml();
                    $instruction = _t('HPF_IFRAME_INSTRUCTION');
                    break;
                case 'buttonHelloASso':
                default:
                    $html = $this->helloAssoController->getPaymentFormButtonHtml();
                    $instruction = _t('HPF_CLICK_BUTTON_BOTTOM');
                    break;
            }
            list('paymentMessage' => $paymentMessage, 'linkToHelloAsso' => $linkToHelloAsso) =
                $this->getPaymentMessage($contribEntry, $calcValue, $user['email'], $instruction);
            if ($linkToHelloAsso) {
                switch ($this->arguments['view']) {
                    case 'buttonYW':
                        return $paymentMessage.$this->callAction('button', [
                            'class' => 'btn-primary new-window',
                            'icon' => 'far fa-credit-card',
                            'link' => $url,
                            'text' => $this->arguments['pay_button_title'],
                            'title' => $this->arguments['pay_button_title'],
                        ]).$changedValueMsg;
                    case 'iframe':
                    case 'buttonHelloASso':
                    default:
                        return $paymentMessage.$html.$changedValueMsg;
                }
            } else {
                return $paymentMessage.$changedValueMsg;
            }
        } catch (\Throwable $th) {
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
     * @param string $email
     * @return string|floatval $calcValue
     */
    private function updatePaymentsForEntry(array $entry, string $email)
    {
        // update CalcField before check
        $newEntry = $this->helloAssoController->updateCalcFields($entry, HelloAssoController::CALC_FIELDNAMES);
        $previousValue = $entry[HelloAssoController::CALC_FIELDNAMES["total"]] ?? 0;
        $newValue = $entry[HelloAssoController::CALC_FIELDNAMES["total"]] ?? 0;
        if ($previousValue != $newValue) {
            $entry = $this->helloAssoController->updateEntry($newEntry);
        }

        $calcValue = $entry[HelloAssoController::CALC_FIELDNAMES["total"]] ?? 0;

        if (!empty($calcValue) && intval($calcValue) != 0) {
            // refresh payments from HelloASso
            $this->helloAssoController->refreshPaymentsInfo($entry['id_typeannonce'], $email);

            // reload entry
            $entry = $this->helloAssoController->getCurrentContribEntry($entry['id_typeannonce'], $email);

            $calcValue = $entry[HelloAssoController::CALC_FIELDNAMES["total"]] ?? 0;
        }

        return $calcValue;
    }

    private function updateOtherEntry(string $contribFormId): string
    {
        $output = "";
        if ($this->wiki->UserIsAdmin()) {
            $entry = $this->entryManager->getOne($this->arguments['entry_id']);
            if (!empty($entry) && !empty($entry['id_typeannonce']) && !empty($entry['bf_mail']) && $entry['id_typeannonce'] == $contribFormId) {
                $previousCalcValue = $entry[HelloAssoController::CALC_FIELDNAMES["total"]] ?? 0;
                $newCalcValue = $this->updatePaymentsForEntry($entry, $entry['bf_mail']);
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
        $messageEntry = $this->helloAssoController->getPaymentMessageEntry();
        $field = $this->formManager->findFieldFromNameOrPropertyName('bf_moyen_paiement', $entry['id_typeannonce'] ??"");
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
        $paymentMessage = $this->render("@templates/alert-message.twig", [
            'type' => 'secondary-2',
            'message' => str_replace(
                ['{sum}','{email}',"{instruction}","\n"],
                [$calcValue,$email,$instruction,"<br/>"],
                $paymentMessage
            )
        ]);
        return compact(['paymentMessage','linkToHelloAsso']);
    }
}
