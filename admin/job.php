<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0














declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/assets.php';
require_once CM_LIB . '/jobs.php';
require_once CM_LIB . '/reminders.php';

require_once CM_LIB . '/calendar.php';

require_once CM_LIB . '/invoices.php';

$admin = cm_require_admin();

$job = cm_job((int) ($_GET['id'] ?? 0));
if (!$job) {
    cm_flash('There is no job with that number — it may be a stale link.', 'warn');
    cm_redirect('admin/jobs.php');
}
$id     = (int) $job['id'];
$client = cm_client((int) $job['client_id']);
$errors = [];


if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $action = (string) ($_POST['action'] ?? '');


    if ($action === 'save_job') {






        $name  = trim((string) ($_POST['e_name'] ?? ''));
        $desc  = trim((string) ($_POST['e_description'] ?? ''));
        $prio  = (string) ($_POST['e_priority'] ?? 'normal');
        $start = trim((string) ($_POST['e_start'] ?? ''));
        $end   = trim((string) ($_POST['e_end'] ?? ''));
        $tags  = trim((string) ($_POST['e_tags'] ?? ''));
        $notes = trim((string) ($_POST['e_notes'] ?? ''));
        $bfreq = (string) ($_POST['e_freq'] ?? $job['billing_freq'] ?? 'oneoff');
        $amt   = trim((string) ($_POST['e_amount'] ?? ''));
        $estH  = trim((string) ($_POST['e_hours'] ?? ''));
        $freq  = (string) ($_POST['e_frequency'] ?? '');
        $durD  = trim((string) ($_POST['e_days'] ?? ''));
        $perD  = trim((string) ($_POST['e_perday'] ?? ''));
        $setupIn = trim((string) ($_POST['e_setup'] ?? ''));
        $complIn = trim((string) ($_POST['e_completion'] ?? ''));
        $cmode = ($_POST['e_completion_mode'] ?? '') === 'own_day' ? 'own_day' : 'last_day';

        if ($name === '' || mb_strlen($name) > 160) $errors['name'] = 'A job needs a name (160 characters maximum).';
        if (!isset(cm_job_priorities()[$prio])) $prio = 'normal';
        if (!isset(cm_billing_frequencies()[$bfreq])) $bfreq = 'oneoff';
        foreach (['start' => $start, 'end' => $end] as $k => $d) {
            if ($d !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) $errors[$k . '_date'] = 'Use the date picker.';
        }
        if ($start !== '' && $end !== '' && $end < $start) {
            $errors['end_date'] = 'The end date is before the start date.';
        }
        $cents = 0;
        if ($amt !== '') {
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $amt)) {
                $errors['amount'] = 'A plain amount, like 250 or 199.50.';
            } else {
                $cents = (int) round(((float) $amt) * 100);
            }
        }
        $estMin = 0;
        if ($estH !== '') {
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $estH)) {
                $errors['est_hours'] = 'Hours, like 6 or 2.5.';
            } else {
                $estMin = (int) round(((float) $estH) * 60);
            }
        }
        if (mb_strlen($tags) > 255) $errors['tags'] = 'Too long.';






        if ($freq !== '' && !isset(cm_action_frequencies()[$freq])) $freq = '';

        $whole = static function (string $raw, string $field, string $says) use (&$errors): int {
            if ($raw === '') return 0;
            if (!ctype_digit($raw)) { $errors[$field] = $says; return 0; }
            return (int) $raw;
        };
        $durDays = $whole($durD, 'duration_days', 'Days, like 30.');
        $perDay  = $whole($perD, 'per_day_minutes', 'Minutes, like 60.');
        $setup   = $whole($setupIn, 'setup_minutes', 'Minutes, like 30.');
        $compl   = $whole($complIn, 'completion_minutes', 'Minutes, like 45.');






        if ($estH !== '' && $perD !== '') {
            $errors['per_day_minutes'] = 'A total or a figure per day, not both — clear one.';
        }









        if ($end !== '' && $durD !== '' && !$errors) {
            $between = cm_days_between_inclusive($start, $end);
            if ($between === 0 || $durDays !== $between) {
                $errors['duration_days'] = 'An end date or a length, not both — clear one.';
            }
        }




        if (!$errors && $durDays > 0 && $end === '' && $start !== '') {
            $end = cm_add_workdays($start, $durDays);
        }

        if (!$errors) {
            cm_q('UPDATE job SET name=?, description=?, priority=?, start_date=?, end_date=?,
                                 amount_cents=?, billing_freq=?, est_minutes=?, tags=?, notes=?,
                                 frequency=?, duration_days=?, per_day_minutes=?,
                                 setup_minutes=?, completion_minutes=?, completion_mode=?,
                                 updated_at=?
                  WHERE id=?',
                 [$name, $desc, $prio, $start !== '' ? $start : null, $end !== '' ? $end : null,
                  $cents, $bfreq, $estMin, $tags, $notes,
                  $freq, $durDays, $perDay, $setup, $compl, $cmode, cm_now(), $id]);








            $saved = cm_row('SELECT * FROM job WHERE id = ?', [$id]);
            if ($saved) {
                cm_job_own_generate($saved);
                cm_job_allocate(cm_row('SELECT * FROM job WHERE id = ?', [$id]));
            }

            cm_audit('job.updated', 'client', (int) $client['id'],
                     'job "' . $name . '" details saved by ' . (string) $admin['email']);
            cm_flash('Saved.');
            cm_redirect('admin/job.php?id=' . $id);
        }





        $job = array_merge($job, ['name' => $name, 'description' => $desc, 'priority' => $prio,
                                  'start_date' => $start, 'end_date' => $end, 'tags' => $tags,
                                  'notes' => $notes, 'billing_freq' => $bfreq,
                                  'amount_cents' => $cents, 'est_minutes' => $estMin,
                                  'frequency' => $freq,
                                  'duration_days' => $durD !== '' ? $durD : 0,
                                  'per_day_minutes' => $perD !== '' ? $perD : 0,
                                  'setup_minutes' => $setupIn !== '' ? $setupIn : 0,
                                  'completion_minutes' => $complIn !== '' ? $complIn : 0,
                                  'completion_mode' => $cmode]);
    }







    if ($action === 'add_asset') {
        $aid = (int) ($_POST['asset_id'] ?? 0);
        $now = cm_job_asset_ids($id);
        if ($aid > 0 && !in_array($aid, $now, true)) {
            $now[] = $aid;
            cm_job_assets_set($id, (int) $client['id'], $now);
            cm_audit('job.assets_set', 'client', (int) $client['id'],
                     'job "' . (string) $job['name'] . '": property added from the work card by '
                     . (string) $admin['email']);
            cm_flash('Added to this job — its login is on the card now.');
        }
        $return = cm_safe_return((string) ($_POST['return'] ?? ''));
        cm_redirect($return !== '' ? $return : 'admin/job.php?id=' . $id);
    }







    if ($action === 'add_new_asset') {










        $backTo = 'admin/job.php?id=' . $id . ((string) ($_GET['flow'] ?? '') === 'new' ? '&flow=new' : '');




        $errors += cm_job_asset_add_new_handle_post($admin, $job, $client, $backTo, 'fa_');
    }





    cm_job_assets_handle_post($admin, $job, $client, 'admin/job.php?id=' . $id);


    if ($action === 'add_action') {
        $aname = trim((string) ($_POST['a_name'] ?? ''));
        $freq  = (string) ($_POST['a_frequency'] ?? 'oneoff');
        $start = trim((string) ($_POST['a_start'] ?? ''));
        $end   = trim((string) ($_POST['a_end'] ?? ''));
        $req   = !empty($_POST['a_required']) ? 1 : 0;
        $estM  = trim((string) ($_POST['a_minutes'] ?? ''));

        if ($aname === '' || mb_strlen($aname) > 160) $errors['a_name'] = 'Name the task — "Publish Facebook post".';
        if (!isset(cm_action_frequencies()[$freq])) $freq = 'oneoff';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            $errors['a_start'] = 'A start date is what the schedule is built from.';
        }
        if ($end !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || $end < $start)) {
            $errors['a_end'] = 'The end date is before the start.';
        }
        if ($freq !== 'oneoff' && $end === '' && (string) $job['end_date'] !== '' && $job['end_date'] !== null) {


            $end = (string) $job['end_date'];
        }
        $mins = 0;
        if ($estM !== '') {
            if (!ctype_digit($estM)) $errors['a_minutes'] = 'Minutes, as a plain number.';
            else $mins = (int) $estM;
        }

        if (!$errors) {
            $aid = cm_insert('INSERT INTO job_action (job_id, name, frequency, start_date, end_date,
                                                      est_minutes, required, notes, created_at, updated_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                             [$id, $aname, $freq, $start, $end !== '' ? $end : null, $mins, $req, '',
                              cm_now(), cm_now()]);
            cm_action_generate(cm_row('SELECT * FROM job_action WHERE id = ?', [$aid]));
            cm_q('UPDATE job SET updated_at = ? WHERE id = ?', [cm_now(), $id]);
            cm_audit('job.action_added', 'client', (int) $client['id'],
                     'job "' . (string) $job['name'] . '": ' . cm_action_frequencies()[$freq]
                     . ' action "' . $aname . '" added by ' . (string) $admin['email']);
            cm_flash('Task added — its dates are on the schedule below.');
            cm_redirect('admin/job.php?id=' . $id);
        }
    }

    if ($action === 'delete_action') {
        $aid = (int) ($_POST['action_id'] ?? 0);
        $act = cm_row('SELECT * FROM job_action WHERE id = ? AND job_id = ?', [$aid, $id]);
        if ($act) {
            $touched = (int) cm_val("SELECT COUNT(*) FROM action_occurrence
                                     WHERE action_id = ? AND status <> 'scheduled'", [$aid]);
            if ($touched > 0) {


                cm_flash('That task has been worked on, so it cannot be deleted — set its end date '
                       . 'to its last real day instead. History is never thrown away.', 'error');
            } else {
                cm_q('DELETE FROM job_action WHERE id = ?', [$aid]);
                cm_audit('job.action_removed', 'client', (int) $client['id'],
                         'job "' . (string) $job['name'] . '": unused action "' . (string) $act['name']
                         . '" removed by ' . (string) $admin['email']);
                cm_flash('Task removed.');
            }
        }
        cm_redirect('admin/job.php?id=' . $id);
    }


    if (in_array($action, ['occ_complete', 'occ_edit', 'occ_skip', 'occ_cancel', 'occ_reopen',
                           'occ_start', 'occ_pause', 'occ_notes'], true)) {
        $occ = cm_occurrence((int) ($_POST['occ_id'] ?? 0));











        if ($occ && $action === 'occ_pause' && (string) $occ['status'] === 'in_progress'
            && (int) $occ['job_id'] !== $id) {
            $mins = cm_timer_pause($occ, (string) $admin['email']);
            cm_flash(cm_minutes_label($mins) . ' banked. The task is back on the schedule — '
                   . 'starting it again adds to the same total.');
            cm_redirect('admin/job.php?id=' . $id);
        }

        if (!$occ || (int) $occ['job_id'] !== $id) {
            cm_flash('That task is not on this job.', 'warn');
            cm_redirect('admin/job.php?id=' . $id);
        }
        $oid  = (int) $occ['id'];
        $when = date('j M', strtotime((string) $occ['scheduled_date']));




        $note = isset($_POST['occ_note']) ? trim((string) $_POST['occ_note']) : null;
        $internal = isset($_POST['occ_internal']) ? trim((string) $_POST['occ_internal']) : null;
        $url  = isset($_POST['occ_url']) ? trim((string) $_POST['occ_url']) : null;




        if ($url !== null && $url !== '') {
            $addr = cm_web_address($url);
            if ($addr['error'] !== '') {
                cm_flash('Link to the work: ' . $addr['error'], 'error');
                cm_redirect('admin/job.php?id=' . $id);
            }
            $url = $addr['url'];
        }

        if ($action === 'occ_complete' && cm_occurrence_is_open($occ)) {

            cm_occurrence_complete($occ, $note, $url, (string) $admin['email'], $internal);
            cm_flash('Done: ' . (string) $occ['action_name'] . ' — ' . $when . '.');
        } elseif ($action === 'occ_start') {
            $refusal = cm_timer_start($occ, (string) $admin['email']);
            cm_flash($refusal === ''
                ? 'Timing "' . (string) $occ['action_name'] . '" — the clock is running on the server.'
                : $refusal, $refusal === '' ? 'ok' : 'error');











            if ($refusal === '' && (string) ($_POST['then'] ?? '') === 'task') {
                cm_q('UPDATE job SET updated_at = ? WHERE id = ?', [cm_now(), $id]);
                cm_redirect('admin/task.php?id=' . $oid);
            }
        } elseif ($action === 'occ_pause' && (string) $occ['status'] === 'in_progress') {



            $mins = cm_timer_pause($occ, (string) $admin['email']);
            cm_flash(cm_minutes_label($mins) . ' banked. The task is back on the schedule — '
                   . 'starting it again adds to the same total.');
        } elseif ($action === 'occ_skip' && cm_occurrence_is_open($occ)) {
            cm_occurrence_settle($oid, 'skipped', $note, ['completed_by' => (string) $admin['email']]);
            cm_audit('task.skipped', 'client', (int) $client['id'],
                     (string) $occ['action_name'] . ' (' . $when . ') skipped'
                     . cm_occurrence_note_suffix($note));
            cm_flash('Skipped — it stays on the record as skipped, not done.');
        } elseif ($action === 'occ_cancel' && cm_occurrence_is_open($occ)) {
            cm_occurrence_settle($oid, 'cancelled', $note);
            cm_audit('task.cancelled', 'client', (int) $client['id'],
                     (string) $occ['action_name'] . ' (' . $when . ') cancelled');
            cm_flash('Cancelled.');













        } elseif ($action === 'occ_edit' && cm_occurrence_is_open($occ)) {




            [$msg, $type] = cm_occurrence_edit_apply($occ, (string) $admin['email'], (int) $client['id']);
            cm_flash($msg, $type);
        } elseif ($action === 'occ_notes') {





            cm_occurrence_notes_set($occ, $note, $internal, $url, (string) $admin['email']);
            cm_flash('Notes saved for ' . (string) $occ['action_name'] . ' — ' . $when . '.');
        } elseif ($action === 'occ_reopen' && !cm_occurrence_is_open($occ)
                  && (string) $occ['status'] !== 'rescheduled') {
            cm_q("UPDATE action_occurrence SET status='scheduled', completed_at=NULL, updated_at=? WHERE id=?",
                 [cm_now(), $oid]);
            cm_audit('task.reopened', 'client', (int) $client['id'],
                     (string) $occ['action_name'] . ' (' . $when . ') reopened');
            cm_flash('Reopened — it is back on the schedule for its original day.');
        }
        cm_q('UPDATE job SET updated_at = ? WHERE id = ?', [cm_now(), $id]);
        cm_redirect('admin/job.php?id=' . $id);
    }






    if ($action === 'job_payment') {
        $amt = trim((string) ($_POST['jp_amount'] ?? ''));
        if (!cm_once_spend()) {
            cm_flash('That payment form was already sent, so nothing more was recorded.', 'warn');
            cm_redirect('admin/job.php?id=' . $id . '#money');
        }
        $problem = preg_match('/^\d+(\.\d{1,2})?$/', $amt)
            ? cm_job_payment_add($id, (int) $client['id'], (int) round((float) $amt * 100),
                                 (string) ($_POST['jp_method'] ?? 'platform'), (string) ($_POST['jp_platform'] ?? ''),
                                 (string) ($_POST['jp_paid_on'] ?? date('Y-m-d')), (string) ($_POST['jp_reference'] ?? ''),
                                 (string) ($_POST['jp_note'] ?? ''), (string) $admin['email'])
            : 'The amount: a plain number, like 250 or 199.50.';
        cm_flash($problem !== '' ? $problem : 'Payment recorded against the job — outside invoicing.',
                 $problem !== '' ? 'error' : 'ok');
        cm_redirect('admin/job.php?id=' . $id . '#money');
    }
    if ($action === 'job_payment_remove') {
        cm_flash(cm_job_payment_delete((int) ($_POST['payment_id'] ?? 0), $id, (string) $admin['email'])
            ? 'Payment removed.' : 'That payment is not on this job.', 'ok');
        cm_redirect('admin/job.php?id=' . $id . '#money');
    }


    if ($action === 'set_status') {
        $to = (string) ($_POST['to'] ?? '');
        $allowed = ['active', 'hold', 'cancelled', 'draft'];
        if (in_array($to, $allowed, true) && $to !== (string) $job['status']) {
            cm_q('UPDATE job SET status=?, updated_at=? WHERE id=?', [$to, cm_now(), $id]);
            cm_audit('job.status', 'client', (int) $client['id'],
                     'job "' . (string) $job['name'] . '" ' . (string) $job['status'] . ' → ' . $to
                     . ' by ' . (string) $admin['email']);
            $said = 'Job is now ' . cm_job_statuses()[$to]['label'] . '.';












            $days = (int) ($_POST['remind_days'] ?? 0);
            if ($days > 0 && $to === 'hold') {
                if ($days > 90) $days = 90;
                $due = date('Y-m-d', strtotime('+' . $days . ' days'));
                require_once CM_LIB . '/reminders.php';
                cm_reminder_create('Pick up again: ' . (string) $job['name'],
                                   $due, 'none', 'normal', (int) $client['id'], $id, null,
                                   'This job was put on hold on '
                                   . date('j M Y') . ' (' . cm_job_statuses()[$to]['label'] . ').');
                $said .= ' You will be reminded on ' . date('j M Y', strtotime($due)) . '.';
            }
            cm_flash($said);
        }
        cm_redirect('admin/job.php?id=' . $id);
    }

    if ($action === 'complete_job') {
        $open = cm_job_open_required($id);
        if ($open > 0 && empty($_POST['waive'])) {
            cm_flash($open . ' required task' . ($open === 1 ? ' is' : 's are') . ' still open. '
                   . 'Tick the box to waive them, or deal with them first.', 'error');
            cm_redirect('admin/job.php?id=' . $id);
        }
        if ($open > 0) {
            cm_q("UPDATE action_occurrence o LEFT JOIN job_action a ON a.id = o.action_id
                  SET o.status='skipped', o.note=CONCAT(o.note, ' [waived at completion]'), o.updated_at=?
                  WHERE o.job_id=? AND " . cm_occurrence_required_sql('a') . "=1
                    AND o.status IN ('scheduled','in_progress')",
                 [cm_now(), $id]);
        }
        cm_q("UPDATE job SET status='completed', completed_at=?, updated_at=? WHERE id=?",
             [cm_now(), cm_now(), $id]);
        cm_client_touch((int) $client['id']);
        cm_audit('job.completed', 'client', (int) $client['id'],
                 'job "' . (string) $job['name'] . '" completed by ' . (string) $admin['email']
                 . ($open > 0 ? ' — ' . $open . ' required task(s) WAIVED' : ' — all required work done'));





        $said = [];
        $days = (int) ($_POST['followup_days'] ?? 14);
        if ($days < 1 || $days > 730) $days = 14;
        $when = date('Y-m-d', strtotime('+' . $days . ' days'));
        $clientName = cm_client_display_name($client);

        if (!empty($_POST['followup_nudge'])) {
            cm_reminder_create('Follow up with ' . $clientName . ' about ' . (string) $job['name'],
                               $when, 'none', 'normal', (int) $client['id'], $id, null,
                               'Set when the job was completed.');
            $said[] = 'a reminder for you on ' . date('j M Y', strtotime($when));
        }

        if (!empty($_POST['followup_email']) && !cm_home()) {
            $to = trim((string) ($client['email'] ?? ''));
            if ($to === '') {



                cm_flash('No email address on ' . $clientName . ', so no follow-up email was scheduled. '
                       . 'Add one on their page and set it from Reminders.', 'warn');
            } else {
                cm_followup_schedule((int) $client['id'], $id, $when,
                                     'Follow-up to ' . $clientName . ' — ' . (string) $job['name']);
                $said[] = 'an email to ' . $to . ' on ' . date('j M Y', strtotime($when));
            }
        }

        cm_flash('Job completed.'
               . ($open > 0 ? ' The waived tasks are recorded as skipped, not done.' : '')
               . ($said ? ' Scheduled: ' . implode(' and ', $said) . '.' : ''));
        cm_redirect('admin/job.php?id=' . $id);
    }

    if ($action === 'reopen_job' && (string) $job['status'] === 'completed') {


        cm_q("UPDATE job SET status='active', updated_at=? WHERE id=?", [cm_now(), $id]);
        cm_audit('job.reopened', 'client', (int) $client['id'],
                 'job "' . (string) $job['name'] . '" reopened by ' . (string) $admin['email']
                 . ' — original completion stays on the record');
        cm_flash('Reopened. The original completion is kept in the history.');
        cm_redirect('admin/job.php?id=' . $id);
    }
}


