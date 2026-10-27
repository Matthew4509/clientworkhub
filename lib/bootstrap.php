<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0










declare(strict_types=1);





if (PHP_SAPI !== 'cli'
    && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    exit;
}

define('CM_ROOT', dirname(__DIR__));
define('CM_LIB', __DIR__);






























































const CM_VERSION = '0.3.21';








const CM_EDITION = 'B';











$cmDataDir = (string) getenv('CWH_DATA_DIR');
define('CM_DATA', $cmDataDir !== '' && is_dir($cmDataDir) ? rtrim(str_replace('\\', '/', $cmDataDir), '/') : CM_ROOT);
define('CM_STORAGE', CM_DATA . '/storage');
$cmConfigFile = CM_DATA . '/config.php';
if (!is_file($cmConfigFile)) {
    $cmInstaller = is_file(CM_ROOT . '/install.php');
    if ($cmInstaller && PHP_SAPI !== 'cli') {





        $cmDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
        if (preg_match('#/(account|admin)$#', $cmDir)) {
            $cmDir = (string) substr($cmDir, 0, (int) strrpos($cmDir, '/'));
        }
        header('Location: ' . $cmDir . '/install.php', true, 302);
        exit;
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Client Work Hub is not set up yet.\n\n"
       . ($cmInstaller
            ? "Open /install.php in your browser — it asks for your database\n"
            . "details and does the rest.\n"
            : "config.php is missing. Copy config.sample.php to config.php and\n"
            . "fill in the database details, then reload this page.\n"));
}
$GLOBALS['CM_CONFIG'] = require $cmConfigFile;




function cm_config(string $path, $default = null)
{
    $node = $GLOBALS['CM_CONFIG'];
    foreach (explode('.', $path) as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) {
            return $default;
        }
        $node = $node[$key];
    }
    return $node;
}











function cm_home(): bool
{
    return cm_config('edition') === 'home';
}



function cm_home_hide(): void
{
    if (!cm_home()) return;
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    if (function_exists('cm_send_security_headers')) cm_send_security_headers();
    exit("Not found\n");
}

function cm_is_dev(): bool
{
    return cm_config('env') === 'dev';
}






$cmTz = (string) cm_config('timezone', 'Australia/Melbourne');
if (!in_array($cmTz, timezone_identifiers_list(), true)) {
    $cmTz = 'Australia/Melbourne';
}
date_default_timezone_set($cmTz);






error_reporting(E_ALL);
$cmDebug = cm_is_dev() || getenv('CM_DEBUG') === '1' || PHP_SAPI === 'cli';
ini_set('display_errors', $cmDebug ? '1' : '0');
ini_set('log_errors', '1');






if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s, ?string $enc = null): int
    {
        return count(preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $length = null, ?string $enc = null): string
    {
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode('', array_slice($chars, $start, $length));
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $s, ?string $enc = null): string { return strtolower($s); }
}



function cm_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;






    if ((bool) cm_config('trust_proxy', false)
        && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') return true;
    return false;
}

function cm_host(): string
{
    $h = $_SERVER['HTTP_HOST'] ?? '';
    return strtolower(preg_replace('/:\d+$/', '', $h));
}

function cm_is_local_host(): bool
{








    $peer = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $peerLocal = $peer === '::1' || $peer === '127.0.0.1' || str_starts_with($peer, '127.');
    return $peerLocal && in_array(cm_host(), ['localhost', '127.0.0.1', '::1'], true);
}


function cm_base_url(): string
{
    $configured = rtrim((string) cm_config('base_url', ''), '/');

    if (cm_is_local_host()) {
        $scheme = cm_is_https() ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    return $configured;
}

function cm_url(string $path = ''): string
{
    return cm_base_url() . '/' . ltrim($path, '/');
}

































function cm_portal_url(string $path = ''): string
{
    return cm_url('portal/' . ltrim($path, '/'));
}


function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}





function cm_now(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

function cm_redirect(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : cm_url($path)));
    exit;
}


function cm_json($data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}





if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => cm_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('cm_session');




    ini_set('session.use_strict_mode', '1');





    ini_set('session.gc_maxlifetime', '43200');
    session_start();
}











































function cm_asset_stamp(): string
{
    static $stamp = null;
    if ($stamp !== null) return $stamp;

    $newest = 0;
    foreach (['/css', '/js'] as $dir) {
        foreach (glob(CM_ROOT . $dir . '/*.{css,js}', GLOB_BRACE) ?: [] as $f) {
            $m = @filemtime($f);
            if ($m !== false && $m > $newest) $newest = $m;
        }
    }
    $v = defined('CM_VERSION') ? (string) CM_VERSION : '';
    return $stamp = $v . ($newest > 0 ? '.' . $newest : '');
}







require_once CM_LIB . '/headers.php';




if (cm_home() && PHP_SAPI !== 'cli') {
    $cmPeer = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!($cmPeer === '::1' || str_starts_with($cmPeer, '127.'))) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        cm_send_security_headers();
        exit("Client Work Hub (home edition) only answers on this PC.\n");
    }



    if (!in_array(cm_host(), ['127.0.0.1', 'localhost', '[::1]'], true)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        cm_send_security_headers();
        exit("Client Work Hub (home edition) only answers on this PC.\n");
    }
}

require CM_LIB . '/db.php';
require CM_LIB . '/csrf.php';
require CM_LIB . '/settings.php';
if (PHP_SAPI !== 'cli') cm_storage_seal();



require CM_LIB . '/tax.php';
require CM_LIB . '/mail.php';



require CM_LIB . '/password.php';
require CM_LIB . '/auth.php';
require CM_LIB . '/remember.php';
