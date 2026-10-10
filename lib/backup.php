<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0








































declare(strict_types=1);











if (!defined('CM_ROOT')) { http_response_code(404); exit; }


require_once CM_LIB . '/format.php';


require_once CM_LIB . '/migrate.php';




require_once CM_LIB . '/seal.php';
















const CM_BACKUP_MARKER = '-- @clientmanager-backup 1';


function cm_backup_tables(): array
{
    $out = [];
    foreach (cm_all('SHOW TABLES') as $row) {
        $out[] = (string) array_values($row)[0];
    }
    sort($out, SORT_STRING);
    return $out;
}








function cm_backup_filename(bool $gzip = false): string
{
    $schema = cm_migration_number(cm_schema_version());
    return 'clientworkhub-' . date('Y-m-d-Hi')
         . ($schema !== '' ? '-schema' . $schema : '')
         . '.sql' . ($gzip ? '.gz' : '');
}













function cm_backup_can_gzip(): bool
{
    return function_exists('deflate_init') && function_exists('deflate_add');
}











function cm_backup_sealed_filename(): string
{
    $schema = cm_migration_number(cm_schema_version());
    return 'clientworkhub-' . date('Y-m-d-Hi')
         . ($schema !== '' ? '-schema' . $schema : '')
         . CM_SEAL_EXT;
}














const CM_BACKUP_APPKEY_MARKER = '-- @clientmanager-appkey ';
































function cm_backup_secret_columns(): array
{
    return [
        'client_credential' => ['password_enc', 'notes_enc', 'recovery_enc'],
    ];
}










const CM_BACKUP_PASSWORDS_IN  = 'included';
const CM_BACKUP_PASSWORDS_OUT = 'not included';


