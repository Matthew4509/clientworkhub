<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0







declare(strict_types=1);











if (!defined('CM_ROOT')) { http_response_code(404); exit; }
require_once CM_LIB . '/format.php';

function cm_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function cm_csrf_field(): string
{
    return '<input type="hidden" name="cm_csrf" value="' . e(cm_csrf_token()) . '">';
}

function cm_csrf_valid(?string $token): bool
{
    $expected = $_SESSION['csrf'] ?? '';
    return $expected !== '' && is_string($token) && hash_equals($expected, $token);
}






function cm_once_field(): string
{
    $t = bin2hex(random_bytes(16));
    $list = is_array($_SESSION['cm_once'] ?? null) ? $_SESSION['cm_once'] : [];
    $list[$t] = time();
    if (count($list) > 40) $list = array_slice($list, -40, null, true);
    $_SESSION['cm_once'] = $list;
    return '<input type="hidden" name="cm_once" value="' . e($t) . '">';
}


function cm_once_spend(): bool
{
    $t = (string) ($_POST['cm_once'] ?? '');
    $list = is_array($_SESSION['cm_once'] ?? null) ? $_SESSION['cm_once'] : [];
    if ($t === '' || !isset($list[$t])) return false;
    unset($list[$t]);
    $_SESSION['cm_once'] = $list;
    return true;
}






function cm_require_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    if (cm_post_too_large()) {
        http_response_code(413);
        header('Content-Type: text/plain; charset=utf-8');
        exit('That file is larger than this server accepts in one upload ('
           . cm_bytes_label(cm_upload_cap()) . '), so it never arrived and nothing was changed. '
           . "Choose a smaller file, or ask your host to raise post_max_size and upload_max_filesize.\n");
    }
    $token = $_POST['cm_csrf'] ?? ($_SERVER['HTTP_X_CM_CSRF'] ?? null);
    if (!cm_csrf_valid(is_string($token) ? $token : null)) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        exit("That form expired or came from the wrong place. Go back, reload the page and try again.\n");
    }
    cm_demo_refuse_write();
}























function cm_demo_refuse_write(): void
{
    require_once CM_LIB . '/demo.php';
    if (!cm_demo_on()) return;
    if ((string) ($_POST['action'] ?? '') === 'demo_off') return;
    if (defined('CM_DEMO_SIGNIN_PAGE')) return;

    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Demo mode is on, so nothing can be changed, added or sent — it is here to be "
       . "looked around.\n\nTurn it off from the switch in the header and everything invented "
       . "for it is removed.\n");
}


function cm_require_csrf_json(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $token = $_SERVER['HTTP_X_CM_CSRF'] ?? ($_POST['cm_csrf'] ?? null);
    if (!cm_csrf_valid(is_string($token) ? $token : null)) {
        cm_json(['error' => 'csrf', 'message' => 'Session expired — reload the page.'], 400);
    }
}
