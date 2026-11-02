<?php
/*
 * OscarDB
 * Copyright (C) 2026 PhS & Quintilien
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require dirname(__DIR__).'/src/lib.php';

header('Content-Type: application/json; charset=utf-8');

$table = qs('table');
$value = qs('value', 'id');
$q = trim(qs('q'));

if(!$table || !object_exists('table', $table)) {
    echo '[]';
    exit;
}

$cols = array_column(table_info($table), 'name');

if(!in_array($value, $cols, true)) {
    $value = primary_key($table) ?? $cols[0];
}

$labelSql = label_for_table($table);

$sql = '
    SELECT
        '.qident($value).' AS id,
        '.$labelSql.' AS label
    FROM '.qident($table);

$params = [];
$where = [];

// lookup_where (1.24): fixed condition from the YAML (e.g. "archive = 0"),
// applied in addition to the text typed by the user (see below). The fragment
// comes from the .yml file on the server, not from the HTTP request.
if($w = lookup_where_for_table($table)) {
    $where[] = '('.$w.')';
}

if($q !== '') {
    $where[] = 'CAST(('.$labelSql.') AS TEXT) LIKE ?';
    $params[] = '%'.$q.'%';
}

if($where) {
    $sql .= '
        WHERE '.implode(' AND ', $where).'
    ';
}

// lookup_order_by / lookup_limit (1.24): sort and number of results set per
// table in the YAML; default: sorted by label, 20 results. $orderBy comes from
// the .yml (not from the HTTP request); $limit is an integer
// (lookup_limit_for_table()).
$orderBy = lookup_order_by_for_table($table) ?? 'label';
$limit = lookup_limit_for_table($table);

$sql .= '
    ORDER BY '.$orderBy.'
    LIMIT '.$limit.'
';

$st = db()->prepare($sql);
$st->execute($params);

echo json_encode(
    $st->fetchAll(PDO::FETCH_ASSOC),
    JSON_UNESCAPED_UNICODE
);
