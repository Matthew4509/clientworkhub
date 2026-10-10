<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0




























declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }

require_once CM_LIB . '/settings.php';


function cm_demo_on(): bool
{
    try { return (string) (cm_setting('demo.enabled', '') ?? '') === '1'; }
    catch (Throwable $ex) { return false; }
}


function cm_demo_generated_at(): string
{
    try { return (string) (cm_setting('demo.generated_at', '') ?? ''); }
    catch (Throwable $ex) { return ''; }
}










function cm_demo_blockers(): array
{
    if (cm_demo_on()) return [];

    $out = [];
    $count = static function (string $sql): int {
        try { return (int) cm_val($sql); } catch (Throwable $ex) { return 0; }
    };

    $clients  = $count('SELECT COUNT(*) FROM client');
    $jobs     = $count('SELECT COUNT(*) FROM job');
    $invoices = $count('SELECT COUNT(*) FROM invoice');

    if ($clients > 0) {
        $out[] = $clients . ' client' . ($clients === 1 ? '' : 's')
               . ' already on file. Demo mode fills the application with invented ones and '
               . 'they must not sit beside yours.';
    }
    if ($jobs > 0)     $out[] = $jobs . ' job' . ($jobs === 1 ? '' : 's') . ' already here.';
    if ($invoices > 0) $out[] = $invoices . ' invoice' . ($invoices === 1 ? '' : 's') . ' already raised.';
    return $out;
}


function cm_demo_ids(): array
{
    try { $raw = (string) (cm_setting('demo.ids', '') ?? ''); }
    catch (Throwable $ex) { return []; }
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}


function cm_demo_managed_settings(): array
{
    return ['workday.start', 'workday.end', 'workday.break_start', 'workday.break_end',
            'workday.days', 'capacity.mode', 'capacity.overtime_minutes',
            'capacity.overtime_extends_day'];
}








function cm_demo_services(): array
{
    return [
        [
            'name' => 'Post to Facebook daily — 7 days',
            'blurb' => 'A week of daily posts to the client\'s Facebook page, written and scheduled.',
            'duration_days' => 7, 'setup_minutes' => 15, 'per_day_minutes' => 30,
            'completion_minutes' => 15, 'amount_cents' => 35000,
            'action' => 'Write and publish the day\'s post',
        ],
        [
            'name' => 'Website blog entry — 30 days',
            'blurb' => 'A month of blog writing: research, draft, publish and index.',
            'duration_days' => 30, 'setup_minutes' => 30, 'per_day_minutes' => 60,
            'completion_minutes' => 15, 'amount_cents' => 180000,
            'action' => 'Research, write and publish the day\'s entry',
        ],
    ];
}


function cm_demo_people(): array
{
    $first = ['Alice','Ben','Priya','Marcus','Chloe','Diego','Fatima','Owen','Nadia','Tom',
              'Grace','Hassan','Ivy','Jonas','Keira','Liam','Maya','Noor','Oscar','Petra',
              'Quinn','Rosa','Sam','Tara','Umar','Vera','Wes','Xanthe','Yusuf','Zara',
              'Aaron','Bella','Cormac','Dana','Elif','Frank','Gita','Hugo','Iris','Jack',
              'Kara','Leo','Mina','Nate','Orla','Paul','Rhea','Seth','Tilly','Vince'];
    $last  = ['Baker','Nguyen','Shah','Reilly','Dubois','Marino','Okafor','Sandberg','Rossi','Fletcher',
              'Ahmed','Kowalski','Duarte','Novak','Bright','Osei','Lindqvist','Farah','Whitfield','Mbeki',
              'Costa','Haddad','Iverson','Jansen','Kelly','Larsen','Moreau','Nasser','Ortega','Petrov',
              'Quill','Ramos','Serrano','Tanaka','Ueda','Vargas','Walsh','Xu','Yilmaz','Zhang',
              'Abbott','Byrne','Chen','Doyle','Egan','Foley','Gallo','Hayes','Ito','Joyce'];
    $trade = ['Bakery','Dental','Plumbing','Fitness','Legal','Roofing','Florist','Cafe','Garage','Salon',
              'Vets','Optics','Tiling','Catering','Joinery','Cleaning','Removals','Signage','Framing','Print'];

    $out = [];
    for ($i = 0; $i < 50; $i++) {
        $f = $first[$i % count($first)];
        $l = $last[($i * 7 + 3) % count($last)];
        $t = $trade[($i * 3 + 1) % count($trade)];
        $slug = strtolower($f . $l);
        $out[] = [
            'first' => $f, 'last' => $l,
            'company' => $t . ' ' . (($i % 3 === 0) ? 'Co' : (($i % 3 === 1) ? 'Group' : 'Studio')),



            'email' => $slug . '@example.invalid',
            'phone' => '07' . str_pad((string) (700000 + $i * 137), 9, '0'),
            'site'  => 'https://' . $slug . '.example.invalid',
        ];
    }
    return $out;
}








