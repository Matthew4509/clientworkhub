<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0































declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }

function cm_migration_dir(): string
{
    return CM_ROOT . '/migrations';
}






function cm_migration_ensure_table(): void
{
    cm_db()->exec(
        'CREATE TABLE IF NOT EXISTS schema_migration (
            filename   VARCHAR(190) NOT NULL,
            applied_at DATETIME     NOT NULL,
            PRIMARY KEY (filename)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}









function cm_migrations_applied(): array
{
    try {
        $out = [];
        foreach (cm_all('SELECT filename, applied_at FROM schema_migration ORDER BY filename') as $r) {
            $out[(string) $r['filename']] = (string) $r['applied_at'];
        }
        return $out;
    } catch (Throwable $ex) {
        return [];
    }
}


function cm_migration_files(): array
{
    $files = glob(cm_migration_dir() . '/*.sql') ?: [];
    $names = array_map('basename', $files);
    sort($names, SORT_STRING);
    return $names;
}


function cm_migrations_pending(): array
{
    $applied = cm_migrations_applied();
    return array_values(array_filter(
        cm_migration_files(),
        static fn(string $n) => !isset($applied[$n])
    ));
}






function cm_schema_version(): string
{
    $applied = array_keys(cm_migrations_applied());
    if (!$applied) return '';
    sort($applied, SORT_STRING);
    return (string) end($applied);
}







function cm_migration_is_fresh_install(): bool
{
    try {
        return cm_all('SHOW TABLES') === [];
    } catch (Throwable $ex) {
        return false;
    }
}


function cm_migration_number(string $filename): string
{
    return preg_match('/^(\d+)/', $filename, $m) ? $m[1] : '';
}





function cm_app_version(): string
{
    return defined('CM_VERSION') ? CM_VERSION : '';
}


function cm_app_edition(): string
{
    return defined('CM_EDITION') ? CM_EDITION : '';
}


function cm_app_label(): string
{
    return trim(cm_app_edition() . ' ' . cm_app_version());
}






function cm_migration_state(): array
{
    $pending = cm_migrations_pending();
    return [
        'app'        => cm_app_version(),
        'schema'     => cm_schema_version(),
        'pending'    => $pending,
        'applied'    => count(cm_migrations_applied()),
        'up_to_date' => $pending === [],
    ];
}







function cm_migration_apply(string $filename): array
{




    $filename = basename($filename);
    if (!in_array($filename, cm_migration_files(), true)) {
        return [false, "There is no migration file called {$filename}."];
    }

    $sql = @file_get_contents(cm_migration_dir() . '/' . $filename);
    if ($sql === false) {
        return [false, "Could not read {$filename} — check the file is readable."];
    }

    cm_migration_ensure_table();

    try {




        foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') continue;
            cm_db()->exec($stmt);
        }
        cm_q('INSERT INTO schema_migration (filename, applied_at) VALUES (?, ?)',
             [$filename, cm_now()]);
        return [true, $filename];
    } catch (Throwable $ex) {





        return [false, $filename . ' failed: ' . $ex->getMessage()];
    }
}




















function cm_migration_lock(): bool
{
    try {
        return (int) cm_val("SELECT GET_LOCK('cm_migrate', 0)") === 1;
    } catch (Throwable $ex) {




        error_log('GET_LOCK unavailable: ' . $ex->getMessage());
        return true;
    }
}

function cm_migration_unlock(): void
{
    try {
        cm_val("SELECT RELEASE_LOCK('cm_migrate')");
    } catch (Throwable $ex) {

    }
}










function cm_migration_run_pending(): array
{
    if (!cm_migration_lock()) {
        return ['ok' => false, 'applied' => [], 'locked' => false,
                'error' => 'Another update is running right now. Wait for it to finish and reload this page — '
                         . 'do not start a second one.'];
    }












    @set_time_limit(0);
    @ignore_user_abort(true);

    $done = [];
    try {

        foreach (cm_migrations_pending() as $file) {
            [$ok, $msg] = cm_migration_apply($file);
            if (!$ok) {
                return ['ok' => false, 'applied' => $done, 'error' => $msg, 'locked' => true];
            }
            $done[] = $msg;
        }
    } finally {
        cm_migration_unlock();
    }

    return ['ok' => true, 'applied' => $done, 'error' => '', 'locked' => true];
}







function cm_migration_fail_note(): string
{
    return trim((string) cm_setting('migrate.last_failure', ''));
}

