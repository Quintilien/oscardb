# OscarDB

*English · [Français](README.fr.md)*

Version 5.0.2 — 2 November 2026 — changelog: [CHANGELOG.md](CHANGELOG.md)

**Generic web CRUD interface for SQLite databases**

> OscarDB is a CRUD generated automatically from the existing SQLite schema, with a YAML layer for the presentation — not a database builder, just an interface for a database that already exists.

OscarDB is a lightweight web application, written in PHP, that automatically turns any SQLite `.db` file into a data management interface — paginated lists, input forms, search, foreign keys — **without writing any code**.

Similar in spirit to Django's auto-generated admin, but without a framework: just PHP + SQLite, no server dependency, no build step.

<p align="center"><img src="docs/papy-fichier.jpg" alt="Papy filing a card in his card index" width="480"></p>

OscarDB is part of a small family of self-hosted tools, with [CMS Lite](https://github.com/Quintilien/cms_lite) (the home page for your self-hosted tools).

The interface is in French.

---

## Philosophy: KISS

> *Keep It Simple, Stupid*

OscarDB is meant for users who want to **manage their data** without seeing "what happens under the hood". It rests on a simple idea:

- **One SQLite database = one `.db` file** → simple, portable, no database server
- **SQLite views** define the read-only lists
- **SQLite tables** get full CRUD access (create, read, update, delete)
- **A companion `.yml` file** handles the presentation (widths, labels, field types, hidden columns, behaviour of foreign-key searches)

The database stays **clean**: OscarDB never adds an internal table to it. All the presentation lives in a separate file, next to the `.db`.

### What OscarDB is **not**

- **Not a no-code tool / schema builder** — unlike Airtable, NocoDB or Baserow, OscarDB does not create the structure of the database for you: it displays it. The SQLite database must already exist (tables, views, foreign keys).
- **Not an ORM or an application framework** — no migrations, no models, no business logic. Just reflective CRUD on an existing schema.
- **Not multi-DBMS** — SQLite only, a deliberate choice for simplicity (one file, no server to administer).
- **Not meant to replace a complex business tool** — it is a tool for simple to medium data management needs (collections, reference lists, orders, calendars…), not for analytical reporting or BI.

---

## Features

### CRUD core
- Automatic discovery of tables and views when a database is opened
- Paginated list (500 rows per page) with sorting by column (click on the header) and text search across all fields at once
- Full CRUD forms for tables: create, update, delete with confirmation
- Read-only views (no edit button shown)
- **"Save and add another"** button: saves, then directly opens a blank form, handy for entering a series of records
- SQLite error messages translated into understandable French (unique constraint violated, invalid foreign key, missing required field, etc.) rather than the raw SQL message

### Foreign keys
- Automatic detection through `PRAGMA foreign_key_list` — no configuration needed for a foreign-key field to become a search widget
- AJAX autocomplete widget (`lookup.php`): type to search, click to select
- Display label customisable in SQL (`lookup_label`), including compound expressions (concatenation, `CASE WHEN`…) for rich labels (e.g. `"CMD001 - 2026-01-10 (ext. ref.)"`)
- Filtering, sorting and number of results offered in the widget, configurable per table (`lookup_where`, `lookup_order_by`, `lookup_limit`) — for example to exclude archived records from the search without deleting them or creating a dedicated view
- An existing link always displays correctly, even if the referenced record no longer matches the search filter (e.g. archived afterwards): the filter only applies to the search, never to the display of an existing link
- Referential integrity enforced by SQLite (`PRAGMA foreign_keys=ON`): a link to a non-existent identifier cannot be saved

### Presentation
- Field types: `text`, `int`, `real`, `date`, `textarea`, `boolean`
- Columns can be hidden (`width: -1` in the YAML) — useful for technical identifiers or raw foreign keys
- Columns resizable with the mouse; initial width set in the YAML or measured automatically from the widest content, adjustments remembered in the browser's `localStorage`
- Sticky table header when scrolling vertically and horizontally
- Decimal numbers formatted with a thousands separator, number of decimals configurable per field
- Zero numeric values shown "blank" in lists (`format: blank_zero`), so as not to clutter a table with "discount", "margin" columns, etc. when the value is zero
- Checkboxes for boolean fields (form), centred tick in lists

### Several databases and export
- Several databases: several `.db` files in the `data/` folder, picker at the top of the page, choice remembered in the session
- Skeleton `.yml` file generated automatically the first time a database without one is opened
- Excel export of views (`.xlsx`, through PhpSpreadsheet) — columns driven by the YAML, including columns hidden on screen; views only (tables stay editable, not exportable as such)
- Optional iCalendar export (`creation_ics.php`) for any database containing an `evenements` table in the expected format

### Technical
- Automatic cache busting of CSS/JS assets (`?v=<version>`): a version change forces a reload, without relying on the browser cache
- Optional logout button: rename `public/_exit.php` to `public/exit.php` to show the "Exit" link, useful when authentication is delegated to a reverse proxy (SSO, Authelia, etc.)
- Only the `public/` folder is exposed to the web; databases, configuration and code stay next to it (see "Security")

---

## Requirements

- PHP 8.1 or later
- PHP extension `pdo_sqlite` enabled
- PHP extension `yaml` enabled (`php-yaml`) — without it, OscarDB works in degraded mode (`.yml` files not read, default presentation only)
- A web server (Apache, Nginx, Caddy…) or `php -S localhost:8080` for a local test
- For the Excel export: [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet), **included in the repository** (`vendor/`): nothing to install, Composer is not needed

---

## Installation

```bash
git clone https://github.com/Quintilien/oscardb.git
cd oscardb
```

The `vendor/` folder (PhpSpreadsheet) is committed: the repository is complete as is, without `composer install`.

Structure:

```
oscardb/
├── public/                 ← the ONLY web root: nothing else is served
│   ├── assets/
│   │   ├── app.css
│   │   └── app.js
│   ├── index.php            ← home page + database picker
│   ├── list.php             ← paginated list (views and tables)
│   ├── form.php             ← CRUD form
│   ├── lookup.php           ← JSON API for the foreign-key autocomplete
│   ├── creation_ics.php     ← iCalendar export (optional)
│   └── _exit.php            ← logout (optional, rename to exit.php)
├── config/
│   ├── oscar.php.example   ← configuration template (tracked by Git)
│   └── oscar.php           ← your configuration (copy of the template, ignored by Git)
├── src/
│   ├── lib.php             ← PDO functions, security (CSRF, session, headers), shared logic
│   └── export_excel.php    ← .xlsx export (optional)
├── data/
│   ├── MyBase.db           ← your SQLite database
│   └── MyBase.yml          ← presentation file (generated automatically if missing)
├── vendor/                 ← PhpSpreadsheet (for the Excel export)
├── docs/                   ← README illustration
├── Dockerfile
├── docker-compose.yml
├── LICENSE
├── README.md / README.fr.md
└── CHANGELOG.md / CHANGELOG.fr.md
```

**One rule, whatever the hosting: the web root is `public/`.** The `config/`, `data/`, `src/` and `vendor/` folders stay **next to** `public/`, at the same level. OscarDB always looks for them there (`../data`, `../src`…): this is not a recommendation, it is the only layout that works.

---

## Deployment

### 1. Local development

```bash
php -S localhost:8080 -t public
```

Then open `http://localhost:8080` in the browser.

---

### 2. Shared hosting

OscarDB works on **basic** shared hosting, including free offers (for example the free hosting included with an OVH domain name, o2switch, Infomaniak…), even with **FTP access only, without Composer or SSH**.

**Minimum conditions:**
- PHP 8.1+
- `pdo_sqlite` extension (present by default at almost every host)
- `yaml` extension — to be checked with the host; without it, OscarDB keeps working but without reading the `.yml` files

**Deployment — two possible layouts:**

*a) The host lets you choose the site's root folder* (OVH: "Multisite" → root folder; cPanel/Plesk: "Document root"):

