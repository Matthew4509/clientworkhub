<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0














declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }

function cm_email_valid(string $email): bool
{
    return $email !== '' && mb_strlen($email) <= 190
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}








function cm_login(int $accountId, bool $trustedDevice = false): void
{
    require_once CM_LIB . '/tfa.php';
    $account = cm_row('SELECT * FROM account WHERE id = ?', [$accountId]);

    if ($account && !$trustedDevice && cm_tfa_on($account)) {


        session_regenerate_id(true);
        unset($_SESSION['account_id']);
        $_SESSION['cm_tfa_pending'] = $accountId;
        $_SESSION['cm_tfa_since']   = time();
        return;
    }

    session_regenerate_id(true);
    unset($_SESSION['cm_tfa_pending'], $_SESSION['cm_tfa_since']);
    $_SESSION['account_id'] = $accountId;
    $_SESSION['cm_seen']    = time();
    if ($account) $_SESSION['cm_cred'] = cm_credential_mark($account);
}











function cm_credential_mark(array $account): string
{
    return substr(hash('sha256', (string) ($account['password_hash'] ?? '') . '|'
        . (string) ($account['tfa_method'] ?? '') . '|' . (string) ($account['tfa_enrolled_at'] ?? '') . '|'
        . (string) ($account['tfa_secret'] ?? '')), 0, 32);
}


function cm_credential_mark_refresh(int $accountId): void
{
    $row = cm_row('SELECT * FROM account WHERE id = ?', [$accountId]);
    if ($row && (int) ($_SESSION['account_id'] ?? 0) === $accountId) {
        $_SESSION['cm_cred'] = cm_credential_mark($row);
    }
}

function cm_logout(): void
{
    require_once CM_LIB . '/remember.php';
    cm_remember_forget();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
                  $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}






const CM_IDLE_SECONDS = 12 * 3600;





function cm_current_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;



    if (!empty($_SESSION['account_id'])) {
        $seen = (int) ($_SESSION['cm_seen'] ?? 0);
        if ($seen > 0 && time() - $seen > CM_IDLE_SECONDS) {
            unset($_SESSION['account_id'], $_SESSION['cm_seen']);
        } else {
            $_SESSION['cm_seen'] = time();
        }
    }

    $id = $_SESSION['account_id'] ?? null;
    if (!$id) {


        require_once CM_LIB . '/remember.php';
        if (cm_remember_login()) {
            $id = $_SESSION['account_id'] ?? null;
        }
    }
    if (!$id) return $user = null;

    try {
        $row = cm_row('SELECT * FROM account WHERE id = ?', [(int) $id]);
    } catch (Throwable $ex) {
        return $user = null;
    }
    if (!$row || !empty($row['deleted_at'])) {
        unset($_SESSION['account_id']);
        return $user = null;
    }
    $mark = cm_credential_mark($row);
    if (!isset($_SESSION['cm_cred'])) {
        $_SESSION['cm_cred'] = $mark;
    } elseif (!hash_equals((string) $_SESSION['cm_cred'], $mark)) {


        unset($_SESSION['account_id'], $_SESSION['cm_seen'], $_SESSION['cm_cred']);
        return $user = null;
    }
    return $user = $row;
}


function cm_tfa_waiting(): bool
{
    return !empty($_SESSION['cm_tfa_pending']);
}








function cm_is_admin(?array $user = null): bool
{
    $user = $user ?? cm_current_user();
    if (!$user) return false;

    $email = strtolower((string) ($user['email'] ?? ''));
    foreach ((array) cm_config('admin_emails', []) as $a) {
        if ($email !== '' && strtolower(trim((string) $a)) === $email) return true;
    }
    return (string) ($user['role'] ?? '') === 'admin';
}


function cm_is_staff(?array $user = null): bool
{
    return cm_is_admin($user);
}






function cm_safe_return(?string $raw): string
{
    $raw = (string) $raw;
    if ($raw === '' || $raw[0] !== '/') return '';
    if (isset($raw[1]) && ($raw[1] === '/' || $raw[1] === '\\')) return '';
    if (str_contains($raw, "\r") || str_contains($raw, "\n")) return '';



    if (str_contains($raw, '\\')) return '';
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', ltrim($raw, '/'))) return '';
    return $raw;
}


function cm_require_login(string $returnTo = ''): array
{
    $u = cm_current_user();
    if ($u) return $u;
    if (cm_tfa_waiting()) cm_redirect('account/two-factor.php');
    $q = $returnTo !== '' ? '?return=' . rawurlencode($returnTo) : '';
    cm_redirect('account/login.php' . $q);
}





function cm_require_admin(): array
{
    $u = cm_current_user();
    if ($u && cm_is_admin($u)) return $u;
    if (cm_tfa_waiting()) cm_redirect('account/two-factor.php');
    if (!$u) {
        $return = cm_safe_return('/' . ltrim((string) ($_SERVER['REQUEST_URI'] ?? ''), '/'));
        cm_redirect('account/login.php' . ($return !== '' ? '?return=' . rawurlencode($return) : ''));
    }
    cm_admin_404();
}


function cm_admin_404(): never
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Not found.\n");
}


function cm_account_upsert(string $email, string $name = ''): int
{
    $email = strtolower(trim($email));
    $row = cm_row('SELECT id FROM account WHERE email = ?', [$email]);
    if ($row) {
        if ($name !== '') {
            cm_q('UPDATE account SET name = ? WHERE id = ?', [$name, (int) $row['id']]);
        }
        return (int) $row['id'];
    }
    return cm_insert(
        'INSERT INTO account (email, name, password_hash, created_at) VALUES (?, ?, ?, ?)',
        [$email, $name, '', cm_now()]
    );
}







function cm_dev_login_allowed(): bool
{
    return (bool) cm_config('dev_login', false)
        && cm_is_local_host()
        && cm_is_dev();
}
