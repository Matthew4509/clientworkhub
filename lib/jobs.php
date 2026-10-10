<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0
























declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }


require_once CM_LIB . '/migrate.php';






require_once CM_LIB . '/format.php';
require_once CM_LIB . '/schedule.php';




require_once CM_LIB . '/clients.php';



const CM_OCCURRENCE_HORIZON_DAYS = 62;



function cm_job_statuses(): array
{
    return [
        'draft'     => ['label' => 'Draft',             'pill' => 'pill-pending',
                        'blurb' => 'Being set up — generates no work yet.'],
        'active'    => ['label' => 'Active',            'pill' => 'pill-approved',
                        'blurb' => 'Running. Its tasks appear in the work queue.'],







        'hold'      => ['label' => 'On hold',           'pill' => 'pill-pending',
                        'blurb' => 'Tasks pause and nothing counts as overdue.'],
        'completed' => ['label' => 'Completed',         'pill' => 'pill-approved',
                        'blurb' => 'Done. Can be reopened — history stays.'],
        'cancelled' => ['label' => 'Cancelled',         'pill' => 'pill-spam',
                        'blurb' => 'Never finished, records kept.'],
    ];
}

function cm_job_priorities(): array
{
    return [
        'critical' => ['label' => 'Critical', 'pill' => 'pill-spam'],
        'high'     => ['label' => 'High',     'pill' => 'pill-live'],
        'normal'   => ['label' => 'Normal',   'pill' => 'pill-pending'],
        'low'      => ['label' => 'Low',      'pill' => 'pill-pending'],
    ];
}

function cm_action_frequencies(): array
{
    return [
        'oneoff'  => 'One-off',
        'daily'   => 'Daily',
        'weekly'  => 'Weekly',
        'monthly' => 'Monthly',
    ];
}



function cm_occurrence_statuses(): array
{
    return [
        'scheduled'   => ['label' => 'Scheduled',   'pill' => 'pill-pending'],
        'in_progress' => ['label' => 'In progress', 'pill' => 'pill-live'],
        'completed'   => ['label' => 'Done',        'pill' => 'pill-approved'],
        'skipped'     => ['label' => 'Skipped',     'pill' => 'pill-pending'],
        'cancelled'   => ['label' => 'Cancelled',   'pill' => 'pill-spam'],
        'rescheduled' => ['label' => 'Moved',       'pill' => 'pill-pending'],
    ];
}



function cm_job(int $id): ?array
{
    if ($id <= 0) return null;
    return cm_row('SELECT * FROM job WHERE id = ?', [$id]);
}

function cm_client_jobs(int $clientId): array
{
    return cm_all('SELECT * FROM job WHERE client_id = ?
                   ORDER BY status = \'completed\', status = \'cancelled\', updated_at DESC', [$clientId]);
}


function cm_job_asset_ids(int $jobId): array
{
    return array_map('intval', array_column(
        cm_all('SELECT asset_id FROM job_asset WHERE job_id = ?', [$jobId]), 'asset_id'));
}






























function cm_job_asset_names(array $jobIds): array
{
    $ids = array_values(array_unique(array_map('intval', $jobIds)));
    $ids = array_values(array_filter($ids, static fn (int $i): bool => $i > 0));
    if (!$ids) return [];

    $in   = implode(',', array_fill(0, count($ids), '?'));
    $out  = [];
    foreach (cm_all("SELECT ja.job_id, a.name
                       FROM job_asset ja JOIN client_asset a ON a.id = ja.asset_id
                      WHERE ja.job_id IN ($in)
                      ORDER BY a.name, a.id", $ids) as $r) {
        $out[(int) $r['job_id']][] = (string) $r['name'];
    }
    return $out;
}
















function cm_job_lead_label(array $assetNames, string $jobName): string
{






    $names = [];
    $seen  = [];
    foreach ($assetNames as $n) {
        $n = trim((string) $n);
        if ($n === '') continue;
        $fold = mb_strtolower($n);
        if (isset($seen[$fold])) continue;
        $seen[$fold] = true;
        $names[] = $n;
    }
    if (!$names)             return $jobName;
    if (count($names) === 1) return $names[0];
    if (count($names) === 2) return $names[0] . ' & ' . $names[1];
    return $names[0] . ' +' . (count($names) - 1) . ' more';
}





function cm_job_assets_set(int $jobId, int $clientId, array $assetIds): void
{
    $valid = array_map('intval', array_column(
        cm_all('SELECT id FROM client_asset WHERE client_id = ?', [$clientId]), 'id'));
    $keep = array_values(array_intersect(array_map('intval', $assetIds), $valid));

    cm_q('DELETE FROM job_asset WHERE job_id = ?', [$jobId]);
    foreach ($keep as $aid) {
        cm_q('INSERT INTO job_asset (job_id, asset_id) VALUES (?, ?)', [$jobId, $aid]);
    }
}








function cm_action_dates(array $action, string $until): array
{
    $start = (string) $action['start_date'];
    $end   = (string) ($action['end_date'] ?? '');
    $stop  = ($end !== '' && $end < $until) ? $end : $until;
    if ($start === '' || $stop < $start) return [];

    $freq = (string) $action['frequency'];
    if ($freq === 'oneoff') return [$start];

    $dates = [];
    $cur = new DateTimeImmutable($start);
    $stopD = new DateTimeImmutable($stop);

    if ($freq === 'daily') {



        while ($cur <= $stopD) {
            if (cm_is_workday($cur->format('Y-m-d'))) {
                $dates[] = $cur->format('Y-m-d');
            }
            $cur = $cur->modify('+1 day');
        }
    } elseif ($freq === 'weekly') {
        while ($cur <= $stopD) {
            $dates[] = $cur->format('Y-m-d');
            $cur = $cur->modify('+7 days');
        }
    } elseif ($freq === 'monthly') {




        $day = (int) (new DateTimeImmutable($start))->format('j');
        $y = (int) $cur->format('Y');
        $m = (int) $cur->format('n');
        while (true) {
            $dim = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m)))->format('t');
            $d = sprintf('%04d-%02d-%02d', $y, $m, min($day, $dim));
            if ($d > $stop) break;
            if ($d >= $start) $dates[] = $d;
            $m++;
            if ($m > 12) { $m = 1; $y++; }
        }
    }
    return $dates;
}









