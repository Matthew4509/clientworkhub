<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0




















declare(strict_types=1);

if (!defined('CM_ROOT')) { http_response_code(404); exit; }

require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/schedule.php';













function cm_appointment_kinds(): array
{
    $raw = trim((string) cm_setting('appt.kinds', ''));
    if ($raw === '') {
        return [
            'Client meeting'           => true,
            'Pickup kids from school'  => false,
            'Dentist'                  => false,
            'Doctor'                   => false,
        ];
    }

    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $work = true;
        if (str_contains($line, '|')) {
            [$line, $flag] = array_map('trim', explode('|', $line, 2));
            $work = !in_array(strtolower($flag), ['off', 'not work', 'no', 'personal'], true);
        }
        if ($line !== '') $out[mb_substr($line, 0, 60)] = $work;
    }
    return $out ?: ['Client meeting' => true];
}



function cm_appointment_kind_is_work(string $kind): bool
{
    return cm_appointment_kinds()[$kind] ?? true;
}


function cm_weekdays(): array
{
    return [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu',
            5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
}





function cm_repeat_horizons(): array
{
    return [
        '4'  => ['label' => 'A month ahead',      'blurb' => 'Fewest rows. Good if plans change often.'],
        '12' => ['label' => 'Three months ahead', 'blurb' => 'The default, and enough to plan a term around.'],
        '26' => ['label' => 'Six months ahead',   'blurb' => ''],
        '52' => ['label' => 'A year ahead',       'blurb' => 'For something that genuinely never changes.'],
    ];
}


















function cm_appointment_state(?array $series = null): array
{
    $series ??= cm_appointment_series();
    $weeks   = cm_appointment_repeat_weeks();
    $horizon = mb_strtolower((string) (cm_repeat_horizons()[(string) $weeks]['label'] ?? ($weeks . ' weeks ahead')));

    if (!$series) {
        return ['tone' => 'unset',
                'line' => 'Nothing repeats in your diary. A repeating appointment becomes real '
                        . 'entries, one per day you tick, so each can be moved on its own.'];
    }

    $n = count($series);
    $upcoming = 0;
    foreach ($series as $s) $upcoming += (int) ($s['upcoming'] ?? 0);

    return ['tone' => 'ok',
            'line' => $n . ' repeating appointment' . ($n === 1 ? '' : 's') . ' — '
                    . $upcoming . ' ' . ($upcoming === 1 ? 'entry' : 'entries')
                    . ' still to come, written out ' . $horizon . '.'];
}


function cm_appointment_repeat_weeks(): int
{
    $w = (int) cm_setting('appt.repeat_weeks', '12');
    return max(1, min(52, $w ?: 12));
}








function cm_appointment_create(array $in): array
{
    $fail = static fn (string $m): array => ['ok' => false, 'error' => $m, 'made' => 0, 'first' => 0];

    $title = trim((string) ($in['title'] ?? ''));
    $kind  = trim((string) ($in['kind'] ?? ''));




    if ($title === '' && $kind === '') {
        return $fail('Pick what it is, or describe it.');
    }
    if ($title === '') $title = $kind;
    if (mb_strlen($title) > 160) return $fail('That description is too long.');

    $date = trim((string) ($in['date'] ?? ''));















    $tidyTime = static function (string $v): string {
        $v = trim($v);
        if (preg_match('/^(\d{1,2})(\d{2})$/', $v, $m)) {
            $h = (int) $m[1]; $i = (int) $m[2];
            if ($h <= 23 && $i <= 59) return sprintf('%d:%02d', $h, $i);
        }
        return $v;
    };
    $from = $tidyTime((string) ($in['from'] ?? ''));
    $to   = $tidyTime((string) ($in['to'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
        || !preg_match('/^\d{1,2}:\d{2}$/', $from) || !preg_match('/^\d{1,2}:\d{2}$/', $to)) {
        return $fail('An appointment needs its day, a start and an end (like 13:00 to 14:00).');
    }
    if (cm_hm_minutes($to) <= cm_hm_minutes($from)) {
        return $fail('The end time is not after the start time.');
    }

    $clientId = (int) ($in['client_id'] ?? 0);
    if ($clientId > 0 && !cm_client($clientId)) $clientId = 0;


    $isWork = array_key_exists('is_work', $in)
        ? (bool) $in['is_work']
        : ($kind !== '' ? cm_appointment_kind_is_work($kind) : true);

    $clamp = static fn ($v): int => max(0, min(480, (int) $v));
    $before = $clamp($in['buffer_before'] ?? 0);
    $after  = $clamp($in['buffer_after'] ?? 0);


    $days = [];
    foreach ((array) ($in['repeat_days'] ?? []) as $d) {
        $d = (int) $d;
        if ($d >= 1 && $d <= 7) $days[$d] = true;
    }

    $dates = [$date];
    if ($days) {










        $weeks  = cm_appointment_repeat_weeks();
        $cursor = new DateTimeImmutable($date);
        $limit  = $cursor->modify('+' . $weeks . ' weeks');
        $dates  = [];
        $walk   = $cursor;
        while ($walk <= $limit) {
            if (isset($days[(int) $walk->format('N')])) $dates[] = $walk->format('Y-m-d');
            $walk = $walk->modify('+1 day');
        }
        if (!$dates) {
            return $fail('None of the days you ticked fall inside the repeat window.');
        }
        $dates = array_values(array_unique($dates));
        sort($dates);
    }

    $made = 0; $first = 0;
    foreach ($dates as $d) {
        try {
            $id = cm_insert(
                'INSERT INTO appointment (title, kind, client_id, on_date, start_time, end_time,
                                          buffer_before, buffer_after, series_id, is_work, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$title, $kind, $clientId > 0 ? $clientId : null, $d,
                 $from . ':00', $to . ':00', $before, $after,
                 $first > 0 ? $first : null, $isWork ? 1 : 0, cm_now()]);
        } catch (Throwable $ex) {

            continue;
        }
        $made++;
        if ($first === 0) {
            $first = $id;



            if (count($dates) > 1) {
                cm_q('UPDATE appointment SET series_id = ? WHERE id = ?', [$first, $first]);
            }
        }
    }

    if ($made === 0) return $fail('That appointment could not be saved.');

    if ($clientId > 0) {
        cm_audit('appointment.created', 'client', $clientId,
                 '"' . $title . '" ' . date('j M', strtotime($date)) . ' ' . $from . '–' . $to
                 . ($made > 1 ? ' (+' . ($made - 1) . ' repeats)' : ''));
    }
    return ['ok' => true, 'error' => '', 'made' => $made, 'first' => $first];
}








function cm_appointment_remove(int $id, bool $wholeSeries = false): int
{
    $a = cm_row('SELECT * FROM appointment WHERE id = ?', [$id]);
    if (!$a) return 0;

    if (!$wholeSeries || empty($a['series_id'])) {
        cm_q('DELETE FROM appointment WHERE id = ?', [$id]);
        return 1;
    }
    $n = (int) cm_val('SELECT COUNT(*) FROM appointment WHERE series_id = ? AND on_date >= ?',
                      [(int) $a['series_id'], date('Y-m-d')]);
    cm_q('DELETE FROM appointment WHERE series_id = ? AND on_date >= ?',
         [(int) $a['series_id'], date('Y-m-d')]);
    return $n;
}









function cm_appointment_series(): array
{
    try {
        $rows = cm_all(
            "SELECT series_id, title, kind, start_time, end_time,
                    buffer_before, buffer_after,
                    COUNT(*)      AS upcoming,
                    MAX(on_date)  AS last_on,
                    GROUP_CONCAT(DISTINCT DAYOFWEEK(on_date) ORDER BY DAYOFWEEK(on_date)) AS dows
             FROM appointment
             WHERE series_id IS NOT NULL AND on_date >= ?
             GROUP BY series_id, title, kind, start_time, end_time, buffer_before, buffer_after
             ORDER BY start_time, title", [date('Y-m-d')]);
    } catch (Throwable $ex) {
        return [];
    }

    $names = cm_weekdays();
    foreach ($rows as &$r) {



        $out = [];
        foreach (explode(',', (string) $r['dows']) as $d) {
            $n = ((int) $d + 5) % 7 + 1;
            if (isset($names[$n])) $out[$n] = $names[$n];
        }
        ksort($out);
        $r['days'] = implode(', ', $out);
    }
    unset($r);
    return $rows;
}


function cm_appointment_series_remove(int $seriesId): int
{
    if ($seriesId <= 0) return 0;
    $n = (int) cm_val('SELECT COUNT(*) FROM appointment WHERE series_id = ? AND on_date >= ?',
                      [$seriesId, date('Y-m-d')]);
    cm_q('DELETE FROM appointment WHERE series_id = ? AND on_date >= ?',
         [$seriesId, date('Y-m-d')]);
    return $n;
}







function cm_appointment_blocked_minutes(array $a): int
{
    $mins = cm_hm_minutes(substr((string) $a['end_time'], 0, 5))
          - cm_hm_minutes(substr((string) $a['start_time'], 0, 5));
    return max(0, $mins) + (int) ($a['buffer_before'] ?? 0) + (int) ($a['buffer_after'] ?? 0);
}


function cm_appointment_buffer_note(array $a): string
{
    $b = (int) ($a['buffer_before'] ?? 0);
    $f = (int) ($a['buffer_after'] ?? 0);
    if ($b === 0 && $f === 0) return '';
    if ($b > 0 && $f > 0) return 'plus ' . $b . 'm before and ' . $f . 'm after';
    return $b > 0 ? 'plus ' . $b . 'm before' : 'plus ' . $f . 'm after';
}
