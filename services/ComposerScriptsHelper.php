<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-receipts-creation
 */

namespace YesWiki\Hpf\Service;

use Composer\Script\Event;

class ComposerScriptsHelper
{
    public static function postInstall(Event $event)
    {
        // clean fonts for Mpdf/mpdf
        echo "clean fonts for Mpdf/mpdf\n";
        array_map('unlink', glob('vendor/mpdf/mpdf/ttfonts/[!D][!e][!j][!a]*.[ot]*'));
        array_map('unlink', glob('vendor/mpdf/mpdf/ttfonts/Akka*.[ot]*'));
        array_map('unlink', glob('vendor/mpdf/mpdf/ttfonts/dama*.[ot]*'));
        array_map('unlink', glob('vendor/mpdf/mpdf/ttfonts/Pada*.[ot]*'));

    }

    public static function postUpdate(Event $event)
    {
        self::postInstall($event);
    }
}
