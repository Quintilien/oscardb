<?php
/*
 * OscarDB
 * Copyright (C) 2026 PhS & Quintilien
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require dirname(__DIR__) . '/src/lib.php';

// ---------------------------------------------------------------------------
// STRUCTURE CHECK
// Checks that the current database has an evenements table with the columns
// required for the ICS export.
// ---------------------------------------------------------------------------
function isIcsReady(PDO $pdo): bool {
    try {
        $cols = [];
        foreach ($pdo->query("PRAGMA table_info(evenements)") as $r) {
            $cols[] = $r['name'];
        }
        $required = [
            'id', 'jour', 'heure_debut', 'heure_fin',
            'groupe', 'descr', 'arch', 'uid', 'seq'
        ];
        foreach ($required as $c) {
            if (!in_array($c, $cols, true)) return false;
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ---------------------------------------------------------------------------
// DATA SAFETY CHECK
// Returns a list of problems found in the data before export.
// ---------------------------------------------------------------------------
function checkIcsData(PDO $pdo): array {
    $issues = [];

    $checks = [
        "UID manquant"    => "SELECT COUNT(*) FROM evenements WHERE uid IS NULL OR uid = '' OR lower(uid) = 'null'",
        "SEQ NULL"        => "SELECT COUNT(*) FROM evenements WHERE seq IS NULL",
        "Date manquante"  => "SELECT COUNT(*) FROM evenements WHERE jour IS NULL OR jour = ''",
        "Heures manquantes" => "SELECT COUNT(*) FROM evenements WHERE heure_debut IS NULL OR heure_fin IS NULL",
    ];

    foreach ($checks as $label => $sql) {
        if ((int)$pdo->query($sql)->fetchColumn() > 0) {
            $issues[] = $label;
        }
    }

    return $issues;
}

// ---------------------------------------------------------------------------
// ICS HELPERS
// ---------------------------------------------------------------------------
function ics_clean(string $v): string {
    $v = trim($v);
    return ($v === '' || strtolower($v) === 'null') ? '' : $v;
}

function ics_escape(string $text): string {
    $text = str_replace("\\", "\\\\", $text);
    $text = str_replace(",",  "\\,",  $text);
    $text = str_replace(";",  "\\;",  $text);
    $text = str_replace(["\r\n", "\r", "\n"], "\\n", $text);
    return $text;
}

// Lines folded at 75 octets (RFC 5545 §3.1)
function ics_fold(string $line): string {
    $out = '';
    // 5.0.1: cut on a UTF-8 character boundary (mb_strcut), never in the middle
    while (strlen($line) > 75) {
        $part  = mb_strcut($line, 0, 75, 'UTF-8');
        $out  .= $part . "\r\n ";
        $line  = substr($line, strlen($part));
    }
    return $out . $line . "\r\n";
}

// 5.0.1: null if the date or time is invalid (the event is then skipped,
// instead of making the whole export fail)
function ics_dt(string $date, string $time): ?string {
    if ($time === '') $time = '00:00:00';
    try {
        $dt = new DateTime($date . ' ' . $time, new DateTimeZone('Europe/Brussels'));
    } catch (Exception $e) {
        return null;
    }
    return $dt->format('Ymd\THis');
}

function ics_summary(array $r): string {
    $parts = [];
    foreach (['groupe', 'lieu', 'descr', 'agapes'] as $f) {
        if ($v = ics_clean((string)($r[$f] ?? ''))) $parts[] = $v;
    }
    return implode(' ', $parts);
}

// ---------------------------------------------------------------------------
// EXPORT ICS
// Builds the iCalendar content (RFC 5545) for a list of rows.
// ---------------------------------------------------------------------------
function export_ics(array $rows): string {
    $ics  = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//OscarDB//ICS Export//FR\r\n";
    $ics .= "CALSCALE:GREGORIAN\r\n";
    $ics .= "METHOD:PUBLISH\r\n";

    foreach ($rows as $r) {
        // 5.0.1: UID reduced to safe characters (a line break made it possible
        // to inject a fake event into the file)
        $uid = preg_replace('/[^A-Za-z0-9._@+-]/', '', ics_clean((string)($r['uid'] ?? '')));
        if ($uid === '') $uid = (string)(int)$r['id'];
        $uid .= '@oscardb.local';

        $seq     = (int)($r['seq'] ?? 0);
        $summary = ics_escape(ics_summary($r));
        $start   = ics_dt((string)$r['jour'], (string)($r['heure_debut'] ?? ''));
        $end     = ics_dt((string)$r['jour'], (string)($r['heure_fin']   ?? ''));
        if ($start === null || $end === null) continue;

        $desc = sprintf(
            'Groupe: %s | Lieu: %s | Agapes: %s',
            ics_clean((string)($r['groupe'] ?? '')),
            ics_clean((string)($r['lieu']   ?? '')),
            ics_clean((string)($r['agapes'] ?? ''))
        );

        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= ics_fold("UID:$uid");
        $ics .= ics_fold("SEQUENCE:$seq");
        $ics .= ics_fold("DTSTAMP:" . gmdate('Ymd\THis\Z'));
        $ics .= ics_fold("DTSTART;TZID=Europe/Brussels:$start");
        $ics .= ics_fold("DTEND;TZID=Europe/Brussels:$end");
        $ics .= ics_fold("SUMMARY:$summary");
        $ics .= ics_fold("DESCRIPTION:" . ics_escape($desc));
        $ics .= "END:VEVENT\r\n";
    }

    $ics .= "END:VCALENDAR\r\n";
    return $ics;
}

// ---------------------------------------------------------------------------
// DATABASE SCAN
// Uses data_dir() from lib.php (single location for the databases).
// Only databases with a valid evenements table and clean data are listed.
// ---------------------------------------------------------------------------
$ready  = [];   // ['name' => 'absolute path']
$issues = [];   // ['name' => ['problem 1', ...]]

foreach (db_files() as $filename) {
    $path = data_dir() . '/' . $filename;
    try {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if (!isIcsReady($pdo)) continue;

        $name   = pathinfo($filename, PATHINFO_FILENAME);
        $errs   = checkIcsData($pdo);

        if (empty($errs)) {
            $ready[$name] = $path;
        } else {
            $issues[$name] = $errs;
        }
    } catch (Exception $e) {
        continue;
    }
}

// ---------------------------------------------------------------------------
// EXPORT ACTION
// Exports ALL the selected databases: a ZIP archive, or a single file
// when only one database is selected.
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['db'])) {

    $selected = array_filter(
        (array)$_POST['db'],
        fn($n) => is_string($n) && isset($ready[$n])   // 5.0.1 : is_string
    );

    if (count($selected) === 1) {
        // --- Single-file export ---
        $name = reset($selected);
        $pdo  = new PDO('sqlite:' . $ready[$name]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->query("
            SELECT
                c.id, c.uid, c.seq, c.jour,
                hd.ref AS heure_debut,
                hf.ref AS heure_fin,
                g.ref  AS groupe,
                c.descr, c.lieu,
                a.ref  AS agapes
            FROM evenements c
            LEFT JOIN heures hd ON hd.id = c.heure_debut
            LEFT JOIN heures hf ON hf.id = c.heure_fin
            LEFT JOIN groupes g ON g.id  = c.groupe
            LEFT JOIN agapes  a ON a.id  = c.agapes
            WHERE c.arch = 0
        ");

        $ics = export_ics($stmt->fetchAll(PDO::FETCH_ASSOC));

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^\p{L}\p{N}._ -]/u', '_', $name) . '.ics"');
        echo $ics;
        exit;

    } elseif (count($selected) > 1) {
        // --- Multi-database export: ZIP archive ---
        if (!class_exists('ZipArchive')) {
            die('Extension ZIP PHP non disponible.');
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'oscardb_ics_');   // 5.0.1: unique name
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($selected as $name) {
            $pdo = new PDO('sqlite:' . $ready[$name]);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->query("
                SELECT
                    c.id, c.uid, c.seq, c.jour,
                    hd.ref AS heure_debut,
                    hf.ref AS heure_fin,
                    g.ref  AS groupe,
                    c.descr, c.lieu,
                    a.ref  AS agapes
                FROM evenements c
                LEFT JOIN heures hd ON hd.id = c.heure_debut
                LEFT JOIN heures hf ON hf.id = c.heure_fin
                LEFT JOIN groupes g ON g.id  = c.groupe
                LEFT JOIN agapes  a ON a.id  = c.agapes
                WHERE c.arch = 0
            ");

            $zip->addFromString($name . '.ics', export_ics($stmt->fetchAll(PDO::FETCH_ASSOC)));
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="oscardb_ics.zip"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        unlink($zipPath);
        exit;
    }
}

// ---------------------------------------------------------------------------
// UI
// ---------------------------------------------------------------------------
render_header('Export ICS');
?>

<p><a href="index.php">← Accueil</a></p>

<h1>Export ICS</h1>

<?php if (empty($ready) && empty($issues)): ?>
    <p class="muted">Aucune base compatible trouvée (table <code>evenements</code> absente ou incomplète).</p>

<?php else: ?>

    <form method="post" class="crud-form">
        <?= csrf_field() ?>

        <?php foreach ($ready as $name => $path): ?>
            <label>
                <span><?= h($name) ?></span>
                <input type="checkbox" name="db[]" value="<?= h($name) ?>">
            </label>
        <?php endforeach; ?>

        <?php if (!empty($issues)): ?>
            <hr>
            <p class="muted">Bases avec données incomplètes (non exportables) :</p>
            <?php foreach ($issues as $name => $errs): ?>
                <p class="error">
                    <strong><?= h($name) ?></strong> — <?= h(implode(', ', $errs)) ?>
                </p>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="actions">
            <button type="submit">Générer ICS</button>
            <a href="index.php">Retour</a>
        </div>

    </form>

<?php endif; ?>

<?php render_footer(); ?>