1. Upload the whole `oscardb/` folder by FTP, for example to `~/oscardb/`.
2. Set `oscardb/public` as the root folder of the domain or subdomain.

*b) The web root is imposed* (often `www/`):

1. Upload the **content** of `public/` into `www/`.
2. Upload `config/`, `data/`, `src/` and `vendor/` **next to** `www/`, not inside it:

```
~/                 ← hosting folder, not served
├── www/           ← content of public/
├── config/
├── data/
├── src/
└── vendor/
```

**Do not:** put the complete `oscardb/` folder *inside* `www/`. The application would work (at `…/oscardb/public/`), but the databases and the configuration could be downloaded by anyone. The `.htaccess` file provided at the project root blocks this case on Apache, and only on Apache: it is a safety net, not a layout.

**Then, always:** protect the site with a password (see "Security" below).

> **Note:** SQLite stores the whole database in a single file. There is nothing to configure on the database server side — no MySQL, no PostgreSQL, no connection password to manage. The `data/` folder must be writable by PHP (creation of the skeleton `.yml` files, writes to the databases).

> **Common pitfall:** some shared hosts enable PHP's OPcache with `opcache.validate_timestamps=0`; a file changed by FTP may then not be taken into account immediately. When in doubt after an upload, check the host's OPcache configuration or ask for a restart of the PHP service.

