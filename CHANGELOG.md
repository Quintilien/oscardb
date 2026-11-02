# OscarDB — changelog

*English · [Français](CHANGELOG.fr.md)*

Numbering follows the form `x.y.z` since version 2.0.0:
`x` changes when the installation must be adapted (layout, configuration),
`y` when a feature is added, `z` for a fix.

Renumbering in version 5.0.2: the program was called "OscarDB5", the 5
marking its fifth generation (after the Python versions). The name becomes
OscarDB and the 5 moves into the version number: OscarDB5 3.0.0 and 3.0.1
become 5.0.0 and 5.0.1. Earlier entries keep their original numbers.

## 5.0.2 — 2 November 2026

No change in behaviour: preparation for the publication of the code.

- `README.md` in English, `README.fr.md` in French; changelog in
  `CHANGELOG.md` / `CHANGELOG.fr.md`.
- Code comments in English; old blocks of commented-out code removed. The
  labels of the interface do not change (French).
- Database opened by default: `Bouquins.db` (the former `essai01.db` did not
  exist in the repository).
- "Bouquins" sample database: the long notes taken from Wikipedia are
  replaced by short notes.
- Licence: `LICENSE` file (AGPL text, recognised by GitHub); copyright
  "PhS & Quintilien".
- Name: OscarDB (formerly OscarDB5), as the repository. An existing
  installation keeps the title set in its `config/oscar.php`.
- `.gitignore`: only the three sample databases are tracked in `data/`;
  any other file there is ignored (your own databases are never committed).
- Public repository: `github.com/Quintilien/oscardb`.

Updating: `git pull` (nothing else, Docker included). An existing
installation keeps its configuration (`config/oscar.php`) and its databases.

## 5.0.1 — 1 November 2026

Fixes from a security review of all entry points, by an independent
reviewer. No installation change: `git pull` (Docker:
`docker compose up -d --build`).

- Return parameter (`back`): any space rejected, Unicode included (an
  address preceded by a space could lead to another site).
- Form: the table, the identifier and the return address come from the
  address only; a column named `table` or `back` can no longer override
  them.
- Address parameters: always strings (no more error 500 on `?x[]=…`);
  page number bounded.
- ICS export: UID cleaned; an invalid date skips the event instead of
  making the export fail; lines folded without splitting a character; file
  names sanitized; temporary archive with a unique name.
- Excel export: column headers always text (never a formula); file name
  sanitized.
- Generated `.yml` skeleton: keys and values between quotes.
- README: note on the `Remote-User` header and the banner.

## 5.0.0 — 1 November 2026

Layout aligned with CMS Lite. No new feature; `.db` databases and `.yml`
files are unchanged. Procedure: README, "Upgrading from version 2.0.0".

- **Layout**: `lib/` becomes `src/` (pds/skeleton convention, as in
  CMS Lite): `config/`, `data/`, `public/`, `src/`, `vendor/`.
- **Configuration**: the repository provides `config/oscar.php.example`;
  your settings go in `config/oscar.php`, ignored by Git, so never
  overwritten by an update. Without it, the template is read.
- **Version** defined in `src/lib.php` (`OSCAR_VERSION`), no longer in the
  configuration.
- `lib/socle.php` and `SOCLE.md` dropped (home-made mechanism): the same
  protections fit in a few standard lines at the top of `src/lib.php`.
  Behaviour unchanged.
- `X-Powered-By` header removed (it announced the PHP version).
- New `timezone` key (default `Europe/Brussels`): dates produced by OscarDB
  (Excel export names…) are no longer in UTC under Docker.
- README: "Depending on your server" section (Apache, shared hosting
  without Apache, VPS with or without Docker, unsupported case).

## 2.0.0 — 29 October 2026

Layout and security. No new feature; `.db` databases and `.yml` files are
unchanged.

- **Layout**: only `public/` is the web root. `config/`, `lib/`, `vendor/`
  and `data/` sit next to it, out of the browser's reach. Replaces the old
  `app/` folder (served entirely, `vendor/` and `config/` included).
- **CSRF**: token on every form (save, delete, database choice, ICS
  export); any POST without a valid token is rejected.
- **`back` parameter**: only accepts an address local to the application
  (previously: possible redirect to another site after a deletion, and a
  "Back" link accepting `javascript:`).
- **PHP errors** hidden by default (`'debug' => false` in
  `config/oscar.php`); previously `display_errors=1` hard-coded.
- **Session**: `oscardb` cookie, `HttpOnly`, `SameSite=Lax`, `Secure` over
  HTTPS.
- **Headers**: `X-Frame-Options`, `frame-ancestors`, `nosniff`,
  `Referrer-Policy`.
- **"Unprotected interface" banner** when no upstream authentication is
  visible (can be turned off with `'warn_unprotected' => false`).
- `default_database` now only holds a file name from `data/`.
- `.htaccess` and `index.php` at the project root: safety net for Apache if
  the whole project is put in the web root.
- Common mechanisms grouped in `lib/socle.php` (shared with CMS Lite and
  Quintilien).
- `vendor/` (PhpSpreadsheet) is committed: the repository installs as is,
  without Composer.
- Versions 1.x are no longer supported.

## 1.24 — 9 August 2026

- Three optional YAML keys for the foreign-key search widget:
  `lookup_where`, `lookup_order_by`, `lookup_limit`.

## 1.23 — 7 August 2026

- Fix for the selection menu (AJAX lookup): the list reopened when a field
  already filled got the focus again, or after a selection when typing
  fast.
- Excel export of views (PhpSpreadsheet).
- Cache busting of assets (`?v=<version>`).
