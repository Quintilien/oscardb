<?php
/*
 * OscarDB
 * Copyright (C) 2026 PhS & Quintilien
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require dirname(__DIR__).'/src/lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['db'])) {
    try { set_selected_db((string)$_POST['db']); }
    catch (Throwable $e) { http_response_code(400); exit(h($e->getMessage())); }
    header('Location: index.php');
    exit;
}

$views=get_views();
$tables=get_tables();

// 5.0.1: keys and values written between JSON quotes (valid YAML): a table or
// column name containing ":", a line break, etc. can no longer create a YAML key
// (lookup_where…) in the generated file.
function yaml_scalar($v): string {
    if ($v === null) return 'null';
    if (is_bool($v)) return $v ? 'true' : 'false';
    if (is_int($v) || is_float($v)) return (string)$v;
    return json_encode((string)$v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function write_yaml_skeleton(string $file, array $data, int $indent = 0): string {
    $lines = [];
    $prefix = str_repeat('  ', $indent);

    foreach ($data as $key => $value) {
        if (is_array($value)) {
            if (array_values($value) === $value) {
                $lines[] = $prefix . yaml_scalar($key) . ':';
                foreach ($value as $v) {
                    if (is_array($v)) {
                        $lines[] = "$prefix  -";
                        $lines[] = write_yaml_skeleton($file, $v, $indent + 2);
                    } else {
                        $lines[] = "$prefix  - " . yaml_scalar($v);
                    }
                }
            } else {
                $lines[] = $prefix . yaml_scalar($key) . ':';
                $lines[] = write_yaml_skeleton($file, $value, $indent + 1);
            }
        } else {
            $lines[] = $prefix . yaml_scalar($key) . ': ' . yaml_scalar($value);
        }
    }
    return implode("\n", $lines);
}

// --- Create the YAML file if missing ---
$yaml_file = data_dir() . '/' . pathinfo(selected_db_name(), PATHINFO_FILENAME) . '.yml';

if (!file_exists($yaml_file)) {

    $yaml = [
        'views' => [],
        'tables' => [],
    ];

    // --- All the views ---
    foreach (get_views() as $v) {
        try {
            $cols = view_columns($v['name']);
            $fields = [];
            foreach ($cols as $c) {
                $kind = column_kind($c);
                $fields[$c['name']] = [
                    'type' => $kind,
                    'align' => $kind === 'text' ? 'left' : 'right',
                    'decimals' => $kind === 'real' ? 2 : null
                ];
            }

            // display as an associative array (true by default)
            $display_assoc = array_combine(array_column($cols,'name'), array_fill(0,count($cols), true));

            $yaml['views'][$v['name']] = [
                'display' => $display_assoc,
                'fields' => $fields
            ];
        } catch (Throwable $e) {
            error_log("YAML generation: view {$v['name']} skipped: " . $e->getMessage());
        }
    }

    // --- All the tables ---
    foreach (get_tables() as $t) {
        try {
            $cols = table_info($t['name']);
            $fields = [];
            foreach ($cols as $c) {
                $kind = column_kind($c);
                $fields[$c['name']] = [
                    'type' => $kind,
                    'align' => $kind === 'text' ? 'left' : 'right',
                    'decimals' => $kind === 'real' ? 2 : null
                ];
            }
            $yaml['tables'][$t['name']] = [
                'pk' => primary_key($t['name']),
                'fields' => $fields
            ];
        } catch (Throwable $e) {
            error_log("YAML generation: table {$t['name']} skipped: " . $e->getMessage());
        }
    }

    // --- Write the YAML ---
    try {
        $yaml_content = write_yaml_skeleton($yaml_file, $yaml);
        file_put_contents($yaml_file, $yaml_content);
    } catch (Throwable $e) {
        error_log("YAML write failed: " . $e->getMessage());
    }
}

render_header('Accueil');
?>
<div class="toolbar">
  <form method="post" class="db-picker">
    <?=csrf_field()?>
    <label>Base de données</label>
    <select name="db" onchange="this.form.submit()">
      <?php foreach(db_files() as $db): ?>
        <option value="<?=h($db)?>" <?= $db===selected_db_name()?'selected':'' ?>><?=h($db)?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<section class="section-block">
  <?php if(!$views): ?><p class="muted">Aucune view dans cette base.</p><?php endif; ?>
  <div class="cards">
  <?php foreach($views as $v): $m=infer_view_meta($v['name']); ?>
    <a class="card view-card" href="list.php?type=view&name=<?=urlencode($v['name'])?>">
      <strong><?=h($v['name'])?></strong>
    </a>
  <?php endforeach; ?>
  </div>
</section>

<?php render_footer(); ?>
