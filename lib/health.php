<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0















declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }




require_once CM_LIB . '/requirements.php';


function cm_installer_present(): bool
{
    return is_file(CM_ROOT . '/install.php');
}


























function cm_removable_after_install(): array
{
    return [
        'install.php' => 'The installer — shipped in the release, removed once the site runs.',
    ];
}

function cm_manifest_check(): array
{
    $file = CM_ROOT . '/MANIFEST.txt';
    if (!is_file($file)) {
        return ['state' => 'absent', 'missing' => [], 'changed' => [], 'total' => 0];
    }

    $missing = $changed = [];
    $total = 0;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (!preg_match('/^([0-9a-f]{64})\s+(.+)$/', trim($line), $m)) continue;
        $total++;
        [, $hash, $rel] = $m;


        $rel = str_replace('\\', '/', $rel);
        if (str_contains($rel, '..')) continue;
        $abs = CM_ROOT . '/' . $rel;
        if (!is_file($abs)) {



            if (isset(cm_removable_after_install()[$rel])) { continue; }
            $missing[] = $rel;
        } elseif (!hash_equals($hash, (string) hash_file('sha256', $abs))) {
            $changed[] = $rel;
        }
    }
    return ['state' => 'present', 'missing' => $missing, 'changed' => $changed, 'total' => $total];
}











function cm_cleanup_candidates(): array
{
    $manifest = [];
    $mf = CM_ROOT . '/MANIFEST.txt';
    if (is_file($mf)) {
        foreach (file($mf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match('/^[0-9a-f]{64}\s+(.+)$/', trim($line), $m)) {
                $manifest[str_replace('\\', '/', trim($m[1]))] = true;
            }
        }
    }

    $offer = [
        'install.php' => [
            'label'  => 'The installer',
            'why'    => 'Shipped with the release and meant to go once the site runs. Left '
                      . 'here, it is the one file that could hand this site to somebody else. '
                      . 'It does NOT delete itself — a file that removes itself is a file that '
                      . 'destroys work when somebody opens it to look.',
            'danger' => true,
        ],
    ];

    $out = [];
    foreach ($offer as $key => $meta) {
        $abs = CM_ROOT . '/' . $key;
        if (!is_file($abs)) continue;



        if (isset($manifest[$key]) && !isset(cm_removable_after_install()[$key])) continue;
        $writable = is_writable($abs) && is_writable(dirname($abs));
        $out[] = [
            'key'       => $key,
            'label'     => $meta['label'],
            'why'       => $meta['why'],
            'danger'    => $meta['danger'],
            'removable' => $writable,
            'blocked'   => $writable ? '' : 'The file is not writable by PHP — delete it in cPanel → File Manager.',
        ];
    }
    return $out;
}







function cm_cleanup_run(array $keys, string $actor): array
{
    $offered = [];
    foreach (cm_cleanup_candidates() as $c) {
        if ($c['removable']) $offered[$c['key']] = true;
    }

    $removed = [];
    $failed  = [];
    foreach ($keys as $key) {
        $key = (string) $key;
        if (!isset($offered[$key])) {
            $failed[$key] = 'not something this screen offers to remove';
            continue;
        }
        if (@unlink(CM_ROOT . '/' . $key)) {
            $removed[] = $key;
            cm_audit('file.cleanup', '', null, $key . ' removed by ' . $actor);
        } else {
            $failed[$key] = 'could not be deleted — remove it in cPanel → File Manager';
        }
    }
    return ['removed' => $removed, 'failed' => $failed];
}














