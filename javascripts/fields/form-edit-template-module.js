/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import {defaultMapping} from '../../../bazar/presentation/javascripts/form-edit-template/fields/commons/attributes.js'

registerFieldAsModuleHpf(getConditionViewField())
registerFieldAsModuleHpf(getPaymentsField(defaultMapping))
