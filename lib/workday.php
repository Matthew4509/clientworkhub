<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0






























declare(strict_types=1);

if (!defined('CM_ROOT')) { http_response_code(404); exit; }

require_once CM_LIB . '/schedule.php';


function cm_workday_weekdays(): array
{
    return [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu',
            5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
}








function cm_workday_schedules(): array
{
    try {
        $rows = cm_all('SELECT * FROM workday ORDER BY sort, id');
    } catch (Throwable $ex) {
        return [];
    }

    if (!$rows) {
        if (cm_workday_seed_from_settings()) {
            $rows = cm_all('SELECT * FROM workday ORDER BY sort, id');
        } else {
            return [];
        }
    }

    foreach ($rows as &$r) {
        $r['days']   = cm_workday_day_list((string) $r['weekdays']);
        $r['breaks'] = cm_all('SELECT * FROM workday_break WHERE workday_id = ? ORDER BY starts, id',
                              [(int) $r['id']]);
    }
    unset($r);
    return $rows;
}


function cm_workday_day_list(string $csv): array
{
    $names = cm_workday_weekdays();
    $out = [];
    foreach (explode(',', $csv) as $d) {
        $d = (int) trim($d);
        if (isset($names[$d])) $out[$d] = $names[$d];
    }
    ksort($out);
    return $out;
}








function cm_workday_seed_from_settings(): bool
{
    $start = trim((string) cm_setting('workday.start', ''));
    $end   = trim((string) cm_setting('workday.end', ''));
    if (!preg_match('/^\d{1,2}:\d{2}$/', $start) || !preg_match('/^\d{1,2}:\d{2}$/', $end)) {
        return false;
    }

    $map  = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7];
    $days = [];
    foreach (explode(',', (string) cm_setting('workday.days', '')) as $k) {
        $k = strtolower(trim($k));
        if (isset($map[$k])) $days[] = $map[$k];
    }


    if (!$days) $days = [1, 2, 3, 4, 5, 6, 7];
    sort($days);

    $id = cm_insert(
        'INSERT INTO workday (label, weekdays, starts, ends, sort, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        ['Working day', implode(',', $days), $start . ':00', $end . ':00', 10, cm_now(), cm_now()]);

    $bs = trim((string) cm_setting('workday.break_start', ''));
    $be = trim((string) cm_setting('workday.break_end', ''));
    if (preg_match('/^\d{1,2}:\d{2}$/', $bs) && preg_match('/^\d{1,2}:\d{2}$/', $be)
        && cm_hm_minutes($be) > cm_hm_minutes($bs)) {
        cm_insert(
            'INSERT INTO workday_break (workday_id, label, starts, ends, flexible, sort)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$id, 'Break', $bs . ':00', $be . ':00', 1, 10]);
    }
    return true;
}







function cm_workday_for(string $date): ?array
{
    $n = (int) date('N', strtotime($date));
    foreach (cm_workday_schedules() as $s) {
        if (isset($s['days'][$n])) return $s;
    }
    return null;
}






function cm_workday_breaks_for(string $date): array
{
    $s = cm_workday_for($date);
    if (!$s) {


        $bs = trim((string) cm_setting('workday.break_start', ''));
        $be = trim((string) cm_setting('workday.break_end', ''));
        if (preg_match('/^\d{1,2}:\d{2}$/', $bs) && preg_match('/^\d{1,2}:\d{2}$/', $be)
            && cm_hm_minutes($be) > cm_hm_minutes($bs)) {
            return [['label' => 'Break', 's' => cm_hm_minutes($bs),
                     'e' => cm_hm_minutes($be), 'flexible' => true]];
        }
        return [];
    }

    $out = [];
    foreach ($s['breaks'] as $b) {
        $from = cm_hm_minutes(substr((string) $b['starts'], 0, 5));
        $to   = cm_hm_minutes(substr((string) $b['ends'], 0, 5));
        if ($to <= $from) continue;
        $out[] = ['label' => (string) $b['label'] !== '' ? (string) $b['label'] : 'Break',
                  's' => $from, 'e' => $to, 'flexible' => (int) $b['flexible'] === 1];
    }
    usort($out, static fn ($a, $b) => $a['s'] <=> $b['s']);
    return $out;
}


function cm_workday_hours_for(string $date): array
{
    $s = cm_workday_for($date);
    if ($s) {
        return ['start' => substr((string) $s['starts'], 0, 5),
                'end'   => substr((string) $s['ends'], 0, 5)];
    }
    $t = cm_workday_times();
    return ['start' => $t['start'], 'end' => $t['end']];
}




function cm_workday_save(int $id, array $in): string
{
    $label = mb_substr(trim((string) ($in['label'] ?? '')), 0, 60);
    $start = trim((string) ($in['starts'] ?? ''));
    $end   = trim((string) ($in['ends'] ?? ''));
    if (!preg_match('/^\d{1,2}:\d{2}$/', $start) || !preg_match('/^\d{1,2}:\d{2}$/', $end)) {
        return 'A working day needs a start and an end, like 9:00 and 17:00.';
    }
    if (cm_hm_minutes($end) <= cm_hm_minutes($start)) {
        return 'The day ends before it starts.';
    }

    $names = cm_workday_weekdays();
    $days = [];
    foreach ((array) ($in['weekdays'] ?? []) as $d) {
        $d = (int) $d;
        if (isset($names[$d])) $days[$d] = true;
    }
    if (!$days) return 'Tick at least one day this applies to.';
    $csv = implode(',', array_keys($days));

    if ($id > 0) {
        cm_q('UPDATE workday SET label = ?, weekdays = ?, starts = ?, ends = ?, updated_at = ?
               WHERE id = ?',
             [$label, $csv, $start . ':00', $end . ':00', cm_now(), $id]);
        return '';
    }
    $sort = (int) cm_val('SELECT COALESCE(MAX(sort), 0) + 10 FROM workday');
    cm_insert('INSERT INTO workday (label, weekdays, starts, ends, sort, created_at, updated_at)
               VALUES (?, ?, ?, ?, ?, ?, ?)',
              [$label !== '' ? $label : 'Working day', $csv, $start . ':00', $end . ':00',
               $sort, cm_now(), cm_now()]);
    return '';
}


function cm_workday_break_add(int $workdayId, array $in): string
{
    if (!cm_row('SELECT id FROM workday WHERE id = ?', [$workdayId])) {
        return 'That working day no longer exists.';
    }
    $start = trim((string) ($in['starts'] ?? ''));
    $end   = trim((string) ($in['ends'] ?? ''));
    if (!preg_match('/^\d{1,2}:\d{2}$/', $start) || !preg_match('/^\d{1,2}:\d{2}$/', $end)) {
        return 'A break needs a start and an end, like 12:30 and 13:00.';
    }
    if (cm_hm_minutes($end) <= cm_hm_minutes($start)) {
        return 'That break ends before it starts.';
    }
    cm_insert('INSERT INTO workday_break (workday_id, label, starts, ends, flexible, sort)
               VALUES (?, ?, ?, ?, ?, ?)',
              [$workdayId, mb_substr(trim((string) ($in['label'] ?? '')), 0, 60) ?: 'Break',
               $start . ':00', $end . ':00', !empty($in['flexible']) ? 1 : 0,
               (int) cm_val('SELECT COALESCE(MAX(sort), 0) + 10 FROM workday_break WHERE workday_id = ?',
                            [$workdayId])]);
    return '';
}

function cm_workday_remove(int $id): void
{
    cm_q('DELETE FROM workday WHERE id = ?', [$id]);
}

function cm_workday_break_remove(int $id): void
{
    cm_q('DELETE FROM workday_break WHERE id = ?', [$id]);
}


function cm_workday_summary(array $s): string
{
    $bits = [implode(', ', $s['days']) ?: 'no days',
             substr((string) $s['starts'], 0, 5) . '–' . substr((string) $s['ends'], 0, 5)];
    $n = count($s['breaks'] ?? []);
    if ($n > 0) $bits[] = $n . ' break' . ($n === 1 ? '' : 's');
    return implode(' · ', $bits);
}
