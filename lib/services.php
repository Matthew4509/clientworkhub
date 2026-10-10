<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0















declare(strict_types=1);

if (!defined('CM_ROOT')) { http_response_code(404); exit; }

require_once CM_LIB . '/format.php';
require_once CM_LIB . '/tax.php';


function cm_service_categories(): array
{
    try { return cm_all('SELECT * FROM service_category ORDER BY sort, name'); }
    catch (Throwable $ex) { return []; }
}


function cm_service_category_map(): array
{
    $out = [];
    foreach (cm_service_categories() as $c) $out[(int) $c['id']] = (string) $c['name'];
    return $out;
}


function cm_service_category_ensure(string $name): int
{
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    if ($name === '') return 0;
    $name = mb_substr($name, 0, 80);

    $row = cm_row('SELECT id FROM service_category WHERE name = ?', [$name]);
    if ($row) return (int) $row['id'];
    try {
        return cm_insert('INSERT INTO service_category (name, sort, created_at) VALUES (?, ?, ?)',
                         [$name, 100, cm_now()]);
    } catch (Throwable $ex) {
        $row = cm_row('SELECT id FROM service_category WHERE name = ?', [$name]);
        return $row ? (int) $row['id'] : 0;
    }
}








function cm_services_grouped(array $services): array
{
    $names  = cm_service_category_map();
    $groups = []; $loose = [];
    foreach ($services as $s) {
        $cid = (int) ($s['category_id'] ?? 0);
        if ($cid > 0 && isset($names[$cid])) $groups[$cid][] = $s;
        else                                 $loose[] = $s;
    }
    $out = [];
    foreach ($names as $cid => $name) {
        if (!empty($groups[$cid])) $out[] = ['id' => $cid, 'name' => $name, 'services' => $groups[$cid]];
    }
    if ($loose) $out[] = ['id' => 0, 'name' => $out ? 'Everything else' : '', 'services' => $loose];
    return $out;
}







function cm_service_summary(array $t): string
{
    $bits = [];

    if ((int) $t['amount_cents'] > 0) {
        $freq = cm_billing_frequencies()[(string) $t['billing_freq']] ?? '';
        $bits[] = cm_money((int) $t['amount_cents'])
                . ($freq !== '' ? ' ' . mb_strtolower($freq) : '');
    }
    if ((int) $t['duration_days'] > 0) {
        $bits[] = (int) $t['duration_days'] . ' working day'
                . ((int) $t['duration_days'] === 1 ? '' : 's');
    }
    if ((int) $t['per_day_minutes'] > 0) {
        $bits[] = cm_minutes_label((int) $t['per_day_minutes']) . ' a day';
    } elseif ((int) $t['total_minutes'] > 0) {
        $bits[] = cm_minutes_label((int) $t['total_minutes']) . ' spread';
    }
    if ((int) $t['setup_minutes'] > 0) {
        $bits[] = '+' . cm_minutes_label((int) $t['setup_minutes']) . ' setup';
    }
    if ((int) $t['completion_minutes'] > 0) {
        $bits[] = '+' . cm_minutes_label((int) $t['completion_minutes'])
                . ((string) $t['completion_mode'] === 'own_day' ? ' wrap-up day' : ' wrap-up');
    }

    return $bits ? implode(' · ', $bits) : 'No price or time set yet';
}










