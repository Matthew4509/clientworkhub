<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0









declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/jobs.php';
require_once CM_LIB . '/reminders.php';
require_once CM_LIB . '/schedule.php';
require_once CM_LIB . '/queueactions.php';

$admin = cm_require_admin();

$f = (string) ($_GET['f'] ?? '');



$back = array_filter(['f' => $f, 'vip' => (string) ($_GET['vip'] ?? ''),
                      'n' => (string) ($_GET['n'] ?? ''), 'p' => (string) ($_GET['p'] ?? '')],
                     static fn(string $v): bool => $v !== '');
cm_queue_handle_post($admin, 'admin/workflow.php' . ($back ? '?' . http_build_query($back) : ''));


$queue = [];
try {
    foreach (cm_all("SELECT id FROM job WHERE status = 'active'") as $j) {
        cm_job_generate((int) $j['id']);
    }
    $queue = cm_work_queue();
} catch (Throwable $ex) {

}

$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));











$filters = [
    ''         => 'List all',
    'overdue'  => 'Overdue',
    'today'    => 'Due today',
    'tomorrow' => 'Due tomorrow',
    'paused'   => 'Started but paused',
];
if (!isset($filters[$f])) $f = '';
$primaryFilters = array_intersect_key($filters, array_flip(['', 'overdue', 'today']));
$moreFilters = array_intersect_key($filters, array_flip(['tomorrow', 'paused']));

$isPaused = static fn(array $o): bool =>
    (string) $o['status'] === 'scheduled' && (int) ($o['minutes_spent'] ?? 0) > 0;

$counts = ['' => count($queue), 'overdue' => 0, 'today' => 0, 'tomorrow' => 0, 'paused' => 0];
foreach ($queue as $o) {
    if ((string) $o['scheduled_date'] < $today) $counts['overdue']++;
    if ((string) $o['scheduled_date'] === $today) $counts['today']++;
    if ((string) $o['scheduled_date'] === $tomorrow) $counts['tomorrow']++;
    if ($isPaused($o)) $counts['paused']++;
}










$vipOnly = (string) ($_GET['vip'] ?? '') === '1';
$moreOpen = !$vipOnly && isset($moreFilters[$f]);
$counts['vip'] = count(array_filter($queue,
    static fn(array $o): bool => (int) ($o['client_vip'] ?? 0) === 1));

$shown = array_values(array_filter($queue, static function (array $o) use ($f, $today, $tomorrow, $vipOnly, $isPaused): bool {
    if ($vipOnly && (int) ($o['client_vip'] ?? 0) !== 1) return false;
    return match ($f) {
        'overdue'  => (string) $o['scheduled_date'] < $today,
        'today'    => (string) $o['scheduled_date'] === $today,
        'tomorrow' => (string) $o['scheduled_date'] === $tomorrow,
        'paused'   => $isPaused($o),
        default    => true,
    };
}));


[$pageSize, $page] = cm_page_params(count($shown));
$pageRows = array_slice($shown, ($page - 1) * $pageSize, $pageSize);
$wfUrl = cm_page_url('admin/workflow.php',
    ['f' => $f, 'vip' => $vipOnly ? '1' : '', 'n' => (string) $pageSize, 'p' => (string) $page]);

$recent = [];
$recentDays = (int) ($_GET['recent'] ?? 30);
if (!in_array($recentDays, [7, 30, 90, 3650], true)) $recentDays = 30;
try {
    $recent = cm_recent_work(30, $recentDays);
} catch (Throwable $ex) {
}

$working = null;
try { $working = cm_timer_running(); } catch (Throwable $ex) {}
$showQueue = $working === null || isset($_GET['queue']);

cm_head('Workflow', '', true);
cm_render_flashes();
cm_admin_nav('workflow');










echo '<h1>Workflow</h1>';

echo cm_work_card_html($admin);
?>
<?php if (!$showQueue): ?>
<?php 
 ?>
<p class="muted small mt-14">The queue is set aside while
you work — <b>Complete &amp; start next</b> brings the next task straight here and starts its clock.
<a href="<?= e(cm_url('admin/workflow.php?queue=1')) ?>">Show the queue anyway</a>.</p>
<?php else: ?>

