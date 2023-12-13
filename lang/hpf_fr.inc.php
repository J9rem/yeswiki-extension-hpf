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
    'HPF_AREA_MNGT_PARENTS_TITLES' => 'Sélectionnez une structure',
    'HPF_AREA_MNGT_AREAS_TITLES' => 'Périmètre géographique',

    // actions/documentation.yaml
    'AB_HPF_group_label' => 'Spécifique HPF',
    // Feature UUID : hpf-payment-status-action
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
    // Feature UUID : hpf-bazar-template-list-no-empty
    'HPF_bazarlistnoempty_label' => 'Liste (sauf vide)',
    'AB_bazarliste_initialheight_label' => 'Hauteur initiale pendant le chargement',
    'AB_bazarliste_initialheight_hint' => 'Nombre de pixels sans unité (500 = défaut) ; ex. : 100',
    'HPF_BAZARTABLEAU_LINK_TO_GROUP_LABEL' => 'Tableau (avec lien groupe vers BDD)',

    // actions/HpfPaymentStatusAction.php
    // Feature UUID : hpf-payment-status-action
    'HPF_CLICK_BUTTON_BOTTOM' => "cliquant sur le bouton ci-dessous",
    'HPF_IFRAME_INSTRUCTION' => "complétant le formulaire ci-dessous",
    'HPF_GET_URL_ERROR' => 'Une erreur est survenue dans le fichier {file} (ligne {line}) : {message}',
    'HPF_PAY' => "Payer sur Hello Asso",
    'HPF_PAYMENT_MESSAGE' => <<<TXT
        Vous devez payer une somme de {sum} €. Vous pouvez le faire en {instruction}.
        Pensez bien à recopier correctement votre e-mail ({email})
        et la bonne somme à payer ({sum} €).
        Si vous avez déjà payé, veuillez cliquer {hereLinkStart}ici{hereLinkEnd} pour forcer une synchronisation des données de paiement.
        La fiche concernée est {entryLink}.
        TXT,
    'HPF_PAYMENT_MESSAGE_CB' => <<<TXT
        Vous vous êtes engagé à payer {sum} €. Vous pouvez le faire en {instruction}.
        Le paiement se fera par carte bancaire via le site de HelloAsso.
        Utilisez la même adresse e-mail ({email}) et reportez le bon montant à payer ({sum} €).
        Si vous avez déjà payé, veuillez cliquer {hereLinkStart}ici{hereLinkEnd} pour forcer une synchronisation des données de paiement.
        La fiche concernée est {entryLink}.
        TXT,
    'HPF_PAYMENT_MESSAGE_VIREMENT' => <<<TXT
        Vous vous êtes engagé à payer {sum} €. Vous pouvez le faire en faisant un virement
        sur le compte suivant :
        IBAN : FR76 XXXX XXXX XXXX XXXX XXXX - BIC : XXXXXXXX.
        Indiquez comme référence votre nom et prénom comme dans la fiche (éventuellement votre code postal).
        La fiche concernée est {entryLink}.
        TXT,
    'HPF_PAYMENT_MESSAGE_CHEQUE' => <<<TXT
        Vous vous êtes engagé à payer {sum} €. Vous pouvez le faire en faisant un chèque
        A l'ordre de XXXX
        et à envoyer à xxxx
        en indiquant vos nom, prénom, adresse e-mail et code postal.
        La fiche concernée est {entryLink}.
        TXT,
    'HPF_UPDATED_ENTRY' => "Les données de la fiche '{titre}' affichée ici pourraient ne pas être à jour.\n".
        "Veuillez recharger la fiche.",
    'HPF_NOT_FOR_EMPTY_TAG' => 'rafraichissement impossible pour un tag vide',
    'HPF_FORBIDEN_FOR_THIS_ENTRY' => 'vous n\'avez ps le droit de modifier cette fiche',

    // actions/HPFHelloAssoPaymentsAction.php
    // Feature UUID : hpf-helloasso-payments-table
    'AB_hpf_hpfhelloassopayments_label' => 'Liste des paiements Hello Asso',
    'AB_hpf_hpfhelloassopayments_college1_label' => 'Collège 1',
    'AB_hpf_hpfhelloassopayments_college2_label' => 'Collège 2',
    'AB_hpf_hpfhelloassopayments_college3_label' => 'Collège 3',
    'AB_hpf_hpfhelloassopayments_college4_label' => 'Collège 4',
    'AB_hpf_hpfhelloassopayments_college3to4fieldname_label' => 'Nom du champ d\'association du collège 3 vers le collège 4',
    'AB_hpf_hpfhelloassopayments_partner_label' => 'Partenaires',

    // actions/HpfpaymentsbycatAction.php
    // Feature UUID : hpf-payments-by-cat-table
    'AB_hpf_hpfpaymentsbycat_label' => 'Liste des paiements Hello Asso par catégories',

    // action/HPFRegisterPaymentAction.php
    // Feature UUID : hpf-register-payment-action
    'AB_hpf_hpfregisterpayment_label' => 'Enregistrement simplifié d\'un paiement',
    'AB_hpf_hpfregisterpayment_formsids_label' => 'Formulaires concernés',
    'AB_hpf_hpfregisterpayment_formsids_hint' => 'entiers séparés par des virgules. Ex.: 16,23,24',
    'HPF_REGISTER_A_PAYMENT' => 'Enregistrer un paiement',
    'HPF_REGISTER_PAYMENT_FORM' => 'Formulaire concerné',
    
    // action/HPFRegisterPaymentAction.php
    // Feature UUID : hpf-import-payments
    'HPF_IMPORT_BAD_ERROR_FORMAT' => 'Le format du fichier fournit n\'est pas bon. Il devrait être ".ods", ".csv", ".xls" ou ".xlsx" !',
    'HPF_IMPORT_DATA_FROM' => 'Données extraites de \'%{file}\' :',
    'HPF_IMPORT_MEMBERSHIPS_LABEL' => 'Fichier à importer',
    'HPF_IMPORT_MEMBERSHIPS_TITLE' => 'Import de listes d\'adhésions',
    'HPF_IMPORT_OTHER_FILE' => 'Importer un autre fichier',
    'AB_hpf_hpfimportmembership_label' => 'Import de listes d\'adhésions',

    // config.yaml
    // Feature UUID : hpf-payment-status-action
    'EDIT_CONFIG_HINT_HPF[CONTRIBFORMIDS]' => 'Identifiant des formulaires liés au paiement (avec \'bf_mail\' et un champ \'CalcField\'), séparés par des virgules',
    'EDIT_CONFIG_HINT_HPF[PAYMENTSFORMURLS]' => 'Lien vers le formulaire HelloAsso de paiement associé (séparé par des virgules)',
    'EDIT_CONFIG_HINT_HPF[PAYMENTMESSAGEENTRY]' => 'Fiche avec les messages pour le paiement',
    'EDIT_CONFIG_GROUP_HPF' => 'Paramètres spécifiques HPF',
    // Feature UUID : hpf-area-management
    'EDIT_CONFIG_HINT_AREAFIELDNAME' => 'Nom du champ avec la localisation validée pour les structures',
    'EDIT_CONFIG_HINT_FORMIDAREATODEPARTMENT' => 'Numero du formulaire de correspondance entre région et département',
    'EDIT_CONFIG_HINT_GROUPSADMINSSUFFIXFOREMAILS' => 'Suffix des groupes admins qui peuvent envoyer des e-mails',
    'EDIT_CONFIG_HINT_POSTALCODEFIELDNAME' => 'Nom du champ avec le code postal',

    // docs/actions/bazarliste.yaml via templates/aceditor/actions-builder.tpl.html
    // Feature UUID : hpf-area-management
    'HPF_SELECTMEMBERSPARENT_FORM_LABEL' => 'Formulaire parent',
    'HPF_SELECTMEMBERS_BY_AREA' => 'Membres ET profils de la zone géographique',
    'HPF_SELECTMEMBERS_DISPLAY_FILTERS_LABEL' => 'Ajouter les structures d\'intérêt et le périmètre géographique aux facettes',
    'HPF_SELECTMEMBERS_HINT' => 'Filtre à partir des fiches mères (structures) où je suis administrateur',
    'HPF_SELECTMEMBERS_LABEL' => 'Filtrer les fiches',
    'HPF_SELECTMEMBERS_ONLY_MEMBERS' => 'Uniquement les membres',
    
    // templates/hpf-import-memberships-action.twig
    // Feature UUID : hpf-import-payments
    'HPF_ADJUSTED' => 'Ajustée',
    'HPF_ADD_ENTRY_OR_PAYMENT' => 'Ajouter le paiement',
    'HPF_APPEND_INSTEAD_OF_CREATE' => 'Une fiche existe déjà pour cet e-mail : seul le paiement sera ajouté à la fiche existante, sans mettre à jour le reste ! Cliquer pour voir la fiche.',
    'HPF_COMMENT' => 'Commentaire',
    'HPF_CREATE_ENTRY_NOT_POSSIBLE' => 'Création impossible :',
    'HPF_DATE' => 'Date',
    'HPF_DEPT' => 'Département',
    'HPF_EMAIL' => 'E-mail',
    'HPF_EMAIL_ALREADY_USED' => 'E-mail déjà utilisé',
    'HPF_EMAIL_BADLY_FORMATTED' => 'E-mail mal formatté',
    'HPF_FIRSTNAME' => 'Prénom',
    'HPF_FIRSTNAME_EMPTY' => 'le prénom est vide',
    'HPF_FREE' => 'Libre',
    'HPF_GROUP_MEMBERSHIP' => 'Adhésion de groupe',
    'HPF_GROUP_NAME' => 'Nom du groupe',
    'HPF_GROUP_NAME_EMPTY' => 'le nom du groupe est vide',
    'HPF_IMPORT_HELP' => "Les données importées sont rattachées au département qui correspond au code postal.\n".
        "Si un département est donné, il sera pris en priorité sur le code postal.\n".
        "Si une structure est trouvée pour ce département, elle sera sélectionnée.\n".
        "Si plusieurs structures sont disponibles pour le département, aucune structure ne sera sélectionnée.\n".
        "Un fonctionnement identique est appliqué pour la région associé au département.\n".
        "Aucune structure n'est vraiment associé au paiement. Il est supposé que le paiement de type structure a été encaissé par la structure liée.\n".
        "S'il y a une structure régionale et une structure départementale, il n'est pas possible de savoir laquelle a reçu le paiment.\n".
        "Il est possible d'indiquer le nom de la structure à préférer dans la colonne dédiée. Celle-ci n'est prise en compte que si la structure fait partie des structures du département sélectionné.",
    'HPF_IS_GROUP' => 'Type d\'adhésion',
    'HPF_MEMBERSHIP_TYPE' => 'Type de montant',
    'HPF_NAME' => 'Nom',
    'HPF_NAME_EMPTY' => 'Nle nom est vide',
    'HPF_PAYMENT_NUMBER' => 'Numéro de paiement',
    'HPF_PERSONAL_MEMBERSHIP' => 'Adhésion individuelle',
    'HPF_POSTAL_CODE' => 'Code postal',
    'HPF_POSTAL_CODE_BADLY_FORMATTED' => 'Code postal mal formatté',
    'HPF_POSTAL_CODE_OR_DEPT_MISSING' => 'Code postal ou département manquant',
    'HPF_PROCESS' => 'Traiter les données',
    'HPF_PROCESSING' => 'Traitement des données en cours',
    'HPF_PUBLIC_VISIBILITY' => 'Visibilité publique ?',
    'HPF_STANDARD' => 'Standard',
    'HPF_SUPPORT' => 'Soutien',
    'HPF_TOWN' => 'Ville',
    'HPF_VALUE' => 'Valeur',
    'HPF_WANTED_STRUCTURE' => 'Structure visée',
    'HPF_YEAR' => 'Année d\'adhésion',
];
