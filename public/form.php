<?php
/*
 * OscarDB
 * Copyright (C) 2026 PhS & Quintilien
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
require dirname(__DIR__).'/src/lib.php';

// 5.0.1: table, id and back come from the address only (the form action
// repeats them): a column named "table" or "back" can no longer override them.
$table = qs('table');
if(!$table) exit('Table manquante');

if(!object_exists('table', $table)) exit('Table inconnue');

$pk=primary_key($table); 
if(!$pk) exit('Pas de clé primaire');

$id=qs('id'); 
// 2.0.0: 'back' only accepts a local address (no other site, no javascript:).
$back=safe_local_url(qs('back'), 'index.php');

$cols=table_info($table); 
$fks=foreign_keys($table);

$errors = [];
$infoMessage = '';

$yamlConfig = [];
$yamlFile = __DIR__ . '/../data/' . pathinfo(selected_db_name(), PATHINFO_FILENAME) . '.yml';

if(file_exists($yamlFile) && function_exists('yaml_parse_file')) {
    try {
        $yamlData = yaml_parse_file($yamlFile);

        if(
            isset($yamlData['tables'][trim($table)]['fields']) &&
            is_array($yamlData['tables'][trim($table)]['fields'])
        ) {
            $yamlConfig = $yamlData['tables'][trim($table)]['fields'];
        }

    } catch (\Exception $e) {
        $yamlConfig = [];
    }
}

function html_date_value($v): string {

    $v = trim((string)$v);

    if($v === '') return '';

    // already YYYY-MM-DD
    if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return $v;
    }

    // DD-MM-YYYY or DD/MM/YYYY
    if(preg_match('/^(\d{2})[-\/](\d{2})[-\/](\d{4})$/', $v, $m)) {
        return $m[3].'-'.$m[2].'-'.$m[1];
    }

    return $v;
}

function crud_nice_db_error(Throwable $e, string $actionLabel = 'opération'): string {
    $msg = $e->getMessage();
    $low = strtolower($msg);

    // Straight apostrophes escaped (\') in the messages below: a typographic
    // apostrophe once got replaced by a straight one and broke the file.

    if(str_contains($low, 'unique constraint failed')) {
        $detail = trim(preg_replace('/^.*unique constraint failed:\s*/i', '', $msg));
        return 'Impossible d\'enregistrer : une valeur doit être unique et existe déjà.' . ($detail !== '' ? ' (' . $detail . ')' : '');
    }

    if(str_contains($low, 'foreign key constraint failed')) {
        return 'Impossible d\'enregistrer : une référence liée n\'existe pas ou est encore utilisée ailleurs.';
    }

    if(str_contains($low, 'not null constraint failed')) {
        $detail = trim(preg_replace('/^.*not null constraint failed:\s*/i', '', $msg));
        return 'Impossible d\'enregistrer : un champ obligatoire est vide.' . ($detail !== '' ? ' (' . $detail . ')' : '');
    }

    if(str_contains($low, 'check constraint failed')) {
        return 'Impossible d\'enregistrer : une règle de validation de la base de données n\'est pas respectée.';
    }

    if(str_contains($low, 'datatype mismatch')) {
        return 'Impossible d\'enregistrer : type de valeur incorrect pour un des champs.';
    }

    return 'Erreur pendant l\'' . $actionLabel . ' : ' . $msg;
}

function crud_post_row(array $cols): array {
    $r = [];
    foreach($cols as $c) {
        $name = $c['name'];
        $r[$name] = $_POST[$name] ?? '';
    }
    return $r;
}

