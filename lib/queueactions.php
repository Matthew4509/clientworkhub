<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0






declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }




require_once CM_LIB . '/jobs.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/schedule.php';
require_once CM_LIB . '/reminders.php';
require_once CM_LIB . '/view.php';




require_once CM_LIB . '/format.php';





function cm_queue_handle_post(array $admin, string $back): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
    cm_require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if (in_array($action, ['occ_complete', 'occ_complete_next', 'occ_skip', 'occ_edit', 'occ_cancel',
                           'occ_start', 'occ_pause'], true)) {
        $occ = cm_occurrence((int) ($_POST['occ_id'] ?? 0));
        if ($occ && cm_occurrence_is_open($occ)) {
            $job = cm_job((int) $occ['job_id']);







            $note = isset($_POST['occ_note']) ? trim((string) $_POST['occ_note']) : null;
            $internal = isset($_POST['occ_internal']) ? trim((string) $_POST['occ_internal']) : null;
            $url  = isset($_POST['occ_url']) ? trim((string) $_POST['occ_url']) : null;


            if ($url !== null && $url !== '') {
                $addr = cm_web_address($url);
                if ($addr['error'] !== '') {
                    cm_flash('Link to the work: ' . $addr['error'], 'error');
                    cm_redirect($back);
                }
                $url = $addr['url'];
            }
            $when = date('j M', strtotime((string) $occ['scheduled_date']));













            if ($action === 'occ_start') {
                $refusal = cm_timer_start($occ, (string) $admin['email']);
                cm_flash($refusal === ''
                    ? 'Timing "' . (string) $occ['action_name'] . '" — the clock is running on the server.'
                    : $refusal, $refusal === '' ? 'ok' : 'error');












                if ($refusal === '' && (string) ($_POST['then'] ?? '') === 'task') {
                    cm_q('UPDATE job SET updated_at = ? WHERE id = ?', [cm_now(), (int) $occ['job_id']]);
                    cm_redirect('admin/task.php?id=' . (int) $occ['id']);
                }
            } elseif ($action === 'occ_pause' && (string) $occ['status'] === 'in_progress') {
                $mins = cm_timer_pause($occ, (string) $admin['email']);
                cm_flash(cm_minutes_label($mins) . ' banked. The task is back on the schedule — '
                       . 'starting it again adds to the same total.');







            } elseif ($action === 'occ_complete' || $action === 'occ_complete_next') {
                cm_occurrence_complete($occ, $note, $url, (string) $admin['email'], $internal);
                $spent = cm_time_totals('occurrence', (int) $occ['id']);
                $said = 'Done: ' . (string) $occ['action_name'] . ' — ' . $when
                      . ($spent['total'] > 0 ? ' (' . cm_minutes_label($spent['total']) . ' tracked)' : '') . '.';
                if ($action === 'occ_complete_next') {
                    $next = null;
                    foreach (cm_work_queue() as $cand) {
                        if ((int) $cand['id'] !== (int) $occ['id']) { $next = $cand; break; }
                    }
                    if ($next !== null) {
                        $refusal = cm_timer_start(cm_occurrence((int) $next['id']), (string) $admin['email']);
                        $said .= $refusal === ''
                            ? ' Next up, already timing: "' . (string) $next['action_name'] . '".'
                            : ' Next: "' . (string) $next['action_name'] . '".';
                    } else {
                        $said .= ' That was the last one — the queue is empty.';
                    }
                }
                cm_flash($said);
            } elseif ($action === 'occ_edit') {








                [$msg, $type] = cm_occurrence_edit_apply($occ, (string) $admin['email'],
                                                         (int) $job['client_id']);
                cm_flash($msg, $type);
            } elseif ($action === 'occ_cancel') {




                cm_occurrence_settle((int) $occ['id'], 'cancelled', $note);
                cm_audit('task.cancelled', 'client', (int) $job['client_id'],
                         (string) $occ['action_name'] . ' (' . $when . ') cancelled from the queue');
                cm_flash('Cancelled — it stays on the record as cancelled.');
            } elseif ($action === 'occ_skip') {
                cm_occurrence_settle((int) $occ['id'], 'skipped', $note,
                    ['completed_by' => (string) $admin['email'], 'started_at' => null]);
                cm_audit('task.skipped', 'client', (int) $job['client_id'],
                         (string) $occ['action_name'] . ' (' . $when . ') skipped from the queue');
                cm_flash('Skipped — recorded as skipped, not done.');
            }
            cm_q('UPDATE job SET updated_at = ? WHERE id = ?', [cm_now(), (int) $occ['job_id']]);
        }
        cm_redirect($back);
    }







    if ($action === 'reveal_asset') {
        require_once CM_LIB . '/assets.php';
        $running = cm_timer_running();
        $assetId = (int) ($_POST['asset_id'] ?? 0);
        $asset = cm_asset($assetId);
        if ($running !== null && $asset !== null
            && in_array($assetId, cm_job_asset_ids((int) $running['job_id']), true)) {
            $cred = cm_credential($assetId);
            if ($cred !== null) {
                $attempt = cm_reveal_attempt($admin, $cred,
                                             (string) $asset['name'], (int) $asset['client_id']);
                if ($attempt['ok']) {
                    header('Cache-Control: no-store, no-cache, must-revalidate');
                    header('Pragma: no-cache');
                    $GLOBALS['CM_WORK_REVEAL'] = ['asset_id' => $assetId, 'values' => $attempt['values']];
                } else {
                    cm_flash($attempt['error'], 'error');
                }
            }
        }
        return;
    }


    if ($action === 'appt_add') {





        require_once CM_LIB . '/appointments.php';
        $r = cm_appointment_create([
            'title'         => (string) ($_POST['ap_title'] ?? ''),
            'kind'          => (string) ($_POST['ap_kind'] ?? ''),
            'date'          => (string) ($_POST['ap_date'] ?? ''),
            'from'          => (string) ($_POST['ap_from'] ?? ''),
            'to'            => (string) ($_POST['ap_to'] ?? ''),
            'client_id'     => (int) ($_POST['ap_client'] ?? 0),
            'buffer_before' => (int) ($_POST['ap_before'] ?? 0),
            'buffer_after'  => (int) ($_POST['ap_after'] ?? 0),
            'repeat_days'   => (array) ($_POST['ap_days'] ?? []),
        ] + (isset($_POST['ap_workset'])
                ? ['is_work' => empty($_POST['ap_offwork'])]
                : []));

        cm_flash($r['ok']
            ? ($r['made'] > 1
                ? 'Appointment set, and ' . ($r['made'] - 1) . ' repeats — the day plans around each.'
                : 'Appointment set — the day plans around it.')
            : $r['error'], $r['ok'] ? 'ok' : 'error');
        cm_redirect($back);
    }
    if ($action === 'appt_delete') {
        cm_q('DELETE FROM appointment WHERE id = ?', [(int) ($_POST['appt_id'] ?? 0)]);
        cm_flash('Appointment removed.');
        cm_redirect($back);
    }


    if ($action === 'rem_done' || isset($_POST['rem_snooze_days'])) {
        $r = cm_reminder((int) ($_POST['reminder_id'] ?? 0));
        if ($r && in_array((string) $r['status'], ['pending', 'snoozed'], true)) {
            if ($action === 'rem_done') {
                $next = cm_reminder_complete($r);
                cm_flash('Done: ' . (string) $r['title']
                       . ($next !== '' ? '. The next one is set for ' . date('j M Y', strtotime($next)) . '.' : '.'));
            } else {
                $days = (int) ($_POST['rem_snooze_days'] ?? 1);
                if (!in_array($days, [1, 7], true)) $days = 1;
                $until = date('Y-m-d', strtotime('+' . $days . ' days'));
                cm_q("UPDATE reminder SET status='snoozed', snoozed_until=?, updated_at=? WHERE id=?",
                     [$until, cm_now(), (int) $r['id']]);
                cm_flash('Snoozed until ' . date('j M', strtotime($until)) . '.');
            }
        }
        cm_redirect($back);
    }
}
