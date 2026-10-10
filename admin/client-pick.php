<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0



















declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/clients.php';

cm_require_admin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');


header('X-Content-Type-Options: nosniff');

$term = trim((string) ($_GET['q'] ?? ''));















if ($term === '') {
    echo json_encode(['rows' => []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
$rows = cm_client_search($term, '', 1, 10)['rows'];

$out = [];
foreach ($rows as $c) {



    $bits = array_values(array_filter([
        trim((string) $c['company']) !== '' ? trim((string) $c['company']) : '',
        trim((string) $c['email']),
        trim((string) $c['phone']),
    ], static fn ($s) => $s !== ''));

    $out[] = [
        'id'   => (int) $c['id'],
        'name' => cm_client_display_name($c),
        'meta' => implode(' · ', array_slice($bits, 0, 2)),
    ];
}

echo json_encode(['rows' => $out], JSON_UNESCAPED_UNICODE);