$row=[];

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postedPk = $_POST[$pk] ?? '';
    $id = $postedPk !== '' ? (string)$postedPk : $id;
    $row = crud_post_row($cols);

    if($action === 'delete') {
        if($id === '') {
            $errors[] = 'Impossible de supprimer : identifiant manquant.';
        } else {
            try {
                $st = db()->prepare('DELETE FROM '.qident($table).' WHERE '.qident($pk).'=?');
                $st->execute([$id]);
                header('Location: '.$back);
                exit;
            } catch(Throwable $e) {
                $errors[] = crud_nice_db_error($e, 'suppression');
            }
        }
    // "Save and add another" (action="save_new", 1.24): same saving logic
    // (update or insert) as "Save"; only the final redirect differs (a blank
    // form of the same table).

    } elseif($action === 'save' || $action === 'save_new') {
        try {
            if($id !== '') {
                $sets = [];
                $vals = [];

                // Read the current record to compare it before the UPDATE (1.21)
                $current = [];
                $st = db()->prepare(
                    'SELECT * FROM '.qident($table).' WHERE '.qident($pk).'=?'
                );
                $st->execute([$id]);
                $current = $st->fetch(PDO::FETCH_ASSOC) ?: [];

                foreach($cols as $c) {
                    $name = $c['name'];
                    if($name === $pk) continue;


                    // Field missing from the POST: leave it alone (e.g. hidden field not sent)
                    if(!array_key_exists($name, $_POST)) {
                        continue;
                    }

                    $new = $_POST[$name];
                    $old = $current[$name] ?? null;


                    // "real" fields compared as numbers (1.22): "100.00" shown in the
                    // form and "100" stored are the same value, not a change.
                    if(column_kind($c) === 'real' && is_numeric($new) && is_numeric($old)) {
                        $unchanged = ((float)$new === (float)$old);
                    } else {
                        // Compared as strings, to avoid int/string false positives (e.g. 1 vs "1")
                        $unchanged = ((string)$new === (string)$old);
                    }

                    if($unchanged) {
                        continue; // no real change: not included in SET
                    }

                    $sets[] = qident($name).'=?';
                    $vals[] = $new;
                }


                // Nothing changed (form saved again without modification): no UPDATE
                // (an empty SET would be a SQL syntax error), just an information message.
                if(!empty($sets)) {
                    $vals[] = $id;

                    $sql = 'UPDATE '.qident($table).' SET '.implode(', ', $sets).' WHERE '.qident($pk).'=?';
                    $st = db()->prepare($sql);
                    $st->execute($vals);

                    $infoMessage = 'Enregistrement modifié.';
                } else {
                    $infoMessage = 'Aucune modification à enregistrer.';
                }

                // "Save and add another": redirect to a blank form of the same table.
                if($action === 'save_new') {
                    header('Location: form.php?table='.urlencode($table).'&back='.urlencode($back).'&saved=1');
                    exit;
                }
            } else {
                $names = [];
                $marks = [];
                $vals = [];

                foreach($cols as $c) {
                    $name = $c['name'];
                    $isPk = ((int)$c['pk'] === 1);


                    // Field missing from the POST: SQLite DEFAULT (not included in the INSERT)
                    if(!array_key_exists($name, $_POST)) {
                        continue;
                    }

                    $v = $_POST[$name];

                    // empty PK: SQLite handles AUTOINCREMENT where applicable
                    if($isPk && trim((string)$v) === '') continue;

                    // A field present but empty is an intentional value (the user really
                    // wants to clear it): it is included. Missing fields are skipped above.


                    $names[] = qident($name);
                    $marks[] = '?';
                    $vals[] = $v;
                }

                $sql = 'INSERT INTO '.qident($table).' ('.implode(', ', $names).') VALUES ('.implode(', ', $marks).')';
                $st = db()->prepare($sql);
                $st->execute($vals);

                $newId = db()->lastInsertId();

                // "Save and add another": after the creation, redirect to a blank form
                // (no id) instead of reopening the record just created.
                if($action === 'save_new') {
                    header('Location: form.php?table='.urlencode($table).'&back='.urlencode($back).'&saved=1');
                    exit;
                }

                header('Location: form.php?table='.urlencode($table).'&id='.urlencode((string)$newId).'&back='.urlencode($back));
                exit;
            }
        } catch(Throwable $e) {
            $errors[] = crud_nice_db_error($e, 'enregistrement');
        }
    }
}

if(empty($row) && $id!==''){
    $st=db()->prepare(
        'SELECT * FROM '.qident($table).' WHERE '.qident($pk).'=?'
    );

    $st->execute([$id]);

    $row=$st->fetch(PDO::FETCH_ASSOC) ?: [];
}

// Confirmation shown on the blank form after "Save and add another"
// (the original message is lost with the redirect, a new GET request).
if($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['saved'])) {
    $infoMessage = 'Enregistrement sauvegardé. Prêt pour un nouveau.';
}

render_header($table);
?>

<p>
    <a href="<?=h($back)?>">← Retour</a>
</p>

<p>
    <?= $id!=='' ? '' : '(new)' ?>
</p>

<div class="messages">
    <?php foreach($errors as $err): ?>
        <div class="error"><?=h($err)?></div>
    <?php endforeach; ?>
    <?php if($infoMessage !== ''): ?>
        <div class="info"><?=h($infoMessage)?></div>
    <?php endif; ?>
</div>

<form method="post" action="form.php?table=<?=urlencode($table)?><?= $id!=='' ? '&id='.urlencode((string)$id) : '' ?>&back=<?=urlencode($back)?>" class="crud-form">

<input type="hidden" name="pk" value="<?=h($pk)?>">
<?=csrf_field()?>

<?php foreach($cols as $c):

    $name = $c['name'];
    $val = $row[$name] ?? '';
    $isPk = ((int)$c['pk'] === 1);

    $fieldConf = $yamlConfig[$name] ?? [];

    $fieldType =
        $fieldConf['type']
        ?? column_kind($c);


    // Default width by field TYPE (1.22): date 150px, int/real 120px, others
    // 300px; only when the .yml gives no explicit width (the .yml always wins).
    $defaultFieldWidth = match($fieldType) {
        'date' => '150px',
        'int', 'real' => '120px',
        default => '300px',
    };

    $fieldWidth =
        isset($fieldConf['width'])
        ? trim((string)$fieldConf['width'])
        : $defaultFieldWidth;



    $fieldLabel =
        isset($fieldConf['label']) && trim((string)$fieldConf['label']) !== ''
        ? (string)$fieldConf['label']
        : $name;

    // width < 0 => hidden field
    if($fieldWidth < 0):