function cm_demo_enable(string $actor): string
{
    if (cm_demo_blockers()) return 'There is real data here — demo mode refused.';
    return cm_demo_generate($actor);
}














function cm_demo_generate(string $actor): string
{
    require_once CM_LIB . '/jobs.php';
    require_once CM_LIB . '/clients.php';

    $today = date('Y-m-d');
    $ids = ['clients' => [], 'templates' => [], 'jobs' => [], 'assets' => []];


    $prior = [];
    foreach (cm_demo_managed_settings() as $k) {
        $prior[$k] = ['stored' => cm_setting_source($k) === 'db', 'value' => (string) cm_setting($k, '')];
    }




    foreach (['workday.start' => '9:00', 'workday.end' => '17:00',
              'workday.break_start' => '13:00', 'workday.break_end' => '13:30',
              'workday.days' => 'mon,tue,wed,thu,fri',
              'capacity.mode' => 'off',
              'capacity.overtime_minutes' => '240',
              'capacity.overtime_extends_day' => '1'] as $k => $v) {
        cm_setting_set($k, $v, $actor);
    }
    cm_settings_forget();


    foreach (cm_demo_services() as $svc) {
        $tid = cm_insert(
            "INSERT INTO job_template (name, notes, published, amount_cents, billing_freq,
                                       duration_days, per_day_minutes, setup_minutes,
                                       completion_minutes, completion_mode, created_at, updated_at)
             VALUES (?, ?, 1, ?, 'oneoff', ?, ?, ?, ?, 'own_day', ?, ?)",
            [$svc['name'], $svc['blurb'], $svc['amount_cents'], $svc['duration_days'],
             $svc['per_day_minutes'], $svc['setup_minutes'], $svc['completion_minutes'],
             cm_now(), cm_now()]);
        cm_insert("INSERT INTO job_template_action (template_id, name, frequency, anchor, required, est_minutes)
                   VALUES (?, ?, 'daily', 'start', 1, ?)",
                  [$tid, $svc['action'], $svc['per_day_minutes']]);
        $ids['templates'][] = $tid;
    }




    $people = cm_demo_people();
    foreach ($people as $i => $p) {
        $started = date('Y-m-d', strtotime($today . ' -' . (49 - $i) . ' days'));

        $cid = cm_insert(
            'INSERT INTO client (first_name, last_name, company, email, phone, status, tags, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$p['first'], $p['last'], $p['company'], $p['email'], $p['phone'],
             'active', 'demo', 'Invented for demo mode.', $started . ' 09:00:00', cm_now()]);
        $ids['clients'][] = $cid;



        $aid = cm_insert(
            'INSERT INTO client_asset (client_id, name, type, url, handle, notes, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$cid, $p['company'] . ' website', 'website', $p['site'],
             strtolower($p['first']) . '.admin', 'Demo property.', 'active', cm_now(), cm_now()]);
        $ids['assets'][] = $aid;

        $svcIndex = $i % 2;
        $tpl = cm_row('SELECT * FROM job_template WHERE id = ?', [$ids['templates'][$svcIndex]]);
        $jid = cm_insert(
            "INSERT INTO job (client_id, name, status, start_date, amount_cents, billing_freq, created_at, updated_at)
             VALUES (?, ?, 'active', ?, ?, 'oneoff', ?, ?)",
            [$cid, (string) $tpl['name'], $started, (int) $tpl['amount_cents'], cm_now(), cm_now()]);
        $ids['jobs'][] = $jid;

        cm_job_assets_set($jid, $cid, [$aid]);
        try { cm_template_apply((int) $tpl['id'], cm_job($jid)); } catch (Throwable $ex) {}





        try {
            cm_q("UPDATE action_occurrence
                     SET status = 'completed', completed_at = CONCAT(scheduled_date, ' 16:30:00'),
                         minutes_spent = est_minutes,
                         note = 'Done and sent to the client.',
                         completed_by = ?, updated_at = ?
                   WHERE job_id = ? AND scheduled_date < ?",
                 [$actor, cm_now(), $jid, $today]);
        } catch (Throwable $ex) {}
    }

    cm_setting_set('demo.ids', (string) json_encode($ids), $actor);
    cm_setting_set('demo.prior', (string) json_encode($prior), $actor);
    cm_setting_set('demo.generated_at', cm_now(), $actor);
    cm_setting_set('demo.enabled', '1', $actor);
    cm_settings_forget();

    cm_audit('demo.enabled', '', null,
             'demo mode generated ' . count($ids['clients']) . ' clients and '
             . count($ids['jobs']) . ' jobs by ' . $actor);

    return count($ids['clients']) . ' clients, ' . count($ids['jobs']) . ' jobs and '
         . count($ids['templates']) . ' services — all invented, all removed when you leave.';
}