function cm_migration_fail_record(string $message, string $actor = ''): void
{




    try {
        cm_setting_set('migrate.last_failure', cm_now() . ' - ' . $message, $actor);
    } catch (Throwable $ex) {
        error_log('could not record migration failure: ' . $ex->getMessage());
    }
}

function cm_migration_fail_clear(): void
{
    try {
        cm_setting_clear('migrate.last_failure');
    } catch (Throwable $ex) {

    }
}























function cm_schema_tables(): array
{
    static $tables = null;
    if ($tables !== null) return $tables;
    $tables = [];
    try {
        foreach (cm_all('SHOW TABLES') as $row) {
            $tables[strtolower((string) reset($row))] = true;
        }
    } catch (Throwable $ex) {



        error_log('cm_schema_tables: ' . $ex->getMessage());
    }
    return $tables;
}

function cm_schema_has_table(string $table): bool
{
    return isset(cm_schema_tables()[strtolower($table)]);
}


function cm_schema_columns(string $table): array
{
    static $cache = [];
    $key = strtolower($table);
    if (isset($cache[$key])) return $cache[$key];
    $cols = [];
    if (cm_schema_has_table($table)) {
        try {



            foreach (cm_all('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`') as $row) {
                $cols[strtolower((string) ($row['Field'] ?? ''))] = true;
            }
        } catch (Throwable $ex) {
            error_log('cm_schema_columns(' . $table . '): ' . $ex->getMessage());
        }
    }
    return $cache[$key] = $cols;
}

function cm_schema_has_column(string $table, string $column): bool
{
    return isset(cm_schema_columns($table)[strtolower($column)]);
}









function cm_feature_requirements(): array
{
    return [
        'register' => [
            'label'  => 'Cash register',
            'since'  => '047_cash_register.sql',
            'tables' => [
                'register_sale'   => ['receipt_no', 'client_id', 'description', 'total_cents',
                                      'payment_method', 'amount_received_cents', 'change_cents'],
                'register_refund' => ['sale_id', 'amount_cents', 'reason', 'refund_method'],
            ],
        ],




        'property_templates' => [
            'label'  => 'Property templates',
            'since'  => '048_property_templates.sql',
            'tables' => ['property_template' => ['name', 'fields_json']],
        ],



        'work_summary' => [
            'label'  => 'Sharing a work summary with the client',
            'since'  => '052_work_summary_share.sql',
            'tables' => [
                'work_summary' => ['job_id', 'client_id', 'token_hash', 'token_enc',
                                   'heading', 'intro', 'snapshot', 'include_time',
                                   'created_at', 'created_by', 'expires_at', 'opened_at',
                                   'approved_at', 'signature', 'signed_name',
                                   'replied_at', 'feedback', 'revoked_at'],
            ],
        ],
        'work_access' => [
            'label'  => 'Work screens',
            'since'  => '050_work_screen_feeds.sql',
            'tables' => [
                'work_access' => ['name', 'username', 'password_hash', 'token_hash', 'token_enc',
                                  'status', 'screen_view', 'expires_at',
                                   'auth_required', 'public_heading', 'show_first_name',
                                  'show_job_name', 'show_task_name', 'show_estimate', 'show_status',
                                   'show_business_name', 'show_client_name',
                                  'highlight_vip', 'max_items'],
                'work_access_item' => ['access_id', 'item_type', 'item_id'],
            ],
        ],
    ];
}





function cm_feature_schema_missing(string $feature): array
{
    $spec = cm_feature_requirements()[$feature] ?? null;
    if ($spec === null) return [];

    $missing = [];
    foreach ($spec['tables'] as $table => $columns) {
        if (!cm_schema_has_table($table)) { $missing[$table] = ['*']; continue; }
        $gone = [];
        foreach ($columns as $c) if (!cm_schema_has_column($table, $c)) $gone[] = $c;
        if ($gone) $missing[$table] = $gone;
    }
    return $missing;
}

function cm_feature_ready(string $feature): bool
{
    return cm_feature_schema_missing($feature) === [];
}


function cm_feature_missing_label(array $missing): string
{
    $parts = [];
    foreach ($missing as $table => $cols) {
        $parts[] = $cols === ['*']
            ? 'table ' . $table . ' (missing)'
            : $table . '.' . implode(', ' . $table . '.', $cols);
    }
    return implode(' · ', $parts);
}
