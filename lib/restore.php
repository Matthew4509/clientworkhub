<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0















































































declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }

require_once CM_LIB . '/backup.php';
require_once CM_LIB . '/seal.php';
require_once CM_LIB . '/migrate.php';














function cm_restore_dir(): string
{
    return CM_ROOT . '/storage/restore';
}


function cm_restore_available(): array
{
    $dir = cm_restore_dir();
    if (!is_dir($dir)) return [];

    $out = [];
    foreach ((array) @scandir($dir) as $name) {
        if ($name === '.' || $name === '..' || $name === '.htaccess') continue;
        $path = $dir . '/' . $name;
        if (!is_file($path)) continue;


        if (!preg_match('/(\.sql|\.sql\.gz|' . preg_quote(CM_SEAL_EXT, '/') . ')$/i', $name)) continue;
        $out[] = ['name' => $name, 'size' => (int) @filesize($path), 'at' => (int) @filemtime($path)];
    }
    usort($out, static fn(array $a, array $b) => $b['at'] <=> $a['at']);
    return $out;
}








function cm_restore_path(string $name): ?string
{
    $dir  = cm_restore_dir();
    $path = $dir . '/' . basename($name);
    $real = @realpath($path);
    $base = @realpath($dir);
    if ($real === false || $base === false) return null;
    if (strncmp($real, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0) return null;
    return is_file($real) ? $real : null;
}










function cm_restore_identify(string $path): array
{
    $fh = @fopen($path, 'rb');
    if ($fh === false) {
        return ['kind' => 'unknown', 'error' => 'That file cannot be read. Check its permissions, or upload it again.'];
    }
    $head = (string) fread($fh, 4096);
    fclose($fh);

    if (cm_seal_is_sealed($head)) {
        $h = cm_seal_read_header($head);
        return $h['ok']
            ? ['kind' => 'sealed', 'header' => $h['header']]
            : ['kind' => 'sealed', 'error' => $h['error']];
    }


    if (strncmp($head, "\x1f\x8b", 2) === 0) return ['kind' => 'gzip'];
    if (strpos($head, CM_BACKUP_MARKER) !== false) return ['kind' => 'sql'];

    return ['kind' => 'unknown', 'error' => 'That file is not a Client Work Hub backup. '
        . 'It is not sealed, it is not compressed, and it does not begin with the marker '
        . 'every backup this site writes begins with.'];
}





const CM_RESTORE_MAX_STATEMENT = 67108864;












function cm_restore_inflater(callable $source): callable
{
    $gz = inflate_init(ZLIB_ENCODING_GZIP);
    $pending = '';
    $ended = false;
    return static function () use ($source, $gz, &$pending, &$ended): string {
        while (true) {
            if ($pending === '') {
                if ($ended) return '';
                $pending = (string) $source();
                if ($pending === '') { $ended = true; return ''; }
            }
            $piece   = substr($pending, 0, 8192);
            $pending = (string) substr($pending, 8192);
            $out = inflate_add($gz, $piece, ZLIB_NO_FLUSH);
            if ($out === false) { $ended = true; $pending = ''; return ''; }
            if ($out !== '') return $out;
        }
    };
}











function cm_restore_plain_reader(string $path, array $phrase = []): array
{
    $kind = cm_restore_identify($path);
    if (isset($kind['error'])) return ['ok' => false, 'error' => $kind['error']];

    $fh = @fopen($path, 'rb');
    if ($fh === false) return ['ok' => false, 'error' => 'That file cannot be read.'];

    $raw = static function (int $n) use ($fh): string {
        if (feof($fh)) return '';
        $s = fread($fh, $n);
        return $s === false ? '' : $s;
    };

    if ($kind['kind'] === 'sealed') {
        $r = cm_seal_reader($raw, $phrase);
        if (!$r['ok']) { fclose($fh); return $r; }

        $done = false;
        $plain = static function () use ($r, &$done): string {
            if ($done) return '';
            $c = ($r['chunk'])();
            if ($c === null) { $done = true; return ''; }
            return $c;
        };
        $next = $r['header']['gzip'] ? cm_restore_inflater($plain) : $plain;
        return ['ok' => true, 'next' => $next, 'sealed' => true, 'header' => $r['header']];
    }

    if ($kind['kind'] === 'gzip') {
        if (!function_exists('inflate_init')) {
            fclose($fh);
            return ['ok' => false, 'error' => 'That backup is compressed and this host has no zlib, so it cannot be '
                . 'decompressed here. Unzip it on your own computer and upload the plain .sql.'];
        }
        $next = cm_restore_inflater(static fn(): string => $raw(262144));
        return ['ok' => true, 'next' => $next, 'sealed' => false, 'header' => null];
    }

    $next = static function () use ($raw): string { return $raw(262144); };
    return ['ok' => true, 'next' => $next, 'sealed' => false, 'header' => null];
}










function cm_restore_inspect(string $path, array $phrase = []): array
{
    $r = cm_restore_plain_reader($path, $phrase);
    if (!$r['ok']) return $r;

    $head      = '';
    $tail      = '';
    $bytes     = 0;
    $statements = 0;
    $appkey    = null;
    $carry     = '';
    $run       = 0;

    try {
        while (($chunk = ($r['next'])()) !== '') {
            $bytes += strlen($chunk);
            if (strlen($head) < 8192) $head .= $chunk;





            $statements += substr_count(substr($carry, -1) . $chunk, ";\n");
            $carry .= $chunk;
            $last = strrpos($carry, ";\n");
            $run = $last === false ? $run + strlen($chunk) : strlen($carry) - $last - 2;
            if ($run > CM_RESTORE_MAX_STATEMENT) {
                return ['ok' => false, 'error' => 'That file is not a backup this site wrote — it holds a single '
                    . 'statement of more than ' . cm_bytes_label(CM_RESTORE_MAX_STATEMENT) . ', and a real backup '
                    . 'writes one row at a time. Nothing has been changed.'];
            }


            $tail = substr($carry, -4096);
            $carry = $tail;
        }
    } catch (RuntimeException $ex) {

        return ['ok' => false, 'error' => $ex->getMessage()];
    }

    if (strpos($head, CM_BACKUP_MARKER) === false) {
        return ['ok' => false, 'error' => 'That file opened, but it is not a Client Work Hub backup — it does not '
            . 'carry the marker every backup this site writes. Nothing has been changed.'];
    }


    if (!preg_match('/-- end of backup, (\d+) tables/', $tail, $m)) {
        return ['ok' => false, 'error' => 'That backup is INCOMPLETE — the line every finished backup ends with is not '
            . 'there, so the download or the copy stopped part way. Nothing has been changed. Use another copy: '
            . 'restoring this one would leave the database half replaced with no way back.'];
    }



    if ($r['sealed'] && preg_match('/^' . preg_quote(CM_BACKUP_APPKEY_MARKER, '/') . '(\S+)$/m', $head, $k)) {
        $decoded = base64_decode($k[1], true);
        if (is_string($decoded) && $decoded !== '') $appkey = $decoded;
    }

    $report = [
        'bytes'      => $bytes,
        'tables'     => (int) $m[1],
        'statements' => $statements,
        'sealed'     => $r['sealed'],
        'appkey'     => $appkey,
        'taken'      => cm_restore_header_value($head, 'taken'),
        'site'       => cm_restore_header_value($head, 'site'),
        'app'        => cm_restore_header_value($head, 'app'),
        'schema'     => cm_restore_header_value($head, 'schema'),












        'passwords'  => cm_restore_header_value($head, 'passwords'),
    ];




    $ours  = cm_migration_number(cm_schema_version());
    $theirs = cm_migration_number((string) $report['schema']);
    if ($theirs !== '' && $ours !== '' && (int) $theirs > (int) $ours) {
        return ['ok' => false, 'error' => 'That backup is from a NEWER database than this site\'s files '
            . '(schema ' . $theirs . ' against ' . $ours . '). Update the site to at least the version that '
            . 'took it, then restore. Migrations only run forwards, so restoring it here would leave tables '
            . 'this code does not understand. Nothing has been changed.'];
    }
    $report['forward'] = ($theirs !== '' && $ours !== '' && (int) $theirs < (int) $ours);

    return ['ok' => true, 'report' => $report];
}


function cm_restore_header_value(string $head, string $field): string
{
    return preg_match('/^-- ' . preg_quote($field, '/') . ':\s*(.+)$/m', $head, $m)
        ? trim($m[1])
        : '';
}














function cm_restore_statements(array $r): Generator
{
    $buf = '';
    $no  = 0;
    $strip = static function (string $sql): string {
        $sql = ltrim($sql);
        while (strncmp($sql, '--', 2) === 0) {
            $nl = strpos($sql, "\n");
            $sql = $nl === false ? '' : ltrim(substr($sql, $nl + 1));
        }
        return rtrim($sql);
    };
    while (true) {
        $chunk = ($r['next'])();
        if ($chunk === '') break;
        $buf .= $chunk;

        if (strlen($buf) > CM_RESTORE_MAX_STATEMENT + 8388608 && strpos($buf, ";\n") === false) {
            throw new RuntimeException('a single statement is larger than any backup this site writes');
        }


        while (($cut = strpos($buf, ";\n")) !== false) {
            $sql = $strip(substr($buf, 0, $cut + 1));
            $buf = substr($buf, $cut + 2);
            if ($sql === '' || $sql === ';') continue;
            yield [++$no, $sql];
        }
    }


    $rest = $strip($buf);
    if ($rest !== '' && $rest !== ';') yield [++$no, $rest];
}










function cm_restore_statement_problem(string $sql, array &$made, array &$dropped): string
{
    $name = '`([A-Za-z0-9_]{1,64})`';
    if (preg_match('/^SET NAMES utf8mb4;?$/', $sql)
        || preg_match('/^SET FOREIGN_KEY_CHECKS = [01];?$/', $sql)
        || preg_match("/^SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';?$/", $sql)) {
        return '';
    }
    if (preg_match('/^DROP TABLE IF EXISTS ' . $name . ';?$/', $sql, $m)) {
        $dropped[$m[1]] = true;
        return '';
    }
    $bare = preg_replace("/'(?:[^'\\\\]++|\\\\.)*+'/s", "''", $sql);
    if (!is_string($bare)) return 'could not be read as SQL';
    if (substr_count($bare, ';') > (str_ends_with($bare, ';') ? 1 : 0)) {
        return 'holds more than one statement';
    }
    if (preg_match('/^CREATE TABLE ' . $name . ' \(/', $bare, $m)) {

        $ddl = (string) preg_replace('/`[^`]*`/', '``', $bare);
        if (preg_match('/\b(SELECT|DEFINER|CONNECTION|DIRECTORY|TRIGGER|PROCEDURE|FUNCTION|EVENT)\b|\/\*|#/i', $ddl)) {
            return 'creates a table with options a backup never uses';
        }
        $made[$m[1]] = true;
        return '';
    }
    if (preg_match('/^INSERT INTO ' . $name . ' \(`[A-Za-z0-9_]{1,64}`(?:,`[A-Za-z0-9_]{1,64}`)*\) VALUES /', $bare, $m)) {
        if (!isset($made[$m[1]])) return 'puts rows into a table the file did not create';
        $values = str_replace(["''", 'NULL'], '', rtrim(substr($bare, strlen($m[0])), ';'));
        if (!preg_match('/^[(),]*$/', $values)) return 'carries something other than plain values';
        return '';
    }
    return 'is not something a backup writes';
}
















function cm_restore_run(string $path, array $phrase = [], ?PDO $into = null): array
{
    $r = cm_restore_plain_reader($path, $phrase);
    if (!$r['ok']) return ['ok' => false, 'statements' => 0, 'error' => $r['error']];








    @set_time_limit(0);
    ignore_user_abort(true);







    $made = []; $dropped = [];
    try {
        foreach (cm_restore_statements($r) as [$no, $sql]) {
            $why = cm_restore_statement_problem($sql, $made, $dropped);
            if ($why !== '') {
                return ['ok' => false, 'statements' => 0,
                        'error' => 'That file was refused before anything was changed: statement ' . $no
                                 . ' ' . $why . '. A backup made by this site only ever creates its tables and '
                                 . 'puts rows back, so this file was edited or made somewhere else.'];
            }
        }
    } catch (Throwable $ex) {
        return ['ok' => false, 'statements' => 0, 'error' => 'That file could not be read through: '
                 . $ex->getMessage() . '. Nothing has been changed.'];
    }
    foreach (array_keys($dropped) as $t) {
        if (!isset($made[$t])) {
            return ['ok' => false, 'statements' => 0,
                    'error' => 'That file was refused before anything was changed: it removes a table ("' . $t
                             . '") without putting it back.'];
        }
    }
    $r = cm_restore_plain_reader($path, $phrase);
    if (!$r['ok']) return ['ok' => false, 'statements' => 0, 'error' => $r['error']];

    $pdo = $into ?? cm_db();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $count = 0;
    $line  = 0;

    try {
        foreach (cm_restore_statements($r) as [$no, $sql]) {
            $line = $no;
            $pdo->exec($sql);
            $count++;
        }
    } catch (Throwable $ex) {




        try { $pdo->exec('SET FOREIGN_KEY_CHECKS = 1'); } catch (Throwable $ignored) {}
        return ['ok' => false, 'statements' => $count,
                'error' => 'The restore stopped after ' . $count . ' statements, at line ' . $line . ': '
                         . $ex->getMessage() . ' — the database is now PART WAY through the backup and this '
                         . 'cannot be undone from here. Restore another copy of the file, or the backup you '
                         . 'took before starting.'];
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    return ['ok' => true, 'statements' => $count, 'at' => cm_now()];
}













function cm_restore_vault_snapshot(?PDO $into = null): array
{
    $pdo = $into ?? cm_db();
    $cols = cm_backup_secret_columns()['client_credential'] ?? [];
    if (!$cols) return [];
    try {
        $rows = $pdo->query('SELECT c.asset_id, a.client_id, c.username, c.' . implode(', c.', $cols)
                          . ' FROM client_credential c JOIN client_asset a ON a.id = c.asset_id')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $plain = [];
        foreach ($cols as $c) {
            $stored = (string) ($r[$c] ?? '');
            if ($stored === '') continue;
            $v = cm_decrypt($stored);
            if ($v !== '') $plain[$c] = $v;
        }
        if ($plain) $out[] = ['asset_id' => (int) $r['asset_id'], 'client_id' => (int) $r['client_id'],
                              'username' => (string) $r['username'], 'plain' => $plain];
    }
    return $out;
}



function cm_restore_encrypt_with(string $plain, string $key): string
{
    $iv  = random_bytes(12);
    $tag = '';
    $ct  = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($ct === false) throw new RuntimeException('Encryption failed.');
    return 'v1:' . base64_encode($iv . $tag . $ct);
}









function cm_restore_vault_putback(array $snapshot, ?string $key = null, ?PDO $into = null): array
{
    $pdo = $into ?? cm_db();
    $kept = 0; $lost = 0;
    foreach ($snapshot as $s) {
        try {
            $st = $pdo->prepare('SELECT c.* FROM client_credential c JOIN client_asset a ON a.id = c.asset_id
                                  WHERE c.asset_id = ? AND a.client_id = ? AND c.username = ?');
            $st->execute([$s['asset_id'], $s['client_id'], $s['username']]);
            $now = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $ex) {
            $now = false;
        }
        if (!$now) { $lost++; continue; }
        $set = []; $args = [];
        foreach ($s['plain'] as $col => $plain) {
            if ((string) ($now[$col] ?? '') !== '') continue;
            $set[] = $col . ' = ?';
            $args[] = $key !== null ? cm_restore_encrypt_with($plain, $key) : cm_encrypt($plain);
        }
        if ($set) {
            $args[] = $s['asset_id'];
            $pdo->prepare('UPDATE client_credential SET ' . implode(', ', $set) . ' WHERE asset_id = ?')->execute($args);
        }
        $kept++;
    }
    return ['kept' => $kept, 'lost' => $lost];
}



























function cm_restore_stream_plain(string $path, array $phrase, string $filename): int
{
    $r = cm_restore_plain_reader($path, $phrase);
    if (!$r['ok']) throw new RuntimeException($r['error']);




    @set_time_limit(0);

    while (ob_get_level() > 0) ob_end_clean();
    @ini_set('zlib.output_compression', '0');

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate');



    $bytes = 0;
    while (($chunk = ($r['next'])()) !== '') {
        echo $chunk;
        $bytes += strlen($chunk);
        flush();
    }
    return $bytes;
}















function cm_restore_appkey(string $source, bool $force = false): array
{
    $have = cm_app_key_source();

    if ($have['where'] !== 'none' && hash_equals($have['value'], $source)) {
        return ['ok' => true, 'action' => 'same',
                'note' => 'The settings key on this site is already the one the backup was made with, so stored credentials will read back normally.'];
    }
    if ($have['where'] === 'config') {



        return ['ok' => false, 'action' => 'config',
                'note' => 'This site takes its settings key from secret_key in config.php, and that is not this '
                        . 'file\'s to change. Stored credentials from the backup will read back as empty until '
                        . 'secret_key matches the site the backup came from. The value is on this screen — put it '
                        . 'in config.php by hand, or clear secret_key and let storage/appkey.php take over.'];
    }
    if ($have['where'] === 'file' && !$force) {
        return ['ok' => false, 'action' => 'exists',
                'note' => 'This site already has a DIFFERENT settings key in storage/appkey.php. Replacing it makes '
                        . 'the restored credentials readable and anything still encrypted under the old key '
                        . 'unreadable. Tick the box to replace it.'];
    }

    $dir = CM_ROOT . '/storage';
    if (!is_dir($dir)) @mkdir($dir, 0770, true);
    $file = $dir . '/appkey.php';
    if (@file_put_contents($file, "<?php return '" . addslashes($source) . "';\n") === false) {
        return ['ok' => false, 'action' => 'failed',
                'note' => 'storage/appkey.php could not be written, so stored credentials will read back as empty. '
                        . 'Check the folder is writable and try again, or paste the value into config.php as secret_key.'];
    }
    @chmod($file, 0600);
    return ['ok' => true, 'action' => 'written',
            'note' => 'The settings key from the backup has been written to storage/appkey.php, so stored credentials read back normally.'];
}
