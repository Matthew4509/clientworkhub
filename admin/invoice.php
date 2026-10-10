<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0













declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/invoices.php';
require_once CM_LIB . '/invoicepdf.php';
require_once CM_LIB . '/emails.php';
require_once CM_LIB . '/publicsite.php';
require_once CM_LIB . '/unsubscribe.php';
require_once CM_LIB . '/payments.php';

$admin = cm_require_admin();
$id    = (int) ($_GET['id'] ?? $_POST['invoice_id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $action  = (string) ($_POST['action'] ?? '');
    $invoice = cm_invoice($id);
    if (!$invoice) { cm_flash('That invoice no longer exists.', 'error'); cm_redirect('admin/invoices.php'); }

    $isDraft = (string) $invoice['status'] === 'draft';

    if ($action === 'add_item' && $isDraft) {
        $desc = trim((string) ($_POST['description'] ?? ''));
        $amt  = trim((string) ($_POST['unit'] ?? ''));
        $qty  = trim((string) ($_POST['qty'] ?? '1'));
        if ($desc === '') {
            cm_flash('The line needs a description.', 'error');
        } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $amt)) {
            cm_flash('The amount: a plain number, like 250 or 199.50.', 'error');
        } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $qty) || (float) $qty <= 0) {
            cm_flash('The quantity: a number greater than nothing.', 'error');
        } else {
            cm_invoice_add_item($id, $desc, (float) $qty, (int) round((float) $amt * 100),
                                !empty($_POST['taxable']));
            cm_flash('Line added.');
        }
        cm_redirect('admin/invoice.php?id=' . $id);
    }

    if ($action === 'add_service' && $isDraft) {
        cm_invoice_add_template($id, (int) ($_POST['template_id'] ?? 0));
        cm_flash('Service added — edit the amount by removing and re-adding if it differs this time.');
        cm_redirect('admin/invoice.php?id=' . $id);
    }

    if ($action === 'remove_item' && $isDraft) {
        cm_flash(cm_invoice_remove_item((int) ($_POST['item_id'] ?? 0), $id)
            ? 'Line removed.' : 'That line is not on this draft, so nothing was removed.');
        cm_redirect('admin/invoice.php?id=' . $id);
    }

    if ($action === 'save_notes' && $isDraft) {
        cm_q('UPDATE invoice SET notes = ?, updated_at = ? WHERE id = ?',
             [mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 2000), cm_now(), $id]);
        cm_flash('Note saved.');
        cm_redirect('admin/invoice.php?id=' . $id);
    }

    if ($action === 'issue') {
        $problem = cm_invoice_issue($id, (string) $admin['email']);
        cm_flash($problem !== '' ? $problem : 'Issued. It is numbered and frozen, and the client can see it.',
                 $problem !== '' ? 'error' : 'ok');
        cm_redirect('admin/invoice.php?id=' . $id);
    }

    if ($action === 'add_payment') {
        $amt = trim((string) ($_POST['amount'] ?? ''));
        if (!cm_once_spend()) {
            cm_flash('That payment form was already sent, so nothing more was recorded. Check the payments below.', 'warn');
        } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $amt)) {
            cm_flash('The amount: a plain number, like 250 or 199.50.', 'error');
        } else {
            $problem = cm_invoice_add_payment(
                $id, (int) round((float) $amt * 100),
                (string) ($_POST['method'] ?? 'transfer'),
                (string) ($_POST['paid_on'] ?? date('Y-m-d')),
                trim((string) ($_POST['reference'] ?? '')),
                (string) $admin['email']);
            cm_flash($problem !== '' ? $problem : 'Payment recorded.', $problem !== '' ? 'error' : 'ok');
        }
        cm_redirect('admin/invoice.php?id=' . $id);
    }



    if ($action === 'add_refund') {
        $amt = trim((string) ($_POST['amount'] ?? ''));
        if (!cm_once_spend()) {
            cm_flash('That refund form was already sent, so nothing more was recorded. Check the payments below.', 'warn');
        } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $amt)) {
            cm_flash('The amount: a plain number, like 250 or 199.50.', 'error');
        } else {
            $problem = cm_invoice_add_refund(
                $id, (int) round((float) $amt * 100),
                (string) ($_POST['method'] ?? 'transfer'),
                (string) ($_POST['paid_on'] ?? date('Y-m-d')),
                (string) ($_POST['reason'] ?? ''),
                (string) $admin['email']);
            cm_flash($problem !== '' ? $problem : 'Refund recorded.', $problem !== '' ? 'error' : 'ok');
        }
        cm_redirect('admin/invoice.php?id=' . $id);
    }








    if ($action === 'email') {
        $to   = strtolower(trim((string) ($_POST['to'] ?? '')));
        $subj = trim((string) ($_POST['subject'] ?? ''));
        $body = (string) ($_POST['body'] ?? '');
        if ($isDraft) {
            cm_flash('Issue the invoice first — a draft has no number to send.', 'error');
        } elseif (!cm_email_valid($to)) {
            cm_flash('That does not look like an email address.', 'error');
        } elseif ($subj === '' || trim($body) === '') {
            cm_flash('The message needs a subject and a body.', 'error');
        } else {
            $ok = cm_mail($to, $subj, $body, ['kind' => (string) $invoice['status'] === 'paid' ? 'invoice_paid' : 'invoice_issued',
                                              'client_id' => (int) $invoice['client_id']]);
            if (!$ok && cm_client_is_unsubscribed((int) $invoice['client_id'])) {
                cm_flash('Not sent — this client has unsubscribed from all emails. They have to call and '
                       . 'confirm before you can turn it back on.', 'error');
            } elseif ($ok) {
                cm_audit('invoice.emailed', 'client', (int) $invoice['client_id'],
                         (string) $invoice['number'] . ' emailed to ' . $to . ' by ' . (string) $admin['email']
                         . (cm_mail_can_deliver() ? '' : ' (spooled — email is off)'));
                cm_flash(cm_mail_can_deliver()
                    ? 'Sent to ' . $to . '.'
                    : 'Saved on the server, not sent: email sending is off, so nothing has gone to the client. Turn it on under Settings, Email.');
            } else {
                cm_flash('The email could not be sent. Check Settings → Email.', 'error');
            }
        }
        cm_redirect('admin/invoice.php?id=' . $id);
    }

    if ($action === 'void') {
        $problem = cm_invoice_void($id, (string) $admin['email']);
        cm_flash($problem !== '' ? $problem : 'Voided. It keeps its number.',
                 $problem !== '' ? 'error' : 'ok');
        cm_redirect('admin/invoice.php?id=' . $id);
    }

    cm_redirect('admin/invoice.php?id=' . $id);
}

