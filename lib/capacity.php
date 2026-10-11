<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0

































declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }

require_once CM_LIB . '/format.php';
require_once CM_LIB . '/schedule.php';






function cm_capacity_modes(): array
{
    return [
        'off' => [
            'label' => 'Take everything',
            'blurb' => 'No limits. Every date offered is the nominal one and nothing is ever '
                     . 'refused for being busy. This is the default and it suits most people.',
        ],
        'extend' => [
            'label' => 'Take everything, but quote honest dates',
            'blurb' => 'Still never refuses work. When the days are full the start slides later '
                     . 'or the delivery stretches, so a seven-day package might be quoted as '
                     . (cm_home() ? 'fourteen, and the date you give the client says so.'
                                  : 'fourteen — and the client is told that before they buy.'),
        ],
        'restrict' => [
            'label' => 'Stop taking work when full',
            'blurb' => (cm_home() ? 'Says no to work' : 'Refuses bookings') . ' that will not fit inside your hours plus the overtime '
                     . 'allowance below. Only choose this when you actually want to turn work '
                     . 'away — it is the one setting here that can cost you a sale.',
        ],
    ];
}

function cm_capacity_mode(): string
{
    try { $m = (string) (cm_setting('capacity.mode', 'off') ?? 'off'); }
    catch (Throwable $ex) { return 'off'; }
    return isset(cm_capacity_modes()[$m]) ? $m : 'off';
}


function cm_capacity_overtime_minutes(): int
{
    try { $v = trim((string) (cm_setting('capacity.overtime_minutes', '') ?? '')); }
    catch (Throwable $ex) { return 0; }
    return ctype_digit($v) ? min(960, (int) $v) : 0;
}























function cm_overtime_extends_day(): bool
{
    try { $v = cm_setting('capacity.overtime_extends_day', ''); }
    catch (Throwable $ex) { return false; }
    return $v === '1' || $v === 1 || $v === true || $v === 'on';
}












function cm_day_capacity_minutes(string $date): ?int
{
    if (!cm_is_workday($date)) return 0;

    $t = cm_workday_times();
    if ($t['start'] === '' || $t['end'] === '') return null;

    $mins = cm_hm_minutes($t['end']) - cm_hm_minutes($t['start']);
    if ($t['break_start'] !== '' && $t['break_end'] !== '') {
        $mins -= max(0, cm_hm_minutes($t['break_end']) - cm_hm_minutes($t['break_start']));
    }
    return max(0, $mins) + cm_capacity_overtime_minutes();
}









function cm_day_load_minutes(string $date): int
{
    $mins = (int) cm_val(
        "SELECT COALESCE(SUM(" . cm_occurrence_minutes_sql('o', 'a', 'j') . "), 0)
           FROM action_occurrence o
           LEFT JOIN job_action a ON a.id = o.action_id
           JOIN job j ON j.id = o.job_id
          WHERE o.scheduled_date = ?
            AND o.status IN ('scheduled', 'in_progress')
            AND j.status = 'active'", [$date]);


    foreach (cm_appointments_between($date, $date) as $ap) {
        $start = (string) ($ap['start_time'] ?? '');
        $end   = (string) ($ap['end_time'] ?? '');
        if (preg_match('/^\d{1,2}:\d{2}/', $start) && preg_match('/^\d{1,2}:\d{2}/', $end)) {
            $mins += max(0, cm_hm_minutes(substr($end, 0, 5)) - cm_hm_minutes(substr($start, 0, 5)));
        }
    }
    return $mins;
}





function cm_day_free_minutes(string $date): ?int
{
    if (cm_capacity_mode() === 'off') return null;
    $cap = cm_day_capacity_minutes($date);
    if ($cap === null) return null;
    if ($cap === 0) return 0;
    return max(0, $cap - cm_day_load_minutes($date));
}





















function cm_capacity_plan(int $perDayMinutes, int $days, ?string $from = null): array
{
    $days = max(1, min(365, $days));
    $from = $from !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)
          ? $from : date('Y-m-d');



    $nominalStart = cm_workday_on_or_after($from);
    $nominalEnd   = cm_add_workdays($nominalStart, $days);

    $mode = cm_capacity_mode();
    if ($mode === 'off') {
        $dates = [];
        $d = $nominalStart;
        for ($i = 0; $i < $days; $i++) {
            $dates[] = $d;
            $d = cm_workday_after($d);
        }
        return ['ok' => true, 'start' => $nominalStart, 'end' => $nominalEnd, 'dates' => $dates,
                'nominal_end' => $nominalEnd, 'delayed' => false, 'extended' => false,
                'reason' => ''];
    }

    $dates = [];
    $day = $nominalStart;



    $guard = 0;
    while (count($dates) < $days && $guard++ < 520) {
        $free = cm_day_free_minutes($day);
        if ($free === null || $free >= $perDayMinutes) {
            $dates[] = $day;
        }
        $day = cm_workday_after($day);
    }

    if (count($dates) < $days) {
        return ['ok' => false, 'start' => '', 'end' => '', 'dates' => [],
                'nominal_end' => $nominalEnd, 'delayed' => false, 'extended' => false,
                'reason' => 'There is not enough room in the next two years of working days '
                          . 'for this at ' . cm_minutes_label($perDayMinutes) . ' a day.'];
    }

    $start = $dates[0];
    $end   = $dates[count($dates) - 1];
    $delayed  = $start !== $nominalStart;
    $extended = $end !== $nominalEnd;

    $reason = '';
    if ($delayed && $extended) {
        $reason = 'The first free day is ' . date('j M', strtotime($start))
                . ', and some days in between are already full.';
    } elseif ($delayed) {
        $reason = 'The first day with room is ' . date('j M', strtotime($start)) . '.';
    } elseif ($extended) {
        $reason = 'Some days in between are already full, so it finishes on '
                . date('j M', strtotime($end)) . ' rather than '
                . date('j M', strtotime($nominalEnd)) . '.';
    }

    return ['ok' => true, 'start' => $start, 'end' => $end, 'dates' => $dates,
            'nominal_end' => $nominalEnd, 'delayed' => $delayed, 'extended' => $extended,
            'reason' => $reason];
}









function cm_capacity_accepts(int $perDayMinutes, int $days, ?string $from = null): array
{
    $plan = cm_capacity_plan($perDayMinutes, $days, $from);
    if (cm_capacity_mode() !== 'restrict') {


        return ['accepted' => true, 'plan' => $plan];
    }
    return ['accepted' => $plan['ok'], 'plan' => $plan];
}





function cm_capacity_outlook(int $days = 21, ?string $from = null): array
{
    $d = $from !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-d');
    $out = [];
    for ($i = 0; $i < max(1, min(90, $days)); $i++) {
        $working = cm_is_workday($d);
        $cap = cm_day_capacity_minutes($d);
        $load = $working ? cm_day_load_minutes($d) : 0;
        $out[] = [
            'date' => $d,
            'working' => $working,
            'capacity' => $cap,
            'load' => $load,
            'free' => ($cap === null || !$working) ? null : max(0, $cap - $load),
        ];
        $d = date('Y-m-d', strtotime($d . ' +1 day'));
    }
    return $out;
}
