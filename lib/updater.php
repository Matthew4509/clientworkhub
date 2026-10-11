<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0















































declare(strict_types=1);
if (!defined('CM_ROOT')) { http_response_code(404); exit; }


const CM_UPDATE_DIR = 'storage/update';

const CM_UPDATE_MAX_BYTES = 67108864;






const CM_UPDATE_MAX_FILE  = 16777216;
const CM_UPDATE_MAX_TOTAL = 134217728;





const CM_UPDATE_FEED_DEFAULT = '';

const CM_PROJECT_URL  = 'https://github.com/Matthew4509/clientworkhub';
const CM_RELEASES_URL = 'https://github.com/Matthew4509/clientworkhub/releases';



const CM_UPDATE_PUBKEY = '7jyb4pvxoFqA9dKj3NeIjCl/R6wqWcdF0yW/VzszrcM=';



function cm_update_protected(): array
{
    return ['config.php', 'config.sample.php', 'storage', 'install.php', '.well-known'];
}

function cm_update_ready(): bool
{
    return class_exists('ZipArchive');
}

function cm_update_online_ready(): bool
{
    return function_exists('curl_init') && function_exists('sodium_crypto_sign_verify_detached');
}


function cm_update_dir(): ?string
{
    $dir = (defined('CM_STORAGE') ? CM_STORAGE : CM_ROOT . '/storage') . '/update';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return null;
    return $dir;
}



function cm_update_safe_rel(string $rel): bool
{
    if ($rel === '' || strlen($rel) > 512)              return false;
    if (str_contains($rel, "\0"))                        return false;
    if (str_contains($rel, '\\'))                        return false;
    if ($rel[0] === '/' || preg_match('/^[A-Za-z]:/', $rel)) return false;
    foreach (explode('/', $rel) as $part) {
        if ($part === '' || $part === '.' || $part === '..') return false;
    }
    return true;
}

function cm_update_is_protected(string $rel): bool
{
    foreach (cm_update_protected() as $p) {
        if ($rel === $p || str_starts_with($rel, $p . '/')) return true;
    }
    return false;
}


function cm_update_parse_manifest(string $text): array
{
    $out = [];
    foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $at = strpos($line, '  ');
        if ($at === false) continue;
        $hash = substr($line, 0, $at);
        $rel  = trim(substr($line, $at + 2));
        if (!preg_match('/^[0-9a-f]{64}$/', $hash)) continue;
        if (!cm_update_safe_rel($rel)) continue;
        $out[$rel] = $hash;
    }
    return $out;
}







function cm_update_entry_size(ZipArchive $zip, string $rel): ?int
{
    $st = $zip->statName($rel);
    return is_array($st) ? (int) $st['size'] : null;
}






function cm_update_entry_stream(ZipArchive $zip, string $rel, int &$budget, callable $sink): string
{
    $size = cm_update_entry_size($zip, $rel);
    if ($size === null) return 'missing';
    if ($size > CM_UPDATE_MAX_FILE || $size > $budget) return 'too large';
    $in = $zip->getStream($rel);
    if ($in === false) return 'missing';
    $seen = 0;
    while (!feof($in)) {
        $chunk = fread($in, 65536);
        if ($chunk === false) { fclose($in); return 'unreadable'; }
        if ($chunk === '') continue;
        $seen += strlen($chunk);
        if ($seen > CM_UPDATE_MAX_FILE || $seen > $budget) { fclose($in); return 'too large'; }
        if ($sink($chunk) === false) { fclose($in); return 'unwritable'; }
    }
    fclose($in);
    $budget -= $seen;
    return '';
}


function cm_update_entry_small(ZipArchive $zip, string $rel, int $max): ?string
{
    $size = cm_update_entry_size($zip, $rel);
    if ($size === null || $size > $max) return null;
    $data = '';
    $budget = $max;
    $why = cm_update_entry_stream($zip, $rel, $budget, static function (string $c) use (&$data): bool { $data .= $c; return true; });
    return $why === '' ? $data : null;
}






