/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

var registerFieldHpf = function(field){
  window.typeUserAttrs = {
    ...window.typeUserAttrs,
    ...{
      [field.field.name]: field.attributes
    }
  }
  window.templates = {
    ...window.templates,
    ...{
      [field.field.name]: field.renderInput
    }
  }
  window.yesWikiMapping = {
    ...window.yesWikiMapping,
    ...{
      [field.field.name]: field.attributesMapping
    }
  }
  if ('disabledAttributes' in field){
    window.typeUserDisabledAttrs[field.field.name] = field.disabledAttributes
  }
  window.fields.push(field.field)
}

var registerFieldAsModuleHpf = function(field){
  window.formBuilderFields[field.field.name] = field
}

