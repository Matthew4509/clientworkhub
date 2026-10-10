<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0












































declare(strict_types=1);











if (!defined('CM_ROOT')) { http_response_code(404); exit; }


function cm_recovery_configured(): bool
{
    return strlen(trim((string) cm_config('update_key', ''))) >= 32;
}







function cm_recovery_active(): bool
{
    static $known = null;
    if ($known !== null) return $known;

    if (!cm_recovery_configured()) return $known = false;







    $u = cm_current_user();
    if ($u && cm_is_admin($u)) return $known = false;

    $expected = trim((string) cm_config('update_key', ''));
    $given    = (string) ($_GET['key'] ?? ($_POST['cm_key'] ?? ''));

    return $known = ($given !== '' && hash_equals($expected, $given));
}





function cm_recovery_field(): string
{
    if (!cm_recovery_active()) return '';
    return '<input type="hidden" name="cm_key" value="'
         . e(trim((string) cm_config('update_key', ''))) . '">';
}


function cm_recovery_url(string $path): string
{
    $url = cm_url($path);
    if (!cm_recovery_active()) return $url;
    return $url . (strpos($url, '?') === false ? '?' : '&')
         . 'key=' . rawurlencode(trim((string) cm_config('update_key', '')));
}










function cm_require_admin_or_recovery(): array
{
    $u = cm_current_user();
    if ($u && cm_is_admin($u)) return $u;

    if (cm_recovery_active()) {



        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        cm_audit('recovery.used', '', null,
                 'update_key used from ' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ' for ' . $path);
        return ['id' => 0, 'email' => 'recovery-key', 'recovery' => true];
    }




    if (cm_tfa_waiting()) cm_redirect('account/two-factor.php');



    cm_admin_404();
}


function cm_in_recovery(array $actor): bool
{
    return !empty($actor['recovery']);
}










function cm_redirect_recovery(string $path): never
{
    if (!cm_recovery_active()) cm_redirect($path);

    $url = cm_recovery_url($path);
    header('Location: ' . $url, true, 302);
    exit;
}