function cm_action_generate(array $action): void
{
    $until = (new DateTimeImmutable('today'))
        ->modify('+' . CM_OCCURRENCE_HORIZON_DAYS . ' days')->format('Y-m-d');
    $wanted = cm_action_dates($action, $until);
    $wantedSet = array_flip($wanted);



















    $moved = cm_all("SELECT DISTINCT moved_to FROM action_occurrence
                      WHERE action_id = ? AND moved_to IS NOT NULL", [(int) $action['id']]);
    foreach ($moved as $m) {
        $to = (string) $m['moved_to'];
        if ($to !== '') $wantedSet[$to] = true;
    }

    $existing = cm_all('SELECT id, scheduled_date, status FROM action_occurrence WHERE action_id = ?',
                       [(int) $action['id']]);
    $have = [];
    foreach ($existing as $row) {
        $have[(string) $row['scheduled_date']] = $row;
        if ((string) $row['status'] === 'scheduled'
            && !isset($wantedSet[(string) $row['scheduled_date']])) {
            cm_q('DELETE FROM action_occurrence WHERE id = ? AND status = \'scheduled\'',
                 [(int) $row['id']]);
        }
    }
    foreach ($wanted as $date) {
        if (isset($have[$date])) continue;
        cm_q('INSERT IGNORE INTO action_occurrence
                (action_id, job_id, scheduled_date, created_at, updated_at)
              VALUES (?, ?, ?, ?, ?)',
             [(int) $action['id'], (int) $action['job_id'], $date, cm_now(), cm_now()]);
    }
}















function cm_job_own_generate(array $job): void
{
    $jobId = (int) $job['id'];




    if ((string) $job['frequency'] === '') return;

    $until = (new DateTimeImmutable('today'))
        ->modify('+' . CM_OCCURRENCE_HORIZON_DAYS . ' days')->format('Y-m-d');
    $wanted = cm_action_dates($job, $until);
    if (!$wanted) return;
    $wantedSet = array_flip($wanted);









    $dur = (int) ($job['duration_days'] ?? 0);
    if ($dur > 0 && count($wanted) > $dur) {
        $wanted = array_slice($wanted, 0, $dur);
        $wantedSet = array_flip($wanted);
    }






    if ((string) $job['completion_mode'] === 'own_day'
        && (int) $job['completion_minutes'] > 0) {
        $wantedSet[cm_workday_after((string) end($wanted))] = true;
    }



    $moved = cm_all("SELECT DISTINCT moved_to FROM action_occurrence
                      WHERE job_id = ? AND action_id IS NULL AND moved_to IS NOT NULL",
                    [$jobId]);
    foreach ($moved as $m) {
        $to = (string) $m['moved_to'];
        if ($to !== '') $wantedSet[$to] = true;
    }

    $existing = cm_all('SELECT id, scheduled_date, status FROM action_occurrence
                         WHERE job_id = ? AND action_id IS NULL', [$jobId]);
    $have = [];
    foreach ($existing as $row) {
        $have[(string) $row['scheduled_date']] = $row;
        if ((string) $row['status'] === 'scheduled'
            && !isset($wantedSet[(string) $row['scheduled_date']])) {
            cm_q('DELETE FROM action_occurrence WHERE id = ? AND status = \'scheduled\'',
                 [(int) $row['id']]);
        }
    }
    foreach (array_keys($wantedSet) as $date) {
        if (isset($have[(string) $date])) continue;
        cm_q('INSERT IGNORE INTO action_occurrence
                (action_id, job_id, scheduled_date, created_at, updated_at)
              VALUES (NULL, ?, ?, ?, ?)',
             [$jobId, (string) $date, cm_now(), cm_now()]);
    }
}
































function cm_job_allocate(array $job): void
{
    $jobId = (int) $job['id'];

    $days = cm_all("SELECT id, scheduled_date FROM action_occurrence
                     WHERE job_id = ? AND action_id IS NULL
                     ORDER BY scheduled_date", [$jobId]);
    if (!$days) return;

    $setup      = (int) $job['setup_minutes'];
    $completion = (int) $job['completion_minutes'];
    $ownDay     = (string) $job['completion_mode'] === 'own_day' && $completion > 0;



    $spreadDays = $ownDay ? array_slice($days, 0, -1) : $days;
    if (!$spreadDays) $spreadDays = $days;
    $n = count($spreadDays);





    $perDay = (int) $job['per_day_minutes'];
    $remainder = 0;
    if ($perDay < 1) {







        $spread = (int) $job['est_minutes'] - $setup - $completion;
        if ($spread > 0) {
            $perDay    = intdiv($spread, $n);
            $remainder = $spread - ($perDay * $n);
        }
    }




    if ($perDay !== (int) $job['per_day_minutes']) {
        cm_q('UPDATE job SET per_day_minutes = ?, updated_at = ? WHERE id = ?',
             [$perDay, cm_now(), $jobId]);
    }


    $first = $perDay + $remainder + $setup;
    if ($first !== $perDay) {
        cm_q('UPDATE action_occurrence SET est_minutes = ?, updated_at = ? WHERE id = ?',
             [$first, cm_now(), (int) $spreadDays[0]['id']]);
    }

    if ($completion > 0) {
        $lastId = (int) ($ownDay ? end($days)['id'] : end($spreadDays)['id']);
        $lastMinutes = $ownDay ? $completion : $perDay + $completion;


        if (!$ownDay && (int) $spreadDays[0]['id'] === $lastId) {
            $lastMinutes = $first + $completion;
        }
        cm_q('UPDATE action_occurrence SET est_minutes = ?, updated_at = ? WHERE id = ?',
             [$lastMinutes, cm_now(), $lastId]);
    }
}








function cm_job_generate(int $jobId): void
{
    $job = cm_row('SELECT * FROM job WHERE id = ?', [$jobId]);
    if ($job) cm_job_own_generate($job);

    foreach (cm_all('SELECT * FROM job_action WHERE job_id = ?', [$jobId]) as $action) {
        cm_action_generate($action);
    }
}



function cm_occurrence(int $id): ?array
{
    return cm_row('SELECT o.*, ' . cm_occurrence_parent_fields('a', 'j') . '
                   FROM action_occurrence o
                   LEFT JOIN job_action a ON a.id = o.action_id
                   JOIN job j ON j.id = o.job_id
                   WHERE o.id = ?', [$id]);
}


function cm_job_occurrences(int $jobId): array
{
    return cm_all("SELECT o.*, " . cm_occurrence_parent_fields('a', 'j') . "
                   FROM action_occurrence o
                   LEFT JOIN job_action a ON a.id = o.action_id
                   JOIN job j ON j.id = o.job_id
                   WHERE o.job_id = ?
                   ORDER BY o.status IN ('completed','skipped','cancelled','rescheduled'),
                            o.scheduled_date, " . cm_occurrence_name_sql('a', 'j'),
                  [$jobId]);
}

function cm_occurrence_is_open(array $o): bool
{
    return in_array((string) $o['status'], ['scheduled', 'in_progress'], true);
}

function cm_occurrence_is_overdue(array $o): bool
{
    return (string) $o['status'] === 'scheduled'
        && (string) $o['scheduled_date'] < date('Y-m-d');
}




function cm_job_open_required(int $jobId): int
{
    return (int) cm_val("SELECT COUNT(*) FROM action_occurrence o
                         LEFT JOIN job_action a ON a.id = o.action_id
                         WHERE o.job_id = ? AND " . cm_occurrence_required_sql('a') . " = 1
                           AND o.status IN ('scheduled','in_progress')", [$jobId]);
}


function cm_job_progress(int $jobId): array
{
    $total = (int) cm_val('SELECT COUNT(*) FROM action_occurrence o
                           LEFT JOIN job_action a ON a.id = o.action_id
                           WHERE o.job_id = ? AND ' . cm_occurrence_required_sql('a') . ' = 1',
                          [$jobId]);
    $open = cm_job_open_required($jobId);
    return ['done' => $total - $open, 'total' => $total];
}




















function cm_recent_work(int $limit = 30, int $days = 30): array
{
    $limit = max(1, min(200, $limit));
    try {
        return cm_all(
            "SELECT o.*, " . cm_occurrence_name_sql('a', 'j') . " AS action_name,
                    j.name AS job_name, j.client_id,
                    c.first_name, c.last_name, c.company, c.email AS client_email,
                    c.vip AS client_vip
             FROM action_occurrence o
             LEFT JOIN job_action a ON a.id = o.action_id
             JOIN job j ON j.id = o.job_id
             JOIN client c ON c.id = j.client_id
             WHERE o.status = 'completed' AND o.completed_at >= ?
             ORDER BY o.completed_at DESC LIMIT " . $limit,
            [date('Y-m-d', strtotime('-' . max(1, $days) . ' days')) . ' 00:00:00']);
    } catch (Throwable $ex) {
        return [];
    }
}

function cm_work_queue(int $daysAhead = 14): array
{



    $rows = cm_all(
        "SELECT o.*, " . cm_occurrence_parent_fields('a', 'j') . ",
                j.name AS job_name, j.priority, j.amount_cents, j.billing_freq, j.client_id,
                j.status AS job_status,
                c.first_name, c.last_name, c.company, c.email AS client_email,
                /* ALIASED, not `c.vip`. A flattened join row is not a row:
                   `o.*` already carries columns of its own, and a bare `vip`
                   would be one more name in a single namespace where the
                   next migration could collide with it. */
                c.vip AS client_vip
         FROM action_occurrence o
         LEFT JOIN job_action a ON a.id = o.action_id
         JOIN job j ON j.id = o.job_id
         JOIN client c ON c.id = j.client_id
         WHERE o.status IN ('scheduled','in_progress') AND j.status = 'active'
           AND o.scheduled_date <= ?
         ORDER BY o.scheduled_date",
        [date('Y-m-d', strtotime('+' . $daysAhead . ' days'))]);





    require_once CM_LIB . '/priority.php';
    $rows = cm_priority_sort($rows);

    return $rows;
}








function cm_timer_running(): ?array
{
    return cm_row(
        "SELECT o.*, " . cm_occurrence_name_sql('a', 'j') . " AS action_name,
                j.name AS job_name, j.client_id,
                c.first_name, c.last_name, c.company, c.email AS client_email
         FROM action_occurrence o
         LEFT JOIN job_action a ON a.id = o.action_id
         JOIN job j ON j.id = o.job_id
         JOIN client c ON c.id = j.client_id
         WHERE o.status = 'in_progress'
         ORDER BY o.started_at LIMIT 1");
}






function cm_timer_start(array $occ, string $actor): string
{
    $running = cm_timer_running();
    if ($running !== null && (int) $running['id'] !== (int) $occ['id']) {
        return 'Already timing "' . (string) $running['action_name'] . '" — finish or pause that first.';
    }
    if ($running !== null) return '';
    if ((string) $occ['status'] !== 'scheduled') {
        return 'Only an open task can be started.';
    }
    cm_q("UPDATE action_occurrence SET status='in_progress', started_at=?, updated_at=? WHERE id=? AND status='scheduled'",
         [cm_now(), cm_now(), (int) $occ['id']]);
    return '';
}



function cm_timer_elapsed_minutes(array $occ): int
{
    $since = strtotime((string) $occ['started_at']);
    if ($since === false) return 1;
    return max(1, (int) round((time() - $since) / 60));
}


function cm_time_recount(int $occurrenceId): void
{
    cm_q('UPDATE action_occurrence
          SET minutes_spent = COALESCE((SELECT SUM(minutes) FROM time_entry WHERE occurrence_id = ?), 0)
          WHERE id = ?', [$occurrenceId, $occurrenceId]);
}


function cm_time_record(array $occ, int $minutes, bool $billable, string $source,
                        string $note, string $actor): void
{
    if ($minutes === 0) return;


    $startedAt = $source === 'timer' && (string) ($occ['started_at'] ?? '') !== ''
        ? (string) $occ['started_at'] : null;
    cm_insert('INSERT INTO time_entry (occurrence_id, job_id, client_id, started_at, minutes,
                                       billable, source, note, created_by, created_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
              [(int) $occ['id'], (int) $occ['job_id'],
               (int) cm_val('SELECT client_id FROM job WHERE id = ?', [(int) $occ['job_id']]),
               $startedAt,
               $minutes, $billable ? 1 : 0, $source,
               mb_substr($note, 0, 255), mb_substr($actor, 0, 190), cm_now()]);
    cm_time_recount((int) $occ['id']);
}



function cm_timer_pause(array $occ, string $actor): int
{
    $mins = cm_timer_elapsed_minutes($occ);
    cm_time_record($occ, $mins, true, 'timer', 'paused', $actor);
    cm_q("UPDATE action_occurrence SET status='scheduled', started_at=NULL, updated_at=? WHERE id=?",
         [cm_now(), (int) $occ['id']]);
    return $mins;
}


function cm_time_totals(string $scope, int $id): array
{
    $col = $scope === 'client' ? 'client_id' : ($scope === 'occurrence' ? 'occurrence_id' : 'job_id');
    $row = cm_row("SELECT COALESCE(SUM(minutes),0) AS total,
                          COALESCE(SUM(CASE WHEN billable = 1 THEN minutes ELSE 0 END),0) AS billable
                   FROM time_entry WHERE {$col} = ?", [$id]);
    return ['total' => (int) $row['total'], 'billable' => (int) $row['billable']];
}













function cm_job_annual_cents(array $job): int
{
    $amt = (int) $job['amount_cents'];
    return match ((string) $job['billing_freq']) {
        'weekly'  => $amt * 52,
        'monthly' => $amt * 12,
        'yearly'  => $amt,
        default   => $amt,
    };
}





function cm_effective_rate_cents(int $amountCents, int $billableMinutes): ?int
{
    if ($billableMinutes <= 0 || $amountCents <= 0) return null;
    return (int) round($amountCents / ($billableMinutes / 60));
}








function cm_client_value(int $clientId): array
{
    $activeAnnual = 0;
    $jobsTotal    = 0;
    $activeJobs   = 0;
    foreach (cm_all("SELECT * FROM job WHERE client_id = ? AND status <> 'cancelled'", [$clientId]) as $j) {
        $jobsTotal += (int) $j['amount_cents'];
        if (in_array((string) $j['status'], ['active', 'hold'], true)) {
            $activeAnnual += cm_job_annual_cents($j);
            $activeJobs++;
        }
    }
    $time = cm_time_totals('client', $clientId);
    return [
        'active_annual' => $activeAnnual,
        'jobs_total'    => $jobsTotal,
        'active_jobs'   => $activeJobs,
        'minutes'       => $time['total'],
        'billable'      => $time['billable'],
        'rate'          => cm_effective_rate_cents($jobsTotal, $time['billable']),
    ];
}






















function cm_occurrence_edit_window(array $o): void
{
    require_once CM_LIB . '/calendar.php';
    ?>
        <?php 
















 ?>
        <dialog class="modal modal-wider" id="dlg-occ-edit-<?= (int) $o['id'] ?>">
          <div class="modal-box">
            <div class="modal-head">
              <h2>Edit this task</h2>
              <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
            </div>
            <p class="modal-sub"><b><?= e((string) $o['action_name']) ?></b>, currently
            <?= e(date('D j M', strtotime((string) $o['scheduled_date']))) ?>.</p>

            <form method="post">
              <?= cm_csrf_field() ?>
              <input type="hidden" name="occ_id" value="<?= (int) $o['id'] ?>">

              <div class="edit-cols">
                <div>
                  <?php 

 ?>
                  <label class="lbl">The day</label>
                  <?= cm_calendar_html('occ_to', (string) $o['scheduled_date'], 'cal-edit-' . (int) $o['id']) ?>
                  <?php 




 ?>
                  <div class="hint">Choose a new date to reschedule.</div>
                </div>

                <div>
                  <?php 


























 ?>
                  <div class="row">
                    <label class="lbl" for="ednote-<?= (int) $o['id'] ?>">Note <span class="muted">(internal)</span></label>
                    <textarea id="ednote-<?= (int) $o['id'] ?>" name="occ_internal" rows="5"
                      class="f-full"><?= e((string) ($o['internal_note'] ?? '')) ?></textarea>
                    <div class="hint">Internal only. Use <b>The client&rsquo;s record</b> on the task for client-facing notes.</div>
                  </div>

                  <?php 



 ?>
                  <label class="lbl">Time</label>
                  <div class="occ-time m-0">
                    <span>Time allowed:
                      <b class="mono"><?= (int) $o['est_minutes'] > 0
                          ? e(cm_minutes_label((int) $o['est_minutes']))
                          : '&mdash;' ?></b></span>
                    <span>Time so far:
                      <b class="mono"><?= e(cm_minutes_label((int) $o['minutes_spent'])) ?></b></span>
                  </div>
                  <div class="row-2 mt-8">
                    <div class="row m-0">
                      <label class="lbl" for="edmin-<?= (int) $o['id'] ?>">Add minutes</label>
                      <input type="text" id="edmin-<?= (int) $o['id'] ?>" name="add_minutes"
                             inputmode="numeric" class="f-sm">
                    </div>
                    <div class="row m-0">
                      <label class="lbl" for="edwhy-<?= (int) $o['id'] ?>">Why</label>
                      <input type="text" id="edwhy-<?= (int) $o['id'] ?>" name="add_why"
                             class="f-full" placeholder="forgot the timer&hellip;">
                    </div>
                  </div>
                  <label class="tick mt-8">
                    <input type="checkbox" name="add_nonbillable" value="1">
                    <span><b>Not billable</b> — it still counts towards the
                    time spent, and stays out of what you charge.</span>
                  </label>
                </div>
              </div>

              <?php 


 ?>
              <div class="actions">
                <button class="btn" name="action" value="occ_edit">Save changes</button>
                <button class="btn btn-quiet" name="action" value="occ_complete">Mark as done</button>
                <button class="btn btn-quiet" name="action" value="occ_cancel"
                        data-confirm="Cancel this task? It stays on the record as cancelled and Reopen puts it back.">Cancel this task</button>
                <span class="spacer"></span>
                <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
              </div>
            </form>
          </div>
        </dialog>
    <?php
}



























function cm_occurrence_edit_apply(array $occ, string $actor, int $clientId): array
{
    $oid  = (int) $occ['id'];
    $when = date('j M', strtotime((string) $occ['scheduled_date']));
    $said = [];
    $failed = '';




    $note = isset($_POST['occ_note']) ? trim((string) $_POST['occ_note']) : null;






    $internal = isset($_POST['occ_internal']) ? trim((string) $_POST['occ_internal']) : null;







    $addMin = trim((string) ($_POST['add_minutes'] ?? ''));
    $addWhy = trim((string) ($_POST['add_why'] ?? ''));
    if ($addMin !== '') {
        if (!ctype_digit($addMin) || (int) $addMin < 1 || (int) $addMin > 1440) {
            $failed = 'Minutes, as a plain number (up to a day\'s worth).';
        } else {
            $before = cm_time_totals('occurrence', $oid)['total'];
            cm_time_record($occ, (int) $addMin, empty($_POST['add_nonbillable']),
                           'manual', $addWhy, $actor);
            cm_audit('time.adjusted', 'client', $clientId,
                     (string) $occ['action_name'] . ' (' . $when . '): '
                     . cm_minutes_label($before) . ' → ' . cm_minutes_label($before + (int) $addMin)
                     . ' by ' . $actor
                     . ($addWhy !== '' ? ' — ' . mb_substr($addWhy, 0, 60) : ''));
            $said[] = cm_minutes_label((int) $addMin) . ' added';
        }
    }

    if ($failed === '' && $note !== null && $note !== (string) ($occ['note'] ?? '')) {
        cm_q('UPDATE action_occurrence SET note = ?, updated_at = ? WHERE id = ?',
             [$note, cm_now(), $oid]);
        $said[] = "the client's record saved";
    }

    if ($failed === '' && $internal !== null
        && $internal !== (string) ($occ['internal_note'] ?? '')) {
        cm_q('UPDATE action_occurrence SET internal_note = ?, updated_at = ? WHERE id = ?',
             [$internal, cm_now(), $oid]);
        $said[] = 'note saved';
    }

    $to = trim((string) ($_POST['occ_to'] ?? ''));
    $moves = $failed === '' && $to !== '' && $to !== (string) $occ['scheduled_date'];



























    $actionId = $occ['action_id'] !== null ? (int) $occ['action_id'] : null;
    $clash = static function (?int $actionId, int $jobId, string $to): bool {
        return $actionId !== null
            ? cm_row('SELECT id FROM action_occurrence
                       WHERE action_id = ? AND scheduled_date = ?',
                     [$actionId, $to]) !== null
            : cm_row('SELECT id FROM action_occurrence
                       WHERE action_id IS NULL AND job_id = ? AND scheduled_date = ?',
                     [$jobId, $to]) !== null;
    };

    if ($moves && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $failed = 'Pick the day it moves to.';
    } elseif ($moves && $clash($actionId, (int) $occ['job_id'], $to)) {
        $failed = 'That day already has this task — complete or move that one instead.';
    } elseif ($moves) {
        cm_occurrence_settle($oid, 'rescheduled', $note, ['moved_to' => $to]);
        cm_insert('INSERT INTO action_occurrence
                     (action_id, job_id, scheduled_date, note, created_at, updated_at)
                   VALUES (?, ?, ?, ?, ?, ?)',
                  [$actionId, (int) $occ['job_id'], $to,
                   $note !== null ? $note : (string) ($occ['note'] ?? ''), cm_now(), cm_now()]);
        cm_audit('task.rescheduled', 'client', $clientId,
                 (string) $occ['action_name'] . ' moved ' . $when . ' → '
                 . date('j M', strtotime($to)));
        $said[] = 'moved to ' . date('j M', strtotime($to));
    }

    if ($failed !== '') return [$failed, 'error'];
    if ($said) return [(string) $occ['action_name'] . ' — ' . implode(', ', $said) . '.', 'ok'];
    return ['Nothing to change — the day, the note and the time are as they were.', 'warn'];
}

















function cm_occurrence_settle(int $oid, string $status, ?string $note, array $extra = []): void
{
    $sets = ['status = ?', 'updated_at = ?'];
    $args = [$status, cm_now()];
    foreach ($extra as $col => $val) { $sets[] = $col . ' = ?'; $args[] = $val; }
    if ($note !== null) { $sets[] = 'note = ?'; $args[] = $note; }
    $args[] = $oid;
    cm_q('UPDATE action_occurrence SET ' . implode(', ', $sets) . ' WHERE id = ?', $args);
}


function cm_occurrence_note_suffix(?string $note): string
{
    return ($note !== null && $note !== '') ? ' — ' . mb_substr($note, 0, 80) : '';
}








function cm_occurrence_notes_set(array $occ, ?string $note, ?string $internal, ?string $url, string $actor): void
{
    $sets = ['updated_at = ?'];
    $args = [cm_now()];
    if ($note !== null)     { $sets[] = 'note = ?';          $args[] = $note; }
    if ($internal !== null) { $sets[] = 'internal_note = ?'; $args[] = $internal; }
    if ($url !== null)      { $sets[] = 'content_url = ?';   $args[] = $url; }
    $args[] = (int) $occ['id'];
    cm_q('UPDATE action_occurrence SET ' . implode(', ', $sets) . ' WHERE id = ?', $args);
    cm_audit('task.notes', 'client', (int) ($occ['client_id'] ?? 0),
             (string) $occ['action_name'] . ' (' . date('j M', strtotime((string) $occ['scheduled_date']))
             . '): notes edited after completion by ' . $actor);
}

function cm_occurrence_complete(array $occ, ?string $note, ?string $url, string $actor,
                                ?string $internal = null): void
{
    if ((string) $occ['status'] === 'in_progress') {
        cm_time_record($occ, cm_timer_elapsed_minutes($occ), true, 'timer', 'completed', $actor);
    }























    $sets = ["status = 'completed'", 'started_at = NULL'];
    $args = [];
    $sets[] = 'completed_at = ?';  $args[] = cm_now();
    $sets[] = 'completed_by = ?';  $args[] = $actor;
    $sets[] = 'updated_at = ?';    $args[] = cm_now();
    if ($note !== null)     { $sets[] = 'note = ?';          $args[] = $note; }
    if ($url !== null)      { $sets[] = 'content_url = ?';   $args[] = $url; }
    if ($internal !== null) { $sets[] = 'internal_note = ?'; $args[] = $internal; }
    $args[] = (int) $occ['id'];
    cm_q('UPDATE action_occurrence SET ' . implode(', ', $sets) . ' WHERE id = ?', $args);




    require_once CM_LIB . '/snippets.php';
    if ($note !== null) cm_snippets_count_uses($note);
    cm_q('UPDATE job SET updated_at = ? WHERE id = ?', [cm_now(), (int) $occ['job_id']]);

    $clientId = (int) cm_val('SELECT client_id FROM job WHERE id = ?', [(int) $occ['job_id']]);
    $spent = cm_time_totals('occurrence', (int) $occ['id']);
    cm_audit('task.completed', 'client', $clientId,
             (string) $occ['action_name'] . ' (' . date('j M', strtotime((string) $occ['scheduled_date'])) . ') done'
             . ($spent['total'] > 0 ? ' in ' . cm_minutes_label($spent['total']) : '')
             . ($note !== null && $note !== '' ? ' — ' . mb_substr($note, 0, 80) : '')
             . ($internal !== null && trim($internal) !== '' ? ' [internal note kept]' : ''));
}























function cm_timer_bar_html(): string
{
    $o = cm_timer_running();
    if ($o === null) return '';

    $mins = cm_timer_elapsed_minutes($o);
    $who  = cm_client_display_name(['id' => $o['client_id'], 'first_name' => $o['first_name'],
                                    'last_name' => $o['last_name'], 'company' => $o['company'],
                                    'email' => $o['client_email']]);

    return '<div class="card card-running">'
       . '<div class="card-head">'
       . '<h2 class="m-0">A task is running &mdash; ' . e((string) $o['action_name']) . '</h2>'
       . '<span class="spacer"></span>'
       . '<span class="pill pill-spam">' . e(cm_minutes_label($mins)) . ' so far</span>'
       . '</div>'
       . '<p class="small mb-12">' . e((string) $o['job_name']) . ' &mdash; ' . e($who)
       . ' &middot; started ' . e(date('g:ia', strtotime((string) $o['started_at'])))
       . '. The clock runs on the server, so nothing here loses it.</p>'
       . '<div class="actions">'
       . '<a class="btn" href="' . e(cm_url('admin/task.php?id=' . (int) $o['id'])) . '">'
       . 'Open the task and manage it &rarr;</a>'
       . '<form method="post" class="inline">'
       . cm_csrf_field()
       . '<input type="hidden" name="occ_id" value="' . (int) $o['id'] . '">'
       . '<button class="btn btn-quiet" name="action" value="occ_pause">Pause the clock</button>'
       . '</form>'
       . '</div></div>';
}












































function cm_job_progress_many(array $jobIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $jobIds))));
    if (!$ids) return [];





    $in = implode(',', $ids);
    $rows = cm_all(
        "SELECT o.job_id,
                COUNT(*) AS total,
                SUM(o.status IN ('scheduled','in_progress')) AS still_open
         FROM action_occurrence o
         LEFT JOIN job_action a ON a.id = o.action_id
         WHERE " . cm_occurrence_required_sql('a') . " = 1 AND o.job_id IN ($in)
         GROUP BY o.job_id");

    $out = [];
    foreach ($rows as $r) {
        $total = (int) $r['total'];
        $out[(int) $r['job_id']] = [
            'done'  => $total - (int) $r['still_open'],
            'total' => $total,
        ];
    }
    return $out;
}



























function cm_job_progress_html(array $job): string
{
    $prog = cm_job_progress((int) $job['id']);
    if ((int) $prog['total'] <= 0) return '';

    $done  = (int) $prog['done'];
    $total = (int) $prog['total'];
    $left  = max(0, $total - $done);
    $isDone = (string) ($job['status'] ?? '') === 'completed';

    if ($isDone) {
        $label = '<b>JOB DONE</b> &mdash; all ' . $total . ' tasks';
    } elseif ($left === 0) {
        $label = '<b>All ' . $total . ' done</b> &mdash; ready to complete';
    } else {
        $label = '<b>' . $done . ' of ' . $total . ' done</b> &middot; ' . $left . ' to go';
    }

    return '<div class="jobprog' . ($isDone ? ' is-done' : '') . '">'
         . '<progress class="jobprog-bar" max="' . $total . '" value="' . $done . '"></progress>'
         . '<p class="jobprog-l">' . $label . '</p>'
         . '</div>';
}


















function cm_job_progress_cell_html(array $prog, bool $isDone = false): string
{
    $total = (int) ($prog['total'] ?? 0);
    if ($total <= 0) return '<span class="muted">&mdash;</span>';
    $done = min($total, max(0, (int) ($prog['done'] ?? 0)));

    return '<div class="jobprog jobprog-cell' . ($isDone || $done >= $total ? ' is-done' : '') . '">'
         . '<progress class="jobprog-bar" max="' . $total . '" value="' . $done . '"></progress>'
         . '<p class="jobprog-l"><b>' . $done . '</b> / ' . $total . '</p>'
         . '</div>';
}

function cm_work_card_html(array $admin): string
{
    $o = cm_timer_running();
    if ($o === null) return '';

    require_once CM_LIB . '/clients.php';
    require_once CM_LIB . '/assets.php';

    $jobId    = (int) $o['job_id'];
    $clientId = (int) $o['client_id'];
    $clientName = cm_client_display_name(['id' => $clientId,
        'first_name' => $o['first_name'], 'last_name' => $o['last_name'],
        'company' => $o['company'], 'email' => $o['client_email']]);
    $mins  = cm_timer_elapsed_minutes($o);
    $panel = cm_core_details_for_job_panel($clientId);
    $picked = cm_job_asset_ids($jobId);


    $allAssets = cm_client_assets($clientId, false);
    $assets = array_values(array_filter($allAssets,
        static fn($a) => in_array((int) $a['id'], $picked, true)));
    $unticked = array_values(array_filter($allAssets,
        static fn($a) => !in_array((int) $a['id'], $picked, true)));




    $returnTo = (string) ($_SERVER['REQUEST_URI'] ?? '');

    ob_start();
    ?>
<?php 


 ?>
<div class="card card-accent">
  <div class="card-head">
    <h2>Working on: <?= e((string) $o['action_name']) ?></h2>
    <span class="spacer"></span>
    <span class="pill pill-live"><?= e(cm_minutes_label($mins)) ?> so far</span>
  </div>
  <p class="muted small mb-12">
    <?= e((string) $o['job_name']) ?> &mdash;
    <a href="<?= e(cm_url('admin/client.php?id=' . $clientId)) ?>"><?= e($clientName) ?></a>
    &middot; started <?= e(date('g:ia', strtotime((string) $o['started_at']))) ?>
    &middot; <a href="<?= e(cm_url('admin/job.php?id=' . $jobId)) ?>">open the job</a>
    &mdash; the clock runs on the server; reloading loses nothing.
  </p>

  <div class="work-grid">
    <div>
      <h3 class="work-h">The brief &mdash; <?= e($clientName) ?></h3>
      <?php if (!$panel): ?>
        <p class="muted small">No Core Details yet for this client &mdash;
        <a href="<?= e(cm_url('admin/client.php?id=' . $clientId . '&tab=core')) ?>">write the brief</a>
        and it will sit here on every task.</p>
      <?php else: ?>
        <?php foreach ($panel as $key => $fld): ?>
          <h4 class="work-f<?= str_contains($key, 'avoid') ? ' work-avoid' : '' ?>"><?= e($fld['label']) ?></h4>
          <p class="small prewrap mb-8"><?= e($fld['value']) ?></p>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div data-cred-endpoint="<?= e(cm_url('admin/credential.php')) ?>"
         data-cred-csrf="<?= e(cm_csrf_token()) ?>">
      <h3 class="work-h">The properties this job touches</h3>
      <?php 


 ?>
      <?php if (!$assets): ?>
        <p class="muted small">None on this job yet<?= $unticked ? ' — their logins appear here the moment they are' : '' ?>.</p>
        <?php if ($unticked): ?>
          <button class="btn btn-sm" type="button" data-modal-open="dlg-work-assets">Add the properties</button>
        <?php else: ?>
          <p class="muted small"><?= e($clientName) ?> has no properties on file &mdash;
          <a href="<?= e(cm_url('admin/client.php?id=' . $clientId . '&tab=assets')) ?>">add one on their file</a>
          and it can be ticked on here.</p>
        <?php endif; ?>
      <?php else: ?>
        <?php 

 ?>
        <?php foreach ($assets as $a): ?>
          <?= cm_asset_signin_html($a) ?>
        <?php endforeach; ?>
        <?php if ($unticked): ?>
          <button class="btn btn-sm btn-quiet mt-4" type="button" data-modal-open="dlg-work-assets">Add another property</button>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php 

 ?>
  <div class="actions mt-16">
    <a class="btn" href="<?= e(cm_url('admin/task.php?id=' . (int) $o['id'])) ?>">Open the daily task &rarr;</a>
    <form method="post" class="inline">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="occ_id" value="<?= (int) $o['id'] ?>">
      <button class="btn btn-quiet" name="action" value="occ_pause">Pause</button>
    </form>
  </div>
</div>

<?php 




 ?>
<?php if ($unticked): ?>
<dialog class="modal" id="dlg-work-assets">
  <div class="modal-box">
    <div class="modal-head">
      <h2>Add a property to this job</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <p class="modal-sub"><?= e($clientName) ?>&rsquo;s properties. One click
    puts one on the job and its sign-in onto this card.</p>
    <?php foreach ($allAssets as $a): $on = in_array((int) $a['id'], $picked, true); ?>
      <div class="card-head mb-4">
        <span><b><?= e((string) $a['name']) ?></b>
          <span class="muted small"><?= e(cm_asset_types()[(string) $a['type']] ?? '') ?></span>
          <?php if (trim((string) ($a['url'] ?? '')) !== ''): ?>
            <span class="muted small mono"><?= e((string) $a['url']) ?></span>
          <?php endif; ?>
        </span>
        <span class="spacer"></span>
        <?php if ($on): ?>
          <span class="pill pill-approved">on the job</span>
        <?php else: ?>
          <form method="post" action="<?= e(cm_url('admin/job.php?id=' . $jobId)) ?>" class="inline">
            <?= cm_csrf_field() ?>
            <input type="hidden" name="action" value="add_asset">
            <input type="hidden" name="asset_id" value="<?= (int) $a['id'] ?>">
            <input type="hidden" name="return" value="<?= e($returnTo) ?>">
            <button class="btn btn-sm" type="submit">Add</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div class="modal-foot">
      <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
    </div>
  </div>
</dialog>
<?php endif; ?>
    <?php
    return (string) ob_get_clean();
}












































function cm_job_create(array $in, string $actor): array
{
    $clientId = (int) ($in['client_id'] ?? 0);
    $name     = trim((string) ($in['name'] ?? ''));
    $start    = trim((string) ($in['start'] ?? ''));
    $end      = trim((string) ($in['end'] ?? ''));
    $tplId    = (int) ($in['template_id'] ?? 0);



    $refuse = static function (string $why) use ($in): array {
        cm_job_draft_keep($in);
        return ['ok' => false, 'id' => 0, 'stamped' => 0, 'status' => '', 'error' => $why];
    };

    $client = $clientId > 0 ? cm_client($clientId) : null;
    if ($client === null) {
        return $refuse('Pick the client.');
    }

    $tpl = $tplId > 0 ? cm_row('SELECT * FROM job_template WHERE id = ?', [$tplId]) : null;




    if ($name === '' && $tpl !== null) $name = (string) $tpl['name'];
    if ($name === '') {
        $name = cm_client_display_name($client) . ' — ' . date('j M Y');
    }


    if (mb_strlen($name) > 160) $name = mb_substr($name, 0, 160);

    foreach (['start' => $start, 'end' => $end] as $label => $d) {
        if ($d !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return $refuse('Use the date picker for the ' . $label . ' date.');
        }
    }






    if ($start === '') $start = date('Y-m-d');

    if ($end !== '' && $end < $start) {
        return $refuse('The end date is before the start date.');
    }



    $amountRaw = trim((string) ($in['amount'] ?? ''));
    $cents = 0;
    if ($amountRaw !== '') {
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $amountRaw)) {
            return $refuse('The price: a plain amount, like 250 or 199.50.');
        }
        $cents = (int) round(((float) $amountRaw) * 100);
    }
    $freq = isset(cm_billing_frequencies()[(string) ($in['freq'] ?? '')])
          ? (string) $in['freq'] : 'oneoff';

    $intOrNull = static function ($v): ?int {
        $v = trim((string) $v);
        return $v !== '' && ctype_digit($v) ? (int) $v : null;
    };
    $days   = $intOrNull($in['duration_days'] ?? '');
    $perDay = $intOrNull($in['per_day_minutes'] ?? '');
    if ($days !== null && $days > 365) $days = 365;
    if ($perDay !== null && $perDay > 1440) $perDay = 1440;






    $brief = trim((string) ($in['description'] ?? ''));






















    $prio = isset(cm_job_priorities()[(string) ($in['priority'] ?? '')])
          ? (string) $in['priority'] : 'normal';
    $tags = trim((string) ($in['tags'] ?? ''));
    if (mb_strlen($tags) > 255) $tags = mb_substr($tags, 0, 255);
    $notes = trim((string) ($in['notes'] ?? ''));
    $setup = $intOrNull($in['setup_minutes'] ?? '') ?? 0;
    $compl = $intOrNull($in['completion_minutes'] ?? '') ?? 0;
    $cmode = (string) ($in['completion_mode'] ?? '') === 'own_day' ? 'own_day' : 'last_day';





    $id = cm_insert(
        "INSERT INTO job (client_id, name, description, status, priority, start_date, end_date,
                          amount_cents, billing_freq, tags, notes, per_day_minutes,
                          setup_minutes, completion_minutes, completion_mode,
                          created_at, updated_at)
         VALUES (?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$clientId, $name, $brief !== '' ? $brief : null, $prio, $start, $end !== '' ? $end : null,
         $cents, $freq, $tags, $notes !== '' ? $notes : null, $perDay ?? 0,
         $setup, $compl, $cmode, cm_now(), cm_now()]);
    cm_client_touch($clientId);
    cm_audit('job.created', 'client', $clientId, 'job "' . $name . '" created by ' . $actor);





    if (!empty($in['assets']) && is_array($in['assets'])) {
        cm_job_assets_set($id, $clientId, $in['assets']);
    }
























    $span    = (string) ($in['frequency'] ?? '');
    $hours   = trim((string) ($in['hours'] ?? ''));
    $total   = preg_match('/^\d+(\.\d{1,2})?$/', $hours) ? (int) round(((float) $hours) * 60) : 0;
    $stated  = $span === 'daily' || $span === 'oneoff';

    if ($stated) {
        $ownDays = $span === 'oneoff' ? 1 : (int) ($days ?? 0);
        cm_q('UPDATE job SET frequency = ?, duration_days = ?, est_minutes = ?, updated_at = ?
               WHERE id = ?',
             [$span, max(0, $ownDays), $total, cm_now(), $id]);
    }

    $stamped = 0;
    if ($tpl !== null) {
        $stamped = cm_template_apply($tplId, cm_job($id), [
            'duration_days'   => $days,
            'per_day_minutes' => $perDay,
        ]);
        cm_audit('job.template_applied', 'client', $clientId,
                 'service "' . (string) $tpl['name'] . '" stamped ' . $stamped
                 . ' day(s) onto "' . $name . '"'
                 . ($days !== null && $days !== (int) $tpl['duration_days']
                     ? ' — days changed to ' . $days : '')
                 . ($perDay !== null && $perDay !== (int) $tpl['per_day_minutes']
                     ? ' — time per day changed to ' . $perDay . 'm' : ''));
    }









    if ($tpl === null) {
        $fresh = cm_row('SELECT * FROM job WHERE id = ?', [$id]);
        if ($fresh) {
            cm_job_own_generate($fresh);
            cm_job_allocate(cm_row('SELECT * FROM job WHERE id = ?', [$id]));
            $stamped = (int) cm_val('SELECT COUNT(*) FROM action_occurrence
                                      WHERE job_id = ? AND action_id IS NULL', [$id]);
        }
    }





    $status = $stamped > 0 ? 'active' : 'draft';
    if ($status !== 'draft') {
        cm_q('UPDATE job SET status = ?, updated_at = ? WHERE id = ?', [$status, cm_now(), $id]);
    }

    cm_job_draft_clear();
    return ['ok' => true, 'id' => $id, 'stamped' => $stamped, 'status' => $status, 'error' => ''];
}











function cm_job_draft_keep(array $in): void
{
    if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) return;
    $_SESSION['cm_job_draft'] = [
        'template' => (string) (int) ($in['template_id'] ?? 0) !== '0'
                    ? (string) (int) ($in['template_id'] ?? 0) : '',
        'name'     => (string) ($in['name'] ?? ''),
        'description' => (string) ($in['description'] ?? ''),
        'start'    => (string) ($in['start'] ?? ''),
        'end'      => (string) ($in['end'] ?? ''),
        'amount'   => (string) ($in['amount'] ?? ''),
        'freq'     => (string) ($in['freq'] ?? ''),
        'days'     => (string) ($in['duration_days'] ?? ''),
        'perday'   => (string) ($in['per_day_minutes'] ?? ''),
        'assets'   => array_map('intval', (array) ($in['assets'] ?? [])),
        'client_id'=> (int) ($in['client_id'] ?? 0),
    ];
}

function cm_job_draft_clear(): void
{
    if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) return;
    unset($_SESSION['cm_job_draft']);
}


function cm_job_draft_take(): array
{
    if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) return [];
    $d = $_SESSION['cm_job_draft'] ?? null;
    unset($_SESSION['cm_job_draft']);
    return is_array($d) ? $d : [];
}