cm_job_generate($id);

$job        = cm_job($id);
$statuses   = cm_job_statuses();
$jst        = $statuses[(string) $job['status']] ?? $statuses['draft'];
$priorities = cm_job_priorities();
$assets     = cm_client_assets((int) $client['id'], false);
$picked     = cm_job_asset_ids($id);
$actions    = cm_all('SELECT * FROM job_action WHERE job_id = ? ORDER BY start_date, id', [$id]);
$occs       = cm_job_occurrences($id);
$prog       = cm_job_progress($id);
$openReq    = cm_job_open_required($id);

$openOccs    = array_values(array_filter($occs, 'cm_occurrence_is_open'));
$settledOccs = array_values(array_filter($occs, static fn($o) => !cm_occurrence_is_open($o)));

$spent = cm_time_totals('job', $id);















$errOwn = static fn(array $keys): bool => (bool) array_intersect_key($errors, array_flip($keys));
$errAction   = $errOwn(['a_name', 'a_start', 'a_end', 'a_minutes']);
$errFlowProp = $errOwn(['fa_name', 'fa_url']);



$errJobEdit  = $errOwn(['name', 'tags', 'amount', 'end_date', 'est_hours', 'start_date',
                        'duration_days', 'per_day_minutes', 'setup_minutes', 'completion_minutes']);

