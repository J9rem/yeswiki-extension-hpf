<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Hpf\Controller;

use Exception;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManager; // Feature UUID : hpf-register-payment-action
use YesWiki\Bazar\Service\EntryManager; // Feature UUID : hpf-payment-status-action
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\Service\UserManager; // Feature UUID : hpf-payment-status-action
use YesWiki\Core\YesWikiController;
use YesWiki\Hpf\Controller\HpfImportController; // Feature UUID : hpf-import-payments
use YesWiki\Hpf\Exception\ApiException; // Feature UUID : hpf-payment-status-action
use YesWiki\Hpf\Service\HpfService;
use YesWiki\Shop\Controller\ApiController as ShopApiController; // Feature UUID : hpf-api-helloasso-token-triggered

class ApiController extends YesWikiController
{
    /**
     * @Route("/api/shop/helloasso/{token}", methods={"POST"},options={"acl":{"public"}},priority=2)
     * Feature UUID : hpf-api-helloasso-token-triggered
     */
    public function postHelloAsso($token)
    {
        // force construct of hpfService to register event
        $hpfService = $this->getService(HpfService::class);
        return $this->getService(ShopApiController::class)->postHelloAsso($token);
    }

    /**
     * @Route("/api/hpf/refresh-payment/{tag}", methods={"GET"},options={"acl":{"public","+"}})
     * Feature UUID : hpf-payment-status-action
     */
    public function refreshHelloAsso($tag)
    {
        $entryManager = $this->getService(EntryManager::class);
        $hpfService = $this->getService(HpfService::class);
        $userManager = $this->getService(UserManager::class);
        try {
            if (empty($tag)){
                throw new ApiException(_t('HPF_NOT_FOR_EMPTY_TAG'));
            }
            $entry = $entryManager->getOne($tag);
            if (empty($entry)){
                throw new ApiException('not existing entry');
            }
            $user = $userManager->getLoggedUser();
            if (empty($entry['bf_mail']) || (!$this->wiki->UserIsAdmin() && $user['email'] !== $entry['bf_mail'])){
                throw new ApiException(_t('HPF_FORBIDEN_FOR_THIS_ENTRY'));
            }
            $previousValue = $entry[HpfService::CALC_FIELDNAMES["total"]] ?? 0;
            $newEntry = $hpfService->refreshEntryFromHelloAsso($entry,$user['email']);

            return new ApiResponse([
                'action' => 'refreshing',
                'tag' => $tag,
                'result' => 'refresh',
                'needRefresh' => (md5(json_encode($entry)) != md5(json_encode($newEntry)))
            ],200);
        } catch (ApiException $th) {
            return new ApiResponse([
                'action' => 'refreshing',
                'tag' => $tag,
                'error' => $th->getMessage(),
                'needRefresh' => false
            ],404);
        }
    }

    /**
     * @Route("/api/hpf/helloasso/payment/info/{id}", methods={"GET"},options={"acl":{"public","@admins"}})
     * Feature UUID : hpf-payments-field
     */
    public function getPaymentInfo($id)
    {
        return new ApiResponse($this->getService(HpfService::class)->getPaymentInfos($id),200);
    }

    /**
     * @Route("/api/hpf/helloasso/payment/email/{email}", methods={"GET"},options={"acl":{"public","@admins"}})
     * Feature UUID : hpf-bazar-template-list-no-empty
     */
    public function getPaymentsViaEmail($email)
    {
        return new ApiResponse($this->getService(HpfService::class)->getPaymentsViaEmail($email),200);
    }
    
    /**
     * @Route("/api/hpf/helloasso/payment/refreshcache", methods={"POST"},options={"acl":{"public","@admins"}})
     * Feature UUID : hpf-helloasso-payments-table
     */
    public function refreshPaymentCache()
    {
        return $this->callRefreshPaymentCommon('refresh-payment-cache-token',false);
    }

    /**
     * @Route("/api/hpf/payments-by-cat/refreshcache", methods={"POST"},options={"acl":{"public","@admins"}})
     * Feature UUID : hpf-payments-by-cat-table
     */
    public function refreshPaymentsByCatCache()
    {
        return $this->callRefreshPaymentCommon('refresh-payments-by-cat-cache-token',true);
    }

