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

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\YesWikiHandler;

class DirectPaymentHandler extends YesWikiHandler
{
    protected $aclService;
    protected $entryManager;

    public function run()
    {
        // get services
        $this->aclService = $this->getService(AclService::class);
        $this->entryManager = $this->getService(EntryManager::class);

        // check current user can read
        if (!$this->aclService->hasAccess('read')){
            return $this->finalRender($this->render('@templates/alert-message.twig',[
                'type' => 'danger',
                'message' => _t('DENY_READ')
            ]));
        }

        return $this->finalRender($this->render('@templates/alert-message.twig',[
            'type' => 'success',
            'message' => 'test'
        ]));
    }

    protected function finalRender(string $content, bool $includePage = false): string
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
}
