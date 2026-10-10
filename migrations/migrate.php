<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0















declare(strict_types=1);





if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

require dirname(__DIR__) . '/lib/bootstrap.php';
require CM_LIB . '/migrate.php';

$statusOnly = in_array('--status', $argv, true);

cm_migration_ensure_table();

$pending = cm_migrations_pending();
$applied = cm_migrations_applied();

if (!$pending) {
    echo 'Up to date — ' . count($applied) . " migration(s) already applied.\n";
    echo 'Schema ' . (cm_schema_version() ?: 'none') . ', app ' . (cm_app_version() ?: 'unknown') . ".\n";
    exit(0);
}

echo ($statusOnly ? "Would apply:\n" : "Applying:\n");
foreach ($pending as $name) {
    echo '  ' . $name . "\n";
}
if ($statusOnly) exit(0);

foreach ($pending as $name) {
    [$ok, $msg] = cm_migration_apply($name);
    if (!$ok) {




        cm_migration_fail_record($msg, 'cli');
        fwrite(STDERR, "  x {$msg}\n");
        fwrite(STDERR, "\nStopped. Restore your backup before trying again — the file above\n"
                     . "may have applied part-way, and re-running will fail on the same statement.\n");
        exit(1);
    }
    echo "  ok {$msg}\n";
}

cm_migration_fail_clear();
echo "Done. Schema is now " . cm_schema_version() . ".\n";
