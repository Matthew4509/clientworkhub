<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0







declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/jobs.php';

$admin = cm_require_admin();


if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['add_job'])) {
    cm_require_csrf();




    $r = cm_job_create([
        'client_id'       => (int) ($_POST['client_id'] ?? 0),
        'name'            => (string) ($_POST['name'] ?? ''),
        'description'     => (string) ($_POST['description'] ?? ''),
        'start'           => (string) ($_POST['start'] ?? ''),
        'end'             => (string) ($_POST['end'] ?? ''),
        'template_id'     => (int) ($_POST['template'] ?? 0),
        'amount'          => (string) ($_POST['amount'] ?? ''),
        'freq'            => (string) ($_POST['freq'] ?? 'oneoff'),
        'duration_days'   => (string) ($_POST['days'] ?? ''),
        'per_day_minutes' => (string) ($_POST['perday'] ?? ''),





        'frequency'       => (string) ($_POST['frequency'] ?? ''),
        'hours'           => (string) ($_POST['hours'] ?? ''),







        'priority'           => (string) ($_POST['priority'] ?? ''),
        'tags'               => (string) ($_POST['tags'] ?? ''),
        'notes'              => (string) ($_POST['notes'] ?? ''),
        'setup_minutes'      => (string) ($_POST['setup'] ?? ''),
        'completion_minutes' => (string) ($_POST['completion'] ?? ''),
        'completion_mode'    => (string) ($_POST['completion_mode'] ?? ''),
        'assets'          => (array) ($_POST['job_assets'] ?? []),
    ], (string) $admin['email']);

    if (!$r['ok']) {


        cm_flash($r['error'], 'error');
        cm_redirect('admin/jobs.php');
    }


    $bid = (int) ($_POST['booking_id'] ?? 0);
    if ($bid > 0) {
        try {
            cm_q("UPDATE booking SET job_id = ?, status = 'accepted', updated_at = ?
                   WHERE id = ? AND job_id IS NULL", [$r['id'], cm_now(), $bid]);
        } catch (Throwable $ex) {}
    }




    cm_flash($r['stamped'] > 0
        ? 'Job created and running, with ' . $r['stamped'] . ' day'
          . ($r['stamped'] === 1 ? '' : 's') . ' on the calendar.'
        : 'Job created as a draft — it has no days yet. Give it a length and '
          . 'a time on the job page and it goes on the calendar.');
    cm_redirect('admin/job.php?id=' . $r['id'] . '&flow=new');
}














$fromBooking = null;
$prefill     = [];
$preClient   = (int) ($_GET['client_id'] ?? 0);

$bookingId = (int) ($_GET['booking'] ?? 0);
if ($bookingId > 0) {
    try {
        $fromBooking = cm_row('SELECT * FROM booking WHERE id = ?', [$bookingId]);
    } catch (Throwable $ex) {
        $fromBooking = null;
    }
    if ($fromBooking !== null) {
        $preClient = (int) ($fromBooking['client_id'] ?? 0);
        $tpl = null;
        if ($fromBooking['template_id'] !== null) {
            $tpl = cm_row('SELECT * FROM job_template WHERE id = ?', [(int) $fromBooking['template_id']]);
        }
        $prefill = [
            'template' => (string) ($fromBooking['template_id'] ?? ''),
            'name'     => $tpl !== null ? (string) $tpl['name'] : '',
            'start'    => (string) ($fromBooking['planned_start'] ?? ''),
            'end'      => (string) ($fromBooking['planned_end'] ?? ''),
            'amount'   => (int) $fromBooking['quoted_cents'] > 0
                        ? number_format((int) $fromBooking['quoted_cents'] / 100, 2, '.', '') : '',
            'freq'     => (string) ($fromBooking['quoted_freq'] ?? 'oneoff'),
            'days'     => $tpl !== null && (int) $tpl['duration_days'] > 0
                        ? (string) (int) $tpl['duration_days'] : '',
            'perday'   => $tpl !== null && (int) $tpl['per_day_minutes'] > 0
                        ? (string) (int) $tpl['per_day_minutes'] : '',
        ];
    }
}


$term   = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
if ($status !== '' && $status !== 'all' && !isset(cm_job_statuses()[$status])) $status = '';








$vipOnly = (string) ($_GET['vip'] ?? '') === '1';

$where  = [];
$params = [];



if ($vipOnly) $where[] = 'c.vip = 1';
if ($status === '') {
    $where[] = "j.status NOT IN ('completed','cancelled')";
} elseif ($status !== 'all') {
    $where[] = 'j.status = ?';
    $params[] = $status;
}
if ($term !== '') {
    $like = '%' . $term . '%';






    $where[] = '(j.name LIKE ? OR j.tags LIKE ? OR j.notes LIKE ?
                 OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.company LIKE ?
                 OR EXISTS (SELECT 1 FROM job_asset ja JOIN client_asset a ON a.id = ja.asset_id
                             WHERE ja.job_id = j.id AND a.name LIKE ?))';
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
}
$sorts = [
    ''      => 'Recently touched',
    'new'   => 'Newest',
    'value' => 'Most valuable (per year)',
    'rate'  => 'Lowest hourly rate',
];
$sort = (string) ($_GET['sort'] ?? '');
if (!isset($sorts[$sort])) $sort = '';