function cm_job_draft_pending(): bool
{
    if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) return false;
    return is_array($_SESSION['cm_job_draft'] ?? null);
}













function cm_job_form_fields_html(string $p, ?int $clientId = null, bool $compact = false,
                                 array $fill = [], bool $editing = false): string
{








    $tpls = [];
    try { $tpls = cm_templates(); } catch (Throwable $ex) {}











    if (!$fill) $fill = cm_job_draft_take();

    $f = static fn(string $k): string => (string) ($fill[$k] ?? '');





    $startValue = $f('start') !== '' ? $f('start') : date('Y-m-d');
    $ticked = array_map('intval', (array) ($fill['assets'] ?? []));







    $data = [];
    foreach ($tpls as $t) {
        $tid = (int) $t['id'];





        $tasks = [];
        try {
            foreach (cm_template_actions($tid) as $a) {
                $tasks[] = [
                    'name' => (string) $a['name'],
                    'freq' => (string) (cm_action_frequencies()[(string) $a['frequency']] ?? $a['frequency']),
                    'mins' => (int) $a['est_minutes'] > 0 ? cm_minutes_label((int) $a['est_minutes']) : '',
                ];
            }
        } catch (Throwable $ex) {}

        $data[(string) $tid] = [
            'name'    => (string) $t['name'],
            'amount'  => (int) $t['amount_cents'] > 0
                       ? number_format((int) $t['amount_cents'] / 100, 2, '.', '') : '',
            'freq'    => (string) $t['billing_freq'],
            'days'    => (int) $t['duration_days'] > 0 ? (string) (int) $t['duration_days'] : '',
            'perday'  => (int) $t['per_day_minutes'] > 0
                       ? (string) (int) $t['per_day_minutes']
                       : ((int) $t['total_minutes'] > 0 && (int) $t['duration_days'] > 0
                          ? (string) (int) ceil((int) $t['total_minutes'] / (int) $t['duration_days'])
                          : ''),












            'spans'   => ((string) ($t['frequency'] ?? '') === 'daily'
                          || ((string) ($t['frequency'] ?? '') === ''
                              && (int) $t['duration_days'] > 1)) ? '1' : '',
            'hours'   => (int) $t['total_minutes'] > 0
                       ? rtrim(rtrim(number_format((int) $t['total_minutes'] / 60, 2, '.', ''), '0'), '.')
                       : '',
            'tasks'   => $tasks,
        ];
    }

    ob_start();
    ?>
<div class="job-form<?= $compact ? ' is-compact' : '' ?>" data-job-templates="<?= e(json_encode($data, JSON_UNESCAPED_UNICODE)) ?>">
  <?php if ($clientId !== null): ?>
    <input type="hidden" name="<?= e($p) ?>client_id" value="<?= (int) $clientId ?>">
  <?php endif; ?>

  <?php 




















 ?>

  <?php 

















 ?>
  <div class="form-2col">
  <div class="form-col">

  <?php  ?>
  <div class="form-step">
    <h3><span class="step-n">1</span> <?= $editing ? 'The job' : 'Service' ?></h3>

    <?php if ($editing): ?>
      <?php  ?>
    <?php elseif (!$tpls): ?>
      <?php 







 ?>
      <p class="note note-warn">
        <b>No services have been set up.</b>
        While you can set up a custom one here, it is recommended to set up a
        service in <a href="<?= e(cm_url('admin/services.php')) ?>">Services &rarr;</a>
      </p>
    <?php else: ?>
      <div class="field">
        <label class="lbl" for="<?= e($p) ?>template">Start from a service</label>
        <select id="<?= e($p) ?>template" name="<?= e($p) ?>template" class="f-full" data-job-template>
          <option value="0">&mdash; nothing, start empty &mdash;</option>
          <?php foreach ($tpls as $t): ?>
            <option value="<?= (int) $t['id'] ?>"
                    <?= $f('template') !== '' && (int) $f('template') === (int) $t['id'] ? 'selected' : '' ?>>
              <?= e((string) $t['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint">Uses this service&rsquo;s defaults. You can review and change them before creating the job.</div>
      </div>
    <?php endif; ?>

    <?php 











 ?>
    <div class="field">
      <label class="lbl" for="<?= e($p) ?>name">Name <span class="muted">(optional)</span></label>
      <input type="text" id="<?= e($p) ?>name" name="<?= e($p) ?>name" class="f-full"
             maxlength="160" data-job-field="name" value="<?= e($f('name')) ?>"
             placeholder="<?= $editing ? 'What the job is called' : 'Named after the service if you leave it empty' ?>"<?= $editing ? ' required' : '' ?>>
    </div>
  </div>

  <?php  ?>
  <div class="form-step">
    <h3><span class="step-n">2</span> Schedule</h3>

    <?php 














 ?>
    <div class="field">
      <label class="lbl">Schedule type</label>
      <div class="caps-pick caps-2 caps-abreast" data-shows="jobspan-<?= e($p) ?>">
        <?php 

              $spans = $f('frequency') !== ''
                  ? $f('frequency') === 'daily'
                  : ($f('days') !== '' && (int) $f('days') > 1); ?>
        <label class="caps-row">
          <input type="radio" name="<?= e($p) ?>frequency" value="oneoff"
                 data-job-field="oneoff" <?= $spans ? '' : 'checked' ?>>
          <span><b>One-off</b>
            <span class="muted small d-block">Done in one day</span></span>
        </label>
        <label class="caps-row">
          <input type="radio" name="<?= e($p) ?>frequency" value="daily"
                 data-job-field="spans" <?= $spans ? 'checked' : '' ?>>
          <span><b>Multi-day</b>
            <span class="muted small d-block">Scheduled across working days.</span></span>
        </label>
      </div>
    </div>

    <div class="form-row row-even">
      <div class="field field-tight">
        <label class="lbl" for="<?= e($p) ?>start">Starts</label>
        <input type="date" id="<?= e($p) ?>start" name="<?= e($p) ?>start" class="f-full"
               data-span-start value="<?= e($startValue) ?>">
      </div>
      <?php 









 ?>
      <div class="field field-tight" data-when="jobspan-<?= e($p) ?>" data-when-value="daily">
        <label class="lbl" for="<?= e($p) ?>end">End</label>
        <input type="date" id="<?= e($p) ?>end" name="<?= e($p) ?>end" class="f-full"
               data-span-end value="<?= e($f('end')) ?>">
      </div>
      <div class="field field-tight" data-when="jobspan-<?= e($p) ?>" data-when-value="daily">
        <label class="lbl" for="<?= e($p) ?>days">or run for</label>
        <input type="text" id="<?= e($p) ?>days" name="<?= e($p) ?>days" inputmode="numeric"
               class="f-full" data-job-field="days" data-avg-days
               value="<?= $f('days') !== '' ? e($f('days')) : '1' ?>">
      </div>
    </div>
    <?php 
 ?>
    <?php 











 ?>
    <div class="hint" data-when="jobspan-<?= e($p) ?>" data-when-value="daily">Give a
    deadline <i>or</i> a length. Set an end date and the length is counted for
    you; clear it to type a length instead.</div>
  </div>

  </div>
  <div class="form-col">

  <?php  ?>
  <div class="form-step">
    <h3><span class="step-n">3</span> Price and estimated time</h3>

    <div class="form-row row-even">
      <div class="field field-tight">
        <label class="lbl" for="<?= e($p) ?>hours">Total time <span class="muted">(hours)</span></label>
        <input type="text" id="<?= e($p) ?>hours" name="<?= e($p) ?>hours" inputmode="decimal"
               class="f-full" data-job-field="hours" data-avg-total
               data-exclusive-with="<?= e($p) ?>perday"
               value="<?= e($f('hours')) ?>">
      </div>
      <div class="field field-tight">
        <label class="lbl" for="<?= e($p) ?>perday">Time per day <span class="muted">(minutes)</span></label>
        <input type="text" id="<?= e($p) ?>perday" name="<?= e($p) ?>perday" inputmode="numeric"
               class="f-full" data-job-field="perday"
               data-exclusive-with="<?= e($p) ?>hours"
               value="<?= e($f('perday')) ?>">
      </div>
      <?php 







 ?>
      <div class="field field-tight">
        <label class="lbl" for="<?= e($p) ?>amount">Job price</label>
        <input type="text" id="<?= e($p) ?>amount" name="<?= e($p) ?>amount" inputmode="decimal"
               class="f-full" data-job-field="amount" value="<?= e($f('amount')) ?>">
      </div>
    </div>
    <div class="hint mb-12">Enter total hours or minutes per day, not both.</div>

    <?php if ($compact): ?>
    <details class="job-advanced">
      <summary>Additional time options</summary>
      <div class="job-advanced-body">
    <?php endif; ?>
    <?php 


 ?>
    <div class="form-row row-even">
      <div class="field field-tight">
        <label class="lbl" for="<?= e($p) ?>setup">Setup time (minutes)</label>
        <input type="text" id="<?= e($p) ?>setup" name="<?= e($p) ?>setup" inputmode="numeric"
               class="f-full" value="<?= e($f('setup')) ?>">
        <div class="hint">Added to the first day, on top of its share.</div>
      </div>
      <div class="field field-tight">
        <label class="lbl" for="<?= e($p) ?>completion">Wrap-up time (minutes)</label>
        <input type="text" id="<?= e($p) ?>completion" name="<?= e($p) ?>completion" inputmode="numeric"
               class="f-full" value="<?= e($f('completion')) ?>">
        <label class="tick mt-8">
          <input type="checkbox" name="<?= e($p) ?>completion_mode" value="own_day"
                 <?= $f('completion_mode') === 'own_day' ? 'checked' : '' ?>>
          <span>Give it a day of its own, after the last</span>
        </label>
      </div>
    </div>
    <?php if ($compact): ?>
      </div>
    </details>
    <?php endif; ?>
  </div>

  <?php  ?>
  <?php if ($compact): ?>
  <details class="job-advanced">
    <summary>More options: priority, tags, notes and description</summary>
    <div class="job-advanced-body">
  <?php endif; ?>
  <div class="form-step">
    <h3><span class="step-n">4</span> Job details</h3>

    <div class="field">
      <label class="lbl">Priority</label>
      <div class="caps-pick caps-2">
        <?php foreach (cm_job_priorities() as $key => $meta): ?>
          <label class="caps-row">
            <input type="radio" name="<?= e($p) ?>priority" value="<?= e($key) ?>"
                   <?= ($f('priority') !== '' ? $f('priority') : 'normal') === $key ? 'checked' : '' ?>>
            <span><b><?= e((string) $meta['label']) ?></b></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-row row-even">
      <div class="field field-tight">
        <label class="lbl" for="<?= e($p) ?>tags">Tags</label>
        <input type="text" id="<?= e($p) ?>tags" name="<?= e($p) ?>tags" class="f-full"
               maxlength="255" value="<?= e($f('tags')) ?>">
      </div>
      <div class="field field-tight">
        <label class="lbl" for="<?= e($p) ?>notes">Job notes (internal)</label>
        <input type="text" id="<?= e($p) ?>notes" name="<?= e($p) ?>notes" class="f-full"
               value="<?= e($f('notes')) ?>">
      </div>
    </div>

    <?php 





 ?>
    <div class="field">
      <label class="lbl" for="<?= e($p) ?>description">Job description
        <span class="muted">(optional)</span></label>
      <textarea id="<?= e($p) ?>description" name="<?= e($p) ?>description" rows="5" class="f-full"
                placeholder="Paste everything the client sent &mdash; keywords, targets, links, the lot. It stays on the job and shows wherever the work is done."><?= e($f('description')) ?></textarea>
    </div>
  </div>
  <?php if ($compact): ?>
    </div>
  </details>
  <?php endif; ?>

  </div>
  </div><?php  ?>

  <?php 









 ?>
  <?php if (!$editing): ?>
  <div class="hint job-form-note">If you picked a service, check the settings it filled in before creating this job.</div>
  <?php endif; ?>
</div>
    <?php
    return (string) ob_get_clean();
}



function cm_templates(): array
{



    return cm_all('SELECT t.*, (SELECT COUNT(*) FROM job_template_action ta WHERE ta.template_id = t.id) AS actions
                   FROM job_template t ORDER BY t.sort, t.name');
}

function cm_template_actions(int $templateId): array
{
    return cm_all('SELECT * FROM job_template_action WHERE template_id = ? ORDER BY id', [$templateId]);
}



























function cm_template_apply(int $templateId, array $job, array $overrides = []): int
{
    $start = (string) ($job['start_date'] ?? '');
    $end   = (string) ($job['end_date'] ?? '');
    if ($start === '') return 0;

    $tpl = cm_row('SELECT * FROM job_template WHERE id = ?', [$templateId]);
    if (!$tpl) return 0;











    foreach (['duration_days', 'per_day_minutes', 'total_minutes',
              'setup_minutes', 'completion_minutes'] as $k) {
        if (array_key_exists($k, $overrides) && $overrides[$k] !== null) {
            $tpl[$k] = (int) $overrides[$k];
        }
    }



    if (!empty($overrides['per_day_minutes'])) {
        $tpl['total_minutes'] = 0;
    }





    cm_q('UPDATE job SET template_id = ?, updated_at = ? WHERE id = ?',
         [$templateId, cm_now(), (int) $job['id']]);





    $duration = (int) ($tpl['duration_days'] ?? 0);
    $spanEnd  = $end;
    if ($duration > 0) {
        $spanEnd = cm_add_workdays($start, $duration);
    }
























    $freq = (string) ($tpl['frequency'] ?? '');
    if (!isset(cm_action_frequencies()[$freq])) {





        $freq = ($duration > 0 || ($spanEnd !== '' && $spanEnd > $start)) ? 'daily' : 'oneoff';
    }

    $total      = (int) ($tpl['total_minutes'] ?? 0);
    $perDay     = (int) ($tpl['per_day_minutes'] ?? 0);
    $setup      = (int) ($tpl['setup_minutes'] ?? 0);
    $completion = (int) ($tpl['completion_minutes'] ?? 0);
    $mode       = (string) ($tpl['completion_mode'] ?? 'last_day') === 'own_day' ? 'own_day' : 'last_day';






    if ($perDay < 1 && $total < 1) {
        $fromTasks = 0;
        foreach (cm_template_actions($templateId) as $ta) {
            $fromTasks += (int) $ta['est_minutes'];
        }
        if ($fromTasks > 0) $perDay = $fromTasks;
    }





    $sets   = [];
    $params = [];
    $fill = static function (string $col, $value, bool $jobHasNone) use (&$sets, &$params): void {
        if ($jobHasNone) { $sets[] = $col . ' = ?'; $params[] = $value; }
    };

    $fill('frequency', $freq, (string) ($job['frequency'] ?? '') === '');
    $fill('duration_days', $duration, (int) ($job['duration_days'] ?? 0) === 0 && $duration > 0);
    $fill('per_day_minutes', $perDay, (int) ($job['per_day_minutes'] ?? 0) === 0 && $perDay > 0);
    $fill('setup_minutes', $setup, (int) ($job['setup_minutes'] ?? 0) === 0 && $setup > 0);
    $fill('completion_minutes', $completion, (int) ($job['completion_minutes'] ?? 0) === 0 && $completion > 0);
    $fill('completion_mode', $mode, $completion > 0);

    if ($duration > 0 && $end === '') {



        $jobEnd = ($completion > 0 && $mode === 'own_day') ? cm_workday_after($spanEnd) : $spanEnd;
        $sets[] = 'end_date = ?';
        $params[] = $jobEnd;
        $end = $jobEnd;
    }
    if ((int) $tpl['amount_cents'] > 0 && (int) $job['amount_cents'] === 0) {
        $sets[] = 'amount_cents = ?';
        $params[] = (int) $tpl['amount_cents'];
        $sets[] = 'billing_freq = ?';
        $params[] = isset(cm_billing_frequencies()[(string) $tpl['billing_freq']])
            ? (string) $tpl['billing_freq'] : 'oneoff';
    }




    $overall = ($total > 0 ? $total : $perDay * max(1, $duration)) + $setup + $completion;
    if ($overall > 0 && (int) $job['est_minutes'] === 0) {
        $sets[] = 'est_minutes = ?';
        $params[] = $overall;
    }

    if ($sets) {
        $params[] = cm_now();
        $params[] = (int) $job['id'];
        cm_q('UPDATE job SET ' . implode(', ', $sets) . ', updated_at = ? WHERE id = ?', $params);
    }



    $saved = cm_row('SELECT * FROM job WHERE id = ?', [(int) $job['id']]);
    if (!$saved) return 0;
    cm_job_own_generate($saved);
    cm_job_allocate(cm_row('SELECT * FROM job WHERE id = ?', [(int) $job['id']]));




    return (int) cm_val('SELECT COUNT(*) FROM action_occurrence
                          WHERE job_id = ? AND action_id IS NULL', [(int) $job['id']]);
}
















function cm_job_properties_picker_html(int $clientId, ?array $ticked = null): string
{
    require_once CM_LIB . '/assets.php';
    $props = [];
    try { $props = cm_client_assets($clientId); } catch (Throwable $ex) { return ''; }
    if (!$props) return '';





    ob_start();
    ?>
<fieldset class="prop-pick">
  <legend>Which of their properties does this touch?</legend>
  <?php foreach ($props as $p): ?>
    <label class="tick">
      <input type="checkbox" name="job_assets[]" value="<?= (int) $p['id'] ?>"
             <?= $ticked === null || in_array((int) $p['id'], $ticked, true) ? 'checked' : '' ?>>
      <span><b><?= e((string) $p['name']) ?></b>
        <?php if (trim((string) ($p['url'] ?? '')) !== ''): ?>
          <span class="muted small"><?= e((string) $p['url']) ?></span>
        <?php endif; ?>
      </span>
    </label>
  <?php endforeach; ?>
  <p class="hint">Ticked ones appear on the work card while you are doing the
  job, with their sign-in a click away.</p>
</fieldset>
    <?php
    return (string) ob_get_clean();
}
