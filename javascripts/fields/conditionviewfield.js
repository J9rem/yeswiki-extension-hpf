/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-condition-view-field
 */

function getConditionViewField(){

    return {
        field: {
          label: _t('HPF_CONDITIONVIEW_FIELD'),
          name: "conditionview",
          attrs: { type: "conditionview" },
          icon: '<i class="fas fa-code-branch"></i>',
        },
        attributes: {
          label: { value: _t('HPF_CONDITIONVIEW_FIELD'), value: '' },
          content_display: { label: _t('BAZ_FORM_EDIT_VIEW_CONTENT_LABEL'), type: 'textarea', rows: '4', value: '' },
          conditionfieldname: {
            label: _t('HPF_CONDITIONVIEW_FIELDNAME'),
            value: ''
          },
          conditionfieldwaitedvalue: {
            label: _t('HPF_CONDITIONVIEW_WAITEDVALUE'),
            value: ''
          }
        },
        attributesMapping: {
          0: 'type',
          1: '',
          2: '',
          3: 'content_display',
          4: 'conditionfieldname',
          5: '',
          6: 'conditionfieldwaitedvalue'
        },
        renderInput(field) {
          return {
            field:
              `<div>${field.content_display || ''}</div>`
          }
        },
        disabledAttributes: ['required','value','name', 'label']
    }
}