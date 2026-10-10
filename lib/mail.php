<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0







































declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }




const CM_DEFAULT_BUSINESS_NAME = 'ClientWorkHub';




require_once CM_LIB . '/unsubscribe.php';

function cm_mail_enabled(): bool
{
    $v = cm_setting('mail.enabled', '');
    return $v === '1' || $v === 1 || $v === true || $v === 'on';
}



























function cm_mail_can_deliver(): bool
{
    return cm_mail_enabled() && !cm_is_local_host();
}

function cm_mail_from(): string
{
    $v = trim((string) cm_setting('mail.from', ''));
    if ($v !== '') return $v;
    $host = (string) parse_url(cm_base_url(), PHP_URL_HOST);
    return 'no-reply@' . ($host !== '' ? $host : 'localhost');
}

function cm_mail_from_name(): string
{




    return trim((string) cm_setting('mail.from_name', CM_DEFAULT_BUSINESS_NAME)) ?: CM_DEFAULT_BUSINESS_NAME;
}









function cm_business_display_name(): string
{
    foreach (['business.legal_name', 'public.business_name', 'mail.from_name'] as $key) {
        try { $n = trim((string) (cm_setting($key, '') ?? '')); }
        catch (Throwable $ex) { $n = ''; }
        if ($n !== '') return $n;
    }
    return CM_DEFAULT_BUSINESS_NAME;
}

function cm_mail_reply_to(): string
{
    return trim((string) cm_setting('mail.reply_to', ''));
}


function cm_mail_header_safe(string $v): string
{
    return trim(str_replace(["\r", "\n", "\0"], '', $v));
}


function cm_mail_encode_name(string $name): string
{
    $name = cm_mail_header_safe($name);
    if (preg_match('/^[\x20-\x7e]*$/', $name) && !preg_match('/[",<>]/', $name)) {
        return $name;
    }
    return '=?UTF-8?B?' . base64_encode($name) . '?=';
}

function cm_mail_encode_subject(string $subject): string
{
    $subject = cm_mail_header_safe($subject);
    if (preg_match('/^[\x20-\x7e]*$/', $subject)) return $subject;
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}










function cm_mail(string $to, string $subject, string $body, array $opts = []): bool
{
    $kind     = (string) ($opts['kind'] ?? 'other');
    $clientId = (int) ($opts['client_id'] ?? 0);








    require_once CM_LIB . '/demo.php';
    if (cm_demo_on()) {
        cm_mail_record($to, $subject, $kind, 'refused: demo mode is on', $clientId, $body);
        return false;
    }
    $to       = cm_mail_header_safe($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        cm_mail_record($to, $subject, $kind, 'refused: bad address', $clientId, $body);
        return false;
    }




    if ($clientId > 0) {
        if (cm_client_is_unsubscribed($clientId)) {
            cm_mail_record($to, $subject, $kind, 'refused: client unsubscribed', $clientId, $body);
            return false;
        }

        $footer = cm_unsub_footer($clientId);
        if ($footer === '') {




            cm_mail_record($to, $subject, $kind,
                           'refused: no unsubscribe link could be built', $clientId, $body);
            return false;
        }
        $body = rtrim($body) . $footer;
    }

    $headers  = 'From: ' . cm_mail_encode_name(cm_mail_from_name()) . ' <' . cm_mail_header_safe(cm_mail_from()) . ">\r\n";
    $reply = cm_mail_reply_to();
    if ($reply !== '' && filter_var($reply, FILTER_VALIDATE_EMAIL)) {
        $headers .= 'Reply-To: ' . cm_mail_header_safe($reply) . "\r\n";
    }
    $headers .= "MIME-Version: 1.0\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: 8bit\r\n"
              . 'X-Mailer: ClientWorkHub/' . CM_VERSION;










    if (!cm_mail_can_deliver()) {
        cm_mail_spool($to, $subject, $headers, $body, $kind);
        cm_mail_record($to, $subject, $kind, 'spooled', $clientId, $body);
        return true;
    }

    $ok = @mail($to, cm_mail_encode_subject($subject), $body, $headers);
    cm_mail_record($to, $subject, $kind, $ok ? 'sent' : 'mail() failed', $clientId, $body);
    if (!$ok) error_log('cm_mail: mail() failed for kind=' . $kind);
    return $ok;
}


function cm_mail_spool(string $to, string $subject, string $headers, string $body, string $kind): void
{
    $dir = CM_ROOT . '/storage/mail';
    if (!is_dir($dir)) @mkdir($dir, 0770, true);
    $file = $dir . '/' . date('Ymd-His') . '-' . $kind . '-' . substr(md5($to . microtime()), 0, 6) . '.txt';
    @file_put_contents($file,
        "To: {$to}\nSubject: {$subject}\n{$headers}\n\n{$body}\n");
}











function cm_mail_record(string $to, string $subject, string $kind, string $result,
                        int $clientId = 0, ?string $body = null): void
{
    try {
        cm_insert('INSERT INTO mail_log (client_id, sent_to, subject, body, kind, result, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?)',
                  [$clientId > 0 ? $clientId : null,
                   mb_substr($to, 0, 190), mb_substr($subject, 0, 190),
                   $body === null ? null : $body,
                   mb_substr($kind, 0, 40), mb_substr($result, 0, 190), cm_now()]);
    } catch (Throwable $ex) {
        error_log('mail_log write failed: ' . $ex->getMessage());
    }
}



function cm_first_name(array $account): string
{
    $name = trim((string) ($account['name'] ?? ''));
    if ($name === '') return 'there';
    return explode(' ', $name)[0];
}











function cm_mail_onboarding_link(string $to, string $name, string $link, int $clientId): bool
{
    $first = trim(explode(' ', trim($name))[0] ?? '');
    return cm_mail($to, 'Setting up your work with us',
        ($first !== '' ? "Hello {$first},\n\n" : "Hello,\n\n")
      . "Thank you for booking. Before the work starts there are a few\n"
      . "questions to answer — what you would like, and the details we need\n"
      . "to do it.\n\n"
      . "  {$link}\n\n"
      . "Anything you would rather tell us another way, just reply to this\n"
      . "email instead.\n",
        ['kind' => 'onboarding_link', 'client_id' => $clientId]);
}

function cm_mail_password_reset(array $account, string $link): bool
{
    $name = cm_first_name($account);
    return cm_mail((string) $account['email'], 'Reset your Client Work Hub password',
        "Hello {$name},\n\n"
      . "Somebody asked to reset the password for this address. If it was you,\n"
      . "open this link — it works once and expires in an hour:\n\n"
      . "  {$link}\n\n"
      . "If it was not you, do nothing. Your password has not changed, and\n"
      . "nobody can change it without this link.\n",
        ['kind' => 'password_reset']);
}
