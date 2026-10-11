<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0


















declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }

require_once CM_LIB . '/format.php';



require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/schedule.php';






function cm_insight_heatmap(string $table = 'client', string $column = 'created_at'): array
{



    $allowed = ['client' => 'created_at', 'job' => 'created_at',
                'action_occurrence' => 'completed_at', 'booking' => 'created_at'];
    if (!isset($allowed[$table])) { $table = 'client'; }
    $column = $allowed[$table];

    $to   = date('Y-m-d');
    $from = date('Y-m-d', strtotime('-364 days'));

    $days = [];
    try {
        $rows = cm_all("SELECT DATE({$column}) AS d, COUNT(*) AS n FROM {$table}
                         WHERE {$column} IS NOT NULL AND DATE({$column}) BETWEEN ? AND ?
                         GROUP BY DATE({$column})", [$from, $to]);
        foreach ($rows as $r) $days[(string) $r['d']] = (int) $r['n'];
    } catch (Throwable $ex) {

    }

    return ['from' => $from, 'to' => $to, 'days' => $days,
            'total' => array_sum($days), 'best' => $days ? max($days) : 0];
}









function cm_insight_heatmap_grid(array $map): array
{
    $best = max(1, (int) $map['best']);








    $first  = new DateTimeImmutable($map['from']);
    $cursor = $first->modify('-' . (int) $first->format('w') . ' days');
    $end    = new DateTimeImmutable($map['to']);

    $weeks = [];
    while ($cursor <= $end) {
        $col = [];
        for ($d = 0; $d < 7; $d++) {
            $day = $cursor->modify('+' . $d . ' day');
            if ($day > $end) { $col[$d] = null; continue; }
            $key = $day->format('Y-m-d');
            $n = (int) ($map['days'][$key] ?? 0);


            $level = $n === 0 ? 0 : max(1, (int) ceil($n / $best * 4));
            $col[$d] = ['date' => $key, 'n' => $n, 'level' => $level];
        }
        $weeks[] = $col;
        $cursor = $cursor->modify('+7 days');
    }
    return $weeks;
}


function cm_insight_heatmap_months(array $weeks): array
{
    $out = [];
    $seen = '';
    foreach ($weeks as $i => $col) {
        $first = null;
        foreach ($col as $cell) { if ($cell !== null) { $first = $cell; break; } }
        if ($first === null) continue;
        $m = date('M', strtotime($first['date']));
        if ($m !== $seen) { $out[$i] = $m; $seen = $m; }
    }
    return $out;
}








function cm_insight_growth(string $table, string $column = 'created_at'): ?int
{
    $allowed = ['client' => 'created_at', 'job' => 'created_at', 'booking' => 'created_at'];
    if (!isset($allowed[$table])) return null;
    $column = $allowed[$table];

    try {
        $now  = (int) cm_val("SELECT COUNT(*) FROM {$table} WHERE {$column} >= ?",
                             [date('Y-m-d', strtotime('-364 days'))]);
        $prev = (int) cm_val("SELECT COUNT(*) FROM {$table} WHERE {$column} >= ? AND {$column} < ?",
                             [date('Y-m-d', strtotime('-729 days')),
                              date('Y-m-d', strtotime('-364 days'))]);
    } catch (Throwable $ex) {
        return null;
    }
    if ($prev < 5) return null;
    return (int) round(($now - $prev) / $prev * 100);
}







function cm_insight_blocks(): array
{
    $count = static function (string $sql, array $a = []): int {
        try { return (int) cm_val($sql, $a); } catch (Throwable $ex) { return 0; }
    };
    $sum = static function (string $sql, array $a = []): int {
        try { return (int) cm_val($sql, $a); } catch (Throwable $ex) { return 0; }
    };
    $out = [];


    $lines = [];
    foreach (cm_client_statuses() as $key => $meta) {
        $n = $count('SELECT COUNT(*) FROM client WHERE status = ?', [$key]);
        if ($n === 0 && in_array($key, ['reminders', 'inactive'], true)) continue;


        $lines[] = ['n' => $n,
                    'label' => $key === 'reminders' ? 'kept for reminders only'
                                                    : 'currently ' . strtolower($meta['label']),
                    'href' => cm_url('admin/clients.php?status=' . $key)];
    }
    $out[] = [
        'title' => 'Clients',
        'href'  => cm_url('admin/clients.php'),
        'lead'  => $count('SELECT COUNT(*) FROM client') . ' clients on file',
        'growth' => cm_insight_growth('client'),
        'lines' => $lines,
    ];


    $lines = [];
    foreach (['active' => 'currently active', 'completed' => 'completed',
              'draft' => 'still drafts', 'hold' => 'on hold'] as $k => $lab) {
        $n = $count('SELECT COUNT(*) FROM job WHERE status = ?', [$k]);
        if ($n === 0 && in_array($k, ['hold', 'draft'], true)) continue;
        $lines[] = ['n' => $n, 'label' => $lab, 'href' => cm_url('admin/jobs.php?status=' . $k)];
    }
    $fromService = $count('SELECT COUNT(*) FROM job WHERE template_id IS NOT NULL');
    $allJobs = max(1, $count('SELECT COUNT(*) FROM job'));
    $lines[] = ['pct' => (int) round($fromService / $allJobs * 100),
                'label' => 'stamped from a service', 'href' => cm_url('admin/services.php')];
    $out[] = [
        'title' => 'Jobs',
        'href'  => cm_url('admin/jobs.php'),
        'lead'  => $count('SELECT COUNT(*) FROM job') . ' jobs created',
        'growth' => cm_insight_growth('job'),
        'lines' => $lines,
    ];


    $doneAll = $count("SELECT COUNT(*) FROM action_occurrence WHERE status = 'completed'");
    $skipped = $count("SELECT COUNT(*) FROM action_occurrence WHERE status = 'skipped'");
    $mins    = $sum('SELECT COALESCE(SUM(minutes),0) FROM time_entry WHERE billable = 1');
    $lines = [
        ['n' => $doneAll, 'label' => 'task-days completed', 'href' => cm_url('admin/workflow.php')],
    ];
    if ($skipped > 0) {
        $lines[] = ['n' => $skipped, 'label' => 'skipped or waived', 'href' => cm_url('admin/workflow.php')];
    }
    if ($mins > 0) {
        $lines[] = ['text' => cm_minutes_label($mins), 'label' => 'tracked, billable',
                    'href' => cm_url('admin/reports.php')];
    }
    $out[] = ['title' => 'Work done', 'href' => cm_url('admin/workflow.php'),
              'lead' => $doneAll . ' task-day' . ($doneAll === 1 ? '' : 's') . ' completed',
              'growth' => null, 'lines' => $lines];


    $bk = cm_home() ? 0 : $count('SELECT COUNT(*) FROM booking');
    if ($bk > 0) {
        $accepted = $count("SELECT COUNT(*) FROM booking WHERE status = 'accepted'");
        $quoted   = $sum('SELECT COALESCE(SUM(quoted_cents),0) FROM booking');
        $lines = [
            ['n' => $count("SELECT COUNT(*) FROM booking WHERE status = 'new'"),
             'label' => 'still to answer', 'href' => cm_url('admin/bookings.php')],
            ['n' => $accepted, 'label' => 'accepted', 'href' => cm_url('admin/bookings.php')],
            ['text' => cm_money($quoted), 'label' => 'quoted in total',
             'href' => cm_url('admin/bookings.php')],
            ['pct' => (int) round($accepted / max(1, $bk) * 100), 'label' => 'accepted',
             'href' => cm_url('admin/bookings.php')],
        ];
        $out[] = ['title' => 'Bookings', 'href' => cm_url('admin/bookings.php'),
                  'lead' => $bk . ' came in from your site',
                  'growth' => cm_insight_growth('booking'), 'lines' => $lines];
    }


    $rem = $count('SELECT COUNT(*) FROM reminder');
    if ($rem > 0) {
        $lines = [
            ['n' => $count("SELECT COUNT(*) FROM reminder WHERE kind='nudge' AND status='pending'"),
             'label' => 'waiting on you', 'href' => cm_url('admin/reminders.php')],
        ];

        if (!cm_home()) $lines = array_merge($lines, [
            ['n' => $count("SELECT COUNT(*) FROM reminder WHERE kind='email' AND sent_at IS NOT NULL"),
             'label' => 'follow-up emails sent', 'href' => cm_url('admin/reminders.php#scheduled')],
            ['n' => $count("SELECT COUNT(*) FROM reminder WHERE kind='email' AND sent_at IS NULL AND status='pending'"),
             'label' => 'follow-ups scheduled', 'href' => cm_url('admin/reminders.php#scheduled')],
        ]);
        $out[] = ['title' => 'Follow-ups', 'href' => cm_url('admin/reminders.php'),
                  'lead' => $rem . ' reminders set', 'growth' => null, 'lines' => $lines];
    }


    $annual = 0;
    try {
        require_once CM_LIB . '/jobs.php';
        foreach (cm_all("SELECT * FROM job WHERE status = 'active'") as $j) {
            $annual += cm_job_annual_cents($j);
        }
    } catch (Throwable $ex) {}
    if ($annual > 0 || $mins > 0) {
        $rate = null;
        try { $rate = cm_effective_rate_cents($annual, $mins); } catch (Throwable $ex) {}
        $lines = [
            ['text' => cm_money((int) round($annual / 12)), 'label' => 'a month, active work',
             'href' => cm_url('admin/reports.php')],
            ['text' => cm_money($annual), 'label' => 'a year, active work',
             'href' => cm_url('admin/reports.php')],
        ];
        if ($rate !== null) {
            $lines[] = ['text' => cm_money($rate), 'label' => 'an hour, across tracked time',
                        'href' => cm_url('admin/reports.php')];
        }
        $out[] = ['title' => 'What it is worth', 'href' => cm_url('admin/reports.php'),
                  'lead' => cm_money($annual) . ' a year, running', 'growth' => null,
                  'lines' => $lines];
    }

    return $out;
}










function cm_insight_heatmap_html(array $map, string $noun = 'clients'): string
{






    try {
        $weeks  = cm_insight_heatmap_grid($map);
        $months = cm_insight_heatmap_months($weeks);
    } catch (Throwable $ex) {
        return '<p class="muted small">The year map could not be drawn.</p>';
    }
    $dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    ob_start();
    ?>
<div class="heat">
  <div class="heat-head">
    <b>Last 365 days</b>
    <span class="muted small">
      <?= e(date('j M Y', strtotime($map['from']))) ?> –
      <?= e(date('j M Y', strtotime($map['to']))) ?>
    </span>
    <span class="spacer"></span>
    <span class="mono small"><?= (int) $map['total'] ?> <?= e($noun) ?></span>
  </div>

  <?php if ((int) $map['total'] === 0): ?>
    <?php 
 ?>
    <p class="muted small">Nothing yet in the last year. This fills in as you go.</p>
  <?php else: ?>
    <div class="heat-scroll">
      <div class="heat-months">
        <span class="heat-daycol"></span>
        <?php foreach ($weeks as $i => $col): ?>
          <span class="heat-month"><?= isset($months[$i]) ? e($months[$i]) : '' ?></span>
        <?php endforeach; ?>
      </div>
      <?php for ($d = 0; $d < 7; $d++): ?>
        <div class="heat-row">
          <span class="heat-daycol"><?= $d % 2 === 1 ? e($dayLabels[$d]) : '' ?></span>
          <?php foreach ($weeks as $col):
              $cell = $col[$d] ?? null;
              if ($cell === null) { echo '<span class="heat-cell heat-none"></span>'; continue; }
              $t = $cell['n'] === 0
                 ? 'Nothing on ' . date('j M Y', strtotime($cell['date']))
                 : $cell['n'] . ' on ' . date('j M Y', strtotime($cell['date']));
          ?>
            <span class="heat-cell heat-l<?= (int) $cell['level'] ?>"
                  title="<?= e($t) ?>"></span>
          <?php endforeach; ?>
        </div>
      <?php endfor; ?>
    </div>
    <div class="heat-key">
      <span class="muted small">Quieter</span>
      <?php for ($l = 0; $l <= 4; $l++): ?>
        <span class="heat-cell heat-l<?= $l ?>"></span>
      <?php endfor; ?>
      <span class="muted small">Busier</span>
    </div>
  <?php endif; ?>
</div>
    <?php
    return (string) ob_get_clean();
}




























function cm_insight_today(?string $day = null): array
{
    $day = $day !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) ? $day : date('Y-m-d');

    $rows = [];
    try {
        $rows = cm_all(
            "SELECT o.id, o.est_minutes, o.status,
                    " . cm_occurrence_parent_minutes_sql('a', 'j') . " AS action_minutes,
                    j.id AS job_id, j.amount_cents
               FROM action_occurrence o
               LEFT JOIN job_action a ON a.id = o.action_id
               JOIN job j ON j.id = o.job_id
              WHERE o.scheduled_date = ? AND j.status = 'active'
                AND o.status IN ('scheduled', 'in_progress', 'completed')", [$day]);
    } catch (Throwable $ex) {

    }



    $spread = [];
    $tasks = $done = $minutes = $minutesDone = 0;
    $valueTodo = $valueDone = $unpriced = 0;

    foreach ($rows as $r) {
        $isDone = (string) $r['status'] === 'completed';
        $mins = cm_occurrence_minutes($r);

        if ($isDone) { $done++; $minutesDone += $mins; }
        else         { $tasks++; $minutes += $mins; }

        $jid = (int) $r['job_id'];
        $amount = (int) $r['amount_cents'];
        if ($amount <= 0) {
            if (!$isDone) $unpriced++;
            continue;
        }



        if (!isset($spread[$jid])) {
            $spread[$jid] = max(1, (int) cm_val(
                "SELECT COUNT(*) FROM action_occurrence o
                  WHERE o.job_id = ? AND o.status <> 'rescheduled'", [$jid]));
        }
        $share = (int) round($amount / $spread[$jid]);
        if ($isDone) $valueDone += $share; else $valueTodo += $share;
    }




    $appts = $apptMins = $offMins = 0;
    try {
        foreach (cm_all('SELECT start_time, end_time, is_work FROM appointment WHERE on_date = ?',
                        [$day]) as $ap) {
            $s = substr((string) $ap['start_time'], 0, 5);
            $e = substr((string) $ap['end_time'], 0, 5);
            if (!preg_match('/^\d{1,2}:\d{2}$/', $s) || !preg_match('/^\d{1,2}:\d{2}$/', $e)) continue;
            $len = max(0, cm_hm_minutes($e) - cm_hm_minutes($s));
            if ((int) $ap['is_work'] === 1) { $appts++; $apptMins += $len; }
            else { $offMins += $len; }
        }
    } catch (Throwable $ex) {

    }

    return [
        'tasks' => $tasks, 'done' => $done,
        'minutes' => $minutes, 'minutes_done' => $minutesDone,

        'value_cents' => $valueTodo + $valueDone,
        'value_todo' => $valueTodo, 'value_done' => $valueDone,
        'unpriced' => $unpriced, 'appts' => $appts, 'appts_minutes' => $apptMins,
        'offwork_minutes' => $offMins,
    ];
}
