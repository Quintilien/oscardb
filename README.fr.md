# OscarDB

*[English](README.md) · Français*

Version 5.0.2 — 2 novembre 2026 — historique : [CHANGELOG.fr.md](CHANGELOG.fr.md)

**Interface web CRUD générique pour bases de données SQLite**

> OscarDB, c'est un CRUD auto-généré à partir du schéma SQLite existant, avec une surcouche YAML pour l'habillage — pas un constructeur de base de données, juste une interface pour une base qui existe déjà.

OscarDB est une application web légère, écrite en PHP, qui transforme automatiquement n'importe quel fichier `.db` SQLite en interface de gestion de données — listes paginées, formulaires de saisie, recherche, clés étrangères — **sans rien coder**.

Comparable dans l'esprit à l'admin auto-généré de Django, mais sans framework : juste PHP + SQLite, zéro dépendance serveur, zéro build step.

<p align="center"><img src="docs/papy-fichier.jpg" alt="Papy range une fiche dans son fichier" width="480"></p>

OscarDB fait partie d'une petite famille d'outils auto-hébergés, avec [CMS Lite](https://github.com/Quintilien/cms_lite) (la page d'accueil de vos outils).

---

## Philosophie : KISS

> *Keep It Simple, Stupid*

OscarDB s'adresse à l'utilisateur qui veut **gérer ses données** sans voir "ce qui se passe sous le capot". Il repose sur une idée simple :

- **Une base SQLite = un fichier `.db`** → sobre, portable, sans serveur de base de données
- **Les vues SQLite** définissent les listes en lecture seule
- **Les tables SQLite** offrent un accès CRUD complet (créer, lire, modifier, supprimer)
- **Un fichier `.yml` compagnon** gère la présentation (largeurs, labels, types de champs, colonnes masquées, comportement des recherches par clé étrangère)

La base de données reste **propre** : OscarDB n'y injecte jamais de table interne. Toute la présentation vit dans un fichier séparé, à côté du `.db`.

### Ce qu'OscarDB n'est **pas**

- **Pas un no-code / constructeur de schéma** — contrairement à Airtable, NocoDB ou Baserow, OscarDB ne crée pas la structure de la base pour vous : il l'affiche. La base SQLite doit déjà exister (tables, vues, clés étrangères).
- **Pas un ORM ni un framework applicatif** — pas de migrations, pas de modèles, pas de logique métier. Juste du CRUD réflexif sur un schéma existant.
- **Pas multi-SGBD** — SQLite uniquement, assumé comme choix de simplicité (un fichier, pas de serveur à administrer).
- **Pas destiné à remplacer un vrai outil métier complexe** — c'est un outil pour des besoins de gestion de données simples à moyens (collections, référentiels, commandes, calendriers…), pas pour du reporting analytique ou du BI.

---

## Fonctionnalités

### Cœur CRUD
- Découverte automatique des tables et vues au démarrage de chaque base
- Liste paginée (500 lignes par page) avec tri par colonne (clic sur l'en-tête) et recherche texte sur tous les champs simultanément
- Formulaires CRUD complets pour les tables : création, modification, suppression avec confirmation
- Vues en lecture seule (aucun bouton de modification affiché)
- Bouton **"Enregistrer et ajouter un autre"** : sauvegarde puis rouvre directement un formulaire vierge, pratique pour la saisie en série
- Messages d'erreur SQLite traduits en français compréhensible (contrainte unique violée, clé étrangère invalide, champ obligatoire manquant, etc.) plutôt que le message SQL brut

### Clés étrangères
- Détection automatique via `PRAGMA foreign_key_list` — aucune configuration requise pour qu'un champ FK devienne un widget de recherche
- Widget d'autocomplete AJAX (`lookup.php`) : tape pour chercher, clique pour sélectionner
- Libellé d'affichage personnalisable par SQL (`lookup_label`), y compris des expressions composées (concaténation, `CASE WHEN`…) pour des libellés riches (ex. `"CMD001 - 2026-01-10 (réf. externe)"`)
- Filtrage, tri et limitation du nombre de résultats proposés dans le widget, configurables par table (`lookup_where`, `lookup_order_by`, `lookup_limit`) — par exemple pour exclure les enregistrements archivés de la recherche sans les supprimer ni créer de vue dédiée
- La valeur déjà liée s'affiche toujours correctement même si l'enregistrement référencé ne correspond plus aux critères du filtre de recherche (ex. devenu archivé après coup) : le filtre ne s'applique qu'à la recherche, jamais à l'affichage d'un lien existant
- Intégrité référentielle garantie par SQLite (`PRAGMA foreign_keys=ON`) : impossible d'enregistrer un lien vers un identifiant inexistant

### Présentation
- Types de champs : `text`, `int`, `real`, `date`, `textarea`, `boolean`
- Colonnes masquables (`width: -1` dans le YAML) — utile pour les identifiants techniques ou les clés étrangères brutes
- Redimensionnement des colonnes à la souris ; largeur initiale définie par le YAML ou mesurée automatiquement selon le contenu le plus large, ajustements mémorisés dans `localStorage` du navigateur
- En-tête de tableau collant (sticky) lors du défilement vertical et horizontal
- Formatage des nombres décimaux avec séparateur de milliers, nombre de décimales configurable par champ
- Affichage "à blanc" des valeurs numériques nulles dans les listes (`format: blank_zero`), pour ne pas surcharger visuellement un tableau de colonnes de type "remise", "marge", etc. quand la valeur est zéro
- Cases à cocher pour les champs booléens (formulaire), coche visuelle centrée en liste

### Multi-bases et export
- Gestion multi-bases : plusieurs fichiers `.db` dans le dossier `data/`, sélecteur en haut de page, choix mémorisé en session
- Génération automatique d'un fichier `.yml` squelette si absent au premier accès à une base
- Export Excel des vues (`.xlsx`, via PhpSpreadsheet) — colonnes pilotées par le YAML, y compris les colonnes masquées à l'écran ; réservé aux vues (les tables restent éditables, pas exportables telles quelles)
- Export iCalendar optionnel (`creation_ics.php`) pour toute base contenant une table `evenements` au format attendu

### Technique
- Cache-busting automatique des assets CSS/JS (`?v=<version>`) : un changement de version force le rechargement, sans dépendre du cache navigateur
- Bouton de déconnexion optionnel : renommer `public/_exit.php` en `public/exit.php` pour afficher le lien « Exit », utile en cas d'authentification déléguée à un reverse proxy (SSO, Authelia, etc.)
- Seul le dossier `public/` est exposé au web ; bases, configuration et code restent à côté (voir « Sécurité »)

---

## Prérequis

- PHP 8.1 ou supérieur
- Extension PHP `pdo_sqlite` activée
- Extension PHP `yaml` activée (`php-yaml`) — sans elle, OscarDB fonctionne en mode dégradé (pas de lecture des fichiers `.yml`, présentation par défaut uniquement)
- Serveur web (Apache, Nginx, Caddy…) ou `php -S localhost:8080` pour un test local
- Pour l'export Excel : [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet), **fourni dans le dépôt** (`vendor/`) : rien à installer, Composer n'est pas nécessaire

---

## Installation

```bash
git clone https://github.com/Quintilien/oscardb.git
cd oscardb
```

Le dossier `vendor/` (PhpSpreadsheet) est versionné : le dépôt est complet tel quel, sans `composer install`.

Structure :

```
oscardb/
├── public/                 ← SEULE racine web : rien d'autre n'est servi
│   ├── assets/
│   │   ├── app.css
│   │   └── app.js
│   ├── index.php            ← accueil + sélecteur de base
│   ├── list.php             ← liste paginée (views et tables)
│   ├── form.php             ← formulaire CRUD
│   ├── lookup.php           ← API JSON pour l'autocomplete des clés étrangères
│   ├── creation_ics.php     ← export iCalendar (optionnel)
│   └── _exit.php            ← déconnexion (optionnel, à renommer en exit.php)
├── config/
│   ├── oscar.php.example   ← modèle de configuration (suivi par Git)
│   └── oscar.php           ← votre configuration (copie du modèle, ignorée par Git)
├── src/
│   ├── lib.php             ← fonctions PDO, sécurité (CSRF, session, en-têtes), logique partagée
│   └── export_excel.php    ← export .xlsx (optionnel)
├── data/
│   ├── MaBase.db           ← votre base SQLite
│   └── MaBase.yml          ← fichier de présentation (généré automatiquement si absent)
├── vendor/                 ← PhpSpreadsheet (si export Excel utilisé)
├── docs/                   ← illustration du README
├── Dockerfile
├── docker-compose.yml
├── LICENSE
├── README.md / README.fr.md
└── CHANGELOG.md / CHANGELOG.fr.md
```

**Règle unique, quel que soit l'hébergement : la racine web est `public/`.** Les dossiers `config/`, `data/`, `src/` et `vendor/` restent **à côté** de `public/`, au même niveau. OscarDB les cherche toujours à cet endroit (`../data`, `../src`…) : ce n'est pas une recommandation, c'est la seule disposition qui fonctionne.

---

## Déploiement

### 1. Développement local

```bash
php -S localhost:8080 -t public
```

Puis ouvrir `http://localhost:8080` dans le navigateur.

---

### 2. Hébergement mutualisé

OscarDB fonctionne sur les hébergements mutualisés **basiques**, y compris les offres gratuites (par exemple l'hébergement gratuit inclus avec un nom de domaine OVH, o2switch, Infomaniak…), même en accès **FTP seul, sans Composer ni SSH**.

**Conditions minimales :**
- PHP 8.1+
- Extension `pdo_sqlite` (présente par défaut chez la quasi-totalité des hébergeurs)
- Extension `yaml` — à vérifier selon l'hébergeur ; en son absence, OscarDB continue de fonctionner mais sans lecture des fichiers `.yml`

**Déploiement — deux dispositions possibles :**

*a) L'hébergeur permet de choisir le dossier racine du site* (OVH : « Multisite » → dossier racine ; cPanel/Plesk : « Document root ») :

1. Transférer tout le dossier `oscardb/` par FTP, par exemple dans `~/oscardb/`.
2. Indiquer `oscardb/public` comme dossier racine du domaine ou du sous-domaine.

*b) La racine web est imposée* (souvent `www/`) :

1. Transférer le **contenu** de `public/` dans `www/`.
2. Transférer `config/`, `data/`, `src/` et `vendor/` **à côté** de `www/`, pas dedans :

```
~/                 ← dossier de l'hébergement, non servi
├── www/           ← contenu de public/
├── config/
├── data/
├── src/
└── vendor/
```

**À ne pas faire :** déposer le dossier `oscardb/` complet *dans* `www/`. L'application fonctionnerait (à l'adresse `…/oscardb/public/`), mais les bases et la configuration seraient téléchargeables par n'importe qui. Le fichier `.htaccess` fourni à la racine du projet bloque ce cas sur Apache, et seulement sur Apache : c'est un filet de secours, pas une disposition.

