<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0
































declare(strict_types=1);











if (!defined('CM_ROOT')) { http_response_code(404); exit; }

const CM_REMEMBER_COOKIE = 'cm_remember';


const CM_REMEMBER_DAYS = 30;


function cm_remember_ready(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        cm_val('SELECT 1 FROM remember_token LIMIT 1');
        return $ok = true;
    } catch (Throwable $ex) {
        return $ok = false;
    }
}


function cm_remember_issue(int $accountId): void
{
    if (!cm_remember_ready() || $accountId <= 0) return;

    $selector  = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $expires   = date('Y-m-d H:i:s', time() + CM_REMEMBER_DAYS * 86400);

    cm_q(
        'INSERT INTO remember_token (account_id, selector, validator, expires_at, created_at, user_agent)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$accountId, $selector, hash('sha256', $validator), $expires, cm_now(),
         mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 190)]
    );
    cm_remember_cookie($selector . ':' . $validator, time() + CM_REMEMBER_DAYS * 86400);
}









function cm_remember_cookie(string $value, int $expires): void
{
    if (headers_sent()) return;
    setcookie(CM_REMEMBER_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => cm_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}






function cm_remember_login(): bool
{
    if (!cm_remember_ready()) return false;
    $raw = (string) ($_COOKIE[CM_REMEMBER_COOKIE] ?? '');
    if ($raw === '' || substr_count($raw, ':') !== 1) return false;

    [$selector, $validator] = explode(':', $raw, 2);


    if (!preg_match('/^[a-f0-9]{32}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
        cm_remember_forget();
        return false;
    }

    $row = cm_row('SELECT * FROM remember_token WHERE selector = ?', [$selector]);
    if (!$row) { cm_remember_forget(); return false; }


    if (strtotime((string) $row['expires_at']) < time()) {
        cm_q('DELETE FROM remember_token WHERE id = ?', [(int) $row['id']]);
        cm_remember_forget();
        return false;
    }



    if (!hash_equals((string) $row['validator'], hash('sha256', $validator))) {




        cm_q('DELETE FROM remember_token WHERE account_id = ?', [(int) $row['account_id']]);
        cm_remember_forget();
        return false;
    }



    $newSelector  = bin2hex(random_bytes(16));
    $newValidator = bin2hex(random_bytes(32));
    cm_q(
        'UPDATE remember_token SET selector = ?, validator = ?, used_at = ? WHERE id = ?',
        [$newSelector, hash('sha256', $newValidator), cm_now(), (int) $row['id']]
    );
    cm_remember_cookie($newSelector . ':' . $newValidator,
                       strtotime((string) $row['expires_at']));





    cm_login((int) $row['account_id'], true);
    return true;
}


function cm_remember_forget(): void
{
    if (!isset($_COOKIE[CM_REMEMBER_COOKIE])) return;
    $raw = (string) $_COOKIE[CM_REMEMBER_COOKIE];
    if (cm_remember_ready() && substr_count($raw, ':') === 1) {
        [$sel] = explode(':', $raw, 2);
        if (preg_match('/^[a-f0-9]{32}$/', $sel)) {
            cm_q('DELETE FROM remember_token WHERE selector = ?', [$sel]);
        }
    }
    unset($_COOKIE[CM_REMEMBER_COOKIE]);
    cm_remember_cookie('', time() - 42000);
}








function cm_remember_revoke_all(int $accountId): void
{
    if (!cm_remember_ready() || $accountId <= 0) return;
    cm_q('DELETE FROM remember_token WHERE account_id = ?', [$accountId]);
    cm_remember_cookie('', time() - 42000);
}


function cm_remember_prune(): void
{
    if (!cm_remember_ready()) return;
    cm_q('DELETE FROM remember_token WHERE expires_at < ?', [cm_now()]);
}