function cm_service_save(int $id, array $in): string
{
    if (!cm_row('SELECT id FROM job_template WHERE id = ?', [$id])) {
        return 'That service no longer exists.';
    }

    $name = trim((string) ($in['name'] ?? ''));
    if ($name === '')           return 'Give it a name.';
    if (mb_strlen($name) > 160) return 'That name is too long (160 characters maximum).';

    $amt = trim((string) ($in['amount'] ?? ''));
    if ($amt !== '' && !preg_match('/^\d+(\.\d{1,2})?$/', $amt)) {
        return 'The price: a plain amount, like 250 or 199.50.';
    }

    $num = static function ($v): int {
        $v = trim((string) $v);
        return $v !== '' && ctype_digit($v) ? (int) $v : 0;
    };






    $timePosted = array_key_exists('per_day_minutes', $in) || array_key_exists('total_hours', $in);
    $time = cm_service_time_read($in);
    if ($time['problem'] !== '') return $time['problem'];







    $freqPosted = array_key_exists('billing_freq', $in);
    $freq = isset(cm_billing_frequencies()[(string) ($in['billing_freq'] ?? '')])
          ? (string) $in['billing_freq'] : 'oneoff';
    $mode = (string) ($in['completion_mode'] ?? 'last_day') === 'own_day' ? 'own_day' : 'last_day';



    $catId  = (int) ($in['category_id'] ?? 0);
    $newCat = trim((string) ($in['category_new'] ?? ''));
    if ($newCat !== '') {
        $catId = cm_service_category_ensure($newCat);
    } elseif ($catId > 0 && !isset(cm_service_category_map()[$catId])) {
        $catId = 0;
    }








    $runFreq = '';
    if (array_key_exists('frequency', $in)) {







        require_once CM_LIB . '/jobs.php';
        $runFreq = isset(cm_action_frequencies()[(string) $in['frequency']])
                 ? (string) $in['frequency'] : '';
    }







    $durationIn = $num($in['duration_days'] ?? '');
    if ($runFreq === 'oneoff') $durationIn = 1;

    cm_q('UPDATE job_template
             SET name = ?, category_id = ?, amount_cents = ?, taxable = ?,
                 duration_days = ?,
                 setup_minutes = ?, completion_minutes = ?, completion_mode = ?, updated_at = ?'
         . ($freqPosted ? ', billing_freq = ?' : '')
         . (array_key_exists('frequency', $in) ? ', frequency = ?' : '') . '
           WHERE id = ?',
         array_merge(
           [$name, $catId > 0 ? $catId : null,
            $amt !== '' ? (int) round((float) $amt * 100) : 0,
            cm_tax_charges() ? (!empty($in['taxable']) ? 1 : 0) : 1,
            min(365, $durationIn),
            $num($in['setup_minutes'] ?? ''), $num($in['completion_minutes'] ?? ''), $mode,
            cm_now()],
           $freqPosted ? [$freq] : [],
           array_key_exists('frequency', $in) ? [$runFreq] : [],
           [$id]));
    if ($timePosted) cm_service_time_set($id, $time);












    if (array_key_exists('snippet_category', $in)) {
        require_once CM_LIB . '/snippets.php';
        $cat = (string) $in['snippet_category'];
        cm_template_snippets_set($id, $cat === 'all'
            ? [] : array_map('intval', (array) ($in['snippet_ids'] ?? [])));
    }



    if (array_key_exists('public_blurb', $in)) {
        cm_q('UPDATE job_template SET public_blurb = ? WHERE id = ?',
             [trim((string) $in['public_blurb']), $id]);
    }

    return '';
}















function cm_service_siblings(?int $categoryId): array
{
    $sql = 'SELECT id, sort, name FROM job_template WHERE '
         . ($categoryId === null ? 'category_id IS NULL' : 'category_id = ?')
         . ' ORDER BY sort, name, id';
    return cm_all($sql, $categoryId === null ? [] : [$categoryId]);
}










function cm_service_renumber(?int $categoryId): array
{
    $rows = cm_service_siblings($categoryId);
    $n = 0;
    foreach ($rows as $i => $r) {
        $n += 10;
        if ((int) $r['sort'] !== $n) {
            cm_q('UPDATE job_template SET sort = ? WHERE id = ?', [$n, (int) $r['id']]);
        }
        $rows[$i]['sort'] = $n;
    }
    return $rows;
}






function cm_service_move(int $id, string $dir): bool
{
    $t = cm_row('SELECT id, category_id FROM job_template WHERE id = ?', [$id]);
    if ($t === null) return false;

    $cid  = $t['category_id'] === null ? null : (int) $t['category_id'];
    $rows = cm_service_renumber($cid);

    $at = null;
    foreach ($rows as $i => $r) if ((int) $r['id'] === $id) { $at = $i; break; }
    if ($at === null) return false;

    $to = $dir === 'up' ? $at - 1 : $at + 1;
    if ($to < 0 || $to >= count($rows)) return false;



    cm_q('UPDATE job_template SET sort = ? WHERE id = ?', [(int) $rows[$to]['sort'], $id]);
    cm_q('UPDATE job_template SET sort = ? WHERE id = ?', [(int) $rows[$at]['sort'], (int) $rows[$to]['id']]);
    return true;
}








function cm_service_set_category(int $id, ?int $categoryId): bool
{
    $t = cm_row('SELECT id, category_id FROM job_template WHERE id = ?', [$id]);
    if ($t === null) return false;
    $was = $t['category_id'] === null ? null : (int) $t['category_id'];
    if ($was === $categoryId) return false;
    if ($categoryId !== null && !isset(cm_service_category_map()[$categoryId])) return false;

    $rows = cm_service_renumber($categoryId);
    $last = $rows ? (int) $rows[count($rows) - 1]['sort'] : 0;
    cm_q('UPDATE job_template SET category_id = ?, sort = ?, updated_at = ? WHERE id = ?',
         [$categoryId, $last + 10, cm_now(), $id]);


    cm_service_renumber($was);
    return true;
}









function cm_service_time_read(array $in): array
{
    $perDayRaw = trim((string) ($in['per_day_minutes'] ?? ''));
    $perDay = $perDayRaw !== '' && ctype_digit($perDayRaw) ? (int) $perDayRaw : 0;

    $hours = trim((string) ($in['total_hours'] ?? ''));
    $total = ($hours !== '' && preg_match('/^\d+(\.\d{1,2})?$/', $hours))
           ? (int) round((float) $hours * 60) : 0;

    return [
        'per_day' => $perDay,
        'total'   => $total,
        'problem' => $perDay > 0 && $total > 0
            ? 'Fill in time per day OR total time, not both — they answer the same question two ways.'
            : '',
    ];
}


