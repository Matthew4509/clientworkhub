<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0











declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }




require_once CM_LIB . '/clients.php';


require_once CM_LIB . '/format.php';


function cm_day_keys(): array
{
    return ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday',
            'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
}











function cm_workdays(): array
{
    require_once CM_LIB . '/workday.php';
    $fromSchedules = [];
    foreach (cm_workday_schedules() as $s) {
        foreach (array_keys($s['days']) as $n) {

            $fromSchedules[strtolower(date('D', strtotime('Sunday +' . $n . ' day')))] = true;
        }
    }
    if ($fromSchedules) return array_keys($fromSchedules);

    $raw = trim((string) cm_setting('workday.days', ''));
    if ($raw === '') return [];
    $out = [];
    foreach (explode(',', $raw) as $d) {
        $d = strtolower(trim($d));
        if (isset(cm_day_keys()[$d])) $out[] = $d;
    }
    return $out;
}

function cm_is_workday(string $date): bool
{
    $days = cm_workdays();
    if (!$days) return true;
    return in_array(strtolower(date('D', (int) strtotime($date))), $days, true);
}


function cm_workday_on_or_after(string $date): string
{
    $d = new DateTimeImmutable($date);
    for ($i = 0; $i < 14; $i++) {
        if (cm_is_workday($d->format('Y-m-d'))) return $d->format('Y-m-d');
        $d = $d->modify('+1 day');
    }
    return $date;
}


function cm_workday_after(string $date): string
{
    return cm_workday_on_or_after((new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d'));
}












function cm_days_between_inclusive(string $start, string $end): int
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) return 0;
    $utc = new DateTimeZone('UTC');
    $a = DateTimeImmutable::createFromFormat('!Y-m-d', $start, $utc);
    $b = DateTimeImmutable::createFromFormat('!Y-m-d', $end, $utc);
    if (!$a || !$b || $b < $a) return 0;
    return (int) $a->diff($b)->days + 1;
}

function cm_add_workdays(string $start, int $n): string
{
    $d = cm_workday_on_or_after($start);
    for ($i = 1; $i < $n; $i++) {
        $d = cm_workday_after($d);
    }
    return $d;
}


function cm_workday_times(): array
{
    $out = [];
    foreach (['start', 'end', 'break_start', 'break_end'] as $k) {
        $v = trim((string) cm_setting('workday.' . $k, ''));
        $out[$k] = preg_match('/^\d{1,2}:\d{2}$/', $v) ? $v : '';
    }
    return $out;
}




















function cm_workday_end_effective(): string
{
    $t = cm_workday_times();
    if ($t['end'] === '') return '';

    require_once CM_LIB . '/capacity.php';
    if (!cm_overtime_extends_day()) return $t['end'];

    $extra = cm_capacity_overtime_minutes();
    if ($extra < 1) return $t['end'];




    $m = min(23 * 60 + 59, cm_hm_minutes($t['end']) + $extra);
    return sprintf('%d:%02d', intdiv($m, 60), $m % 60);
}


function cm_workday_overtime_minutes(): int
{
    $t = cm_workday_times();
    if ($t['end'] === '') return 0;
    $eff = cm_workday_end_effective();
    return max(0, cm_hm_minutes($eff) - cm_hm_minutes($t['end']));
}



