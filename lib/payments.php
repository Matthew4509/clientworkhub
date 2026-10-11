<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0



































declare(strict_types=1);
if (!defined('CM_ROOT')) { http_response_code(404); exit; }
require_once CM_LIB . '/invoices.php';

const CM_STRIPE_API = 'https://api.stripe.com';
const CM_PAYPAL_API_LIVE    = 'https://api-m.paypal.com';
const CM_PAYPAL_API_SANDBOX = 'https://api-m.sandbox.paypal.com';



function cm_stripe_modes(): array
{
    return ['test' => ['label' => 'Test', 'blurb' => 'Test keys (sk_test_…). Nothing is really charged.'],
            'live' => ['label' => 'Live', 'blurb' => 'Live keys (sk_live_…). Real money.']];
}

function cm_paypal_modes(): array
{
    return ['sandbox' => ['label' => 'Sandbox', 'blurb' => 'PayPal\'s sandbox. Nothing is really charged.'],
            'live'    => ['label' => 'Live',    'blurb' => 'Real money.']];
}



function cm_stripe_ready(): bool
{
    try {
        return trim((string) cm_setting('stripe.secret_key', '')) !== ''
            && trim((string) cm_setting('stripe.publishable_key', '')) !== '';
    } catch (Throwable $ex) { return false; }
}

function cm_paypal_ready(): bool
{
    try {
        return trim((string) cm_setting('paypal.client_id', '')) !== ''
            && trim((string) cm_setting('paypal.client_secret', '')) !== '';
    } catch (Throwable $ex) { return false; }
}






function cm_pay_providers(): array
{
    if (cm_home()) return [];
    $out = [];
    if (cm_stripe_ready()) $out['stripe'] = ['label' => 'Pay by card', 'via' => 'Stripe'];
    if (cm_paypal_ready()) $out['paypal'] = ['label' => 'Pay with PayPal', 'via' => 'PayPal'];
    return $out;
}


function cm_pay_invoice_problem(array $invoice): string
{
    if (!cm_pay_providers())                               return 'No online payment is set up.';
    if ((string) $invoice['status'] !== 'sent')            return 'Only an issued, unpaid invoice can be paid online.';
    if (cm_invoice_balance_cents($invoice) <= 0)           return 'There is nothing owing on it.';
    if (trim((string) $invoice['currency']) === '')        return 'The invoice carries no currency.';
    return '';
}










