<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0





















declare(strict_types=1);

if (!defined('CM_ROOT')) { http_response_code(404); exit; }

require_once CM_LIB . '/tax.php';
require_once CM_LIB . '/format.php';






require_once CM_LIB . '/clients.php';








function cm_invoice_statuses(): array
{
    return [
        'draft' => ['label' => 'Draft',  'blurb' => 'Not issued. No number yet, and the client cannot see it.'],
        'sent'  => ['label' => 'Issued', 'blurb' => 'Numbered, frozen, and visible to the client.'],
        'paid'  => ['label' => 'Paid',   'blurb' => 'Settled in full. It prints as a receipt.'],
        'void'  => ['label' => 'Void',   'blurb' => 'Cancelled. It keeps its number so the sequence stays whole.'],
    ];
}


function cm_payment_methods(): array
{
    return [
        'transfer' => 'Bank transfer',
        'cash'     => 'Cash',
        'card'     => 'Card machine',
        'stripe'   => 'Stripe',
        'paypal'   => 'PayPal',
        'platform' => 'Another platform (Upwork, Fiverr…)',
        'other'    => 'Something else',
    ];
}









function cm_job_payments(int $jobId): array
{
    try {
        return cm_all('SELECT * FROM job_payment WHERE job_id = ? ORDER BY paid_on DESC, id DESC', [$jobId]);
    } catch (Throwable $ex) {
        return [];
    }
}

function cm_job_payment_add(int $jobId, int $clientId, int $amountCents, string $method, string $platform,
                            string $paidOn, string $reference, string $note, string $actor): string
{
    if ($amountCents <= 0)                      return 'The amount: a plain number, like 250 or 199.50.';
    if (!isset(cm_payment_methods()[$method]))  $method = 'other';
    $platform = mb_substr(trim($platform), 0, 80);
    if ($method === 'platform' && $platform === '') return 'Which platform paid you? Name it.';
    $when = preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidOn) ? $paidOn : date('Y-m-d');
    try {
        cm_insert('INSERT INTO job_payment (job_id, client_id, amount_cents, method, platform, paid_on,
                                            reference, note, recorded_by, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                  [$jobId, $clientId, $amountCents, $method, $platform, $when,
                   mb_substr(trim($reference), 0, 120), mb_substr(trim($note), 0, 255),
                   mb_substr($actor, 0, 190), cm_now()]);
    } catch (Throwable $ex) {
        return 'The database needs updating before a payment can be recorded against a job (Admin → Update).';
    }
    cm_audit('job.payment', 'client', $clientId,
             cm_money($amountCents) . ' received for job #' . $jobId . ' via '
             . (cm_payment_methods()[$method] ?? $method) . ($platform !== '' ? ' (' . $platform . ')' : '')
             . ', outside invoicing, recorded by ' . $actor);
    return '';
}

function cm_job_payment_delete(int $paymentId, int $jobId, string $actor): bool
{
    $row = cm_row('SELECT * FROM job_payment WHERE id = ? AND job_id = ?', [$paymentId, $jobId]);
    if (!$row) return false;
    cm_q('DELETE FROM job_payment WHERE id = ?', [$paymentId]);
    cm_audit('job.payment_removed', 'client', (int) $row['client_id'],
             cm_money((int) $row['amount_cents']) . ' received for job #' . $jobId . ' removed by ' . $actor);
    return true;
}






