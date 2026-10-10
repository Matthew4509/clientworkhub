<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0

















declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/publicview.php';
require_once CM_LIB . '/payments.php';

cm_public_require_enabled();

$k = (string) ($_GET['k'] ?? '');
$link = cm_link_resolve($k);
if ($link === null) {
    cm_public_404('That link has expired or is no longer in use. Get in touch and we will send you a new one.');
}
$invoice = cm_invoice((int) ($_GET['i'] ?? 0));
if ($invoice === null || (int) $invoice['client_id'] !== (int) $link['client_id']
    || (string) $invoice['status'] === 'draft') {
    cm_public_404('There is no invoice at this address.');
}
cm_link_touch($link);

$via = (string) ($_GET['via'] ?? '');
$back = cm_url('portal/receipt.php?k=' . rawurlencode($k) . '&i=' . (int) $invoice['id']);

$problem = 'That way of paying is not available.';
if ($via === 'stripe' && cm_stripe_ready()) {
    $problem = cm_stripe_session_settle((string) ($_GET['session_id'] ?? ''));
} elseif ($via === 'paypal' && cm_paypal_ready()) {
    $problem = cm_paypal_order_settle((string) ($_GET['token'] ?? ''));
}

if ($problem !== '') {



    cm_audit('payment.return_unsettled', 'client', (int) $invoice['client_id'],
             $via . ' return for ' . (string) $invoice['number'] . ': ' . $problem);
    header('Location: ' . $back . '&unsettled=1', true, 303);
    exit;
}
header('Location: ' . $back . '&paid=1', true, 303);
exit;