**Puis, obligatoirement :** protéger le site par mot de passe (voir « Sécurité » ci-dessous).

> **Note :** SQLite stocke toute la base dans un seul fichier. Il n'y a rien à configurer côté serveur de base de données — pas de MySQL, pas de PostgreSQL, pas de mot de passe de connexion à gérer. Le dossier `data/` doit être accessible en écriture par PHP (création des `.yml` squelettes, écriture dans les bases).

> **Piège courant :** certains hébergeurs mutualisés activent l'OPcache PHP avec `opcache.validate_timestamps=0` ; un fichier modifié par FTP peut alors ne pas être pris en compte immédiatement. En cas de doute après un transfert, vérifier la configuration OPcache de l'hébergeur ou solliciter un redémarrage du service PHP.

---

### 3. Docker

OscarDB est livré avec un `Dockerfile` et un `docker-compose.yml` prêts à l'emploi.

#### Image

L'image (`Dockerfile`) embarque PHP 8.3, Apache et l'extension `yaml`, avec `public/` comme racine web (`APACHE_DOCUMENT_ROOT=/var/www/public`). Elle est **légère et autonome** : pas de service externe requis.

#### Lancement

```bash
docker compose up -d --build
```

#### `docker-compose.yml`

```yaml
services:
  oscar:
    build: .
    container_name: oscar
    restart: unless-stopped
    ports:
      - "127.0.0.1:8084:80"
    volumes:
      - ./config:/var/www/config
      - ./src:/var/www/src
      - ./public:/var/www/public
      - ./vendor:/var/www/vendor
      - ./data:/var/www/data
```