cm_head($job['name'] . ' — ' . cm_client_display_name($client), '', true);
cm_render_flashes();
cm_admin_nav('jobs');





















echo cm_crumbs_html([
    ['label' => 'Clients', 'href' => cm_url('admin/clients.php')],
    ['label' => cm_client_display_name($client),
     'href'  => cm_url('admin/client.php?id=' . (int) $client['id'])],
    ['label' => 'All Jobs',
     'href'  => cm_url('admin/client.php?id=' . (int) $client['id'] . '&tab=jobs')],
    ['label' => (string) $job['name']],
]);

echo cm_timer_bar_html();
?>
<?php 







 ?>
<?php $clientVip = (int) ($client['vip'] ?? 0) === 1; ?>
<div class="page-hero<?= $clientVip ? ' is-vip' : '' ?>">
<div class="card-head align-baseline">
  <h1 class="mb-4"><?= e((string) $job['name']) ?></h1>
  <span class="pill <?= e($jst['pill']) ?>"><?= e($jst['label']) ?></span>
  <?php if ((string) $job['priority'] !== 'normal'): ?>
    <span class="pill <?= e($priorities[(string) $job['priority']]['pill']) ?>"><?= e($priorities[(string) $job['priority']]['label']) ?></span>
  <?php endif; ?>
  <span class="spacer"></span>
  <?php 






 ?>
  <?php if (cm_invoicing_enabled()): ?>
    <a class="btn btn-sm" href="<?= e(cm_url('admin/invoices.php?new_for=' . (int) $client['id'] . '&job=' . (int) $job['id'])) ?>">Create invoice</a>
  <?php endif; ?>
</div>
<div class="job-summary-grid" aria-label="Job summary">
  <div class="job-summary-item">
    <span class="job-summary-label">Dates</span>
    <span class="job-summary-value mono"><?= $job['start_date'] ? e(date('j M Y', strtotime((string) $job['start_date']))) : 'Not set' ?> &rarr; <?= $job['end_date'] ? e(date('j M Y', strtotime((string) $job['end_date']))) : 'Open' ?></span>
  </div>
  <div class="job-summary-item">
    <span class="job-summary-label">Progress</span>
    <span class="job-summary-value mono"><?= $prog['total'] > 0 ? $prog['done'] . ' of ' . $prog['total'] : 'No tasks' ?></span>
  </div>
  <div class="job-summary-item">
    <span class="job-summary-label">Price</span>
    <span class="job-summary-value mono"><?= (int) $job['amount_cents'] > 0 ? e(cm_money((int) $job['amount_cents'])) . ' ' . e(strtolower(cm_billing_frequencies()[(string) $job['billing_freq']])) : 'Not priced' ?></span>
  </div>
  <div class="job-summary-item">
    <span class="job-summary-label">Tracked</span>
    <span class="job-summary-value mono"><?= e(cm_minutes_label($spent['total'])) ?><?= (int) $job['est_minutes'] > 0 ? ' of ' . e(cm_minutes_label((int) $job['est_minutes'])) : '' ?></span>
  </div>