---

### 3. Docker

OscarDB comes with a ready-to-use `Dockerfile` and `docker-compose.yml`.

#### Image

The image (`Dockerfile`) contains PHP 8.3, Apache and the `yaml` extension, with `public/` as the web root (`APACHE_DOCUMENT_ROOT=/var/www/public`). It is **light and self-contained**: no external service required.

#### Starting

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

**Important points:**

- The layout inside the container is the same as on shared hosting: `/var/www/public` is served, the rest sits next to it.
- The folders are **external volumes** mounted in the container. The Docker image is disposable — the PHP files and the databases survive any rebuild of the image.
- Port `8084` is only exposed on `127.0.0.1`: the application cannot be reached directly from outside. It goes behind a reverse proxy (Caddy, Nginx, Traefik) that handles HTTPS **and authentication** (see "Security").
- Resource limits (1 GB of RAM, 1 vCPU) can be added in `deploy.resources.limits`; that is comfortable for a few simultaneous users.

#### Updating

```bash
git pull
docker compose up -d --build
```

The data in `./data/` is not affected.

#### Common pitfall: renaming a folder

If you rename or replace a mounted folder (for example `./public`) on the host (for example by deploying a new version into a separate folder and then renaming it), Docker may keep seeing the old content until the container is restarted — the bind mount keeps a reference to the old mount point. In that case:

```bash
docker compose down
docker compose up -d
```

A simple `restart` is not always enough; a full `down` then `up` cycle forces a clean re-read of the volume.

---

## General configuration (`config/oscar.php`)

Copy `config/oscar.php.example` to `config/oscar.php`, then edit the copy. `oscar.php` is ignored by Git: an update never overwrites it. Without it, OscarDB reads the template, which allows an immediate test after cloning.

```php
return [
    'app_title'        => 'OscarDB',
    'default_database' => 'Bouquins.db',     // name of a file in data/
    'timezone'         => 'Europe/Brussels', // time zone of the dates produced (export names…)
    'debug'            => false,             // true: shows PHP errors
    'warn_unprotected' => true,              // banner if no upstream authentication
];
```

The version number (form `x.y.z`, see `CHANGELOG.md`) is defined in `src/lib.php`, not in the configuration. Shown in the header, it also serves as a cache buster for the CSS/JS assets: it changes with every version, which guarantees that browsers reload the modified files.

`debug` should only be turned on for the time of a diagnosis: error messages reveal the server's paths and the SQL queries.

---

## Depending on your server

The layout (only `public/` is served) protects the configuration, the code and the databases **on any web server**, without any particular setting. What changes from one server to another is the way to put an authentication in front of OscarDB (see "Security").

| Server | Layout | Authentication in front of OscarDB |
|---|---|---|
| Apache (shared or not) | web root = `public/`, or content of `public/` in `www/` | `.htaccess` and `.htpasswd`, or folder protection from the host's control panel |
| Shared hosting without Apache (nginx, IIS…) | same | folder protection from the host's control panel (cPanel, Plesk: "Password-protected directories"); if it offers none, OscarDB **cannot** be used online there |
| VPS with Docker | `Dockerfile` and `docker-compose.yml` provided | reverse proxy: forward-auth (Tinyauth, Authelia…) or Basic authentication |
| VPS without Docker (Apache, nginx or Caddy with PHP-FPM) | web root = `public/` | forward-auth or the server's Basic authentication |
| Hosting where every uploaded file necessarily lands in the web root | Apache only, thanks to the `.htaccess` safety net; **not supported** with any other server | — |

