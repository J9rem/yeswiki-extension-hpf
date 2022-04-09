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

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\UserManager;
use YesWiki\Hpf\Controller\HelloAssoController;

class HPFPaymentStatusAction extends YesWikiAction
{
    protected $debug;
    protected $formManager;
    protected $helloAssoController;

    public function formatArguments($arg)
    {
        return([
            'empty_message' => $arg['empty_message'] ?? "",
            'pay_button_title' => $arg['pay_button_title'] ?? _t('HPF_PAY'),
            'nothing_to_pay_message' => $arg['nothing_to_pay_message'] ?? "",
            'view' => (empty($arg['view']) || !in_array($arg['view'], ['buttonHelloAsso','buttonYW','iframe'], true)) ? "buttonHelloAsso" : $arg['view'],
        ]);
    }

    public function run()
    {
        $this->debug = ($this->wiki->GetConfigValue('debug') =='yes');
        // get Services
        $this->formManager = $this->getService(FormManager::class);
        $this->helloAssoController = $this->getService(HelloAssoController::class);
        $this->userManager = $this->getService(UserManager::class);

        $user = $this->userManager->getLoggedUser();
        if (empty($user)) {
            return "";
        }

        $contribEntry = $this->helloAssoController->getCurrentContribEntry($user['email']);
        if (empty($contribEntry)) {
            return $this->arguments['empty_message'];
        }

        $contribFormId = $this->helloAssoController->getCurrentContribFormId();
        $form = $this->formManager->getOne($contribFormId);

        // update CalcField before check
        $newEntry = $this->helloAssoController->updateCalcFields($contribEntry, HelloAssoController::CALC_FIELDNAMES);
        $previousValue = $contribEntry[HelloAssoController::CALC_FIELDNAMES["total"]] ?? 0;
        $newValue = $newEntry[HelloAssoController::CALC_FIELDNAMES["total"]] ?? 0;
        if ($previousValue != $newValue) {
            $contribEntry = $this->helloAssoController->updateEntry($newEntry);
        }

        $calcValue = $contribEntry[HelloAssoController::CALC_FIELDNAMES["total"]] ?? 0;

        if (!empty($calcValue) && intval($calcValue) != 0) {
            // refresh payments from HelloASso
            $this->helloAssoController->refreshPaymentsInfo($user['email']);

            // reload entry
            $contribEntry = $this->helloAssoController->getCurrentContribEntry($user['email']);

            $calcValue = $contribEntry[HelloAssoController::CALC_FIELDNAMES["total"]] ?? 0;
        }

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
}
