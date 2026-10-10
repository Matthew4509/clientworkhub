<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0







































declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }

require_once CM_LIB . '/linkexpiry.php';
require_once CM_LIB . '/signature.php';



require_once CM_LIB . '/clients.php';


const CM_WORK_SUMMARY_TABLE = 'work_summary';










function cm_work_summary_ready(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    require_once CM_LIB . '/migrate.php';
    return $ok = cm_schema_has_table(CM_WORK_SUMMARY_TABLE);
}










function cm_work_summary_token(): string
{
    return bin2hex(random_bytes(16));
}

function cm_work_summary_hash(string $token): string
{
    return hash('sha256', $token);
}


function cm_work_summary_url(string $token): string
{
    return cm_url('portal/summary.php?k=' . rawurlencode($token));
}









function cm_work_summary_token_of(array $row): string
{
    $enc = (string) ($row['token_enc'] ?? '');
    if ($enc === '') return '';
    try { return (string) cm_decrypt($enc); } catch (Throwable $ex) { return ''; }
}













function cm_work_summary_issue(int $jobId, ?int $clientId, array $rows, array $opts, string $actor): array
{
    if (!cm_work_summary_ready()) {
        return ['ok' => false, 'id' => 0, 'token' => '', 'url' => '',
                'error' => 'Sharing a summary needs a database update that has not run yet. '
                         . 'Open Update & backup and apply what is waiting.'];
    }

    $token = cm_work_summary_token();
    $days  = cm_link_expiry_days($opts['expiry_days'] ?? null, false);






    $frozen = array_map(static fn(array $o): array => [
        'date' => (string) ($o['scheduled_date'] ?? ''),
        'task' => (string) ($o['action_name'] ?? ''),
        'note' => trim((string) ($o['note'] ?? '')),
        'url'  => (string) ($o['content_url'] ?? ''),
    ], array_values($rows));

    try {
        $id = cm_insert(
            'INSERT INTO work_summary
                (job_id, client_id, token_hash, token_enc, heading, intro, snapshot,
                 include_time, created_at, created_by, expires_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $jobId,
                $clientId !== null && $clientId > 0 ? $clientId : null,
                cm_work_summary_hash($token),
                cm_encrypt($token),
                mb_substr(trim((string) ($opts['heading'] ?? '')), 0, 190),
                trim((string) ($opts['intro'] ?? '')),
                json_encode($frozen, JSON_UNESCAPED_UNICODE),
                !empty($opts['include_time']) ? 1 : 0,
                cm_now(),
                mb_substr($actor, 0, 190),
                cm_link_expires_at($days),
            ]);
    } catch (Throwable $ex) {
        error_log('work summary issue failed: ' . $ex->getMessage());
        return ['ok' => false, 'id' => 0, 'token' => '', 'url' => '',
                'error' => 'The link could not be created and nothing was saved. '
                         . 'Check Update & backup for a waiting database update; the server error log has the detail.'];
    }

    cm_audit('summary.shared', 'client', $clientId ?: null,
             'a work summary link was issued for job ' . $jobId . ' by ' . $actor
             . ($days !== null ? ' (expires in ' . $days . ' days)' : ''));

    return ['ok' => true, 'id' => $id, 'token' => $token,
            'url' => cm_work_summary_url($token), 'error' => ''];
}


function cm_work_summaries_for_job(int $jobId): array
{
    if (!cm_work_summary_ready()) return [];
    try {
        return cm_all('SELECT * FROM work_summary WHERE job_id = ? ORDER BY id DESC', [$jobId]);
    } catch (Throwable $ex) { return []; }
}


function cm_work_summary_revoke(int $id, string $actor): void
{
    if (!cm_work_summary_ready()) return;
    cm_q('UPDATE work_summary SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL', [cm_now(), $id]);
    cm_audit('summary.share_revoked', '', null, 'work summary link ' . $id . ' revoked by ' . $actor);
}










function cm_work_summary_resolve(?string $token): ?array
{
    if (!cm_work_summary_ready()) return null;
    $token = trim((string) $token);


    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;

    try {
        $row = cm_row('SELECT * FROM work_summary WHERE token_hash = ?', [cm_work_summary_hash($token)]);
    } catch (Throwable $ex) { return null; }

    if (!$row) return null;
    if ($row['revoked_at'] !== null) return null;
    if (cm_link_expired($row['expires_at'] === null ? null : (string) $row['expires_at'])) return null;

    if ($row['client_id'] !== null
        && (string) cm_val('SELECT status FROM client WHERE id = ?', [(int) $row['client_id']]) === 'inactive') {
        return null;
    }
    return $row;
}








function cm_work_summary_rows(array $row): array
{
    $raw = json_decode((string) ($row['snapshot'] ?? ''), true);
    if (!is_array($raw)) return [];
    $out = [];
    foreach ($raw as $r) {
        if (!is_array($r)) continue;
        $out[] = ['date' => (string) ($r['date'] ?? ''), 'task' => (string) ($r['task'] ?? ''),
                  'note' => (string) ($r['note'] ?? ''), 'url' => (string) ($r['url'] ?? '')];
    }
    return $out;
}