?>

    <input
        type="hidden"
        name="<?=h($name)?>"
        value="<?=h($val)?>"
    >

    <?php continue; ?>

<?php endif; ?>

<label>

<span><?=h($fieldLabel)?></span>

<?php
// =====================================================
// FOREIGN KEY
// =====================================================
if(
    isset($fks[$name]) &&
    object_exists('table', $fks[$name]['table'])
):

    $fk = $fks[$name];

    $label = label_for_table($fk['table']);

    $curLabel = '';

    if($val !== '') {
        $s = db()->prepare(
            'SELECT '.$label.'
             FROM '.qident($fk['table']).'
             WHERE '.qident($fk['to']).'=?'
        );


        $s->execute([$val]);

        $curLabel = (string)($s->fetchColumn() ?: '');
    }
?>

<div
    class="lookup"
    data-table="<?=h($fk['table'])?>"
    data-value="<?=h($fk['to'])?>"
    data-label="<?=h($label)?>"
>

    <input
        type="hidden"
        name="<?=h($name)?>"
        value="<?=h($val)?>"
    >
    
    <input
        type="text"
        class="lookup-search"
        value="<?=h($curLabel)?>"
        placeholder="Tapez pour chercher..."
        style="width:<?=h((string)$fieldWidth)?>"
    >

    <div class="lookup-results"></div>

</div>

<?php
// =====================================================
// PRIMARY KEY
// =====================================================
elseif($isPk):


    // Primary key right-aligned (always numeric), class "num-align" (see app.css).
?>

<input
    name="<?=h($name)?>"
    value="<?=h($val)?>"
    readonly
    class="num-align"
    style="width:<?=h((string)$fieldWidth)?>"
>

<?php
// =====================================================
// BOOLEAN
// =====================================================
elseif($fieldType === 'boolean'):
?>

<input
    type="hidden"
    name="<?=h($name)?>"
    value="0"
>

<input
    type="checkbox"
    name="<?=h($name)?>"
    value="1"
    <?= intval($val) === 1 ? 'checked' : '' ?>
>

<?php
// =====================================================
// DATE
// =====================================================
elseif($fieldType === 'date'):
?>

<input
    name="<?=h($name)?>"
    value="<?=h(html_date_value($val))?>"
    type="date"
    style="width:<?=h((string)$fieldWidth)?>"
>

<?php
// =====================================================
// TEXTAREA
// =====================================================
elseif($fieldType === 'textarea'):

    $fieldHeight =
        isset($fieldConf['height'])
        ? trim((string)$fieldConf['height'])
        : '120px';
?>

<textarea
    name="<?=h($name)?>"
    style="
        width:<?=h((string)$fieldWidth)?>;
        height:<?=h((string)$fieldHeight)?>;
    "
><?=h($val)?></textarea>

<?php
// =====================================================
// NORMAL INPUT
// =====================================================
else:

    $kind = column_kind($c);




    // "real" fields (1.22): step="any" (any precision can be typed and saved);
    // the stored decimals are shown, at least 2 ("100" -> "100.00", "100.567"
    // stays as is, never rounded); class "num-real" hides the browser spinner
    // (see app.css); "num-align" right-aligns int and real fields.
    if($kind === 'real' && $val !== '') {
        $decimalsShown = 2;
        if(preg_match('/\.(\d+)$/', (string)$val, $m)) {
            $decimalsShown = max(2, strlen($m[1]));
        }
        $displayVal = number_format((float)$val, $decimalsShown, '.', '');
    } else {
        $displayVal = $val;
    }

    $numClass = trim(
        ($kind === 'int' || $kind === 'real' ? 'num-align' : '')
        . ($kind === 'real' ? ' num-real' : '')
    );
?>

<input
    name="<?=h($name)?>"
    value="<?=h($displayVal)?>"
    type="<?= $kind==='real' ? 'number' : 'text' ?>"
    class="<?=h($numClass)?>"
    style="width:<?=h((string)$fieldWidth)?>"
    <?= $kind==='real' ? 'step="any"' : '' ?>
>

<?php endif; ?>

</label>

<?php endforeach; ?>

<div class="actions">

    <button name="action" value="save">
        Enregistrer
    </button>

    <?php
    // "Save and add another": saves (update or insert, same logic as above),
    // then redirects to a blank form of the same table (see action === 'save_new').
    ?>
    <button name="action" value="save_new">
        Enregistrer et ajouter un autre
    </button>

    <?php if($id!==''): ?>

    <button
        name="action"
        value="delete"
        class="danger"
        onclick="return confirm('Supprimer cet enregistrement ?')"
    >
        Supprimer
    </button>

    <?php endif; ?>

    <a href="<?=h($back)?>">Retour</a>

</div>

</form>

<?php render_footer(); ?>
