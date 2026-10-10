<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0










































declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit("Not found\n"); }
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/reminders.php';

$dry = in_array('--dry-run', $argv, true);
$started = microtime(true);




$waiting = cm_followups_waiting();

if ($dry) {
    printf("dry run: %d follow-up%s due, %d would be sent this run (batch of %d)\n",
           $waiting, $waiting === 1 ? '' : 's',
           min($waiting, CM_FOLLOWUP_BATCH), CM_FOLLOWUP_BATCH);
    foreach (cm_followups_due() as $r) {
        printf("  due %s  %s\n", (string) $r['due_date'], (string) ($r['client_email'] ?? '(no address)'));
    }
    exit(0);
}

$sent = 0;
try {
    $sent = cm_followups_send_due();
} catch (Throwable $e) {


    fwrite(STDERR, 'cron failed: ' . $e->getMessage() . "\n");
    cm_audit('cron.failed', '', null, 'scheduled run failed: ' . $e->getMessage());
    exit(1);
}





cm_setting_set('cron.last_run', (string) time(), 'cron');
cm_setting_set('cron.last_sent', (string) $sent, 'cron');

$left = max(0, $waiting - $sent);
printf("sent %d of %d due (batch %d, %.2fs)%s\n",
       $sent, $waiting, CM_FOLLOWUP_BATCH, microtime(true) - $started,
       $left > 0 ? ", {$left} still waiting for the next run" : '');
exit(0);
