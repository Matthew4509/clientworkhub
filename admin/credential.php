<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0





















declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/assets.php';

$admin = cm_require_admin();



if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex');


function cm_cred_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}






cm_require_csrf_json();

$assetId = (int) ($_POST['asset_id'] ?? 0);
$asset   = cm_asset($assetId);
if ($asset === null) {
    cm_cred_json(['ok' => false, 'error' => 'That account is no longer here.'], 404);
}

$cred = cm_credential($assetId);
if ($cred === null || $cred['password_enc'] === null) {
    cm_cred_json(['ok' => false, 'error' => 'No password is stored for this account.'], 404);
}

$attempt = cm_reveal_attempt($admin, $cred, (string) $asset['name'], (int) $asset['client_id']);
if (!$attempt['ok']) {
    cm_cred_json(['ok' => false, 'error' => $attempt['error']], 429);
}

cm_cred_json([
    'ok'       => true,
    'password' => (string) $attempt['values']['password'],


    'notes'    => (string) ($attempt['values']['notes'] ?? ''),
]);