function cm_pay_http(string $method, string $url, array $headers, ?string $body = null): array
{



    if (PHP_SAPI === 'cli' && isset($GLOBALS['cm_pay_http_stub']) && is_callable($GLOBALS['cm_pay_http_stub'])) {
        return ($GLOBALS['cm_pay_http_stub'])($method, $url, $headers, $body);
    }
    if (!function_exists('curl_init')) {
        return ['status' => 0, 'body' => '', 'error' => 'PHP\'s curl extension is not loaded.'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $out = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = $out === false ? (string) curl_error($ch) : '';
    return ['status' => $status, 'body' => is_string($out) ? $out : '', 'error' => $err];
}










const CM_WEBHOOK_MAX_BODY = 262144;

function cm_webhook_precheck(string $rail, bool $headersLookRight): void
{
    require_once CM_LIB . '/password.php';
    $ip = cm_client_ip_bucket();
    if (cm_rate_exceeded('webhook-refused', $ip, 30, 60)) {
        http_response_code(429);
        header('Retry-After: 3600');
        exit("Too many refused requests.\n");
    }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > CM_WEBHOOK_MAX_BODY) {
        cm_webhook_refused($rail, 'a webhook body over ' . CM_WEBHOOK_MAX_BODY . ' bytes was refused unread');
        http_response_code(413);
        exit("Too large.\n");
    }
    if (!$headersLookRight) {
        cm_webhook_refused($rail, 'a request without the provider\'s signature headers was refused');
        http_response_code(400);
        exit("Signature headers missing.\n");
    }
}


function cm_webhook_body(): string
{
    $in = fopen('php://input', 'rb');
    $body = $in ? (string) stream_get_contents($in, CM_WEBHOOK_MAX_BODY + 1) : '';
    if ($in) fclose($in);
    if (strlen($body) > CM_WEBHOOK_MAX_BODY) {
        http_response_code(413);
        exit("Too large.\n");
    }
    return $body;
}


function cm_webhook_refused(string $rail, string $why): void
{
    require_once CM_LIB . '/password.php';
    $ip = cm_client_ip_bucket();
    $first = cm_rate_count('webhook-refused', $ip, 60) === 0;
    cm_rate_record('webhook-refused', $ip);
    if ($first) cm_audit('payment.webhook_refused', '', null, $rail . ': ' . $why . ' (further refusals from this address this hour are counted, not logged)');
}


function cm_paypal_headers_look_right(array $headers): bool
{
    foreach (['PAYPAL-AUTH-ALGO', 'PAYPAL-CERT-URL', 'PAYPAL-TRANSMISSION-ID', 'PAYPAL-TRANSMISSION-SIG', 'PAYPAL-TRANSMISSION-TIME'] as $k) {
        $v = (string) ($headers[$k] ?? '');
        if ($v === '' || strlen($v) > 2048) return false;
    }
    return str_starts_with((string) $headers['PAYPAL-CERT-URL'], 'https://');
}

function cm_pay_window(?int $now = null): int
{
    $now ??= time();
    return $now - ($now % 1800);
}


function cm_pay_already_recorded(int $invoiceId, string $reference): bool
{
    return $reference !== '' && (int) cm_val('SELECT COUNT(*) FROM invoice_payment WHERE invoice_id = ? AND reference = ?',
                                             [$invoiceId, $reference]) > 0;
}
























function cm_pay_record(int $invoiceId, int $amountCents, string $currency, string $method, string $reference): string
{
    $db = cm_db();
    $own = !$db->inTransaction();
    if ($own) $db->beginTransaction();
    try {
        cm_val('SELECT id FROM invoice WHERE id = ? FOR UPDATE', [$invoiceId]);
        $result = cm_pay_record_locked($invoiceId, $amountCents, $currency, $method, $reference);
        if ($own) $db->commit();
    } catch (Throwable $ex) {
        if ($own && $db->inTransaction()) $db->rollBack();
        throw $ex;
    }


    if ($result['tell_owner'] !== '') cm_pay_tell_owner($result['subject'], $result['tell_owner']);
    return $result['problem'];
}


function cm_pay_record_locked(int $invoiceId, int $amountCents, string $currency, string $method, string $reference): array
{
    $out = ['problem' => '', 'tell_owner' => '', 'subject' => ''];
    $invoice = cm_invoice($invoiceId);
    if (!$invoice)                                        return ['problem' => 'No such invoice.'] + $out;
    if (cm_pay_already_recorded($invoiceId, $reference))  return $out;

    $number = (string) $invoice['number'];
    $who    = $method === 'paypal' ? 'PayPal' : 'Stripe';
    $open   = "\n\nOpen it: " . cm_url('admin/invoice.php?id=' . $invoiceId);

    if (strtoupper(trim($currency)) !== strtoupper(trim((string) $invoice['currency']))) {
        cm_audit('payment.refused', 'client', (int) $invoice['client_id'],
                 $method . ' paid ' . $currency . ' for ' . $number . ' which is in '
                 . (string) $invoice['currency'] . ' — not recorded (' . $reference . ')');
        return ['problem' => 'The payment was in the wrong currency.',
                'subject' => 'A payment for invoice ' . $number . ' could not be recorded',
                'tell_owner' => $who . ' took a payment for invoice ' . $number . ' in ' . strtoupper($currency)
                              . ', but the invoice is in ' . (string) $invoice['currency'] . ", so it was not recorded.\n\n"
                              . 'Reference: ' . $reference . "\n\n"
                              . 'Check the payment in your ' . $who . ' dashboard, then either refund it there '
                              . 'or record it on the invoice by hand.' . $open] + $out;
    }
    if ((string) $invoice['status'] === 'void') {
        cm_audit('payment.refused', 'client', (int) $invoice['client_id'],
                 $method . ' paid ' . cm_invoice_money($invoice, $amountCents) . ' against ' . $number
                 . ' which is void — not recorded (' . $reference . ')');
        return ['problem' => 'That invoice has been cancelled.',
                'subject' => 'A payment arrived for cancelled invoice ' . $number,
                'tell_owner' => $who . ' took ' . cm_invoice_money($invoice, $amountCents) . ' for invoice ' . $number
                              . ", which was cancelled (void), so it was not recorded.\n\n"
                              . 'Reference: ' . $reference . "\n\n"
                              . 'Refund it from your ' . $who . ' dashboard, or issue a new invoice and record it there by hand.'
                              . $open] + $out;
    }

    $balance = cm_invoice_balance_cents($invoice);
    $problem = cm_invoice_add_payment($invoiceId, $amountCents, $method, date('Y-m-d'), $reference, $method . ' (online)');
    if ($problem !== '') {


        cm_audit('payment.refused', 'client', (int) $invoice['client_id'],
                 $method . ' paid ' . cm_invoice_money($invoice, $amountCents) . ' against ' . $number
                 . ' — not recorded: ' . $problem . ' (' . $reference . ')');
        return ['problem' => $problem,
                'subject' => 'A payment for invoice ' . $number . ' could not be recorded',
                'tell_owner' => $who . ' took ' . cm_invoice_money($invoice, $amountCents) . ' for invoice ' . $number
                              . ' but it could not be recorded: ' . $problem . "\n\nReference: " . $reference
                              . "\n\nCheck the payment in your " . $who . ' dashboard.' . $open] + $out;
    }
    if ($amountCents === $balance) return $out;

    $after = cm_invoice($invoiceId) ?? $invoice;
    $left  = cm_invoice_balance_cents($after);
    cm_audit('payment.mismatch', 'client', (int) $invoice['client_id'],
             $method . ' paid ' . cm_invoice_money($invoice, $amountCents) . ' against ' . $number . ' which had '
             . cm_invoice_money($invoice, $balance) . ' owing — recorded; '
             . ($left < 0 ? 'overpaid by ' . cm_invoice_money($invoice, -$left) : 'still owing ' . cm_invoice_money($invoice, $left))
             . ' (' . $reference . ')');
    return ['problem' => '',
            'subject' => $left < 0 ? 'Invoice ' . $number . ' has been overpaid' : 'A part payment arrived for invoice ' . $number,
            'tell_owner' => $who . ' took ' . cm_invoice_money($invoice, $amountCents) . ' for invoice ' . $number
                          . ', which had ' . cm_invoice_money($invoice, $balance) . " owing. The payment has been recorded.\n\n"
                          . ($left < 0
                              ? 'The invoice is now OVERPAID by ' . cm_invoice_money($invoice, -$left) . '. This usually means '
                                . 'the client paid twice (two tabs, or after a payment you recorded by hand). Refund the extra '
                                . 'from your ' . $who . ' dashboard, then use Record a refund on the invoice.'
                              : 'It still owes ' . cm_invoice_money($invoice, $left) . '.')
                          . "\n\nReference: " . $reference . $open];
}







function cm_pay_tell_owner(string $subject, string $body): void
{
    try {
        $to = cm_mail_reply_to() !== '' ? cm_mail_reply_to() : cm_mail_from();
        if ($to !== '') cm_mail($to, $subject, $body, ['kind' => 'payment_alert']);
    } catch (Throwable $ex) {
        error_log('payment alert failed: ' . $ex->getMessage());
    }
}














function cm_pay_zero_decimal(string $currency, string $provider): bool
{
    $lists = [
        'stripe' => ['BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF'],
        'paypal' => ['HUF','JPY','TWD'],
    ];
    return in_array(strtoupper(trim($currency)), $lists[$provider] ?? [], true);
}


function cm_stripe_zero_decimal(string $currency): bool
{
    return cm_pay_zero_decimal($currency, 'stripe');
}









function cm_pay_install_mark(): string
{
    return substr(hash_hmac('sha256', 'checkout-mark', cm_app_key()), 0, 16);
}







function cm_pay_checkout_is_ours(int $invoiceId, string $number, string $mark): bool
{
    if ($invoiceId <= 0) return false;
    $invoice = cm_invoice($invoiceId);
    if (!$invoice || (string) $invoice['number'] === '' || !hash_equals((string) $invoice['number'], $number)) return false;
    return $mark === '' || hash_equals(cm_pay_install_mark(), $mark);
}

function cm_stripe_checkout_url(array $invoice, string $successUrl, string $cancelUrl): array
{
    $secret = trim((string) cm_setting('stripe.secret_key', ''));
    $cur = strtolower((string) $invoice['currency']);
    $balance = cm_invoice_balance_cents($invoice);
    $unit = cm_stripe_zero_decimal($cur) ? (int) round($balance / 100) : $balance;
    $fields = [
        'mode' => 'payment',
        'client_reference_id' => (string) (int) $invoice['id'],
        'success_url' => $successUrl,
        'cancel_url'  => $cancelUrl,
        'line_items[0][quantity]' => '1',
        'line_items[0][price_data][currency]' => $cur,
        'line_items[0][price_data][unit_amount]' => (string) $unit,
        'line_items[0][price_data][product_data][name]' => 'Invoice ' . (string) $invoice['number'],
        'metadata[invoice_id]' => (string) (int) $invoice['id'],
        'metadata[invoice_number]' => (string) $invoice['number'],
        'metadata[cwh_install]' => cm_pay_install_mark(),
    ];
    if (trim((string) $invoice['bill_to_email']) !== '') $fields['customer_email'] = trim((string) $invoice['bill_to_email']);







    $window = cm_pay_window();
    $fields['expires_at'] = (string) ($window + 3600);
    $r = cm_pay_http('POST', CM_STRIPE_API . '/v1/checkout/sessions',
                     ['Authorization: Basic ' . base64_encode($secret . ':'),
                      'Content-Type: application/x-www-form-urlencoded',
                      'Idempotency-Key: cwh-' . hash('sha256', http_build_query($fields))],
                     http_build_query($fields));
    if ($r['error'] !== '')  return ['error' => 'Stripe could not be reached: ' . $r['error']];
    $j = json_decode($r['body'], true);
    if ($r['status'] !== 200 || !is_array($j) || empty($j['url'])) {
        return ['error' => 'Stripe refused to start the payment' . (is_array($j) && !empty($j['error']['message']) ? ': ' . (string) $j['error']['message'] : '.')];
    }
    return ['url' => (string) $j['url'], 'id' => (string) ($j['id'] ?? '')];
}


function cm_stripe_session_settle(string $sessionId): string
{
    if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) return 'That does not look like a Stripe session.';
    $secret = trim((string) cm_setting('stripe.secret_key', ''));
    $r = cm_pay_http('GET', CM_STRIPE_API . '/v1/checkout/sessions/' . rawurlencode($sessionId),
                     ['Authorization: Basic ' . base64_encode($secret . ':')]);
    if ($r['error'] !== '') return 'Stripe could not be reached: ' . $r['error'];
    $s = json_decode($r['body'], true);
    if ($r['status'] !== 200 || !is_array($s)) return 'Stripe did not recognise that payment.';
    return cm_stripe_session_record($s);
}