function cm_update_inspect(string $zipPath): array
{
    $fail = static function (string $why): array {
        return ['ok' => false, 'error' => $why, 'version' => '', 'files' => [],
                'count' => 0, 'writes' => 0, 'has_migrations' => false];
    };
    if (!cm_update_ready()) {
        return $fail('This server does not have PHP\'s zip extension, so a release cannot be '
                   . 'opened here. Ask your host to enable it, or upload the files by FTP.');
    }
    if (!is_file($zipPath)) return $fail('That upload is no longer on the server.');
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CHECKCONS) !== true) {


        if ($zip->open($zipPath) !== true) {
            return $fail('That file could not be opened as a zip. If the upload was slow, it may '
                       . 'have been cut short — try again.');
        }
        return $fail('That zip is damaged or incomplete. Upload it again.');
    }
    $manifestText = cm_update_entry_small($zip, 'MANIFEST.txt', 1048576);
    if ($manifestText === null) {
        $zip->close();
        return $fail('That zip has no readable MANIFEST.txt, so it is not a Client Work Hub release. '
                   . 'Nothing was changed.');
    }
    $files = cm_update_parse_manifest((string) $manifestText);
    if (!$files) {
        $zip->close();
        return $fail('That release\'s MANIFEST.txt is empty or unreadable. Nothing was changed.');
    }


    $hasLib = false;
    $hasMigrations = false;
    foreach (array_keys($files) as $rel) {
        if (str_starts_with($rel, 'lib/'))        $hasLib = true;
        if (str_starts_with($rel, 'migrations/')) $hasMigrations = true;
    }
    if (!$hasLib) {
        $zip->close();
        return $fail('That zip has no application files in it, so it is not a Client Work Hub '
                   . 'release. Applying it would delete the whole application. Refused.');
    }

    $budget = CM_UPDATE_MAX_TOTAL;
    foreach ($files as $rel => $want) {
        $h = hash_init('sha256');
        $why = cm_update_entry_stream($zip, $rel, $budget, static function (string $c) use ($h): bool { hash_update($h, $c); return true; });
        if ($why === 'too large') {
            $zip->close();
            return $fail('That zip is not a Client Work Hub release — ' . $rel . ' unpacks to far more than any '
                       . 'release file, or the whole release would. Nothing was changed.');
        }
        if ($why !== '') {
            $zip->close();
            return $fail('The release is incomplete — ' . $rel . ' is listed in its manifest but '
                       . 'is not in the zip. Upload it again.');
        }
        if (!hash_equals($want, hash_final($h))) {
            $zip->close();
            return $fail('The release is damaged — ' . $rel . ' does not match its own checksum. '
                       . 'Upload it again.');
        }
    }

    $version = '';
    $bootstrap = cm_update_entry_small($zip, 'lib/bootstrap.php', CM_UPDATE_MAX_FILE);
    if (is_string($bootstrap) && preg_match("/const CM_VERSION = '([^']+)';/", $bootstrap, $m)) {
        $version = $m[1];
    }
    $zip->close();
    if ($version === '') {
        return $fail('That release does not say what version it is. Nothing was changed.');
    }


    $writes = count(array_filter(array_keys($files), static fn($r) => !cm_update_is_protected((string) $r)));
    return ['ok' => true, 'error' => '', 'version' => $version, 'files' => $files,
            'count' => count($files), 'writes' => $writes, 'has_migrations' => $hasMigrations];
}


























function cm_update_upload_signature(string $zipPath, ?string $pubB64 = null): array
{



    if (function_exists('cm_config') && cm_config('update.allow_unsigned_upload', false) === true) {
        return ['ok' => true, 'error' => '', 'note' => 'Its signature was not checked: config.php allows unsigned uploads.'];
    }
    if (!function_exists('sodium_crypto_sign_verify_detached')) {
        return ['ok' => true, 'error' => '',
                'note' => 'This server\'s PHP has no sodium, so the release\'s signature could not be checked — '
                        . 'only its own checksums. Upload only a release you got from ' . CM_RELEASES_URL . '.'];
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) return ['ok' => false, 'error' => 'The release could not be reopened.', 'note' => ''];
    $manifest = cm_update_entry_small($zip, 'MANIFEST.txt', 1048576);
    $sigText  = cm_update_entry_small($zip, 'MANIFEST.sig', 4096);
    $zip->close();
    if ($manifest === null) return ['ok' => false, 'error' => 'That zip has no MANIFEST.txt. Nothing was changed.', 'note' => ''];
    if ($sigText === null) {
        return ['ok' => false, 'note' => '',
                'error' => 'That release is not signed (it has no MANIFEST.sig), so it was refused and nothing was '
                         . 'changed. Every release from 0.3.16 on is signed; an older one cannot be installed by '
                         . 'upload. Download the current release from ' . CM_RELEASES_URL . '.'];
    }
    $sig = base64_decode(trim($sigText), true);
    $pub = base64_decode($pubB64 ?? CM_UPDATE_PUBKEY, true);
    if ($sig === false || $pub === false || strlen($pub) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
        || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES
        || !sodium_crypto_sign_verify_detached($sig, $manifest, $pub)) {
        return ['ok' => false, 'note' => '',
                'error' => 'That release is NOT signed with Client Work Hub\'s release key, so it was refused and '
                         . 'nothing was changed. If you did not build it yourself, do not install it.'];
    }
    return ['ok' => true, 'error' => '', 'note' => ''];
}




