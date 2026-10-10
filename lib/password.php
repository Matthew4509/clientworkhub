<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0






















declare(strict_types=1);











if (!defined('CM_ROOT')) { http_response_code(404); exit; }


const CM_PASSWORD_MIN = 10;





function cm_password_problem(string $password, string $email = ''): string
{
    $len = mb_strlen($password);
    if ($len < CM_PASSWORD_MIN) {
        return 'Use at least ' . CM_PASSWORD_MIN . ' characters — length matters far more than symbols.';
    }
    if ($len > 200) {


        return 'That password is too long (200 characters maximum).';
    }
    if (trim($password) === '') {
        return 'A password of only spaces is not a password.';
    }
    $lower = mb_strtolower($password);
    if ($email !== '' && $lower === mb_strtolower(explode('@', $email)[0])) {
        return 'That is just your email address — please choose something else.';
    }




    foreach (['password', '12345678', 'clientmanager', 'clientworkhub',
              'qwertyuiop', 'letmein123'] as $bad) {
        if ($lower === $bad || $lower === $bad . '1') {
            return 'That password is one of the first anyone guesses.';
        }
    }
    return '';
}

function cm_password_hash(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}






function cm_password_verify_account(array $account, string $password): bool
{
    $hash = (string) ($account['password_hash'] ?? '');





    if ($hash === '') {
        password_verify($password, '$2y$10$usesomesillystringfaketohashxxxxxxxxxxxxxxxxxxxxxxxxxxx');
        return false;
    }

    if (!password_verify($password, $hash)) return false;



    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        cm_q('UPDATE account SET password_hash = ? WHERE id = ?',
             [cm_password_hash($password), (int) $account['id']]);
    }
    return true;
}






function cm_rate_record(string $bucket, string $subject): void
{
    try {
        cm_insert('INSERT INTO rate_hit (bucket, subject, created_at) VALUES (?, ?, ?)',
                  [mb_substr($bucket, 0, 40), mb_substr(strtolower($subject), 0, 190), cm_now()]);
    } catch (Throwable $ex) {
        error_log('rate_hit write failed: ' . $ex->getMessage());
    }





    if (random_int(1, 50) === 1) cm_rate_prune();
}









function cm_rate_prune(): void
{
    $dayAgo = (new DateTimeImmutable('now'))->modify('-1 day')->format('Y-m-d H:i:s');
    try {
        cm_q('DELETE FROM rate_hit WHERE created_at < ?', [$dayAgo]);
        cm_q('DELETE FROM auth_token WHERE (used_at IS NOT NULL OR expires_at < ?) AND created_at < ?',
             [$dayAgo, $dayAgo]);
    } catch (Throwable $ex) {
        error_log('prune failed: ' . $ex->getMessage());
    }
}


function cm_rate_count(string $bucket, string $subject, int $minutes): int
{




    $since = (new DateTimeImmutable('now'))
        ->modify("-{$minutes} minutes")->format('Y-m-d H:i:s');
    return (int) cm_val(
        'SELECT COUNT(*) FROM rate_hit WHERE bucket = ? AND subject = ? AND created_at >= ?',
        [mb_substr($bucket, 0, 40), mb_substr(strtolower($subject), 0, 190), $since]
    );
}


function cm_rate_exceeded(string $bucket, string $subject, int $limit, int $minutes): bool
{
    return cm_rate_count($bucket, $subject, $minutes) >= $limit;
}






function cm_rate_within(string $bucket, string $subject, int $limit, int $minutes): bool
{
    cm_rate_record($bucket, $subject);
    return cm_rate_count($bucket, $subject, $minutes) <= $limit;
}


function cm_rate_clear(string $bucket, string $subject): void
{
    cm_q('DELETE FROM rate_hit WHERE bucket = ? AND subject = ?',
         [mb_substr($bucket, 0, 40), mb_substr(strtolower($subject), 0, 190)]);
}


function cm_client_ip(): string
{



    $trustProxy = (bool) cm_config('trust_proxy', false);
    if ($trustProxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']));
        $last = end($parts);
        if (filter_var($last, FILTER_VALIDATE_IP)) return $last;
    }
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}






function cm_client_ip_bucket(): string
{
    $ip = cm_client_ip();
    if (str_contains($ip, ':')) {
        $bin = @inet_pton($ip);
        if ($bin !== false && strlen($bin) === 16) {
            return bin2hex(substr($bin, 0, 8)) . '::/64';
        }
    }
    return $ip;
}









function cm_token_issue(int $accountId, string $kind, int $ttlMinutes): string
{
    cm_q('UPDATE auth_token SET used_at = ? WHERE account_id = ? AND kind = ? AND used_at IS NULL',
         [cm_now(), $accountId, $kind]);

    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');





    $expires = (new DateTimeImmutable('now'))
        ->modify("+{$ttlMinutes} minutes")->format('Y-m-d H:i:s');

    cm_insert(
        'INSERT INTO auth_token (account_id, kind, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
        [$accountId, $kind, hash('sha256', $token), $expires, cm_now()]
    );
    return $token;
}





function cm_token_check(string $token, string $kind): ?array
{
    if ($token === '') return null;

    $row = cm_row(
        'SELECT * FROM auth_token WHERE token_hash = ? AND kind = ?',
        [hash('sha256', $token), $kind]
    );
    if (!$row) return null;
    if ($row['used_at'] !== null) return null;
    if ((string) $row['expires_at'] < cm_now()) return null;

    $account = cm_row('SELECT * FROM account WHERE id = ?', [(int) $row['account_id']]);
    if (!$account) return null;

    $account['_token_id'] = (int) $row['id'];
    return $account;
}


function cm_token_spend(int $tokenId): bool
{



    $st = cm_q('UPDATE auth_token SET used_at = ? WHERE id = ? AND used_at IS NULL', [cm_now(), $tokenId]);
    return $st->rowCount() > 0;
}










const CM_HONEYPOT = 'cm_hp_ref';

function cm_honeypot_field(): void
{
    echo '<div class="hp-field" aria-hidden="true">'
       . '<label for="' . CM_HONEYPOT . '">Leave this empty</label>'
       . '<input type="text" id="' . CM_HONEYPOT . '" name="' . CM_HONEYPOT . '"'
       . ' tabindex="-1" autocomplete="off" value="">'
       . '</div>';
}

function cm_honeypot_tripped(): bool
{
    return trim((string) ($_POST[CM_HONEYPOT] ?? '')) !== '';
}