function cm_stripe_session_record(array $session): string
{
    if ((string) ($session['payment_status'] ?? '') !== 'paid') return 'That payment has not completed.';


    $meta = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
    $invoiceId = (int) ($meta['invoice_id'] ?? 0);
    if ($invoiceId <= 0) return 'That payment names no invoice.';
    if (!cm_pay_checkout_is_ours($invoiceId, (string) ($meta['invoice_number'] ?? ''), (string) ($meta['cwh_install'] ?? ''))) {
        return 'That payment was not started by this site.';
    }
    $cur = (string) ($session['currency'] ?? '');
    $amount = (int) ($session['amount_total'] ?? 0);
    if (cm_stripe_zero_decimal($cur)) $amount *= 100;
    $ref = (string) ($session['payment_intent'] ?? $session['id'] ?? '');
    return cm_pay_record($invoiceId, $amount, $cur, 'stripe', 'stripe:' . $ref);
}








function cm_stripe_webhook_verify(string $payload, string $sigHeader, string $secret, int $tolerance = 300, ?int $now = null): bool
{
    if ($secret === '' || $sigHeader === '') return false;
    $t = null; $v1 = [];
    foreach (explode(',', $sigHeader) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) continue;
        if ($kv[0] === 't')  $t = $kv[1];
        if ($kv[0] === 'v1') $v1[] = $kv[1];
    }
    if ($t === null || !ctype_digit($t) || !$v1) return false;
    if (abs(($now ?? time()) - (int) $t) > $tolerance) return false;
    $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
    foreach ($v1 as $sig) {
        if (hash_equals($expected, $sig)) return true;
    }
    return false;
}


