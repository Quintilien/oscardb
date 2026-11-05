<?php
/*
 * OscarDB
 * Copyright (C) 2026 PhS & Quintilien
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

// Layout (5.0.0, as in CMS Lite): only public/ is served;
//   config/  data/  src/  vendor/  sit next to it, at the same level.
const OSCAR_ROOT = __DIR__ . '/..';
const OSCAR_VERSION = '5.0.4';

// Configuration: config/oscar.php (specific to the installation, ignored by Git),
// otherwise config/oscar.php.example (the repository works as soon as it is cloned).
function cfg(): array {
    static $c = null;
    if ($c === null) {
        $f = OSCAR_ROOT . '/config/oscar.php';
        $c = require (is_file($f) ? $f : $f . '.example');
    }
    return $c;
}

// --- Request settings (standard lines, see OWASP) --------------------------
// Errors hidden unless 'debug' => true; always logged.
$__debug = (bool)(cfg()['debug'] ?? false);
ini_set('display_errors', $__debug ? '1' : '0');
ini_set('display_startup_errors', $__debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
unset($__debug);

// Time zone of the dates OscarDB produces (export names, etc.).
date_default_timezone_set((string)(cfg()['timezone'] ?? 'Europe/Brussels'));

if (!headers_sent()) {
    header_remove('X-Powered-By');           // do not announce the PHP version
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: frame-ancestors 'none'");
    header('Referrer-Policy: same-origin');
}

// Session: HttpOnly cookie, SameSite=Lax, Secure over HTTPS.
if (session_status() === PHP_SESSION_NONE) {
    session_name('oscardb');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
csrf_check();

/** True if the request arrives over HTTPS, including behind a reverse proxy. */
function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** URL parameter (5.0.1): always a string, '' if missing or if it is an array. */
function qs(string $key, string $default = ''): string {
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? $v : $default;
}

/** HTML escaping (text and attributes). */
function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// --- CSRF (synchronizer token, OWASP CSRF Prevention Cheat Sheet) ----------

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Hidden field to put in every POST form. */
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

/** Rejects any POST without a valid token (403). GET requests change nothing. */
function csrf_check(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $sent = (string)($_POST['_csrf'] ?? '');
    $good = (string)($_SESSION['csrf'] ?? '');
    if ($good === '' || !hash_equals($good, $sent)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Requête refusée : jeton de formulaire absent ou expiré. Rechargez la page et recommencez.\n");
    }
}

/**
 * Returns $url if it is an address local to the application, otherwise $default.
 * Rejected: any scheme (http:, javascript:, data:…), "//host", backslashes,
 * control characters and (5.0.1) any space, Unicode included: the browser strips
 * a leading space, and "//host" reappeared.
 */
function safe_local_url(?string $url, string $default = 'index.php'): string {
    $url = (string)$url;
    if ($url === '' || strlen($url) > 2000) return $default;
    if (preg_match('/[\x00-\x20\x7F\\\\]|\p{Z}/u', $url) !== 0) return $default;   // 0 = none; false = invalid UTF-8
    if (str_starts_with($url, '//')) return $default;
    $head = strpbrk($url, '/?#');
    $scheme_zone = $head === false ? $url : substr($url, 0, strlen($url) - strlen($head));
    if (str_contains($scheme_zone, ':')) return $default;
    return $url;
}

/**
 * User authenticated upstream (host's Basic authentication, Tinyauth…), or null.
 * Only used to WARN that protection is missing, never to grant a right
 * (a Remote-User header can be forged if the proxy does not replace it).
 */
function upstream_user(): ?string {
    foreach (['REMOTE_USER', 'PHP_AUTH_USER', 'REDIRECT_REMOTE_USER', 'HTTP_REMOTE_USER'] as $k) {
        if (!empty($_SERVER[$k])) return (string)$_SERVER[$k];
    }
    return null;
}

function qident(string $name): string {
    return '"'.str_replace('"','""',$name).'"';
}

function data_dir(): string {
    $dir = OSCAR_ROOT . '/data';
    return realpath($dir) ?: $dir;
}

