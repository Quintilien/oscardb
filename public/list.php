<?php
/*
 * OscarDB
 * Copyright (C) 2026 PhS & Quintilien
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require dirname(__DIR__).'/src/lib.php';

$type=qs('type', isset($_GET['view']) ? 'view' : 'table');
$name=qs('name', qs('view', qs('table')));
$type=$type === 'table' ? 'table' : 'view';
if(!$name || !object_exists($type, $name)) { http_response_code(404); exit($type === 'view' ? 'View inconnue' : 'Table inconnue'); }

$isView = $type === 'view';
$meta=$isView ? infer_view_meta($name) : ['table'=>$name,'pk'=>primary_key($name),'key_col'=>primary_key($name)];
$cols=$isView ? view_columns($name) : table_info($name);
$colNames=array_column($cols,'name');
$sort=qs('sort');
$dir=strtoupper(qs('dir', 'ASC')); $dir=$dir==='DESC'?'DESC':'ASC';
$q=trim(qs('q'));





$yamlConfig = [];




$yamlFile = __DIR__ . '/../data/' . pathinfo(selected_db_name(), PATHINFO_FILENAME) . '.yml';


if(file_exists($yamlFile) && function_exists('yaml_parse_file')) {
    try {
        $yamlData = yaml_parse_file($yamlFile);
        if(isset($yamlData['views'][trim($name)]['fields']) && is_array($yamlData['views'][trim($name)]['fields'])) {
            $yamlConfig = $yamlData['views'][trim($name)]['fields'];
        } elseif(isset($yamlData['tables'][trim($name)]['fields']) && is_array($yamlData['tables'][trim($name)]['fields'])) {
            $yamlConfig = $yamlData['tables'][trim($name)]['fields'];
        }
    } catch (\Exception $e) {
        $yamlConfig = [];
    }
}










// Type, decimals and format injected from the YAML for every column, here
// (before the SQL and the Excel export) so that both the HTML display and
// the Excel export of views can use them. "format" (1.25, e.g. "blank_zero")
// is used by format_value() in lib.php, in lists only.
foreach($cols as &$c) {
    $colName = $c['name'];
    $c['type'] = $yamlConfig[$colName]['type'] ?? ($c['type'] ?? 'text');
    $c['decimals'] = $yamlConfig[$colName]['decimals'] ?? null;
    $c['format'] = $yamlConfig[$colName]['format'] ?? null;
}
unset($c);

// --- SQL ---

// $sqlBase = SELECT + WHERE + ORDER BY, without LIMIT/OFFSET, reused as is
// by the Excel export below, which covers the whole filtered result and not
// only the page shown.
$sqlBase='SELECT * FROM '.qident($name); $params=[];
if($q!=='') {
    $parts=[];
    foreach($colNames as $c){ $parts[]='CAST('.qident($c).' AS TEXT) LIKE ?'; $params[]='%'.$q.'%'; }
    $sqlBase.=' WHERE '.implode(' OR ',$parts);
}
if($sort && in_array($sort,$colNames,true)) $sqlBase.=' ORDER BY '.qident($sort).' '.$dir;

// Excel export of views (1.23, see src/export_excel.php). Views only (tables
// are not exported here). Every column of the view is exported, including
// those hidden on screen (width: -1 in the YAML). Detected BEFORE any HTML
// output (before render_header()), so that the .xlsx HTTP headers can be sent.
$doExport = $isView && qs('export') === '1';

if($doExport) {

    require dirname(__DIR__).'/src/export_excel.php';

    $exportColumns = [];
    foreach($cols as $c) {
        $colName = $c['name'];
        $exportColumns[$colName] = [
            'label'   => $yamlConfig[$colName]['label'] ?? $colName,
            'kind'    => column_kind($c),
            'boolean' => (($c['type'] ?? '') === 'boolean'),
        ];
    }

    $stExport = db()->prepare($sqlBase);
    $stExport->execute($params);
    $exportRows = $stExport->fetchAll(PDO::FETCH_ASSOC);

    $exportFilename = selected_db_name().'_'.$name.'_'.date('Ymd_His');
    export_array_to_excel($exportFilename, $exportColumns, $exportRows);
    // export_array_to_excel() exits
}

$page = min(1000000, max(1, intval(qs('page', '1'))));   // 5.0.1: bounded
$limit = 500;
$offset = ($page - 1) * $limit;

$sql = $sqlBase." LIMIT $limit OFFSET $offset";

$st=db()->prepare($sql); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC);

render_header($name);
?>
<div class="toolbar">
  <div>
    <p><a href="index.php">← Accueil</a></p>
    <h1><?=h($name)?> <span class="badge <?= $isView?'badge-view':'badge-table' ?>"><?= $isView?'':'table / CRUD' ?>        
    </span></h1>
  </div>
  <form method="get" class="search">
    <input type="hidden" name="type" value="<?=h($type)?>">
    <input type="hidden" name="name" value="<?=h($name)?>">
    <input name="q" value="<?=h($q)?>" placeholder="Recherche tous champs...">
    <button>Rechercher</button>
    <?php if($q!==''): ?><a href="list.php?type=<?=h($type)?>&name=<?=urlencode($name)?>">Effacer</a><?php endif; ?>
  </form>
  <?php if($isView):
      // Excel export button (views only); keeps the current filter and sort
      // (q, sort, dir) in the export address.
      $exportUrl = '?type='.h($type).'&name='.urlencode($name)
          .'&q='.urlencode($q)
          .'&sort='.urlencode($sort)
          .'&dir='.urlencode($dir)
          .'&export=1';
  ?>
  <a class="button-link" href="<?=$exportUrl?>">⭳ Export Excel</a>
  <?php endif; ?>
</div>

<?php if($meta['pk'] && $meta['table']): ?>
<p>
  <a class="button-link" href="form.php?table=<?=urlencode($meta['table'])?>&back=<?=urlencode($_SERVER['REQUEST_URI'])?>">
    + Nouvel enregistrement
  </a>
</p>
<?php endif; ?>

    

<div class="table-wrapper">

    <table id="table" class="grid resizable" data-grid-id="<?=h(selected_db_name().'.'.$type.'.'.$name)?>">

        <colgroup>
            <?php foreach($cols as $c):
                $col = $c['name'];
                $widthValue = $yamlConfig[$col]['width'] ?? null;

                // Hide the column if width < 0
                if($widthValue !== null && $widthValue < 0) continue;

                // width > 0 in the YAML: imposed initial width.
                // Otherwise the JS measures the widest content on load.
                $initialWidth = ($widthValue !== null && $widthValue > 0) ? (int)$widthValue : 0;
            ?>
            <col data-col="<?=h($col)?>" data-initial-width="<?=$initialWidth?>">
            <?php endforeach; ?>
        </colgroup>

        <thead>
            <tr>
                <?php foreach($cols as $c):
                    $col = $c['name'];
                    $widthValue = $yamlConfig[$col]['width'] ?? null;

                    // Hide the column if width < 0
                    if($widthValue !== null && $widthValue < 0) continue;

                    $next = ($sort === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
                ?>

                <th data-col="<?=h($col)?>" class="<?=column_kind($c)==='real'||column_kind($c)==='int'?'num':''?>">
                    <a href="?type=<?=h($type)?>&name=<?=urlencode($name)?>&q=<?=urlencode($q)?>&sort=<?=urlencode($col)?>&dir=<?=$next?>">
                        <?=h($yamlConfig[$col]['label'] ?? $col)?><?= $sort === $col ? ($dir==='ASC'?' ▲':' ▼') : '' ?>
                    </a>
                    <span class="resize-handle"></span>
                </th>

                <?php endforeach; ?>
            </tr>
        </thead>

        <tbody>
        <?php



        foreach($rows as $r):
            $href = '#';
            // Clickable row only if the key value is not empty (1.22): on a view mixing
            // detail and subtotal rows (id='' on subtotals), an empty id opened a blank
            // form, and a click on a subtotal row created an empty record.
            if($isView) {
                $key = $meta['key_col'] ?? '';
                if($meta['table'] && $meta['pk'] && isset($r[$key]) && $r[$key] !== '') {
                    $href = 'form.php?table='.urlencode($meta['table']).'&id='.urlencode((string)$r[$key]).'&back='.urlencode($_SERVER['REQUEST_URI']);
                }
            } elseif($meta['pk'] && isset($r[$meta['pk']]) && $r[$meta['pk']] !== '') {
                $href = 'form.php?table='.urlencode($name).'&id='.urlencode((string)$r[$meta['pk']]).'&back='.urlencode($_SERVER['REQUEST_URI']);
            }
        ?>
        <tr <?= $href !== '#' ? "onclick=\"location.href='".h($href)."'\"" : '' ?>>
            <?php foreach($cols as $c):
                $col = $c['name'];
                $widthValue = $yamlConfig[$col]['width'] ?? null;

                if($widthValue !== null && $widthValue < 0) continue;

                $tdClass = column_kind($c)==='real'||column_kind($c)==='int' ? 'num' : '';
                if(($c['type'] ?? '') === 'boolean') {
                    $tdClass = trim($tdClass.' bool-center');
                }
            ?>
            <td class="<?=h($tdClass)?>">
                <?php
                $val = $r[$col] ?? null;

                if (($c['type'] ?? '') === 'boolean') {
                    // boolean: a tick if the value is 1, nothing otherwise
                    echo intval($val) === 1 ? '✓' : '';
                } else {
                    // Other types: formatted value
                    echo h(format_value($val, $c));
                }
                ?>
            </td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>

    </table>

</div>

<script>
(function(){
    const table = document.getElementById("table");
    if(!table) return;

    const cols = Array.from(table.querySelectorAll("col"));
    const headers = Array.from(table.querySelectorAll("th"));

    function px(value){
        const n = parseInt(value, 10);
        return Number.isFinite(n) && n > 0 ? n : 0;
    }

    function measureColumn(index){
        let max = 30;

        const cells = table.querySelectorAll(
            "thead tr > *:nth-child(" + (index + 1) + ")," +
            "tbody tr > *:nth-child(" + (index + 1) + ")"
        );

        cells.forEach(cell => {
            max = Math.max(max, cell.scrollWidth + 2);
        });

        return max;
    }

    function updateTableWidth(){
        let total = 0;

        cols.forEach(col => {
            total += px(col.style.width);
        });

        table.style.width = total + "px";
    }






    


    cols.forEach((col, index) => {
        const initial = px(col.dataset.initialWidth);
        const width = initial > 0 ? initial : measureColumn(index);
        col.style.width = width + "px";
    });

    updateTableWidth();

    headers.forEach((th, index) => {
        const handle = th.querySelector(".resize-handle");
        if(!handle || !cols[index]) return;

        handle.addEventListener("mousedown", function(e){
            const startX = e.pageX;
            const startWidth = px(cols[index].style.width) || 30;

            function resizeColumn(e){
                const newWidth = Math.max(30, startWidth + (e.pageX - startX));
                cols[index].style.width = newWidth + "px";
                updateTableWidth();
            }

            function stopResize(){
                document.removeEventListener("mousemove", resizeColumn);
                document.removeEventListener("mouseup", stopResize);
            }

            document.addEventListener("mousemove", resizeColumn);
            document.addEventListener("mouseup", stopResize);
            e.preventDefault();
            e.stopPropagation();
        });
    });
})();

/*
(function(){
    const table = document.getElementById("table");
    if (!table) return;

    const thead = table.querySelector("thead");
    const cols = Array.from(table.querySelectorAll("col"));
    const ths = Array.from(table.querySelectorAll("thead th"));

    if (!thead || !cols.length || !ths.length) return;

    function syncWidths(){
        ths.forEach((th, i) => {
            if (cols[i]) {
                th.style.width = cols[i].style.width;
                th.style.minWidth = cols[i].style.width;
                th.style.maxWidth = cols[i].style.width;
            }
        });
    }

    function syncHeader(){
        const rect = table.getBoundingClientRect();
        const shouldStick =
            rect.top < 0 &&
            rect.bottom > thead.offsetHeight;

        syncWidths();

        if (!shouldStick) {
            thead.style.position = "";
            thead.style.top = "";
            thead.style.left = "";
            thead.style.zIndex = "";
            thead.style.display = "";
            thead.style.width = "";

            return;
        }

        thead.style.position = "fixed";
        thead.style.top = "0";
        thead.style.left = rect.left + "px";
        thead.style.zIndex = "9999";
        thead.style.display = "table";

        thead.style.width =
            table.style.width || rect.width + "px";
    }

    window.addEventListener("scroll", syncHeader);
    window.addEventListener("resize", syncHeader);
    window.addEventListener("mousemove", syncHeader);

    syncHeader();
})();
*/