function cm_service_time_set(int $id, array $time): void
{
    cm_q('UPDATE job_template SET per_day_minutes = ?, total_minutes = ?, updated_at = ? WHERE id = ?',
         [(int) $time['per_day'], (int) $time['total'], cm_now(), $id]);
}























function cm_service_time_breakdown(array $t): array
{




    require_once CM_LIB . '/jobs.php';

    $tid  = (int) ($t['id'] ?? 0);
    $days = max(1, (int) ($t['duration_days'] ?? 0));

    $fromTasks = 0;
    foreach (cm_template_actions($tid) as $a) {
        $fromTasks += (int) $a['est_minutes'];
    }

    $stored = (int) ($t['per_day_minutes'] ?? 0);
    if ($stored < 1) {
        $tot = (int) ($t['total_minutes'] ?? 0);
        $stored = $tot > 0 ? (int) ceil($tot / $days) : 0;
    }











    $perDay = $stored > 0 ? $stored : $fromTasks;
    $setup  = (int) ($t['setup_minutes'] ?? 0);
    $finish = (int) ($t['completion_minutes'] ?? 0);

    return [
        'per_day' => $perDay,
        'days'    => $days,
        'tasks'   => $perDay * $days,
        'setup'   => $setup,
        'finish'  => $finish,
        'total'   => $setup + $perDay * $days + $finish,
        'source'  => $fromTasks > 0 ? 'tasks' : 'service',





        'stale'   => $fromTasks < 1 && $stored > 0,
    ];
}









function cm_task_grid_html(string $idPrefix, int $rows = 5, bool $withHeader = true): string
{
    require_once CM_LIB . '/jobs.php';
    ob_start(); ?>
          <div class="taskgrid" id="taskgrid<?= e($idPrefix) ?>">
            <?php if ($withHeader): ?>
              <div class="taskgrid-head">
                <span>Task</span><span>How often</span><span>Minutes</span>
              </div>
            <?php endif; ?>
            <?php for ($r = 0; $r < $rows; $r++): ?>
              <div class="taskgrid-row" data-task-row>
                <input type="text" name="t_name[<?= $r ?>]" id="tn<?= e($idPrefix) ?>-r<?= $r ?>"
                       class="f-full" maxlength="160"
                       aria-label="Task <?= $r + 1 ?>"
                       placeholder="<?= $r === 0 ? 'Write and publish the post' : '' ?>">
                <select name="t_frequency[<?= $r ?>]" id="tf<?= e($idPrefix) ?>-r<?= $r ?>"
                        aria-label="How often, task <?= $r + 1 ?>">
                  <?php foreach (cm_action_frequencies() as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $k === 'daily' ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="text" name="t_minutes[<?= $r ?>]" id="tm<?= e($idPrefix) ?>-r<?= $r ?>"
                       class="f-xs" inputmode="numeric" aria-label="Minutes, task <?= $r + 1 ?>">
              </div>
            <?php endfor; ?>
          </div>
          <div class="actions mt-8">
            <button class="btn btn-quiet btn-sm" type="button"
                    data-task-add="taskgrid<?= e($idPrefix) ?>">+ Add another row</button>
            <span class="muted small ml-8">Empty rows are ignored.</span>
          </div>
<?php
    return (string) ob_get_clean();
}








function cm_service_tasks_save(int $templateId, array $post, int &$orphanMinutes = 0): int
{
    require_once CM_LIB . '/jobs.php';
    $names = (array) ($post['t_name'] ?? []);
    $freqs = (array) ($post['t_frequency'] ?? []);
    $minus = (array) ($post['t_minutes'] ?? []);

    $made = 0;
    $orphanMinutes = 0;
    foreach ($names as $i => $raw) {
        $name = trim((string) $raw);
        $mins = trim((string) ($minus[$i] ?? ''));
        if ($name === '') {
            if ($mins !== '' && $mins !== '0') $orphanMinutes++;
            continue;
        }
        $freq = (string) ($freqs[$i] ?? 'daily');
        if (!isset(cm_action_frequencies()[$freq])) $freq = 'daily';
        cm_insert('INSERT INTO job_template_action (template_id, name, frequency, anchor, est_minutes, required)
                   VALUES (?, ?, ?, ?, ?, ?)',
                  [$templateId, mb_substr($name, 0, 160), $freq, 'start',
                   ctype_digit($mins) ? (int) $mins : 0, 1]);
        $made++;
    }
    return $made;
}














function cm_service_time_clear(int $templateId): void
{
    cm_q('UPDATE job_template SET per_day_minutes = 0, total_minutes = 0, updated_at = ? WHERE id = ?',
         [cm_now(), $templateId]);
}
