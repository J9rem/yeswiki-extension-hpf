<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-helloasso-payments-table
 * Feature UUID : hpf-payments-by-cat-table
 * 
 * could be deleted now
 */

namespace YesWiki\Hpf;

use YesWiki\Core\Service\AssetsManager;
use YesWiki\Core\YesWikiAction;

class __LinkstyleAction extends YesWikiAction
{
    public function run()
    {
        $baseUrl = $this->wiki->getBaseUrl();
        $this->getService(AssetsManager::class)->AddCSSFile('tools/hpf/styles/fields/payments.css');
        return <<<HTML
        <script type="importmap">
            {
                "imports":{
                    "DynTable": "$baseUrl/tools/bazar/presentation/javascripts/components/DynTable.js",
                    "TemplateRenderer": "$baseUrl/tools/bazar/presentation/javascripts/components/TemplateRenderer.js"
                }
            }
        </script>
        HTML;
    }
}
