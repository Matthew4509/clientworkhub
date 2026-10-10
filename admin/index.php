<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0








declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/appointments.php';
require_once CM_LIB . '/maillog.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/jobs.php';
require_once CM_LIB . '/reminders.php';
require_once CM_LIB . '/schedule.php';
require_once CM_LIB . '/queueactions.php';
require_once CM_LIB . '/migrate.php';
require_once CM_LIB . '/health.php';
require_once CM_LIB . '/setup.php';
require_once CM_LIB . '/insights.php';

$admin = cm_require_admin();

$today = date('Y-m-d');
$day = (string) ($_GET['day'] ?? $today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || strtotime($day) === false) $day = $today;

cm_queue_handle_post($admin, 'admin/' . ($day !== $today ? '?day=' . $day : ''));


$state  = cm_migration_state();





try { cm_followups_send_due(); } catch (Throwable $ex) {}







require_once CM_LIB . '/updater.php';
try { cm_update_quiet_check(); } catch (Throwable $ex) {}



$setup      = cm_setup_progress();
$setupSteps = cm_setup_steps();










$activeJobs = [];
try { $activeJobs = cm_all("SELECT * FROM job WHERE status = 'active'"); } catch (Throwable $ex) {}
$annualCents = 0;
foreach ($activeJobs as $aj) { $annualCents += cm_job_annual_cents($aj); }

$monthStart = date('Y-m-01');
$minutesMonth = 0;
$minutesTotal = 0;
try {
    $minutesMonth = (int) cm_val(
        'SELECT COALESCE(SUM(minutes),0) FROM time_entry WHERE billable = 1 AND created_at >= ?',
        [$monthStart . ' 00:00:00']);
    $minutesTotal = (int) cm_val('SELECT COALESCE(SUM(minutes),0) FROM time_entry WHERE billable = 1');
} catch (Throwable $ex) {}

$rateCents = cm_effective_rate_cents($annualCents, $minutesTotal);




$trend = [];
for ($i = 5; $i >= 0; $i--) {
    $mStart = date('Y-m-01', strtotime("-{$i} month"));
    $mEnd   = date('Y-m-01', strtotime($mStart . ' +1 month'));
    $mins = 0;
    try {
        $mins = (int) cm_val(
            'SELECT COALESCE(SUM(minutes),0) FROM time_entry
             WHERE billable = 1 AND created_at >= ? AND created_at < ?',
            [$mStart . ' 00:00:00', $mEnd . ' 00:00:00']);
    } catch (Throwable $ex) {}
    $trend[] = ['label' => date('M', strtotime($mStart)), 'minutes' => $mins];
}
$trendMax = max(1, max(array_column($trend, 'minutes')));

