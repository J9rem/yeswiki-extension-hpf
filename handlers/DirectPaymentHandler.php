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
use YesWiki\Core\Service\AclService;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Hpf\Service\HpfService;

class DirectPaymentHandler extends YesWikiHandler
{
    protected $aclService;
    protected $entryManager;
    protected $hpfService;

    public function run()
    {
        // get services
        $this->aclService = $this->getService(AclService::class);
        $this->entryManager = $this->getService(EntryManager::class);
        $this->hpfService = $this->getService(HpfService::class);

        extract($this->check());

        if (!$status){
            return $this->finalRender($this->render('@templates/alert-message.twig',[
                'type' => $errorType,
                'message' => $errorMsg
            ]));
        }

        $valueToPay = $entry[HpfService::CALC_FIELDNAMES["total"]] ?? 0;
        if ($valueToPay <= 0) {
            return $this->finalRender($this->render('@templates/alert-message.twig',[
                'type' => 'success',
                'message' => _t('HPF_NOTHING_TO_PAY')
            ]));
        }

        return $this->finalRender($this->render('@templates/alert-message.twig',[
            'type' => 'success',
            'message' => 'test'
        ]));
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
     * check if all is right
     * @return array [
     *  'status' => boolean, 
     *  'errorMsg' => string,
     *  'errorType' => string,
     *  'entry' => array,
     *  'formId' => string
     * ]
     */
    protected function check(): array
    {
        $output = [
            'status' => false,
            'errorMsg' => 'error',
            'errorType' => 'danger',
            'entry' => [],
            'formId' => '',
        ];

        try {
            // check current user is owner
            if (!$this->aclService->hasAccess('read')){
                throw new Exception(_t('DENY_READ'));
            }
            // check current user is owner
            if (!$this->aclService->check('%')){
                throw new Exception(_t('DELETEPAGE_NOT_OWNER'));
            }
            // check if current page is an entry
            $tag = $this->wiki->GetPageTag();
            if (empty($tag) || !$this->entryManager->isEntry($tag)) {
                throw new Exception(_t('HPF_SHOULD_BE_AN_ENTRY'));
            }
            // prepare for action
            $output['entry'] = $this->entryManager->getOne($tag);
            $output['formId'] = $output['entry']['id_typeannonce'] ?? '';

            $contribFormIds = $this->hpfService->getCurrentPaymentsFormIds();
            if (!in_array($output['formId'], $contribFormIds)) {
                throw new Exception(_t('HPF_SHOULD_BE_AN_ENTRY_FOR_PAYMENT'));
            }

            // all is Right
            $output['status'] = true;
        } catch (Throwable $th) {
            $output['status'] = false;
            $output['errorMsg'] = $th->getMessage();
        }

        return $output;
    }
}
