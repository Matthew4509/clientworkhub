<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0























declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }

require_once CM_LIB . '/format.php';
require_once CM_LIB . '/tax.php';

function cm_register_methods(): array
{
    return ['cash' => 'Cash', 'card' => 'Card', 'bank' => 'Bank transfer', 'other' => 'Other'];
}





















function cm_register_money_to_cents(string $raw): int
{
    $v = trim($raw);
    if ($v === '') return -1;



    $symbol = function_exists('cm_currency') ? (string) cm_currency()['symbol'] : '$';
    foreach ([$symbol, '$', '£', '€', '¥', 'A$', 'NZ$', 'US$'] as $s) {
        if ($s !== '' && str_starts_with($v, $s)) { $v = trim(substr($v, strlen($s))); break; }
    }

    $neg = false;
    if (str_starts_with($v, '-')) { $neg = true; $v = trim(substr($v, 1)); }



    if (preg_match('/^\d{1,3}(,\d{3})+(\.\d{1,2})?$/', $v)) $v = str_replace(',', '', $v);

    if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $v, $m)) return -1;

    $cents = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    return $neg ? -$cents : $cents;
}

function cm_register_sale(int $id): ?array
{
    return cm_row(
        'SELECT s.*, c.first_name, c.last_name, c.company, c.email AS client_email,
                COALESCE((SELECT SUM(r.amount_cents) FROM register_refund r
                           WHERE r.sale_id = s.id), 0) AS refunded_cents
           FROM register_sale s
           LEFT JOIN client c ON c.id = s.client_id
          WHERE s.id = ?', [$id]);
}

function cm_register_status(array $s): string
{
    $r = (int) ($s['refunded_cents'] ?? 0);
    $t = (int) $s['total_cents'];
    return $r <= 0 ? 'Paid' : ($r >= $t ? 'Refunded' : 'Partially refunded');
}


function cm_register_statuses(): array
{
    return [
        'paid'               => ['label' => 'Paid',               'having' => 'refunded_cents = 0'],
        'partially_refunded' => ['label' => 'Partially refunded',  'having' => 'refunded_cents > 0 AND refunded_cents < s.total_cents'],
        'refunded'           => ['label' => 'Refunded',            'having' => 'refunded_cents >= s.total_cents'],
    ];
}










function cm_register_create(array $in, string $actor): array
{
    $total    = cm_register_money_to_cents((string) ($in['total'] ?? ''));
    $method   = (string) ($in['payment_method'] ?? 'cash');
    $received = cm_register_money_to_cents((string) ($in['amount_received'] ?? ''));
    $client   = (int) ($in['client_id'] ?? 0);
    $desc     = mb_substr(trim((string) ($in['description'] ?? '')), 0, 255);

    if ($total === -1 && trim((string) ($in['total'] ?? '')) !== '') {
        return ['ok' => false, 'id' => 0,
                'error' => 'That amount could not be read. Enter it in numbers, like 86 or 86.50.'];
    }
    if ($total < 1) {
        return ['ok' => false, 'id' => 0, 'error' => 'Enter a sale amount greater than zero.'];
    }
    if (!isset(cm_register_methods()[$method])) {
        return ['ok' => false, 'id' => 0, 'error' => 'Choose a payment method.'];
    }
    if ($method === 'cash') {
        if ($received === -1 && trim((string) ($in['amount_received'] ?? '')) !== '') {
            return ['ok' => false, 'id' => 0,
                    'error' => 'The cash received could not be read. Enter it in numbers, like 100 or 100.00.'];
        }
        if ($received < $total) {


            return ['ok' => false, 'id' => 0,
                    'error' => 'Cash received must be at least ' . cm_money($total)
                             . '. Enter another amount, or choose a different payment method.'];
        }
    } else {
        $received = -1;
    }

    if ($client > 0 && (int) cm_val('SELECT COUNT(*) FROM client WHERE id = ?', [$client]) === 0) {
        $client = 0;
    }
    $change = $method === 'cash' ? max(0, $received - $total) : 0;

    $db = cm_db();
    $own = !$db->inTransaction();
    if ($own) $db->beginTransaction();
    try {
        $id = cm_insert(
            'INSERT INTO register_sale
                (receipt_no, client_id, description, total_cents, payment_method,
                 amount_received_cents, change_cents, created_by, created_at)
             VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$client ?: null, $desc, $total, $method,
             $received >= 0 ? $received : null, $change, $actor, cm_now()]);

        $receipt = 'R' . date('Ymd') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
        cm_q('UPDATE register_sale SET receipt_no = ? WHERE id = ?', [$receipt, $id]);





        cm_audit('register.sale', '', null, $receipt . ' ' . cm_money($total) . ' by ' . $actor);

        if ($own) $db->commit();
        return ['ok' => true, 'error' => '', 'id' => $id];
    } catch (Throwable $ex) {
        if ($own && $db->inTransaction()) $db->rollBack();
        error_log('register sale failed: ' . $ex->getMessage());
        return ['ok' => false, 'id' => 0,
                'error' => 'The sale could not be recorded and nothing was taken. '
                         . 'Try again — if it happens twice, check Update & backup for a waiting database update.'];
    }
}







