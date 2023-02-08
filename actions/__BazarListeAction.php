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

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Customsendmail\Service\CustomSendMailService;
use YesWiki\Groupmanagement\Controller\GroupController;

class __BazarListeAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        $newArg = [];
        if (!empty($arg['template']) && $arg['template'] == "list-no-empty") {
            $newArg['dynamic'] = true;
        }
        return $newArg;
    }

    public function run()
    {
    }
}
