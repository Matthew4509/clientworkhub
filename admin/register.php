<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0























declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/register.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/format.php';

$admin = cm_require_admin();
cm_schema_gate('register', 'register');

$error = '';
$sticky = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();

    if (!cm_once_spend()) {
        cm_flash('That sale was already sent, so nothing more was recorded. Check the latest receipts.', 'warn');
        cm_redirect('admin/register.php');
    }
    $r = cm_register_create($_POST, (string) $admin['email']);
    if (!$r['ok']) {
        $error = (string) $r['error'];

        $sticky = $_POST;
    } else {
        cm_flash('Sale completed and receipt created.');
        cm_redirect('admin/register-receipt.php?id=' . (int) $r['id']);
    }
}

$q      = trim((string) ($_GET['q'] ?? ''));
$method = (string) ($_GET['method'] ?? '');
$status = (string) ($_GET['status'] ?? '');
$filtered = ($q !== '' || $method !== '' || $status !== '');
$limit  = $filtered ? 100 : 10;

$sales  = cm_register_recent($limit, $q, $method, $status);
$totals = cm_register_daily_totals(date('Y-m-d'));

$stickyMethod = (string) ($sticky['payment_method'] ?? 'cash');
$stickyClient = (int) ($sticky['client_id'] ?? 0);

cm_head('Cash register', '', true);
cm_render_flashes();
cm_admin_nav('register');
?>
<div class="topbar">
  <div>
    <h1>Cash register</h1>
    <p class="sub">Take a payment and create a receipt.</p>
  </div>
  <span class="spacer"></span>
  <a class="btn btn-quiet" href="#transactions">Transactions</a>
</div>

<?php if ($error !== ''): ?><div class="note note-error"><?= e($error) ?></div><?php endif; ?>

<div class="page-grid">
  <div>
    <div class="card">
      <h2>New sale</h2>
      <form method="post" data-register-form data-progress
            data-currency="<?= e((string) cm_currency()['symbol']) ?>">
        <?= cm_csrf_field() ?><?= cm_once_field() ?>
        <div class="form-2col">
          <div class="form-col">
            <div class="row">
              <label class="lbl" for="reg-total">Amount</label>
              <input id="reg-total" name="total" inputmode="decimal" required placeholder="0.00"
                     autocomplete="off" autofocus value="<?= e((string) ($sticky['total'] ?? '')) ?>">
            </div>
          </div>
          <div class="form-col">
            <div class="row">
              <label class="lbl" for="reg-method">Payment method</label>
              <select id="reg-method" name="payment_method">
                <?php foreach (cm_register_methods() as $k => $v): ?>
                  <option value="<?= e($k) ?>"<?= $stickyMethod === $k ? ' selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <?php 
 ?>
        <div data-cash-fields>
          <div class="form-2col">
            <div class="form-col">
              <div class="row">
                <label class="lbl" for="reg-received">Cash received</label>
                <input id="reg-received" name="amount_received" inputmode="decimal" placeholder="0.00"
                       autocomplete="off" value="<?= e((string) ($sticky['amount_received'] ?? '')) ?>">
              </div>
            </div>
            <div class="form-col">
              <div class="row">
                <span class="lbl">Change due</span>
                <strong class="register-change" data-change><?= e(cm_money(0)) ?></strong>
              </div>
            </div>
          </div>
        </div>

        <?php 





 ?>
        <div class="row">
          <label class="lbl" for="reg-client">Customer</label>
          <?= cm_client_picker('client_id', $stickyClient, false, 'reg-client') ?>
          <div class="hint">Leave empty for a walk-in customer.</div>
        </div>

        <div class="row">
          <label class="lbl" for="reg-desc">What was sold? <span class="muted">optional</span></label>
          <input id="reg-desc" name="description" maxlength="255"
                 placeholder="Tuning, haircut, product sale"
                 value="<?= e((string) ($sticky['description'] ?? '')) ?>">
          <div class="hint">This prints on the receipt.</div>
        </div>

        <?php 
 ?>
        <div class="card-subtle mt-16" data-register-review>
          <h3>Review this sale</h3>
          <div class="summary-strip">
            <div><span class="summary-k">Amount</span><b data-review-total><?= e(cm_money(0)) ?></b></div>
            <div><span class="summary-k">Method</span><b data-review-method>Cash</b></div>
            <div><span class="summary-k">Customer</span><b data-review-client>Walk-in</b></div>
            <div data-review-change-wrap><span class="summary-k">Change</span><b data-review-change><?= e(cm_money(0)) ?></b></div>
          </div>
        </div>

        <div class="actions mt-16">
          <button class="btn" type="submit">Complete sale</button>
        </div>
      </form>
    </div>
  </div>

  <aside class="side-rail">
    <div class="card">
      <h2>Today</h2>
      <?php foreach ($totals['methods'] as $k => $c): ?>
        <div class="summary-line"><span><?= e(cm_register_methods()[$k]) ?></span><b><?= e(cm_money($c)) ?></b></div>
      <?php endforeach; ?>
      <div class="summary-line"><span>Refunds</span><b>&minus;<?= e(cm_money($totals['refunds'])) ?></b></div>
      <?php 

 ?>
      <div class="summary-line summary-total"><span>Net total</span><strong><?= e(cm_money($totals['net'])) ?></strong></div>
    </div>
  </aside>