</div>
<p class="sub job-summary-legacy">
  <?= $job['start_date'] ? e(date('j M Y', strtotime((string) $job['start_date']))) : 'no start date' ?>
  → <?= $job['end_date'] ? e(date('j M Y', strtotime((string) $job['end_date']))) : 'open-ended' ?>
  <?php if ($prog['total'] > 0): ?> · progress <span class="mono"><?= $prog['done'] ?> / <?= $prog['total'] ?></span><?php endif; ?>
  <?php if ((int) $job['amount_cents'] > 0): ?> · <span class="mono"><?= e(cm_money((int) $job['amount_cents'])) ?></span> <?= e(strtolower(cm_billing_frequencies()[(string) $job['billing_freq']])) ?><?php endif; ?>
  <?php $rate = cm_effective_rate_cents((int) $job['amount_cents'], $spent['billable']);
        if ($rate !== null): ?>
    · <span class="mono"><?= e(cm_money($rate)) ?>/hr</span> effective
  <?php endif; ?>
  <?php if ($spent['total'] > 0 || (int) $job['est_minutes'] > 0): ?>
    · tracked <span class="mono"><?= e(cm_minutes_label($spent['total'])) ?></span><?php
    if ((int) $job['est_minutes'] > 0):
        $var = $spent['total'] - (int) $job['est_minutes']; ?> of
      <span class="mono"><?= e(cm_minutes_label((int) $job['est_minutes'])) ?></span> estimated
      <?php if ($var > 0): ?><span class="mono txt-err">(+<?= e(cm_minutes_label($var)) ?> over)</span><?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</p>
</div>

<?php 








 ?>