## Security

OscarDB **has no authentication of its own**: this is a choice, to stay simple and fit in with what already exists. Anyone who reaches the address can read and change the data. So **always** put an authentication in front of it:

- **Shared hosting**: protect the root folder with a password from the host's control panel (OVH: `.htaccess`/`.htpasswd` file; cPanel and Plesk: "Password-protected directories").
- **Server with a reverse proxy**: delegated authentication (Tinyauth, Authelia, Authentik…), for example with `forward_auth` in Caddy.

**Warning banner.** If OscarDB sees no upstream authentication — neither a user authenticated by the server (`REMOTE_USER`, the host's "Basic" authentication) nor a `Remote-User` header passed on by the proxy — it shows a red banner at the top of every page. With Caddy and Tinyauth, have the header passed on:

```caddy
# adapt the Tinyauth address to your installation
forward_auth 127.0.0.1:3000 {
    uri /api/auth/caddy
    copy_headers Remote-User
}
```

If the interface is protected in a way PHP cannot see (IP filtering, VPN…), turn the banner off with `'warn_unprotected' => false`. The banner is only a warning: it neither grants nor refuses any access. A visitor could also send a `Remote-User` header themselves to make it disappear, if the proxy does not replace it; that has no consequence, since the real protection is the authentication placed in front of OscarDB.

**Built-in protections (2.0.0):**

- only `public/` is served; databases, configuration and code are outside the web root;
- anti-CSRF token on every form;
- the return parameter (`back`) only accepts an address local to the application;
- PHP errors hidden by default;
- session cookie `HttpOnly`, `SameSite=Lax`, `Secure` over HTTPS;
- headers forbidding the display of the application in a frame (anti-clickjacking);
- table and column names checked against the schema, values always bound (prepared statements);
- only `.db` files present in `data/` can be opened.

These protections fit in a few standard lines at the top of `src/lib.php` (headers, session, CSRF token), following the OWASP recommendations; the `X-Powered-By` header, which would announce the PHP version, is removed.

To report a security problem privately, use GitHub's "Report a vulnerability" button (Security tab of the repository).

---

## The `.yml` presentation file

Each `MyBase.db` database can come with a `MyBase.yml` file in the same `data/` folder.

If this file is missing, OscarDB generates a skeleton automatically the first time the database is opened.

### Complete example

```yaml
views:
  Titres:
    fields:
      id:
        type: int
        width: -1          # hidden column
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
        format: blank_zero   # shown blank when the value is 0

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
        label: "Collection"   # foreign key → automatic autocomplete

  order_header:
    pk: id
    # Compound label, used both in the search and in the display
    # of an existing link (can mix several columns)
    lookup_label: "\"ref\" || ' - ' || \"orderdate\" || CASE WHEN \"external_ref\" IS NOT NULL AND \"external_ref\" <> '' THEN ' (' || \"external_ref\" || ')' ELSE '' END"
    # Excludes archived orders from the search (but not from the display
    # of an existing link to an order archived since)
    lookup_where: "\"arch\" IS NOT 1"
    # Sort order of the results offered by the search widget
    lookup_order_by: "ref COLLATE NOCASE"
    # Maximum number of results offered (default: 20)
    lookup_limit: 20
```

### Available field types

| Type | Effect |
|---|---|
| `text` | Simple text field |
| `int` | Integer, right-aligned |
| `real` | Decimal, formatted with a thousands separator, number of decimals configurable (`decimals`) |
| `date` | Native HTML date picker |
| `textarea` | Multi-line text area, configurable height |
| `boolean` | Checkbox (stored as 0/1), centred tick in lists |

### Field options

| Option | Description |
|---|---|
| `label` | Label shown instead of the technical column name |
| `width` | Width in pixels (`350px`) or `-1` to hide the field completely |
| `height` | Height for `textarea` fields |
| `align` | `left` or `right` |
| `decimals` | Number of decimals shown for a `real` field (default: 2) |
| `format` | Special display behaviour, **lists (views) only**. Only value currently supported: `blank_zero` — shows an empty cell rather than `0` for a numeric field (`real` or `int`) whose value is zero. No effect in forms (`form.php`), where the value is shown and editable as usual. |

> `format` is deliberately a generic key rather than a dedicated boolean (e.g. `hide_zero`), so that it can take other values in the future (e.g. `percent`, `currency`…) without adding keys version after version.

### Table options (foreign keys)

These keys go at the same level as `pk`, directly under the name of the **target** table of the foreign key (not under the table containing the foreign-key field):

| Option | Description |
|---|---|
| `lookup_label` | SQL expression used as the display and search label (a simple column or a compound expression) |
| `lookup_where` | Fixed SQL condition applied to the autocomplete search (e.g. exclude archived records) |
| `lookup_order_by` | Sort expression for the results offered (default: sorted by `lookup_label`) |
| `lookup_limit` | Maximum number of results offered (default: 20) |

> The last three keys are **optional**: without them, the behaviour is the original one (no filter, alphabetical order, 20 results at most). They only apply to the search widget — an existing link to a record that no longer matches the filter keeps displaying normally.

---

## Sample databases

The `data/` folder contains three sample databases, each with its `.yml` file:

- **`Bouquins.db`** (opened by default): a comic book collection. A `Collections` view and a `Titres` view, two tables `t_collections` and `t_titres` linked by a foreign key. The simplest one to start with.
- **`gesco.db`**: the management of a small pastry workshop. Purchased components, items sold, recipes (bills of materials), orders, with the cost price computed by views.
- **`calendrier.db`**: a calendar of events, exportable in iCalendar format (`creation_ics.php`).

To start from scratch, just put your own `.db` files in `data/` (and delete the ones you do not need). Git ignores them: only the three sample databases are tracked.

---

## Upgrading from version 2.0.0

Version 5.0.0 renames `lib/` to `src/` (same layout as CMS Lite) and separates the configuration from the repository. The data does not change.

*Git installation* (Docker or not), from the installation folder:

```sh
cp config/oscar.php /tmp/oscar.php.saved      # your configuration, which Git is about to remove
git checkout -- config/oscar.php               # discards your local changes, saved above
git pull --ff-only
cp /tmp/oscar.php.saved config/oscar.php       # now ignored by Git
# the 'app_version' line is no longer used (it may stay); 'timezone' defaults to Europe/Brussels
docker compose up -d --build                   # Docker only: the Dockerfile has changed
```

*FTP hosting*: upload `src/` next to the web root, delete the old `lib/`, replace the content of `public/`, and keep your `config/oscar.php` (the template is now called `oscar.php.example`).

## Upgrading from version 1.24

Version 2.0.0 changes the file layout, not the data: `.db` and `.yml` files are reused as they are.

1. Deploy the current version following the "Deployment" section.
2. Copy your `.db` and `.yml` files into `data/`.
3. Carry the title and the default database over into `config/oscar.php` (only the file **name** counts now, for example `'my_base.db'`).
4. Docker: `docker compose up -d --build` (the `Dockerfile` has changed).
5. Delete the old `app/` folder from the web root.

Versions 1.x are no longer supported.

---

## Compared with Oscar (previous version)

The old version of Oscar (Python, Windows only) stored its presentation metadata **inside the database itself**, as internal tables `oscar_parameters`, `oscar_tables`, `oscar_attach`. This mixed data and configuration in the same file, and required an external tool (DB Browser for SQLite) to change these settings.

OscarDB takes a different approach:

| | Oscar (Python) | OscarDB (PHP) |
|---|---|---|
| Environment | Windows application | Web interface |
| Metadata | `oscar_*` tables in the `.db` | Separate `.yml` file |
| The database stays clean | No | **Yes** |
| Multiple users | Local network | Web, any browser |
| Installation | `.exe` to download | PHP on a server |

---

## Licence

Copyright (C) 2026 PhS & Quintilien

This program is free software, distributed under the terms of the **GNU Affero General Public License**, version 3 or (at your option) any later version (`AGPL-3.0-or-later`). Full text in the `LICENSE` file. In short: you may use, modify and redistribute it, provided that any modified version stays under the same licence and that its source code is made available to its users, including when it is only used online, on a server.

Third-party component: [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) and its dependencies (`vendor/`), each under its own licence (MIT for PhpSpreadsheet).

Illustration (`docs/papy-fichier.jpg`): "Papy", a character created by PhS & Quintilien; image generated with ChatGPT (OpenAI) from text descriptions and earlier images of the character.
