/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Feature UUID : hpf-receipts-creation
 */

function getReceiptsField(defaultMapping){

    return {
        field: {
          label: _t('HPF_RECEIPTS_FIELD'),
          name: "receipts",
          attrs: { type: "receipts" },
          icon: '<i class="fas fa-file-invoice-dollar"></i>',
        },
        attributes: {},
        attributesMapping: {
          ...defaultMapping,
          ...{
            1: "",
            2: "",
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
          return 'Reçus de paiement';
        },
        disabledAttributes: ['required','value','label','name']
    }
}