function cm_stripe_webhook_handle(array $event): string
{
    $type = (string) ($event['type'] ?? '');
    if ($type !== 'checkout.session.completed' && $type !== 'checkout.session.async_payment_succeeded') {
        return 'ignored';
    }
    $session = $event['data']['object'] ?? null;
    if (!is_array($session)) return 'ignored';
    $problem = cm_stripe_session_record($session);
    return $problem === '' ? 'recorded' : $problem;
}







function cm_paypal_api(): string
{
    return (string) cm_setting('paypal.mode', 'sandbox') === 'live' ? CM_PAYPAL_API_LIVE : CM_PAYPAL_API_SANDBOX;
}

function cm_paypal_token(): array
{
    $id = trim((string) cm_setting('paypal.client_id', ''));
    $secret = trim((string) cm_setting('paypal.client_secret', ''));



    $for = hash('sha256', cm_paypal_api() . '|' . $id . '|' . $secret);
    $cached = json_decode(cm_decrypt((string) cm_setting('paypal.token_cache', '')), true);
    if (is_array($cached) && ($cached['for'] ?? '') === $for && (int) ($cached['until'] ?? 0) > time() + 60
        && !empty($cached['token'])) {
        return ['token' => (string) $cached['token']];
    }
    $r = cm_pay_http('POST', cm_paypal_api() . '/v1/oauth2/token',
                     ['Authorization: Basic ' . base64_encode($id . ':' . $secret),
                      'Content-Type: application/x-www-form-urlencoded'],
                     'grant_type=client_credentials');
    if ($r['error'] !== '') return ['error' => 'PayPal could not be reached: ' . $r['error']];
    $j = json_decode($r['body'], true);
    if ($r['status'] !== 200 || !is_array($j) || empty($j['access_token'])) return ['error' => 'PayPal refused the keys.'];
    $life = max(0, min((int) ($j['expires_in'] ?? 0), 8 * 3600));
    if ($life > 120) {
        try {
            cm_setting_set('paypal.token_cache', cm_encrypt((string) json_encode(
                ['for' => $for, 'until' => time() + $life, 'token' => (string) $j['access_token']])), 'paypal');
        } catch (Throwable $ex) {

        }
    }
    return ['token' => (string) $j['access_token']];
}