function cm_update_current_manifest(): array
{
    $path = CM_ROOT . '/MANIFEST.txt';
    if (!is_file($path)) return [];
    return cm_update_parse_manifest((string) @file_get_contents($path));
}



function cm_update_removals(array $newFiles): array
{
    $old = cm_update_current_manifest();
    if (!$old) return [];
    $out = [];
    foreach (array_keys($old) as $rel) {
        if (isset($newFiles[$rel]))       continue;
        if (cm_update_is_protected($rel)) continue;
        if (!cm_update_safe_rel($rel))    continue;
        $abs = CM_ROOT . '/' . $rel;
        if (!is_file($abs) || is_link($abs)) continue;
        $out[] = $rel;
    }
    sort($out);
    return $out;
}







function cm_update_extract(string $zipPath, array $files): array
{
    $base = cm_update_dir();
    if ($base === null) {
        return ['ok' => false, 'dir' => '',
                'error' => 'The server cannot create ' . CM_UPDATE_DIR . '. Check the folder is writable.'];
    }
    $dir = $base . '/stage';
    cm_update_rmtree($dir);
    if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'dir' => '', 'error' => 'The server could not create the staging folder.'];
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return ['ok' => false, 'dir' => '', 'error' => 'The release could not be reopened.'];
    }
    $budget = CM_UPDATE_MAX_TOTAL;
    foreach (array_keys($files) as $rel) {
        if (cm_update_is_protected($rel)) continue;
        $dest = $dir . '/' . $rel;
        $destDir = dirname($dest);
        if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            $zip->close();
            return ['ok' => false, 'dir' => '', 'error' => 'Could not create ' . dirname($rel) . ' while staging.'];
        }
        $out = @fopen($dest, 'wb');
        $why = $out === false ? 'unwritable'
             : cm_update_entry_stream($zip, $rel, $budget, static fn(string $c): bool => fwrite($out, $c) === strlen($c));
        if ($out !== false) fclose($out);
        if ($why === 'missing' || $why === 'unreadable' || $why === 'too large') {
            $zip->close();
            return ['ok' => false, 'dir' => '', 'error' => 'Could not read ' . $rel . ' from the release.'];
        }
        if ($why !== '') {
            $zip->close();
            return ['ok' => false, 'dir' => '',
                    'error' => 'Could not write ' . $rel . ' while staging — the disk may be full '
                             . 'or the folder may not be writable.'];
        }
    }
    $zip->close();
    foreach ($files as $rel => $want) {
        if (cm_update_is_protected($rel)) continue;
        $dest = $dir . '/' . $rel;
        if (!is_file($dest) || !hash_equals($want, hash_file('sha256', $dest))) {
            return ['ok' => false, 'dir' => '',
                    'error' => 'Staging did not complete — ' . $rel . ' did not survive the write. '
                             . 'Nothing on the live site has been touched.'];
        }
    }
    return ['ok' => true, 'error' => '', 'dir' => $dir];
}









function cm_update_apply(string $stageDir, array $files, string $actor): array
{
    $written = 0;
    $failed  = [];
    @set_time_limit(300);
    @ignore_user_abort(true);
    foreach (array_keys($files) as $rel) {
        if (cm_update_is_protected($rel)) continue;
        if (!cm_update_safe_rel($rel))    { $failed[$rel] = 'refused as an unsafe path'; continue; }
        $src  = $stageDir . '/' . $rel;
        $dest = CM_ROOT . '/' . $rel;
        if (!is_file($src)) { $failed[$rel] = 'missing from the staged copy'; continue; }
        $destDir = dirname($dest);
        if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            $failed[$rel] = 'could not create its folder';
            continue;
        }



        $tmp = $dest . '.cmnew';
        if (@copy($src, $tmp) && @rename($tmp, $dest)) {
            $written++;
        } else {
            @unlink($tmp);
            if (@copy($src, $dest)) {
                $written++;
            } else {
                $failed[$rel] = 'the server refused to write it (file permissions)';
            }
        }
    }
    if ($failed) {
        return ['ok' => false, 'written' => $written, 'removed' => [], 'failed' => $failed,
                'error' => 'Some files could not be written, so the update was stopped part-way '
                         . 'and nothing was deleted. The site still runs the old release where the '
                         . 'new one could not be written. Fix the permissions and run it again.'];
    }
    $removed = [];
    foreach (cm_update_removals($files) as $rel) {
        $abs = CM_ROOT . '/' . $rel;
        if (@unlink($abs)) $removed[] = $rel;
        else $failed[$rel] = 'could not be removed (file permissions)';
    }







    $lines = [];
    foreach ($files as $rel => $hash) {
        if (cm_update_is_protected($rel)) continue;
        $lines[] = $hash . '  ' . $rel;
    }
    sort($lines);
    if (@file_put_contents(CM_ROOT . '/MANIFEST.txt', implode("\n", $lines) . "\n") === false) {
        return ['ok' => false, 'written' => $written, 'removed' => $removed, 'failed' => $failed,
                'error' => 'The files were updated but MANIFEST.txt could not be written, so this '
                         . 'site cannot check itself. Fix the permissions and run the update again.'];
    }


    if (function_exists('opcache_reset')) @opcache_reset();
    if (function_exists('cm_audit')) {
        cm_audit('update.files', '', null, $written . ' file(s) written, ' . count($removed)
                 . ' removed, by ' . $actor);
    }
    return ['ok' => true, 'error' => '', 'written' => $written,
            'removed' => $removed, 'failed' => $failed];
}


