# Documentationof extension HPF

## Aim of extension

This extension tries to group several features for website <https://www.habitatparticipatif-france.fr>.

Eveything can be reuse on other websites but the features are think to use first with this website.

## Fonctiunning

The extension is fully working if these extension are installed:
 - [`alternativeupdatej9rem`](https://github.com/J9rem/yeswiki-extension-alternativeupdatej9rem)
 - [`groupmanagement`](https://github.com/J9rem/yeswiki-extension-groupmanagement)
 - [`shop`](https://github.com/J9rem/yeswiki-extension-shop)

## Other sources of documentation

Commons features of site <https://www.habitatparticipatif-france.fr> and other siteswere mutualized in extension [`alternativeupdatej9rem`](https://github.com/J9rem/yeswiki-extension-alternativeupdatej9rem).

Embeded documentation of this extension is available [HERE](/tools/alternativeupdatej9rem/docs/fr/README.md)

## Features

see [docs/fr](../fr/)

## Display private data for administrators (area management)

### Configuration

To display private data for administrators, the name of the field corresponding to areas or departements of the strucutres must be type into the configuration file.

  1. in the form dedicated to structures, create a field of type `checkbox`, `liste` or `radio` to allow users to define areas concerning the structure (area or departement by example).
  2. in the same form, create another field of type `checkbox`, `liste` or `radio` only accessible to yeswiki administrators and whiwh allows, after moderation, what are the validated areas for each structure. This field can represent a departement even if the first field accessible by all users can correspond to a different level (example, areas).
  3. Note fieldname only accessible to administrators (example `checkboxListeDepartementsFrancaisbf_departements_valides` or `bf_departements_valides`, the short name is more stable and will work).
  4. go to page `GererConfig` (you can click bellow to go to this page)
  ```yeswiki preview=70px
  {{button link="GererConfig" class="btn-primary new-window" text="Go to page GererConfig" title="Go to page GererConfig"}}
  ```
  5. in part `Hpf`, copy the field name in the parameter `AreaFieldName`
  6. in part `Hpf`, check the field name in the parameter `PostalCodeFieldName` to be sure that it is the same as the one used in wiki's forms.


### Usage

To display private data, action `bazarliste` should be configured by the following procedure.

 1. edit a page (handler `/edit`)
 2. Click on button components
 3. Then choose "Display form data"
 4. Choose the form to display and the template
 5. Check box "Advanced Parameters"
 6. then choose in the menu "filter entries", the wanted option between "only members" and "members AND profiles from area"
 7. if option "members AND profiles from area",it is needed to choose the parent form id
 8. Check box "Add parents entries to filters" to add parent entries to filters.