$sql = 'SELECT j.*, c.first_name, c.last_name, c.company, c.email AS client_email,
               c.vip AS client_vip
        FROM job j JOIN client c ON c.id = j.client_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY ' . ($sort === 'new' ? 'j.created_at DESC' : 'j.updated_at DESC');





$jobs = [];
$jobAssetNames = [];
$counts = ['' => 0, 'all' => 0, 'vip' => 0];
try {
    $jobs = cm_all($sql, $params);
    $counts[''] = (int) cm_val("SELECT COUNT(*) FROM job WHERE status NOT IN ('completed','cancelled')");




    $counts['vip'] = (int) cm_val("SELECT COUNT(*) FROM job j JOIN client c ON c.id = j.client_id
                                    WHERE c.vip = 1 AND j.status NOT IN ('completed','cancelled')");
    $counts['all'] = (int) cm_val('SELECT COUNT(*) FROM job');
    foreach (cm_all('SELECT status, COUNT(*) AS n FROM job GROUP BY status') as $r) {
        $counts[(string) $r['status']] = (int) $r['n'];
    }
} catch (Throwable $ex) {

}



if ($sort === 'value' || $sort === 'rate') {
    foreach ($jobs as &$j) {
        $j['_annual'] = cm_job_annual_cents($j);
        $bill = 0;
        try { $bill = cm_time_totals('job', (int) $j['id'])['billable']; } catch (Throwable $ex) {}
        $j['_rate'] = cm_effective_rate_cents((int) $j['amount_cents'], $bill);
    }
    unset($j);
    if ($sort === 'value') {
        usort($jobs, static fn($x, $y) => ($y['_annual'] ?? 0) <=> ($x['_annual'] ?? 0));
    } else {



        usort($jobs, static function ($x, $y) {
            $rx = $x['_rate'];
            $ry = $y['_rate'];
            if ($rx === null && $ry === null) return 0;
            if ($rx === null) return 1;
            if ($ry === null) return -1;
            return $rx <=> $ry;
        });
    }
}


$jobTotal = count($jobs);
[$pageSize, $page] = cm_page_params($jobTotal);
$jobs = array_slice($jobs, ($page - 1) * $pageSize, $pageSize);

try { $jobAssetNames = cm_job_asset_names(array_map(static fn ($j) => (int) $j['id'], $jobs)); }
catch (Throwable $ex) {}
$jobsUrl = cm_page_url('admin/jobs.php',



    ['status' => $status, 'q' => $term, 'sort' => $sort, 'vip' => $vipOnly ? '1' : '',
     'n' => (string) $pageSize, 'p' => (string) $page]);

$clients = [];
try {
    $clients = cm_all("SELECT * FROM client WHERE status <> 'inactive' ORDER BY company, first_name, last_name");
} catch (Throwable $ex) {
}

cm_head('Jobs', '', true);
cm_render_flashes();
cm_admin_nav('jobs');





















$tabs = ['' => 'Open', 'hold' => cm_job_statuses()['hold']['label']];
$more = ['active' => cm_job_statuses()['active']['label'],
         'draft' => cm_job_statuses()['draft']['label'],
         'completed' => cm_job_statuses()['completed']['label'],
         'cancelled' => cm_job_statuses()['cancelled']['label'],
         'all' => 'Everything'];



$moreOpen = isset($more[$status]);
?>
<div class="card-head">
  <h1>Jobs</h1>
  <span class="spacer"></span>
  <?php 

 ?>
  <a class="btn" href="<?= e(cm_url('admin/clients.php')) ?>">Create job</a>
</div>

<nav class="subnav" aria-label="Job status">
  <?php foreach ($tabs as $key => $label):
      $href = $jobsUrl(['status' => $key, 'vip' => '', 'p' => '']);



      $on = $status === $key && !$vipOnly; ?>
    <a class="subnav-item<?= $on ? ' is-on' : '' ?>" href="<?= e($href) ?>"
       <?= $on ? 'aria-current="page"' : '' ?>>
      <?= e($label) ?> <span class="subnav-n"><?= (int) ($counts[$key] ?? 0) ?></span>
    </a>
  <?php endforeach; ?>
  <?php 



 ?>
  <details class="subnav-more"<?= $moreOpen ? ' open' : '' ?>>
    <summary class="subnav-item<?= $moreOpen ? ' is-on' : '' ?>">More<?php
      if ($moreOpen): ?>: <?= e($more[$status]) ?><?php endif; ?></summary>
    <div class="subnav-more-body">
      <?php foreach ($more as $key => $label):
          $href = $jobsUrl(['status' => $key, 'vip' => '', 'p' => '']);
          $on = $status === $key && !$vipOnly; ?>
        <a class="subnav-item<?= $on ? ' is-on' : '' ?>" href="<?= e($href) ?>"
           <?= $on ? 'aria-current="page"' : '' ?>>
          <?= e($label) ?> <span class="subnav-n"><?= (int) ($counts[$key] ?? 0) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </details>

  <?php 


 ?>
  <a class="subnav-item subnav-vip<?= $vipOnly ? ' is-on' : '' ?>"
     href="<?= e($jobsUrl(['status' => '', 'vip' => '1', 'p' => ''])) ?>"
     <?= $vipOnly ? 'aria-current="page"' : '' ?>>
    VIP <span class="subnav-n"><?= (int) ($counts['vip'] ?? 0) ?></span>
  </a>
</nav>

<div class="card client-search-card">
  <form class="client-search" method="get" action="<?= e(cm_url('admin/jobs.php')) ?>">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <?php if ($vipOnly): ?><input type="hidden" name="vip" value="1"><?php endif; ?>
    <div class="client-search-field">
      <label class="lbl" for="q">Find a job</label>
      <input type="search" id="q" name="q" value="<?= e($term) ?>"
             placeholder="Job name, client, tag, or anything in the notes">
    </div>
    <div class="job-sort-field">
      <label class="lbl" for="sort">Order by</label>
      <select id="sort" name="sort">
        <?php foreach ($sorts as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="actions client-search-actions">
      <button class="btn btn-sm" type="submit">Apply</button>
      <?php if ($term !== '' || $vipOnly): ?>
        <a class="btn btn-sm btn-quiet" href="<?= e(cm_url('admin/jobs.php' . ($status !== '' ? '?status=' . $status : ''))) ?>">Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <?php if (!$jobs): ?>
    <?php 


 ?>
    <?php
      $narrow = [];
      if ($status !== '' && $status !== 'all') $narrow[] = 'with that status';
      if ($vipOnly) $narrow[] = 'for a VIP client';
      $narrow = $narrow ? ' ' . implode(' and ', $narrow) : '';
    ?>
    <p class="muted small">
      <?php if ($term !== ''): ?>
        Nothing matches “<?= e($term) ?>”<?= $narrow ?>.
        <?php if ($vipOnly): ?><b>Search</b> looks at every job.<?php endif; ?>
      <?php elseif ($vipOnly): ?>
        No jobs here for a VIP client — <b>Make VIP</b> on a client’s own page marks them.
      <?php else: ?>
        No jobs here yet — create the first one below.
      <?php endif; ?>
    </p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="responsive-table">
        <thead><tr><th>Job</th><th>Client</th><th>Dates</th><th>Progress</th><th>Value</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($jobs as $j):
            $jst = cm_job_statuses()[(string) $j['status']] ?? null;
            $pri = cm_job_priorities()[(string) $j['priority']] ?? null;
            $prog = cm_job_progress((int) $j['id']);
            $overdue = (int) cm_val("SELECT COUNT(*) FROM action_occurrence
                                     WHERE job_id = ? AND status = 'scheduled' AND scheduled_date < ?",
                                    [(int) $j['id'], date('Y-m-d')]);



            $rowVip = (int) ($j['client_vip'] ?? 0) === 1;
        ?>
          <?php 

 ?>
          <tr<?= $rowVip ? ' class="is-vip"' : '' ?>>
            <?php 





 ?>
            <?php $lead = cm_job_lead_label($jobAssetNames[(int) $j['id']] ?? [], (string) $j['name']); ?>
            <td data-label="Job"><a href="<?= e(cm_url('admin/job.php?id=' . (int) $j['id'])) ?>"><b><?= e($lead) ?></b></a>
              <?php if ($overdue > 0 && in_array((string) $j['status'], ['active'], true)): ?>
                <span class="pill pill-spam"><?= $overdue ?> overdue</span>
              <?php endif; ?>
              <?php if ($lead !== (string) $j['name']): ?>
                <div class="muted small"><?= e((string) $j['name']) ?></div>
              <?php endif; ?>
            </td>
            <?php 





 ?>
            <td class="small" data-label="Client"><a href="<?= e(cm_url('admin/client.php?id=' . (int) $j['client_id'])) ?>"><?= e(cm_client_display_name(['id' => $j['client_id'], 'first_name' => $j['first_name'], 'last_name' => $j['last_name'], 'company' => $j['company'], 'email' => $j['client_email']])) ?></a>
              <?php if ($rowVip): ?> <span class="pill pill-vip">VIP</span><?php endif; ?>
            </td>
            <td class="small mono"><?= $j['start_date'] ? e(date('j M', strtotime((string) $j['start_date']))) : '—' ?> → <?= $j['end_date'] ? e(date('j M', strtotime((string) $j['end_date']))) : 'open' ?></td>
            <?php 



 ?>
            <td class="small" data-label="Progress"><?=
              cm_job_progress_cell_html($prog, (string) $j['status'] === 'completed') ?></td>
            <td class="small mono" data-label="Value">
              <?= (int) $j['amount_cents'] > 0
                  ? e(cm_money((int) $j['amount_cents'])) . ' ' . e(strtolower(cm_billing_frequencies()[(string) $j['billing_freq']]))
                  : '—' ?>
              <?php if (array_key_exists('_rate', $j) && $j['_rate'] !== null): ?>
                <div class="muted"><?= e(cm_money((int) $j['_rate'])) ?>/hr</div>
              <?php endif; ?>
              <?php if ($pri && $j['priority'] !== 'normal'): ?><span class="pill <?= e($pri['pill']) ?>"><?= e($pri['label']) ?></span><?php endif; ?>
            </td>
            <td data-label="Status"><?php if ($jst): ?><span class="pill <?= e($jst['pill']) ?>"><?= e($jst['label']) ?></span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= cm_pager_html($jobTotal, $page, $pageSize, $jobsUrl) ?>
    <div>
    </div>
  <?php endif; ?>
</div>


<?php cm_foot(); ?>