function cm_update_rmtree(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir() && !$item->isLink()) @rmdir($item->getPathname());
        else @unlink($item->getPathname());
    }
    @rmdir($dir);
}


function cm_update_clear(): void
{
    $base = cm_update_dir();
    if ($base === null) return;
    cm_update_rmtree($base . '/stage');
    foreach (glob($base . '/*.zip') ?: [] as $f) @unlink($f);
    @unlink($base . '/feed.json');
}


function cm_update_pending_zip(): ?string
{
    $base = cm_update_dir();
    if ($base === null) return null;
    $zips = glob($base . '/*.zip') ?: [];
    usort($zips, static fn($a, $b) => filemtime($b) <=> filemtime($a));
    return $zips[0] ?? null;
}


function cm_update_receive(array $file): array
{
    $fail = static fn(string $why): array => ['ok' => false, 'error' => $why, 'path' => ''];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return $fail('The upload did not arrive. If the zip is large and the connection slow, try again.');
    }
    if (($file['size'] ?? 0) > CM_UPDATE_MAX_BYTES) {
        return $fail('That file is far larger than any release. Refused.');
    }
    $base = cm_update_dir();
    if ($base === null) return $fail('The server cannot create ' . CM_UPDATE_DIR . '.');
    cm_update_clear();
    $dest = $base . '/upload-' . date('Ymd-His') . '.zip';
    if (!@move_uploaded_file((string) $file['tmp_name'], $dest)) {


        if (PHP_SAPI !== 'cli' || !@rename((string) $file['tmp_name'], $dest)) {
            return $fail('The server could not keep the upload. Check storage/ is writable.');
        }
    }
    return ['ok' => true, 'error' => '', 'path' => $dest];
}






function cm_update_feed_url(): string
{

    if (function_exists('cm_home') && cm_home()) return '';
    if (function_exists('cm_config')) {
        $u = cm_config('update.feed', CM_UPDATE_FEED_DEFAULT);


        if (is_string($u)) {
            $u = trim($u);
            $parts = parse_url($u);
            if (is_array($parts) && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
                && (string) ($parts['host'] ?? '') !== '') {
                return $u;
            }
        }
    }
    return '';
}




function cm_update_online_on(): bool
{
    return cm_update_feed_url() !== '';
}





function cm_update_http_get(string $url, int $maxBytes, int $timeout = 60, int $connect = 10): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'PHP\'s curl extension is not loaded.', 'body' => ''];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => $connect,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'ClientWorkHub-Updater',




        CURLOPT_MAXFILESIZE => $maxBytes,
        CURLOPT_NOPROGRESS => false,
        CURLOPT_XFERINFOFUNCTION => static fn($ch, int $dlTotal, int $dlNow): int => $dlNow > $maxBytes ? 1 : 0,
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    if ($errno === CURLE_FILESIZE_EXCEEDED || $errno === CURLE_ABORTED_BY_CALLBACK) {
        return ['ok' => false, 'error' => 'The update server\'s answer was too large.', 'body' => ''];
    }
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = $body === false ? (string) curl_error($ch) : '';
    if ($err !== '')      return ['ok' => false, 'error' => 'Could not reach the update server: ' . $err, 'body' => ''];
    if ($status !== 200)  return ['ok' => false, 'error' => 'The update server answered ' . $status . '.', 'body' => ''];
    if (!is_string($body) || strlen($body) > $maxBytes) {
        return ['ok' => false, 'error' => 'The update server\'s answer was too large.', 'body' => ''];
    }
    return ['ok' => true, 'error' => '', 'body' => $body];
}