**Points importants :**

- La disposition dans le conteneur est la même que sur un mutualisé : `/var/www/public` est servi, le reste est à côté.
- Les dossiers sont des **volumes externes** montés dans le conteneur. L'image Docker est jetable — les fichiers PHP et les bases de données survivent à toute reconstruction de l'image.
- Le port `8084` est exposé uniquement sur `127.0.0.1` : l'application n'est pas directement accessible depuis l'extérieur. On la place derrière un reverse proxy (Caddy, Nginx, Traefik) qui gère le HTTPS **et l'authentification** (voir « Sécurité »).
- Des limites de ressources (1 Go de RAM, 1 vCPU) peuvent être ajoutées dans `deploy.resources.limits` ; c'est confortable pour quelques utilisateurs simultanés.

#### Mise à jour

```bash
git pull
docker compose up -d --build
```

Les données dans `./data/` ne sont pas affectées.

#### Piège courant : renommage de dossier

Si vous renommez ou remplacez un dossier monté (par exemple `./public`) sur l'hôte (par exemple en déployant une nouvelle version dans un dossier séparé puis en le renommant), Docker peut continuer à voir l'ancien contenu tant que le conteneur n'est pas redémarré — le bind-mount garde une référence à l'ancien point de montage. Dans ce cas :

```bash
docker compose down
docker compose up -d
```

