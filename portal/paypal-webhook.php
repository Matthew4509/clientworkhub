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
if (!cm_paypal_ready() || trim((string) cm_setting('paypal.webhook_id', '')) === '') {
    http_response_code(404);
    exit("Not set up.\n");
}
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_PAYPAL_')) {
        $headers[str_replace('_', '-', substr($key, 5))] = (string) $value;
    }
}


cm_webhook_precheck('paypal', cm_paypal_headers_look_right($headers));
$payload = cm_webhook_body();
if (!cm_paypal_webhook_verify($headers, $payload)) {
    cm_webhook_refused('paypal', 'a webhook arrived that PayPal did not verify');
    http_response_code(400);
    exit("Signature did not verify.\n");
}
$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    exit("Not JSON.\n");
}
$verdict = cm_paypal_webhook_handle($event);
cm_audit('payment.webhook', '', null, 'paypal ' . (string) ($event['event_type'] ?? '?') . ' (' . (string) ($event['id'] ?? '') . '): ' . $verdict);

http_response_code(str_starts_with($verdict, 'retry: ') ? 503 : 200);
echo $verdict, "\n";
