<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0








declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }
























function cm_client_statuses(): array
{
    return [
        'prospect'  => ['label' => 'Prospect', 'pill' => 'pill-pending',
                        'blurb' => 'Talking, not yet working together.'],
        'active'    => ['label' => 'Active',   'pill' => 'pill-approved',
                        'blurb' => 'Work happens for this client.'],
        'paused'    => ['label' => 'Paused',   'pill' => 'pill-live',
                        'blurb' => 'On hold — nothing scheduled, nothing lost.'],
        'reminders' => ['label' => 'Reminders', 'pill' => 'pill-live',
                        'blurb' => 'Not active, but their reminders and follow-ups still run.'],
        'inactive'  => ['label' => 'Inactive', 'pill' => 'pill-spam',
                        'blurb' => 'No work and no reminders — every record kept. A client is never deleted.'],
    ];
}

function cm_client_status_valid(string $s): bool
{
    return isset(cm_client_statuses()[$s]);
}




function cm_client_open_reminder_count(int $id): int
{
    return (int) cm_val(
        "SELECT COUNT(*) FROM reminder
          WHERE status IN ('pending', 'snoozed')
            AND (client_id = ? OR job_id IN (SELECT id FROM job WHERE client_id = ?))",
        [$id, $id]);
}










function cm_client_status_cancel(int $id, string $old, string $new): int
{
    if ($new !== 'inactive' || $old === 'inactive') return 0;
    return cm_q(
        "UPDATE reminder SET status = 'cancelled', updated_at = ?
          WHERE status IN ('pending', 'snoozed')
            AND (client_id = ? OR job_id IN (SELECT id FROM job WHERE client_id = ?))",
        [cm_now(), $id, $id])->rowCount();
}








function cm_client_inactive_warning(int $id, string $current): string
{
    if ($current === 'inactive') return '';
    $n = 0;
    try { $n = cm_client_open_reminder_count($id); }
    catch (Throwable $ex) {  }
    if ($n === 0) return '';
    return 'This client has ' . $n . ' open reminder' . ($n === 1 ? '' : 's')
         . '. Making them Inactive cancels every one, follow-up emails included — they do '
         . 'not come back if the client is made active again. Save anyway?';
}



function cm_client(int $id): ?array
{
    if ($id <= 0) return null;
    return cm_row('SELECT * FROM client WHERE id = ?', [$id]);
}





function cm_client_display_name(array $c): string
{
    $name = trim(trim((string) $c['first_name']) . ' ' . trim((string) $c['last_name']));
    if ($name !== '') return $name;
    if (trim((string) $c['company']) !== '') return trim((string) $c['company']);
    if (trim((string) $c['email']) !== '') return trim((string) $c['email']);
    return 'Client #' . (int) $c['id'];
}




































function cm_client_picker(string $name, int $selected = 0, bool $required = false,
                          string $id = '', bool $roomy = false): string
{
    $id = $id !== '' ? $id : 'pick_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $chosen = $selected > 0 ? cm_client($selected) : null;
    $label  = $chosen ? cm_client_display_name($chosen) : '';

    return '<div class="picker' . ($chosen ? ' is-chosen' : '')
         . ($roomy ? ' picker-roomy' : '') . '" data-picker'
         . ' data-picker-url="' . e(cm_url('admin/client-pick.php')) . '">'
         . '<input type="hidden" name="' . e($name) . '" value="'
         . ($chosen ? (int) $selected : '') . '" data-picker-value>'
         . '<input type="text" class="f-full" id="' . e($id) . '"'
         . ' placeholder="Start typing a name, email, phone or website…"'
         . ' autocomplete="off" role="combobox" aria-autocomplete="list"'
         . ' aria-expanded="false" value="' . e($label) . '"'
         . ' data-picker-input' . ($required ? ' data-picker-required' : '') . '>'
         . '<ul class="picker-list" role="listbox" data-picker-list hidden></ul>'
         . '</div>';
}