Un simple `restart` ne suffit pas toujours ; un cycle complet `down` puis `up` force une relecture propre du volume.

---

## Configuration générale (`config/oscar.php`)

Copier `config/oscar.php.example` en `config/oscar.php`, puis modifier la copie. `oscar.php` est ignoré par Git : une mise à jour ne l'écrase jamais. Sans lui, OscarDB lit le modèle, ce qui permet un essai immédiat après le clonage.

```php
return [
    'app_title'        => 'OscarDB',
    'default_database' => 'Bouquins.db',     // nom d'un fichier de data/
    'timezone'         => 'Europe/Brussels', // fuseau des dates produites (noms des exports…)
    'debug'            => false,             // true : affiche les erreurs PHP
    'warn_unprotected' => true,              // bandeau si aucune authentification amont
];
```

Le numéro de version (forme `x.y.z`, voir `CHANGELOG.md`) est défini dans `src/lib.php`, pas dans la configuration. Affiché dans l'en-tête, il sert aussi de cache-buster pour les assets CSS/JS : il change à chaque version, ce qui garantit que les navigateurs rechargent les fichiers modifiés.

`debug` ne doit être activé que le temps d'un diagnostic : les messages d'erreur révèlent les chemins du serveur et les requêtes SQL.

---

## Selon votre serveur

La disposition (seul `public/` est servi) protège la configuration, le code et les bases **sur tout serveur web**, sans réglage particulier. Ce qui change d'un serveur à l'autre, c'est la façon de placer une authentification devant OscarDB (voir « Sécurité »).

