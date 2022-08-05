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
use YesWiki\Core\YesWikiController;
use YesWiki\Hpf\Controller\HelloAssoController;
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
}
