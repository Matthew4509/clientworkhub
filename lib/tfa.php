<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0































declare(strict_types=1);











if (!defined('CM_ROOT')) { http_response_code(404); exit; }

require_once CM_LIB . '/qr.php';

const CM_TFA_DIGITS   = 6;
const CM_TFA_PERIOD   = 30;
const CM_TFA_WINDOW   = 1;
const CM_TFA_EMAIL_TTL = 10;
const CM_TFA_BACKUP_COUNT = 10;

function cm_tfa_methods(): array
{
    return [
        'totp'  => [
            'label' => 'An authenticator app',
            'blurb' => 'Microsoft Authenticator, Google Authenticator, Authy, 1Password — any of them. '
                     . 'Codes are generated on your phone and work with no signal and no internet.',
        ],
        'email' => [
            'label' => 'A code sent to my email',
            'blurb' => 'Simplest to set up, and weaker: anyone who can read your email can sign in as you. '
                     . 'Better than nothing, not as good as an app.',
        ],
    ];
}


function cm_tfa_method(?array $user): string
{
    if (!$user) return '';
    if (!array_key_exists('tfa_method', $user)) {
        if (empty($user['id'])) return '';
        try {
            $row = cm_row('SELECT tfa_method FROM account WHERE id = ?', [(int) $user['id']]);
        } catch (Throwable $ex) {
            return '';
        }
        $user['tfa_method'] = $row['tfa_method'] ?? '';
    }
    $m = (string) ($user['tfa_method'] ?? '');
    return isset(cm_tfa_methods()[$m]) ? $m : '';
}

function cm_tfa_on(?array $user): bool
{
    return cm_tfa_method($user) !== '';
}



const CM_B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function cm_base32_encode(string $raw): string
{
    $bits = '';
    for ($i = 0, $n = strlen($raw); $i < $n; $i++) {
        $bits .= str_pad(decbin(ord($raw[$i])), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= CM_B32[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
    }
    return $out;
}

function cm_base32_decode(string $b32): string
{
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $b32) ?? '');
    $bits = '';
    for ($i = 0, $n = strlen($b32); $i < $n; $i++) {
        $v = strpos(CM_B32, $b32[$i]);
        if ($v === false) continue;
        $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) $out .= chr(bindec($chunk));
    }
    return $out;
}



function cm_totp_secret_new(): string
{
    return cm_base32_encode(random_bytes(20));
}


function cm_totp_code(string $secretB32, int $step): string
{
    $key  = cm_base32_decode($secretB32);
    $time = pack('N*', 0) . pack('N*', $step);
    $hash = hash_hmac('sha1', $time, $key, true);
    $off  = ord($hash[19]) & 0x0f;
    $bin  = ((ord($hash[$off]) & 0x7f) << 24)
          | ((ord($hash[$off + 1]) & 0xff) << 16)
          | ((ord($hash[$off + 2]) & 0xff) << 8)
          | (ord($hash[$off + 3]) & 0xff);
    return str_pad((string) ($bin % (10 ** CM_TFA_DIGITS)), CM_TFA_DIGITS, '0', STR_PAD_LEFT);
}









function cm_totp_check(string $secretB32, string $code, int $notBeforeStep = 0): ?int
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== CM_TFA_DIGITS) return null;

    $now = intdiv(time(), CM_TFA_PERIOD);
    for ($d = -CM_TFA_WINDOW; $d <= CM_TFA_WINDOW; $d++) {
        $step = $now + $d;
        if ($step <= $notBeforeStep) continue;
        if (hash_equals(cm_totp_code($secretB32, $step), $code)) return $step;
    }
    return null;
}















function cm_totp_uri(string $email, string $secretB32): string
{
    $issuer = 'Client Work Hub';
    $label  = rawurlencode($issuer) . ':' . rawurlencode($email);
    return 'otpauth://totp/' . $label . '?' . http_build_query([
        'secret' => $secretB32,
        'issuer' => $issuer,
    ], '', '&', PHP_QUERY_RFC3986);
}


function cm_totp_pretty(string $secretB32): string
{
    return trim(chunk_split($secretB32, 4, ' '));
}



function cm_tfa_backup_new(): array
{
    $codes = [];
    for ($i = 0; $i < CM_TFA_BACKUP_COUNT; $i++) {


        $codes[] = strtoupper(bin2hex(random_bytes(5)));
    }
    return $codes;
}