<nav class="subnav" aria-label="Queue filters">
  <?php foreach ($primaryFilters as $key => $label): ?>
    <?php 

 ?>
    <?php $on = $f === $key && !$vipOnly; ?>
    <a class="subnav-item<?= $on ? ' is-on' : '' ?>"
       href="<?= e($wfUrl(['f' => $key, 'vip' => '', 'p' => ''])) ?>"
       <?= $on ? 'aria-current="page"' : '' ?>>
      <?= e($label) ?> <span class="subnav-n<?= $key === 'overdue' && $counts['overdue'] > 0 ? ' is-work' : '' ?>"><?= $counts[$key] ?></span>
    </a>
  <?php endforeach; ?>
  <details class="subnav-more"<?= $moreOpen ? ' open' : '' ?>>
    <summary class="subnav-item<?= $moreOpen ? ' is-on' : '' ?>">
      More filters<?php if ($moreOpen): ?>: <?= e($moreFilters[$f]) ?><?php endif; ?>
    </summary>
    <div class="subnav-more-body">
      <?php foreach ($moreFilters as $key => $label): $on = $f === $key && !$vipOnly; ?>
        <a class="subnav-item<?= $on ? ' is-on' : '' ?>"
           href="<?= e($wfUrl(['f' => $key, 'vip' => '', 'p' => ''])) ?>"
           <?= $on ? 'aria-current="page"' : '' ?>>
          <?= e($label) ?> <span class="subnav-n"><?= $counts[$key] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </details>
  <?php 


 ?>
  <a class="subnav-item subnav-vip<?= $vipOnly ? ' is-on' : '' ?>"
     href="<?= e($wfUrl(['f' => '', 'vip' => '1', 'p' => ''])) ?>"
     <?= $vipOnly ? 'aria-current="page"' : '' ?>>
    VIP <span class="subnav-n"><?= (int) ($counts['vip'] ?? 0) ?></span>
  </a>
</nav>

<div class="card">
  <?php if (!$shown): ?>
    <?php if (!$queue): ?>
      <p class="note note-ok m-0"><b>Nothing due.</b> No open
      tasks on any active job for the next two weeks. Jobs that are drafts,
      paused or waiting on a client do not appear here — that is what those
      statuses are for.</p>
    <?php else: ?>
      <p class="muted small">Nothing under
      <?= $vipOnly ? 'this filter <b>among VIPs</b>' : 'this filter' ?> — the
      queue has <?= count($queue) ?> task<?= count($queue) === 1 ? '' : 's' ?>
      under “List all”.</p>
    <?php endif; ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="responsive-table">
        <thead><tr><th>Day</th><th>Task</th><th>Client / job</th><th>Wrap it up</th></tr></thead>
        <tbody>
        <?php foreach ($pageRows as $o):
            $overdue = (string) $o['scheduled_date'] < $today;
            $isToday = (string) $o['scheduled_date'] === $today;
            $rowVip  = (int) ($o['client_vip'] ?? 0) === 1;
            $pri = cm_job_priorities()[(string) $o['priority']] ?? null; ?>
          <?php 




 ?>
          <tr class="<?= trim(($overdue ? 'occ-overdue ' : '') . ($rowVip ? 'is-vip' : '')) ?>">
            <td class="mono small occ-date nowrap" data-label="Day">
              <?= e(date('D j M', strtotime((string) $o['scheduled_date']))) ?>
              <?= $overdue ? '<span class="pill pill-spam">overdue</span>'
                  : ($isToday ? '<span class="pill pill-approved">today</span>' : '') ?>
            </td>
            <?php 






 ?>
            <td data-label="Task"><a href="<?= e(cm_url('admin/task.php?id=' . (int) $o['id'])) ?>"><b><?= e((string) $o['action_name']) ?></b></a>
              <?php if ($pri && (string) $o['priority'] !== 'normal'): ?>
                <span class="pill <?= e($pri['pill']) ?>"><?= e($pri['label']) ?></span>
              <?php endif; ?>
              <?php 

















 ?>
              <?php $est = (int) $o['est_minutes'] > 0 ? (int) $o['est_minutes'] : (int) $o['action_minutes']; ?>
              <div class="occ-time">
                <span>Time allowed:
                  <b class="mono"><?= $est > 0 ? e(cm_minutes_label($est)) : '&mdash;' ?></b></span>
                <span>Time so far:
                  <b class="mono"><?= e(cm_minutes_label((int) $o['minutes_spent'])) ?></b></span>
              </div>
            </td>
            <td class="small" data-label="Client / job">
              <a href="<?= e(cm_url('admin/client.php?id=' . (int) $o['client_id'])) ?>"><?= e(cm_client_display_name(['id' => $o['client_id'], 'first_name' => $o['first_name'], 'last_name' => $o['last_name'], 'company' => $o['company'], 'email' => $o['client_email']])) ?></a>
              <?php 




 ?>
              <?php if ($rowVip): ?><span class="pill pill-vip">VIP</span><?php endif; ?>
              <div class="muted"><a href="<?= e(cm_url('admin/job.php?id=' . (int) $o['job_id'])) ?>"><?= e((string) $o['job_name']) ?></a></div>
            </td>
            <?php 























 ?>
            <td data-label="Wrap it up">
              <div class="occ-actions">
              <form method="post" class="inline">
                <?= cm_csrf_field() ?>
                <input type="hidden" name="occ_id" value="<?= (int) $o['id'] ?>">
                <input type="hidden" name="then" value="task">
                <button class="btn btn-sm" name="action" value="occ_start">Start</button>
                <button class="btn btn-sm btn-quiet" name="action" value="occ_complete">Mark as done</button>
                <button class="btn btn-sm btn-quiet" name="action" value="occ_cancel"
                        data-confirm="Cancel this task? It stays on the record as cancelled.">Cancel</button>
              </form>
              <button class="btn btn-sm btn-quiet" type="button"
                      data-modal-open="dlg-occ-edit-<?= (int) $o['id'] ?>">Edit</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php 





 ?>
    <?php foreach ($pageRows as $o): cm_occurrence_edit_window($o); endforeach; ?>
    <?= cm_pager_html(count($shown), $page, $pageSize, $wfUrl) ?>
  <?php endif; ?>
