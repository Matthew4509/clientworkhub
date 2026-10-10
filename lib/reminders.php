<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0









declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }




require_once CM_LIB . '/clients.php';


require_once CM_LIB . '/unsubscribe.php';

function cm_reminder_statuses(): array
{
    return [
        'pending'   => ['label' => 'Pending',   'pill' => 'pill-pending'],
        'snoozed'   => ['label' => 'Snoozed',   'pill' => 'pill-live'],
        'completed' => ['label' => 'Done',      'pill' => 'pill-approved'],
        'cancelled' => ['label' => 'Cancelled', 'pill' => 'pill-spam'],
    ];
}

function cm_reminder_repeats(): array
{
    return [
        'none'    => 'Once',
        'daily'   => 'Every day',
        'weekly'  => 'Every week',
        'monthly' => 'Every month',
        'yearly'  => 'Every year',



        '2years'  => 'Every 2 years',
        '3years'  => 'Every 3 years',
    ];
}

function cm_communication_kinds(): array
{
    return [
        'email'   => 'Email',
        'phone'   => 'Phone call',
        'sms'     => 'Text message',
        'meeting' => 'Meeting',
        'other'   => 'Other',
    ];
}






function cm_reminder_next_date(string $from, string $repeat, ?int $anchorDay): string
{
    $d = new DateTimeImmutable($from);
    switch ($repeat) {
        case 'daily':  return $d->modify('+1 day')->format('Y-m-d');
        case 'weekly': return $d->modify('+7 days')->format('Y-m-d');
        case 'monthly':
            $day = $anchorDay ?? (int) $d->format('j');
            $y = (int) $d->format('Y');
            $m = (int) $d->format('n') + 1;
            if ($m > 12) { $m = 1; $y++; }
            $dim = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m)))->format('t');
            return sprintf('%04d-%02d-%02d', $y, $m, min($day, $dim));
        case 'yearly':
        case '2years':
        case '3years':



            $step = $repeat === '3years' ? 3 : ($repeat === '2years' ? 2 : 1);
            $day = $anchorDay ?? (int) $d->format('j');
            $y = (int) $d->format('Y') + $step;
            $m = (int) $d->format('n');
            $dim = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m)))->format('t');
            return sprintf('%04d-%02d-%02d', $y, $m, min($day, $dim));
    }
    return $from;
}

function cm_reminder(int $id): ?array
{
    return cm_row('SELECT * FROM reminder WHERE id = ?', [$id]);
}











function cm_days_between(string $a, string $b): int
{
    try {
        $da = new DateTimeImmutable(substr($a, 0, 10));
        $db = new DateTimeImmutable(substr($b, 0, 10));
        return $da <= $db ? (int) $da->diff($db)->days : -(int) $db->diff($da)->days;
    } catch (Throwable $ex) {
        return 0;
    }
}


function cm_reminder_repeat_days(string $repeat): int
{
    return ['daily' => 1, 'weekly' => 7, 'monthly' => 30, 'yearly' => 365,
            '2years' => 730, '3years' => 1095][$repeat] ?? 0;
}


function cm_reminder_interval_days(array $r): int
{
    $set = 0;
    $created = substr((string) ($r['created_at'] ?? ''), 0, 10);


    $due = (string) (($r['original_due'] ?? null) !== null && (string) $r['original_due'] !== ''
                     ? $r['original_due'] : ($r['due_date'] ?? ''));
    if ($created !== '' && $due !== '') {
        $set = cm_days_between($created, $due);
    }
    return max($set, cm_reminder_repeat_days((string) ($r['repeat_every'] ?? 'none')));
}


const CM_REMINDER_LONG_DAYS = 170;

function cm_reminder_is_long(array $r): bool
{
    return cm_reminder_interval_days($r) >= CM_REMINDER_LONG_DAYS;
}




function cm_smart_long_paused(): bool
{
    try { return (string) cm_setting('smart.pause_long', '0') === '1'; }
    catch (Throwable $ex) { return false; }
}





function cm_smart_all_paused(): bool
{
    try { return (string) cm_setting('smart.pause_all', '0') === '1'; }
    catch (Throwable $ex) { return false; }
}









function cm_smart_no_push_vip(): bool
{
    try { return (string) cm_setting('smart.no_push_vip', '1') !== '0'; }
    catch (Throwable $ex) { return true; }
}