function cm_register_refund(int $saleId, string $amount, string $reason, string $method, string $actor): string
{
    $cents = cm_register_money_to_cents($amount);
    if ($cents === -1) return 'That amount could not be read. Enter it in numbers, like 25 or 25.50.';
    if (trim($reason) === '') return 'Add a refund reason.';
    if (!isset(cm_register_methods()[$method])) return 'Choose a refund method.';

    $db = cm_db();
    $own = !$db->inTransaction();
    if ($own) $db->beginTransaction();
    try {
        cm_val('SELECT id FROM register_sale WHERE id = ? FOR UPDATE', [$saleId]);
        $sale = cm_register_sale($saleId);
        if (!$sale) { if ($own) $db->rollBack(); return 'Sale not found.'; }

        $remaining = (int) $sale['total_cents'] - (int) $sale['refunded_cents'];
        if ($cents < 1 || $cents > $remaining) {
            if ($own) $db->rollBack();
            return 'Refund must be greater than zero and no more than ' . cm_money($remaining) . '.';
        }
        cm_q('INSERT INTO register_refund (sale_id, amount_cents, reason, refund_method, created_by, created_at)
              VALUES (?, ?, ?, ?, ?, ?)',
             [$saleId, $cents, mb_substr(trim($reason), 0, 255), $method, $actor, cm_now()]);
        cm_audit('register.refund', '', null,
                 (string) $sale['receipt_no'] . ' refund ' . cm_money($cents) . ' by ' . $actor);
        if ($own) $db->commit();
        return '';
    } catch (Throwable $ex) {
        if ($own && $db->inTransaction()) $db->rollBack();
        error_log('register refund failed: ' . $ex->getMessage());
        return 'The refund could not be recorded and nothing was refunded. Try again.';
    }
}










function cm_register_recent(int $limit = 10, string $q = '', string $method = '', string $status = ''): array
{
    $where = ['1 = 1'];
    $args  = [];

    if ($q !== '') { $where[] = 's.receipt_no LIKE ?'; $args[] = '%' . $q . '%'; }
    if (isset(cm_register_methods()[$method])) { $where[] = 's.payment_method = ?'; $args[] = $method; }

    $statuses = cm_register_statuses();
    $having   = isset($statuses[$status]) ? ' HAVING ' . $statuses[$status]['having'] : '';

    return cm_all(
        "SELECT s.*, c.first_name, c.last_name, c.company, c.email AS client_email,
                COALESCE(SUM(r.amount_cents), 0) AS refunded_cents
           FROM register_sale s
           LEFT JOIN client c ON c.id = s.client_id
           LEFT JOIN register_refund r ON r.sale_id = s.id
          WHERE " . implode(' AND ', $where) . "
          GROUP BY s.id" . $having . "
          ORDER BY s.created_at DESC, s.id DESC
          LIMIT " . max(1, min(200, $limit)), $args);
}








function cm_register_daily_totals(string $date): array
{
    $from = $date . ' 00:00:00';
    $to   = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';

    $out = [];
    foreach (cm_register_methods() as $k => $v) $out[$k] = 0;

    foreach (cm_all('SELECT payment_method, SUM(total_cents) total
                       FROM register_sale
                      WHERE created_at >= ? AND created_at < ?
                      GROUP BY payment_method', [$from, $to]) as $r) {
        if (isset($out[(string) $r['payment_method']])) {
            $out[(string) $r['payment_method']] = (int) $r['total'];
        }
    }

    $refund = (int) cm_val('SELECT COALESCE(SUM(amount_cents), 0) FROM register_refund
                             WHERE created_at >= ? AND created_at < ?', [$from, $to]);

    return ['methods' => $out, 'refunds' => $refund,
            'gross'   => array_sum($out), 'net' => array_sum($out) - $refund];
}