</div>

<?php 

 ?>
<div class="card" id="recent">
  <div class="card-head">
    <h2>Recently completed</h2>
    <span class="spacer"></span>
    <span class="small muted">
      <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days', 3650 => 'all'] as $d => $lbl): ?>
        <?= $recentDays === $d ? '<b>' . $lbl . '</b>' : '<a href="' . e(cm_url('admin/workflow.php?recent=' . $d . ($f !== '' ? '&f=' . $f : ''))) . '">' . $lbl . '</a>' ?><?= $d !== 3650 ? ' · ' : '' ?>
      <?php endforeach; ?>
    </span>
  </div>
  <?php if (!$recent): ?>
    <p class="muted small">Nothing completed in this window.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="responsive-table">
        <thead><tr><th>Completed</th><th>Task</th><th>Client / job</th><th>Note</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $o): $rowVip = (int) ($o['client_vip'] ?? 0) === 1; ?>
          <?php 


 ?>
          <tr<?= $rowVip ? ' class="is-vip"' : '' ?>>
            <td class="small mono nowrap" data-label="Completed"><?= e(date('j M, g:ia', strtotime((string) $o['completed_at']))) ?></td>
            <td class="small" data-label="Task"><?= e((string) $o['action_name']) ?></td>
            <td class="small" data-label="Client / job">
              <?= e(cm_client_display_name(['id' => $o['client_id'], 'first_name' => $o['first_name'], 'last_name' => $o['last_name'], 'company' => $o['company'], 'email' => $o['client_email']])) ?>
              <span class="muted">— <a href="<?= e(cm_url('admin/job.php?id=' . (int) $o['job_id'])) ?>"><?= e((string) $o['job_name']) ?></a></span>
            </td>
            <td class="small" data-label="Note"><?= e((string) $o['note']) ?>
              <?php if ((string) $o['content_url'] !== ''): ?>
                <a href="<?= e(cm_safe_href((string) $o['content_url'])) ?>" target="_blank" rel="noopener noreferrer">link</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="hint">Completed something too early? Open its job and press
    <b>Reopen</b> on the task — it goes back on the schedule for its
    original day.</p>
  <?php endif; ?>
</div>
<?php endif;  ?>
<?php cm_foot(); ?>
