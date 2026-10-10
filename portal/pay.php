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
$providers = cm_pay_providers();
$back = cm_url('portal/receipt.php?k=' . rawurlencode($k) . '&i=' . (int) $invoice['id']);


$refuse = static function (string $why) use ($back, $invoice): never {
    http_response_code(409);
    cm_public_head('Payment not started');
    echo '<div class="card"><h1>Payment not started</h1><p>' . e($why) . '</p>'
       . '<p><a class="btn" href="' . e($back) . '">Back to invoice ' . e((string) $invoice['number']) . '</a></p></div>';
    cm_public_foot();
    exit;
};

$problem = cm_pay_invoice_problem($invoice);
if ($problem !== '')          $refuse($problem);
if (!isset($providers[$via])) $refuse('That way of paying is not available.');




$rateKey = 'invoice:' . (int) $invoice['id'];
if (cm_rate_exceeded('pay_start', $rateKey, 20, 60)) {
    $refuse('This invoice has been opened for payment many times in the last hour. Wait a few minutes and try again, or pay another way.');
}
cm_rate_record('pay_start', $rateKey);

$returnBase = cm_url('portal/pay-return.php?k=' . rawurlencode($k) . '&i=' . (int) $invoice['id'] . '&via=' . $via);
if ($via === 'stripe') {



    $r = cm_stripe_checkout_url($invoice, $returnBase . '&session_id={CHECKOUT_SESSION_ID}', $back . '&cancelled=1');
} else {
    $r = cm_paypal_order_url($invoice, $returnBase, $back . '&cancelled=1');
}
if (isset($r['error'])) {
    cm_audit('payment.start_failed', 'client', (int) $invoice['client_id'],
             $via . ' for ' . (string) $invoice['number'] . ': ' . (string) $r['error']);
    $refuse((string) $r['error'] . ' Nothing has been charged. Try again in a moment, or pay another way.');
}
cm_audit('payment.started', 'client', (int) $invoice['client_id'],
         $via . ' checkout opened for ' . (string) $invoice['number'] . ' (' . (string) ($r['id'] ?? '') . ')');
header('Location: ' . $r['url'], true, 303);
exit;