function cm_paypal_amount(int $cents, string $currency = ''): string
{
    return cm_pay_zero_decimal($currency, 'paypal')
        ? number_format(round($cents / 100), 0, '.', '')
        : number_format($cents / 100, 2, '.', '');
}


function cm_paypal_order_body(array $invoice, string $returnUrl, string $cancelUrl): string
{
    $cur = strtoupper((string) $invoice['currency']);
    return (string) json_encode([
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => (string) (int) $invoice['id'],

            'custom_id'    => (int) $invoice['id'] . '|' . (string) $invoice['number'] . '|' . cm_pay_install_mark(),
            'description'  => 'Invoice ' . (string) $invoice['number'],
            'amount'       => ['currency_code' => $cur,
                               'value' => cm_paypal_amount(cm_invoice_balance_cents($invoice), $cur)],
        ]],
        'application_context' => [
            'return_url' => $returnUrl, 'cancel_url' => $cancelUrl,
            'user_action' => 'PAY_NOW', 'shipping_preference' => 'NO_SHIPPING',
        ],
    ]);
}

function cm_paypal_order_url(array $invoice, string $returnUrl, string $cancelUrl): array
{
    $tok = cm_paypal_token();
    if (isset($tok['error'])) return $tok;
    $body = cm_paypal_order_body($invoice, $returnUrl, $cancelUrl);


    $r = cm_pay_http('POST', cm_paypal_api() . '/v2/checkout/orders',
                     ['Authorization: Bearer ' . $tok['token'], 'Content-Type: application/json',
                      'PayPal-Request-Id: cwh-' . hash('sha256', cm_pay_window() . '|' . $body)], $body);
    if ($r['error'] !== '') return ['error' => 'PayPal could not be reached: ' . $r['error']];
    $j = json_decode($r['body'], true);
    if (!in_array($r['status'], [200, 201], true) || !is_array($j)) return ['error' => 'PayPal refused to start the payment.'];
    foreach ((array) ($j['links'] ?? []) as $l) {
        if ((string) ($l['rel'] ?? '') === 'approve' && !empty($l['href'])) return ['url' => (string) $l['href'], 'id' => (string) ($j['id'] ?? '')];
    }
    return ['error' => 'PayPal gave no approval link.'];
}


