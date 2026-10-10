<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0




















declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/payments.php';

header('Content-Type: text/plain; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit("POST only.\n");
}
$secret = trim((string) cm_setting('stripe.webhook_secret', ''));
if ($secret === '' || !cm_stripe_ready()) {
    http_response_code(404);
    exit("Not set up.\n");
}
$sig = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

cm_webhook_precheck('stripe', (bool) preg_match('/(^|,)\s*t=\d+/', $sig) && str_contains($sig, 'v1='));
$payload = cm_webhook_body();
if (!cm_stripe_webhook_verify($payload, $sig, $secret)) {
    cm_webhook_refused('stripe', 'a webhook arrived with a signature that did not verify');
    http_response_code(400);
    exit("Signature did not verify.\n");
}
$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    exit("Not JSON.\n");
}
$verdict = cm_stripe_webhook_handle($event);
cm_audit('payment.webhook', '', null, 'stripe ' . (string) ($event['type'] ?? '?') . ' (' . (string) ($event['id'] ?? '') . '): ' . $verdict);



http_response_code(200);
echo $verdict, "\n";