function cm_reminder_create(string $title, string $due, string $repeat = 'none',
                            string $priority = 'normal', ?int $clientId = null,
                            ?int $jobId = null, ?int $assetId = null,
                            string $description = ''): int
{
    if (!isset(cm_reminder_repeats()[$repeat])) $repeat = 'none';
    $anchor = in_array($repeat, ['monthly', 'yearly', '2years', '3years'], true)
        ? (int) (new DateTimeImmutable($due))->format('j') : null;
    return cm_insert('INSERT INTO reminder (title, description, due_date, repeat_every, anchor_day,
                                            priority, client_id, job_id, asset_id, created_at, updated_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                     [mb_substr($title, 0, 190), $description, $due, $repeat, $anchor,
                      $priority, $clientId, $jobId, $assetId, cm_now(), cm_now()]);
}





function cm_reminder_complete(array $r): string
{
    cm_q("UPDATE reminder SET status='completed', completed_at=?, updated_at=? WHERE id=?",
         [cm_now(), cm_now(), (int) $r['id']]);
    if ((string) $r['repeat_every'] === 'none') return '';

    $next = cm_reminder_next_date((string) $r['due_date'], (string) $r['repeat_every'],
                                  $r['anchor_day'] !== null ? (int) $r['anchor_day'] : null);
    cm_insert('INSERT INTO reminder (title, description, due_date, repeat_every, anchor_day,
                                     priority, client_id, job_id, asset_id, created_at, updated_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
              [(string) $r['title'], (string) ($r['description'] ?? ''), $next,
               (string) $r['repeat_every'], $r['anchor_day'], (string) $r['priority'],
               $r['client_id'], $r['job_id'], $r['asset_id'], cm_now(), cm_now()]);
    return $next;
}















function cm_reminder_pending_sql(string $alias = 'r'): string
{
    $a = $alias === '' ? '' : $alias . '.';
    return "{$a}kind = 'nudge'
            AND ( ({$a}status = 'pending' AND {$a}due_date <= ?)
               OR ({$a}status = 'snoozed' AND {$a}snoozed_until <= ?) )";
}