function cm_paypal_order_settle(string $orderId): string
{
    if (!preg_match('/^[A-Z0-9]{8,32}$/', $orderId)) return 'That does not look like a PayPal order.';
    $tok = cm_paypal_token();
    if (isset($tok['error'])) return $tok['error'];
    $r = cm_pay_http('POST', cm_paypal_api() . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture',
                     ['Authorization: Bearer ' . $tok['token'], 'Content-Type: application/json', 'Prefer: return=representation'], '{}');
    if ($r['error'] !== '') return 'PayPal could not be reached: ' . $r['error'];
    $j = json_decode($r['body'], true);



    if ($r['status'] === 422 && is_array($j) && str_contains($r['body'], 'ORDER_ALREADY_CAPTURED')) {
        $g = cm_pay_http('GET', cm_paypal_api() . '/v2/checkout/orders/' . rawurlencode($orderId),
                         ['Authorization: Bearer ' . $tok['token']]);
        if ($g['error'] !== '') return 'PayPal could not be reached: ' . $g['error'];
        $j = json_decode($g['body'], true);
    }
    if (!is_array($j)) return 'PayPal did not recognise that payment.';
    return cm_paypal_order_record($j);
}


function cm_paypal_order_record(array $order): string
{
    if ((string) ($order['status'] ?? '') !== 'COMPLETED') return 'That payment has not completed.';
    $unit = $order['purchase_units'][0] ?? [];



    $custom = (string) ($unit['custom_id'] ?? '');
    $parts = explode('|', $custom);
    $invoiceId = (int) ($parts[0] ?? 0);
    if (count($parts) === 3) {
        $number = $parts[1];
        $mark = $parts[2] !== '' ? $parts[2] : 'missing';
    } else {
        $number = preg_match('/^Invoice (.+)$/', (string) ($unit['description'] ?? ''), $dm) ? $dm[1] : '';
        $mark = '';
    }
    $capture = $unit['payments']['captures'][0] ?? [];
    if ($invoiceId <= 0 || !$capture) return 'That payment names no invoice.';
    if (!cm_pay_checkout_is_ours($invoiceId, $number, $mark)) return 'That payment was not started by this site.';
    if ((string) ($capture['status'] ?? '') !== 'COMPLETED') return 'That payment has not completed.';
    $cur = (string) ($capture['amount']['currency_code'] ?? '');
    $cents = (int) round(((float) ($capture['amount']['value'] ?? 0)) * 100);
    return cm_pay_record($invoiceId, $cents, $cur, 'paypal', 'paypal:' . (string) ($capture['id'] ?? $order['id'] ?? ''));
}