$invoice = cm_invoice($id);
if (!$invoice) { http_response_code(404); cm_flash('That invoice no longer exists.', 'error'); cm_redirect('admin/invoices.php'); }

$client   = cm_row('SELECT * FROM client WHERE id = ?', [(int) $invoice['client_id']]);
$items    = cm_invoice_items($id);
$payments = cm_invoice_payments($id);
$tax      = cm_invoice_tax($invoice);
$isDraft  = (string) $invoice['status'] === 'draft';
$isVoid   = (string) $invoice['status'] === 'void';
$isPaid   = (string) $invoice['status'] === 'paid';
$balance  = cm_invoice_balance_cents($invoice);
$money    = static fn (int $c): string => cm_invoice_money($invoice, $c);
$statuses = cm_invoice_statuses();



$payNow = (string) $invoice['pay_details'];
if ($isDraft) {
    $bn = trim((string) cm_setting('bank.account_name', ''));
    $ba = trim((string) cm_setting('bank.account_no', ''));
    if ($bn !== '' && $ba !== '') {
        $bc = trim((string) cm_setting('bank.code', ''));
        $bl = trim((string) cm_setting('bank.code_label', ''));
        $payNow = $bn . ($bc !== '' ? ', ' . trim($bl . ' ' . $bc) : '') . ', Account ' . $ba;
    }
}
$job = !empty($invoice['job_id'])
    ? cm_row('SELECT id, name FROM job WHERE id = ?', [(int) $invoice['job_id']]) : null;

cm_head($isDraft ? 'Draft invoice' : 'Invoice ' . (string) $invoice['number'], '', true);
cm_render_flashes();
cm_admin_nav('invoices');