function cm_tfa_backup_store(int $accountId, array $codes): void
{
    $hashes = array_map(static fn(string $c) => password_hash($c, PASSWORD_DEFAULT), $codes);
    cm_q('UPDATE account SET tfa_backup = ? WHERE id = ?', [implode(',', $hashes), $accountId]);
}


function cm_tfa_backup_use(array $user, string $code): bool
{
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    if ($code === '') return false;

    $hashes = array_filter(explode(',', (string) ($user['tfa_backup'] ?? '')));
    foreach ($hashes as $i => $hash) {
        if (password_verify($code, $hash)) {
            unset($hashes[$i]);
            cm_q('UPDATE account SET tfa_backup = ? WHERE id = ?',
                 [implode(',', $hashes), (int) $user['id']]);
            cm_audit('tfa.backup_used', 'account', (int) $user['id'],
                     'a backup code was used; ' . count($hashes) . ' left');
            return true;
        }
    }
    return false;
}

function cm_tfa_backup_left(?array $user): int
{
    return count(array_filter(explode(',', (string) ($user['tfa_backup'] ?? ''))));
}




function cm_tfa_email_send(array $user): bool
{
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    cm_q('UPDATE account SET tfa_code_hash = ?, tfa_code_expires = ? WHERE id = ?', [
        password_hash($code, PASSWORD_DEFAULT),
        date('Y-m-d H:i:s', time() + CM_TFA_EMAIL_TTL * 60),
        (int) $user['id'],
    ]);

    $name = cm_first_name($user);
    return cm_mail((string) $user['email'], 'Your Client Work Hub sign-in code',
        "Hello {$name},\n\n"
      . "Your sign-in code is:  {$code}\n\n"
      . 'It expires in ' . CM_TFA_EMAIL_TTL . " minutes and can be used once.\n\n"
      . "If you did not just try to sign in, someone has your password. Change it\n"
      . "as soon as you can — this code alone does not let them in.\n",
        ['kind' => 'tfa_code']);
}

function cm_tfa_email_check(array $user, string $code): bool
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    $hash = (string) ($user['tfa_code_hash'] ?? '');
    $exp  = (string) ($user['tfa_code_expires'] ?? '');

    if ($code === '' || $hash === '' || $exp === '') return false;
    if (strtotime($exp) < time()) return false;
    if (!password_verify($code, $hash)) return false;


    cm_q("UPDATE account SET tfa_code_hash = '', tfa_code_expires = NULL WHERE id = ?",
         [(int) $user['id']]);
    return true;
}







function cm_tfa_verify(array $user, string $input): bool
{
    $method = cm_tfa_method($user);
    if ($method === '') return true;

    if ($method === 'totp') {
        $secret = cm_decrypt((string) ($user['tfa_secret'] ?? ''));
        if ($secret !== '') {
            $step = cm_totp_check($secret, $input, (int) ($user['tfa_last_step'] ?? 0));
            if ($step !== null) {
                cm_q('UPDATE account SET tfa_last_step = ? WHERE id = ?', [$step, (int) $user['id']]);
                return true;
            }
        }
    } elseif ($method === 'email') {
        if (cm_tfa_email_check($user, $input)) return true;
    }



    return cm_tfa_backup_use($user, $input);
}


function cm_tfa_disable(int $accountId, string $by = ''): void
{
    cm_q("UPDATE account SET tfa_method = '', tfa_secret = '', tfa_enrolled_at = NULL,
                             tfa_last_step = 0, tfa_backup = NULL,
                             tfa_code_hash = '', tfa_code_expires = NULL
          WHERE id = ?", [$accountId]);
    cm_audit('tfa.disabled', 'account', $accountId, 'two-factor turned off' . ($by !== '' ? ' by ' . $by : ''));
}




function cm_tfa_pending(): ?array
{
    $id = $_SESSION['cm_tfa_pending'] ?? null;
    if (!$id) return null;




    if (time() - (int) ($_SESSION['cm_tfa_since'] ?? 0) > 900) {
        unset($_SESSION['cm_tfa_pending'], $_SESSION['cm_tfa_since']);
        return null;
    }
    return cm_row('SELECT * FROM account WHERE id = ?', [(int) $id]);
}


function cm_tfa_complete(int $accountId): void
{
    unset($_SESSION['cm_tfa_pending'], $_SESSION['cm_tfa_since']);
    session_regenerate_id(true);
    $_SESSION['account_id'] = $accountId;
    $_SESSION['cm_seen']    = time();
    $row = cm_row('SELECT * FROM account WHERE id = ?', [$accountId]);
    if ($row) $_SESSION['cm_cred'] = cm_credential_mark($row);
}
