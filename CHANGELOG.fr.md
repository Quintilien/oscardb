# OscarDB — historique des versions

*[English](CHANGELOG.md) · Français*

La numérotation suit la forme `x.y.z` depuis la version 2.0.0 :
`x` change quand l'installation doit être adaptée (disposition, configuration),
`y` quand une fonctionnalité s'ajoute, `z` pour un correctif.

Renumérotation à la version 5.0.2 : le logiciel s'appelait « OscarDB5 »,
le 5 marquant sa cinquième génération (après les versions Python). Le nom
devient OscarDB et le 5 passe dans le numéro de version : OscarDB5 3.0.0 et
3.0.1 deviennent 5.0.0 et 5.0.1. Les entrées antérieures gardent leur
numéro d'origine.

## 5.0.2 — 2 novembre 2026

Aucun changement de fonctionnement : préparation de la publication du code.

- `README.md` en anglais, `README.fr.md` en français ; historique dans
  `CHANGELOG.md` / `CHANGELOG.fr.md`.
- Commentaires du code en anglais ; anciens blocs de code laissés en
  commentaire supprimés. Les libellés de l'interface ne changent pas.
- Base ouverte par défaut : `Bouquins.db` (l'ancien `essai01.db` n'existait
  pas dans le dépôt).
- Base d'exemple « Bouquins » : les notes longues reprises de Wikipédia sont
  remplacées par des notes courtes.
- Licence : fichier `LICENSE` (texte de l'AGPL, reconnu par GitHub) ;
  copyright « PhS & Quintilien ».
- Nom : OscarDB (anciennement OscarDB5), comme le dépôt. Une installation
  existante garde le titre défini dans son `config/oscar.php`.
- `.gitignore` : seules les trois bases d'exemple sont suivies dans `data/` ;
  tout autre fichier y est ignoré (vos propres bases ne sont jamais
  versionnées).
- Dépôt public : `github.com/Quintilien/oscardb`.

Mise à jour : `git pull` (rien d'autre, même sous Docker). Une installation
existante garde sa configuration (`config/oscar.php`) et ses bases.

## 5.0.1 — 1er novembre 2026

Correctifs issus d'une revue de sécurité de tous les points d'entrée, par
un relecteur indépendant. Aucun changement d'installation : `git pull`
(Docker : `docker compose up -d --build`).

- Paramètre de retour (`back`) : tout espace refusé, y compris Unicode
  (une adresse précédée d'un espace pouvait mener vers un autre site).
- Formulaire : la table, l'identifiant et l'adresse de retour viennent de
  l'adresse seulement ; une colonne nommée `table` ou `back` ne peut plus
  les remplacer.
- Paramètres d'adresse : toujours des chaînes (plus d'erreur 500 sur
  `?x[]=…`) ; numéro de page borné.
- Export ICS : UID nettoyé ; une date invalide écarte l'événement au lieu
  de faire échouer l'export ; lignes repliées sans couper un caractère ;
  noms de fichiers assainis ; archive temporaire à nom unique.
- Export Excel : en-têtes de colonnes toujours en texte (jamais en
  formule) ; nom de fichier assaini.
- Squelette `.yml` généré : clés et valeurs entre guillemets.
- README : précision sur l'en-tête `Remote-User` et le bandeau.

## 5.0.0 — 1er novembre 2026

Disposition alignée sur CMS Lite. Aucune fonctionnalité nouvelle ; les
bases `.db` et les fichiers `.yml` sont inchangés. Procédure : README,
« Migration depuis la version 2.0.0 ».

- **Disposition** : `lib/` devient `src/` (convention pds/skeleton, comme
  CMS Lite) : `config/`, `data/`, `public/`, `src/`, `vendor/`.
- **Configuration** : le dépôt fournit `config/oscar.php.example` ; votre
  réglage va dans `config/oscar.php`, ignoré par Git, donc jamais écrasé
  par une mise à jour. Sans lui, le modèle est lu.
- **Version** définie dans `src/lib.php` (`OSCAR_VERSION`), plus dans la
  configuration.
- `lib/socle.php` et `SOCLE.md` abandonnés (mécanisme maison) : les mêmes
  protections tiennent en quelques lignes standard au début de
  `src/lib.php`. Comportement inchangé.
- En-tête `X-Powered-By` retiré (il annonçait la version de PHP).
- Nouvelle clé `timezone` (défaut `Europe/Brussels`) : les dates produites
  par OscarDB (noms des exports Excel…) ne sont plus en UTC sous Docker.
- README : section « Selon votre serveur » (Apache, mutualisé sans Apache,
  VPS avec ou sans Docker, cas non pris en charge).

## 2.0.0 — 29 octobre 2026

Disposition et sécurité. Aucune fonctionnalité nouvelle ; les bases `.db` et
les fichiers `.yml` sont inchangés.

- **Disposition** : seul `public/` est la racine web. `config/`, `lib/`,
  `vendor/` et `data/` sont à côté, hors de portée du navigateur.
  Remplace l'ancien dossier `app/` (servi en entier, `vendor/` et
  `config/` compris).
- **CSRF** : jeton sur tous les formulaires (enregistrement, suppression,
  choix de la base, export ICS) ; tout POST sans jeton valide est refusé.
- **Paramètre `back`** : n'accepte plus qu'une adresse locale à
  l'application (auparavant : redirection possible vers un autre site
  après une suppression, et lien « Retour » acceptant `javascript:`).
- **Erreurs PHP** masquées par défaut (`'debug' => false` dans
  `config/oscar.php`) ; auparavant `display_errors=1` en dur.
- **Session** : cookie `oscardb`, `HttpOnly`, `SameSite=Lax`, `Secure` en
  HTTPS.
- **En-têtes** : `X-Frame-Options`, `frame-ancestors`, `nosniff`,
  `Referrer-Policy`.
- **Bandeau « interface non protégée »** lorsque aucune authentification
  amont n'est visible (désactivable par `'warn_unprotected' => false`).
- `default_database` ne contient plus qu'un nom de fichier de `data/`.
- Fichiers `.htaccess` et `index.php` à la racine du projet : filet de
  secours pour Apache si le projet entier est déposé dans la racine web.
- Mécanismes communs regroupés dans `lib/socle.php` (socle v1.0, partagé
  avec CMS Lite et Quintilien).
- `vendor/` (PhpSpreadsheet) est versionné : le
  dépôt s'installe tel quel, sans Composer.
- Les versions 1.x ne sont plus prises en charge.

## 1.24 — 9 août 2026

- Trois clés YAML optionnelles pour le widget de recherche des clés
  étrangères : `lookup_where`, `lookup_order_by`, `lookup_limit`.

## 1.23 — 7 août 2026

- Correctif du menu de sélection (lookup AJAX) : la liste se rouvrait à la
  reprise de focus d'un champ déjà rempli, ou après une sélection en cas de
  frappe rapide.
- Export Excel des vues (PhpSpreadsheet).
- Cache-busting des assets (`?v=<version>`).