echo cm_crumbs_html([
    ['label' => 'Clients', 'href' => cm_url('admin/clients.php')],
    ['label' => cm_client_display_name($client),
     'href'  => cm_url('admin/client.php?id=' . (int) $client['id'])],
    ['label' => $isDraft ? 'Draft invoice'
                         : ($isPaid ? 'Receipt ' : 'Invoice ') . (string) $invoice['number']],
]);

?>
<?php 












 ?>
<div class="card-head">
  <h1><?= $isDraft ? 'Draft invoice' : ($isPaid ? 'Receipt ' : 'Invoice ') . e((string) $invoice['number']) ?></h1>
  <span class="pill <?= $isPaid ? 'pill-approved' : ($isVoid ? 'pill-spam' : ($isDraft ? 'pill-pending' : 'pill-live')) ?>"><?=
    e($statuses[(string) $invoice['status']]['label']) ?></span>
  <span class="spacer"></span>
  <a class="btn btn-quiet" href="<?= e(cm_url('admin/invoice-print.php?id=' . $id)) ?>">
    <?= $isPaid ? 'View / print the receipt' : 'View / print' ?></a>
  <button class="btn btn-quiet" type="button" data-invoice-pdf>Download PDF</button>
  <?php if (!$isDraft && !$isVoid): ?>
    <button class="btn" type="button" data-modal-open="dlg-invoice-email"><?= $isPaid ? 'Email the receipt' : 'Email the invoice' ?></button>
  <?php endif; ?>
  <?php if ($isDraft): ?>
    <form method="post" class="inline">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="issue">
      <input type="hidden" name="invoice_id" value="<?= $id ?>">
      <button class="btn" type="submit" <?= $items ? '' : 'disabled' ?>>Issue this invoice</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($isDraft && !$items): ?>
  <p class="sub">Add what you are charging for, then issue it. Issuing gives it
  a number and <b>freezes</b> the tax rate, your business details and the
  client's address onto the invoice &mdash; which is what lets you change your
  rate next year without rewriting this document.</p>
<?php endif; ?>