function cm_client_search(string $term, string $status, int $page = 1, int $perPage = 50, bool $vipOnly = false): array
{
    $where  = [];
    $params = [];

    if ($status === '') {
        $where[] = "status <> 'inactive'";
    } elseif ($status !== 'all' && cm_client_status_valid($status)) {
        $where[] = 'status = ?';
        $params[] = $status;
    }


    if ($vipOnly) $where[] = 'vip = 1';

    $term = trim($term);
    if ($term !== '') {
        $like = '%' . $term . '%';





        $where[] = '(first_name LIKE ? OR last_name LIKE ? OR company LIKE ? OR email LIKE ?'
                 . ' OR phone LIKE ? OR tags LIKE ? OR notes LIKE ?'
                 . " OR CONCAT(first_name, ' ', last_name) LIKE ?"
                 . ' OR id IN (SELECT client_id FROM client_asset'
                 . '           WHERE url LIKE ? OR handle LIKE ? OR name LIKE ?))';
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like,
                            $like, $like, $like);
    }

    $sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $total = (int) cm_val('SELECT COUNT(*) FROM client' . $sql, $params);
    $rows = cm_all(
        'SELECT * FROM client' . $sql
        . ' ORDER BY updated_at DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (($page - 1) * $perPage),
        $params
    );
    return ['rows' => $rows, 'total' => $total];
}






































function cm_core_detail_spec(): array
{
    return [
        'business_description' => ['label' => 'About the business', 'job_panel' => true],
        'anything_else'        => ['label' => 'Anything else to keep in mind', 'job_panel' => true],
    ];
}





function cm_core_details(int $clientId): array
{
    $out = [];
    foreach (cm_all('SELECT dkey, dval FROM client_core_detail WHERE client_id = ?', [$clientId]) as $r) {
        $k = (string) $r['dkey'];
        if ($k !== '' && $k[0] === '_') continue;
        $out[$k] = (string) $r['dval'];
    }
    return $out;
}
























function cm_core_panel_keys(int $clientId): array
{
    return array_keys(array_filter(cm_core_detail_spec(),
        static fn ($m) => !empty($m['job_panel'])));
}