function cm_backup_single_key(PDO $pdo, string $quotedTable): ?string
{
    try {
        $keys = $pdo->query("SHOW KEYS FROM {$quotedTable} WHERE Key_name = 'PRIMARY'")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {
        return null;
    }
    return count($keys) === 1 ? (string) $keys[0]['Column_name'] : null;
}


function cm_backup_page(PDO $pdo, string $quotedTable, string $pk, $after, int $size): PDOStatement
{
    $col = '`' . str_replace('`', '``', $pk) . '`';
    if ($after === null) {
        return $pdo->query("SELECT * FROM {$quotedTable} ORDER BY {$col} LIMIT " . $size);
    }
    $st = $pdo->prepare("SELECT * FROM {$quotedTable} WHERE {$col} > ? ORDER BY {$col} LIMIT " . $size);
    $st->execute([$after]);
    return $st;
}


























function cm_backup_stream(bool $gzip = false, ?array $seal = null, bool $withPasswords = false): int
{
    $pdo    = cm_db();
    $tables = cm_backup_tables();
    $bytes  = 0;



    $redact = $withPasswords ? [] : cm_backup_secret_columns();










    @set_time_limit(0);

    $deflate = $gzip && cm_backup_can_gzip()
        ? deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6])
        : null;





    $sink = $seal !== null
        ? $seal['write']
        : static function (string $s) use (&$bytes): void {
            echo $s;
            $bytes += strlen($s);
            flush();
        };

    $emit = static function (string $s) use ($deflate, $sink): void {
        if ($deflate !== null) {
            $s = deflate_add($deflate, $s, ZLIB_NO_FLUSH);
            if ($s === '') return;
        }
        $sink($s);
    };

    $emit(CM_BACKUP_MARKER . "\n"
        . "-- Client Work Hub database backup\n"
        . '-- taken:    ' . cm_now() . " (server time)\n"
        . '-- site:     ' . cm_base_url() . "\n"
        . '-- app:      ' . (cm_app_version() ?: 'unknown') . "\n"
        . '-- schema:   ' . (cm_schema_version() ?: 'none applied') . "\n"
        . '-- tables:   ' . count($tables) . "\n"





        . '-- passwords: ' . ($withPasswords ? CM_BACKUP_PASSWORDS_IN : CM_BACKUP_PASSWORDS_OUT) . "\n"
        . "--\n"
        . "-- Restore with:  mysql -u USER -p DATABASE < this-file.sql\n"
        . "-- or import it through phpMyAdmin, which is what most hosts offer.\n"
        . "--\n"
        . "-- IT CONTAINS PERSONAL DATA AND PASSWORD HASHES. Treat it as you\n"
        . "-- would the database itself.\n"
        . ($withPasswords
            ? "--\n"
            . "-- YOUR CLIENTS' STORED PASSWORDS ARE IN THIS FILE, because the box\n"
            . "-- was ticked. They are encrypted with a key kept OUTSIDE the\n"
            . "-- database, so they are not readable from this file alone.\n"
            . ($seal !== null
                ? "-- THAT KEY TRAVELS INSIDE THIS SEALED FILE, a few lines below, so\n"
                . "-- this file IS enough. It is only as safe as its four words.\n"
                : '')
            : "--\n"
            . "-- YOUR CLIENTS' STORED PASSWORDS ARE NOT IN THIS FILE. Every saved\n"
            . "-- password, secret note and recovery code comes back EMPTY if this\n"
            . "-- backup is restored. That is the default; the box on the backup\n"
            . "-- screen is what puts them in.\n")
        . "\n"
        . "SET NAMES utf8mb4;\n"
        . "SET FOREIGN_KEY_CHECKS = 0;\n"
        . "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");






    if ($seal !== null) {
        $src = cm_app_key_source();
        if ($src['where'] !== 'none') {
            $emit("--\n"
                . "-- The key that stored credentials are encrypted with travels INSIDE this\n"
                . "-- sealed file, because without it a restore brings back every PayPal, Stripe,\n"
                . "-- Google and mail password as an empty string and says nothing.\n"
                . CM_BACKUP_APPKEY_MARKER . base64_encode($src['value']) . "\n"
                . "--\n\n");
        }
    }

    foreach ($tables as $table) {
        $q = '`' . str_replace('`', '``', $table) . '`';

        $create = cm_row("SHOW CREATE TABLE {$q}");
        $ddl    = (string) ($create['Create Table'] ?? $create['Create View'] ?? '');

        $emit("--\n-- {$table}\n--\n"
            . "DROP TABLE IF EXISTS {$q};\n"
            . $ddl . ";\n\n");








        $pk = cm_backup_single_key($pdo, $q);
        $pageSize = 2000;
        $after = null;
        $pageRows = 0;
        $st = $pk === null
            ? $pdo->query("SELECT * FROM {$q}")
            : cm_backup_page($pdo, $q, $pk, null, $pageSize);
        $batch = [];
        $len   = 0;
        $cols  = null;









        $skip  = array_flip($redact[$table] ?? []);

        $flushBatch = static function () use (&$batch, &$len, $emit, $q, &$cols): void {
            if (!$batch) return;







            $emit('INSERT INTO ' . $q . ' (' . $cols . ') VALUES ' . implode(',', $batch) . ";\n");
            $batch = [];
            $len   = 0;
        };

        while (true) {
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                if ($pk === null || $pageRows < $pageSize) break;
                $st->closeCursor();
                $st = cm_backup_page($pdo, $q, $pk, $after, $pageSize);
                $pageRows = 0;
                continue;
            }
            $pageRows++;
            if ($pk !== null) $after = $row[$pk];
            if ($cols === null) {
                $cols = implode(',', array_map(
                    static fn(string $c) => '`' . str_replace('`', '``', $c) . '`',
                    array_keys($row)
                ));
            }
            $vals = [];
            foreach ($row as $col => $v) {
                $vals[] = ($v === null || isset($skip[$col]))
                    ? 'NULL' : $pdo->quote((string) $v);
            }
            $tuple  = '(' . implode(',', $vals) . ')';
            $batch[] = $tuple;
            $len    += strlen($tuple);



            if ($len > 262144 || count($batch) >= 200) $flushBatch();
        }
        $flushBatch();
        $emit("\n");
    }

    $emit("SET FOREIGN_KEY_CHECKS = 1;\n"
        . '-- end of backup, ' . count($tables) . " tables\n");





    if ($deflate !== null) {
        $tail = deflate_add($deflate, '', ZLIB_FINISH);
        if ($tail !== '') $sink($tail);
    }






    if ($seal !== null) {
        $seal['finish']();
        return $seal['bytes']();
    }

    return $bytes;
}








function cm_backup_taken_at(): ?int
{
    $t = $_SESSION['cm_backup_at'] ?? null;
    return is_int($t) ? $t : null;
}

function cm_backup_mark_taken(int $bytes): void
{
    $_SESSION['cm_backup_at']    = time();
    $_SESSION['cm_backup_bytes'] = $bytes;
}















const CM_SEAL_HOLD_SECONDS = 1800;


function cm_seal_pending(): ?array
{
    $held = $_SESSION['cm_seal_hold'] ?? null;
    if (!is_array($held) || !isset($held['phrase'], $held['at'])) return null;
    if (!is_array($held['phrase']) || count($held['phrase']) !== CM_SEAL_WORDS) return null;


    if (time() - (int) $held['at'] > CM_SEAL_HOLD_SECONDS) { cm_seal_forget(); return null; }
    return $held;
}









function cm_seal_hold(array $phrase, bool $withPasswords = false): void
{
    $_SESSION['cm_seal_hold'] = [
        'phrase' => array_values($phrase), 'at' => time(), 'confirmed' => false,
        'passwords' => $withPasswords,
    ];
}


function cm_seal_confirm(): void
{
    if (isset($_SESSION['cm_seal_hold']) && is_array($_SESSION['cm_seal_hold'])) {
        $_SESSION['cm_seal_hold']['confirmed'] = true;
    }
}

function cm_seal_forget(): void
{
    unset($_SESSION['cm_seal_hold']);
}


function cm_backup_size_label(int $bytes): string
{


    return cm_bytes_label($bytes);
}
