/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

typeUserAttrs = {
  ...typeUserAttrs,
  ...{
    payments: {},
  }
};

templates = {
  ...templates,
  ...{
    payments: function (fieldData) {
      var string = `<input type="text" value=""/>`;
      return string;
    },
  }
};

yesWikiMapping = {
  ...yesWikiMapping,
  ...{
    payments: {
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
  }
};

fields.push({
    label: _t('HPF_PAYMENTS_FIELD'),
    name: "payments",
    attrs: { type: "payments" },
    icon: '<i class="far fa-money-bill-alt"></i>',
  });

typeUserDisabledAttrs['payments'] = ['required','value'];
