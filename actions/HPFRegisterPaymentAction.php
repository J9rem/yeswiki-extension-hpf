<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-register-payment-action
 */

namespace YesWiki\Hpf;

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiAction;

class HPFRegisterPaymentAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        return[
            'formsids' => $this->formatArray($arg['formsids'] ?? '')
        ];
    }

    public function run()
    {
        // only admins
        if (!$this->wiki->UserIsAdmin()){
            return $this->render('@templates/alert-message.twig',[
                'message' => _t('BAZ_NEED_ADMIN_RIGHTS'),
                'type' => 'danger'
            ]);
        }
        $params = [
            'formsids' => []
        ];
        $formManager = $this->getService(FormManager::class);
        foreach($this->arguments['formsids'] as $id){
            if (
                is_scalar($id)
                && strval($id) == strval(intval($id))
                && intval($id) > 0
                ){
                $form = $formManager->getOne($id);
                if (!empty($form)){
                    $params['formsids'][strval($id)] = $form['bn_label_nature'];
                }
            }

            
        }

        return $this->render('@hpf/register-payment.twig',compact(['params']));        
    }
}