function db_files(): array {
    $files = glob(data_dir().'/*.db') ?: [];
    $out = [];
    foreach ($files as $f) {
        if (is_file($f)) $out[] = basename($f);
    }
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

function selected_db_name(): string {
    $files = db_files();
    $default = cfg()['default_database'] ?? cfg()['database'] ?? 'Bouquins.db';
    $default = basename((string)$default);
    $current = $_SESSION['oscar_db'] ?? $default;
    if (!in_array($current, $files, true)) {
        $current = $files[0] ?? $default;
        $_SESSION['oscar_db'] = $current;
    }
    return $current;
}

function selected_db_path(): string {
    $name = selected_db_name();
    $path = data_dir() . '/' . $name;
    $real = realpath($path);
    $base = data_dir();
    if ($real === false || !str_starts_with($real, $base) || !is_file($real)) {
        throw new RuntimeException('Base SQLite introuvable.');
    }
    return $real;
}

function set_selected_db(string $name): void {
    $name = basename($name);
    if (!in_array($name, db_files(), true)) {
        throw new RuntimeException('Base SQLite inconnue.');
    }
    $_SESSION['oscar_db'] = $name;
}

function db(): PDO {
    static $pdo=null, $dbName=null;
    $current = selected_db_name();
    if ($pdo && $dbName === $current) return $pdo;
    $pdo = new PDO('sqlite:' . selected_db_path());
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys=ON');
    $dbName = $current;
    return $pdo;
}

function get_views(): array {
    return db()->query("SELECT name, sql FROM sqlite_master WHERE type='view' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

function get_tables(): array {
    return db()->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

function object_exists(string $type, string $name): bool {
    $st = db()->prepare("SELECT 1 FROM sqlite_master WHERE type=? AND name=? AND name NOT LIKE 'sqlite_%'");
    $st->execute([$type, $name]);
    return (bool)$st->fetchColumn();
}

function table_info(string $table): array {
    $st=db()->query('PRAGMA table_info('.qident($table).')');
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function primary_key(string $table): ?string {
    foreach(table_info($table) as $c){ if((int)$c['pk']===1) return $c['name']; }
    return null;
}

function foreign_keys(string $table): array {
    $st=db()->query('PRAGMA foreign_key_list('.qident($table).')');
    $out=[];
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $fk){ $out[$fk['from']]=$fk; }
    return $out;
}

function view_sql(string $view): ?string {
    $st=db()->prepare("SELECT sql FROM sqlite_master WHERE type='view' AND name=?");
    $st->execute([$view]);
    $r=$st->fetchColumn();
    return $r!==false ? (string)$r : null;
}

function infer_main_table_from_view_sql(string $sql): ?string {
    if (preg_match('/\bfrom\s+(?:"([^"]+)"|`([^`]+)`|\[([^\]]+)\]|([a-zA-Z_][\w]*))/i', $sql, $m)) {
        return $m[1] ?: ($m[2] ?: ($m[3] ?: $m[4]));
    }
    return null;
}

function view_columns(string $view): array {
    $st=db()->query('PRAGMA table_info('.qident($view).')');
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function infer_key_column_in_view(string $view, string $table): ?string {
    $pk=primary_key($table); if(!$pk) return null;
    $sql=view_sql($view) ?? '';
    $cols=view_columns($view); $names=array_column($cols,'name');
    if(in_array($pk,$names,true)) return $pk;
    $patterns=[
        '/\b'.preg_quote($table,'/').'\s*\.\s*'.preg_quote($pk,'/').'\s+as\s+"([^"]+)"/i',
        '/"'.preg_quote($table,'/').'"\s*\.\s*"'.preg_quote($pk,'/').'"\s+as\s+"([^"]+)"/i',
        '/\b'.preg_quote($pk,'/').'\s+as\s+"([^"]+)"/i',
    ];
    foreach($patterns as $p){ if(preg_match($p,$sql,$m) && in_array($m[1],$names,true)) return $m[1]; }
    // No guess such as "first column whose name contains 'id'" (removed in 1.22):
    // it could pick a foreign key (e.g. customer_id) and open the wrong record.
    // Better no edit link from the view than a link to the wrong record.
    return null;
}

function infer_view_meta(string $view): array {
    $sql=view_sql($view) ?? '';
    $table=infer_main_table_from_view_sql($sql);
    if ($table && !object_exists('table', $table)) $table = null;
    return ['view'=>$view,'sql'=>$sql,'table'=>$table,'pk'=>$table?primary_key($table):null,'key_col'=>$table?infer_key_column_in_view($view,$table):null];
}



function label_for_table(string $table): string {

    static $yamlData = null;

    if($yamlData === null) {

        $yamlFile =
            data_dir() . '/' .
            pathinfo(selected_db_name(), PATHINFO_FILENAME) .
            '.yml';

        if(file_exists($yamlFile) && function_exists('yaml_parse_file')) {

            try {
                $yamlData = yaml_parse_file($yamlFile);
            } catch(Throwable $e) {
                $yamlData = [];
            }

        } else {
            $yamlData = [];
        }
    }

    // --- lookup_label defined in the YAML ---

    if(
        isset($yamlData['tables'][$table]['lookup_label']) &&
        trim((string)$yamlData['tables'][$table]['lookup_label']) !== ''
    ) {
        return (string)$yamlData['tables'][$table]['lookup_label'];
    }

    // --- automatic fallback ---

    $cols = table_info($table);

    $preferred = [
        'nom',
        'name',
        'libelle',
        'label',
        'title',
        'description'
    ];

    foreach($preferred as $p) {
        foreach($cols as $c) {

            if(strtolower($c['name']) === $p) {
                return qident($c['name']);
            }
        }
    }

    foreach($cols as $c) {

        if(
            (int)$c['pk'] !== 1 &&
            stripos((string)$c['type'], 'TEXT') !== false
        ) {
            return qident($c['name']);
        }
    }

    return qident(
        primary_key($table)
        ?? ($cols[0]['name'] ?? 'rowid')
    );
}








// Optional YAML keys (1.24), read at the same level as "pk" / "lookup_label"
// in tables.<table_name>: lookup_where, lookup_order_by, lookup_limit. They
// filter, sort and limit the list offered by the foreign-key search widget
// (lookup.php) without duplicating the table in a separate view.
function lookup_table_config(string $table): array {

    static $yamlData = null;

    if($yamlData === null) {

        $yamlFile =
            data_dir() . '/' .
            pathinfo(selected_db_name(), PATHINFO_FILENAME) .
            '.yml';

        if(file_exists($yamlFile) && function_exists('yaml_parse_file')) {

            try {
                $yamlData = yaml_parse_file($yamlFile);
            } catch(Throwable $e) {
                $yamlData = [];
            }

        } else {
            $yamlData = [];
        }
    }

    return $yamlData['tables'][$table] ?? [];
}

function lookup_where_for_table(string $table): ?string {
    $w = lookup_table_config($table)['lookup_where'] ?? null;
    return (is_string($w) && trim($w) !== '') ? trim($w) : null;
}

function lookup_order_by_for_table(string $table): ?string {
    $o = lookup_table_config($table)['lookup_order_by'] ?? null;
    return (is_string($o) && trim($o) !== '') ? trim($o) : null;
}

function lookup_limit_for_table(string $table): int {
    $l = lookup_table_config($table)['lookup_limit'] ?? null;
    if(is_numeric($l) && (int)$l > 0) return (int)$l;
    return 20; // default value: unchanged behaviour (before 1.24)
}


function column_kind(array $col): string {
    $type=strtoupper((string)($col['type'] ?? ''));
    $name=strtolower((string)$col['name']);
    if(str_contains($name,'date')) return 'date';
    if(str_contains($type,'REAL')||str_contains($type,'FLOA')||str_contains($type,'DOUB')||str_contains($type,'NUM')||str_contains($type,'DEC')) return 'real';
    if(str_contains($type,'INT')) return 'int';
    return 'text';
}


// Number of decimals read from the YAML ("decimals" key of the field; 2 if
// absent, null or invalid). Optional "format" key (1.25), only value so far:
// "blank_zero": a numeric field (real or int) whose value is zero is shown as
// an empty cell in lists rather than "0" (lists only, not in forms).
// "format" rather than a boolean, to allow other values later (percent,
// currency…). list.php injects 'type', 'decimals' and 'format' into $col.
function format_value($v, array $col): string {
    if($v===null) return '';

    $kind = column_kind($col);

    if(
        ($col['format'] ?? null) === 'blank_zero' &&
        ($kind === 'real' || $kind === 'int') &&
        is_numeric($v) &&
        (float)$v === 0.0
    ) {
        return '';
    }

    if($kind !== 'real') return (string)$v;

    $decimals = $col['decimals'] ?? 2;
    if($decimals === null || $decimals === '' || !is_numeric($decimals)) {
        $decimals = 2;
    }
    $decimals = max(0, (int)$decimals);

    return number_format((float)$v, $decimals, ',', ' ');
}



function render_header(string $title=''): void {
    $app = h(cfg()['app_title']);
    $version = h(OSCAR_VERSION);
    $title_text = h($title ?: cfg()['app_title']);
    $db = h(selected_db_name());

    $showExit = file_exists(OSCAR_ROOT . '/public/exit.php');
    $exitLink = $showExit
        ? "<a class='exit-link' href='exit.php'>Exit</a>"
        : "";

    // OscarDB has no authentication of its own (2.0.0). If no upstream
    // authentication is visible (host's Basic authentication, Tinyauth…),
    // say so. Can be turned off with 'warn_unprotected' => false.
    $warn = '';
    if ((cfg()['warn_unprotected'] ?? true) && upstream_user() === null) {
        $warn = "<div class='unprotected-warning'>Attention : cette interface "
              . "n'est pas protégée par mot de passe. Toute personne qui connaît "
              . "son adresse peut lire et modifier les données. "
              . "Voir la section « Sécurité » du README.</div>";
    }

    echo "<!doctype html>
<html lang='fr'>
<head>
<meta charset='utf-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<title>$title_text</title>
<link rel='icon' href='assets/favicon.svg' type='image/svg+xml'>
<link rel='apple-touch-icon' href='assets/apple-touch-icon.png'>
<link rel='stylesheet' href='assets/app.css?v=$version'>
</head>
<body>
<header>
    <a class='brand' href='index.php'>
        $app <span class='app-version'>v. $version</span>
    </a>
    <span class='current-db'>$db</span>
    $exitLink
</header>
$warn
<main>";
}


function render_footer(): void {
    // "?v=..." forces the browser to reload assets/app.js whenever the version
    // changes, so that a JS fix is not hidden by a cached copy.
    $version = h(OSCAR_VERSION);
    echo "</main><script src='assets/app.js?v=$version'></script></body></html>";
}
