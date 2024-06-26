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
    'AB_hpf_hpfpaymentstatus_view_handler_label' => 'Paiement direct Hello Asso',
    'AB_hpf_hpfpaymentstatus_view_iframe_label' => 'Iframe Hello Asso',
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
    'HPF_UPDATED_ENTRY' => "Les données de la fiche '{titre}' affichée ici pourraient ne pas être à jour.\n" .
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
    'EDIT_CONFIG_HINT_HPF[GLOBALFORMURLS]' => 'Liens vers les formulaires HelloAsso de paiement généraux (séparés par des virgules)',
    'EDIT_CONFIG_HINT_HPF[PAYMENTSFORMURLS]' => 'Liens vers les formulaires HelloAsso de paiement associés (séparés par des virgules)',
    'EDIT_CONFIG_HINT_HPF[PAYMENTMESSAGEENTRY]' => 'Fiche avec les messages pour le paiement',
    'EDIT_CONFIG_GROUP_HPF' => 'Paramètres spécifiques HPF',
    // Feature UUID : hpf-area-management
    'EDIT_CONFIG_HINT_AREAFIELDNAME' => 'Nom du champ avec la localisation validée pour les structures',
    'EDIT_CONFIG_HINT_FORMIDAREATODEPARTMENT' => 'Numero du formulaire de correspondance entre région et département',
    'EDIT_CONFIG_HINT_GROUPSADMINSSUFFIXFOREMAILS' => 'Suffix des groupes admins qui peuvent envoyer des e-mails',
    'EDIT_CONFIG_HINT_POSTALCODEFIELDNAME' => 'Nom du champ avec le code postal',
    // Feature UUID : hpf-receipts-creation
    'EDIT_CONFIG_HINT_HPF[CANVIEWRECEIPTS]' => 'Qui peut voir les reçu ? admins ou % (admins et propriétaire de la fiche)',
    'EDIT_CONFIG_HINT_HPF[STRUCTUREINFO][NAME]' => 'Nom de la structure pour les reçus',
    'EDIT_CONFIG_HINT_HPF[STRUCTUREINFO][ADDRESS]' => 'Adresse de la structure pour les reçus',
    'EDIT_CONFIG_HINT_HPF[STRUCTUREINFO][ADDRESSCOMPLEMENT]' => 'Complément d\'adresse de la structure pour les reçus',
    'EDIT_CONFIG_HINT_HPF[STRUCTUREINFO][POSTALCODE]' => 'Code postal de la structure pour les reçus',
    'EDIT_CONFIG_HINT_HPF[STRUCTUREINFO][TOWN]' => 'Ville de la structure pour les reçus',
    'EDIT_CONFIG_HINT_HPF[STRUCTUREINFO][EMAIL]' => 'E-mail de la structure pour les reçus',
    'EDIT_CONFIG_HINT_HPF[STRUCTUREINFO][WEBSITE]' => 'Lien vers le site internet de la structure pour les reçus',

    // controllers/ApiController.php
    // Feature UUID : hpf-receipts-creation
    'HPF_RECEIPT_API_CAN_NOT_SEE_RECEIPT' => 'Vous n\'avez pas le droit d\'accéder à ce reçu !',

    // docs/actions/bazarliste.yaml via templates/aceditor/actions-builder.tpl.html
    // Feature UUID : hpf-area-management
    'HPF_SELECTMEMBERSPARENT_FORM_LABEL' => 'Formulaire parent',
    'HPF_SELECTMEMBERS_BY_AREA' => 'Membres ET profils de la zone géographique',
    'HPF_SELECTMEMBERS_DISPLAY_FILTERS_LABEL' => 'Ajouter les structures d\'intérêt et le périmètre géographique aux facettes',
    'HPF_SELECTMEMBERS_HINT' => 'Filtre à partir des fiches mères (structures) où je suis administrateur',
    'HPF_SELECTMEMBERS_LABEL' => 'Filtrer les fiches',
    'HPF_SELECTMEMBERS_ONLY_MEMBERS' => 'Uniquement les membres',

    // fields/ReceiptsField.php
    // Feature UUID : hpf-receipts-creation
    'HPF_RECEIPTSFIELD_LABEL' => 'Liste des reçus',

    // handlers/DirectPaymentHandler.php
    // Feature UUID : hpf-direct-payment-helloasso
    'HPF_CURRENT_USER_SHOULD_HAVE_SAME_EMAIL_AS_ENTRY' => 'L\'utilisateurice courante doit avoir le même e-mail que celui de la fiche.',
    'HPF_DIRECT_PAYMENT_CANCEL' => "Vous avez annulé le paiement en cours.\n%{specificMessage}\n\n%{entryLink}",
    'HPF_DIRECT_PAYMENT_CANCEL_NOTHING_TO_PAY' => "Il semblerait que votre fiche ait été mise à jour pendant ce temps.\nLe montant actuel à payer est nul. Vous pouvez vérifier ceci sur votre fiche en cliquant ci-dessous.",
    'HPF_DIRECT_PAYMENT_CANCEL_REDO' => "Il semblerait que vous ayez toujours à payer la somme %{ofAmount}.\nVous pouvez payer cette somme en suivant les indications dans le formulaire ci-dessous ou juste consulter sur votre fiche avec ce lien.",
    'HPF_DIRECT_PAYMENT_ERROR' => "Une erreur est survenue lors de votre paiement %{ofAmount}.\nL'opération a été annulée.\n%{specificMessage}\n\n%{entryLink}",
    'HPF_DIRECT_PAYMENT_LINK_TO_ENTRY' => "Voir votre fiche %{title}",
    'HPF_DIRECT_PAYMENT_OF' => 'de',
    'HPF_DIRECT_PAYMENT_SUCCESS' => "Votre paiement %{ofAmount}a bien été enregistré.\nMerci pour votre contribution.\n\n%{warningMessage}%{entryLink}",
    'HPF_DIRECT_PAYMENT_SUCCESS_WARNING' => "Toutefois, l'information est encore en cours d'enregistrement sur le site, ...\nVeuillez cliquer sur le lien ci-dessous pour vérifier les données de votre fiche.\n\n",
    'HPF_DIRECT_PAYMENT_TITLE' => 'Paiement d\'adhésion et don à Habitat Participatif France pour la fiche \'%{entryId}\'',
    'HPF_NOTHING_TO_PAY' => 'Vous n\'avez rien à payer pour cette fiche.',
    'HPF_SHOULD_BE_AN_ENTRY' => 'Cet handler n\'est utilisable que pour les fiches.',
    'HPF_SHOULD_BE_AN_ENTRY_FOR_PAYMENT' => 'Cette fiche n\'est pas associée à un formulaire de paiement.',
    'HPF_SHOULD_BE_AN_ENTRY_FOR_FORM_WITH_UNIQ_ENTRY_BY_USER' => 'Cette fiche n\'appartient pas à un formulaire pour lequel un '
        . 'utilisateur ne peut avoir qu\'une seule fiche.',

    // templates/hpf-import-memberships-action.twig
    // Feature UUID : hpf-import-payments
    'HPF_ADJUSTED' => 'Ajustée',
    'HPF_ADD_ENTRY_OR_PAYMENT' => 'Ajouter le paiement',
    'HPF_ALREADY_APPENDED' => 'Une fiche existe déjà pour cet e-mail et le paiement a déjà été enregistré ! Cliquer pour voir la fiche.',
    'HPF_APPEND_INSTEAD_OF_CREATE' => 'Une fiche existe déjà pour cet e-mail : seul le paiement sera ajouté à la fiche existante, sans mettre à jour le reste ! Cliquer pour voir la fiche.',
    'HPF_CHEQUE_TYPE' => 'HPF (par chèque)',
    'HPF_COMMENT' => 'Commentaire',
    'HPF_CREATE_ENTRY_NOT_POSSIBLE' => 'Création impossible :',
    'HPF_DATE' => 'Date',
    'HPF_DEPT' => 'Département',
    'HPF_EMAIL' => 'E-mail',
    'HPF_EMAIL_ALREADY_USED' => 'E-mail déjà utilisé',
    'HPF_EMAIL_BADLY_FORMATTED' => 'E-mail mal formatté',
    'HPF_ESPECES_TYPE' => 'HPF (par espèces)',
    'HPF_FIRSTNAME' => 'Prénom',
    'HPF_FIRSTNAME_EMPTY' => 'le prénom est vide',
    'HPF_FREE' => 'Libre',
    'HPF_GROUP_MEMBERSHIP' => 'Adhésion de groupe',
    'HPF_GROUP_NAME' => 'Nom du groupe',
    'HPF_GROUP_NAME_EMPTY' => 'le nom du groupe est vide',
    'HPF_IMPORT_HELP' => "Les données importées sont rattachées au département qui correspond au code postal.\n" .
        "Si un département est donné, il sera pris en priorité sur le code postal.\n" .
        "Si une structure est trouvée pour ce département, elle sera sélectionnée.\n" .
        "Si plusieurs structures sont disponibles pour le département, aucune structure ne sera sélectionnée.\n" .
        "Un fonctionnement identique est appliqué pour la région associé au département.\n" .
        "Aucune structure n'est vraiment associé au paiement. Il est supposé que le paiement de type structure a été encaissé par la structure liée.\n" .
        "S'il y a une structure régionale et une structure départementale, il n'est pas possible de savoir laquelle a reçu le paiment.\n" .
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
    'HPF_RECEIVED_BY' => 'Encaissé par',
    'HPF_STANDARD' => 'Standard',
    'HPF_STRUCTURE_TYPE' => 'une des structures locales',
    'HPF_SUPPORT' => 'Soutien',
    'HPF_TOWN' => 'Ville',
    'HPF_VALUE' => 'Valeur',
    'HPF_VIREMENT_TYPE' => 'HPF (par virement)',
    'HPF_WANTED_STRUCTURE' => 'Structure visée',
    'HPF_YEAR' => 'Année d\'adhésion',

    // tempaltes/bazar/fields/receipts.twig
    // Feature UUID : hpf-receipts-creation
    'HPF_RECEIPT_GENERATING' => 'Reçu en cours de génération',
    'HPF_RECEIPT_NOT_EXISTING' => 'Reçu non existant',
    'HPF_DOWNLOAD_RECEIPT' => 'Télécharger le reçu',
];
