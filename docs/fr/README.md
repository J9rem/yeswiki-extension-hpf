# Documentation de l'extension HPF

## Intention de l'extension

L'extension cherche à regrouper les différentes fonctionnalités développées pour le site <https://www.habitatparticipatif-france.fr>.

Tout est réutilisable sur d'autres YesWiki mais la conception est d'abord pensée pour les besoins de ce site.

## Fonctionnalités

|**Type**|**Fonctionnalité**|**Objectifs**|**Identifiant unique**|
|:-|:-|:-|:-|
|Action|`HPFHelloAssoPayments`|Affiche les paiements HelloAsso dans un tableau par type de collège, mois et année|[`hpf-helloasso-payments-table`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-helloasso-payments-table&type=code)|
||`HpfpaymentsByCat`|Affiche les paiements dans une tablea par zone géographique et par année|[`hpf-payments-by-cat-table`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-payments-by-cat-table&type=code)|
||`HpfPaymentStatus`|Affiche un texte si un paiment doit être réalisé|[`hpf-payment-status-action`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-payment-status-action&type=code)|
||`hpfregisterpayment`|Enregistrer un paiement dans une fiche|[`hpf-register-payment-action`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-register-payment-action&type=code)|
|Champ Bazar|`conditionview`|Champ pour afficher une zone en fonction d'une condition|[`hpf-condition-view-field`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-condition-view-field&type=code)|
||`payments`|Champ pour enregistrer les détails sur les paiements|[`hpf-payments-field`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-payments-field&type=code)|
|Route api|`/api/shop/helloasso/{token}`|Enregistre les informations de paiements lors d'un appel de l'api depuis HelloAsso|[`hpf-api-helloasso-token-triggered`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-api-helloasso-token-triggered&type=code)|
|Template Bazar|`list-no-empty`|Template bazar pour afficher une liste dynamique uniquement si non vide|[`hpf-bazar-template-list-no-empty`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-bazar-template-list-no-empty&type=code)|
||`tableau-link-to-group`|Template statique bazar tableau avec lien vers les groupes (OBSOLETE)|[`hpf-bazar-template-tableau-link-to-group`](https://github.com/search?q=repo%3AJ9rem%2Fyeswiki-extension-hpf%20hpf-bazar-template-tableau-link-to-grouptype=code)|