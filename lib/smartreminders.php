<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0





































declare(strict_types=1);
if (!defined('CM_ROOT')) { http_response_code(404); exit; }

require_once CM_LIB . '/reminders.php';



require_once CM_LIB . '/format.php';


const CM_SMART_BATCH = 20;


const CM_SMART_HORIZON_DAYS = 180;

const CM_SMART_PUSH_WINDOW_DAYS = 42;




function cm_smart_cooldown_days(): int
{
    try { $v = (string) cm_setting('smart.cooldown', '14'); }
    catch (Throwable $ex) { $v = '14'; }
    return in_array($v, ['0', '7', '14', '30'], true) ? (int) $v : 14;
}









function cm_smart_focuses(): array
{
    return [
        'balanced' => [
            'label' => 'Balanced',
            'blurb' => 'The everyday list, in the usual order of preference.',
            'logic' => [
                'VIPs first, always.',
                'Then whoever a push-back has delayed longest past their original date.',
                'Then the clients you have known longest — they are likely ready.',
                'Then the highest lifetime value (every job, all time).',
            ],
        ],
        'small_gaps' => [
            'label' => 'Fill small gaps',
            'blurb' => 'The diary is tight — an hour here and there.',
            'logic' => [
                'VIPs first, always.',
                'Then clients whose work has AVERAGED smallest — a history of '
                    . 'small jobs is the best sign the next one fits a gap.',
                'Ties go to higher lifetime value, then to the longest-known.',
                'Average job value stands in for job length — this application '
                    . 'knows prices better than it knows minutes.',
            ],
        ],
        'big_work' => [
            'label' => 'Big work first',
            'blurb' => 'The diary is empty — go after the large jobs.',
            'logic' => [
                'VIPs first, always.',
                'Then the highest lifetime value across every job — the '
                    . 'thousand-small-jobs client outranks one big job from '
                    . 'three years ago.',
                'Then the largest average job.',
                'Ties go to the longest-known client.',
            ],
        ],
    ];
}




function cm_smart_bands(): array
{
    return [
        'within 2 weeks' => [1, 14],
        '2–4 weeks'      => [15, 28],
        '1–2 months'     => [29, 60],
        '2–4 months'     => [61, 120],
        '4 months on'    => [121, 100000],
    ];
}

function cm_smart_band_label(int $daysOut): string
{
    foreach (cm_smart_bands() as $label => [$lo, $hi]) {
        if ($daysOut >= $lo && $daysOut <= $hi) return $label;
    }
    return 'soon';
}