(function(){
    const table = document.getElementById("table");
    if (!table) return;

    const wrapper = table.closest(".table-wrapper");
    const thead = table.querySelector("thead");
    const cols = Array.from(table.querySelectorAll("col"));
    const ths = Array.from(table.querySelectorAll("thead th"));

    if (!wrapper || !thead || !cols.length || !ths.length) return;

    function syncWidths(){
        ths.forEach((th, i) => {
            if (!cols[i]) return;
            const w = cols[i].style.width;
            th.style.width = w;
            th.style.minWidth = w;
            th.style.maxWidth = w;
        });
    }

    function syncHeader(){
        const rect = table.getBoundingClientRect();
        const wrapperRect = wrapper.getBoundingClientRect();

        const shouldStick =
            rect.top < 0 &&
            rect.bottom > thead.offsetHeight;

        syncWidths();
/*
        if (!shouldStick) {
            thead.style.position = "";
            thead.style.top = "";
            thead.style.left = "";
            thead.style.zIndex = "";
            thead.style.display = "";
            thead.style.width = "";
            return;
        }
*/


        if (!shouldStick) {
            thead.style.position = "";
            thead.style.top = "";
            thead.style.left = "";
            thead.style.zIndex = "";
            thead.style.display = "";
            thead.style.width = "";
            thead.style.transform = "";
            return;
        }

        
        thead.style.position = "fixed";
        thead.style.top = "0";
        thead.style.left = wrapperRect.left + "px";
        thead.style.zIndex = "9999";
        thead.style.display = "table";
        thead.style.width = table.style.width || rect.width + "px";

        thead.style.transform = "translateX(" + (-wrapper.scrollLeft) + "px)";
    }

    window.addEventListener("scroll", syncHeader);
    window.addEventListener("resize", syncHeader);
    wrapper.addEventListener("scroll", syncHeader);

    syncHeader();
})();



</script>





<div class="pagination">

<?php
$baseParams = [
    'type' => $type,
    'name' => $name,
    'q' => $q,
    'sort' => $sort,
    'dir' => $dir,
];
?>

<?php if($page > 1): ?>
    <a class="button-link"
       href="?<?=h(http_build_query($baseParams + ['page' => $page - 1]))?>">
       ← Précédent
    </a>
<?php endif; ?>

<span class="muted">
    Page <?=$page?> — <?=count($rows)?> ligne(s)
</span>

<?php if(count($rows) === $limit): ?>
    <a class="button-link"
       href="?<?=h(http_build_query($baseParams + ['page' => $page + 1]))?>">
       Suivant →
    </a>
<?php endif; ?>

</div>



<?php render_footer(); ?>
