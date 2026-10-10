<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0






























declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }









function cm_mail_kinds(): array
{
    return [
        'job_summary'     => ['label' => 'Work summary',   'client' => true],
        'client_followup' => ['label' => 'Follow-up',      'client' => true],
        'invoice'         => ['label' => 'Invoice',        'client' => true],
        'receipt'         => ['label' => 'Receipt',        'client' => true],
        'password_reset'  => ['label' => 'Password reset', 'client' => false],
        'tfa_code'        => ['label' => 'Sign-in code',   'client' => false],
        'booking_alert'   => ['label' => 'New booking',    'client' => false],
        'enquiry'         => ['label' => 'Enquiry',        'client' => false],
        'feedback'        => ['label' => 'Feedback',       'client' => false],
        'onboarding'      => ['label' => 'Onboarding',     'client' => false],
        'payment_alert'   => ['label' => 'Payment alert',  'client' => false],
    ];
}


function cm_mail_kind_label(string $kind): string
{
    $known = cm_mail_kinds()[$kind] ?? null;
    if ($known !== null) return $known['label'];

    return ucfirst(str_replace('_', ' ', trim($kind))) ?: 'Other';
}











function cm_mail_outcome(string $result): array
{
    $r = mb_strtolower(trim($result));

    if (str_starts_with($r, 'refused')) {



        $why = trim(substr($result, strlen('refused:')));
        return ['key' => 'refused', 'label' => 'Refused', 'pill' => 'pill-spam',
                'note' => $why !== '' ? $why : 'refused'];
    }
    if ($r === 'sent') {
        return ['key' => 'sent', 'label' => 'Sent', 'pill' => 'pill-approved', 'note' => ''];
    }
    if ($r === 'spooled') {

















        return ['key' => 'spooled', 'label' => 'Not sent', 'pill' => 'pill-pending',
                'note' => 'written to storage/mail/ — sending is off'];
    }
    return ['key' => 'failed', 'label' => 'Failed', 'pill' => 'pill-spam',
            'note' => $result];
}


function cm_mail_filters(): array
{
    return [
        'all'      => 'Everything',
        'sent'     => 'Sent',
        'spooled'  => 'Not sent',
        'refused'  => 'Refused',
        'failed'   => 'Failed',
        'clients'  => 'To clients',
    ];
}










function cm_mail_where(string $filter): array
{
    return match ($filter) {
        'sent'    => ['sql' => "result = 'sent'", 'params' => []],
        'spooled' => ['sql' => "result = 'spooled'", 'params' => []],
        'refused' => ['sql' => "result LIKE 'refused%'", 'params' => []],
        'failed'  => ['sql' => "result <> 'sent' AND result <> 'spooled' AND result NOT LIKE 'refused%'",
                      'params' => []],
        'clients' => ['sql' => 'client_id IS NOT NULL', 'params' => []],
        default   => ['sql' => '1 = 1', 'params' => []],
    };
}










