# Documentation de l'extension HPF

## Intention de l'extension

L'extension cherche à regrouper les différentes fonctionnalités développées pour le site <https://www.habitatparticipatif-france.fr>.

Tout est réutilisable sur d'autres YesWiki mais la conception est d'abord pensée pour les besoins de ce site.

## Fonctionnement de cette extension

L'extension est pleinement fonctionnelle si les extensions suivantes sont installées:
 - [`alternativeupdatej9rem`](https://github.com/J9rem/yeswiki-extension-alternativeupdatej9rem)
 - [`groupmanagement`](https://github.com/J9rem/yeswiki-extension-groupmanagement)
 - [`shop`](https://github.com/J9rem/yeswiki-extension-shop)

## Autre source de documentation

Des fonctionnalités communes au site <https://www.habitatparticipatif-france.fr> et aussi à d'autres sites ont été mutualisées dans l'extension [`alternativeupdatej9rem`](https://github.com/J9rem/yeswiki-extension-alternativeupdatej9rem).

La documentation embarquée de cette extension est disponible [ICI](/tools/alternativeupdatej9rem/docs/fr/README.md)

## Fonctionnalités

|**Type**|**Fonctionnalité**|**Objectifs**|**Identifiant unique**|
|:-|:-|:-|:-|
|Action|`HPFHelloAssoPayments`|Affiche les paiements HelloAsso dans un tableau par type de collège, mois et année|[`hpf-helloasso-payments-table`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-helloasso-payments-table&type=code)|
||`HpfpaymentsByCat`|Affiche les paiements dans une tablea par zone géographique et par année|[`hpf-payments-by-cat-table`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-payments-by-cat-table&type=code)|
||`HpfPaymentStatus`|Affiche un texte si un paiment doit être réalisé|[`hpf-payment-status-action`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-payment-status-action&type=code)|
||`hpfregisterpayment`|Enregistrer un paiement dans une fiche|[`hpf-register-payment-action`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-register-payment-action&type=code)|
||`hpfimportmembership`|Importer les paiments depuis un tableau|[`hpf-import-payments`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-import-payments&type=code)|
|Nouvelle fonctionnalité|`area-management`|Gérer les membres en fonction de leur zone géographique|[`hpf-area-management`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-area-management&type=code)|
|Champ Bazar|`conditionview`|Champ pour afficher une zone en fonction d'une condition|[`hpf-condition-view-field`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-condition-view-field&type=code)|
||`payments`|Champ pour enregistrer les détails sur les paiements|[`hpf-payments-field`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-payments-field&type=code)|
|Route api|`/api/shop/helloasso/{token}`|Enregistre les informations de paiements lors d'un appel de l'api depuis HelloAsso|[`hpf-api-helloasso-token-triggered`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-api-helloasso-token-triggered&type=code)|
|Template Bazar|`list-no-empty`|Template bazar pour afficher une liste dynamique uniquement si non vide|[`hpf-bazar-template-list-no-empty`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-bazar-template-list-no-empty&type=code)|
||`tableau-link-to-group`|Template statique bazar tableau avec lien vers les groupes (OBSOLETE)|[`hpf-bazar-template-tableau-link-to-group`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-bazar-template-tableau-link-to-group&type=code)|
||`tableau-with-email`|Template statique bazar tableau avec affichage des e-mails (OBSOLETE)|[`hpf-bazar-template-tableau-with-email`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-bazar-template-tableau-with-email&type=code)|

## Affichage des données sensibles (gestion des régions)

### Configuration

Pour afficher les données sensibles, il est nécessaire de fournir au ficher de configuration du wiki, le nom du champ qui correspond aux régions ou aux départements correspondant à la structure. IL est conseillé que ce champ soit modéré par les administrateurs.

  1. dans le formulaire correspondant aux structures, créer un champ de type `checkbox`, `liste` ou `radio` qui permet aux usagers de fournir une indication de localisation (région ou département par exemple).
  2. dans ce même formulaire, créer un autre champ de type `checkbox`, `liste` ou `radio` uniquement accessibles pour les administrateurs et qui permet de définir, après modération, quelles sont les zones géographiques validées pour cette structure. Ce champ peut représenter les départements alors que le premier champ accessible à tous les usagers peut représenter un niveau différent (exemple, les régions).
  3. Noter le nom du champ uniquement accessible aux administrateurs (exemple `checkboxListeDepartementsFrancaisbf_departements_valides` ou `bf_departements_valides`, le nom court est plus stable et fonctionnera très bien).
  4. se rendre sur la page `GererConfig` de votre wiki (vous pouvez cliquer ci-dessous pour vous y rendre)
  ```yeswiki preview=70px
  {{button link="GererConfig" class="btn-primary new-window" text="Se rendre sur la page GererConfig" title="Se rendre sur la page GererConfig"}}
  ```
  5. dans la partie `HPF`, recopier le nom du champ pour le paramètre `AreaFieldName`
  6. dans la partie `HPFe`, vérifier aussi le nom du champ pour le paramètre `PostalCodeFieldName` afin qu'il corresponde au nom utilisé pour le champ code postal dans les formulaire.

### Fonctionnement

Pour afficher les données sensibles, il faut configurer l'action `bazarliste` en suivant cette procédure.

 1. modifier une page (handler `/edit`)
 2. Appuyer sur le bouton composant  
    ![image du bouton composant](images/bouton_composant.png ':size=300')
 3. Choisir ensuite "Afficher les données d'un formulaire"  
    ![menu de choix des composanrs](images/display_data_in_form.png ':size=300')
 4. Choisir le formulaire à afficher et le format des données (`template`)
 5. Cocher la case "Paramètres Avancés"  
    ![case à cocher paramètres avancés](images/parametres_avancees.png ':size=300')
 6. puis choisir dans le menu "filtrer les fiches", l'option désirée entre "uniquement les membres" et "membres ET profiles de la zone géographique"  
    ![menu filtrage des fiches](images/filter_menu.png ':size=300')
 7. si l'option "membres ET profiles de la zone géographique", il faudra choisir le formulaire parent associé  
    ![copie d'écran choix du formulaire](images/choix_formulaire.png ':size=300')
 8. Cocher la case "Ajouter les fiches mères aux filtres" pour ajouter lee choix des fiches structures mères aux facettes.

### Critère d'affichage pour "membres ET profiles de la zone géographique"

||Structure|Structure|Acteur|Acteur|Acteur|Acteur|Est affiché ?|
|:-|:-|:-|:-|:-|:-|:-|:-|
|_Nom_|**Région**|**Départements validés**|**Région**|**Département**|**Code postal**|**Est membre ?**||
|_Nom du champ_|`bf_region`|`checkboxListeDepartementsFrancais`|`bf_region_adhesion`|`bf_departement_adhesion`|`bf_code_postal`|`bf_structure_locale_adhesion`|
|_Liste associée_|`ListeRegionsFrancaises`|`ListeDepartementsFrancais`|`ListeRegionsFrancaises`|`ListeDepartementsFrancais`|---|formulaire structure|
||peu importe|peu importe|peu importe|peu importe|peu importe|**oui**|oui|
||peu importe|**vide**|peu importe|peu importe|peu importe|non|non|
||peu importe|**Morbihan,Finistère**|Bretagne|vide|peu importe|non|oui (*)|
||peu importe|**Morbihan,Finistère**|PACA|peu importe|vide|non|non|
||peu importe|**Morbihan,Finistère**|vide|vide|vide|non|non|
||peu importe|**Morbihan,Finistère**|peu importe|Finistère|peu importe|non|oui|
||peu importe|**Morbihan,Finistère**|Bretagne|Ille-et-Vilaine|vide|non|non|
||peu importe|**Morbihan,Finistère**|PACA|peu importe|**56000**|non|oui|
||peu importe|**Morbihan,Finistère**|PACA|peu importe|**13000**|non|non|
||peu importe|**Morbihan,Finistère**|vide|vide|**56000**|non|oui|
||peu importe|**Morbihan,Finistère**|vide|vide|**13000**|non|non|
||peu importe|**Morbihan,Finistère**|Bretagne|Ille-et-Vilaine|**56000**|non|oui|
||peu importe|**Morbihan,Finistère**|Bretagne|Ille-et-Vilaine|**13000**|non|non|

(*): ne fonctionne que si un formulaire d'association de régions et de départements a été créé (voir _plus bas_)

**important** : la détection automatique de département à partir du code postal ne fonctionne que si la liste des départements possède le bon numéro de département comme clé et que si le paramètre `departmentListName` est bien défini avec le nom de cette liste.

#### Création du formulaire d'association entre régions et départements

 1. créer un formulaire avec ce code
    ```
    titre***Départements de {{bf_region}}***Titre Automatique***
    liste***ListeRegionsFrancaises***Région*** *** *** ***bf_region*** ***1*** *** *** * *** * *** *** *** ***
    checkbox***ListeDepartementsFrancais***Départements*** *** *** ***bf_departement*** ***1*** *** *** * *** * *** *** *** ***
    acls*** * ***@admins***comments-closed***
    ```
 2. enregistrer puis revenir modifier le formulaire avec le constructeur graphique pour sélectionner les bonnes listes
 3. Puis créer une fiche région pour chaque région française de la liste des régions.
 4. se rendre sur la page `GererConfig` de votre wiki (vous pouvez cliquer ci-dessous pour vous y rendre)
 ```yeswiki preview=70px
 {{button link="GererConfig" class="btn-primary new-window" text="Se rendre sur la page GererConfig" title="Se rendre sur la page GererConfig"}}
 ```
 5. dans la partie `HPF`, mettre le numéro du formulaire en question pour le paramètre `formIdAreaToDepartment`