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
use YesWiki\Hpf\Service\HpfService;

class DirectPaymentHandler extends YesWikiHandler
{
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

        list(
            'status' => $status,
            'errorMsg' => $errorMsg,
            'errorType' => $errorType,
            'entry' => $entry,
            'formId' => $formId
            ) = $this->check();

        if (!$status){
            return $this->finalRender($this->render('@templates/alert-message.twig',[
                'type' => $errorType,
                'message' => $errorMsg
            ]));
        }

        $data = $this->extractData($entry);

        list(
            'status' => $status,
            'errorMsg' => $errorMsg,
            'errorType' => $errorType
            ) = $this->checkData($data);

        if (!$status){
            return $this->finalRender($this->render('@templates/alert-message.twig',[
                'type' => $errorType,
                'message' => $errorMsg
            ]));
        }

        return $this->finalRender($this->callAction('helloassodirectpayment',$data));
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
            'formId' => ''
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

            $form = $this->formManager->getOne($output['formId']);
            if (empty($form['bn_only_one_entry']) || $form['bn_only_one_entry'] !== 'Y'){
                throw new Exception(_t('HPF_SHOULD_BE_AN_ENTRY_FOR_FORM_WITH_UNIQ_ENTRY_BY_USER'));
            }

            // all is Right
            $output['status'] = true;
        } catch (Throwable $th) {
            $output['status'] = false;
            $output['errorMsg'] = $th->getMessage();
        }

        return $output;
    }

    /**
     * check if all is right in data
     * @param array $data
     * @return array [
     *  'status' => boolean, 
     *  'errorMsg' => string,
     *  'errorType' => string
     * ]
     */
    protected function checkData(array $data): array
    {
        $output = [
            'status' => false,
            'errorMsg' => 'error',
            'errorType' => 'danger'
        ];

        try {
            if ($data['total'] <= 0) {
                $output['errorType'] = 'info';
                throw new Exception(_t('HPF_NOTHING_TO_PAY'));
            }

            $user = $this->authController->getLoggedUser();
            if (
                empty($user['email'])
                || empty($data['email']) 
                || $user['email'] != $data['email']
                ){
                    throw new Exception(_t('HPF_CURRENT_USER_SHOULD_HAVE_SAME_EMAIL_AS_ENTRY'));
            }

            // all is Right
            $output['status'] = true;
        } catch (Throwable $th) {
            $output['status'] = false;
            $output['errorMsg'] = $th->getMessage();
        }

        return $output;
    }

    /**
     * extract useful data from entry
     * @param array $entry
     * @return array [
     *  'firstname' => string,
     *  'name' => string,
     *  'postalcode' => string,
     *  'town' => string,
     *  'email' => string,
     *  'total' => float
     * ]
     */
    protected function extractData(array $entry): array
    {
        $associations = [
            'firstname' => 'bf_prenom',
            'name' => 'bf_name',
            'postalcode' => 'bf_code_postal',
            'town' => 'bf_ville',
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
            $data[str_replace('_','',$key)] = floatval($entry[HpfService::CALC_FIELDNAMES[$key]] ?? 0);
        }

        return $data;
    }
}
