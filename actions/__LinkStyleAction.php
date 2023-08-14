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

use YesWiki\Core\YesWikiAction;

class __LinkstyleAction extends YesWikiAction
{
    public function run()
    {
        $path = (!is_file('tools/bazar/presentation/javascripts/components/DynTable.js'))
            ? 'hpf/javascripts/components'
            : 'bazar/presentation/javascripts/components';
        $baseUrl = $this->wiki->getBaseUrl();
        return <<<HTML
        <script type="importmap">
            {
                "imports":{
                    "DynTable": "$baseUrl/tools/$path/DynTable.js",
                    "TemplateRenderer": "$baseUrl/tools/$path/TemplateRenderer.js"
                }
            }
        </script>
        HTML;
    }
}
