/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

function getPaymentsField(defaultMapping){

    return {
        field: {
          label: _t('HPF_PAYMENTS_FIELD'),
          name: "payments",
          attrs: { type: "payments" },
          icon: '<i class="far fa-money-bill-alt"></i>',
        },
        attributes: {},
        attributesMapping: {
          ...defaultMapping,
          ...{
            3: "",
            4: "",
            5: "",
            6: "",
            7: "",
            8: "",
            9: "",
          }
        },
        renderInput(field) {
          var string = `<input type="text" value=""/>`;
          return string;
        },
        disabledAttributes: ['required','value']
    }
}