function cm_core_detail_set(int $clientId, string $key, string $value): void
{
    if (!isset(cm_core_detail_spec()[$key])) return;
    $value = trim($value);
    if ($value === '') {
        cm_q('DELETE FROM client_core_detail WHERE client_id = ? AND dkey = ?', [$clientId, $key]);
        return;
    }
    cm_q('INSERT INTO client_core_detail (client_id, dkey, dval, updated_at)
          VALUES (?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE dval = VALUES(dval), updated_at = VALUES(updated_at)',
         [$clientId, $key, $value, cm_now()]);
}




function cm_core_details_for_job_panel(int $clientId): array
{
    $stored = cm_core_details($clientId);
    $chosen = cm_core_panel_keys($clientId);
    $out = [];
    foreach (cm_core_detail_spec() as $key => $meta) {
        if (in_array($key, $chosen, true) && isset($stored[$key])) {
            $out[$key] = ['label' => $meta['label'], 'value' => $stored[$key]];
        }
    }
    return $out;
}



function cm_client_contacts(int $clientId): array
{
    return cm_all('SELECT * FROM client_contact WHERE client_id = ?
                   ORDER BY is_primary DESC, last_name, first_name, id', [$clientId]);
}

function cm_contact(int $id, int $clientId): ?array
{

    return cm_row('SELECT * FROM client_contact WHERE id = ? AND client_id = ?', [$id, $clientId]);
}






function cm_client_history(int $clientId, int $limit = 20): array
{
    return cm_all('SELECT * FROM audit_log WHERE target_type = ? AND target_id = ?
                   ORDER BY id DESC LIMIT ' . (int) $limit, ['client', $clientId]);
}
















function cm_client_tags_all(int $limit = 24): array
{
    $counts = [];
    foreach (cm_all('SELECT tags FROM client') as $row) {
        foreach (explode(',', (string) $row['tags']) as $tag) {
            $tag = trim($tag);
            if ($tag === '') continue;
            $key = mb_strtolower($tag);
            if (!isset($counts[$key])) $counts[$key] = ['tag' => $tag, 'n' => 0];
            $counts[$key]['n']++;
        }
    }
    uasort($counts, static function (array $a, array $b): int {
        return $b['n'] <=> $a['n'] ?: strcasecmp($a['tag'], $b['tag']);
    });
    return array_slice(array_column($counts, 'tag'), 0, $limit);
}


function cm_client_touch(int $clientId): void
{
    cm_q('UPDATE client SET updated_at = ? WHERE id = ?', [cm_now(), $clientId]);
}














function cm_client_context_cards(array $client): void
{
    $cid   = (int) $client['id'];
    $panel = cm_core_details_for_job_panel($cid);
    $core  = cm_url('admin/client.php?id=' . $cid . '&tab=core');
    $all   = cm_url('admin/client.php?id=' . $cid);
    ?>
  <div class="card">
    <h2 class="t-ui">Client brief</h2>
    <?php if (!$panel): ?>
      <?php 





 ?>
      <p class="muted small">No company Core Details yet — <a href="<?= e($core) ?>">Add</a></p>
    <?php else: ?>
      <?php 


 ?>
      <?php foreach ($panel as $key => $f): ?>
        <p class="side-fact"><b<?= str_contains((string) $key, 'avoid') ? ' class="side-avoid"' : '' ?>><?=
          e($f['label']) ?>:</b> <?= e($f['value']) ?></p>
      <?php endforeach; ?>
      <p class="hint mt-10"><a href="<?= e($core) ?>">Edit the Core Details</a></p>
    <?php endif; ?>
  </div>
  <?php 






 ?>
  <div class="card<?= (int) ($client['vip'] ?? 0) === 1 ? ' is-vip' : '' ?>">
    <h2 class="t-ui">Client contact</h2>
    <p class="small">
      <?php if ((string) $client['email'] !== ''): ?><span class="mono small"><?= e((string) $client['email']) ?></span><br><?php endif; ?>
      <?php if ((string) $client['phone'] !== ''): ?><span class="mono small"><?= e((string) $client['phone']) ?></span><br><?php endif; ?>
      <a href="<?= e($all) ?>">All contacts</a>
    </p>
  </div>
    <?php
}










function cm_flow_chart_html(int $current, ?int $clientId = null): string
{
    $steps = [
        1 => ['Create a client',  'Who the work is for.',
              null],
        2 => ['Add properties',   'Their website, pages and accounts — with sign-ins.',
              $clientId ? cm_url('admin/client.php?id=' . $clientId . '&tab=assets') : null],
        3 => ['Create a job',     'What you do for them, and how often.',
              $clientId ? cm_url('admin/client.php?id=' . $clientId . '#jobs') : null],
    ];
    $h = '<div class="card flow-card"><h3>How it goes</h3><ol class="flow">';
    foreach ($steps as $n => [$label, $blurb, $href]) {
        $cls = 'flow-step' . ($n === $current ? ' is-now' : ($n < $current ? ' is-done' : ''));
        $h .= '<li class="' . $cls . '">'
            . '<span class="flow-n">' . $n . '</span>'
            . '<span class="flow-body"><b>'
            . ($href !== null && $n !== $current ? '<a href="' . e($href) . '">' . e($label) . '</a>' : e($label))
            . '</b><span class="flow-blurb">' . e($blurb) . '</span>'
            . ($n === $current ? '<span class="flow-here">You are here</span>' : '')
            . '</span></li>';
        if ($n < 3) $h .= '<li class="flow-arrow" aria-hidden="true">&darr;</li>';
    }
    return $h . '</ol></div>';
}












function cm_core_save_from_post(int $clientId, string $actor, bool $withPanel): int
{
    $changed = 0;
    $before  = cm_core_details($clientId);
    foreach (array_keys(cm_core_detail_spec()) as $key) {
        if (!isset($_POST['cd_' . $key])) continue;
        $new = trim((string) $_POST['cd_' . $key]);
        if (($before[$key] ?? '') === $new) continue;
        cm_core_detail_set($clientId, $key, $new);
        $changed++;
    }




    if ($changed > 0) {
        cm_client_touch($clientId);
        cm_audit('client.core_updated', 'client', $clientId,
                 $changed . ' Core Detail field(s) changed by ' . $actor);
    }
    return $changed;
}











function cm_core_brief_window_html(int $clientId, string $clientName): string
{
    $spec = cm_core_detail_spec();
    $keys = cm_core_panel_keys($clientId);
    $core = cm_core_details($clientId);
    $rest = count($spec) - count($keys);
    ob_start(); ?>
  <dialog class="modal modal-wide" id="dlg-brief">
    <div class="modal-box">
      <div class="modal-head">
        <h2>The brief for <?= e($clientName) ?></h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">What the work gets done from &mdash; these are the
      fields that sit beside every job and on the work card. Fill in what you
      know; anything left empty is simply not stored.</p>
      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="save_core">
        <?php foreach ($keys as $key): $meta = $spec[$key] ?? null; if ($meta === null) continue; ?>
          <div class="row">
            <label class="lbl" for="cd_<?= e($key) ?>"><?= e($meta['label']) ?></label>
            <textarea id="cd_<?= e($key) ?>" name="cd_<?= e($key) ?>"
                      rows="<?= isset($core[$key]) && substr_count($core[$key], "\n") > 1 ? 5 : 3 ?>"><?= e($core[$key] ?? '') ?></textarea>
          </div>
        <?php endforeach; ?>
        <?php if ($rest > 0): ?>
          <p class="hint">The other <?= $rest ?> fields &mdash; hours, competitors,
          who approves, and the rest &mdash; are on their
          <a href="<?= e(cm_url('admin/client.php?id=' . $clientId . '&tab=core')) ?>">Core tab</a>, for later.</p>
        <?php endif; ?>
        <div class="modal-foot">
          <button class="btn" type="submit">Save the brief</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>
<?php
    return (string) ob_get_clean();
}






















function cm_countries(): array
{
    return [
        'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola',
        'Antigua and Barbuda', 'Argentina', 'Armenia', 'Australia', 'Austria',
        'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados',
        'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia',
        'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria',
        'Burkina Faso', 'Burundi', 'Cambodia', 'Cameroon', 'Canada',
        'Cape Verde', 'Central African Republic', 'Chad', 'Chile', 'China',
        'Colombia', 'Comoros', 'Congo', 'Congo (DRC)', 'Costa Rica',
        'Croatia', 'Cuba', 'Cyprus', 'Czechia', 'Denmark', 'Djibouti',
        'Dominica', 'Dominican Republic', 'Ecuador', 'Egypt', 'El Salvador',
        'Equatorial Guinea', 'Eritrea', 'Estonia', 'Eswatini', 'Ethiopia',
        'Fiji', 'Finland', 'France', 'Gabon', 'Gambia', 'Georgia', 'Germany',
        'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guinea-Bissau',
        'Guyana', 'Haiti', 'Honduras', 'Hong Kong', 'Hungary', 'Iceland',
        'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy',
        'Ivory Coast', 'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya',
        'Kiribati', 'Kosovo', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia',
        'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein',
        'Lithuania', 'Luxembourg', 'Macau', 'Madagascar', 'Malawi',
        'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands',
        'Mauritania', 'Mauritius', 'Mexico', 'Micronesia', 'Moldova',
        'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique',
        'Myanmar', 'Namibia', 'Nauru', 'Nepal', 'Netherlands', 'New Zealand',
        'Nicaragua', 'Niger', 'Nigeria', 'North Korea', 'North Macedonia',
        'Norway', 'Oman', 'Pakistan', 'Palau', 'Palestine', 'Panama',
        'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland',
        'Portugal', 'Qatar', 'Romania', 'Russia', 'Rwanda',
        'Saint Kitts and Nevis', 'Saint Lucia',
        'Saint Vincent and the Grenadines', 'Samoa', 'San Marino',
        'Sao Tome and Principe', 'Saudi Arabia', 'Senegal', 'Serbia',
        'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia',
        'Solomon Islands', 'Somalia', 'South Africa', 'South Korea',
        'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden',
        'Switzerland', 'Syria', 'Taiwan', 'Tajikistan', 'Tanzania',
        'Thailand', 'Timor-Leste', 'Togo', 'Tonga', 'Trinidad and Tobago',
        'Tunisia', 'Turkey', 'Turkmenistan', 'Tuvalu', 'Uganda', 'Ukraine',
        'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay',
        'Uzbekistan', 'Vanuatu', 'Vatican City', 'Venezuela', 'Vietnam',
        'Yemen', 'Zambia', 'Zimbabwe',
    ];
}










function cm_country_datalist_html(): string
{
    static $drawn = false;
    if ($drawn) return '';
    $drawn = true;

    $out = '<datalist id="cm-countries">';
    foreach (cm_countries() as $c) {
        $out .= '<option value="' . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '"></option>';
    }
    return $out . '</datalist>';
}
