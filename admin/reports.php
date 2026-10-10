<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0









declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/jobs.php';
require_once CM_LIB . '/insights.php';
require_once CM_LIB . '/invoices.php';

$admin = cm_require_admin();

$periods = [
    'week'    => ['label' => 'This week',    'since' => date('Y-m-d', strtotime('monday this week'))],
    'month'   => ['label' => 'This month',   'since' => date('Y-m-01')],
    'quarter' => ['label' => 'This quarter', 'since' => date('Y-m-d', mktime(0, 0, 0, ((int) ceil(((int) date('n')) / 3) - 1) * 3 + 1, 1, (int) date('Y')))],
    'year'    => ['label' => 'This year',    'since' => date('Y-01-01')],
    'all'     => ['label' => 'All time',     'since' => '2000-01-01'],
];
$p = (string) ($_GET['p'] ?? 'month');
if (!isset($periods[$p])) $p = 'month';
$since = $periods[$p]['since'] . ' 00:00:00';


$time = ['total' => 0, 'billable' => 0, 'entries' => 0];
$byClient = [];
try {
    $row = cm_row('SELECT COALESCE(SUM(minutes),0) AS total,
                          COALESCE(SUM(CASE WHEN billable=1 THEN minutes ELSE 0 END),0) AS billable,
                          COUNT(*) AS entries
                   FROM time_entry WHERE created_at >= ?', [$since]);
    $time = ['total' => (int) $row['total'], 'billable' => (int) $row['billable'],
             'entries' => (int) $row['entries']];
    $byClient = cm_all(
        'SELECT t.client_id, COALESCE(SUM(t.minutes),0) AS mins,
                c.first_name, c.last_name, c.company, c.email AS client_email
         FROM time_entry t JOIN client c ON c.id = t.client_id
         WHERE t.created_at >= ?
         GROUP BY t.client_id ORDER BY mins DESC LIMIT 15', [$since]);
} catch (Throwable $ex) {
}


$jobsDone = 0;
$jobsActive = 0;
$tasksDone = 0;
$overdueNow = 0;
try {
    $jobsDone   = (int) cm_val("SELECT COUNT(*) FROM job WHERE status='completed' AND completed_at >= ?", [$since]);
    $jobsActive = (int) cm_val("SELECT COUNT(*) FROM job WHERE status='active'");
    $tasksDone  = (int) cm_val("SELECT COUNT(*) FROM action_occurrence WHERE status='completed' AND completed_at >= ?", [$since]);
    $overdueNow = (int) cm_val("SELECT COUNT(*) FROM action_occurrence o JOIN job j ON j.id=o.job_id
                                WHERE o.status='scheduled' AND o.scheduled_date < ? AND j.status='active'",
                               [date('Y-m-d')]);
} catch (Throwable $ex) {
}


$clientRows = [];
try {
    foreach (cm_all("SELECT * FROM client WHERE status <> 'inactive'") as $c) {
        $v = cm_client_value((int) $c['id']);
        if ($v['jobs_total'] === 0 && $v['minutes'] === 0) continue;
        $clientRows[] = ['client' => $c] + $v;
    }

    usort($clientRows, static function ($x, $y) {
        if ($x['rate'] === null && $y['rate'] === null) return $y['jobs_total'] <=> $x['jobs_total'];
        if ($x['rate'] === null) return 1;
        if ($y['rate'] === null) return -1;
        return $x['rate'] <=> $y['rate'];
    });
} catch (Throwable $ex) {
}

cm_head('Reports', '', true);
cm_render_flashes();
cm_admin_nav('reports');
?>
<h1>Reports</h1>

<?php 

 ?>
<div class="card">
  <?= cm_insight_heatmap_html(cm_insight_heatmap('client'), 'clients added') ?>
  <p class="hint">Each square is a day; darker means more clients started
  that day. It fills in as you go.</p>
</div>

<div class="blocks mb-16">
  <?php foreach (cm_insight_blocks() as $b): ?>
    <section class="block">
      <h3><a href="<?= e($b['href']) ?>"><?= e($b['title']) ?></a></h3>
      <p class="block-lead"><?= e($b['lead']) ?>
        <?php if ($b['growth'] !== null): ?>
          <span class="block-growth <?= $b['growth'] >= 0 ? 'block-up' : 'block-down' ?>">
            <?= $b['growth'] >= 0 ? '+' : '' ?><?= (int) $b['growth'] ?>%
          </span>
        <?php endif; ?>
      </p>
      <ul>
        <?php foreach ($b['lines'] as $l): ?>
          <li>
            <a href="<?= e((string) $l['href']) ?>">
              <span class="n"><?php
                if (isset($l['pct'])) echo (int) $l['pct'] . '%';
                elseif (isset($l['text'])) echo e((string) $l['text']);
                else echo (int) $l['n'];
              ?></span>
              <span class="lb"><?= e((string) $l['label']) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>
</div>


<nav class="subnav" aria-label="Period">
  <?php foreach ($periods as $key => $per): ?>
    <a class="subnav-item<?= $p === $key ? ' is-on' : '' ?>"
       href="<?= e(cm_url('admin/reports.php?p=' . $key)) ?>"
       <?= $p === $key ? 'aria-current="page"' : '' ?>><?= e($per['label']) ?></a>
  <?php endforeach; ?>
</nav>

<div class="card">
  <h2><?= e($periods[$p]['label']) ?></h2>
  <div class="mini-grid">
    <div class="mini"><span class="n mono"><?= e(cm_minutes_label($time['total'])) ?></span> time tracked</div>
    <div class="mini"><span class="n mono"><?= e(cm_minutes_label($time['billable'])) ?></span> of it billable</div>
    <div class="mini"><span class="n"><?= $tasksDone ?></span> tasks completed</div>
    <div class="mini"><span class="n"><?= $jobsDone ?></span> jobs completed</div>
    <div class="mini"><span class="n"><?= $jobsActive ?></span> jobs active now</div>
    <div class="mini"><span class="n"><?= $overdueNow ?></span> tasks overdue now</div>
  </div>
</div>

<?php 






 ?>
<?php $moneyAll = cm_money_summary(); ?>
<div class="card">
  <h2>Money — all time</h2>
  <div class="mini-grid">
    <div class="mini"><span class="n mono"><?= e(cm_money($moneyAll['job_value'])) ?></span> job value on the books</div>
    <div class="mini"><span class="n mono"><?= e(cm_money($moneyAll['invoiced'])) ?></span> invoiced</div>
    <div class="mini"><span class="n mono"><?= e(cm_money($moneyAll['via_invoice'])) ?></span> paid on invoices</div>
    <div class="mini"><span class="n mono"><?= e(cm_money($moneyAll['elsewhere'])) ?></span> received elsewhere, not declared</div>
    <div class="mini"><span class="n mono"><?= e(cm_money($moneyAll['outstanding'])) ?></span> still owed on invoices</div>
  </div>
  <p class="muted small m-0">"Received elsewhere" counts towards job earnings but not invoiced totals. Use it for payments recorded outside Client Work Hub.</p>
</div>

<div class="card">
  <h2>Clients, by effective rate — lowest first</h2>
  <p class="muted small">Job value divided by billable hours. Lower rates may identify work that needs repricing; this report never changes prices.</p>
  <?php if (!$clientRows): ?>
    <p class="muted small">Nothing to compare yet — value and tracked time
    both start this table.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Client</th><th>On the books</th><th>Active / year</th><th>Billable time</th><th>Effective rate</th></tr></thead>
        <tbody>
        <?php foreach ($clientRows as $r): $c = $r['client']; ?>
          <tr>
            <td><a href="<?= e(cm_url('admin/client.php?id=' . (int) $c['id'])) ?>"><b><?= e(cm_client_display_name($c)) ?></b></a></td>
            <td class="small mono"><?= e(cm_money($r['jobs_total'])) ?></td>
            <td class="small mono"><?= e(cm_money($r['active_annual'])) ?></td>
            <td class="small mono"><?= e(cm_minutes_label($r['billable'])) ?></td>
            <td class="small mono"><?= $r['rate'] !== null ? '<b>' . e(cm_money($r['rate'])) . '/hr</b>' : '<span class="muted">no time yet</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Where the time went — <?= e(strtolower($periods[$p]['label'])) ?></h2>
  <?php if (!$byClient): ?>
    <p class="muted small">No time tracked in this period.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Client</th><th>Time</th></tr></thead>
        <tbody>
        <?php foreach ($byClient as $r): ?>
          <tr>
            <td class="small"><a href="<?= e(cm_url('admin/client.php?id=' . (int) $r['client_id'])) ?>"><?= e(cm_client_display_name(['id' => $r['client_id'], 'first_name' => $r['first_name'], 'last_name' => $r['last_name'], 'company' => $r['company'], 'email' => $r['client_email']])) ?></a></td>
            <td class="small mono"><?= e(cm_minutes_label((int) $r['mins'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php cm_foot(); ?>