function cm_appointments_between(string $from, string $to): array
{








    return cm_all('SELECT a.*, c.first_name, c.last_name, c.company, c.email AS client_email,
                          c.vip AS client_vip
                   FROM appointment a LEFT JOIN client c ON c.id = a.client_id
                   WHERE a.on_date BETWEEN ? AND ?
                   ORDER BY a.on_date, a.start_time', [$from, $to]);
}









function cm_next_boundary(?string $now = null, ?string $date = null): ?array
{
    $date = $date ?? date('Y-m-d');
    $now  = $now ?? date('H:i');
    $nowM = cm_hm_minutes($now);

    $candidates = [];
    $times = cm_workday_times();





    $end = cm_workday_end_effective();
    if ($end !== '' && cm_hm_minutes($end) > $nowM) {
        $candidates[] = ['at' => $end,
                         'what' => cm_workday_overtime_minutes() > 0
                                 ? 'the end of your day, overtime included'
                                 : 'the end of your workday'];
    }



    require_once CM_LIB . '/workday.php';
    foreach (cm_workday_breaks_for(date('Y-m-d')) as $b) {
        if ($b['s'] > $nowM) {
            $candidates[] = ['at' => sprintf('%d:%02d', intdiv($b['s'], 60), $b['s'] % 60),
                             'what' => mb_strtolower($b['label'])];
        }
    }
    foreach (cm_appointments_between($date, $date) as $a) {
        $t = substr((string) $a['start_time'], 0, 5);
        if (cm_hm_minutes($t) > $nowM) {
            $who = $a['client_id']
                ? ' with ' . cm_client_display_name(['id' => $a['client_id'],
                      'first_name' => $a['first_name'], 'last_name' => $a['last_name'],
                      'company' => $a['company'], 'email' => $a['client_email']])
                : '';
            $candidates[] = ['at' => $t, 'what' => '“' . (string) $a['title'] . '”' . $who];
        }
    }
    if (!$candidates) return null;

    usort($candidates, static fn($x, $y) => cm_hm_minutes($x['at']) <=> cm_hm_minutes($y['at']));
    $next = $candidates[0];
    return ['minutes' => cm_hm_minutes($next['at']) - $nowM,
            'at' => $next['at'], 'what' => $next['what']];
}

function cm_hm_minutes(string $hm): int
{
    [$h, $m] = array_pad(explode(':', $hm), 2, '0');
    return (int) $h * 60 + (int) $m;
}




























function cm_occurrence_minutes(array $row): int
{
    $own = (int) ($row['est_minutes'] ?? 0);
    if ($own > 0) return $own;

    return (int) ($row['action_minutes'] ?? 0);
}




















function cm_occurrence_parent_minutes_sql(string $act = 'a', string $job = 'j'): string
{
    return "COALESCE({$act}.est_minutes, {$job}.per_day_minutes)";
}








function cm_occurrence_minutes_sql(string $occ = 'o', string $act = 'a', string $job = 'j'): string
{
    return "CASE WHEN {$occ}.est_minutes > 0 THEN {$occ}.est_minutes"
         . " ELSE " . cm_occurrence_parent_minutes_sql($act, $job) . " END";
}









function cm_occurrence_required_sql(string $act = 'a'): string
{
    return "COALESCE({$act}.required, 1)";
}


function cm_occurrence_name_sql(string $act = 'a', string $job = 'j'): string
{
    return "COALESCE({$act}.name, {$job}.name)";
}





function cm_occurrence_parent_fields(string $act = 'a', string $job = 'j'): string
{
    return cm_occurrence_name_sql($act, $job) . " AS action_name, "
         . cm_occurrence_required_sql($act) . " AS required, "
         . "COALESCE({$act}.frequency, {$job}.frequency) AS frequency, "
         . cm_occurrence_parent_minutes_sql($act, $job) . " AS action_minutes";
}







function cm_suggest_fitting_task(array $queue, int $minutesAvailable): ?array
{
    foreach ($queue as $o) {
        $est = cm_occurrence_minutes($o);
        if ($est > 0 && $est <= $minutesAvailable) return $o;
    }
    return null;
}



const CM_PLAN_DEFAULT_MINUTES = 30;


function cm_queue_for_day(string $date): array
{
    return cm_all(
        "SELECT o.*, " . cm_occurrence_parent_fields('a', 'j') . ",
                j.name AS job_name, j.priority, j.client_id,
                c.first_name, c.last_name, c.company, c.email AS client_email,
                /* `c.vip AS client_vip`, ALIASED -- see cm_work_queue().
                   WITHOUT THIS THE RAIL GOES GOLD TODAY AND PLAIN TOMORROW:
                   today's rail is fed by cm_work_queue(), which already
                   carries the flag, and only the other days come through
                   here. A marker that appears on one day and not the next is
                   worse than no marker, because it reads as an answer. */
                c.vip AS client_vip
         FROM action_occurrence o
         LEFT JOIN job_action a ON a.id = o.action_id
         JOIN job j ON j.id = o.job_id
         JOIN client c ON c.id = j.client_id
         WHERE o.status IN ('scheduled','in_progress') AND j.status = 'active'
           AND o.scheduled_date = ?
         ORDER BY " . cm_occurrence_name_sql('a', 'j'), [$date]);
}




function cm_day_history(string $date): array
{
    return cm_all(
        "SELECT o.*, " . cm_occurrence_name_sql('a', 'j') . " AS action_name,
                j.name AS job_name, j.client_id,
                c.first_name, c.last_name, c.company, c.email AS client_email,
                c.vip AS client_vip   /* ALIASED -- see cm_work_queue() */
         FROM action_occurrence o
         LEFT JOIN job_action a ON a.id = o.action_id
         JOIN job j ON j.id = o.job_id
         JOIN client c ON c.id = j.client_id
         WHERE o.scheduled_date = ?
         ORDER BY o.status IN ('scheduled','in_progress') DESC, o.completed_at, "
         . cm_occurrence_name_sql('a', 'j'), [$date]);
}















function cm_day_plan(array $queue, ?string $now = null, ?string $date = null): array
{
    $date  = $date ?? date('Y-m-d');
    $now   = $now ?? date('H:i');
    $times = cm_workday_times();








    require_once CM_LIB . '/workday.php';
    $blocks = [];
    foreach (cm_workday_breaks_for($date ?? date('Y-m-d')) as $b) {
        $blocks[] = ['type' => 'break', 'label' => $b['label'],
                     's' => $b['s'], 'e' => $b['e']];
    }
    foreach (cm_appointments_between($date, $date) as $a) {
        $who = $a['client_id']
            ? cm_client_display_name(['id' => $a['client_id'], 'first_name' => $a['first_name'],
                  'last_name' => $a['last_name'], 'company' => $a['company'],
                  'email' => $a['client_email']])
            : '';
        $blocks[] = ['type' => 'appt', 'label' => (string) $a['title'], 'who' => $who,
                     'appt' => $a,
                     's' => cm_hm_minutes(substr((string) $a['start_time'], 0, 5)),
                     'e' => cm_hm_minutes(substr((string) $a['end_time'], 0, 5))];
    }
    usort($blocks, static fn($x, $y) => $x['s'] <=> $y['s']);











    $effEnd      = cm_workday_end_effective();
    $dayEnd      = $effEnd !== '' ? cm_hm_minutes($effEnd) : null;
    $standardEnd = $times['end'] !== '' ? cm_hm_minutes($times['end']) : null;
    $otMinutes   = cm_workday_overtime_minutes();

    $cursor = cm_hm_minutes($now);
    if ($times['start'] !== '' && $cursor < cm_hm_minutes($times['start'])) {
        $cursor = cm_hm_minutes($times['start']);
    }

    $fmt = static fn(int $m): string => sprintf('%02d:%02d', intdiv($m, 60) % 24, $m % 60);
    $plan = [];
    if ($times['start'] !== '') {
        $plan[] = ['type' => 'start', 'at' => $times['start'], 'label' => 'Start of day'];
    }



    $today = $date;
    $tasks = array_values(array_filter($queue, static fn($o) => (string) $o['scheduled_date'] <= $today));
    usort($tasks, static fn($x, $y) =>
        ((string) $y['status'] === 'in_progress') <=> ((string) $x['status'] === 'in_progress'));

    $bi = 0;

    while ($bi < count($blocks) && $blocks[$bi]['e'] <= $cursor) {
        $b = $blocks[$bi++];
        $plan[] = ['type' => $b['type'], 'at' => $fmt($b['s']), 'until' => $fmt($b['e']),
                   'label' => $b['label'], 'who' => $b['who'] ?? '', 'appt' => $b['appt'] ?? null,
                   'passed' => true];
    }

    foreach ($tasks as $t) {
        $est = cm_occurrence_minutes($t);
        $guessed = $est === 0;
        if ($guessed) $est = CM_PLAN_DEFAULT_MINUTES;


        while ($bi < count($blocks) && $cursor + $est > $blocks[$bi]['s']) {
            $b = $blocks[$bi++];
            if ($cursor < $b['s']) {



            }
            $plan[] = ['type' => $b['type'], 'at' => $fmt($b['s']), 'until' => $fmt($b['e']),
                       'label' => $b['label'], 'who' => $b['who'] ?? '', 'appt' => $b['appt'] ?? null];
            $cursor = max($cursor, $b['e']);
        }

        $plan[] = ['type' => 'task', 'at' => $fmt($cursor), 'row' => $t, 'est' => $est,
                   'guessed' => $guessed,
                   'after_hours' => $dayEnd !== null && $cursor + $est > $dayEnd,



                   'overtime' => $otMinutes > 0 && $standardEnd !== null
                                 && $cursor + $est > $standardEnd
                                 && $dayEnd !== null && $cursor + $est <= $dayEnd];
        $cursor += $est;
    }


    while ($bi < count($blocks)) {
        $b = $blocks[$bi++];
        $plan[] = ['type' => $b['type'], 'at' => $fmt($b['s']), 'until' => $fmt($b['e']),
                   'label' => $b['label'], 'who' => $b['who'] ?? '', 'appt' => $b['appt'] ?? null];
    }
    if ($times['end'] !== '') {
        if ($otMinutes > 0) {



            $plan[] = ['type' => 'end', 'at' => $times['end'],
                       'label' => 'Standard day ends — overtime from here'];
            $plan[] = ['type' => 'end', 'at' => $effEnd,
                       'label' => 'End of day, with ' . cm_minutes_label($otMinutes) . ' overtime'];
        } else {
            $plan[] = ['type' => 'end', 'at' => $times['end'], 'label' => 'End of day'];
        }
    }
    return $plan;
}
