<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0






declare(strict_types=1);











if (!defined('CM_ROOT')) { http_response_code(404); exit; }


function cm_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = (string) cm_config('db.host', 'localhost');
    $port = (int) cm_config('db.port', 3306);
    $name = (string) cm_config('db.name', '');
    $user = (string) cm_config('db.user', '');
    $pass = (string) cm_config('db.pass', '');
    $charset = (string) cm_config('db.charset', 'utf8mb4');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,


            PDO::ATTR_EMULATE_PREPARES   => false,


            (defined('Pdo\Mysql::ATTR_MULTI_STATEMENTS') ? constant('Pdo\Mysql::ATTR_MULTI_STATEMENTS')
                                                         : PDO::MYSQL_ATTR_MULTI_STATEMENTS) => false,
        ]);
    } catch (PDOException $ex) {

        error_log('Client Work Hub DB connect failed: ' . $ex->getMessage());
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        exit(cm_is_dev()
            ? "Database connection failed: " . $ex->getMessage() . "\n"
            : "The site is temporarily unavailable. Please try again shortly.\n");
    }

    return $pdo;
}


function cm_q(string $sql, array $params = []): PDOStatement
{
    $st = cm_db()->prepare($sql);
    $st->execute($params);
    return $st;
}


function cm_row(string $sql, array $params = []): ?array
{
    $row = cm_q($sql, $params)->fetch();
    return $row === false ? null : $row;
}


function cm_all(string $sql, array $params = []): array
{
    return cm_q($sql, $params)->fetchAll();
}


function cm_val(string $sql, array $params = [])
{
    $v = cm_q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}


function cm_insert(string $sql, array $params = []): int
{
    cm_q($sql, $params);
    return (int) cm_db()->lastInsertId();
}


function cm_audit(string $action, string $targetType = '', ?int $targetId = null, string $note = ''): void
{
    try {
        $actor = cm_current_user();
        cm_insert(
            'INSERT INTO audit_log (actor_email, action, target_type, target_id, note, created_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$actor['email'] ?? '', $action, $targetType, $targetId, $note, cm_now()]
        );
    } catch (Throwable $ex) {
        error_log('audit_log write failed: ' . $ex->getMessage());
    }
}
