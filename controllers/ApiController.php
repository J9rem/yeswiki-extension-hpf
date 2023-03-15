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
use YesWiki\Hpf\Controller\HelloAssoController;
use YesWiki\Hpf\Exception\ApiException;
use YesWiki\Shop\Controller\ApiController as ShopApiController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api/shop/helloasso/{token}", methods={"POST"},options={"acl":{"public"}},priority=2)
     */
    public function postHelloAsso($token)
    {
        // force construct of helloAssoController to register event
        $helloAssoController = $this->getService(HelloAssoController::class);
        return $this->getService(ShopApiController::class)->postHelloAsso($token);
    }

    /**
     * @Route("/api/hpf/refresh-payment/{tag}", methods={"GET"},options={"acl":{"public","+"}})
     */
    public function refreshHelloAsso($tag)
    {
        $entryManager = $this->getService(EntryManager::class);
        $helloAssoController = $this->getService(HelloAssoController::class);
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
            $previousValue = $entry[HelloAssoController::CALC_FIELDNAMES["total"]] ?? 0;
            $newCalcValue = $helloAssoController->refreshEntryFromHelloAsso($entry,$user['email']);

            return new ApiResponse([
                'action' => 'refreshing',
                'tag' => $tag,
                'result' => 'refresh',
                'needRefresh' => ($previousValue != $newCalcValue)
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
}