| Serveur | Disposition | Authentification devant OscarDB |
|---|---|---|
| Apache (mutualisé ou non) | racine web = `public/`, ou contenu de `public/` dans `www/` | `.htaccess` et `.htpasswd`, ou protection de dossier du panneau de l'hébergeur |
| Mutualisé sans Apache (nginx, IIS…) | idem | protection de dossier du panneau de l'hébergeur (cPanel, Plesk : « Répertoires protégés ») ; s'il n'en propose pas, OscarDB n'y est **pas** utilisable en ligne |
| VPS avec Docker | `Dockerfile` et `docker-compose.yml` fournis | reverse proxy : forward-auth (Tinyauth, Authelia…) ou authentification Basic |
| VPS sans Docker (Apache, nginx ou Caddy avec PHP-FPM) | racine web = `public/` | forward-auth ou authentification Basic du serveur |
| Hébergement où tout fichier déposé est forcément dans la racine web | Apache seulement, grâce au filet de secours `.htaccess` ; **non pris en charge** avec un autre serveur | — |

## Sécurité

OscarDB **n'a pas d'authentification propre** : c'est un choix, pour rester simple et s'intégrer à ce qui existe déjà. Toute personne qui atteint l'adresse peut lire et modifier les données. Il faut donc **toujours** placer une authentification devant :

- **Hébergement mutualisé** : protéger le dossier racine par mot de passe depuis le panneau de l'hébergeur (OVH : fichier `.htaccess`/`.htpasswd` ; cPanel et Plesk : « Répertoires protégés » / « Password-protected directories »).
- **Serveur avec reverse proxy** : authentification déléguée (Tinyauth, Authelia, Authentik…), par exemple avec `forward_auth` dans Caddy.

**Bandeau d'avertissement.** Si OscarDB ne voit aucune authentification amont — ni utilisateur authentifié par le serveur (`REMOTE_USER`, authentification « Basic » de l'hébergeur), ni en-tête `Remote-User` transmis par le proxy —, il affiche un bandeau rouge en haut de chaque page. Avec Caddy et Tinyauth, faire transmettre l'en-tête :

```caddy
# adapter l'adresse de Tinyauth à votre installation
forward_auth 127.0.0.1:3000 {
    uri /api/auth/caddy
    copy_headers Remote-User
}
```

Si l'interface est protégée d'une façon que PHP ne peut pas voir (filtrage par adresse IP, VPN…), désactiver le bandeau avec `'warn_unprotected' => false`. Le bandeau n'est qu'un avertissement : il n'accorde ni ne refuse aucun accès. Un visiteur peut d'ailleurs envoyer lui-même un en-tête `Remote-User` pour le faire disparaître, si le proxy ne le remplace pas ; c'est sans conséquence, puisque la protection réelle est l'authentification placée devant OscarDB.

**Protections intégrées (2.0.0) :**

- seul `public/` est servi ; bases, configuration et code sont hors de la racine web ;
- jeton anti-CSRF sur tous les formulaires ;
- le paramètre de retour (`back`) n'accepte qu'une adresse locale à l'application ;
- erreurs PHP masquées par défaut ;
- cookie de session `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS ;
- en-têtes interdisant l'affichage de l'application dans un cadre (anti-clickjacking) ;
- noms de tables et de colonnes vérifiés contre le schéma, valeurs toujours liées (requêtes préparées) ;
- seuls les fichiers `.db` présents dans `data/` peuvent être ouverts.

Ces protections tiennent en quelques lignes standard au début de `src/lib.php` (en-têtes, session, jeton CSRF), selon les recommandations de l'OWASP ; l'en-tête `X-Powered-By`, qui annoncerait la version de PHP, est retiré.

Pour signaler un problème de sécurité en privé : bouton « Report a vulnerability » de GitHub (onglet Security du dépôt).

---

## Le fichier `.yml` de présentation

Chaque base `MaBase.db` peut être accompagnée d'un fichier `MaBase.yml` dans le même dossier `data/`.

Si ce fichier est absent, OscarDB en génère un squelette automatiquement au premier accès.

### Exemple complet

```yaml
views:
  Titres:
    fields:
      id:
        type: int
        width: -1          # colonne masquée
      Titre:
        type: text
        width: 350px
        label: "Titre du livre"
      Parution:
        type: int
        align: right
      Remise:
        type: real
        align: right
        decimals: 2
        format: blank_zero   # affichage à blanc si la valeur vaut 0

