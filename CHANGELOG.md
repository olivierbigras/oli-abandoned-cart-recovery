# Changelog

## 1.2.0

J'ai refait l'interface d'administration pour qu'elle tienne en quatre écrans et qu'un premier réglage se fasse sans se tromper :

* J'ai regroupé l'admin sous un seul sous-menu WooCommerce, « Récupération de paniers », avec quatre écrans : Tableau de bord, Paniers, Courriels, Réglages. Les anciennes adresses à onglets redirigent vers l'écran ou la vue équivalente.
* J'ai ajouté un assistant en 4 étapes pour les nouvelles installations (consentement, séquence, expéditeur et test, activation). Les relances restent à l'arrêt tant qu'on ne clique pas « Activer les relances » ; les paniers consentis peuvent être enregistrés entre-temps, sans jamais être relancés automatiquement.
* Pour une nouvelle installation, j'ai mis la conservation à 90 jours (les paniers non récupérés restent supprimés après 30 jours) et j'ai laissé la relance 2 avec coupon et l'accès des gestionnaires de boutique désactivés.
* Pour une mise à jour depuis une 1.1.x, je garde tout tel quel (réglages, modèles, paniers, journal, relances actives), je n'affiche pas l'assistant et je montre un seul avis fermable sur les écrans du plugin.
* J'ai rendu la migration des modèles stricte et idempotente : une mise à jour ne remplace un objet, un corps, un titre ou un libellé de bouton que s'il est identique à l'octet près à un ancien texte par défaut (par exemple les défauts anglais écrits par la 1.1.4 sur un site en français). Un texte modifié par l'admin, un texte en français, la clé plate héritée de la 1.0.x et un défaut retouché d'une espace ou d'une majuscule restent tels quels. Avant, la comparaison ignorait les espaces et la clé plate suivait toujours la langue de repli. Test : tests/test-template-migration.php.
* J'ai refait le tableau de bord (4 tuiles avec leur définition, état vide, paniers à relancer, derniers récupérés), la liste des paniers (vues, actions toujours visibles, panneau avec frise), la séquence de courriels (cartes, interrupteur, aperçu rendu avec des données d'exemple) et les réglages (résumé en 4 lignes, avancé enregistré section par section).
* J'ai ajouté un bandeau de statut (actif, à l'arrêt, envoi bloqué) sur le Tableau de bord et les Réglages, et je n'affiche plus la demande de don qu'une fois, sur les Réglages.
* À la caisse, j'ai changé le libellé de la case, retiré la mention « (facultatif) » et ajouté une ligne d'aide (désinscription, durée de conservation, lien vers la politique de confidentialité).
* J'affiche les onglets de langue seulement sur un site multilingue, sans code de locale.
* Pendant l'arrêt, le bandeau et l'assistant disent que les paniers consentis peuvent être enregistrés mais qu'aucun courriel n'est envoyé ; au moment d'activer, j'indique combien de paniers enregistrés pendant l'arrêt ne seront pas relancés automatiquement, avec un lien vers Paniers.
* J'ai travaillé l'accessibilité (noms accessibles, écrans sans défilement horizontal jusqu'à 390 px, couleurs qui suivent le schéma de l'admin).
* Je charge une feuille de style d'admin minifiée (la source lisible admin.css est livrée et sert quand SCRIPT_DEBUG est actif) ; tools/build-css.py la régénère.

## 2026-10-23

* composer.json : nom du paquet renommé de bigrat95/ en olivierbigras/

## 1.1.7

* Include `languages/` (POT and French translations) in the GitHub / `git archive` ZIP and in the WordPress.org / `build-zip.sh` package
* Declare `Domain Path: /languages` and call `load_plugin_textdomain()` on `init` only on WordPress < 6.7
* Use the `wp_verify_nonce()` result directly in a conditional on the unsubscribe confirmation form (Plugin Check)
* Show a translatable error and the confirmation form again when an unsubscribe POST has an invalid nonce