<div class="inv-grid">

  <?php  ?>
  <aside class="inv-rail">
    <section class="card">
      <h2 class="inv-h">Client</h2>
      <p class="inv-name">
        <a href="<?= e(cm_url('admin/client.php?id=' . (int) $invoice['client_id'])) ?>"><?=
          e($isDraft ? ($client ? cm_client_display_name($client) : 'Unknown') : (string) $invoice['bill_to_name']) ?></a>
      </p>
      <?php
        $addr = $isDraft && $client
          ? implode(', ', array_filter([
              trim((string) $client['address']), trim((string) $client['suburb']),
              trim(trim((string) $client['state']) . ' ' . trim((string) $client['postcode']))],
              static fn ($s) => $s !== ''))
          : (string) $invoice['bill_to_address'];
        $mail = $isDraft && $client ? (string) $client['email'] : (string) $invoice['bill_to_email'];
      ?>
      <?php if ($addr !== ''): ?><p class="inv-line"><?= e($addr) ?></p><?php endif; ?>
      <?php if ($mail !== ''): ?><p class="inv-line"><?= e($mail) ?></p><?php endif; ?>
      <?php if ($isDraft && $client && trim((string) $client['phone']) !== ''): ?>
        <p class="inv-line"><?= e((string) $client['phone']) ?></p>
      <?php endif; ?>
      <?php if ($isDraft && $addr === ''): ?>
        <p class="inv-line muted"><a href="<?= e(cm_url('admin/client.php?id=' . (int) $invoice['client_id'])) ?>">Add
        an address</a> &mdash; it prints on the invoice.</p>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2 class="inv-h">Details</h2>
      <?php 
 ?>
      <?php $rails = cm_pay_providers(); ?>
      <p class="small <?= $rails ? '' : 'muted' ?> mb-8">
        <?php if ($rails): ?>
          <b>Online:</b> <?= e(implode(' and ', array_map(static fn(array $r): string => $r['via'], $rails))) ?>
          &mdash; buttons on the client's copy<?= cm_public_enabled() ? '' : ' once the client portal is on' ?>.
        <?php else: ?>
          No online payment set up &mdash; <a href="<?= e(cm_url('admin/billing-settings.php')) ?>">Settings → Invoicing → Online payment</a>.
        <?php endif; ?>
      </p>

      <dl class="inv-dl">
        <dt>Invoice date</dt>
        <dd><?= $invoice['issued_on'] ? e(date('j M Y', strtotime((string) $invoice['issued_on'])))
              : '<span class="muted">on issue</span>' ?></dd>
        <dt>Due date</dt>
        <dd><?= $invoice['due_on'] ? e(date('j M Y', strtotime((string) $invoice['due_on'])))
              : '<span class="muted">' . e((string) cm_setting('invoice.terms_days', '14')) . ' days after issue</span>' ?></dd>
        <?php if (!$isDraft): ?>
          <dt>Currency</dt><dd class="mono"><?= e((string) $invoice['currency']) ?></dd>
        <?php endif; ?>
        <?php if ($job): ?>
          <dt>For the job</dt>
          <dd><a href="<?= e(cm_url('admin/job.php?id=' . (int) $job['id'])) ?>"><?= e((string) $job['name']) ?></a></dd>
        <?php endif; ?>
      </dl>
    </section>

    <?php if ($payNow !== '' && !$isPaid && !$isVoid): ?>
      <section class="card">
        <h2 class="inv-h">Payment details</h2>
        <p class="inv-line"><?= e($payNow) ?></p>
        <?php if ($isDraft): ?>
          <p class="inv-line muted">Copied onto the invoice when you issue it.</p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="card">
      <h2 class="inv-h">Notes</h2>
      <?php if ($isDraft): ?>
        <form method="post">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="save_notes">
          <input type="hidden" name="invoice_id" value="<?= $id ?>">
          <textarea name="notes" rows="3" class="f-full"
                    placeholder="Anything that should print at the foot of this one"><?= e((string) ($invoice['notes'] ?? '')) ?></textarea>
          <button class="btn btn-sm btn-quiet mt-8" type="submit">Save the note</button>
        </form>
      <?php elseif (trim((string) ($invoice['notes'] ?? '')) !== ''): ?>
        <p class="inv-line prewrap"><?= e((string) $invoice['notes']) ?></p>
      <?php else: ?>
        <p class="inv-line muted">None. Notes can only be changed while it is a draft.</p>
      <?php endif; ?>
    </section>
  </aside>

  <?php  ?>
  <div class="inv-main">
    <section class="card">
      <div class="card-head">
        <h2>What is on it</h2>
        <span class="spacer"></span>
        <?php if ($isDraft): ?>
          <button class="btn" type="button" data-modal-open="dlg-inv-line">Add a line</button>
        <?php endif; ?>
      </div>

      <?php if (!$items): ?>
        <p class="muted small">Nothing on it yet.<?= $isDraft ? ' Add what you are charging for.' : '' ?></p>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit</th><th class="num">Amount</th><?php if ($isDraft): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <tr>
                  <td><?= e((string) $it['description']) ?>
                    <?php if (cm_tax_charges($tax) && (int) $it['taxable'] === 0): ?>
                      <span class="pill">no <?= e($tax['name']) ?></span>
                    <?php endif; ?></td>
                  <td class="num mono"><?= e(rtrim(rtrim(number_format((float) $it['qty'], 2), '0'), '.')) ?></td>
                  <td class="num mono"><?= e($money((int) $it['unit_cents'])) ?></td>
                  <td class="num mono"><?= e($money((int) $it['line_cents'])) ?></td>
                  <?php if ($isDraft): ?>
                    <td class="num">
                      <form method="post" class="inline">
                        <?= cm_csrf_field() ?>
                        <input type="hidden" name="action" value="remove_item">
                        <input type="hidden" name="invoice_id" value="<?= $id ?>">
                        <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
                        <button class="btn btn-sm btn-quiet" type="submit">&times;</button>
                      </form>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php 

 ?>
      <dl class="inv-tot">
        <?php if (cm_tax_charges($tax)): ?>
          <dt>Subtotal</dt><dd class="mono"><?= e($money((int) $invoice['subtotal_cents'])) ?></dd>
          <dt><?= e($tax['name']) ?>
            <span class="muted"><?= e(rtrim(rtrim(number_format((float) $tax['rate'], 2, '.', ''), '0'), '.')) ?>%<?=
              $tax['mode'] === 'inclusive' ? ' included' : '' ?></span></dt>
          <dd class="mono"><?= e($money((int) $invoice['tax_cents'])) ?></dd>
        <?php endif; ?>
        <dt class="inv-tot-b">Total</dt><dd class="mono inv-tot-b"><?= e($money((int) $invoice['total_cents'])) ?></dd>
        <?php if ((int) $invoice['paid_cents'] > 0): ?>
          <dt>Paid</dt><dd class="mono">&minus;<?= e($money((int) $invoice['paid_cents'])) ?></dd>
        <?php endif; ?>
        <?php if (!$isDraft): ?>
          <dt class="inv-tot-due"><?= $balance <= 0 ? 'Settled' : 'Amount due' ?></dt>
          <dd class="mono inv-tot-due"><?= e($money(max(0, $balance))) ?></dd>
        <?php endif; ?>
      </dl>
    </section>

    <?php if (!$isDraft && !$isVoid): ?>
      <section class="card">
        <div class="card-head">
          <h2>Payments</h2>
          <span class="spacer"></span>
          <?php if ((int) $invoice['paid_cents'] > 0): ?>
            <button class="btn btn-quiet" type="button" data-modal-open="dlg-inv-refund">Record a refund</button>
          <?php endif; ?>
          <button class="btn" type="button" data-modal-open="dlg-inv-pay">Record a payment</button>
        </div>
        <?php if (!$payments): ?>
          <p class="muted small">Nothing recorded yet.</p>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead><tr><th>When</th><th>How</th><th>Reference</th><th class="num">Amount</th></tr></thead>
              <tbody>
                <?php foreach ($payments as $p): ?>
                  <tr>
                    <td class="small"><?= e(date('j M Y', strtotime((string) $p['paid_on']))) ?></td>
                    <td class="small"><?= e(cm_payment_methods()[(string) $p['method']] ?? 'Other') ?></td>
                    <td class="small"><?= e((string) $p['reference']) ?></td>
                    <td class="num mono"><?= e($money((int) $p['amount_cents'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if (!$isVoid && (int) $invoice['paid_cents'] === 0): ?>
      <section class="card">
        <div class="card-head">
          <h2>Cancel it</h2>
          <span class="spacer"></span>
          <form method="post" class="inline" data-confirm="Void this invoice? It keeps its number and stays on the record.">
            <?= cm_csrf_field() ?>
            <input type="hidden" name="action" value="void">
            <input type="hidden" name="invoice_id" value="<?= $id ?>">
            <button class="btn btn-quiet" type="submit">Void</button>
          </form>
        </div>
        <p class="muted small">A void invoice <b>keeps its number</b> so the
        sequence stays whole &mdash; a missing number is the first thing an
        accountant asks about. Nothing is ever deleted.</p>
      </section>
    <?php endif; ?>
  </div>
</div>

<?php if ($isDraft): ?>
  <dialog class="modal" id="dlg-inv-line">
    <div class="modal-box">
      <div class="modal-head">
        <h2>Add a line</h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <?php $services = cm_all('SELECT id, name, amount_cents FROM job_template WHERE amount_cents > 0 ORDER BY name'); ?>
      <?php if ($services): ?>
        <p class="modal-sub">One of your services, or type it yourself.</p>
        <div class="mb-12">
          <?php foreach ($services as $s): ?>
            <form method="post" class="inline">
              <?= cm_csrf_field() ?>
              <input type="hidden" name="action" value="add_service">
              <input type="hidden" name="invoice_id" value="<?= $id ?>">
              <input type="hidden" name="template_id" value="<?= (int) $s['id'] ?>">
              <button class="btn btn-sm btn-quiet" type="submit"><?= e((string) $s['name']) ?>
                &middot; <?= e(cm_money((int) $s['amount_cents'])) ?></button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="add_item">
        <input type="hidden" name="invoice_id" value="<?= $id ?>">
        <div class="row">
          <label class="lbl" for="description">What it is</label>
          <input type="text" id="description" name="description" class="f-full" maxlength="400" required
                 placeholder="Monthly care plan — August">
        </div>
        <div class="row">
          <label class="lbl" for="qty">Quantity</label>
          <input class="f-sm" type="text" id="qty" name="qty" inputmode="decimal" value="1">
          <label class="lbl" for="unit">Each</label>
          <input class="f-sm" type="text" id="unit" name="unit" inputmode="decimal" placeholder="250" required>
          <?php $note = cm_tax_price_note($tax); ?>
          <?php if ($note !== ''): ?><div class="hint"><b><?= e($note) ?></b></div><?php endif; ?>
        </div>
        <?php if (cm_tax_charges($tax)): ?>
          <label class="tick mb-12">
            <input type="checkbox" name="taxable" value="1" checked>
            <span><?= e($tax['name']) ?> applies to this line</span>
          </label>
        <?php endif; ?>
        <div class="modal-foot">
          <button class="btn" type="submit">Add the line</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>
<?php endif; ?>

<?php if (!$isDraft && !$isVoid): ?>
  <dialog class="modal" id="dlg-inv-pay">
    <div class="modal-box">
      <div class="modal-head">
        <h2>Record a payment</h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">Money that has actually arrived. Paying it in full
      turns this invoice into a receipt.</p>
      <form method="post" data-saving>
        <?= cm_csrf_field() ?>
        <?= cm_once_field() ?>
        <input type="hidden" name="action" value="add_payment">
        <input type="hidden" name="invoice_id" value="<?= $id ?>">
        <div class="row">
          <label class="lbl" for="amount">Amount</label>
          <input class="f-md" type="text" id="amount" name="amount" inputmode="decimal" required
                 value="<?= $balance > 0 ? e(number_format($balance / 100, 2, '.', '')) : '' ?>">
        </div>
        <div class="row">
          <label class="lbl" for="method">How it arrived</label>
          <select class="f-full" id="method" name="method">
            <?php foreach (cm_payment_methods() as $k => $label): ?>
              <option value="<?= e($k) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row">
          <label class="lbl" for="paid_on">When</label>
          <input class="f-auto" type="date" id="paid_on" name="paid_on" value="<?= e(date('Y-m-d')) ?>">
        </div>
        <div class="row">
          <label class="lbl" for="reference">Reference</label>
          <input type="text" id="reference" name="reference" class="f-full" maxlength="190"
                 placeholder="The transfer reference, or the receipt number">
        </div>
        <div class="modal-foot">
          <button class="btn" type="submit">Record it</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>
<?php endif; ?>

<?php if (!$isDraft && (int) $invoice['paid_cents'] > 0): ?>
  <dialog class="modal" id="dlg-inv-refund">
    <div class="modal-box">
      <div class="modal-head">
        <h2>Record a refund</h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">Money that went back to the client, or a payment
      recorded by mistake. It is added as its own line; the payment it answers
      stays on the record. Up to <?= e($money((int) $invoice['paid_cents'])) ?>.</p>
      <form method="post" data-saving>
        <?= cm_csrf_field() ?>
        <?= cm_once_field() ?>
        <input type="hidden" name="action" value="add_refund">
        <input type="hidden" name="invoice_id" value="<?= $id ?>">
        <div class="row">
          <label class="lbl" for="rf-amount">Amount</label>
          <input class="f-md" type="text" id="rf-amount" name="amount" inputmode="decimal" required>
        </div>
        <div class="row">
          <label class="lbl" for="rf-reason">Why</label>
          <input type="text" id="rf-reason" name="reason" class="f-full" maxlength="160" required
                 placeholder="Refunded by bank transfer, or: recorded twice">
        </div>
        <div class="row">
          <label class="lbl" for="rf-method">How it went back</label>
          <select class="f-full" id="rf-method" name="method">
            <?php foreach (cm_payment_methods() as $k => $label): ?>
              <option value="<?= e($k) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row">
          <label class="lbl" for="rf-on">When</label>
          <input class="f-auto" type="date" id="rf-on" name="paid_on" value="<?= e(date('Y-m-d')) ?>">
        </div>
        <div class="modal-foot">
          <button class="btn" type="submit">Record the refund</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>
<?php endif; ?>

<?php  ?>
<script type="application/json" id="invoice-pdf-data"><?= json_encode(cm_invoice_pdf_data($invoice, $client ? cm_client_display_name($client) : null), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?></script>
<script defer src="<?= e(cm_url('js/jspdf.umd.min.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>
<script defer src="<?= e(cm_url('js/invoice-pdf.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>

<?php if (!$isDraft && !$isVoid && $client): ?>
<?php 


      $unsub = cm_unsub_state((int) $client['id']);
      $tplKey = $isPaid ? 'invoice_paid' : 'invoice_issued';
      $tpl = cm_email_template($tplKey);
      $ctx = [
          'client_name'       => cm_client_display_name($client),
          'client_first_name' => trim((string) ($client['first_name'] ?? '')) !== '' ? (string) $client['first_name'] : cm_client_display_name($client),
          'job_name'          => (string) (cm_val('SELECT name FROM job WHERE id = ?', [(int) ($invoice['job_id'] ?? 0)]) ?? ''),
          'invoice_number'    => (string) $invoice['number'],
          'invoice_total'     => cm_invoice_money($invoice, (int) $invoice['total_cents']),
          'invoice_due'       => !$isPaid && $invoice['due_on'] ? date('j M Y', strtotime((string) $invoice['due_on'])) : '',





          'invoice_link'      => cm_public_enabled()
              ? cm_url('portal/receipt.php?k=' . rawurlencode(cm_link_for((int) $client['id'])) . '&i=' . $id) : '',
          'my_link'           => cm_public_enabled() ? cm_link_url(cm_link_for((int) $client['id'])) : '',
      ];
      $emailSubject = $tpl ? cm_email_render($tpl['subject'], $ctx) : '';
      $emailBody    = $tpl ? cm_email_render($tpl['body'], $ctx) : '';
?>
<dialog class="modal modal-wide" id="dlg-invoice-email">
  <div class="modal-box">
    <div class="modal-head">
      <h2><?= $isPaid ? 'Email the receipt' : 'Email the invoice' ?></h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <?php if ($unsub !== null && $unsub['unsubscribed']): ?>
      <div class="note note-error m-0">
        <b>This client has unsubscribed from all emails.</b> Nothing can be sent to them
        &mdash; print or download the PDF instead, or read it to them.
      </div>
    <?php else: ?>
    <?php if (!cm_public_enabled()): ?>
      <div class="note note-warn">Your client portal is <b>off</b> (Settings → Your client portal), so there is
      no link to their copy in this message. Turn it on to send one, or download the PDF and send it yourself.</div>
    <?php endif; ?>
    <p class="modal-sub">What is in the box is exactly what goes &mdash; edit it
    freely first.<?= cm_public_enabled() ? ' The link opens their copy, with the PDF and any pay-online buttons you have switched on.' : '' ?>
    <?= cm_mail_can_deliver() ? '' : '<b>Email sending is off</b>, so nothing goes to the client: the message is saved on the server instead. Turn sending on under Settings, Email.' ?></p>
    <form method="post" data-confirm="Email this to <?= e((string) ($client['email'] ?: 'the address above')) ?>?">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="email">
      <input type="hidden" name="invoice_id" value="<?= $id ?>">
      <div class="row">
        <label class="lbl" for="inv_to">To</label>
        <input type="email" id="inv_to" name="to" class="f-full" value="<?= e((string) ($invoice['bill_to_email'] ?: $client['email'])) ?>" required>
      </div>
      <div class="row">
        <label class="lbl" for="inv_subject">Subject</label>
        <input type="text" id="inv_subject" name="subject" class="f-full" maxlength="200" value="<?= e($emailSubject) ?>" required>
      </div>
      <div class="row">
        <label class="lbl" for="inv_body">Message</label>
        <textarea id="inv_body" name="body" rows="12" class="f-full small"><?= e($emailBody) ?></textarea>
        <div class="hint">Reword the template under Settings → Email templates. An unsubscribe link is included in every email this platform sends.</div>
      </div>
      <div class="modal-foot">
        <button class="btn" type="submit"><?= cm_mail_can_deliver() ? 'Send' : 'Save it (email is off)' ?></button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</dialog>
<?php endif; ?>
<?php cm_foot(); ?>
