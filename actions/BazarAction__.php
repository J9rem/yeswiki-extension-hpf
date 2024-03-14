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

use BazarAction;
use YesWiki\Core\YesWikiAction;

class BazarAction__ extends YesWikiAction
{
    public function run()
    {
        if (!$this->isWikiHibernated()
            && $this->wiki->UserIsAdmin()
            && isset($this->arguments[BazarAction::VARIABLE_VOIR]) && $this->arguments[BazarAction::VARIABLE_VOIR] === BazarAction::VOIR_FORMULAIRE
            && isset($this->arguments[BazarAction::VARIABLE_ACTION]) && in_array($this->arguments[BazarAction::VARIABLE_ACTION], [BazarAction::ACTION_FORM_CREATE,BazarAction::ACTION_FORM_EDIT], true)
        ) {
            $this->wiki->AddJavascriptFile('tools/hpf/javascripts/fields/form-edit-template-register-field.js');
            // Feature UUID : hpf-condition-view-field
            $this->wiki->AddJavascriptFile('tools/hpf/javascripts/fields/conditionviewfield.js');
            // Feature UUID : hpf-payments-field
            $this->wiki->AddJavascriptFile('tools/hpf/javascripts/fields/paymentsfield.js');
            // Feature UUID : hpf-receipts-creation
            $this->wiki->AddJavascriptFile('tools/hpf/javascripts/fields/receiptsfield.js');
            if (file_exists('tools/bazar/presentation/javascripts/form-edit-template/fields/commons/render-helper.js')) {
                $this->wiki->AddJavascriptFile('tools/hpf/javascripts/fields/form-edit-template-module.js', false, true);
            } else {
                $this->wiki->AddJavascriptFile('tools/hpf/javascripts/fields/form-edit-template.js');
            }
        }
    }
}