function cm_job_money(int $jobId): array
{
    $job = cm_row('SELECT amount_cents, billing_freq FROM job WHERE id = ?', [$jobId]);
    $invoiced = (int) cm_val("SELECT COALESCE(SUM(total_cents), 0) FROM invoice WHERE job_id = ? AND status IN ('sent', 'paid')", [$jobId]);
    $viaInvoice = (int) cm_val("SELECT COALESCE(SUM(p.amount_cents), 0) FROM invoice_payment p
                                 JOIN invoice i ON i.id = p.invoice_id WHERE i.job_id = ? AND i.status <> 'void'", [$jobId]);
    $elsewhere = 0;
    try { $elsewhere = (int) cm_val('SELECT COALESCE(SUM(amount_cents), 0) FROM job_payment WHERE job_id = ?', [$jobId]); }
    catch (Throwable $ex) {}
    return [
        'price'       => (int) ($job['amount_cents'] ?? 0),
        'invoiced'    => $invoiced,
        'via_invoice' => $viaInvoice,
        'elsewhere'   => $elsewhere,
        'received'    => $viaInvoice + $elsewhere,
    ];
}





function cm_money_summary(): array
{
    $elsewhere = 0;
    try { $elsewhere = (int) cm_val('SELECT COALESCE(SUM(amount_cents), 0) FROM job_payment'); }
    catch (Throwable $ex) {}
    return [
        'job_value'    => (int) cm_val("SELECT COALESCE(SUM(amount_cents), 0) FROM job WHERE status <> 'cancelled'"),
        'invoiced'     => (int) cm_val("SELECT COALESCE(SUM(total_cents), 0) FROM invoice WHERE status IN ('sent', 'paid')"),
        'via_invoice'  => (int) cm_val("SELECT COALESCE(SUM(p.amount_cents), 0) FROM invoice_payment p
                                         JOIN invoice i ON i.id = p.invoice_id WHERE i.status <> 'void'"),
        'elsewhere'    => $elsewhere,
        'outstanding'  => cm_invoice_outstanding_cents(),
    ];
}



function cm_invoice(int $id): ?array
{
    return cm_row('SELECT * FROM invoice WHERE id = ?', [$id]);
}

function cm_invoice_items(int $invoiceId): array
{
    return cm_all('SELECT * FROM invoice_item WHERE invoice_id = ? ORDER BY sort, id', [$invoiceId]);
}

function cm_invoice_payments(int $invoiceId): array
{
    return cm_all('SELECT * FROM invoice_payment WHERE invoice_id = ? ORDER BY paid_on, id', [$invoiceId]);
}







function cm_client_invoices(int $clientId, bool $visibleOnly = false): array
{
    $sql = 'SELECT * FROM invoice WHERE client_id = ?';
    if ($visibleOnly) $sql .= " AND status <> 'draft'";
    return cm_all($sql . ' ORDER BY COALESCE(issued_on, created_at) DESC, id DESC', [$clientId]);
}


function cm_invoice_outstanding_cents(): int
{
    return (int) cm_val(
        "SELECT COALESCE(SUM(total_cents - paid_cents), 0)
           FROM invoice WHERE status = 'sent'");
}





























function cm_invoice_summary(): array
{
    return [
        'created'  => (int) cm_val("SELECT COUNT(*) FROM invoice"),
        'invoiced' => (int) cm_val("SELECT COALESCE(SUM(total_cents), 0) FROM invoice
                                     WHERE status IN ('sent', 'paid')"),
        'received' => (int) cm_val("SELECT COALESCE(SUM(p.amount_cents), 0)
                                      FROM invoice_payment p
                                      JOIN invoice i ON i.id = p.invoice_id
                                     WHERE i.status <> 'void'"),
        'due'      => cm_invoice_outstanding_cents(),
    ];
}





function cm_invoice_tax(array $invoice): array
{
    if ((string) $invoice['status'] === 'draft') {
        return cm_tax_settings();
    }
    return [
        'mode'         => (string) $invoice['tax_mode'],
        'rate'         => (float) $invoice['tax_rate'],
        'name'         => (string) $invoice['tax_name'],
        'number_label' => (string) $invoice['tax_number_label'],
        'number'       => (string) $invoice['tax_number'],
        'currency'     => (string) $invoice['currency'],
    ];
}




function cm_invoice_create(int $clientId, ?int $jobId = null, ?int $bookingId = null): int
{
    return cm_insert(
        'INSERT INTO invoice (client_id, job_id, booking_id, status, currency, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$clientId, $jobId ?: null, $bookingId ?: null, 'draft', cm_currency()['code'], cm_now(), cm_now()]
    );
}







function cm_invoice_add_item(int $invoiceId, string $description, float $qty,
                             int $unitCents, bool $taxable = true, ?int $templateId = null): int
{
    $qty  = max(0.0, $qty);
    $line = (int) round($qty * $unitCents);
    $sort = (int) cm_val('SELECT COALESCE(MAX(sort), 0) + 10 FROM invoice_item WHERE invoice_id = ?',
                         [$invoiceId]);
    $id = cm_insert(
        'INSERT INTO invoice_item (invoice_id, description, qty, unit_cents, line_cents, taxable, template_id, sort)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$invoiceId, mb_substr(trim($description), 0, 400), $qty, $unitCents, $line,
         $taxable ? 1 : 0, $templateId ?: null, $sort]
    );
    cm_invoice_recalculate($invoiceId);
    return $id;
}


function cm_invoice_add_template(int $invoiceId, int $templateId): void
{
    $t = cm_row('SELECT * FROM job_template WHERE id = ?', [$templateId]);
    if (!$t) return;
    cm_invoice_add_item(
        $invoiceId,
        (string) $t['name'],
        1.0,
        (int) $t['amount_cents'],
        (int) ($t['taxable'] ?? 1) === 1,
        $templateId
    );
}






function cm_invoice_remove_item(int $itemId, int $invoiceId): bool
{
    $removed = cm_q("DELETE i FROM invoice_item i JOIN invoice v ON v.id = i.invoice_id
                      WHERE i.id = ? AND i.invoice_id = ? AND v.status = 'draft'", [$itemId, $invoiceId])->rowCount();
    if ($removed > 0) cm_invoice_recalculate($invoiceId);
    return $removed > 0;
}








function cm_invoice_recalculate(int $invoiceId): void
{
    $invoice = cm_invoice($invoiceId);
    if (!$invoice) return;

    $sum = 0; $taxable = 0;
    foreach (cm_invoice_items($invoiceId) as $it) {
        $line = (int) round((float) $it['qty'] * (int) $it['unit_cents']);
        if ($line !== (int) $it['line_cents']) {
            cm_q('UPDATE invoice_item SET line_cents = ? WHERE id = ?', [$line, (int) $it['id']]);
        }
        $sum += $line;
        if ((int) $it['taxable'] === 1) $taxable += $line;
    }

    $totals = cm_tax_totals($sum, $taxable, cm_invoice_tax($invoice));
    $paid   = (int) cm_val('SELECT COALESCE(SUM(amount_cents), 0) FROM invoice_payment WHERE invoice_id = ?',
                           [$invoiceId]);

    cm_q('UPDATE invoice SET subtotal_cents = ?, tax_cents = ?, total_cents = ?, paid_cents = ?, updated_at = ?
           WHERE id = ?',
         [$totals['subtotal'], $totals['tax'], $totals['total'], $paid, cm_now(), $invoiceId]);
}












function cm_invoice_next_number(): string
{
    $prefix = trim((string) cm_setting('invoice.number_prefix', 'INV')) ?: 'INV';
    $year   = date('Y');
    $like   = $prefix . '-' . $year . '-%';





    $last = (int) cm_val(
        "SELECT MAX(CAST(SUBSTRING_INDEX(number, '-', -1) AS UNSIGNED)) FROM invoice WHERE number LIKE ? FOR UPDATE",
        [$like]);
    return sprintf('%s-%s-%04d', $prefix, $year, $last + 1);
}









function cm_invoice_issue(int $invoiceId, string $actor = ''): string
{
    $invoice = cm_invoice($invoiceId);
    if (!$invoice)                                   return 'That invoice no longer exists.';
    if ((string) $invoice['status'] !== 'draft')     return 'That invoice has already been issued.';
    $items = cm_invoice_items($invoiceId);
    if (!$items)                                     return 'An invoice with no lines on it cannot be issued.';
    if (array_sum(array_map(static fn ($i) => (int) $i['line_cents'], $items)) <= 0) {
        return 'This invoice comes to nothing, so there is nothing to ask the client to pay. Add a price to a line, or delete the draft.';
    }

    $client = cm_row('SELECT * FROM client WHERE id = ?', [(int) $invoice['client_id']]);
    if (!$client)                                    return 'That client no longer exists.';

    $tax  = cm_tax_settings();
    $days = (int) cm_setting('invoice.terms_days', '14');
    $due  = $days > 0 ? date('Y-m-d', strtotime('+' . $days . ' days')) : date('Y-m-d');

    $bizName = trim((string) cm_setting('business.legal_name', ''))
            ?: trim((string) cm_setting('public.business_name', ''));

    $address = static function (array $r, string $p = ''): string {
        $bits = array_filter([
            trim((string) ($r[$p . 'address'] ?? '')),
            trim((string) ($r[$p . 'suburb'] ?? '')),
            trim(trim((string) ($r[$p . 'state'] ?? '')) . ' ' . trim((string) ($r[$p . 'postcode'] ?? ''))),
        ], static fn ($s) => $s !== '');
        return implode(', ', $bits);
    };

    $bizAddress = array_filter([
        trim((string) cm_setting('business.address', '')),
        trim((string) cm_setting('business.suburb', '')),
        trim(trim((string) cm_setting('business.state', '')) . ' '
             . trim((string) cm_setting('business.postcode', ''))),
        trim((string) cm_setting('business.country', '')),
    ], static fn ($s) => $s !== '');

    $bizContact = array_filter([
        trim((string) cm_setting('public.email', '')),
        trim((string) cm_setting('public.phone', '')),
    ], static fn ($s) => $s !== '');



    $bankName = trim((string) cm_setting('bank.account_name', ''));
    $bankNo   = trim((string) cm_setting('bank.account_no', ''));
    $pay      = '';
    if ($bankName !== '' && $bankNo !== '') {
        $codeLabel = trim((string) cm_setting('bank.code_label', ''));
        $code      = trim((string) cm_setting('bank.code', ''));
        $pay = $bankName . ($code !== '' ? ', ' . trim($codeLabel . ' ' . $code) : '') . ', Account ' . $bankNo;
    }




    $db = cm_db();
    $own = !$db->inTransaction();
    if ($own) $db->beginTransaction();
    try {
    $still = (string) cm_val('SELECT status FROM invoice WHERE id = ? FOR UPDATE', [$invoiceId]);
    if ($still !== 'draft') {
        if ($own) $db->rollBack();
        return 'That invoice has already been issued.';
    }
    $number = cm_invoice_next_number();

    cm_q('UPDATE invoice SET number = ?, status = ?, issued_on = ?, due_on = ?,
                             tax_mode = ?, tax_rate = ?, tax_name = ?,
                             tax_number_label = ?, tax_number = ?, currency = ?,
                             biz_name = ?, biz_address = ?, biz_contact = ?,
                             bill_to_name = ?, bill_to_address = ?, bill_to_email = ?,
                             pay_details = ?, footer = ?, issued_by = ?, updated_at = ?
           WHERE id = ?',
         [$number, 'sent', date('Y-m-d'), $due,
          $tax['mode'], $tax['rate'], $tax['name'],
          $tax['number_label'], $tax['number'], $tax['currency'],
          mb_substr($bizName, 0, 200), mb_substr(implode(', ', $bizAddress), 0, 400),
          mb_substr(implode(' · ', $bizContact), 0, 200),
          mb_substr(cm_client_display_name($client), 0, 200),
          mb_substr($address($client), 0, 400),
          mb_substr((string) ($client['email'] ?? ''), 0, 190),
          mb_substr($pay, 0, 400),
          mb_substr(trim((string) cm_setting('invoice.footer', '')), 0, 400),
          mb_substr($actor, 0, 190), cm_now(), $invoiceId]);
    if ($own) $db->commit();
    } catch (Throwable $ex) {
        if ($own && $db->inTransaction()) $db->rollBack();
        throw $ex;
    }




    cm_invoice_recalculate($invoiceId);
    cm_audit('invoice.issued', 'client', (int) $invoice['client_id'],
             'invoice ' . $number . ' issued by ' . $actor);
    return '';
}








function cm_invoice_add_payment(int $invoiceId, int $amountCents, string $method,
                                string $paidOn, string $reference = '', string $actor = ''): string
{
    $invoice = cm_invoice($invoiceId);
    if (!$invoice)                                return 'That invoice no longer exists.';
    if ((string) $invoice['status'] === 'draft')  return 'Issue the invoice before recording a payment against it.';
    if ((string) $invoice['status'] === 'void')   return 'That invoice is void.';
    if ($amountCents === 0)                       return 'A payment of nothing is not a payment.';
    if (!isset(cm_payment_methods()[$method]))    $method = 'other';

    $when = preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidOn) ? $paidOn : date('Y-m-d');






    $byHand = !str_ends_with($actor, '(online)');
    $db = cm_db();
    $own = !$db->inTransaction();
    if ($own) $db->beginTransaction();
    try {
        cm_val('SELECT id FROM invoice WHERE id = ? FOR UPDATE', [$invoiceId]);
        if ($byHand) {
            $twin = (int) cm_val('SELECT COUNT(*) FROM invoice_payment WHERE invoice_id = ? AND amount_cents = ?
                                   AND method = ? AND paid_on = ? AND reference = ? AND created_at >= ?',
                                 [$invoiceId, $amountCents, $method, $when, mb_substr($reference, 0, 190),
                                  (new DateTimeImmutable('now'))->modify('-60 seconds')->format('Y-m-d H:i:s')]);
            if ($twin > 0) {
                if ($own) $db->commit();
                return 'That payment was recorded a moment ago, so it was not recorded again. '
                     . 'If it really is a second payment of the same amount, give it its own reference.';
            }
        }
        cm_insert(
            'INSERT INTO invoice_payment (invoice_id, method, amount_cents, paid_on, reference, recorded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$invoiceId, $method, $amountCents, $when, mb_substr($reference, 0, 190),
             mb_substr($actor, 0, 190), cm_now()]);
        if ($own) $db->commit();
    } catch (Throwable $ex) {
        if ($own && $db->inTransaction()) $db->rollBack();
        throw $ex;
    }

    cm_invoice_recalculate($invoiceId);

    $after = cm_invoice($invoiceId);
    $justPaid = false;
    if ($after && (int) $after['paid_cents'] >= (int) $after['total_cents']
        && (string) $after['status'] === 'sent') {
        cm_q("UPDATE invoice SET status = 'paid', updated_at = ? WHERE id = ?", [cm_now(), $invoiceId]);
        $justPaid = true;
    }
















    if ($justPaid) {
        try {
            require_once CM_LIB . '/onboarding.php';
            cm_onboard_on_payment($invoiceId);
        } catch (Throwable $ex) {
            cm_audit('onboarding.failed', 'client', (int) $invoice['client_id'],
                     'payment of ' . (string) ($invoice['number'] ?? '') . ' was recorded, but the '
                   . 'onboarding form it should have sent did not go: ' . $ex->getMessage());
        }
    }
    cm_audit('invoice.payment', 'client', (int) $invoice['client_id'],
             cm_money($amountCents) . ' recorded against ' . (string) $invoice['number'] . ' by ' . $actor);
    return '';
}











function cm_invoice_add_refund(int $invoiceId, int $amountCents, string $method, string $paidOn,
                               string $reason, string $actor = ''): string
{
    $reason = trim($reason);
    if ($amountCents <= 0) return 'The amount: a plain number, like 250 or 199.50.';
    if ($reason === '')    return 'Say why the money went back (for example "refunded by bank transfer" or "recorded twice").';
    if (!isset(cm_payment_methods()[$method])) $method = 'other';
    $when = preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidOn) ? $paidOn : date('Y-m-d');

    $db = cm_db();
    $own = !$db->inTransaction();
    if ($own) $db->beginTransaction();
    try {
        cm_val('SELECT id FROM invoice WHERE id = ? FOR UPDATE', [$invoiceId]);
        $invoice = cm_invoice($invoiceId);
        if (!$invoice) {
            if ($own) $db->rollBack();
            return 'That invoice no longer exists.';
        }
        $paid = (int) cm_val('SELECT COALESCE(SUM(amount_cents), 0) FROM invoice_payment WHERE invoice_id = ?', [$invoiceId]);
        if ($amountCents > $paid) {
            if ($own) $db->rollBack();
            return 'Only ' . cm_invoice_money($invoice, max(0, $paid)) . ' has been recorded against this invoice, '
                 . 'so no more than that can go back.';
        }
        cm_insert(
            'INSERT INTO invoice_payment (invoice_id, method, amount_cents, paid_on, reference, recorded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$invoiceId, $method, -$amountCents, $when, mb_substr('Refund: ' . $reason, 0, 190),
             mb_substr($actor, 0, 190), cm_now()]);
        cm_invoice_recalculate($invoiceId);
        $after = cm_invoice($invoiceId);
        if ($after && (string) $after['status'] === 'paid' && (int) $after['paid_cents'] < (int) $after['total_cents']) {
            cm_q("UPDATE invoice SET status = 'sent', updated_at = ? WHERE id = ?", [cm_now(), $invoiceId]);
        }
        if ($own) $db->commit();
    } catch (Throwable $ex) {
        if ($own && $db->inTransaction()) $db->rollBack();
        throw $ex;
    }
    cm_audit('invoice.refund', 'client', (int) $invoice['client_id'],
             cm_invoice_money($invoice, $amountCents) . ' refunded on ' . (string) $invoice['number']
             . ' (' . $reason . ') by ' . $actor);
    return '';
}


function cm_invoice_void(int $invoiceId, string $actor = ''): string
{
    $invoice = cm_invoice($invoiceId);
    if (!$invoice)                                  return 'That invoice no longer exists.';
    $refused = 'Money has been recorded against this one. Record the refund on the invoice first (Payments, Record a refund), then void it.';
    if ((int) $invoice['paid_cents'] > 0)           return $refused;


    $st = cm_q("UPDATE invoice SET status = 'void', updated_at = ? WHERE id = ? AND paid_cents = 0", [cm_now(), $invoiceId]);
    if ($st->rowCount() === 0 && (int) cm_val('SELECT paid_cents FROM invoice WHERE id = ?', [$invoiceId]) > 0) {
        return $refused;
    }
    cm_audit('invoice.voided', 'client', (int) $invoice['client_id'],
             'invoice ' . (string) ($invoice['number'] ?? 'draft') . ' voided by ' . $actor);
    return '';
}


function cm_invoice_balance_cents(array $invoice): int
{
    return (int) $invoice['total_cents'] - (int) $invoice['paid_cents'];
}







function cm_invoice_money(array $invoice, int $cents): string
{
    $all    = cm_currencies();
    $code   = (string) $invoice['currency'];
    $symbol = $all[$code]['symbol'] ?? '$';


    return cm_money_format($symbol, $cents);
}