function cm_demo_disable(string $actor): string
{
    $ids = cm_demo_ids();

    $gone = ['clients' => 0, 'jobs' => 0, 'templates' => 0];

    foreach ($ids['jobs'] ?? [] as $jid) {
        try { cm_q('DELETE FROM job WHERE id = ?', [(int) $jid]); $gone['jobs']++; } catch (Throwable $ex) {}
    }
    foreach ($ids['clients'] ?? [] as $cid) {
        try {
            cm_q('DELETE FROM mail_log WHERE client_id = ?', [(int) $cid]);
            cm_q("DELETE FROM audit_log WHERE target_type = 'client' AND target_id = ?", [(int) $cid]);
            cm_q('DELETE FROM client WHERE id = ?', [(int) $cid]);
            $gone['clients']++;
        } catch (Throwable $ex) {}
    }
    foreach ($ids['templates'] ?? [] as $tid) {
        try {
            cm_q('DELETE FROM job_template_action WHERE template_id = ?', [(int) $tid]);
            cm_q('DELETE FROM job_template WHERE id = ?', [(int) $tid]);
            $gone['templates']++;
        } catch (Throwable $ex) {}
    }


    $prior = [];
    try { $prior = json_decode((string) (cm_setting('demo.prior', '') ?? ''), true) ?: []; }
    catch (Throwable $ex) {}
    foreach (cm_demo_managed_settings() as $k) {
        if (!empty($prior[$k]['stored'])) cm_setting_set($k, (string) $prior[$k]['value'], $actor);
        else                              cm_setting_clear($k);
    }

    foreach (['demo.enabled', 'demo.ids', 'demo.prior', 'demo.generated_at'] as $k) {
        cm_setting_clear($k);
    }
    cm_settings_forget();

    cm_audit('demo.disabled', '', null,
             'demo mode cleared: ' . $gone['clients'] . ' clients, ' . $gone['jobs']
             . ' jobs, ' . $gone['templates'] . ' services removed by ' . $actor);

    if (!$ids) {
        return 'Demo mode is off. Nothing was removed — there was no record of what it made, '
             . 'and a wipe that guesses is worse than one that does nothing.';
    }
    return 'Demo mode off. ' . $gone['clients'] . ' invented clients, ' . $gone['jobs']
         . ' jobs and ' . $gone['templates'] . ' services removed, and your settings are back.';
}
