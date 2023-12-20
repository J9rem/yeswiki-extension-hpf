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
    // actions/__BazarListeAction.php
    // Feature UUID : hpf-area-management
    'HPF_AREA_MNGT_PARENTS_TITLES' => 'Interested structures',
    'HPF_AREA_MNGT_AREAS_TITLES' => 'Geographical scope',

    // actions/documentation.yaml
    // 'AB_HPF_group_label' => 'Spécifique HPF',
    // Feature UUID : hpf-payment-status-action
    // 'AB_hpf_hpfpaymentstatus_label' => 'Afficher le lien de paiement HelloAsso',
    // 'AB_hpf_hpfpaymentstatus_empty_message_label' => 'Texte à afficher s\'il n\'y a pas de fiche',
    // 'AB_hpf_hpfpaymentstatus_empty_message_hint' => 'Laisser vide pour ne rien afficher',
    // 'AB_hpf_hpfpaymentstatus_nothing_to_pay_message_label' => 'Texte à afficher s\'il n\'y rien à payer',
    // 'AB_hpf_hpfpaymentstatus_nothing_to_pay_message_hint' => 'Laisser vide pour ne rien afficher',
    // 'AB_hpf_hpfpaymentstatus_view_label' => 'Type de vue',
    // 'AB_hpf_hpfpaymentstatus_view_buttonHelloAsso_label' => 'Bouton HelloASso',
    // 'AB_hpf_hpfpaymentstatus_view_button_label' => 'Bouton YesWiki',
    // 'AB_hpf_hpfpaymentstatus_view_iframe_label' => 'Iframe Hello ASso',
    // 'AB_hpf_hpfpaymentstatus_pay_button_title_label' => 'Texte pour le bouton YesWiki',
    // 'AB_hpf_hpfpaymentstatus_formid_label' => 'Formulaire associé',
    // Feature UUID : hpf-bazar-template-list-no-empty
    // 'HPF_bazarlistnoempty_label' => 'Liste (sauf vide)',
    // 'AB_bazarliste_initialheight_label' => 'Hauteur initiale pendant le chargement',
    // 'AB_bazarliste_initialheight_hint' => 'Nombre de pixels sans unité (500 = défaut) ; ex. : 100',
    // 'HPF_BAZARTABLEAU_LINK_TO_GROUP_LABEL' => 'Tableau (avec lien groupe vers BDD)',

    // actions/HpfPaymentStatusAction.php
    // Feature UUID : hpf-payment-status-action
    // 'HPF_CLICK_BUTTON_BOTTOM' => "cliquant sur le bouton ci-dessous",
    // 'HPF_IFRAME_INSTRUCTION' => "complétant le formulaire ci-dessous",
    // 'HPF_GET_URL_ERROR' => 'Une erreur est survenue dans le fichier {file} (ligne {line}) : {message}',
    // 'HPF_PAY' => "Payer sur Hello Asso",
    // 'HPF_PAYMENT_MESSAGE' => <<<TXT
    //     Vous devez payer une somme de {sum} €. Vous pouvez le faire en {instruction}.
    //     Pensez bien à recopier correctement votre e-mail ({email})
    //     et la bonne somme à payer ({sum} €).
    //     Si vous avez déjà payé, veuillez cliquer {hereLinkStart}ici{hereLinkEnd} pour forcer une synchronisation des données de paiement.
    //     La fiche concernée est {entryLink}.
    //     TXT,
    // 'HPF_PAYMENT_MESSAGE_CB' => <<<TXT
    //     Vous vous êtes engagé à payer {sum} €. Vous pouvez le faire en {instruction}.
    //     Le paiement se fera par carte bancaire via le site de HelloAsso.
    //     Utilisez la même adresse e-mail ({email}) et reportez le bon montant à payer ({sum} €).
    //     Si vous avez déjà payé, veuillez cliquer {hereLinkStart}ici{hereLinkEnd} pour forcer une synchronisation des données de paiement.
    //     La fiche concernée est {entryLink}.
    //     TXT,
    // 'HPF_PAYMENT_MESSAGE_VIREMENT' => <<<TXT
    //     Vous vous êtes engagé à payer {sum} €. Vous pouvez le faire en faisant un virement
    //     sur le compte suivant :
    //     IBAN : FR76 XXXX XXXX XXXX XXXX XXXX - BIC : XXXXXXXX.
    //     Indiquez comme référence votre nom et prénom comme dans la fiche (éventuellement votre code postal).
    //     La fiche concernée est {entryLink}.
    //     TXT,
    // 'HPF_PAYMENT_MESSAGE_CHEQUE' => <<<TXT
    //     Vous vous êtes engagé à payer {sum} €. Vous pouvez le faire en faisant un chèque
    //     A l'ordre de XXXX
    //     et à envoyer à xxxx
    //     en indiquant vos nom, prénom, adresse e-mail et code postal.
    //     La fiche concernée est {entryLink}.
    //     TXT,
    // 'HPF_UPDATED_ENTRY' => "Les données de la fiche '{titre}' affichée ici pourraient ne pas être à jour.\n".
    //     "Veuillez recharger la fiche.",
    // 'HPF_NOT_FOR_EMPTY_TAG' => 'rafraichissement impossible pour un tag vide',
    // 'HPF_FORBIDEN_FOR_THIS_ENTRY' => 'vous n\'avez ps le droit de modifier cette fiche',

    // actions/HPFHelloAssoPaymentsAction.php
    // Feature UUID : hpf-helloasso-payments-table
    // 'AB_hpf_hpfhelloassopayments_label' => 'Liste des paiements Hello Asso',
    // 'AB_hpf_hpfhelloassopayments_college1_label' => 'Collège 1',
    // 'AB_hpf_hpfhelloassopayments_college2_label' => 'Collège 2',
    // 'AB_hpf_hpfhelloassopayments_college3_label' => 'Collège 3',
    // 'AB_hpf_hpfhelloassopayments_college4_label' => 'Collège 4',
    // 'AB_hpf_hpfhelloassopayments_college3to4fieldname_label' => 'Nom du champ d\'association du collège 3 vers le collège 4',
    // 'AB_hpf_hpfhelloassopayments_partner_label' => 'Partenaires',

    // actions/HpfpaymentsbycatAction.php
    // Feature UUID : hpf-payments-by-cat-table
    // 'AB_hpf_hpfpaymentsbycat_label' => 'Liste des paiements Hello Asso par catégories',

    // action/HPFRegisterPaymentAction.php
    // Feature UUID : hpf-register-payment-action
    // 'AB_hpf_hpfregisterpayment_label' => 'Enregistrement simplifié d\'un paiement',
    // 'AB_hpf_hpfregisterpayment_formsids_label' => 'Formulaires concernés',
    // 'AB_hpf_hpfregisterpayment_formsids_hint' => 'entiers séparés par des virgules. Ex.: 16,23,24',
    // 'HPF_REGISTER_A_PAYMENT' => 'Enregistrer un paiement',
    // 'HPF_REGISTER_PAYMENT_FORM' => 'Formulaire concerné',
    
    // action/HPFRegisterPaymentAction.php
    // Feature UUID : hpf-import-payments
    // 'HPF_IMPORT_BAD_ERROR_FORMAT' => 'Le format du fichier fournit n\'est pas bon. Il devrait être ".ods", ".csv", ".xls" ou ".xlsx" !',
    // 'HPF_IMPORT_DATA_FROM' => 'Données extraites de \'%{file}\' :',
    // 'HPF_IMPORT_MEMBERSHIPS_LABEL' => 'Fichier à importer',
    // 'HPF_IMPORT_MEMBERSHIPS_TITLE' => 'Import de listes d\'adhésions',
    // 'HPF_IMPORT_OTHER_FILE' => 'Importer un autre fichier',
    // 'AB_hpf_hpfimportmembership_label' => 'Import de listes d\'adhésions',

    // config.yaml
    // Feature UUID : hpf-payment-status-action
    // 'EDIT_CONFIG_HINT_HPF[CONTRIBFORMIDS]' => 'Identifiant des formulaires liés au paiement (avec \'bf_mail\' et un champ \'CalcField\'), séparés par des virgules',
    // 'EDIT_CONFIG_HINT_HPF[PAYMENTSFORMURLS]' => 'Lien vers le formulaire HelloAsso de paiement associé (séparé par des virgules)',
    // 'EDIT_CONFIG_HINT_HPF[PAYMENTMESSAGEENTRY]' => 'Fiche avec les messages pour le paiement',
    // 'EDIT_CONFIG_GROUP_HPF' => 'Paramètres spécifiques HPF',
    // Feature UUID : hpf-area-management
    'EDIT_CONFIG_HINT_AREAFIELDNAME' => 'Fieldname for validated localization for structures',
    'EDIT_CONFIG_HINT_FORMIDAREATODEPARTMENT' => 'Form id of correspondance between area and department',
    'EDIT_CONFIG_HINT_GROUPSADMINSSUFFIXFOREMAILS' => 'Admins groups suffix which can send emails',
    'EDIT_CONFIG_HINT_POSTALCODEFIELDNAME' => 'Fieldname for postal code',

    // docs/actions/bazarliste.yaml via templates/aceditor/actions-builder.tpl.html
    // Feature UUID : hpf-area-management 
    'HPF_SELECTMEMBERSPARENT_FORM_LABEL' => 'Parent form',
    'HPF_SELECTMEMBERS_BY_AREA' => 'Members AND profiles in area',
    'HPF_SELECTMEMBERS_DISPLAY_FILTERS_LABEL' => 'Add structures of interest and geographical scope to filters',
    'HPF_SELECTMEMBERS_HINT' => 'Filter from parent entry (structures) where I am administrator',
    'HPF_SELECTMEMBERS_LABEL' => 'Filter entries',
    'HPF_SELECTMEMBERS_ONLY_MEMBERS' => 'Only members',

    // templates/hpf-import-memberships-action.twig
    // Feature UUID : hpf-import-payments
    'HPF_ADJUSTED' => 'Adjusted',
    'HPF_ADD_ENTRY_OR_PAYMENT' => 'Ajouter le paiement',
    'HPF_ALREADY_APPENDED' => 'An entry already exists for this e-mail and the payment was already appended! Click to see entry.',
    'HPF_APPEND_INSTEAD_OF_CREATE' => 'An entry already exists for this e-mail : only the payment will be appended to the entry, without changing anything else ! Click to see entry.',
    // 'HPF_CHEQUE_TYPE' => 'HPF (par chèque)',
    'HPF_COMMENT' => 'Comment',
    'HPF_CREATE_ENTRY_NOT_POSSIBLE' => 'Not possible:',
    'HPF_DATE' => 'Date',
    'HPF_DEPT' => 'Department',
    'HPF_EMAIL' => 'E-mail',
    'HPF_EMAIL_ALREADY_USED' => 'E-mail aleary used',
    'HPF_EMAIL_BADLY_FORMATTED' => 'E-mail badly formatted',
    // 'HPF_ESPECES_TYPE' => 'HPF (par espèces)',
    'HPF_FIRSTNAME' => 'First name',
    'HPF_FIRSTNAME_EMPTY' => 'Empty first name',
    'HPF_FREE' => 'Free',
    'HPF_GROUP_MEMBERSHIP' => 'Group membership',
    'HPF_GROUP_NAME' => 'Group\'s name',
    'HPF_GROUP_NAME_EMPTY' => 'empty group\'s name',
    // 'HPF_IMPORT_HELP' => '...',
    'HPF_IS_GROUP' => 'Membership type',
    'HPF_MEMBERSHIP_TYPE' => 'Value type',
    'HPF_NAME' => 'Name',
    'HPF_NAME_EMPTY' => 'Empty name',
    'HPF_PAYMENT_NUMBER' => 'Payment number',
    'HPF_PERSONAL_MEMBERSHIP' => 'Personal membership',
    'HPF_POSTAL_CODE' => 'Postal code',
    'HPF_POSTAL_CODE_BADLY_FORMATTED' => 'Postal code badly formatted',
    'HPF_POSTAL_CODE_OR_DEPT_MISSING' => 'Postal code or department missing',
    'HPF_PROCESS' => 'Proceed data',
    'HPF_PROCESSING' => 'Processing data',
    'HPF_PUBLIC_VISIBILITY' => 'Public visibility?',
    'HPF_RECEIVED_BY' => 'Received by',
    'HPF_STANDARD' => 'Standard',
    'HPF_STRUCTURE_TYPE' => 'By a local organization',
    'HPF_SUPPORT' => 'Support',
    'HPF_TOWN' => 'Town',
    'HPF_VALUE' => 'Value',
    // 'HPF_VIREMENT_TYPE' => 'HPF (par virement)',
    'HPF_WANTED_STRUCTURE' => 'Wanted organization',
    'HPF_YEAR' => 'Membership year',
];