function cm_update_history_clean($list): array
{
    $out = [];
    foreach (is_array($list) ? $list : [] as $h) {
        if (!is_array($h)) continue;
        $v = (string) ($h['version'] ?? '');
        if (!preg_match('/^\d+\.\d+\.\d+$/', $v)) continue;
        $date = (string) ($h['released'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = '';
        $out[] = ['version' => $v, 'released' => $date,
                  'notes' => mb_substr((string) ($h['notes'] ?? ''), 0, 2000)];
        if (count($out) >= 25) break;
    }
    return $out;
}











function cm_update_feed_meta_message(array $feed): string
{
    return "cwh-feed-1\n" . json_encode([
        'version'  => (string) ($feed['version'] ?? ''),
        'sha256'   => (string) ($feed['sha256'] ?? ''),
        'released' => (string) ($feed['released'] ?? ''),
        'notes'    => (string) ($feed['notes'] ?? ''),
        'history'  => is_array($feed['history'] ?? null) ? $feed['history'] : [],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}







function cm_update_feed_parse(string $body, string $currentVersion, ?string $pubB64 = null): array
{
    $fail = static fn(string $why): array => ['ok' => false, 'error' => $why];
    $j = json_decode($body, true);
    if (!is_array($j)) return $fail('The update feed could not be read.');
    $metaSig = (string) ($j['notes_sig'] ?? '');
    $wordsChecked = false;
    if ($metaSig !== '') {
        $sigRaw = base64_decode($metaSig, true);
        $pub    = base64_decode($pubB64 ?? CM_UPDATE_PUBKEY, true);
        if (!function_exists('sodium_crypto_sign_verify_detached') || $sigRaw === false || $pub === false
            || strlen($sigRaw) !== SODIUM_CRYPTO_SIGN_BYTES || strlen($pub) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || !sodium_crypto_sign_verify_detached($sigRaw, cm_update_feed_meta_message($j), $pub)) {
            return $fail('The update feed\'s notes are not signed with Client Work Hub\'s release key, so the feed was '
                       . 'refused. Try again later, or update by upload with a release from ' . CM_RELEASES_URL . '.');
        }
        $wordsChecked = true;
    }
    if (!$wordsChecked) {
        $j['notes'] = 'The notes for this release could not be checked against the release signature, so they '
                    . 'are not shown. The release itself is still checked before it is installed.';
        $j['history'] = [];
    }
    $version = (string) ($j['version'] ?? '');
    $zip     = (string) ($j['zip'] ?? '');
    $sha     = strtolower((string) ($j['sha256'] ?? ''));
    $sig     = (string) ($j['sig'] ?? '');
    $notes   = (string) ($j['notes'] ?? '');
    $date    = (string) ($j['released'] ?? '');
    if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) return $fail('The update feed names no version.');
    if (!preg_match('#^https://#', $zip))           return $fail('The update feed carries no https zip address.');
    if (!preg_match('/^[0-9a-f]{64}$/', $sha))      return $fail('The update feed carries no checksum.');
    if ($sig === '')                                 return $fail('The update feed carries no signature.');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = '';
    return ['ok' => true, 'error' => '', 'version' => $version, 'zip' => $zip,
            'sha256' => $sha, 'sig' => $sig, 'notes' => mb_substr($notes, 0, 2000),
            'released' => $date, 'history' => cm_update_history_clean($j['history'] ?? []),
            'words_checked' => $wordsChecked,
            'newer' => version_compare($version, $currentVersion, '>')];
}





function cm_update_check(string $currentVersion): array
{
    if (!cm_update_online_on()) {
        return ['ok' => false, 'error' => 'Online update checks are switched off. Download the newest release '
                                        . 'from ' . CM_RELEASES_URL . ' and upload it on this screen.'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'This server\'s PHP has no curl, so it cannot check '
                                        . 'online. Update by upload instead.'];
    }
    $r = cm_update_http_get(cm_update_feed_url(), 262144);
    if (!$r['ok']) return ['ok' => false, 'error' => $r['error']];
    return cm_update_feed_parse($r['body'], $currentVersion);
}



















const CM_UPDATE_QUIET_EVERY = 86400;




function cm_update_note_feed(array $chk): void
{
    if (!function_exists('cm_setting_set')) return;
    cm_setting_set('update.checked_at', (string) time(), 'update check');
    if (empty($chk['ok'])) return;
    $keep = ['at' => time(), 'version' => (string) $chk['version'],
             'released' => (string) ($chk['released'] ?? ''),
             'notes' => (string) ($chk['notes'] ?? ''),
             'history' => cm_update_history_clean($chk['history'] ?? [])];
    cm_setting_set('update.feed_cache', (string) json_encode($keep), 'update check');
}






function cm_update_known(): array
{
    $none = ['version' => '', 'released' => '', 'notes' => '', 'history' => [],
             'checked_at' => 0, 'newer' => false];
    if (!function_exists('cm_setting') || !cm_update_online_on()) return $none;
    try { $raw = (string) cm_setting('update.feed_cache', ''); }
    catch (Throwable $ex) { return $none; }
    if ($raw === '') return $none;
    $j = json_decode($raw, true);
    if (!is_array($j)) return $none;
    $version = (string) ($j['version'] ?? '');
    if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) return $none;
    $date = (string) ($j['released'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = '';
    $current = defined('CM_VERSION') ? CM_VERSION : '0.0.0';
    return ['version' => $version, 'released' => $date,
            'notes' => mb_substr((string) ($j['notes'] ?? ''), 0, 2000),
            'history' => cm_update_history_clean($j['history'] ?? []),
            'checked_at' => (int) ($j['at'] ?? 0),
            'newer' => version_compare($version, $current, '>')];
}


function cm_update_quiet_due(): bool
{
    if (!function_exists('cm_setting')) return false;
    try { $at = (int) cm_setting('update.checked_at', '0'); }
    catch (Throwable $ex) { return false; }
    return time() - $at >= CM_UPDATE_QUIET_EVERY;
}




function cm_update_quiet_check(): void
{
    if (!function_exists('curl_init') || !cm_update_online_on()) return;
    if (!cm_update_quiet_due()) return;
    $current = defined('CM_VERSION') ? CM_VERSION : '0.0.0';
    $r = cm_update_http_get(cm_update_feed_url(), 262144, 6, 4);
    cm_update_note_feed($r['ok'] ? cm_update_feed_parse($r['body'], $current) : ['ok' => false]);
}







function cm_update_verify_bytes(string $bytes, string $sha256, string $sigB64, ?string $pubB64 = null): string
{
    if (!hash_equals(strtolower($sha256), hash('sha256', $bytes))) {
        return 'The downloaded release does not match the feed\'s checksum. Nothing was changed.';
    }
    $sig = base64_decode($sigB64, true);
    $pub = base64_decode($pubB64 ?? CM_UPDATE_PUBKEY, true);
    if ($sig === false || $pub === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES
        || strlen($pub) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
        || !sodium_crypto_sign_verify_detached($sig, $bytes, $pub)) {
        return 'The downloaded release is NOT signed with Client Work Hub\'s release key, so it '
             . 'was refused and nothing was changed. If this keeps happening, tell the person '
             . 'you got this software from.';
    }
    return '';
}




function cm_update_download(array $feed): array
{
    $fail = static fn(string $why): array => ['ok' => false, 'error' => $why, 'path' => ''];
    if (!cm_update_online_on()) return $fail('Online updates are switched off.');
    if (!cm_update_online_ready()) {
        return $fail('This server\'s PHP is missing curl or sodium, so a release cannot be '
                   . 'verified here. Update by upload instead — that path needs neither.');
    }
    $r = cm_update_http_get((string) $feed['zip'], CM_UPDATE_MAX_BYTES);
    if (!$r['ok']) return $fail($r['error']);
    $bytes = $r['body'];
    $bad = cm_update_verify_bytes($bytes, (string) $feed['sha256'], (string) $feed['sig']);
    if ($bad !== '') return $fail($bad);
    $base = cm_update_dir();
    if ($base === null) return $fail('The server cannot create ' . CM_UPDATE_DIR . '.');
    cm_update_clear();
    $dest = $base . '/online-' . preg_replace('/[^0-9.]/', '', (string) $feed['version']) . '.zip';
    if (@file_put_contents($dest, $bytes) === false) {
        return $fail('The release could not be kept on disk. Check storage/ is writable.');
    }
    return ['ok' => true, 'error' => '', 'path' => $dest];
}













const CM_GITHUB_LATEST_API = 'https://api.github.com/repos/Matthew4509/clientworkhub/releases/latest';


const CM_GITHUB_HOSTS = ['api.github.com', 'github.com', 'objects.githubusercontent.com',
                         'release-assets.githubusercontent.com', 'github-releases.githubusercontent.com'];


function cm_update_github_api(): string
{
    $u = function_exists('cm_config') ? cm_config('update.github_api', '') : '';
    return is_string($u) && preg_match('#^https?://[^/\s]+/#', $u) ? $u : CM_GITHUB_LATEST_API;
}

function cm_update_github_host_ok(string $url): bool
{
    $p = parse_url($url);
    if (!is_array($p) || empty($p['host'])) return false;
    $host = strtolower((string) $p['host']);
    if (cm_update_github_api() !== CM_GITHUB_LATEST_API) {

        $api = parse_url(cm_update_github_api());
        return is_array($api) && $host === strtolower((string) ($api['host'] ?? ''));
    }
    return strtolower((string) ($p['scheme'] ?? '')) === 'https' && in_array($host, CM_GITHUB_HOSTS, true);
}







function cm_update_github_get(string $url, int $maxBytes, ?callable $sink = null): array
{
    $fail = static fn(string $why): array => ['ok' => false, 'error' => $why, 'body' => ''];
    if (!function_exists('curl_init')) return $fail('PHP\'s curl extension is not loaded.');
    for ($hop = 0; $hop < 6; $hop++) {
        if (!cm_update_github_host_ok($url)) {
            return $fail('The download was sent somewhere other than GitHub, so it was stopped.');
        }
        $body = '';
        $got = 0;
        $tooBig = false;
        $ch = curl_init($url);
        $opts = [
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 300,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json',
                                   'User-Agent: ClientWorkHub/' . (defined('CM_VERSION') ? CM_VERSION : '0')],
            CURLOPT_WRITEFUNCTION => static function ($c, string $chunk) use (&$body, &$got, &$tooBig, $maxBytes, $sink): int {
                $got += strlen($chunk);
                if ($got > $maxBytes) { $tooBig = true; return 0; }
                if ($sink !== null && curl_getinfo($c, CURLINFO_RESPONSE_CODE) === 200) {
                    return $sink($chunk) ? strlen($chunk) : 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ];
        if (defined('CURLSSLOPT_NATIVE_CA') && PHP_OS_FAMILY === 'Windows') {
            $opts[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }
        curl_setopt_array($ch, $opts);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $next = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $err = curl_error($ch);
        if ($tooBig) return $fail('GitHub sent far more than any release. Stopped.');
        if (in_array($code, [301, 302, 303, 307, 308], true) && $next !== '') { $url = $next; continue; }
        if ($ok === false) return $fail('GitHub could not be reached: ' . ($err !== '' ? $err : 'no answer') . '.');
        if ($code === 403 || $code === 429) {
            return $fail('GitHub is limiting how often it can be asked. Try again in an hour.');
        }
        if ($code === 404) return $fail('GitHub has no published release to check against yet.');
        if ($code !== 200) return $fail('GitHub answered ' . $code . '. Try again later.');
        return ['ok' => true, 'error' => '', 'body' => $body];
    }
    return $fail('GitHub redirected too many times. Try again later.');
}






function cm_update_github_check(string $current): array
{
    $out = ['ok' => false, 'error' => '', 'version' => '', 'newer' => false, 'zip' => '',
            'size' => 0, 'title' => '', 'notes' => '', 'page' => CM_RELEASES_URL, 'published' => ''];
    $r = cm_update_github_get(cm_update_github_api(), 2097152);
    if (!$r['ok']) return ['error' => $r['error']] + $out;
    $j = json_decode($r['body'], true);
    if (!is_array($j) || !isset($j['assets']) || !is_array($j['assets'])) {
        return ['error' => 'GitHub\'s answer could not be read. Try again later.'] + $out;
    }
    $best = null;
    foreach ($j['assets'] as $a) {
        $name = (string) ($a['name'] ?? '');
        if (!preg_match('/^clientworkhub-(\d+\.\d+\.\d+)\.zip$/', $name, $m)) continue;
        if ($best === null || version_compare($m[1], $best['version'], '>')) {
            $best = ['version' => $m[1], 'zip' => (string) ($a['browser_download_url'] ?? ''),
                     'size' => (int) ($a['size'] ?? 0)];
        }
    }
    if ($best === null || $best['zip'] === '') {
        return ['error' => 'The latest release on GitHub has no Client Work Hub zip attached.'] + $out;
    }
    $page = (string) ($j['html_url'] ?? '');
    return ['ok' => true, 'error' => '', 'version' => $best['version'],
            'newer' => version_compare($best['version'], $current, '>'),
            'zip' => $best['zip'], 'size' => $best['size'],
            'title' => mb_substr(trim((string) ($j['name'] ?? '')), 0, 200),
            'notes' => mb_substr(trim((string) ($j['body'] ?? '')), 0, 4000),
            'page' => str_starts_with($page, 'https://github.com/') ? $page : CM_RELEASES_URL,
            'published' => (string) ($j['published_at'] ?? '')];
}



function cm_update_github_download(array $chk, ?string $pubB64 = null): array
{
    $fail = static fn(string $why): array => ['ok' => false, 'error' => $why, 'path' => '', 'version' => '', 'note' => ''];
    if (empty($chk['ok']) || (string) ($chk['zip'] ?? '') === '') return $fail('There is nothing to download.');
    if ((int) ($chk['size'] ?? 0) > CM_UPDATE_MAX_BYTES) return $fail('That release is far larger than any release. Refused.');
    $base = cm_update_dir();
    if ($base === null) return $fail('The update folder could not be created.');
    cm_update_clear();
    $dest = $base . '/upload-github-' . date('Ymd-His') . '.zip';
    $fh = @fopen($dest, 'wb');
    if ($fh === false) return $fail('The release could not be saved in the update folder.');
    $r = cm_update_github_get((string) $chk['zip'], CM_UPDATE_MAX_BYTES,
                              static fn(string $c): bool => fwrite($fh, $c) === strlen($c));
    fclose($fh);
    if (!$r['ok']) { @unlink($dest); return $fail($r['error']); }
    $ins = cm_update_inspect($dest);
    if (!$ins['ok']) { cm_update_clear(); return $fail($ins['error']); }
    $sig = cm_update_upload_signature($dest, $pubB64);
    if (!$sig['ok']) { cm_update_clear(); return $fail($sig['error']); }
    if ($ins['version'] !== (string) $chk['version']) {
        cm_update_clear();
        return $fail('GitHub named the release ' . $chk['version'] . ' but the signed zip is '
                   . $ins['version'] . ', so it was refused and nothing was changed.');
    }
    return ['ok' => true, 'error' => '', 'path' => $dest, 'version' => $ins['version'],
            'note' => (string) ($sig['note'] ?? '')];
}


function cm_update_rollback_dir(): string
{
    return (defined('CM_STORAGE') ? CM_STORAGE : CM_ROOT . '/storage') . '/rollback';
}


function cm_update_rollback_version(): string
{
    $f = cm_update_rollback_dir() . '/VERSION.txt';
    return is_file($f) ? trim((string) @file_get_contents($f)) : '';
}






function cm_update_rollback_keep(string $version): array
{
    $files = cm_update_current_manifest();
    if (!$files) return ['ok' => false, 'error' => 'This copy has no MANIFEST.txt, so no copy to go back to could be kept.'];
    $dir = cm_update_rollback_dir();
    cm_update_rmtree($dir);
    $lines = [];
    foreach (array_keys($files) as $rel) {
        if (cm_update_is_protected($rel) || !cm_update_safe_rel($rel)) continue;
        $src = CM_ROOT . '/' . $rel;
        if (!is_file($src)) continue;
        $dest = $dir . '/files/' . $rel;
        if ((!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0755, true) && !is_dir(dirname($dest)))
            || !@copy($src, $dest)) {
            return ['ok' => false, 'error' => 'The copy to go back to could not be written (' . $rel . ').'];
        }
        $lines[] = hash_file('sha256', $dest) . '  ' . $rel;
    }
    sort($lines);
    @file_put_contents($dir . '/MANIFEST.txt', implode("\n", $lines) . "\n");
    @file_put_contents($dir . '/VERSION.txt', $version . "\n");
    return ['ok' => true, 'error' => '', 'count' => count($lines)];
}



function cm_update_rollback_apply(string $actor): array
{
    $dir = cm_update_rollback_dir();
    $version = cm_update_rollback_version();
    $files = cm_update_parse_manifest((string) @file_get_contents($dir . '/MANIFEST.txt'));
    if ($version === '' || !$files) return ['ok' => false, 'error' => 'There is no earlier copy to go back to.'];
    foreach ($files as $rel => $want) {
        $f = $dir . '/files/' . $rel;
        if (!is_file($f) || !hash_equals($want, hash_file('sha256', $f))) {
            return ['ok' => false, 'error' => 'The kept copy is damaged (' . $rel . '), so nothing was changed.'];
        }
    }
    $res = cm_update_apply($dir . '/files', $files, $actor);
    if (!$res['ok']) return ['ok' => false, 'error' => $res['error']];
    cm_update_rmtree($dir);
    return ['ok' => true, 'error' => '', 'version' => $version, 'written' => $res['written'],
            'removed' => $res['removed']];
}
