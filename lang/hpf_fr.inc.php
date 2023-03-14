<?php

/*
 * This file is part of the YesWiki Extension Hpf.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [

    // actions/documentation.yaml
    'AB_HPF_group_label' => 'Spécifique HPF',
    'AB_hpf_hpfpaymentstatus_label' => 'Afficher le lien de paiement HelloAsso',
    'AB_hpf_hpfpaymentstatus_empty_message_label' => 'Texte à afficher s\'il n\'y a pas de fiche',
    'AB_hpf_hpfpaymentstatus_empty_message_hint' => 'Laisser vide pour ne rien afficher',
    'AB_hpf_hpfpaymentstatus_nothing_to_pay_message_label' => 'Texte à afficher s\'il n\'y rien à payer',
    'AB_hpf_hpfpaymentstatus_nothing_to_pay_message_hint' => 'Laisser vide pour ne rien afficher',
    'AB_hpf_hpfpaymentstatus_view_label' => 'Type de vue',
    'AB_hpf_hpfpaymentstatus_view_buttonHelloAsso_label' => 'Bouton HelloASso',
    'AB_hpf_hpfpaymentstatus_view_button_label' => 'Bouton YesWiki',
    'AB_hpf_hpfpaymentstatus_view_iframe_label' => 'Iframe Hello ASso',
    'AB_hpf_hpfpaymentstatus_pay_button_title_label' => 'Texte pour le bouton YesWiki',
    'AB_hpf_hpfpaymentstatus_formid_label' => 'Formulaire associé',
    'HPF_bazarlistnoempty_label' => 'Liste (sauf vide)',
    'HPF_BAZARTABLEAU_LINK_TO_GROUP_LABEL' => 'Tableau (avec lien groupe vers BDD)',

    // actions/HpfPaymentStatusAction.php
    'HPF_CLICK_BUTTON_BOTTOM' => "cliquant sur le bouton ci-dessous",
    'HPF_IFRAME_INSTRUCTION' => "complétant le formulaire ci-dessous",
    'HPF_GET_URL_ERROR' => 'Une erreur est survenue dans le fichier {file} (ligne {line}) : {message}',
    'HPF_PAY' => "Payer sur Hello Asso",
    'HPF_PAYMENT_MESSAGE' => "Vous devez payer une somme de {sum} €. Vous pouvez le faire en {instruction}.\n".
        "Pensez bien à recopier correctement votre e-mail ({email})\net la bonne somme à payer ({sum} €).\n".
        "Si vous avez déjà payé, veuillez cliquer {hereLinkStart}ici{hereLinkEnd} pour forcer une synchronization des données de paiement.",
    'HPF_PAYMENT_MESSAGE_CB' => "Vous vous êtes engagé à payer {sum} €. Vous pouvez le faire en {instruction}.\n".
        "Le paiement se fera par carte bancaire via le site de HelloAsso.\n".
        "Utilisez la même adresse e-mail ({email}) et reportez le bon montant à payer ({sum} €).\n".
        "Si vous avez déjà payé, veuillez cliquer {hereLinkStart}ici{hereLinkEnd} pour forcer une synchronization des données de paiement.",
    'HPF_PAYMENT_MESSAGE_VIREMENT' => "Vous vous êtes engagé à payer {sum} €. Vous pouvez le faire en faisant un virement\n".
        "sur le compte suivant :\n".
        "IBAN : FR76 XXXX XXXX XXXX XXXX XXXX - BIC : XXXXXXXX.\n".
        "Indiquez comme référence votre nom et prénom comme dans la fiche (éventuellement votre code postal)",
    'HPF_PAYMENT_MESSAGE_CHEQUE' => "Vous vous êtes engagé à payer {sum} €. Vous pouvez le faire en faisant un chèque\n".
        "A l'ordre de XXXX\n".
        "et à envoyer à xxxx\n".
        "en indiquant vos nom, prénom, adresse e-mail et code postal.",
    'HPF_UPDATED_ENTRY' => "Les données de la fiche '{titre}' affichée ici pourraient ne pas être à jour.\n".
        "Veuillez recharger la fiche.",

    // config.yaml
    'EDIT_CONFIG_HINT_HPF[CONTRIBFORMIDS]' => 'Identifiant des formulaires liés au paiement (avec \'bf_mail\' et un champ \'CalcField\'), séparés par des virgules',
    'EDIT_CONFIG_HINT_HPF[PAYMENTSFORMURLS]' => 'Lien vers le formulaire HelloAsso de paiement associé (séparé par des virgules)',
    'EDIT_CONFIG_HINT_HPF[PAYMENTMESSAGEENTRY]' => 'Fiche avec les messages pour le paiement',
    'EDIT_CONFIG_GROUP_HPF' => 'Paramètres spécifiques HPF',
];