</div>

<div class="card mt-16" id="transactions">
  <div class="card-head">
    <h2><?= $filtered ? 'Transactions' : 'Last 10 transactions' ?></h2>
    <span class="spacer"></span>
    <?php if ($filtered): ?>
      <span class="muted small"><?= count($sales) ?> shown<?= count($sales) >= $limit ? ', newest first' : '' ?></span>
    <?php endif; ?>
  </div>

  <form method="get" class="filter-bar">
    <input name="q" value="<?= e($q) ?>" placeholder="Receipt number" aria-label="Receipt number">
    <select name="method" aria-label="Payment method">
      <option value="">All methods</option>
      <?php foreach (cm_register_methods() as $k => $v): ?>
        <option value="<?= e($k) ?>"<?= $method === $k ? ' selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" aria-label="Status">
      <option value="">All statuses</option>
      <?php foreach (cm_register_statuses() as $k => $v): ?>
        <option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($v['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-quiet">Filter</button>
    <?php if ($filtered): ?>
      <a class="btn btn-sm btn-quiet" href="<?= e(cm_url('admin/register.php')) ?>#transactions">Clear</a>
    <?php endif; ?>
  </form>

  <?php if (!$sales): ?>
    <div class="empty-state">
      <h3><?= $filtered ? 'Nothing matches that' : 'No transactions yet' ?></h3>
      <p><?= $filtered ? 'Clear the filter to see the most recent sales.'
                       : 'Completed sales appear here with their receipt numbers.' ?></p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Receipt</th><th>Time</th><th>Customer</th><th>Method</th><th>Total</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($sales as $s): ?>
          <tr>
            <td class="mono nowrap"><?= e((string) $s['receipt_no']) ?></td>
            <td class="nowrap"><?= e(date('j M, g:ia', strtotime((string) $s['created_at']))) ?></td>
            <td><?= $s['client_id'] ? e(cm_client_display_name($s)) : '<span class="muted">Walk-in</span>' ?></td>
            <td><?= e(cm_register_methods()[(string) $s['payment_method']] ?? 'Other') ?></td>
            <td class="mono nowrap"><?= e(cm_money((int) $s['total_cents'])) ?></td>
            <td><?php $st = cm_register_status($s); ?>
              <span class="pill <?= $st === 'Paid' ? 'pill-approved' : 'pill-pending' ?>"><?= e($st) ?></span></td>
            <td><a class="btn btn-sm btn-quiet"
                   href="<?= e(cm_url('admin/register-receipt.php?id=' . (int) $s['id'])) ?>">View receipt</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php 
 ?>
<script defer src="<?= e(cm_url('js/register.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>
<?php cm_foot(); ?>