function cm_paypal_webhook_verify(array $headers, string $payload): bool
{
    $webhookId = trim((string) cm_setting('paypal.webhook_id', ''));
    if ($webhookId === '') return false;
    $tok = cm_paypal_token();
    if (isset($tok['error'])) return false;
    $h = static fn(string $k): string => (string) ($headers[$k] ?? $headers[strtolower($k)] ?? '');
    $body = json_encode([
        'auth_algo'         => $h('PAYPAL-AUTH-ALGO'),
        'cert_url'          => $h('PAYPAL-CERT-URL'),
        'transmission_id'   => $h('PAYPAL-TRANSMISSION-ID'),
        'transmission_sig'  => $h('PAYPAL-TRANSMISSION-SIG'),
        'transmission_time' => $h('PAYPAL-TRANSMISSION-TIME'),
        'webhook_id'        => $webhookId,
        'webhook_event'     => json_decode($payload, true),
    ]);
    $r = cm_pay_http('POST', cm_paypal_api() . '/v1/notifications/verify-webhook-signature',
                     ['Authorization: Bearer ' . $tok['token'], 'Content-Type: application/json'], $body);
    $j = json_decode($r['body'], true);
    return $r['status'] === 200 && is_array($j) && (string) ($j['verification_status'] ?? '') === 'SUCCESS';
}









function cm_paypal_failure_is_ours(string $problem): bool
{
    foreach (['PayPal could not be reached', 'PayPal refused the keys', 'PayPal did not recognise', 'PayPal did not answer'] as $p) {
        if (str_starts_with($problem, $p)) return true;
    }
    return false;
}


function cm_paypal_webhook_handle(array $event): string
{
    $type = (string) ($event['event_type'] ?? '');
    if ($type !== 'CHECKOUT.ORDER.APPROVED' && $type !== 'PAYMENT.CAPTURE.COMPLETED') return 'ignored';
    if ($type === 'CHECKOUT.ORDER.APPROVED') {


        $id = (string) ($event['resource']['id'] ?? '');
        if ($id === '') return 'ignored';
        $problem = cm_paypal_order_settle($id);
        if ($problem === '') return 'recorded';
        return (cm_paypal_failure_is_ours($problem) ? 'retry: ' : 'not recorded: ') . $problem;
    }


    $capture = $event['resource'] ?? [];
    $orderId = '';
    foreach ((array) ($capture['links'] ?? []) as $l) {
        if ((string) ($l['rel'] ?? '') === 'up' && preg_match('#/v2/checkout/orders/([A-Z0-9]+)#', (string) ($l['href'] ?? ''), $m)) $orderId = $m[1];
    }
    if ($orderId === '') return 'ignored';
    $tok = cm_paypal_token();
    if (isset($tok['error'])) return 'retry: ' . $tok['error'];
    $g = cm_pay_http('GET', cm_paypal_api() . '/v2/checkout/orders/' . rawurlencode($orderId), ['Authorization: Bearer ' . $tok['token']]);
    if ($g['error'] !== '') return 'retry: PayPal could not be reached: ' . $g['error'];
    $order = json_decode($g['body'], true);
    if ($g['status'] !== 200 || !is_array($order)) return 'retry: PayPal did not answer with the order (HTTP ' . (int) $g['status'] . ')';
    $problem = cm_paypal_order_record($order);
    return $problem === '' ? 'recorded' : 'not recorded: ' . $problem;
}