function cm_reminders_due(): array
{
    return cm_all(
        "SELECT r.*, c.first_name, c.last_name, c.company, c.email AS client_email,
                c.vip AS client_vip
         FROM reminder r LEFT JOIN client c ON c.id = r.client_id
         WHERE " . cm_reminder_pending_sql('r') . "
         ORDER BY r.due_date", [date('Y-m-d'), date('Y-m-d')]);
}









function cm_followup_schedule(int $clientId, ?int $jobId, string $due,
                              string $title, string $templateKey = 'client_followup'): int
{
    return cm_insert(
        'INSERT INTO reminder (title, kind, template_key, description, due_date, repeat_every,
                               priority, client_id, job_id, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [mb_substr($title, 0, 190), 'email', $templateKey, '', $due, 'none',
         'normal', $clientId, $jobId, cm_now(), cm_now()]);
}




























const CM_FOLLOWUP_BATCH = 20;







function cm_followup_hourly_cap(): int
{
    $v = '25';
    try { $v = (string) cm_setting('mail.auto_hourly', '25'); }
    catch (Throwable $ex) {}
    if (!function_exists('cm_mail_hourly_choices') || !isset(cm_mail_hourly_choices()[$v])) {
        $v = '25';
    }
    return (int) $v;
}









function cm_followups_sent_last_hour(): int
{
    try {
        return (int) cm_val(
            "SELECT COUNT(*) FROM mail_log
              WHERE kind = 'client_followup' AND result IN ('sent', 'spooled')
                AND created_at >= ?", [date('Y-m-d H:i:s', time() - 3600)]);
    } catch (Throwable $ex) {


        return PHP_INT_MAX;
    }
}







function cm_followups_due(int $limit = CM_FOLLOWUP_BATCH): array
{
    $limit = max(1, min(500, $limit));





    return cm_all(
        "SELECT r.*, c.first_name, c.last_name, c.company, c.email AS client_email,
                c.status AS client_status, c.vip AS client_vip, j.name AS job_name
           FROM reminder r
           JOIN client c ON c.id = r.client_id
      LEFT JOIN job j ON j.id = r.job_id
          WHERE r.kind = 'email' AND r.status = 'pending'
            AND r.sent_at IS NULL AND r.due_date <= ?
          ORDER BY r.due_date, r.id
          LIMIT {$limit}", [date('Y-m-d')]);
}


function cm_followups_waiting(): int
{
    return (int) cm_val(
        "SELECT COUNT(*) FROM reminder r JOIN client c ON c.id = r.client_id
          WHERE r.kind = 'email' AND r.status = 'pending'
            AND r.sent_at IS NULL AND r.due_date <= ?", [date('Y-m-d')]);
}




















function cm_followups_send_due(int $limit = CM_FOLLOWUP_BATCH): int
{
    require_once CM_LIB . '/emails.php';
    $sent = 0;






    if (cm_smart_all_paused() && !cm_smart_no_push_vip()) return 0;





    $room = cm_followup_hourly_cap() - cm_followups_sent_last_hour();
    if ($room <= 0) return 0;
    $limit = min($limit, $room);

    foreach (cm_followups_due($limit) as $r) {
        $to = trim((string) ($r['client_email'] ?? ''));
        if ($to === '' || !cm_email_valid($to)) {



            cm_q('UPDATE reminder SET sent_at = ?, status = ?, updated_at = ? WHERE id = ?',
                 [cm_now(), 'completed', cm_now(), (int) $r['id']]);
            cm_audit('followup.skipped', 'client', (int) $r['client_id'],
                     'follow-up not sent — no email address on the client');
            continue;
        }











        if (cm_client_is_unsubscribed((int) $r['client_id'])) {
            cm_q('UPDATE reminder SET sent_at = ?, status = ?, updated_at = ? WHERE id = ?',
                 [cm_now(), 'completed', cm_now(), (int) $r['id']]);
            cm_audit('followup.skipped', 'client', (int) $r['client_id'],
                     'follow-up not sent — the client has unsubscribed from all emails');
            continue;
        }












        if ((string) ($r['client_status'] ?? '') === 'inactive') {
            cm_q("UPDATE reminder SET status = 'cancelled', updated_at = ? WHERE id = ?",
                 [cm_now(), (int) $r['id']]);
            cm_audit('followup.skipped', 'client', (int) $r['client_id'],
                     'follow-up cancelled, not sent — the client is inactive, and an inactive '
                     . 'client gets no reminders');
            continue;
        }










        $vipShielded = cm_smart_no_push_vip() && (int) ($r['client_vip'] ?? 0) === 1;
        if (!$vipShielded && cm_smart_all_paused()) {
            continue;
        }
        if (!$vipShielded && cm_smart_long_paused() && cm_reminder_is_long($r)) {
            continue;
        }


        $claimed = cm_q('UPDATE reminder SET sent_at = ?, updated_at = ?
                          WHERE id = ? AND sent_at IS NULL',
                        [cm_now(), cm_now(), (int) $r['id']])->rowCount();
        if ($claimed < 1) continue;

        $name = cm_client_display_name([
            'id' => $r['client_id'], 'first_name' => $r['first_name'],
            'last_name' => $r['last_name'], 'company' => $r['company'],
            'email' => $r['client_email']]);



        require_once CM_LIB . '/publicsite.php';
        $myLink = '';
        try {
            $myLink = cm_public_enabled() ? cm_link_url(cm_link_for((int) $r['client_id'])) : '';
        } catch (Throwable $ex) {}

        $msg = cm_email_compose((string) $r['template_key'], [
            'client_name'       => $name,
            'client_first_name' => trim((string) ($r['first_name'] ?? '')) !== ''
                                   ? (string) $r['first_name'] : $name,
            'job_name'          => (string) ($r['job_name'] ?? ''),
            'my_link'           => $myLink,
        ]);

        $ok = cm_mail($to, $msg['subject'], $msg['body'],
                      ['kind' => 'client_followup', 'client_id' => (int) $r['client_id']]);
        if ($ok) {
            cm_q("UPDATE reminder SET status = 'completed', completed_at = ?, updated_at = ?
                   WHERE id = ?", [cm_now(), cm_now(), (int) $r['id']]);
            cm_audit('followup.sent', 'client', (int) $r['client_id'],
                     'follow-up emailed to ' . $to
                     . ((string) ($r['job_name'] ?? '') !== '' ? ' about "' . (string) $r['job_name'] . '"' : ''));
            $sent++;
        } else {

            cm_q('UPDATE reminder SET sent_at = NULL, updated_at = ? WHERE id = ?',
                 [cm_now(), (int) $r['id']]);
            cm_audit('followup.failed', 'client', (int) $r['client_id'],
                     'the mailer refused a follow-up to ' . $to . ' — it will be tried again');
        }
    }
    return $sent;
}



function cm_client_communications(int $clientId, int $limit = 50): array
{
    return cm_all('SELECT * FROM communication WHERE client_id = ?
                   ORDER BY happened_at DESC LIMIT ' . (int) $limit, [$clientId]);
}

function cm_client_last_communication(int $clientId): ?array
{
    return cm_row('SELECT * FROM communication WHERE client_id = ?
                   ORDER BY happened_at DESC LIMIT 1', [$clientId]);
}