<?php if (($_GET['flow'] ?? '') === 'new'):
    $flowAll    = cm_client_assets((int) $client['id'], false);
    $flowPicked = cm_job_asset_ids($id);
    $flowReturn = '/admin/job.php?id=' . $id . '&flow=new';



    $firstOpen  = cm_row("SELECT ao.id FROM action_occurrence ao
                           WHERE ao.job_id = ? AND ao.status = 'scheduled'
                           ORDER BY ao.scheduled_date, ao.id LIMIT 1", [$id]);
?>
  <div class="card card-accent mb-16">
    <div class="card-head">
      <h2>The job is set up &mdash; put its properties on</h2>
      <span class="spacer"></span>
      <a class="muted small" href="<?= e(cm_url('admin/job.php?id=' . $id)) ?>">Done &mdash; hide this</a>
    </div>
    <p class="muted small">Tick the properties this job touches and their
    sign-ins sit on the work card while you do it. One click each.</p>

    <?php if (!$flowAll): ?>
      <p class="muted small"><?= e(cm_client_display_name($client)) ?> has no
      properties on file yet &mdash; add the first one here and it lands on
      the job already.</p>
    <?php else: ?>
      <?php foreach ($flowAll as $fa): $on = in_array((int) $fa['id'], $flowPicked, true); ?>
        <div class="card-head mb-4">
          <span><b><?= e((string) $fa['name']) ?></b>
            <span class="muted small"><?= e(cm_asset_types()[(string) $fa['type']] ?? '') ?></span></span>
          <span class="spacer"></span>
          <?php if ($on): ?>
            <span class="pill pill-approved">on the job</span>
          <?php else: ?>
            <form method="post" class="inline">
              <?= cm_csrf_field() ?>
              <input type="hidden" name="action" value="add_asset">
              <input type="hidden" name="asset_id" value="<?= (int) $fa['id'] ?>">
              <input type="hidden" name="return" value="<?= e($flowReturn) ?>">
              <button class="btn btn-sm" type="submit">Add</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <button class="btn btn-sm<?= $flowAll ? ' btn-quiet' : '' ?> mt-4" type="button"
            data-modal-open="dlg-job-assets">+ New property</button>

    <?php 




 ?>
    <div class="actions mt-12">
      <?php if ($firstOpen !== null): ?>
        <form method="post" class="inline">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="occ_id" value="<?= (int) $firstOpen['id'] ?>">
          <input type="hidden" name="then" value="task">
          <button class="btn" name="action" value="occ_start" type="submit">Start work</button>
        </form>
      <?php else: ?>
        <a class="btn" href="<?= e(cm_url('admin/workflow.php')) ?>">Start work</a>
      <?php endif; ?>
      <?php 











 ?>
    </div>
  </div>

  <?php 

 ?>
<?php endif; ?>

<?php 






 ?>
<?php 


 ?>
<?php 




 ?>
<div class="page-grid mb-16">
<div>
<div class="card">
  <div class="card-head">
    <h2 class="t-ui">Job description</h2>
    <span class="spacer"></span>
    <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-job-edit">Edit</button>
  </div>
  <?php if (trim((string) $job['description']) !== ''): ?>
    <p class="small prewrap m-0"><?= e((string) $job['description']) ?></p>
  <?php else: ?>
    <div class="empty-state compact-empty">
      <p><b>No job description yet.</b></p>
      <p class="muted small">Add the brief, links and targets needed to complete the work.</p>
      <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-job-edit">Add description</button>
    </div>
  <?php endif; ?>
</div>
</div>
<div>
<?php 





















 ?>
<?php



















$nextOcc = $openOccs[0] ?? null;
$running = cm_timer_running();
$showNext = $nextOcc !== null && $running === null && ($_GET['flow'] ?? '') !== 'new'
            && in_array((string) $job['status'], ['active', 'draft'], true);
if ($showNext) {
    $nextDate = (string) $nextOcc['scheduled_date'];
    $nextToday = $nextDate <= date('Y-m-d');



    $didToday = (int) cm_val("SELECT COUNT(*) FROM action_occurrence
                               WHERE job_id = ? AND status = 'completed'
                                 AND DATE(completed_at) = ?", [$id, date('Y-m-d')]);
}
?>
  <div class="card<?= $showNext ? ' card-start' : '' ?>">
    <?php if ($showNext): ?>
    <div class="card-head">
      <div>
        <h2 class="m-0"><?= $nextToday
            ? 'Start work'
            : ($didToday > 0 ? 'Today&rsquo;s is done &mdash; start the next one'
                             : 'Nothing due yet &mdash; start it early') ?></h2>
        <p class="muted small m-0 mt-4">
          <b><?= e((string) $nextOcc['action_name']) ?></b>
          &middot; <?= $nextDate < date('Y-m-d')
              ? 'was due ' . e(date('D j M', strtotime($nextDate)))
              : ($nextToday ? 'due today' : 'due ' . e(date('D j M', strtotime($nextDate)))) ?>
          <?php if ((int) $nextOcc['minutes_spent'] > 0): ?>
            &middot; <?= e(cm_minutes_label((int) $nextOcc['minutes_spent'])) ?> already tracked
          <?php endif; ?>
        </p>
      </div>
      <span class="spacer"></span>
      <form method="post" class="inline">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="occ_id" value="<?= (int) $nextOcc['id'] ?>">
        <input type="hidden" name="then" value="task">
        <button class="btn" name="action" value="occ_start" type="submit">Start work</button>
      </form>
      <a class="btn btn-quiet" href="<?= e(cm_url('admin/task.php?id=' . (int) $nextOcc['id'])) ?>">Open it without the clock</a>
    </div>
    <?php endif; ?>

    <?php 






 ?>
    <?= cm_job_progress_html($job) ?>

    <div class="actions">
      <?php 













 ?>
      <?php switch ((string) $job['status']):
            case 'draft': ?>
        <form method="post" class="inline">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="set_status">
          <input type="hidden" name="to" value="active">
          <button class="btn btn-sm" type="submit">Activate &mdash; start generating work</button>
        </form>
        <?php break;
            case 'active': ?>
        <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-job-hold">Put it on hold&hellip;</button>
        <?php break;
            case 'hold': ?>
        <form method="post" class="inline">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="set_status">
          <input type="hidden" name="to" value="active">
          <button class="btn btn-sm" type="submit">Resume</button>
        </form>
        <?php break;
        endswitch; ?>

      <?php 
















 ?>
      <?php if (!in_array((string) $job['status'], ['draft', 'cancelled', 'completed'], true)): ?>
        <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-job-stop">Stop it&hellip;</button>
      <?php elseif ((string) $job['status'] === 'cancelled'): ?>
        <form method="post" class="inline">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="set_status">
          <input type="hidden" name="to" value="active">
          <button class="btn btn-sm" type="submit">Put it back</button>
        </form>
      <?php endif; ?>

      <a class="btn btn-sm btn-quiet" href="<?= e(cm_url('admin/summary.php?id=' . $id)) ?>">Summary — print<?= cm_home() ? '' : ' or email' ?></a>

      <?php if ((string) $job['status'] === 'completed'): ?>
        <form method="post" class="inline"
              data-confirm="Reopen this job? The original completion stays in the history.">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="reopen_job">
          <button class="btn btn-sm" type="submit">Reopen the job</button>
        </form>
      <?php elseif ((string) $job['status'] !== 'cancelled'): ?>
        <span class="spacer"></span>
        <details class="complete-box">
          <summary class="btn btn-sm <?= $openReq === 0 ? '' : 'btn-quiet' ?>">
            <?= $openReq === 0 ? 'Complete the job…' : 'Complete anyway…' ?>
          </summary>
          <div class="complete-panel">
            <form method="post"
                  data-confirm="<?= $openReq > 0
                      ? 'Complete the job with ' . $openReq . ' required task(s) still open? They are recorded as waived, not done.'
                      : 'Mark this job completed?' ?>">
              <?= cm_csrf_field() ?>
              <input type="hidden" name="action" value="complete_job">

              <?php if ($openReq > 0): ?>
                <label class="tick mb-10">
                  <input type="checkbox" name="waive" value="1">
                  <span class="small"><b>Waive</b> the <?= $openReq ?> open required
                    task<?= $openReq === 1 ? '' : 's' ?> — recorded as waived, not done</span>
                </label>
              <?php endif; ?>

              <?php 

 ?>
              <h3 class="work-h">Before you close it</h3>
              <label class="tick">
                <input type="checkbox" name="followup_nudge" value="1">
                <span class="small"><b>Remind me</b> to follow up with
                  <?= e(cm_client_display_name($client)) ?></span>
              </label>
              <?php if (!cm_home()): ?>
              <label class="tick">
                <input type="checkbox" name="followup_email" value="1"
                       <?= trim((string) ($client['email'] ?? '')) === '' ? 'disabled' : '' ?>>
                <span class="small"><b>Email them</b> a follow-up automatically
                  <?php if (trim((string) ($client['email'] ?? '')) === ''): ?>
                    <span class="muted">— no email address on this client</span>
                  <?php else: ?>
                    <span class="muted">— to <?= e((string) $client['email']) ?>,
                      <a href="<?= e(cm_url('admin/email-templates.php?open=client_followup')) ?>">wording</a></span>
                  <?php endif; ?>
                </span>
              </label>
              <?php endif; ?>
              <div class="row mt-8">
                <label class="lbl" for="followup_days">In how long?</label>
                <select id="followup_days" name="followup_days" class="f-lg">
                  <option value="7">1 week</option>
                  <option value="14" selected>2 weeks</option>
                  <option value="30">1 month</option>
                  <option value="90">3 months</option>
                  <option value="180">6 months</option>
                  <option value="365">1 year</option>
                </select>
              </div>

              <button class="btn mt-12" type="submit">Complete the job</button>
            </form>
          </div>
        </details>
      <?php endif; ?>
    </div>
    <?php if ((string) $job['status'] === 'draft'): ?>
      <p class="hint">A draft generates work on the schedule below but nothing
      is "overdue" yet — activate it when the dates and tasks look right.</p>
    <?php elseif ($openReq === 0 && $prog['total'] > 0 && (string) $job['status'] === 'active'): ?>
      <p class="hint"><b>Every required task is settled</b> — this job is ready
      to complete.</p>
    <?php endif; ?>
  </div>
</div>
</div><?php  ?>

<?php 










 ?>
<div class="page-grid">
<div>


<?php 












 ?>
<?php if ((string) $job['status'] === 'active'): ?>
<?php 











 ?>
<?php if (!in_array((string) $job['status'], ['draft', 'cancelled', 'completed'], true)): ?>
<dialog class="modal" id="dlg-job-stop">
  <div class="modal-box">
    <div class="modal-head">
      <h2>Stop this job</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <p class="modal-sub">Neither of these deletes anything. Every task, note
    and tracked hour stays exactly where it is.</p>

    <div class="hold-opt">
      <h3 class="work-h">Back to draft</h3>
      <p class="muted small mb-8">It was never really started. <?=
        e(cm_job_statuses()['draft']['blurb']) ?> Activate it again whenever
        you are ready and the schedule picks up.</p>
      <div class="actions">
        <form method="post" class="inline">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="set_status">
          <input type="hidden" name="to" value="draft">
          <button class="btn btn-sm" type="submit">Put it back to draft</button>
        </form>
      </div>
    </div>

    <div class="hold-opt">
      <h3 class="work-h">Cancel it</h3>
      <p class="muted small mb-8">It is not happening. <?=
        e(cm_job_statuses()['cancelled']['blurb']) ?> It leaves the work
        queue and stops counting as overdue, and you can put it back.</p>
      <div class="actions">
        <form method="post" class="inline"
              data-confirm="Cancel this job? Nothing is deleted — every task, note and tracked hour stays, and you can put it back.">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="set_status">
          <input type="hidden" name="to" value="cancelled">
          <button class="btn btn-sm btn-danger" type="submit">Cancel the job</button>
        </form>
      </div>
    </div>

    <div class="modal-foot">
      <button class="btn btn-quiet" type="button" data-modal-close>Leave it running</button>
      <span class="spacer"></span>
      <span class="muted small">Find them again:
        <a href="<?= e(cm_url('admin/jobs.php?status=draft')) ?>">Jobs &rsaquo; Draft</a>
        &middot; <a href="<?= e(cm_url('admin/jobs.php?status=cancelled')) ?>">Jobs &rsaquo; Cancelled</a></span>
    </div>
  </div>
</dialog>
<?php endif; ?>

<dialog class="modal" id="dlg-job-hold">
  <div class="modal-box">
    <div class="modal-head">
      <h2>Put this job on hold</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <p class="modal-sub">Its tasks stop appearing in the work queue and
    nothing on it counts as overdue. Nothing is deleted, no dates move, and
    everything picks up where it stopped when you resume.</p>

    <?php 









 ?>
    <div class="hold-opt">
      <h3 class="work-h">For how long?</h3>
      <p class="muted small mb-8">Why it is down is worth writing on the job;
      the software only needs to know that it is. Find it again under
      <b>Jobs &rsaquo; On hold</b>.</p>
      <div class="actions">
        <form method="post" class="inline">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="set_status">
          <input type="hidden" name="to" value="hold">
          <button class="btn btn-sm" type="submit">Until I resume it</button>
        </form>
        <form method="post" class="inline">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="set_status">
          <input type="hidden" name="to" value="hold">
          <input type="hidden" name="remind_days" value="1">
          <button class="btn btn-sm btn-quiet" type="submit">Pick it up tomorrow</button>
        </form>
        <form method="post" class="inline">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="set_status">
          <input type="hidden" name="to" value="hold">
          <input type="hidden" name="remind_days" value="7">
          <button class="btn btn-sm btn-quiet" type="submit">Remind me in 7 days</button>
        </form>
      </div>
    </div>

    <div class="modal-foot">
      <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      <span class="spacer"></span>
      <span class="muted small">Find it again:
        <a href="<?= e(cm_url('admin/jobs.php?status=hold')) ?>">Jobs &rsaquo; On hold</a></span>
    </div>
  </div>
</dialog>
<?php endif; ?>

  <?php 


















 ?>
  <?php 








 ?>
  <?php $money = cm_job_money($id); $jobPayments = cm_job_payments($id);
        $jobInvoices = cm_all("SELECT id, number, status, total_cents, paid_cents, issued_on FROM invoice
                                WHERE job_id = ? AND status <> 'void' ORDER BY id DESC", [$id]); ?>
  <div class="card" id="money">
    <div class="card-head">
      <div><h2 class="m-0">Money</h2></div>
      <span class="spacer"></span>
      <button class="btn btn-sm" type="button" data-modal-open="dlg-job-payment">Record a payment</button>
    </div>
    <div class="stat-row">
      <div class="stat"><span class="v"><?= e(cm_money($money['price'])) ?></span><span class="k">Job value</span></div>
      <div class="stat"><span class="v"><?= e(cm_money($money['invoiced'])) ?></span><span class="k">Invoiced</span></div>
      <div class="stat"><span class="v"><?= e(cm_money($money['via_invoice'])) ?></span><span class="k">Paid on invoices</span></div>
      <div class="stat"><span class="v"><?= e(cm_money($money['elsewhere'])) ?></span><span class="k">Received elsewhere</span></div>
    </div>
    <?php if ($jobInvoices): ?>
      <p class="small mb-4 mt-8"><b>Invoices</b></p>
      <ul class="small m-0">
        <?php foreach ($jobInvoices as $inv): ?>
          <li><a href="<?= e(cm_url('admin/invoice.php?id=' . (int) $inv['id'])) ?>"><?= e((string) ($inv['number'] ?: 'Draft')) ?></a>
            &middot; <?= e(cm_invoice_statuses()[(string) $inv['status']]['label'] ?? $inv['status']) ?>
            &middot; <span class="mono"><?= e(cm_money((int) $inv['total_cents'])) ?></span>
            <?php if ((int) $inv['paid_cents'] > 0 && (string) $inv['status'] !== 'paid'): ?><span class="muted">(<?= e(cm_money((int) $inv['paid_cents'])) ?> paid)</span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if ($jobPayments): ?>
      <p class="small mb-4 mt-8"><b>Received outside invoicing</b> <span class="muted">&mdash; tracked, not declared</span></p>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>How</th><th class="num">Amount</th><th>Reference</th><th class="num"></th></tr></thead>
          <tbody>
          <?php foreach ($jobPayments as $jp): ?>
            <tr>
              <td class="mono small nowrap"><?= e(date('j M Y', strtotime((string) $jp['paid_on']))) ?></td>
              <td class="small"><?= e(cm_payment_methods()[(string) $jp['method']] ?? $jp['method']) ?><?= (string) $jp['platform'] !== '' ? ' &middot; ' . e((string) $jp['platform']) : '' ?><?= (string) $jp['note'] !== '' ? '<span class="muted"> &middot; ' . e((string) $jp['note']) . '</span>' : '' ?></td>
              <td class="num mono small"><?= e(cm_money((int) $jp['amount_cents'])) ?></td>
              <td class="small"><?= e((string) $jp['reference']) ?></td>
              <td class="num">
                <form method="post" class="inline" data-confirm="Remove this payment from the job's record?">
                  <?= cm_csrf_field() ?>
                  <input type="hidden" name="action" value="job_payment_remove">
                  <input type="hidden" name="payment_id" value="<?= (int) $jp['id'] ?>">
                  <button class="btn btn-sm btn-quiet" type="submit">Remove</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php elseif (!$jobInvoices): ?>
      <p class="muted small m-0 mt-8">Nothing received yet. <?= cm_invoicing_enabled() ? '<b>Invoice this job</b> raises a numbered invoice; ' : '' ?><b>Record a payment</b> notes money that arrived some other way &mdash; a platform that has already invoiced it &mdash; and declares nothing.</p>
    <?php endif; ?>
  </div>

  <dialog class="modal" id="dlg-job-payment">
    <div class="modal-box">
      <div class="modal-head">
        <h2>Record a payment</h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">Money that arrived for this job <b>without an invoice from here</b>
      &mdash; a platform that already billed the client, cash, a transfer. It is
      tracked against the job and never becomes an invoice. To raise one, use
      Invoice this job instead.</p>
      <form method="post" data-saving>
        <?= cm_csrf_field() ?>
        <?= cm_once_field() ?>
        <input type="hidden" name="action" value="job_payment">
        <div class="row-2">
          <div class="row">
            <label class="lbl" for="jp_amount">Amount</label>
            <input type="text" id="jp_amount" name="jp_amount" inputmode="decimal" class="f-full" placeholder="<?= e(rtrim(rtrim(number_format($money['price'] / 100, 2, '.', ''), '0'), '.')) ?>">
          </div>
          <div class="row">
            <label class="lbl" for="jp_paid_on">Received on</label>
            <input type="date" id="jp_paid_on" name="jp_paid_on" class="f-full" value="<?= e(date('Y-m-d')) ?>">
          </div>
        </div>
        <div class="row-2">
          <div class="row">
            <label class="lbl" for="jp_method">How</label>
            <select id="jp_method" name="jp_method" class="f-full">
              <?php foreach (cm_payment_methods() as $mk => $ml): ?>
                <option value="<?= e($mk) ?>" <?= $mk === 'platform' ? 'selected' : '' ?>><?= e($ml) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row">
            <label class="lbl" for="jp_platform">Platform <span class="muted">(if one)</span></label>
            <input type="text" id="jp_platform" name="jp_platform" class="f-full" maxlength="80" placeholder="Upwork, Fiverr, Airtasker…">
          </div>
        </div>
        <div class="row-2">
          <div class="row">
            <label class="lbl" for="jp_reference">Reference <span class="muted">(optional)</span></label>
            <input type="text" id="jp_reference" name="jp_reference" class="f-full" maxlength="120">
          </div>
          <div class="row">
            <label class="lbl" for="jp_note">Note <span class="muted">(optional)</span></label>
            <input type="text" id="jp_note" name="jp_note" class="f-full" maxlength="255">
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn" type="submit">Record it</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>

  <div class="card" id="properties"
       data-cred-endpoint="<?= e(cm_url('admin/credential.php')) ?>"
       data-cred-csrf="<?= e(cm_csrf_token()) ?>">
    <?php 















 ?>
    <?php 









 ?>
    <div class="card-head">
      <div><h2 class="m-0">Job resources</h2></div>
      <span class="spacer"></span>
      <?php 







 ?>
      <button class="btn btn-sm" type="button" data-modal-open="dlg-job-assets"><?= $assets ? 'Add or remove properties' : '+ New property' ?></button>
    </div>
    <?php if (!$assets): ?>
      <p class="muted small">This client has no properties yet. Add the first
      one and it goes straight onto this job.</p>
    <?php else: ?>
      <?php $onJob = array_values(array_filter($assets,
                static fn ($a) => in_array((int) $a['id'], $picked, true))); ?>
      <?php if (!$onJob): ?>
        <p class="muted small">None of <?= e(cm_client_display_name($client)) ?>&rsquo;s
        properties are ticked onto this job yet, so there is nothing to sign
        in to. Tick them below and the logins appear here.</p>
      <?php else: ?>
        <p class="muted small mb-8">Websites, accounts and access needed for this work.</p>
        <?php foreach ($onJob as $a): ?>
          <?= cm_asset_signin_html($a) ?>
        <?php endforeach; ?>
      <?php endif; ?>

    <?php endif; ?>
  </div>

<?php 













 ?>
<?php 

 ?>
<?= cm_job_assets_dialog_html($assets, $picked,
                              cm_asset_add_form_html('fa_', 'add_new_asset', $errors, true),
                              cm_asset_add_refused('fa_', $errors)) ?>

  <?php  ?>
  <?php 









 ?>
  <?php if ($actions): ?>
  <div class="card" id="actions">
    <?php 






 ?>
    <div class="card-head">
      <div><h2 class="m-0">Tasks on this job</h2></div>
      <span class="spacer"></span>
      <button class="btn btn-sm" type="button" data-modal-open="dlg-job-action">Add a task</button>
    </div>
    <p class="muted small mt-8 mb-12">This job was set up with tasks, and its
    days come from them. New jobs do not work this way — a job now carries its
    own time and how often it runs, on <b>Edit</b> above, and puts itself on
    the calendar. Nothing here needs changing; it is left as it is so the days
    already on your calendar still add up.</p>
    <?php 



 ?>
    <?php
    $doneByAction = [];
    if ($actions) {
        $aids = array_map(static fn ($a) => (int) $a['id'], $actions);
        $in   = implode(',', array_fill(0, count($aids), '?'));
        foreach (cm_all("SELECT action_id, scheduled_date, completed_at, note, internal_note
                           FROM action_occurrence
                          WHERE action_id IN ($in) AND completed_at IS NOT NULL
                          ORDER BY completed_at DESC", $aids) as $r) {
            $doneByAction[(int) $r['action_id']][] = $r;
        }
    }
    ?>
    <?php if ($actions): ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Task</th><th>How often</th><th>Runs</th><th>Done</th><th></th><th class="num"></th></tr></thead>
          <tbody>
          <?php foreach ($actions as $a): $done = $doneByAction[(int) $a['id']] ?? []; ?>
            <tr>
              <td><b><?= e((string) $a['name']) ?></b></td>
              <td class="small"><?= e(cm_action_frequencies()[(string) $a['frequency']]) ?></td>
              <td class="small mono"><?= e(date('j M', strtotime((string) $a['start_date']))) ?> → <?= $a['end_date'] ? e(date('j M', strtotime((string) $a['end_date']))) : 'open' ?></td>
              <td class="small mono"><?= count($done) ?>×</td>
              <td class="small"><?= (int) $a['required'] === 1 ? 'required' : '<span class="muted">optional</span>' ?></td>
              <td class="num">
                <button class="btn btn-sm btn-quiet" type="button"
                        data-modal-open="dlg-anotes-<?= (int) $a['id'] ?>">View notes</button>
                <form method="post" class="inline"
                      data-confirm="Remove this task? Only possible while none of its days have been worked on.">
                  <?= cm_csrf_field() ?>
                  <input type="hidden" name="action" value="delete_action">
                  <input type="hidden" name="action_id" value="<?= (int) $a['id'] ?>">
                  <button class="btn btn-sm btn-quiet" type="submit">Remove</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php  ?>
      <?php foreach ($actions as $a): $done = $doneByAction[(int) $a['id']] ?? []; ?>
        <dialog class="modal" id="dlg-anotes-<?= (int) $a['id'] ?>">
          <div class="modal-box">
            <div class="modal-head">
              <h2>Notes &mdash; <?= e((string) $a['name']) ?></h2>
              <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
            </div>
            <?php if (!$done): ?>
              <p class="muted small">Not done yet &mdash; there are no notes to show.</p>
            <?php else: $last = $done[0]; ?>
              <p class="small mb-4"><b>Last done <?= e(date('j M Y', strtotime((string) $last['completed_at']))) ?>:</b></p>
              <?php if (trim((string) $last['note']) !== ''): ?>
                <p class="small prewrap m-0"><?= e((string) $last['note']) ?></p>
              <?php else: ?>
                <p class="muted small m-0">Blank &mdash; no notes.</p>
              <?php endif; ?>
              <?php if (trim((string) ($last['internal_note'] ?? '')) !== ''): ?>
                <p class="muted small prewrap mt-4 m-0">Internal: <?= e((string) $last['internal_note']) ?></p>
              <?php endif; ?>
              <?php if (count($done) > 1): ?>
                <h3 class="work-h mt-12">Every time it was done &mdash; <?= count($done) ?></h3>
                <?php foreach (array_slice($done, 0, 30) as $d): ?>
                  <p class="small m-0 mb-4">
                    <span class="mono small"><?= e(date('j M', strtotime((string) $d['completed_at']))) ?></span> &middot;
                    <?= trim((string) $d['note']) !== ''
                        ? e((string) $d['note'])
                        : '<span class="muted">blank, no notes</span>' ?></p>
                <?php endforeach; ?>
                <?php if (count($done) > 30): ?><p class="hint">Showing the last 30.</p><?php endif; ?>
              <?php endif; ?>
            <?php endif; ?>
            <div class="modal-foot">
              <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
            </div>
          </div>
        </dialog>
      <?php endforeach; ?>

      <?php 



















 ?>
    <?php endif; ?>

  </div>
  <?php endif;  ?>

<?php 










 ?>
<?php 



 ?>
<?php if ($actions): ?>
<dialog class="modal modal-wide" id="dlg-job-action"<?= $errAction ? ' data-modal-autoopen' : '' ?>>
  <div class="modal-box">
    <div class="modal-head">
      <h2>Add a task</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="add_action">
        <div class="row">
          <label class="lbl" for="a_name">What is done</label>
          <input type="text" id="a_name" name="a_name" maxlength="160"
                 placeholder="Publish Facebook post, Check comments, Weekly review…">
          <?= cm_field_error($errors, 'a_name') ?>
        </div>
        <div class="row">
          <label class="lbl" for="a_frequency">How often</label>
          <select id="a_frequency" name="a_frequency">
            <?php foreach (cm_action_frequencies() as $key => $label): ?>
              <option value="<?= e($key) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Weekly repeats on the weekday of the first day;
          monthly on that day of the month (a 31st falls on a short month's
          last day, never the month after).</div>
        </div>
        <div class="row">
          <label class="lbl" for="a_start">First day</label>
          <input type="date" id="a_start" name="a_start" value="<?= e((string) ($job['start_date'] ?? '')) ?>">
          <?= cm_field_error($errors, 'a_start') ?>
        </div>
        <div class="row">
          <label class="lbl" for="a_end">Last day</label>
          <input type="date" id="a_end" name="a_end" value="<?= e((string) ($job['end_date'] ?? '')) ?>">
          <?= cm_field_error($errors, 'a_end') ?>
          <div class="hint">A repeating task left empty uses the job's end
          date; with neither, the schedule rolls forward two months ahead,
          for ever, until you end it.</div>
        </div>
        <div class="row">
          <label class="lbl" for="a_minutes">Minutes it usually takes</label>
          <input class="f-sm" type="text" id="a_minutes" name="a_minutes" inputmode="numeric">
          <?= cm_field_error($errors, 'a_minutes') ?>
        </div>
        <label class="tick mb-12">
          <input type="checkbox" name="a_required" value="1" checked>
          <span><b>Required</b> — the job cannot complete while any of its
          days are still open. Untick for nice-to-have work.</span>
        </label>
        <div class="actions">
          <button class="btn" type="submit">Add the task</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
  </div>
</dialog>
<?php endif;  ?>

</div>

<?php 




 ?>
<aside class="side-rail">
<?php cm_client_context_cards($client); ?>
</aside>
</div>

<?php 














 ?>
  <div class="card" id="schedule">
    <div class="card-head">
      <h2>Schedule</h2>
    </div>
    <div>
    <?php 



 ?>
    <?php if (!$openOccs && !$settledOccs): ?>
      <p class="muted small">Nothing scheduled yet. Give the job a start date
      and how often it runs, on <b>Edit</b> above, and its days appear here —
      one row per day, ready to tick off.</p>
    <?php elseif (!$openOccs): ?>
      <p class="note note-ok"><b>Nothing open.</b> Every generated task is
      settled<?= $settledOccs ? ' — the record is under "already settled" below' : '' ?>.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="responsive-table schedule-table">
          <?php 







 ?>
          <thead><tr><th>Day</th><th>Task</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($openOccs as $o):
              $overdue = cm_occurrence_is_overdue($o);
              $today = (string) $o['scheduled_date'] === date('Y-m-d'); ?>
            <tr class="<?= $overdue ? 'occ-overdue' : '' ?>">
              <td class="mono small occ-date nowrap" data-label="Day">
                <?= e(date('D j M', strtotime((string) $o['scheduled_date']))) ?>
                <?= $overdue ? '<span class="pill pill-spam">overdue</span>' : ($today ? '<span class="pill pill-approved">today</span>' : '') ?>
              </td>
              <?php 



 ?>
              <td data-label="Task"><b><?= e((string) $o['action_name']) ?></b>
                <?php if (!(int) $o['required']): ?><span class="muted small">(optional)</span><?php endif; ?>
                <?php 











 ?>
                <?php 








                      $allowed = cm_occurrence_minutes($o); ?>
                <div class="occ-time">
                  <span>Time allowed:
                    <b class="mono"><?= $allowed > 0
                        ? e(cm_minutes_label($allowed))
                        : '&mdash;' ?></b></span>
                  <span>Time so far:
                    <b class="mono"><?= e(cm_minutes_label((int) $o['minutes_spent'])) ?></b></span>
                </div>
              </td>
              <td data-label="Actions">
                <?php 




 ?>
                <div class="occ-actions">
                <form method="post" class="inline">
                  <?= cm_csrf_field() ?>
                  <input type="hidden" name="occ_id" value="<?= (int) $o['id'] ?>">
                  <?php 












 ?>
                  <?php if ((string) $o['status'] !== 'in_progress'): ?>
                    <?php 














 ?>
                    <input type="hidden" name="then" value="task">
                    <button class="btn btn-sm" name="action" value="occ_start">Start</button>
                  <?php endif; ?>
                  <?php 














 ?>
                  <?php 














 ?>
                  <button class="btn btn-sm <?= (string) $o['status'] === 'in_progress' ? '' : 'btn-quiet' ?>" name="action" value="occ_complete">Mark as done</button>
                  <button class="btn btn-sm btn-quiet" name="action" value="occ_cancel">Cancel</button>
                </form>
                <?php 















 ?>
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
      <?php foreach ($openOccs as $o): ?>
        <?php cm_occurrence_edit_window($o); ?>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($settledOccs): ?>
      <?php 











 ?>
      <h3 class="work-h mt-16">Completed tasks</h3>
      <?php $noteWindows = []; ?>
      <div class="table-wrap">
          <table>
            <thead><tr><th>Day</th><th>Task</th><th>Outcome</th><th>Note</th><th class="num"></th></tr></thead>
            <tbody>
            <?php foreach ($settledOccs as $o): $ost = cm_occurrence_statuses()[(string) $o['status']]; ?>
              <tr>
                <td class="mono small"><?= e(date('D j M', strtotime((string) $o['scheduled_date']))) ?></td>
                <td class="small"><?= e((string) $o['action_name']) ?></td>
                <td><span class="pill <?= e($ost['pill']) ?>"><?= e($ost['label']) ?></span>
                  <?php if ((string) $o['status'] === 'rescheduled' && $o['moved_to']): ?>
                    <span class="muted small">→ <?= e(date('j M', strtotime((string) $o['moved_to']))) ?></span>
                  <?php endif; ?>
                </td>
                <?php 








                      $noteFull = trim((string) $o['note']);
                      $noteInt  = trim((string) ($o['internal_note'] ?? ''));
                      $noteOne  = preg_replace('/\s+/', ' ', $noteFull) ?? $noteFull;
                      $noteAny  = $noteFull !== '' || $noteInt !== '' || (string) $o['content_url'] !== '';
                      if ($noteAny) {
                          $noteWindows[] = ['id' => (int) $o['id'], 'task' => (string) $o['action_name'],
                                            'day' => (string) $o['scheduled_date'], 'note' => $noteFull,
                                            'internal' => $noteInt, 'url' => (string) $o['content_url']];
                      } ?>
                <td class="small">
                  <?php if ($noteAny): ?>
                    <?= e(mb_strlen($noteOne) > 90 ? mb_substr($noteOne, 0, 90) . '…' : $noteOne) ?>
                    <?php if ($noteInt !== ''): ?><span class="muted">&middot; internal note</span><?php endif; ?>
                    <button class="btn btn-sm btn-quiet note-expand" type="button"
                            data-modal-open="dlg-note-<?= (int) $o['id'] ?>">Expand</button>
                  <?php endif; ?>
                </td>
                <td class="num">
                  <?php if ((string) $o['status'] !== 'rescheduled'): ?>
                    <form method="post" class="inline"
                          data-confirm="Put this task back on the schedule for its original day?">
                      <?= cm_csrf_field() ?>
                      <input type="hidden" name="occ_id" value="<?= (int) $o['id'] ?>">
                      <button class="btn btn-sm btn-quiet" name="action" value="occ_reopen">Reopen</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
      </div>
      <?php 
 ?>
      <?php 

 ?>
      <?php foreach ($noteWindows as $nw): ?>
        <dialog class="modal modal-wider" id="dlg-note-<?= (int) $nw['id'] ?>">
          <div class="modal-box">
            <div class="modal-head">
              <h2><?= e($nw['task']) ?> &mdash; <?= e(date('D j M', strtotime($nw['day']))) ?></h2>
              <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
            </div>
            <form method="post">
              <?= cm_csrf_field() ?>
              <input type="hidden" name="action" value="occ_notes">
              <input type="hidden" name="occ_id" value="<?= (int) $nw['id'] ?>">
              <div class="form-2col">
                <div class="form-col">
                  <div class="row">
                    <label class="lbl" for="nw_note_<?= (int) $nw['id'] ?>">Client note <span class="muted">&mdash; what goes on their summary</span></label>
                    <textarea id="nw_note_<?= (int) $nw['id'] ?>" name="occ_note" rows="8" class="f-full"><?= e($nw['note']) ?></textarea>
                  </div>
                </div>
                <div class="form-col">
                  <div class="row">
                    <label class="lbl" for="nw_int_<?= (int) $nw['id'] ?>">Internal note <span class="muted">&mdash; never sent</span></label>
                    <textarea id="nw_int_<?= (int) $nw['id'] ?>" name="occ_internal" rows="8" class="f-full"><?= e($nw['internal']) ?></textarea>
                  </div>
                </div>
              </div>
              <div class="row">
                <label class="lbl" for="nw_url_<?= (int) $nw['id'] ?>">Link to the work</label>
                <input type="text" inputmode="url" id="nw_url_<?= (int) $nw['id'] ?>" name="occ_url" class="f-full"
                       autocomplete="off" spellcheck="false" value="<?= e($nw['url']) ?>" placeholder="example.com/the-page">
              </div>
              <div class="modal-foot">
                <button class="btn" type="submit">Save</button>
                <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
              </div>
            </form>
          </div>
        </dialog>
      <?php endforeach; ?>
    <?php endif; ?>
      </div>
  </div>


<?php 










 ?>
<?php 



 ?>
<?php 









      $editFill = (string) ($_POST['action'] ?? '') === 'save_job' && $errJobEdit
          ? ['name' => (string) ($_POST['e_name'] ?? ''), 'start' => (string) ($_POST['e_start'] ?? ''),
             'end' => (string) ($_POST['e_end'] ?? ''), 'days' => (string) ($_POST['e_days'] ?? ''),
             'perday' => (string) ($_POST['e_perday'] ?? ''), 'hours' => (string) ($_POST['e_hours'] ?? ''),
             'amount' => (string) ($_POST['e_amount'] ?? ''), 'setup' => (string) ($_POST['e_setup'] ?? ''),
             'completion' => (string) ($_POST['e_completion'] ?? ''),
             'completion_mode' => (string) ($_POST['e_completion_mode'] ?? ''),
             'priority' => (string) ($_POST['e_priority'] ?? ''), 'tags' => (string) ($_POST['e_tags'] ?? ''),
             'notes' => (string) ($_POST['e_notes'] ?? ''), 'description' => (string) ($_POST['e_description'] ?? ''),
             'frequency' => (string) ($_POST['e_frequency'] ?? '')]
          : ['name' => (string) $job['name'], 'start' => (string) ($job['start_date'] ?? ''),
             'end' => (string) ($job['end_date'] ?? ''),
             'days' => (int) ($job['duration_days'] ?? 0) > 0 ? (string) (int) $job['duration_days'] : '',
             'perday' => (int) ($job['per_day_minutes'] ?? 0) > 0 ? (string) (int) $job['per_day_minutes'] : '',
             'hours' => (int) ($job['est_minutes'] ?? 0) > 0 ? rtrim(rtrim(number_format(((int) $job['est_minutes']) / 60, 2, '.', ''), '0'), '.') : '',
             'amount' => (int) ($job['amount_cents'] ?? 0) > 0 ? rtrim(rtrim(number_format(((int) $job['amount_cents']) / 100, 2, '.', ''), '0'), '.') : '',
             'setup' => (int) ($job['setup_minutes'] ?? 0) > 0 ? (string) (int) $job['setup_minutes'] : '',
             'completion' => (int) ($job['completion_minutes'] ?? 0) > 0 ? (string) (int) $job['completion_minutes'] : '',
             'completion_mode' => (string) ($job['completion_mode'] ?? ''),
             'priority' => (string) ($job['priority'] ?? 'normal'), 'tags' => (string) ($job['tags'] ?? ''),
             'notes' => (string) ($job['notes'] ?? ''), 'description' => (string) ($job['description'] ?? ''),
             'frequency' => (string) ($job['frequency'] ?? '')];


?>
<dialog class="modal modal-widest" id="dlg-job-edit"<?= $errJobEdit ? ' data-modal-autoopen' : '' ?>>
  <div class="modal-box">
    <div class="modal-head">
      <h2>Edit the job</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <p class="modal-sub">Saving never touches work already done.</p>
    <?php if ($errJobEdit): ?>
      <div class="note note-error">
        <?php foreach (['name', 'tags', 'amount', 'end_date', 'est_hours', 'duration_days', 'per_day_minutes',
                        'setup_minutes', 'completion_minutes', 'start_date'] as $ek): ?>
          <?php if (isset($errors[$ek])): ?><div><?= e((string) $errors[$ek]) ?></div><?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="save_job">
        <?= cm_job_form_fields_html('e_', (int) $client['id'], false, $editFill, true) ?>
        <div class="modal-foot">
          <button class="btn" type="submit">Save job details</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
  </div>
</dialog>

<?php cm_foot(); ?>
