<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0







declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/invoices.php';

$admin = cm_require_admin();












if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['new_for'])) {
    $clientId = (int) $_GET['new_for'];
    if (cm_row('SELECT id FROM client WHERE id = ?', [$clientId])) {
        $jobId = (int) ($_GET['job'] ?? 0);


        if ($jobId > 0) {
            $draft = (int) cm_val("SELECT id FROM invoice WHERE client_id = ? AND job_id = ? AND status = 'draft'
                                    ORDER BY id DESC LIMIT 1", [$clientId, $jobId]);
            if ($draft > 0) {
                cm_flash('This job already has a draft invoice, so it is opened here rather than starting another.', 'warn');
                cm_redirect('admin/invoice.php?id=' . $draft);
            }
        }
        $id = cm_invoice_create($clientId, $jobId > 0 ? $jobId : null);





        if ($jobId > 0) {
            $job = cm_row('SELECT * FROM job WHERE id = ? AND client_id = ?', [$jobId, $clientId]);
            if ($job && (int) $job['amount_cents'] > 0) {
                $taxable = true;
                if (!empty($job['template_id'])) {
                    $tpl = cm_row('SELECT taxable FROM job_template WHERE id = ?', [(int) $job['template_id']]);
                    if ($tpl) $taxable = (int) $tpl['taxable'] === 1;
                }
                cm_invoice_add_item($id, (string) $job['name'], 1.0, (int) $job['amount_cents'], $taxable);
            }
        }
        cm_flash('Draft started. Check the lines, then issue it.');
        cm_redirect('admin/invoice.php?id=' . $id);
    }
    cm_flash('That client no longer exists.', 'error');
    cm_redirect('admin/invoices.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    if ((string) ($_POST['action'] ?? '') === 'new') {
        $clientId = (int) ($_POST['client_id'] ?? 0);
        if (!cm_row('SELECT id FROM client WHERE id = ?', [$clientId])) {
            cm_flash('Pick the client it is for.', 'error');
            cm_redirect('admin/invoices.php');
        }
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $id = cm_invoice_create($clientId, $jobId > 0 ? $jobId : null);
        cm_flash('Draft started — put some lines on it.');
        cm_redirect('admin/invoice.php?id=' . $id);
    }
    cm_redirect('admin/invoices.php');
}

$filter = (string) ($_GET['f'] ?? 'open');





$where  = match ($filter) {
    'draft' => "i.status = 'draft'",
    'paid'  => "i.status = 'paid'",
    'void'  => "i.status = 'void'",
    'all'   => '1 = 1',
    default => "i.status = 'sent'",
};
$rows = cm_all(
    "SELECT i.*, c.first_name, c.last_name, c.company, c.email
       FROM invoice i JOIN client c ON c.id = i.client_id
      WHERE $where
      ORDER BY COALESCE(i.issued_on, i.created_at) DESC, i.id DESC
      LIMIT 300");

$outstanding = cm_invoice_outstanding_cents();
$summary     = cm_invoice_summary();
$statuses    = cm_invoice_statuses();
$tabs = ['open' => 'Outstanding', 'draft' => 'Drafts', 'paid' => 'Paid', 'void' => 'Void', 'all' => 'Everything'];

cm_head('Invoices', '', true);
cm_render_flashes();
cm_admin_nav('invoices');
?>
<?php 








 ?>
<div class="card-head">
  <h1>Invoices</h1>
  <span class="spacer"></span>
  <button class="btn" type="button" data-modal-open="dlg-new-invoice">+ New invoice</button>
</div>

<?php 














 ?>
<?php 



















 ?>
<div class="stats mb-16">
  <a class="stat" href="<?= e(cm_url('admin/invoices.php?f=all')) ?>">
    <span class="n"><?= (int) $summary['created'] ?></span>
    <span class="l">invoices created</span>
  </a>
  <a class="stat" href="<?= e(cm_url('admin/invoices.php?f=all')) ?>">
    <span class="n"><?= e(cm_money($summary['invoiced'])) ?></span>
    <span class="l">invoiced</span>
  </a>
  <a class="stat<?= $summary['received'] > 0 ? ' stat-ok' : '' ?>"
     href="<?= e(cm_url('admin/invoices.php?f=paid')) ?>">
    <span class="n"><?= e(cm_money($summary['received'])) ?></span>
    <span class="l">payments received</span>
  </a>
  <a class="stat<?= $summary['due'] > 0 ? ' stat-bad' : '' ?>"
     href="<?= e(cm_url('admin/invoices.php?f=open')) ?>">
    <span class="n"><?= e(cm_money($summary['due'])) ?></span>
    <span class="l">currently due</span>
  </a>
</div>

<?php 





 ?>

<nav class="subnav inv-tabs" aria-label="Which invoices">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="subnav-item<?= $filter === $key ? ' is-on' : '' ?>"
       href="<?= e(cm_url('admin/invoices.php?f=' . $key)) ?>"
       <?= $filter === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<div class="inv-toolbar">
  <span class="inv-count">
    <?php 




 ?>
    <?= count($rows) ?> invoice<?= count($rows) === 1 ? '' : 's' ?>
  </span>
</div>

<?php 

 ?>
<dialog class="modal" id="dlg-new-invoice">
  <div class="modal-box">
    <div class="modal-head">
      <h2>New invoice</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <p class="modal-sub">It starts as a draft with no number. Nothing is
    committed until you issue it.</p>
    <form method="post">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="new">
      <div class="row">
        <label class="lbl" for="client_id">Who it is for</label>
        <?php 
 ?>
        <?= cm_client_picker('client_id', 0, true, '', true) ?>
        <div class="hint">Invoicing for a job you have just finished? Raise it
        from the job instead &mdash; it arrives with the work already on it.</div>
      </div>
      <div class="modal-foot">
        <button class="btn" type="submit">Start the draft</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
  </div>
</dialog>

<div class="card">
  <div class="card-head">
    <h2><?= e($tabs[$filter] ?? 'Invoices') ?></h2>
    <span class="spacer"></span>
    <span class="muted small"><?= count($rows) ?></span>
  </div>

  <?php if (!$rows): ?>
    <?php if ($filter === 'open'): ?>
      <p class="muted small">Nothing outstanding. Every invoice you have issued
      has been paid.</p>
    <?php elseif ($filter === 'all'): ?>
      <p class="muted small">No invoices yet. An invoice and a receipt are the
      same document read before and after payment &mdash; there is no separate
      receipt to make. Once one is paid, its printable says <b>RECEIPT</b>.</p>
    <?php else: ?>
      <p class="muted small">Nothing here.</p>
    <?php endif; ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <?php 


 ?>
          <tr><th>Number</th><th>Status</th><th>Client</th><th>Invoice date</th><th>Due date</th>
              <th class="num">Total</th><th class="num">Paid</th><th class="num">Amount due</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <?php
              $balance = cm_invoice_balance_cents($r);






              $overdue = (string) $r['status'] === 'sent' && $r['due_on'] !== null
                         && (string) $r['due_on'] < date('Y-m-d');
              $pill = $overdue ? 'pill-spam'
                    : ((string) $r['status'] === 'paid' ? 'pill-approved'
                    : ((string) $r['status'] === 'void' ? 'pill'
                    : ((string) $r['status'] === 'draft' ? 'pill-pending' : 'pill-live')));
              $pillText = $overdue ? 'Overdue' : $statuses[(string) $r['status']]['label'];
            ?>
            <tr>
              <td class="mono">
                <a href="<?= e(cm_url('admin/invoice.php?id=' . (int) $r['id'])) ?>"><?= e((string) ($r['number'] ?? 'draft')) ?></a>
              </td>
              <td><span class="pill <?= e($pill) ?>"><?= e($pillText) ?></span></td>
              <td><?= e(cm_client_display_name($r)) ?></td>
              <td class="small"><?= $r['issued_on'] ? e(date('j M Y', strtotime((string) $r['issued_on']))) : '&mdash;' ?></td>
              <td class="small<?= $overdue ? ' txt-err' : '' ?>"><?= $r['due_on']
                    ? e(date('j M Y', strtotime((string) $r['due_on']))) : '&mdash;' ?></td>
              <td class="num mono"><?= e(cm_invoice_money($r, (int) $r['total_cents'])) ?></td>
              <td class="num mono"><?= (int) $r['paid_cents'] > 0
                    ? e(cm_invoice_money($r, (int) $r['paid_cents'])) : '&mdash;' ?></td>
              <td class="num mono<?= $balance > 0 && (string) $r['status'] === 'sent' ? ' inv-due' : '' ?>"><?=
                    $balance > 0 && (string) $r['status'] === 'sent'
                    ? e(cm_invoice_money($r, $balance)) : '&mdash;' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php cm_foot(); ?>