function cm_work_summary_opened(array $row): void
{
    if ($row['opened_at'] !== null) return;
    try { cm_q('UPDATE work_summary SET opened_at = ? WHERE id = ? AND opened_at IS NULL',
               [cm_now(), (int) $row['id']]); }
    catch (Throwable $ex) {  }
}








function cm_work_summary_approve(array $row, string $dataUri, string $name): string
{
    $problem = cm_signature_problem($dataUri, $name);
    if ($problem !== '') return $problem;





    if ($row['approved_at'] !== null) return '';

    try {
        cm_q('UPDATE work_summary SET signature = ?, signed_name = ?, approved_at = ?
               WHERE id = ? AND approved_at IS NULL',
             [$dataUri, mb_substr(trim($name), 0, 120), cm_now(), (int) $row['id']]);
    } catch (Throwable $ex) {
        error_log('work summary approve failed: ' . $ex->getMessage());
        return 'That could not be saved just now. Try once more, and if it keeps failing please reply by email instead.';
    }

    cm_audit('summary.approved', 'client', $row['client_id'] !== null ? (int) $row['client_id'] : null,
             'work summary ' . (int) $row['id'] . ' signed off by ' . trim($name));
    return '';
}








function cm_work_summary_reply(array $row, string $text): string
{
    $text = trim($text);
    if ($text === '') return 'Write something in the box first.';
    if (mb_strlen($text) > 5000) $text = mb_substr($text, 0, 5000);

    $existing = trim((string) ($row['feedback'] ?? ''));
    $stamped  = '[' . date('j M Y, g:ia') . '] ' . $text;
    $merged   = $existing === '' ? $stamped : $existing . "\n\n" . $stamped;


    if (strlen($merged) > 65000) {
        return 'This summary has no room for more replies here. Please reply by email instead.';
    }

    try {
        cm_q('UPDATE work_summary SET feedback = ?, replied_at = ? WHERE id = ?',
             [$merged, cm_now(), (int) $row['id']]);
    } catch (Throwable $ex) {
        error_log('work summary reply failed: ' . $ex->getMessage());
        return 'That could not be saved just now. Try once more, and if it keeps failing please reply by email instead.';
    }

    cm_audit('summary.replied', 'client', $row['client_id'] !== null ? (int) $row['client_id'] : null,
             'work summary ' . (int) $row['id'] . ' had a reply from the client');
    return '';
}
















function cm_work_summary_notify(array $row, ?array $job, ?array $client, string $what, string $comment = ''): void
{
    try {
        require_once CM_LIB . '/mail.php';
        $to = cm_mail_reply_to() !== '' ? cm_mail_reply_to() : cm_mail_from();
        if ($to === '') return;

        $who  = $client !== null ? cm_client_display_name($client) : 'A client';
        $jobN = $job !== null ? (string) $job['name'] : ('job ' . (int) $row['job_id']);

        $subject = $what === 'approved'
            ? 'Signed off: ' . $jobN
            : 'Reply on the work summary: ' . $jobN;

        $body = $what === 'approved'
            ? $who . ' has signed off the work summary for "' . $jobN . '".' . "\n"
              . 'Signed as: ' . trim((string) ($row['signed_name'] ?? '')) . "\n"
            : $who . ' replied to the work summary for "' . $jobN . '" without signing it off.' . "\n";

        if ($comment !== '') {
            $body .= "\nWhat they said:\n" . $comment . "\n";
        }
        $body .= "\nThe summary and everything they have said is on the job's summary screen:\n"
               . cm_url('admin/summary.php?id=' . (int) $row['job_id']) . "\n";




        cm_mail($to, $subject, $body, ['kind' => 'summary_' . $what]);
    } catch (Throwable $ex) {
        error_log('work summary notify failed: ' . $ex->getMessage());
    }
}











function cm_work_summary_state(array $row): array
{
    if ($row['revoked_at'] !== null) {
        return ['key' => 'revoked', 'label' => 'Link stopped', 'pill' => 'pill', 'when' => (string) $row['revoked_at']];
    }
    if ($row['approved_at'] !== null) {
        return ['key' => 'approved', 'label' => 'Signed off', 'pill' => 'pill-approved', 'when' => (string) $row['approved_at']];
    }
    if ($row['replied_at'] !== null) {
        return ['key' => 'replied', 'label' => 'Replied', 'pill' => 'pill-on', 'when' => (string) $row['replied_at']];
    }
    if (cm_link_expired($row['expires_at'] === null ? null : (string) $row['expires_at'])) {
        return ['key' => 'expired', 'label' => 'Expired', 'pill' => 'pill', 'when' => (string) $row['expires_at']];
    }
    if ($row['opened_at'] !== null) {
        return ['key' => 'opened', 'label' => 'Opened', 'pill' => 'pill-on', 'when' => (string) $row['opened_at']];
    }
    return ['key' => 'sent', 'label' => 'Not opened yet', 'pill' => 'pill', 'when' => (string) $row['created_at']];
}