$jobsEndingSoon = 0;
try {
    $jobsEndingSoon = (int) cm_val(
        "SELECT COUNT(*) FROM job WHERE status = 'active' AND end_date IS NOT NULL
         AND end_date BETWEEN ? AND ?", [$today, date('Y-m-d', strtotime('+7 days'))]);
} catch (Throwable $ex) {}
$checks = cm_health_checks();
$worst  = cm_health_worst($checks);

$queue = [];
try {
    foreach (cm_all("SELECT id FROM job WHERE status = 'active'") as $j) {
        cm_job_generate((int) $j['id']);
    }
    $queue = cm_work_queue();
} catch (Throwable $ex) {

}

$counts = ['overdue' => 0, 'today' => 0, 'upcoming' => 0];
foreach ($queue as $o) {
    if ((string) $o['scheduled_date'] < $today) $counts['overdue']++;
    elseif ((string) $o['scheduled_date'] === $today) $counts['today']++;
    else $counts['upcoming']++;
}
$doneToday = 0;
try {
    $doneToday = (int) cm_val("SELECT COUNT(*) FROM action_occurrence
                               WHERE status = 'completed' AND completed_at >= ?", [$today . ' 00:00:00']);
} catch (Throwable $ex) {
}






$plan = [];
$history = [];
$isPast = $day < $today;
$isFuture = $day > $today;
try {
    if ($isPast) {
        $history = cm_day_history($day);
    } elseif ($isFuture) {


        $times = cm_workday_times();
        $plan = cm_day_plan(cm_queue_for_day($day),
                            $times['start'] !== '' ? $times['start'] : '09:00', $day);
    } else {
        $plan = cm_day_plan($queue);
    }
} catch (Throwable $ex) {

}











$boundary = null;
$suggestion = null;










$focusTasks = array_slice($queue, 0, 3);
$recentDone = cm_recent_work(3, 90);




$focusProg = cm_job_progress_many(array_merge(
    array_column($focusTasks, 'job_id'),
    array_column($recentDone, 'job_id')));

$queueHead = null;
try {
    if (!$isPast && !$isFuture) {
        $boundary = cm_next_boundary();
        if ($boundary !== null && $queue) {
            $queueHead = $queue[0];
            $headEst = (int) $queueHead['est_minutes'] > 0
                ? (int) $queueHead['est_minutes'] : (int) ($queueHead['action_minutes'] ?? 0);
            if ($headEst > $boundary['minutes']) {
                $suggestion = cm_suggest_fitting_task($queue, $boundary['minutes']);
            }
        }
    }
} catch (Throwable $ex) {
}

$dueReminders = [];
$upcomingAppts = [];
$replyFeed = [];
$replyMailRows = [];
try {
    $dueReminders = cm_reminders_due();
    $upcomingAppts = cm_appointments_between(date('Y-m-d', strtotime('+1 day')), date('Y-m-d', strtotime('+7 days')));



    $replyFeed = array_slice(cm_replies_feed(), 0, 6);
    $replyMailRows = [];
    $ids = [];
    foreach ($replyFeed as $r) { if ($r['kind'] === 'mailreply') $ids[] = (int) $r['id']; }
    if ($ids) {
        foreach (cm_all('SELECT id, sent_to, subject, body, created_at FROM mail_log WHERE id IN ('
                        . implode(',', array_fill(0, count($ids), '?')) . ')', $ids) as $m) {
            $replyMailRows[(int) $m['id']] = $m;
        }
    }
} catch (Throwable $ex) {
}

$dayPrev = date('Y-m-d', strtotime($day . ' -1 day'));
$dayNext = date('Y-m-d', strtotime($day . ' +1 day'));

cm_head('Dashboard', '', true);
cm_render_flashes();
cm_admin_nav('');
?>
<?php 











 ?>
<div class="topbar">
  <h1>Dashboard</h1>
  <p class="topbar-day"><?= $day === $today ? 'Today' : e(date('l', strtotime($day))) ?>,
      <?= e(date('j F', strtotime($day))) ?></p>
</div>

<?php 




 ?>
<?= cm_work_card_html($admin) ?>

<?php if (!$state['up_to_date']): ?>
  <div class="note note-warn">
    <b><?= count($state['pending']) ?> database update<?= count($state['pending']) === 1 ? '' : 's' ?> waiting.</b>
    <a href="<?= e(cm_url('admin/update.php')) ?>">Run the update</a>.
  </div>
<?php endif; ?>
<?php if ($worst === 'fail'): ?>
  <div class="note note-error">
    <b>The health check found something that needs fixing.</b>
    <a href="<?= e(cm_url('admin/update.php')) ?>">See what it is</a>.
  </div>
<?php endif; ?>

<?php 














 ?>
<?php
$obBack = [];
try {
    require_once CM_LIB . '/onboarding.php';
    $obBack = cm_all("SELECT r.id, r.completed_at, c.first_name, c.last_name, c.company
                        FROM onboarding_response r
                   LEFT JOIN client c ON c.id = r.client_id
                       WHERE r.status = 'complete'
                         AND r.completed_at >= ?
                    ORDER BY r.completed_at DESC",
                     [date('Y-m-d H:i:s', strtotime('-7 days'))]);
} catch (Throwable $ex) {}
?>
<?php if ($obBack): ?>
  <div class="note note-ok">
    <b><?= count($obBack) === 1
        ? e(cm_client_display_name(['id' => 0, 'first_name' => $obBack[0]['first_name'],
             'last_name' => $obBack[0]['last_name'], 'company' => $obBack[0]['company'], 'email' => '']))
          . ' filled in their onboarding form.'
        : count($obBack) . ' onboarding forms came back.' ?></b>
    <a href="<?= e(cm_url('admin/onboarding.php')) ?>">Read
      <?= count($obBack) === 1 ? 'it' : 'them' ?></a>.
  </div>
<?php endif; ?>

<?php if ($boundary !== null && ($suggestion !== null || $boundary['minutes'] <= 60)): ?>
  <div class="note note-info">
    <b><?= e(cm_minutes_label(max(1, (int) $boundary['minutes']))) ?> until <?= e($boundary['what']) ?></b>
    (<?= e(date('g:ia', strtotime($boundary['at'] . ':00'))) ?>).
    <?php if ($suggestion !== null): $sEst = (int) $suggestion['est_minutes'] > 0 ? (int) $suggestion['est_minutes'] : (int) $suggestion['action_minutes']; ?>
      The next task in line needs more time than that — <b>“<?= e((string) $suggestion['action_name']) ?>”</b>
      (~<?= e(cm_minutes_label($sEst)) ?>, <?= e((string) $suggestion['job_name']) ?>) fits instead.
    <?php elseif ($queueHead !== null): ?>
      What is next in the queue still fits.
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="day-grid">
<div>

  <?php 






 ?>
  <?php 






 ?>
  <div class="tiles-2 mb-16">

    <section class="card">
      <div class="card-head">
        <h2>Next up</h2>
        <span class="spacer"></span>
        <a class="small" href="<?= e(cm_url('admin/workflow.php')) ?>">Show all &rarr;</a>
      </div>
      <?php if (!$focusTasks): ?>
        <p class="muted small">Nothing waiting. Every task on every active job
        is either done or scheduled for later.</p>
      <?php else: ?>
        <ul class="focus-list">
          <?php foreach ($focusTasks as $o): ?>
            <?php
              $late = (string) $o['scheduled_date'] < $today;
              $mins = (int) $o['est_minutes'] > 0
                    ? (int) $o['est_minutes'] : (int) ($o['action_minutes'] ?? 0);
              $rowVip = (int) ($o['client_vip'] ?? 0) === 1;
            ?>
            <?php 
 ?>
            <?php $rowProg = $focusProg[(int) $o['job_id']] ?? null; ?>
            <li class="click-tile<?= $rowVip ? ' is-vip' : '' ?>">
              <div class="focus-main">
              <?php 






 ?>
              <a class="focus-name tile-link" href="<?= e(cm_url('admin/task.php?id=' . (int) $o['id'])) ?>"><?=
                e((string) $o['action_name']) ?></a>
              <div class="focus-sub">
                <?= e(cm_client_display_name($o)) ?> &middot; <?= e((string) $o['job_name']) ?>
              </div>
              <div class="focus-meta">
                <?php 





 ?>
                <?php if ($rowVip): ?><span class="pill pill-vip">VIP</span><?php endif; ?>
                <?php if ($late): ?>
                  <span class="pill pill-spam">overdue<?= e(date(' j M', strtotime((string) $o['scheduled_date']))) ?></span>
                <?php elseif ((string) $o['scheduled_date'] === $today): ?>
                  <span class="pill pill-live">today</span>
                <?php else: ?>
                  <span class="pill pill-pending"><?= e(date('j M', strtotime((string) $o['scheduled_date']))) ?></span>
                <?php endif; ?>
                <?php if ($mins > 0): ?>
                  <span class="mono small muted"><?= e(cm_minutes_label($mins)) ?></span>
                <?php endif; ?>
              </div>
              </div>
              <?php 




 ?>
              <?php if ($rowProg !== null): ?>
                <span class="focus-count mono" title="<?= (int) $rowProg['done'] ?> of <?= (int) $rowProg['total'] ?> required tasks on this job are done"><?=
                  (int) $rowProg['done'] ?>/<?= (int) $rowProg['total'] ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head">
        <h2>Recently completed</h2>
        <span class="spacer"></span>
        <a class="small" href="<?= e(cm_url('admin/workflow.php#recent')) ?>">Show all &rarr;</a>
      </div>
      <?php if (!$recentDone): ?>
        <p class="muted small">Nothing finished in the last three months. As
        you complete tasks they land here.</p>
      <?php else: ?>
        <ul class="focus-list">
          <?php foreach ($recentDone as $o): $rowVip = (int) ($o['client_vip'] ?? 0) === 1; ?>
            <?php $rowProg = $focusProg[(int) $o['job_id']] ?? null; ?>
            <li class="click-tile<?= $rowVip ? ' is-vip' : '' ?>">
              <div class="focus-main">
              <?php 






 ?>
              <a class="focus-name tile-link" href="<?= e(cm_url('admin/task.php?id=' . (int) $o['id'])) ?>"><?=
                e((string) $o['action_name']) ?></a>
              <div class="focus-sub">
                <?= e(cm_client_display_name($o)) ?> &middot; <?= e((string) $o['job_name']) ?>
              </div>
              <div class="focus-meta">
                <?php if ($rowVip): ?><span class="pill pill-vip">VIP</span><?php endif; ?>
                <span class="mono small muted"><?= e(date('j M, g:ia', strtotime((string) $o['completed_at']))) ?></span>
              </div>
              </div>
              <?php 




 ?>
              <?php if ($rowProg !== null): ?>
                <span class="focus-count mono" title="<?= (int) $rowProg['done'] ?> of <?= (int) $rowProg['total'] ?> required tasks on this job are done"><?=
                  (int) $rowProg['done'] ?>/<?= (int) $rowProg['total'] ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

  </div>

  <?php if ($dueReminders): ?>
    <div class="card card-rail">
      <h2>Reminders — <?= count($dueReminders) ?> due</h2>
      <div class="table-wrap">
        <table>
          <tbody>
          <?php foreach ($dueReminders as $r):
              $overdueR = (string) $r['due_date'] < $today;
              $rowVip   = (int) ($r['client_vip'] ?? 0) === 1; ?>
            <?php 


 ?>
            <tr class="<?= trim(($overdueR ? 'occ-overdue ' : '') . ($rowVip ? 'is-vip' : '')) ?>">
              <td class="mono small occ-date nowrap"><?= e(date('D j M', strtotime((string) $r['due_date']))) ?></td>
              <td><b><?= e((string) $r['title']) ?></b>
                <?php if ($r['client_id']): ?>
                  <span class="muted small">— <a href="<?= e(cm_url('admin/client.php?id=' . (int) $r['client_id'])) ?>"><?= e(cm_client_display_name(['id' => $r['client_id'], 'first_name' => $r['first_name'], 'last_name' => $r['last_name'], 'company' => $r['company'], 'email' => $r['client_email']])) ?></a></span>
                  <?php if ($rowVip): ?><span class="pill pill-vip">VIP</span><?php endif; ?>
                <?php endif; ?>
              </td>
              <td class="num">
                <form method="post" class="inline">
                  <?= cm_csrf_field() ?>
                  <input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>">
                  <button class="btn btn-sm" name="action" value="rem_done">Done</button>
                  <button class="btn btn-sm btn-quiet" name="rem_snooze_days" value="1">Snooze 1d</button>
                  <button class="btn btn-sm btn-quiet" name="rem_snooze_days" value="7">1w</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="hint"><a href="<?= e(cm_url('admin/reminders.php')) ?>">All reminders</a>,
      including the ones not due yet.</p>
    </div>
  <?php endif; ?>

  <?php 




 ?>
  <div class="split-2 mb-16">
    <section class="card m-0" id="messages-tile">
      <div class="card-head">
        <h2>Messages</h2>
        <span class="spacer"></span>
        <a class="small" href="<?= e(cm_url('admin/mail.php?f=replies')) ?>">See all &rarr;</a>
      </div>
      <?php if (!$replyFeed): ?>
        <div class="empty-state compact-empty">
          <p><b>No replies yet.</b></p>
          <p class="muted small">Client replies and completed question forms will appear here.</p>
        </div>
      <?php else: ?>
        <?php foreach ($replyFeed as $r): ?>
          <div class="reply-row">
            <span class="reply-when mono small muted nowrap"><?= $r['sort'] ? e(date('D j M', (int) $r['sort'])) : '&mdash;' ?></span>
            <span class="reply-what small">
              <b><?= e($r['who'] !== '' ? $r['who'] : '—') ?></b>
              <span class="muted">— <?= e($r['title']) ?><?= $r['kind'] === 'questions' ? ', answered' : ', replied' ?></span>
            </span>
            <button class="btn btn-sm btn-quiet" type="button"
                    data-modal-open="dlg-r-<?= $r['kind'] === 'questions' ? 'q' : 'm' ?>-<?= (int) $r['id'] ?>">Read</button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
    <section class="card m-0">
      <h2>Coming up</h2>
      <?php if (!$upcomingAppts): ?>
        <p class="muted small m-0">No appointments in the next seven days.</p>
      <?php else: ?>
      <?php foreach ($upcomingAppts as $a): ?>
        <?php $apptVip = (int) ($a['client_vip'] ?? 0) === 1;
              $apptMinutes = max(0, cm_hm_minutes(substr((string) $a['end_time'], 0, 5))
                                  - cm_hm_minutes(substr((string) $a['start_time'], 0, 5))); ?>
        <div class="rail-item rail-fixed<?= $apptVip ? ' is-vip' : '' ?>">
          <span class="rail-time mono small"><?= e(date('D j M', strtotime((string) $a['on_date']))) ?></span>
          <span class="small"><?= e(date('g:ia', strtotime((string) $a['start_time']))) ?>
            <b><?= e((string) $a['title']) ?></b>
            <?php if ($apptMinutes > 0): ?><span class="mono muted"><?= e(cm_minutes_label($apptMinutes)) ?></span><?php endif; ?>
            <?php if ($a['client_id']): ?><span class="muted">— <?= e(cm_client_display_name(['id' => $a['client_id'], 'first_name' => $a['first_name'], 'last_name' => $a['last_name'], 'company' => $a['company'], 'email' => $a['client_email']])) ?></span><?php endif; ?>
            <?php if ($apptVip): ?><span class="pill pill-vip">VIP</span><?php endif; ?>
          </span>
        </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </div>

  <div class="today-split mb-16">
    <div class="stats stats-2x2">
      <a class="stat<?= $counts['overdue'] > 0 ? ' stat-bad' : '' ?>"
         href="<?= e(cm_url('admin/workflow.php?f=overdue')) ?>">
        <span class="n"><?= $counts['overdue'] ?></span>
        <span class="l">overdue</span>
      </a>
      <a class="stat" href="<?= e(cm_url('admin/workflow.php?f=today')) ?>">
        <span class="n"><?= $counts['today'] ?></span>
        <span class="l">due today</span>
      </a>
      <a class="stat<?= $doneToday > 0 ? ' stat-ok' : '' ?>"
         href="<?= e(cm_url('admin/workflow.php')) ?>">
        <span class="n"><?= $doneToday ?></span>
        <span class="l">done today</span>
      </a>
      <a class="stat" href="<?= e(cm_url('admin/workflow.php')) ?>">
        <span class="n"><?= $counts['upcoming'] ?></span>
        <span class="l">coming up</span>
      </a>
    </div>

    <?php $t = cm_insight_today($day); ?>
    <section class="daybox">
      <h2 class="daybox-h"><?= $day === $today ? 'Today' : e(date('l j F', strtotime($day))) ?></h2>

      <?php 



 ?>
      <?php if ($t['tasks'] === 0 && $t['done'] === 0 && $t['appts'] === 0): ?>
        <p class="muted small">Nothing scheduled.
          <?php if ($t['offwork_minutes'] > 0): ?>
            <?= e(cm_minutes_label($t['offwork_minutes'])) ?> booked as not work.
          <?php endif; ?>
        </p>
      <?php else: ?>
        <div class="daybox-figs">
          <div class="fig">
            <div class="fig-n mono"><?= (int) $t['tasks'] ?></div>
            <div class="fig-l"><?= $t['tasks'] === 1 ? 'task' : 'tasks' ?> to do</div>
          </div>
          <div class="fig">
            <div class="fig-n mono"><?= e(cm_minutes_label($t['minutes'] + $t['appts_minutes'])) ?></div>
            <div class="fig-l">of work left</div>
          </div>
          <div class="fig">
            <?php 

 ?>
            <div class="fig-n mono<?= $t['value_cents'] > 0 ? ' fig-money' : '' ?>">
              <?= $t['value_cents'] > 0 ? e(cm_money($t['value_cents'])) : '&mdash;' ?>
            </div>
            <div class="fig-l">estimated revenue</div>
          </div>
          <div class="fig">
            <div class="fig-n mono"><?= (int) $t['done'] ?></div>
            <div class="fig-l">already done</div>
          </div>
        </div>

        <ul class="daybox-notes">
          <?php if ($t['value_cents'] > 0): ?>
            <li>
              <?php if ($t['done'] > 0 && $t['tasks'] > 0): ?>
                <b><?= e(cm_money($t['value_done'])) ?></b> of that is done,
                <b><?= e(cm_money($t['value_todo'])) ?></b> still to earn.
              <?php elseif ($t['tasks'] === 0): ?>
                All <b><?= e(cm_money($t['value_cents'])) ?></b> of it done.
              <?php else: ?>
                None of it done yet.
              <?php endif; ?>
              <span class="muted">Each job&rsquo;s price divided across the days it
              runs over — an estimate, not an invoice.</span>
            </li>
          <?php endif; ?>
          <?php if ($t['appts'] > 0): ?>
            <li><?= (int) $t['appts'] ?> appointment<?= $t['appts'] === 1 ? '' : 's' ?>,
              <?= e(cm_minutes_label($t['appts_minutes'])) ?> &mdash; counted as work.</li>
          <?php endif; ?>
          <?php if ($t['offwork_minutes'] > 0): ?>
            <?php 
 ?>
            <li><?= e(cm_minutes_label($t['offwork_minutes'])) ?> booked as
              <b>not work</b> &mdash; held in the diary, counted in nothing.</li>
          <?php endif; ?>
          <?php if ($t['unpriced'] > 0): ?>
            <li><?= (int) $t['unpriced'] ?> task<?= $t['unpriced'] === 1 ? '' : 's' ?>
              on a job with no price, so worth nothing above.
              <a href="<?= e(cm_url('admin/jobs.php')) ?>">Price them</a>.</li>
          <?php endif; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <?php 
 ?>
  <?php foreach ($replyFeed as $r): ?>
    <?= cm_reply_window_html($r, $r['kind'] === 'mailreply' ? ($replyMailRows[(int) $r['id']] ?? null) : null) ?>
  <?php endforeach; ?>

</div><?php  ?>

<?php  ?>
<aside class="day-rail">
  <div class="card">
    <div class="card-head gap-8">
      <a class="btn btn-sm btn-quiet" href="<?= e(cm_url('admin/?day=' . $dayPrev)) ?>" aria-label="Previous day">‹</a>
      <h2 class="t-ui m-0 dayhead-t">
        <?= $day === $today ? 'Today' : e(date('l', strtotime($day))) ?>
        <span class="muted small fw-400"><?= e(date('j M', strtotime($day))) ?></span>
      </h2>
      <a class="btn btn-sm btn-quiet" href="<?= e(cm_url('admin/?day=' . $dayNext)) ?>" aria-label="Next day">›</a>
      <span class="spacer"></span>
      <?php if ($day !== $today): ?>
        <a class="btn btn-sm" href="<?= e(cm_url('admin/')) ?>">Today</a>
      <?php endif; ?>
    </div>

    <?php if ($isPast): ?>
      <?php if (!$history): ?>
        <p class="muted small">Nothing was scheduled for this day.</p>
      <?php else: ?>
        <div class="rail">
          <?php foreach ($history as $o):
              $ost = cm_occurrence_statuses()[(string) $o['status']];
              $stillOpen = cm_occurrence_is_open($o);
              $rowVip = (int) ($o['client_vip'] ?? 0) === 1; ?>
            <?php 



 ?>
            <div class="rail-item click-tile <?= $stillOpen ? 'rail-overdue' : 'rail-passed' ?><?= $rowVip ? ' is-vip' : '' ?>">
              <span class="rail-time mono"><?= $o['completed_at'] ? e(date('g:ia', strtotime((string) $o['completed_at']))) : '—' ?></span>
              <span class="small"><a class="tile-link" href="<?= e(cm_url('admin/task.php?id=' . (int) $o['id'])) ?>"><?= e((string) $o['action_name']) ?></a>
                <span class="muted">— <?= e(cm_client_display_name(['id' => $o['client_id'], 'first_name' => $o['first_name'], 'last_name' => $o['last_name'], 'company' => $o['company'], 'email' => $o['client_email']])) ?></span>
                <?php if ($rowVip): ?><span class="pill pill-vip">VIP</span><?php endif; ?>
                <span class="pill <?= e($ost['pill']) ?>"><?= $stillOpen ? 'still open' : e($ost['label']) ?></span>
                <?php if ((int) $o['minutes_spent'] > 0): ?><span class="muted mono"><?= e(cm_minutes_label((int) $o['minutes_spent'])) ?></span><?php endif; ?>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="hint">History answers for the day work was DUE. A "still
        open" task is simply overdue — it is on today's rail too.</p>
      <?php endif; ?>

    <?php elseif (!$plan): ?>
      <p class="muted small">Nothing planned. Tasks land here as they fall
      due; appointments and your workday times (Settings → Your workday)
      give the day its shape.</p>
    <?php else: ?>
      <div class="rail">
        <?php foreach ($plan as $item): ?>
          <?php if ($item['type'] === 'start' || $item['type'] === 'end'): ?>
            <div class="rail-item rail-fixed">
              <span class="rail-time mono"><?= e(date('g:ia', strtotime($item['at'] . ':00'))) ?></span>
              <span class="muted small"><?= e($item['label']) ?></span>
            </div>

          <?php elseif ($item['type'] === 'break'): ?>
            <div class="rail-item rail-fixed<?= !empty($item['passed']) ? ' rail-passed' : '' ?>">
              <span class="rail-time mono"><?= e(date('g:ia', strtotime($item['at'] . ':00'))) ?></span>
              <span class="small">Break
                <span class="muted">until <?= e(date('g:ia', strtotime($item['until'] . ':00'))) ?></span></span>
            </div>

          <?php elseif ($item['type'] === 'appt'): $a = $item['appt'];



                $apptVip = $a !== null && (int) ($a['client_vip'] ?? 0) === 1;
                $apptMinutes = max(0, cm_hm_minutes((string) $item['until']) - cm_hm_minutes((string) $item['at'])); ?>
            <details class="rail-item rail-appt<?= !empty($item['passed']) ? ' rail-passed' : '' ?><?= $apptVip ? ' is-vip' : '' ?>">
              <summary>
                <span class="rail-time mono"><?= e(date('g:ia', strtotime($item['at'] . ':00'))) ?></span>
                <span class="small"><b><?= e($item['label']) ?></b>
                  <?php if ($item['who'] !== ''): ?><span class="muted">— <?= e($item['who']) ?></span><?php endif; ?>
                  <?php if ($apptVip): ?><span class="pill pill-vip">VIP</span><?php endif; ?>
                  <?php if ($apptMinutes > 0): ?><span class="mono muted"><?= e(cm_minutes_label($apptMinutes)) ?></span><?php endif; ?>
                  <span class="muted">until <?= e(date('g:ia', strtotime($item['until'] . ':00'))) ?></span>
                </span>
              </summary>
              <div class="rail-body">
                <?php if ($a !== null && $a['client_id']): ?>
                  <a class="btn btn-sm btn-quiet" href="<?= e(cm_url('admin/client.php?id=' . (int) $a['client_id'])) ?>">Open the client</a>
                <?php endif; ?>
                <?php if ($a !== null): ?>
                  <form method="post" class="inline" data-confirm="Remove this appointment?">
                    <?= cm_csrf_field() ?>
                    <input type="hidden" name="action" value="appt_delete">
                    <input type="hidden" name="appt_id" value="<?= (int) $a['id'] ?>">
                    <button class="btn btn-sm btn-quiet" type="submit">Remove</button>
                  </form>
                <?php endif; ?>
              </div>
            </details>

          <?php else: $o = $item['row'];
                $overdueT = (string) $o['scheduled_date'] < $today;
                $running = (string) $o['status'] === 'in_progress';
                $rowVip = (int) ($o['client_vip'] ?? 0) === 1; ?>
            <?php 


 ?>
            <?php 













 ?>
            <div class="rail-item rail-task click-tile<?= $overdueT ? ' rail-overdue' : '' ?><?= $running ? ' rail-running' : '' ?><?= $rowVip ? ' is-vip' : '' ?>">
              <span class="rail-time mono">≈<?= e(date('g:ia', strtotime($item['at'] . ':00'))) ?></span>
              <span class="small">
                <a class="tile-link" href="<?= e(cm_url('admin/task.php?id=' . (int) $o['id'])) ?>"><?= e((string) $o['action_name']) ?></a>
                <span class="muted">— <?= e(cm_client_display_name(['id' => $o['client_id'], 'first_name' => $o['first_name'], 'last_name' => $o['last_name'], 'company' => $o['company'], 'email' => $o['client_email']])) ?></span>
                <?php if ($rowVip): ?><span class="pill pill-vip">VIP</span><?php endif; ?>
                <span class="muted mono">~<?= e(cm_minutes_label((int) $item['est'])) ?><?= !empty($item['guessed']) ? '?' : '' ?></span>
                <?= $overdueT ? '<span class="pill pill-spam">overdue</span>' : '' ?>
                <?= $running ? '<span class="pill pill-live">timing</span>' : '' ?>
                <?= !empty($item['after_hours']) ? '<span class="pill pill-pending">past day end</span>' : '' ?>
                <?php 

 ?>
                <?= !empty($item['overtime']) ? '<span class="pill pill-live">overtime</span>' : '' ?>
              </span>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php 

 ?>
    <button class="btn btn-sm mt-10" type="button" data-modal-open="dlg-new-appt">Add an appointment</button>
  </div>
</aside>
</div><?php  ?>


<?php 

















 ?>
<div class="tiles-2 mb-16">

  <?php 



 ?>
  <section class="tile<?= $setup['pct'] === 100 ? ' tile-fit' : ' tile-accent' ?>">
    <div class="tile-h">
      <h2><?= $setup['pct'] === 100 ? 'Setup complete' : 'Finish your setup' ?></h2>
      <span class="spacer"></span>
      <span class="muted small"><?= $setup['done'] ?> of <?= $setup['total'] ?></span>
    </div>

    <?php if ($setup['pct'] === 100): ?>
      <p class="muted small">Everything is configured — sender details, services,
      clients, your workday and two-step sign-in.</p>
    <?php else: ?>
      <div class="prog">
        <div class="prog-bar"><div class="prog-fill" data-pct="<?= (int) $setup['pct'] ?>"></div></div>
      </div>
      <?php if ($setup['critical_missing'] > 0): ?>
        <p class="small txt-err mt-8"><b><?= $setup['critical_missing'] ?>
          essential step<?= $setup['critical_missing'] === 1 ? '' : 's' ?></b>
          still missing.</p>
      <?php endif; ?>
      <ul class="checklist">
        <?php foreach ($setupSteps as $st): ?>
          <li class="<?= $st['done'] ? 'is-done' : '' ?>">
            <span class="ck" aria-hidden="true"><?= $st['done'] ? '&#10003;' : '' ?></span>
            <?php if ($st['done']): ?>
              <span><?= e($st['label']) ?></span>
            <?php else: ?>
              <a href="<?= e(cm_url($st['href'])) ?>"><?= e($st['label']) ?></a>
              <?php if ($st['critical']): ?><span class="pill pill-pending">needed</span><?php endif; ?>
              <span class="muted small ck-blurb"><?= e($st['blurb']) ?></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <?php 







 ?>
  <section class="tile">
    <div class="tile-h">
      <h2>Work value</h2>
      <span class="spacer"></span>
      <a class="small" href="<?= e(cm_url('admin/reports.php')) ?>">Reports &rarr;</a>
    </div>

    <?php if (!$activeJobs): ?>
      <p class="muted small">No active jobs yet. Give a job a value and its
      dates and the figures appear here.</p>
    <?php else: ?>
      <div class="figs">
        <div class="fig">
          <div class="fig-n mono"><?= e(cm_money((int) round($annualCents / 12))) ?></div>
          <div class="fig-l">a month, active work</div>
        </div>
        <div class="fig">
          <div class="fig-n mono"><?= e(cm_minutes_label($minutesMonth)) ?></div>
          <div class="fig-l">tracked this month</div>
        </div>
        <div class="fig">
          <div class="fig-n mono"><?= $rateCents === null ? '&mdash;' : e(cm_money($rateCents)) ?></div>
          <div class="fig-l"><?= $rateCents === null
            ? 'no hours tracked yet'
            : 'an hour, across all tracked time' ?></div>
        </div>
        <div class="fig">
          <div class="fig-n mono"><?= (int) $jobsEndingSoon ?></div>
          <div class="fig-l">finishing within 7 days</div>
        </div>
      </div>

      <?php if ($minutesTotal > 0): ?>
        <div class="spark" role="img"
             aria-label="Time tracked over the last six months">
          <?php foreach ($trend as $tr): ?>
            <div class="sp-col" title="<?= e($tr['label']) ?>: <?= e(cm_minutes_label($tr['minutes'])) ?>">
              <div class="sp-bar" data-h="<?= max(3, (int) round($tr['minutes'] / $trendMax * 100)) ?>"></div>
              <div class="sp-lb"><?= e($tr['label']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="muted small">Time banked, last six months.
        <?= count($activeJobs) ?> active job<?= count($activeJobs) === 1 ? '' : 's' ?>.</p>
      <?php endif; ?>
    <?php endif; ?>
  </section>

</div>

<?php 











 ?>

<?php cm_foot(); ?>