    /**
     * Feature UUID : hpf-helloasso-payments-table
     * Feature UUID : hpf-payments-by-cat-table
     */
    protected function callRefreshPaymentCommon(string $tokenKeyname,bool $byCat = false)
    {
        $csrfTokenController = $this->getService(CsrfTokenController::class);
        $csrfTokenController->checkToken($tokenKeyname, 'POST', 'anti-csrf-token');
        $formsIds = (empty($_POST['formsIds']) || !is_array($_POST['formsIds']))
            ? []
            : array_filter($_POST['formsIds'],function($v,$k){
                return in_array(intval($k),[1,2,3,4,5]) && is_scalar($v) && strval(intval($v)) === strval($v) && intval($v) > 0;
            },ARRAY_FILTER_USE_BOTH);
        $college3to4fieldname = (
                empty($_POST['college3to4fieldname'])
                || !is_string($_POST['college3to4fieldname'])
                || !preg_match('/^[a-z0-9_]+$/',$_POST['college3to4fieldname'])
            )
            ? ''
            : $_POST['college3to4fieldname'];
        list('code'=>$code,'output'=>$output) = $this->getService(HpfService::class)->refreshPaymentCache($formsIds,$college3to4fieldname,$byCat);
        return new ApiResponse($output,$code);
    }

    /**
     * @Route("/api/hpf/helloasso/payment/getToken", methods={"POST"},options={"acl":{"public","@admins"}})
     * Feature UUID : hpf-register-payment-action
     */
    public function getToken()
    {
        return new ApiResponse($this->getService(CsrfTokenManager::class)->refreshToken('payment-admin')->getValue(),200);
    }

    /**
     * @Route("/api/hpf/helloasso/payment/find/{date}/{amount}", methods={"POST"},options={"acl":{"public","@admins"}})
     * Feature UUID : hpf-register-payment-action
     */
    public function findHelloAssoPayments($date,$amount)
    {
        $csrfTokenController = $this->getService(CsrfTokenController::class);
        $csrfTokenController->checkToken('payment-admin', 'POST', 'anti-csrf-token');
        return new ApiResponse($this->getService(HpfService::class)->findHelloAssoPayments($date,$amount),200);
    }

    /**
     * @Route("/api/hpf/helloasso/payment/{entryId}/delete/{paymentId}", methods={"POST"},options={"acl":{"public","@admins"}})
     * Feature UUID : hpf-register-payment-action
     */
    public function deletePaymentInEntry($entryId,$paymentId)
    {
        $csrfTokenController = $this->getService(CsrfTokenController::class);
        $csrfTokenController->checkToken('payment-admin', 'POST', 'anti-csrf-token');
        return new ApiResponse($this->getService(HpfService::class)->deletePaymentInEntry($entryId,$paymentId),200);
    }

    /**
     * @Route("/api/hpf/helloasso/payment/{entryId}/add", methods={"POST"},options={"acl":{"public","@admins"}})
     * Feature UUID : hpf-register-payment-action
     */
    public function addPaymentInEntry($entryId)
    {
        $csrfTokenController = $this->getService(CsrfTokenController::class);
        $csrfTokenController->checkToken('payment-admin', 'POST', 'anti-csrf-token');
        $inputs = [
            'id',
            'origin',
            'date',
            'total',
        ];
        $data = [];
        foreach($inputs as $input){
            if(
                empty($_POST[$input])
                || !is_string($_POST[$input])
            ) {
                throw new Exception("\"\$_POST['$input']\" should be defined !");
            }
            $data[$input] = $_POST[$input];
        }
        
        if(
            !empty($_POST['year'])
            && !is_string($_POST['year'])
        ) {
            throw new Exception("\"\$_POST['year']\" should be defined !");
        } else {
            $data['year'] = $_POST['year'] ?? '';
        }
        return new ApiResponse(
            $this->getService(HpfService::class)->addPaymentInEntry(
                $entryId,
                $data['date'],
                $data['total'],
                $data['origin'],
                $data['id'],
                $data['year']
            ),
            200
        );
    }

    
    /**
     * @Route("/api/hpf/importmembership/{mode}/{type}/{formId}", methods={"POST"},options={"acl":{"public","@admins"}})
     * Feature UUID : hpf-import-payments
     */
    public function createEntryOrAppendPaymentForMemberShip($mode,$type,$formId)
    {
        return $this->getService(HpfImportController::class)->createEntryOrAppendPaymentForMemberShip($mode,$type,$formId);
    }
}