function cm_smart_pool(): array
{
    $today = date('Y-m-d');
    $fetch = static function (int $horizon) use ($today): array {
        return cm_all(
            "SELECT r.*, c.vip, c.created_at AS client_created, c.status AS client_status,
                    c.first_name, c.last_name, c.company, c.email AS client_email, c.id AS cid
               FROM reminder r JOIN client c ON c.id = r.client_id
              WHERE r.kind = 'email' AND r.status = 'pending' AND r.sent_at IS NULL
                AND r.due_date > ? AND r.due_date <= ?
                AND c.status <> 'inactive'
              ORDER BY r.due_date, r.id",
            [$today, date('Y-m-d', time() + $horizon * 86400)]);
    };
    $rows = $fetch(CM_SMART_HORIZON_DAYS);
    $extended = false;
    if (count($rows) < CM_SMART_BATCH) {
        $rows = $fetch(CM_SMART_HORIZON_DAYS * 2);
        $extended = true;
    }


    $eligible = [];
    foreach ($rows as $r) {
        if (!cm_reminder_is_long($r)) continue;
        $interval = cm_reminder_interval_days($r);
        $elapsed  = cm_days_between(substr((string) $r['created_at'], 0, 10), $today);
        if ($elapsed * 3 < $interval * 2) continue;
        if (cm_client_is_unsubscribed((int) $r['cid'])) continue;
        $eligible[] = $r;
    }
    if (!$eligible) return ['cands' => [], 'extended' => $extended];


    $cids = array_values(array_unique(array_map(static fn(array $r): int => (int) $r['cid'], $eligible)));
    $in   = implode(',', $cids);
    $hist = [];
    foreach (cm_all("SELECT client_id, COUNT(*) AS n, COALESCE(SUM(amount_cents),0) AS total,
                            COALESCE(AVG(NULLIF(amount_cents,0)),0) AS avg
                       FROM job WHERE client_id IN ({$in}) GROUP BY client_id") as $h) {
        $hist[(int) $h['client_id']] = $h;
    }
    $lastSent = [];
    foreach (cm_all("SELECT client_id, MAX(created_at) AS at FROM mail_log
                      WHERE client_id IN ({$in}) AND kind = 'client_followup'
                        AND result IN ('sent','spooled') GROUP BY client_id") as $h) {
        $lastSent[(int) $h['client_id']] = (string) $h['at'];
    }



    $jobIds = [];
    foreach ($eligible as $r) {
        if ((int) ($r['job_id'] ?? 0) > 0) $jobIds[(int) $r['job_id']] = true;
    }
    $jobs = [];
    if ($jobIds) {
        foreach (cm_all('SELECT id, name, amount_cents, end_date, status FROM job
                          WHERE id IN (' . implode(',', array_keys($jobIds)) . ')') as $j) {
            $jobs[(int) $j['id']] = $j;
        }
    }

    $cands = [];
    foreach ($eligible as $r) {
        $cid = (int) $r['cid'];
        $h = $hist[$cid] ?? ['n' => 0, 'total' => 0, 'avg' => 0];
        $dueIn = cm_days_between($today, (string) $r['due_date']);
        $orig  = (string) ($r['original_due'] ?? '');
        $cands[] = [
            'r' => $r,
            'cid' => $cid,
            'vip' => (int) $r['vip'],
            'client_name' => cm_client_display_name(['id' => $cid,
                'first_name' => $r['first_name'], 'last_name' => $r['last_name'],
                'company' => $r['company'], 'email' => $r['client_email']]),
            'client_age_days' => cm_days_between(substr((string) $r['client_created'], 0, 10), $today),
            'jobs_n' => (int) $h['n'],
            'total_cents' => (int) $h['total'],
            'avg_cents' => (int) $h['avg'],
            'delayed_days' => $orig !== '' && $orig < (string) $r['due_date']
                ? cm_days_between($orig, (string) $r['due_date']) : 0,
            'due_in' => $dueIn,
            'band' => cm_smart_band_label($dueIn),
            'last_sent' => $lastSent[$cid] ?? '',
            'job_name' => (string) ($jobs[(int) ($r['job_id'] ?? 0)]['name'] ?? ''),
            'job_cents' => (int) ($jobs[(int) ($r['job_id'] ?? 0)]['amount_cents'] ?? 0),
            'job_done' => (string) ($jobs[(int) ($r['job_id'] ?? 0)]['status'] ?? '') === 'completed'
                ? (string) ($jobs[(int) ($r['job_id'] ?? 0)]['end_date'] ?? '') : '',
            'more' => 0,
        ];
    }
    return ['cands' => $cands, 'extended' => $extended];
}



function cm_smart_rank(array $cands, string $focus): array
{
    $key = static function (array $c) use ($focus): array {
        return match ($focus) {
            'small_gaps' => [-$c['vip'], $c['avg_cents'], -$c['total_cents'], -$c['client_age_days']],
            'big_work'   => [-$c['vip'], -$c['total_cents'], -$c['avg_cents'], -$c['client_age_days']],
            default      => [-$c['vip'], -$c['delayed_days'], -$c['client_age_days'], -$c['total_cents']],
        };
    };
    usort($cands, static fn(array $a, array $b): int => $key($a) <=> $key($b));
    return $cands;
}







function cm_smart_pick(array $cands, string $focus): array
{



    $byClient = [];
    foreach ($cands as $c) {
        if (isset($byClient[$c['cid']])) { $byClient[$c['cid']]['more']++; continue; }
        $byClient[$c['cid']] = $c;
    }


    $days = cm_smart_cooldown_days();
    $kept = [];
    $skipped = [];
    foreach ($byClient as $c) {
        if ($days > 0 && $c['last_sent'] !== ''
            && (int) strtotime($c['last_sent']) > time() - $days * 86400) {
            $c['skip_why'] = 'had a reminder within ' . $days . ' days ('
                           . date('j M', (int) strtotime($c['last_sent'])) . ')';
            $skipped[] = $c;
            continue;
        }
        $kept[] = $c;
    }


    $bands = [];
    foreach ($kept as $c) $bands[$c['band']][] = $c;
    foreach ($bands as $label => $list) $bands[$label] = cm_smart_rank($list, $focus);
    $order = array_keys(cm_smart_bands());
    $list = [];
    while (count($list) < CM_SMART_BATCH) {
        $took = false;
        foreach ($order as $label) {
            if (empty($bands[$label])) continue;
            $list[] = array_shift($bands[$label]);
            $took = true;
            if (count($list) >= CM_SMART_BATCH) break;
        }
        if (!$took) break;
    }
    return [$list, $skipped];
}


function cm_smart_why(array $c): string
{
    $bits = [];
    if ($c['vip']) $bits[] = 'VIP';
    $y = $c['client_age_days'] >= 365 ? round($c['client_age_days'] / 365, 1) . ' years' : $c['client_age_days'] . ' days';
    $bits[] = 'client ' . $y;
    $bits[] = $c['jobs_n'] . ' job' . ($c['jobs_n'] === 1 ? '' : 's')
            . ($c['total_cents'] > 0 ? ', ' . cm_money($c['total_cents']) . ' all time' : '');
    $bits[] = 'due ' . date('j M', (int) strtotime((string) $c['r']['due_date']))
            . ' (' . $c['band'] . ')';
    if ($c['delayed_days'] > 0) $bits[] = 'pushed back ' . $c['delayed_days'] . ' days past its original date';
    if ($c['more'] > 0) $bits[] = '+' . $c['more'] . ' more reminder' . ($c['more'] === 1 ? '' : 's') . ' on this client';
    return implode(' · ', $bits);
}












function cm_smart_send(array $ids, string $actor): array
{
    $queued = 0;
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id <= 0) continue;


        $n = cm_q(
            "UPDATE reminder SET original_due = COALESCE(original_due, due_date),
                    due_date = ?, updated_at = ?
              WHERE id = ? AND kind = 'email' AND status = 'pending' AND sent_at IS NULL",
            [date('Y-m-d'), cm_now(), $id])->rowCount();
        $queued += $n;
    }
    $sent = 0;
    if ($queued > 0) {




        $sent = cm_followups_send_due(min($queued, CM_SMART_BATCH));
        cm_audit('smart.batch', '', null, $queued . ' reminder(s) pulled forward by ' . $actor
                 . '; the send pass that followed put ' . $sent . ' message(s) out'
                 . ($sent < $queued ? ' — the rest go out within the hourly cap on the next visit' : ''));
    }
    return ['queued' => $queued, 'sent' => $sent];
}







function cm_smart_pushback_candidates(): array
{
    $rows = cm_all(
        "SELECT r.*, c.vip, c.created_at AS client_created, c.status AS client_status,
                c.first_name, c.last_name, c.company, c.email AS client_email, c.id AS cid
           FROM reminder r JOIN client c ON c.id = r.client_id
          WHERE r.kind = 'email' AND r.status = 'pending' AND r.sent_at IS NULL
            AND r.due_date <= ? AND c.status <> 'inactive'
          ORDER BY r.due_date, r.id",
        [date('Y-m-d', time() + CM_SMART_PUSH_WINDOW_DAYS * 86400)]);
    $out = [];
    foreach ($rows as $r) {
        if (!cm_reminder_is_long($r)) continue;
        if (cm_smart_no_push_vip() && (int) $r['vip'] === 1) continue;
        $out[] = $r;
    }
    if (!$out) return [];
    $cids = implode(',', array_values(array_unique(array_map(static fn(array $r): int => (int) $r['cid'], $out))));
    $hist = [];
    foreach (cm_all("SELECT client_id, COUNT(*) AS n, COALESCE(SUM(amount_cents),0) AS total
                       FROM job WHERE client_id IN ({$cids}) GROUP BY client_id") as $h) {
        $hist[(int) $h['client_id']] = $h;
    }
    foreach ($out as $i => $r) {
        $h = $out[$i]['hist'] = $hist[(int) $r['cid']] ?? ['n' => 0, 'total' => 0];
        $out[$i]['total_cents'] = (int) $h['total'];
        $out[$i]['jobs_n'] = (int) $h['n'];
        $out[$i]['client_name'] = cm_client_display_name(['id' => (int) $r['cid'],
            'first_name' => $r['first_name'], 'last_name' => $r['last_name'],
            'company' => $r['company'], 'email' => $r['client_email']]);
    }
    usort($out, static fn(array $a, array $b): int =>
        [$a['total_cents'], $a['jobs_n']] <=> [$b['total_cents'], $b['jobs_n']]);
    return $out;
}





function cm_smart_pushback(array $ids, int $weeks, string $actor): int
{
    if (!in_array($weeks, [2, 4, 8, 12], true)) return 0;
    $moved = 0;
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id <= 0) continue;
        $r = cm_reminder($id);
        if ($r === null || (string) $r['kind'] !== 'email'
            || (string) $r['status'] !== 'pending' || $r['sent_at'] !== null) continue;
        if (cm_smart_no_push_vip()) {
            $vip = (int) cm_val('SELECT vip FROM client WHERE id = ?', [(int) $r['client_id']]);
            if ($vip === 1) continue;
        }
        $newDue = date('Y-m-d', (int) strtotime((string) $r['due_date']) + $weeks * 7 * 86400);
        $moved += cm_q(
            "UPDATE reminder SET original_due = COALESCE(original_due, due_date),
                    due_date = ?, updated_at = ?
              WHERE id = ? AND status = 'pending' AND sent_at IS NULL",
            [$newDue, cm_now(), $id])->rowCount();
    }
    if ($moved > 0) {
        cm_audit('smart.pushback', '', null, $moved . ' reminder(s) pushed back ' . $weeks
                 . ' week(s) by ' . $actor . ' — original dates kept');
    }
    return $moved;
}



function cm_smart_waiting_today(): int
{
    return (int) cm_val(
        "SELECT COUNT(*) FROM reminder r JOIN client c ON c.id = r.client_id
          WHERE r.kind = 'email' AND r.status = 'pending' AND r.sent_at IS NULL
            AND r.due_date <= ? AND c.status <> 'inactive'", [date('Y-m-d')]);
}