function cm_storage_probe(): ?bool
{
    static $answer = false;
    if ($answer !== false) return $answer;



    if (!function_exists('curl_init') || PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') return $answer = null;
    $marker = 'cwh-storage-probe';
    $file = CM_ROOT . '/storage/probe.txt';
    if (!is_file($file)) @file_put_contents($file, $marker);
    if (!is_file($file)) return $answer = null;
    $ch = curl_init(cm_url('storage/probe.txt'));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
                            CURLOPT_TIMEOUT => 3, CURLOPT_CONNECTTIMEOUT => 2,
                            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($body === false || $status === 0) return $answer = null;
    return $answer = !($status === 200 && str_contains((string) $body, $marker));
}






function cm_health_checks(bool $probe = false): array
{
    $checks = [];


    $phpOk = cm_req_php_ok();
    $checks[] = [
        'label' => 'PHP version',
        'state' => $phpOk ? 'ok' : 'fail',
        'detail' => 'Running PHP ' . PHP_VERSION
                  . ($phpOk ? '.' : ' — this application needs ' . CM_REQ_MIN_PHP . ' or newer.'),
        'fix'   => 'cPanel → MultiPHP Manager, set this domain to a newer PHP.',
    ];


    $missing = cm_requirements_missing(true);
    $checks[] = [
        'label' => 'PHP extensions',
        'state' => $missing ? 'fail' : 'ok',
        'detail' => $missing
            ? 'Missing: ' . implode(', ', $missing) . '. Nothing works without them.'
            : 'Every extension this application cannot run without is loaded.',
        'fix'   => 'cPanel → Select PHP Version → Extensions, tick the missing ones. '
                 . 'The box below is a message you can send your hosting company.',
    ];








    $absent   = [];
    $optional = [];
    foreach (cm_requirements_state() as $row) {
        if ($row['required']) continue;
        $optional[] = $row['ext'];
        if (!$row['have']) $absent[$row['ext']] = $row['lost'];
    }
    $checks[] = [
        'label' => 'Optional extensions',
        'state' => $absent ? 'warn' : 'ok',



        'detail' => $absent
            ? 'Not available here: ' . implode(', ', array_keys($absent)) . '. '
              . implode(' ', array_map(
                    static fn(string $ext, string $lost): string => $ext . ' — ' . $lost,
                    array_keys($absent), array_values($absent)))


            : implode(', ', $optional) . ' are all available, so every feature works.',
        'fix'   => $absent
            ? 'The application runs without these — this is a note, not a fault. '
              . 'cPanel → Select PHP Version → Extensions to turn them on.'
            : '',
    ];


    $cryptOk = cm_crypt_available();
    $keyOk = false;
    if ($cryptOk) {
        try {
            $keyOk = cm_decrypt(cm_encrypt('probe')) === 'probe';
        } catch (Throwable $ex) {
            $keyOk = false;
        }
    }






















    $keySrc  = cm_app_key_source();
    $keyWeak = $keySrc['where'] === 'config' && strlen(trim($keySrc['value'])) < 32;
    $checks[] = [
        'label' => 'Credential encryption',
        'state' => !($cryptOk && $keyOk) ? 'fail' : ($keyWeak ? 'warn' : 'ok'),
        'detail' => $cryptOk && $keyOk
            ? ($keyWeak
                ? 'AES-256-GCM works, but secret_key in config.php is only '
                  . strlen(trim($keySrc['value'])) . ' characters. Everything stored as a secret — '
                  . 'client passwords, 2FA secrets, your payment keys — is only as hard to '
                  . 'guess as that line. The installer writes 64.'
                : 'AES-256-GCM works, and a value encrypted just now decrypted correctly.')
            : ($cryptOk
                ? 'The encryption key could not be used — check secret_key in config.php has not changed.'
                : 'openssl cannot do AES-256-GCM on this server.'),
        'fix'   => $keyWeak
            ? 'Changing it makes everything already stored unreadable, so do it in this order: '
              . 'take a backup, put a long random value in secret_key, then re-enter your stored '
              . 'passwords and payment keys. If nothing is stored yet, just change it.'
            : 'Client credentials cannot be stored safely until this is green.',
    ];


    $stor = CM_ROOT . '/storage';
    $storOk = is_dir($stor) && is_writable($stor);
    $denyOk = is_file($stor . '/.htaccess');
    $closed = ($storOk && $probe) ? cm_storage_probe() : null;
    if (!$storOk) {
        $sState = 'fail';
        $sDetail = 'storage/ is missing or not writable — spooled mail and the encryption key have nowhere to live.';
        $sFix = 'Create the storage folder beside index.php and make it writable by the web server (your host\'s file manager calls this permissions, usually 755 or 775).';
    } elseif ($closed === false) {
        $sState = 'fail';
        $sDetail = 'storage/ can be READ FROM THE WEB — a test file in it was fetched from this site\'s own address. It holds the encryption key and spooled email.';
        $sFix = 'Your host is ignoring the .htaccess files. Ask them to enable AllowOverride for this folder, or move the site so storage/ sits outside public_html.';
    } elseif (!$denyOk) {
        $sState = 'warn';
        $sDetail = 'Writable, but its deny file could not be written.';
        $sFix = 'Check storage/ is writable, then reload this page — the deny file is written on every page load when it is missing.';
    } else {
        $sState = 'ok';
        $sDetail = $closed === true ? 'Writable, and closed to the web — checked by asking for a file in it just now.'
                                    : 'Writable, and its deny file is in place.';
        $sFix = '';
    }
    $checks[] = ['label' => 'The storage folder', 'state' => $sState, 'detail' => $sDetail, 'fix' => $sFix];


    $checks[] = [
        'label' => 'The installer',
        'state' => cm_installer_present() ? 'fail' : 'ok',
        'detail' => cm_installer_present()
            ? 'install.php is still on the server. Remove it below — an installer '
              . 'on a working site is a takeover button the day any of its checks regress.'
            : 'install.php is gone, as it should be after an install.',
        'fix'   => 'Remove it in the Cleanup section below.',
    ];


    $files = cm_manifest_check();
    if ($files['state'] === 'present') {
        $bad = count($files['missing']) + count($files['changed']);
        $checks[] = [
            'label' => 'Uploaded files',
            'state' => $bad ? 'fail' : 'ok',
            'detail' => $bad
                ? $bad . ' of ' . $files['total'] . ' files are missing or did not arrive completely.'
                : 'All ' . $files['total'] . ' files match the release exactly.',
            'fix'   => 'Upload the release again through cPanel → File Manager, then reload this page.',
        ];
    } else {
        $checks[] = [
            'label' => 'Uploaded files',
            'state' => 'warn',
            'detail' => 'No MANIFEST.txt — this is a development copy, not an installed release, '
                      . 'so file integrity cannot be checked.',
            'fix'   => '',
        ];
    }


    $checks[] = [
        'label' => 'Email',
        'state' => cm_mail_enabled() ? 'ok' : 'warn',
        'detail' => cm_mail_enabled()
            ? 'Sending is on, from ' . cm_mail_from() . '.'
            : 'Sending is off — messages are spooled to storage/mail/ instead. Password resets '
            . 'and email sign-in codes cannot reach anybody until it is on.',
        'fix'   => 'Admin → Settings → Email.',
    ];


    try {
        require_once CM_LIB . '/migrate.php';
        $pending = cm_migrations_pending();
        $checks[] = [
            'label' => 'Database schema',
            'state' => $pending ? 'warn' : 'ok',
            'detail' => $pending
                ? count($pending) . ' database update' . (count($pending) === 1 ? '' : 's')
                  . ' waiting to run.'
                : 'Every update file on this server has been applied.',
            'fix'   => 'The update section below runs them.',
        ];
    } catch (Throwable $ex) {
        $checks[] = [
            'label' => 'Database schema',
            'state' => 'fail',
            'detail' => 'The database could not be read: ' . $ex->getMessage(),
            'fix'   => 'Check the db settings in config.php.',
        ];
    }

    return $checks;
}


function cm_health_worst(array $checks): string
{
    $worst = 'ok';
    foreach ($checks as $c) {
        if ($c['state'] === 'fail') return 'fail';
        if ($c['state'] === 'warn') $worst = 'warn';
    }
    return $worst;
}