function cm_mail_log_count(string $filter = 'all', string $search = ''): int
{
    $w = cm_mail_where($filter);
    $sql = $w['sql'];
    $params = $w['params'];
    $search = trim($search);
    if ($search !== '') {
        $like = '%' . $search . '%';
        $sql .= ' AND (m.sent_to LIKE ? OR m.subject LIKE ? OR m.body LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    try { return (int) cm_val("SELECT COUNT(*) FROM mail_log m WHERE $sql", $params); }
    catch (Throwable $ex) { return 0; }
}

function cm_mail_log(string $filter = 'all', int $limit = 200, string $search = '', int $offset = 0): array
{
    $w = cm_mail_where($filter);
    $sql = $w['sql'];
    $params = $w['params'];

    $search = trim($search);
    if ($search !== '') {
        $like = '%' . $search . '%';
        $sql .= ' AND (m.sent_to LIKE ? OR m.subject LIKE ? OR m.body LIKE ?)';
        array_push($params, $like, $like, $like);
    }

    try {
        return cm_all(
            "SELECT m.id, m.client_id, m.sent_to, m.subject, m.body, m.kind, m.result, m.created_at,
                    c.first_name, c.last_name, c.company, c.email AS client_email
               FROM mail_log m
               LEFT JOIN client c ON c.id = m.client_id
              WHERE $sql
              ORDER BY m.created_at DESC, m.id DESC
              LIMIT " . max(1, min(500, $limit)) . ' OFFSET ' . max(0, $offset), $params);
    } catch (Throwable $ex) {
        return [];
    }
}


function cm_mail_counts(): array
{
    $out = [];
    foreach (array_keys(cm_mail_filters()) as $key) {
        $w = cm_mail_where($key);
        try {
            $out[$key] = (int) cm_val('SELECT COUNT(*) FROM mail_log WHERE ' . $w['sql'], $w['params']);
        } catch (Throwable $ex) {
            $out[$key] = 0;
        }
    }
    return $out;
}









function cm_mail_for_client(int $clientId, int $limit = 50): array
{
    try {
        return cm_all(
            'SELECT id, sent_to, subject, body, kind, result, created_at
               FROM mail_log
              WHERE client_id = ?
              ORDER BY created_at DESC, id DESC
              LIMIT ' . max(1, min(200, $limit)), [$clientId]);
    } catch (Throwable $ex) {
        return [];
    }
}







function cm_mail_summary(array $row, int $chars = 90): string
{
    $s = trim((string) ($row['subject'] ?? ''));
    if ($s === '') {
        $s = trim(preg_replace('/\s+/', ' ', (string) ($row['body'] ?? '')) ?? '');
    }
    return mb_strlen($s) > $chars ? mb_substr($s, 0, $chars - 1) . '…' : $s;
}


















function cm_mail_filters_open(): array
{
    return ['sent' => 'Sent', 'all' => 'Everything', 'clients' => 'To clients'];
}

function cm_mail_filters_more(): array
{
    return ['spooled' => 'Not sent', 'refused' => 'Refused', 'failed' => 'Failed'];
}


function cm_reply_add(int $mailId, string $note, string $actor): int
{
    $note = trim($note);
    if ($note === '' || mb_strlen($note) > 20000) return 0;
    $clientId = null;
    try { $clientId = cm_val('SELECT client_id FROM mail_log WHERE id = ?', [$mailId]); }
    catch (Throwable $ex) { return 0; }
    if ($clientId === false) return 0;
    return (int) cm_insert(
        'INSERT INTO mail_reply (mail_id, client_id, note, created_by, created_at) VALUES (?, ?, ?, ?, ?)',
        [$mailId, $clientId !== null ? (int) $clientId : null, $note, $actor, cm_now()]);
}


function cm_replies_for_mail(int $mailId): array
{
    try {
        return cm_all('SELECT * FROM mail_reply WHERE mail_id = ? ORDER BY created_at, id', [$mailId]);
    } catch (Throwable $ex) { return []; }
}


function cm_mail_replied_counts(array $mailIds): array
{
    $ids = array_values(array_filter(array_map('intval', $mailIds), static fn(int $i): bool => $i > 0));
    if (!$ids) return [];
    try {
        $rows = cm_all('SELECT mail_id, COUNT(*) AS n FROM mail_reply WHERE mail_id IN ('
                       . implode(',', array_fill(0, count($ids), '?')) . ') GROUP BY mail_id', $ids);
    } catch (Throwable $ex) { return []; }
    $out = [];
    foreach ($rows as $r) $out[(int) $r['mail_id']] = (int) $r['n'];
    return $out;
}














function cm_replies_feed(string $search = ''): array
{
    require_once CM_LIB . '/clients.php';
    $out = [];
    try {
        $rows = cm_all(
            "SELECT r.id, r.client_id, r.completed_at, c.first_name, c.last_name, c.company,
                    c.email AS client_email, f.name AS form_name, t.name AS service_name, j.name AS job_name
               FROM onboarding_response r
          LEFT JOIN client c ON c.id = r.client_id
          LEFT JOIN onboarding_form f ON f.id = r.form_id
          LEFT JOIN job_template t ON t.id = r.template_id
          LEFT JOIN job j ON j.id = r.job_id
              WHERE r.status = 'complete'
              ORDER BY r.completed_at DESC LIMIT 1000");
    } catch (Throwable $ex) { $rows = []; }
    foreach ($rows as $r) {
        $title = (string) ($r['form_name'] ?: ($r['job_name'] ?: ($r['service_name'] ?: 'General questions')));
        $out[] = [
            'kind'      => 'questions',
            'id'        => (int) $r['id'],
            'client_id' => $r['client_id'] !== null ? (int) $r['client_id'] : null,
            'who'       => $r['client_id'] !== null ? cm_client_display_name([
                               'id' => $r['client_id'], 'first_name' => $r['first_name'],
                               'last_name' => $r['last_name'], 'company' => $r['company'],
                               'email' => $r['client_email']]) : '',
            'at'        => (string) ($r['completed_at'] ?? ''),
            'sort'      => strtotime((string) ($r['completed_at'] ?? '')) ?: 0,
            'title'     => $title,
            'notes'     => 0,
        ];
    }
    try {
        $rows = cm_all(
            "SELECT m.id, m.client_id, m.subject, MAX(p.created_at) AS at, COUNT(p.id) AS notes,
                    c.first_name, c.last_name, c.company, c.email AS client_email
               FROM mail_reply p
               JOIN mail_log m ON m.id = p.mail_id
          LEFT JOIN client c ON c.id = m.client_id
           GROUP BY m.id, m.client_id, m.subject, c.first_name, c.last_name, c.company, c.email
           ORDER BY at DESC LIMIT 1000");
    } catch (Throwable $ex) { $rows = []; }
    foreach ($rows as $r) {
        $out[] = [
            'kind'      => 'mailreply',
            'id'        => (int) $r['id'],
            'client_id' => $r['client_id'] !== null ? (int) $r['client_id'] : null,
            'who'       => $r['client_id'] !== null ? cm_client_display_name([
                               'id' => $r['client_id'], 'first_name' => $r['first_name'],
                               'last_name' => $r['last_name'], 'company' => $r['company'],
                               'email' => $r['client_email']]) : '',
            'at'        => (string) ($r['at'] ?? ''),
            'sort'      => strtotime((string) ($r['at'] ?? '')) ?: 0,
            'title'     => trim((string) $r['subject']) !== '' ? (string) $r['subject'] : '(no subject)',
            'notes'     => (int) $r['notes'],
        ];
    }
    $search = trim($search);
    if ($search !== '') {
        $needle = mb_strtolower($search);
        $out = array_values(array_filter($out, static function (array $r) use ($needle): bool {
            return str_contains(mb_strtolower($r['who']), $needle)
                || str_contains(mb_strtolower($r['title']), $needle);
        }));
    }
    usort($out, static fn(array $a, array $b): int => $b['sort'] <=> $a['sort']);
    return $out;
}












function cm_reply_window_html(array $r, ?array $mail = null): string
{
    require_once CM_LIB . '/onboarding.php';
    $idAttr = 'dlg-r-' . ($r['kind'] === 'questions' ? 'q' : 'm') . '-' . (int) $r['id'];
    ob_start();
    if ($r['kind'] === 'questions') { ?>
      <dialog class="modal modal-wider" id="<?= e($idAttr) ?>">
        <div class="modal-box">
          <div class="modal-head">
            <h2><?= e((string) $r['title']) ?></h2>
            <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
          </div>
          <p class="modal-sub"><?= e($r['who'] !== '' ? (string) $r['who'] : 'No client on file') ?> answered
            <?= $r['sort'] ? e(date('j M Y, g:ia', (int) $r['sort'])) : '' ?>. Any sign-in they gave went
            straight into the vault, not this sheet.</p>
          <?= cm_onboard_answers_html((int) $r['id']) ?>
          <div class="modal-foot">
            <?php if ($r['client_id'] !== null): ?>
              <a class="btn btn-quiet" href="<?= e(cm_url('admin/client.php?id=' . (int) $r['client_id'] . '&tab=comms')) ?>">Open their page</a>
            <?php endif; ?>
            <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
          </div>
        </div>
      </dialog>
    <?php } else { ?>
      <dialog class="modal modal-widest" id="<?= e($idAttr) ?>">
        <div class="modal-box">
          <div class="modal-head">
            <h2><?= e((string) $r['title']) ?></h2>
            <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
          </div>
          <p class="modal-sub">The email you sent, and the reply as you noted it.
            More detail is added from the client&rsquo;s page.</p>
          <div class="split-2">
            <div>
              <h3>What you sent</h3>
              <?php if ($mail !== null): ?>
                <p class="small muted mb-6"><?= e(date('j M Y, g:ia', strtotime((string) $mail['created_at']))) ?>
                  to <span class="mono"><?= e((string) $mail['sent_to']) ?></span></p>
                <?php if (trim((string) ($mail['body'] ?? '')) !== ''): ?>
                  <pre class="small prewrap mail-text"><?= e((string) $mail['body']) ?></pre>
                <?php else: ?>
                  <p class="muted small">No copy of the body was kept.</p>
                <?php endif; ?>
              <?php else: ?>
                <p class="muted small">The sent email is no longer on record.</p>
              <?php endif; ?>
            </div>
            <div>
              <h3>What came back</h3>
              <?php foreach (cm_replies_for_mail((int) $r['id']) as $n): ?>
                <p class="small muted mb-6 mt-10"><b>Noted <?= e(date('j M Y, g:ia', strtotime((string) $n['created_at']))) ?></b><?=
                  (string) $n['created_by'] !== '' ? ' by ' . e((string) $n['created_by']) : '' ?></p>
                <p class="small prewrap m-0"><?= e((string) $n['note']) ?></p>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="modal-foot">
            <?php if ($r['client_id'] !== null): ?>
              <a class="btn btn-quiet" href="<?= e(cm_url('admin/client.php?id=' . (int) $r['client_id'] . '&tab=comms')) ?>">Open their page</a>
            <?php endif; ?>
            <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
          </div>
        </div>
      </dialog>
    <?php }
    return (string) ob_get_clean();
}
