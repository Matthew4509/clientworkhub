<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0

























declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
cm_home_hide();

$qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
http_response_code(301);
header('Location: ' . cm_url('portal/receipt.php') . ($qs !== '' ? '?' . $qs : ''));
exit;