tables:
  t_titres:
    pk: id
    fields:
      id:
        type: int
        width: -1
      nom:
        type: text
        label: "Titre"
      parution:
        type: int
        width: 80px
        label: "Année de parution"
      note:
        type: textarea
        width: 500px
        height: 350px
      fk_collection:
        type: int
        label: "Collection"   # clé étrangère → autocomplete automatique

  order_header:
    pk: id
    # Libellé composé, utilisé à la fois dans la recherche et dans
    # l'affichage du lien existant (peut mélanger plusieurs colonnes)
    lookup_label: "\"ref\" || ' - ' || \"orderdate\" || CASE WHEN \"external_ref\" IS NOT NULL AND \"external_ref\" <> '' THEN ' (' || \"external_ref\" || ')' ELSE '' END"
    # Exclut les commandes archivées de la recherche (mais pas de l'affichage
    # d'un lien déjà existant vers une commande devenue archivée depuis)
    lookup_where: "\"arch\" IS NOT 1"
    # Tri des résultats proposés dans le widget de recherche
    lookup_order_by: "ref COLLATE NOCASE"
    # Nombre maximum de résultats proposés (défaut : 20)
    lookup_limit: 20
```

### Types de champs disponibles

| Type | Effet |
|---|---|
| `text` | Champ texte simple |
| `int` | Entier, aligné à droite |
| `real` | Décimal, formaté avec séparateur de milliers, nombre de décimales configurable (`decimals`) |
| `date` | Sélecteur de date HTML natif |
| `textarea` | Zone de texte multiligne, hauteur configurable |
| `boolean` | Case à cocher (stockée 0/1), coche centrée en liste |

### Options par champ

| Option | Description |
|---|---|
| `label` | Libellé affiché à la place du nom technique de la colonne |
| `width` | Largeur en pixels (`350px`) ou `-1` pour masquer entièrement le champ |
| `height` | Hauteur pour les champs `textarea` |
| `align` | `left` ou `right` |
| `decimals` | Nombre de décimales affichées pour un champ `real` (défaut : 2) |
| `format` | Comportement d'affichage spécial, **listes (views) uniquement**. Seule valeur actuellement supportée : `blank_zero` — affiche une cellule vide plutôt que `0` pour un champ numérique (`real` ou `int`) dont la valeur vaut zéro. Sans effet dans les formulaires (`form.php`), où la valeur reste affichée/éditable normalement. |

> `format` est volontairement une clé générique plutôt qu'un booléen dédié (ex. `hide_zero`), pour pouvoir accueillir d'autres valeurs à l'avenir (ex. `percent`, `currency`…) sans multiplier les clés au fil des versions.

### Options par table (clés étrangères)

Ces clés se placent au même niveau que `pk`, directement sous le nom de la table **cible** de la clé étrangère (pas sous la table qui contient le champ FK) :

| Option | Description |
|---|---|
| `lookup_label` | Expression SQL utilisée comme libellé d'affichage et de recherche (peut être une simple colonne ou une expression composée) |
| `lookup_where` | Condition SQL fixe appliquée à la recherche par autocomplete (ex. exclure les enregistrements archivés) |
| `lookup_order_by` | Expression de tri des résultats proposés (défaut : tri par `lookup_label`) |
| `lookup_limit` | Nombre maximal de résultats proposés (défaut : 20) |

> Ces trois dernières clés sont **optionnelles** : en leur absence, le comportement est celui d'origine (pas de filtre, tri alphabétique, 20 résultats maximum). Elles s'appliquent uniquement au widget de recherche — un lien déjà établi vers un enregistrement qui ne correspond plus au filtre continue de s'afficher normalement.

---

## Bases d'exemple

Le dossier `data/` contient trois bases d'exemple, chacune avec son fichier `.yml` :

- **`Bouquins.db`** (ouverte par défaut) : une collection de bandes dessinées. Une vue `Collections` et une vue `Titres`, deux tables `t_collections` et `t_titres` reliées par une clé étrangère. Le plus simple pour commencer.
- **`gesco.db`** : la gestion d'un petit atelier de pâtisserie. Composants achetés, articles vendus, recettes (nomenclatures), commandes, avec le prix de revient calculé par des vues.
- **`calendrier.db`** : un calendrier d'événements, exportable au format iCalendar (`creation_ics.php`).

Pour repartir de zéro, il suffit de déposer vos propres fichiers `.db` dans `data/` (et de supprimer ceux qui ne servent pas). Git les ignore : seules les trois bases d'exemple sont suivies.

---

## Migration depuis la version 2.0.0

La version 5.0.0 renomme `lib/` en `src/` (même disposition que CMS Lite) et sépare la configuration du dépôt. Les données ne changent pas.

*Installation par Git* (Docker ou non), depuis le dossier de l'installation :

```sh
cp config/oscar.php /tmp/oscar.php.sauve      # votre configuration, que Git va retirer
git checkout -- config/oscar.php               # annule vos modifications locales, sauvegardées ci-dessus
git pull --ff-only
cp /tmp/oscar.php.sauve config/oscar.php       # désormais ignoré par Git
# la ligne 'app_version' ne sert plus (elle peut rester) ; 'timezone' vaut Europe/Brussels par défaut
docker compose up -d --build                   # Docker seulement : le Dockerfile a changé
```

*Hébergement par FTP* : téléverser `src/` à côté de la racine web, supprimer l'ancien `lib/`, remplacer le contenu de `public/`, et garder votre `config/oscar.php` (le modèle s'appelle désormais `oscar.php.example`).

## Migration depuis la version 1.24

La version 2.0.0 change la disposition des fichiers, pas les données : les fichiers `.db` et `.yml` se réutilisent tels quels.

1. Déployer la version actuelle selon la section « Déploiement ».
2. Copier vos `.db` et `.yml` dans `data/`.
3. Reporter dans `config/oscar.php` le titre et la base par défaut (seul le **nom** du fichier compte désormais, par exemple `'ma_base.db'`).
4. Docker : `docker compose up -d --build` (le `Dockerfile` a changé).
5. Supprimer l'ancien dossier `app/` de la racine web.

Les versions 1.x ne sont plus prises en charge.

---

## Par rapport à Oscar (version précédente)

L'ancienne version d'Oscar (Python, Windows uniquement) stockait ses métadonnées de présentation **dans la base de données elle-même**, sous forme de tables internes `oscar_parameters`, `oscar_tables`, `oscar_attach`. Cela avait l'inconvénient de mélanger données et configuration dans un même fichier, et nécessitait un outil externe (DB Browser for SQLite) pour modifier ces paramètres.

OscarDB adopte une approche différente :

| | Oscar (Python) | OscarDB (PHP) |
|---|---|---|
| Environnement | Application Windows | Interface web |
| Métadonnées | Tables `oscar_*` dans le `.db` | Fichier `.yml` séparé |
| La base reste propre | Non | **Oui** |
| Multi-utilisateurs | Réseau local | Web, tout navigateur |
| Installation | `.exe` à télécharger | PHP sur un serveur |

---

## Licence

Copyright (C) 2026 PhS & Quintilien

Ce programme est un logiciel libre, distribué sous les termes de la **GNU Affero General Public License**, version 3 ou (à votre choix) toute version ultérieure (`AGPL-3.0-or-later`). Texte complet dans le fichier `LICENSE`. En résumé : vous pouvez l'utiliser, le modifier et le redistribuer, à condition que toute version modifiée reste sous la même licence et que son code source soit mis à la disposition de ses utilisateurs, y compris lorsqu'elle n'est utilisée qu'en ligne, sur un serveur.

Composant tiers : [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) et ses dépendances (`vendor/`), chacun sous sa propre licence (MIT pour PhpSpreadsheet).

Illustration (`docs/papy-fichier.jpg`) : « Papy », personnage créé par PhS & Quintilien ; image générée avec ChatGPT (OpenAI) à partir de descriptions écrites et d'images antérieures du personnage.
