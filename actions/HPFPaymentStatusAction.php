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
use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\UserManager;
use YesWiki\Hpf\Controller\HelloAssoController;

class HPFPaymentStatusAction extends YesWikiAction
{
    protected $debug;
    protected $entryManager;
    protected $helloAssoController;

    public function formatArguments($arg)
    {
        return([
            'empty_message' => $arg['empty_message'] ?? "",
            'pay_button_title' => $arg['pay_button_title'] ?? _t('HPF_PAY'),
            'nothing_to_pay_message' => $arg['nothing_to_pay_message'] ?? "",
            'view' => (empty($arg['view']) || !in_array($arg['view'], ['buttonHelloAsso','buttonYW','iframe'], true)) ? "buttonHelloAsso" : $arg['view'],
            'entry_id' => isset($arg['entry_id']) && is_string($arg['entry_id']) ? $arg['entry_id'] : "",
        ]);
    }

    public function run()
    {
        $this->debug = ($this->wiki->GetConfigValue('debug') =='yes');
        // get Services
        $this->entryManager = $this->getService(EntryManager::class);
        $this->helloAssoController = $this->getService(HelloAssoController::class);
        $this->userManager = $this->getService(UserManager::class);

        $user = $this->userManager->getLoggedUser();
        if (empty($user)) {
            return "";
        }

        $contribFormId = $this->helloAssoController->getCurrentContribFormId();
        $contribEntry = $this->helloAssoController->getCurrentContribEntry($user['email']);
        if (empty($contribEntry)) {
            if ($this->wiki->UserIsAdmin() && !empty($this->arguments['entry_id'])) {
                $entry = $this->entryManager->getOne($this->arguments['entry_id']);
                if (!empty($entry) && !empty($entry['id_typeannonce']) && !empty($entry['bf_mail']) && $entry['id_typeannonce'] == $contribFormId) {
                    $this->updatePaymentsForEntry($entry, $entry['bf_mail']);
                }
            }
            return $this->arguments['empty_message'];
        } elseif (!empty($this->arguments['entry_id']) && $this->arguments['entry_id'] != $contribEntry['id_fiche']) {
            if ($this->wiki->UserIsAdmin()) {
                $entry = $this->entryManager->getOne($this->arguments['entry_id']);
                if (!empty($entry) && !empty($entry['id_typeannonce']) && !empty($entry['bf_mail']) && $entry['id_typeannonce'] == $contribFormId) {
                    $this->updatePaymentsForEntry($entry, $entry['bf_mail']);
                }
            }
            return "";
        }
        
        $calcValue = $this->updatePaymentsForEntry($contribEntry, $user['email']);

        if (empty($calcValue) || intval($calcValue) == 0) {
            return empty($this->arguments['nothing_to_pay_message']) ? "" :
                $this->render("@templates/alert-message.twig", [
                    'type' => 'success',
                    'message' => $this->arguments['nothing_to_pay_message']
                ]);
        }

        try {
            switch ($this->arguments['view']) {
                case 'buttonYW':
                    $url = $this->helloAssoController->getPaymentFormUrl();
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
            $paymentMessage = $this->render("@templates/alert-message.twig", [
                'type' => 'secondary-2',
                'message' => str_replace(
                    ['{sum}','{email}',"{instruction}","\n"],
                    [$calcValue,$user['email'],$instruction,"<br/>"],
                    _t('HPF_PAYMENT_MESSAGE')
                ),
            ]);
            switch ($this->arguments['view']) {
                case 'buttonYW':
                    return $paymentMessage.$this->callAction('button', [
                        'class' => 'btn-primary new-window',
                        'icon' => 'far fa-credit-card',
                        'link' => $url,
                        'text' => $this->arguments['pay_button_title'],
                        'title' => $this->arguments['pay_button_title'],
                    ]);
                case 'iframe':
                case 'buttonHelloASso':
                default:
                    return $paymentMessage.$html;
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
            $this->helloAssoController->refreshPaymentsInfo($email);

            // reload entry
            $entry = $this->helloAssoController->getCurrentContribEntry($email);

            $calcValue = $entry[HelloAssoController::CALC_FIELDNAMES["total"]] ?? 0;
        }

        return $calcValue;
    }
}
