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

use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Core\YesWikiAction;

class HPFHelloAssoPaymentsAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        return([
            'college1' => $this->formatString($arg,'college1'),
            'college2' => $this->formatString($arg,'college2'),
            'college3' => $this->formatString($arg,'college3'),
            'college4' => $this->formatString($arg,'college4')
        ]);
    }

    protected function formatString(array $arg, string $key): string
    {
        return (!empty($arg[$key]) && is_string($arg[$key]))
            ? $arg[$key]
            : '';
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
            'forms' => [
                '1' => $this->arguments['college1'],
                '2' => $this->arguments['college2'],
                '3' => $this->arguments['college3'],
                '4' => $this->arguments['college4']
            ],
            'anti-csrf-token' => $this->getService(CsrfTokenManager::class)->refreshToken('refresh-payment-cache-token')->getValue()
        ];

        return $this->render('@hpf/hpf-helloasso-payments-action.twig',compact(['params']));        
    }
}
