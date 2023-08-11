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

use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Hpf\Service\HpfService;
use YesWiki\Hpf\Exception\ApiException;
use YesWiki\Shop\Controller\ApiController as ShopApiController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api/shop/helloasso/{token}", methods={"POST"},options={"acl":{"public"}},priority=2)
     */
    public function postHelloAsso($token)
    {
        // force construct of hpfService to register event
        $hpfService = $this->getService(HpfService::class);
        return $this->getService(ShopApiController::class)->postHelloAsso($token);
    }

    /**
     * @Route("/api/hpf/refresh-payment/{tag}", methods={"GET"},options={"acl":{"public","+"}})
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
     */
    public function getPaymentInfo($id)
    {
        return new ApiResponse($this->getService(HpfService::class)->getPaymentInfos($id,[]),200);
    }  
}
