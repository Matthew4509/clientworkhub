<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0















declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }



require_once CM_LIB . '/format.php';



const CM_ASSETS_PER_CLIENT = 99;



function cm_asset_types(): array
{
    return [
        'website'         => 'Website',
        'wordpress'       => 'WordPress admin',
        'facebook'        => 'Facebook',
        'instagram'       => 'Instagram',
        'youtube'         => 'YouTube',
        'tiktok'          => 'TikTok',
        'linkedin'        => 'LinkedIn',
        'google_business' => 'Google Business Profile',
        'domain'          => 'Domain registrar',
        'hosting'         => 'Hosting / cPanel',
        'email'           => 'Email platform',
        'other'           => 'Other',
    ];
}



function cm_property_kinds(): array
{
    return cm_property_default_kind_choices();
}
























function cm_property_columns_exist(): bool
{
    static $has = null;
    if ($has !== null) return $has;
    require_once CM_LIB . '/migrate.php';
    return $has = cm_schema_has_column('client_asset', 'property_kind')
               && cm_schema_has_column('client_asset', 'property_data');
}

function cm_property_template(string $kind): ?array
{
    if (!preg_match('/^custom_(\d+)$/', $kind, $m)) return null;
    try {
        $row = cm_row('SELECT * FROM property_template WHERE id=?', [(int) $m[1]]);
        if (!$row) return null;
        $fields = json_decode((string) $row['fields_json'], true);
        $row['fields'] = is_array($fields) ? $fields : [];
        return $row;
    } catch (Throwable $ex) { return null; }
}

function cm_property_templates(): array
{
    try {
        $rows=cm_all('SELECT * FROM property_template ORDER BY name');
        foreach($rows as &$row){$fields=json_decode((string)$row['fields_json'],true);$row['fields']=is_array($fields)?$fields:[];}
        unset($row); return $rows;
    } catch(Throwable $ex){return [];}
}

function cm_property_default_kind(): string
{
    $kind = (string) cm_setting('property.default_kind', 'web');
    return isset(cm_property_kinds()[$kind]) ? $kind : 'web';
}

function cm_property_data_decode(?string $stored): array
{
    if ($stored === null || trim($stored) === '') return [];
    $data = json_decode($stored, true);
    return is_array($data) ? $data : [];
}

function cm_property_data_encode(array $data): ?string
{
    $data = array_filter($data, static fn($v) => $v !== '' && $v !== null && $v !== false);
    if (!$data) return null;
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $json === false ? null : $json;
}

function cm_property_data_from_post(string $prefix, string $kind): array
{
    $fields = ['service_address', 'suburb', 'access_notes'];
    if ($kind === 'piano') {
        $fields = array_merge($fields, ['make', 'model', 'serial_number', 'room_location',
            'damp_chaser', 'overall_condition', 'last_tuned', 'tuning_frequency', 'technician_notes']);
    } elseif ($kind === 'salon') {
        $fields = array_merge($fields, ['preferred_stylist', 'usual_service', 'colour_formula',
            'hair_length', 'last_visit', 'salon_notes']);
    } elseif ($template = cm_property_template($kind)) {
        foreach ($template['fields'] as $field) {
            if (!empty($field['key'])) $fields[] = (string) $field['key'];
        }
    }
    $out = [];
    foreach ($fields as $field) {
        $out[$field] = mb_substr(trim((string) ($_POST[$prefix . 'property_' . $field] ?? '')), 0, 1000);
    }
    if ($kind === 'piano') {
        $out['condition_action'] = isset($_POST[$prefix . 'property_condition_action']);
        $out['condition_outside'] = isset($_POST[$prefix . 'property_condition_outside']);
    }
    return $out;
}

function cm_property_fields_html(string $prefix, string $pickedKind, array $values = []): string
{
    $value = static function (string $field) use ($prefix, $values): string {
        return e((string) ($_POST[$prefix . 'property_' . $field] ?? $values[$field] ?? ''));
    };
    $checked = static function (string $field) use ($prefix, $values): bool {
        return isset($_POST[$prefix . 'property_' . $field]) || (!$_POST && !empty($values[$field]));
    };
    ob_start(); ?>
    <div data-property-fields="shared"<?= $pickedKind === 'web' ? ' hidden' : '' ?>>
      <h3 class="form-section-title">Location and access</h3>
      <div class="form-2col">
        <div class="form-col"><div class="row"><label class="lbl">Service address</label><input class="f-full" name="<?= e($prefix) ?>property_service_address" maxlength="255" value="<?= $value('service_address') ?>"></div></div>
        <div class="form-col"><div class="row"><label class="lbl">Suburb or town</label><input class="f-full" name="<?= e($prefix) ?>property_suburb" maxlength="120" value="<?= $value('suburb') ?>"></div></div>
      </div>
      <div class="row"><label class="lbl">Access notes</label><textarea name="<?= e($prefix) ?>property_access_notes" rows="2" placeholder="Parking, gate, keys, who to ask for."><?= $value('access_notes') ?></textarea></div>
    </div>
    <div data-property-fields="piano"<?= $pickedKind !== 'piano' ? ' hidden' : '' ?>>
      <h3 class="form-section-title">Piano details</h3>
      <div class="form-2col">
        <div class="form-col"><div class="row"><label class="lbl">Make</label><input class="f-full" name="<?= e($prefix) ?>property_make" maxlength="120" value="<?= $value('make') ?>"></div></div>
        <div class="form-col"><div class="row"><label class="lbl">Model</label><input class="f-full" name="<?= e($prefix) ?>property_model" maxlength="120" value="<?= $value('model') ?>"></div></div>
        <div class="form-col"><div class="row"><label class="lbl">Serial number</label><input class="f-full" name="<?= e($prefix) ?>property_serial_number" maxlength="120" value="<?= $value('serial_number') ?>"></div></div>
        <div class="form-col"><div class="row"><label class="lbl">Room location</label><input class="f-full" name="<?= e($prefix) ?>property_room_location" maxlength="160" value="<?= $value('room_location') ?>" placeholder="Front room, upstairs studio"></div></div>
        <div class="form-col"><div class="row"><label class="lbl">Damp-Chaser installed</label><select class="f-full" name="<?= e($prefix) ?>property_damp_chaser"><option value="">Not recorded</option><option value="yes"<?= $value('damp_chaser') === 'yes' ? ' selected' : '' ?>>Yes</option><option value="no"<?= $value('damp_chaser') === 'no' ? ' selected' : '' ?>>No</option></select></div></div>
        <div class="form-col"><div class="row"><label class="lbl">Overall condition</label><select class="f-full" name="<?= e($prefix) ?>property_overall_condition"><option value="">Not assessed</option><?php foreach (['excellent'=>'Excellent','good'=>'Good','fair'=>'Fair','poor'=>'Poor'] as $k=>$v): ?><option value="<?= e($k) ?>"<?= $value('overall_condition') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div></div>
        <div class="form-col"><div class="row"><label class="lbl">Last tuned</label><input type="date" class="f-full" name="<?= e($prefix) ?>property_last_tuned" value="<?= $value('last_tuned') ?>"></div></div>
        <div class="form-col"><div class="row"><label class="lbl">Usual tuning frequency</label><input class="f-full" name="<?= e($prefix) ?>property_tuning_frequency" maxlength="80" value="<?= $value('tuning_frequency') ?>" placeholder="Every 6 months"></div></div>
      </div>
      <div class="row"><span class="lbl">Condition checklist</span><label class="check"><input type="checkbox" name="<?= e($prefix) ?>property_condition_action" value="1"<?= $checked('condition_action') ? ' checked' : '' ?>> Action needs attention</label> <label class="check"><input type="checkbox" name="<?= e($prefix) ?>property_condition_outside" value="1"<?= $checked('condition_outside') ? ' checked' : '' ?>> Outside/case needs attention</label></div>
      <div class="row"><label class="lbl">Technician notes</label><textarea name="<?= e($prefix) ?>property_technician_notes" rows="2"><?= $value('technician_notes') ?></textarea></div>
    </div>
    <div data-property-fields="salon"<?= $pickedKind !== 'salon' ? ' hidden' : '' ?>>
      <h3 class="form-section-title">Salon details</h3>
      <div class="form-2col">
        <div class="form-col"><div class="row"><label class="lbl">Preferred stylist</label><input class="f-full" name="<?= e($prefix) ?>property_preferred_stylist" maxlength="120" value="<?= $value('preferred_stylist') ?>"></div></div>
        <div class="form-col"><div class="row"><label class="lbl">Usual service</label><input class="f-full" name="<?= e($prefix) ?>property_usual_service" maxlength="160" value="<?= $value('usual_service') ?>" placeholder="Cut and colour"></div></div>
        <div class="form-col"><div class="row"><label class="lbl">Hair length</label><select class="f-full" name="<?= e($prefix) ?>property_hair_length"><option value="">Not recorded</option><?php foreach (['short'=>'Short','medium'=>'Medium','long'=>'Long','extra_long'=>'Extra long'] as $k=>$v): ?><option value="<?= e($k) ?>"<?= $value('hair_length') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div></div>
        <div class="form-col"><div class="row"><label class="lbl">Last visit</label><input type="date" class="f-full" name="<?= e($prefix) ?>property_last_visit" value="<?= $value('last_visit') ?>"></div></div>
      </div>
      <div class="row"><label class="lbl">Colour / formula notes</label><textarea name="<?= e($prefix) ?>property_colour_formula" rows="2" placeholder="Products, shades and mixture used."><?= $value('colour_formula') ?></textarea></div>
      <div class="row"><label class="lbl">Service notes</label><textarea name="<?= e($prefix) ?>property_salon_notes" rows="2"><?= $value('salon_notes') ?></textarea></div>
    </div>
    <?php foreach (cm_property_templates() as $template): $templateKind='custom_'.(int)$template['id']; ?>
    <div data-property-fields="<?= e($templateKind) ?>"<?= $pickedKind !== $templateKind ? ' hidden' : '' ?>>
      <h3 class="form-section-title"><?= e((string) $template['name']) ?> details</h3>
      <div class="form-2col">
      <?php foreach ($template['fields'] as $field): $key=(string)($field['key']??''); $label=(string)($field['label']??''); $type=(string)($field['type']??'text'); if($key===''||$label==='')continue; ?>
        <div class="form-col"><div class="row"><label class="lbl"><?= e($label) ?></label>
        <?php if ($type === 'textarea'): ?><textarea class="f-full" rows="2" name="<?= e($prefix) ?>property_<?= e($key) ?>"><?= $value($key) ?></textarea>
        <?php elseif ($type === 'date'): ?><input class="f-full" type="date" name="<?= e($prefix) ?>property_<?= e($key) ?>" value="<?= $value($key) ?>">
        <?php elseif ($type === 'number'): ?><input class="f-full" type="number" step="any" name="<?= e($prefix) ?>property_<?= e($key) ?>" value="<?= $value($key) ?>">
        <?php else: ?><input class="f-full" maxlength="255" name="<?= e($prefix) ?>property_<?= e($key) ?>" value="<?= $value($key) ?>"><?php endif; ?>
        </div></div>
      <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php return (string) ob_get_clean();
}

function cm_property_kind_script_once(): string
{


    return '';
}

function cm_asset_statuses(): array
{
    return [
        'active'    => ['label' => 'Active',    'pill' => 'pill-approved',
                        'blurb' => 'In use — jobs can involve it.'],
        'inactive'  => ['label' => 'Inactive',  'pill' => 'pill-pending',
                        'blurb' => 'Exists but nothing happens on it right now.'],










        'suspended' => ['label' => 'Suspended', 'pill' => 'pill-spam',
                        'do_label' => 'Suspend + remove password',
                        'erases_password' => true,
                        'confirm' => 'Suspend this property and REMOVE its stored password? '
                                   . 'The username, secure notes and recovery details stay. '
                                   . 'The password cannot be read back afterwards.',
                        'blurb' => 'The platform has restricted it — worth knowing before opening a job.'],
        'archived'  => ['label' => 'Archived',  'pill' => 'pill-spam',
                        'blurb' => 'Gone, records kept. An asset is never deleted.'],
    ];
}




























function cm_asset_categories_on(): bool
{
    return false;
}


function cm_asset_categories(): array
{
    if (!cm_asset_categories_on()) return [];
    try {
        return cm_all('SELECT * FROM asset_category ORDER BY sort, name');
    } catch (Throwable $ex) {
        return [];
    }
}









function cm_asset_category_ensure(string $name): int
{
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    if ($name === '') return 0;
    $name = mb_substr($name, 0, 80);

    $existing = cm_row('SELECT id FROM asset_category WHERE name = ?', [$name]);
    if ($existing) return (int) $existing['id'];

    try {
        return cm_insert('INSERT INTO asset_category (name, sort, created_at) VALUES (?, ?, ?)',
                         [$name, 100, cm_now()]);
    } catch (Throwable $ex) {


        $row = cm_row('SELECT id FROM asset_category WHERE name = ?', [$name]);
        return $row ? (int) $row['id'] : 0;
    }
}


function cm_asset_category_map(): array
{
    $out = [];
    foreach (cm_asset_categories() as $c) $out[(int) $c['id']] = (string) $c['name'];
    return $out;
}









function cm_assets_grouped(array $assets): array
{
    $names  = cm_asset_category_map();
    $groups = [];
    $loose  = [];
    foreach ($assets as $a) {
        $cid = (int) ($a['category_id'] ?? 0);
        if ($cid > 0 && isset($names[$cid])) $groups[$cid][] = $a;
        else                                 $loose[] = $a;
    }
    $out = [];
    foreach ($names as $cid => $name) {
        if (!empty($groups[$cid])) $out[] = ['name' => $name, 'assets' => $groups[$cid]];
    }
    if ($loose) $out[] = ['name' => $out ? 'Everything else' : '', 'assets' => $loose];
    return $out;
}



function cm_asset(int $id): ?array
{
    if ($id <= 0) return null;
    return cm_row('SELECT * FROM client_asset WHERE id = ?', [$id]);
}


function cm_client_assets(int $clientId, bool $includeArchived = true): array
{
    $sql = 'SELECT a.*, (c.asset_id IS NOT NULL) AS has_credential, c.updated_at AS credential_updated,
                   c.password_strength AS credential_strength,
                   (c.password_enc IS NOT NULL) AS has_password
            FROM client_asset a
            LEFT JOIN client_credential c ON c.asset_id = a.id
            WHERE a.client_id = ?';
    if (!$includeArchived) $sql .= " AND a.status <> 'archived'";
    $sql .= ' ORDER BY a.status = \'archived\', a.name, a.id';
    return cm_all($sql, [$clientId]);
}

function cm_client_asset_count(int $clientId): int
{
    return (int) cm_val('SELECT COUNT(*) FROM client_asset WHERE client_id = ?', [$clientId]);
}


function cm_client_credential_last_update(int $clientId): ?string
{
    $v = cm_val('SELECT MAX(c.updated_at) FROM client_credential c
                 JOIN client_asset a ON a.id = c.asset_id
                 WHERE a.client_id = ?', [$clientId]);
    return $v !== null ? (string) $v : null;
}















function cm_credential_login_url(?array $cred, array $asset): string
{
    $stored = trim((string) ($cred['login_url'] ?? ''));
    if ($stored !== '') return $stored;
    return trim((string) ($asset['url'] ?? ''));
}





































function cm_asset_signin_html(array $a): string
{











    $head = cm_credential_tools_script_once();

    $id   = (int) $a['id'];
    $cred = cm_credential($id);
    $url  = trim((string) ($a['url'] ?? ''));
    $login = cm_credential_login_url($cred, $a);






























    $h = $head
       . '<div class="work-asset">'
       . '<div class="work-asset-h">'
       . '<b>' . e((string) $a['name']) . '</b>'
       . '<span class="spacer"></span>'





       . '<button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-property-edit-' . $id . '">Edit</button>'
       . '</div>';







    if (trim((string) ($a['notes'] ?? '')) !== '') {
        $h .= '<p class="small prewrap mt-4 m-0"><b>Property notes:</b> '
            . e((string) $a['notes']) . '</p>';
    }



    $h .= '<div class="work-asset-signin">';






    if ($url !== '') {
        $h .= '<a class="small" href="' . e(cm_safe_href($url)) . '" target="_blank" rel="noopener noreferrer">'
            . e(preg_replace('#^https?://#', '', $url)) . '</a>';
    }
    if ($login !== '' && $login !== $url) {
        $h .= '<a class="small" href="' . e(cm_safe_href($login)) . '" target="_blank" rel="noopener noreferrer">'
            . '<b>the sign-in page</b></a>';
    }
    if ($url === '' && $login === '') {







        $h .= '<span class="muted small">No address yet &mdash; '
            . '<a href="' . e(cm_url('admin/asset.php?id=' . $id)) . '">add one</a></span>';
    }

    if ($cred === null) {
        $h .= '<span class="muted small">No sign-in yet &mdash; '
            . '<a href="' . e(cm_url('admin/asset.php?id=' . $id)) . '">add it</a></span>';
    } else {
        if ((string) $cred['username'] !== '') {
            $h .= '<input type="hidden" id="au' . $id . '" value="' . e((string) $cred['username']) . '">'
                . '<button class="btn btn-sm btn-quiet" type="button" data-copy-for="au' . $id . '">Copy username</button>';
        }
        if ($cred['password_enc'] !== null) {
            $h .= '<button class="btn btn-sm btn-quiet" type="button" data-cred-copy="' . $id . '">Copy password</button>';




            $aw = cm_credential_warnings($cred);
            if ($aw) {
                $h .= '<span class="pill pill-spam" title="' . e(implode('; ', $aw)) . '">!</span>';
            }
        } else {
            $h .= '<span class="muted small">No password yet &mdash; '
                . '<a href="' . e(cm_url('admin/asset.php?id=' . $id)) . '">add one</a></span>';
        }
    }
    $h .= '</div>';



    $h .= '<div class="muted small prewrap mt-4" id="crednotes' . $id . '" hidden></div>';

    if ($cred !== null && trim((string) ($cred['notes'] ?? '')) !== '') {
        $h .= '<p class="muted small prewrap mt-4 m-0">' . e((string) $cred['notes']) . '</p>';
    }
    $h .= '</div>';



    $h .= cm_asset_edit_window_html($a, $cred, [], cm_url('admin/asset.php?id=' . $id),
                                    (string) ($_SERVER['REQUEST_URI'] ?? ''), 'dlg-property-edit-' . $id);
    return $h;
}

function cm_credential(int $assetId): ?array
{
    return cm_row('SELECT * FROM client_credential WHERE asset_id = ?', [$assetId]);
}









function cm_password_strength(string $password): int
{
    $len = mb_strlen($password);
    if ($len === 0) return 0;

    $lower = mb_strtolower($password);




    foreach (['password', 'qwerty', 'letmein', '123456', 'abc123', 'iloveyou', 'admin', 'welcome'] as $bad) {
        if (str_contains($lower, $bad)) return $len >= 20 ? 1 : 0;
    }
    if (preg_match('/^\d+$/', $password)) return $len >= 16 ? 1 : 0;

    $classes = (int) (bool) preg_match('/[a-z]/', $password)
             + (int) (bool) preg_match('/[A-Z]/', $password)
             + (int) (bool) preg_match('/\d/', $password)
             + (int) (bool) preg_match('/[^a-zA-Z\d]/', $password);

    if ($len >= 16 && $classes >= 3) return 3;
    if ($len >= 20) return 3;
    if ($len >= 12 && $classes >= 3) return 2;
    if ($len >= 14) return 2;
    if ($len >= 10 && $classes >= 2) return 1;
    return 0;
}

function cm_strength_label(int $strength): string
{
    return ['Weak', 'Fair', 'Good', 'Strong'][max(0, min(3, $strength))];
}


const CM_CREDENTIAL_STALE_DAYS = 90;







function cm_credential_warnings(?array $row): array
{
    if ($row === null) return [];
    $hasPassword = array_key_exists('password_enc', $row)
        ? $row['password_enc'] !== null
        : !empty($row['has_password']);
    if (!$hasPassword) return [];

    $updatedRaw = (string) ($row['credential_updated'] ?? $row['updated_at'] ?? '');
    $strength   = array_key_exists('password_strength', $row)
        ? $row['password_strength']
        : ($row['credential_strength'] ?? null);

    $out = [];
    $updated = strtotime($updatedRaw);
    if ($updated !== false && (time() - $updated) > CM_CREDENTIAL_STALE_DAYS * 86400) {
        $out[] = 'password not changed in over ' . CM_CREDENTIAL_STALE_DAYS . ' days';
    }
    if ($strength !== null && (int) $strength <= 0) {
        $out[] = 'password is weak';
    }
    return $out;
}









function cm_credential_erase_password(int $assetId, string $by): void
{
    cm_q('UPDATE client_credential SET password_enc = NULL, password_strength = NULL,
          updated_at = ?, updated_by = ? WHERE asset_id = ?',
         [cm_now(), $by, $assetId]);
}







function cm_credential_save(int $assetId, string $loginUrl, string $username,
                            string $password, string $notes, string $recovery,
                            string $actor): void
{
    $existing = cm_credential($assetId);

    $passEnc = $password !== '' ? cm_encrypt($password) : ($existing['password_enc'] ?? null);
    $notesEnc = $notes !== '' ? cm_encrypt($notes) : ($existing['notes_enc'] ?? null);
    $recEnc  = $recovery !== '' ? cm_encrypt($recovery) : ($existing['recovery_enc'] ?? null);
    $strength = $password !== '' ? cm_password_strength($password)
              : ($existing !== null && array_key_exists('password_strength', $existing)
                  ? $existing['password_strength'] : null);




    $stamp = ($password !== '' || $existing === null)
        ? cm_now() : (string) $existing['updated_at'];

    cm_q('INSERT INTO client_credential (asset_id, login_url, username, password_enc,
                                         password_strength, notes_enc,
                                         recovery_enc, updated_at, updated_by)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE login_url = VALUES(login_url), username = VALUES(username),
                                  password_enc = VALUES(password_enc),
                                  password_strength = VALUES(password_strength),
                                  notes_enc = VALUES(notes_enc),
                                  recovery_enc = VALUES(recovery_enc), updated_at = VALUES(updated_at),
                                  updated_by = VALUES(updated_by)',
         [$assetId, mb_substr($loginUrl, 0, 255), mb_substr($username, 0, 190),
          $passEnc, $strength, $notesEnc, $recEnc, $stamp, mb_substr($actor, 0, 190)]);
}




















function cm_asset_history(array $asset, int $limit = 25): array
{
    $name = trim((string) ($asset['name'] ?? ''));
    if ($name === '') return [];







    return cm_all(
        'SELECT created_at, actor_email, action, note FROM audit_log
          WHERE target_type = ? AND target_id = ? AND note LIKE ? AND action <> ?
          ORDER BY id DESC LIMIT ' . (int) $limit,
        ['client', (int) ($asset['client_id'] ?? 0), addcslashes($name, '%_\\') . '%', 'credential.viewed']);
}



function cm_asset_history_line(string $note, string $name): string
{
    if ($name !== '' && str_starts_with($note, $name)) {
        $note = ltrim(substr($note, strlen($name)), ': ');
    }
    return $note !== '' ? $note : '(no detail recorded)';
}










function cm_credential_delete(int $assetId): void
{
    cm_q('DELETE FROM client_credential WHERE asset_id = ?', [$assetId]);
}






function cm_credential_open(array $credential): array
{
    return [
        'login_url' => (string) $credential['login_url'],
        'username'  => (string) $credential['username'],
        'password'  => cm_decrypt((string) ($credential['password_enc'] ?? '')),
        'notes'     => cm_decrypt((string) ($credential['notes_enc'] ?? '')),
        'recovery'  => cm_decrypt((string) ($credential['recovery_enc'] ?? '')),
    ];
}





function cm_reveal_check(array $admin, string $secret): bool
{
    if ($secret === '') return false;
    if (cm_password_verify_account($admin, $secret)) return true;

    require_once CM_LIB . '/tfa.php';
    if (cm_tfa_on($admin)) {
        $fresh = cm_row('SELECT * FROM account WHERE id = ?', [(int) $admin['id']]);
        if ($fresh && cm_tfa_verify($fresh, $secret)) return true;
    }
    return false;
}
































function cm_reveal_attempt(array $admin, array $credential,
                           string $assetName, int $clientId): array
{





    if (cm_rate_exceeded('reveal', (string) $admin['email'], 60, 15)) {
        cm_audit('credential.reveal_refused', 'client', $clientId,
                 $assetName . ': reveal throttled for ' . (string) $admin['email']);
        return ['ok' => false,
                'error' => 'That is a lot of credentials in fifteen minutes — paused as a precaution. Try again shortly.',
                'values' => []];
    }
    cm_rate_record('reveal', (string) $admin['email']);



    cm_audit('credential.viewed', 'client', $clientId,
             $assetName . ': credential read by ' . (string) $admin['email']);
    return ['ok' => true, 'error' => '', 'values' => cm_credential_open($credential)];
}















function cm_job_assets_handle_post(array $admin, array $job, array $client, string $back): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    if ((string) ($_POST['action'] ?? '') !== 'save_assets') return;







    require_once CM_LIB . '/jobs.php';
    require_once CM_LIB . '/view.php';

    cm_require_csrf();



    cm_job_assets_set((int) $job['id'], (int) $client['id'], (array) ($_POST['assets'] ?? []));
    cm_audit('job.assets_set', 'client', (int) $client['id'],
             'job "' . (string) $job['name'] . '": properties updated by ' . (string) $admin['email']);
    cm_flash('Saved which properties this job touches.');
    cm_redirect($back);
}



















function cm_asset_edit_window_html(array $asset, ?array $credential, array $errors,
                                   string $formAction = '', string $return = '',
                                   string $dialogId = 'dlg-property-edit'): string
{
    $types = cm_asset_types();
    $pickedKind = (string) ($asset['property_kind'] ?? 'web');
    if (!isset(cm_property_kinds()[$pickedKind])) $pickedKind = 'web';
    $propertyData = cm_property_data_decode((string) ($asset['property_data'] ?? ''));
    ob_start();
    echo cm_credential_tools_script_once();
    ?>
<dialog class="modal modal-wider" id="<?= e($dialogId) ?>"<?=
    array_diff_key($errors, ['reveal' => '']) ? ' data-modal-autoopen' : '' ?>>
  <div class="modal-box">
  <div class="modal-head">
    <h2>Edit <?= e((string) $asset['name']) ?></h2>
    <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
  </div>
  <form method="post"<?= $formAction !== '' ? ' action="' . e($formAction) . '"' : '' ?>>
    <?= cm_csrf_field() ?>
    <input type="hidden" name="action" value="save_asset">
    <?php if ($return !== ''): ?><input type="hidden" name="return" value="<?= e($return) ?>"><?php endif; ?>
    <div class="row">
      <label class="lbl" for="property_kind">Property type</label>
      <select id="property_kind" name="property_kind" class="f-full" data-property-kind-picker>
        <?php foreach (cm_property_kinds() as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $pickedKind === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php 








 ?>
    <div class="form-2col">
    <div class="form-col">
    <div class="row">
      <label class="lbl" for="name">Name</label>
      <input type="text" id="name" name="name" value="<?= e((string) $asset['name']) ?>" maxlength="120" required>
      <?= cm_field_error($errors, 'name') ?>
    </div>
    <div class="row" data-property-fields="web"<?= $pickedKind !== 'web' ? ' hidden' : '' ?>>
      <label class="lbl" for="url">Web address</label>
      <?php 


 ?>
      <input type="text" inputmode="url" id="url" name="url" value="<?= e((string) $asset['url']) ?>" maxlength="255"
             autocomplete="off" spellcheck="false" placeholder="example.com">
      <?= cm_field_error($errors, 'url') ?>
      <?php 




 ?>
    </div>
    <?php 






 ?>
    <div class="row" data-property-fields="web"<?= $pickedKind !== 'web' ? ' hidden' : '' ?>>
      <label class="lbl" for="handle">Username</label>
      <input type="text" id="handle" name="handle" maxlength="190" autocomplete="off"
             value="<?= e((string) (($credential['username'] ?? '') !== ''
                                    ? $credential['username'] : $asset['handle'])) ?>"
             placeholder="the name you sign in with">
    </div>
    </div><?php  ?>

    <div class="form-col">
    <?php 













 ?>
    <div class="row" data-property-fields="web"<?= $pickedKind !== 'web' ? ' hidden' : '' ?>>
      <label class="lbl" for="type">Type</label>
      <select id="type" name="type">
        <?php foreach ($types as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= (string) $asset['type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php 



 ?>
    <?php if (cm_asset_categories_on()): ?>
    <?php 




 ?>
    <?php $cats = cm_asset_category_map(); $curCat = (int) ($asset['category_id'] ?? 0); ?>
    <div class="row">
      <label class="lbl" for="category_id">Subcategory</label>
      <?php if ($cats): ?>
        <select id="category_id" name="category_id" class="f-full">
          <option value="0">&mdash; none &mdash;</option>
          <?php foreach ($cats as $cid => $cname): ?>
            <option value="<?= (int) $cid ?>" <?= $curCat === (int) $cid ? 'selected' : '' ?>><?= e($cname) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <?php 






 ?>
      <?php if ($cats): ?>
        <label class="lbl mt-8" for="category_new">or create custom category</label>
      <?php endif; ?>
      <input type="text" id="category_new" name="category_new" class="f-full" maxlength="80"
             <?= $cats ? '' : 'placeholder="e.g. Client-owned, Ours, SEO"' ?>>
    </div>
    <?php endif; ?>
    </div><?php  ?>
    </div><?php  ?>

    <?= cm_property_fields_html('', $pickedKind, $propertyData) ?>

    <?php 






 ?>
    <fieldset class="cred-set" data-property-fields="web"<?= $pickedKind !== 'web' ? ' hidden' : '' ?>>
      <legend>Sign-in <?= $credential !== null ? '&mdash; stored, encrypted' : '' ?></legend>

      <?php 



 ?>
      <div class="form-2col">
      <div class="form-col">
        <div class="row">
          <?php 







 ?>
          <div class="lbl-row">
            <label class="lbl" for="password">Password</label>
            <span class="small" data-strength-for="password"></span>
            <span class="spacer"></span>
            <button class="pw-eye" type="button" data-toggle-for="password"
                    aria-label="Show" title="Show" aria-pressed="false"><?= cm_icon_eye() ?></button>
          </div>
          <input type="password" id="password" name="password" autocomplete="new-password"
                 data-strength-meter class="f-full"
                 placeholder="<?= $credential !== null && $credential['password_enc'] !== null ? 'stored — type here to replace' : '' ?>">
                  </div>

        <?php 















 ?>
        <?php $storedLogin = (string) ($credential['login_url'] ?? ''); ?>
        <input type="hidden" name="login_url" value="<?= e($storedLogin) ?>">
      </div>

      <?php 



 ?>
      <div class="form-col">
        <label class="lbl" aria-hidden="true">&#8203;</label>
        <button class="btn btn-sm btn-quiet" type="button" data-generate-for="password">Generate</button>
      </div>

      <div class="form-col">
        <div class="row">
          <label class="lbl" for="secure_notes">Secure notes</label>
          <textarea id="secure_notes" name="secure_notes" rows="3" class="f-full"
                    placeholder="<?= $credential !== null && $credential['notes_enc'] !== null ? 'stored — type here to replace' : 'PIN for phone support, security answers…' ?>"></textarea>
        </div>
      </div>

      <div class="form-col">
        <div class="row">
          <label class="lbl" for="recovery">Recovery details</label>
          <textarea id="recovery" name="recovery" rows="3" class="f-full"
                    placeholder="<?= $credential !== null && $credential['recovery_enc'] !== null ? 'stored — type here to replace' : 'Recovery email, backup codes the client gave you…' ?>"></textarea>
        </div>
      </div>
      </div>
    </fieldset>

    <?php 




 ?>
    <div class="row">
      <?php 

 ?>
      <label class="lbl" for="notes">Notes <span class="muted">(anything specific about this property, shows on the active job)</span></label>
      <textarea id="notes" name="notes" rows="3"><?= e((string) $asset['notes']) ?></textarea>
    </div>

    <div class="actions mt-16">
      <button class="btn" type="submit">Save</button>
      <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
    </div>
  </form>
  <?= cm_property_kind_script_once() ?>
  </div>
</dialog>
<?php
    return (string) ob_get_clean();
}













function cm_job_asset_add_new_handle_post(array $admin, array $job, array $client, string $back,
                                          string $prefix = 'fa_'): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return [];
    if ((string) ($_POST['action'] ?? '') !== 'add_new_asset') return [];
    require_once CM_LIB . '/jobs.php';
    require_once CM_LIB . '/view.php';
    cm_require_csrf();

    $r = cm_asset_add_from_post((int) $client['id'], $prefix, (string) $admin['email']);
    if ($r['ceiling']) {
        cm_flash('This client already has ' . CM_ASSETS_PER_CLIENT . ' assets — the agreed ceiling.', 'error');
        cm_redirect($back);
    }
    if ($r['errors']) return $r['errors'];

    cm_job_assets_set((int) $job['id'], (int) $client['id'],
                      array_merge(cm_job_asset_ids((int) $job['id']), [(int) $r['id']]));
    cm_audit('client.asset_added', 'client', (int) $client['id'],
             $r['said'] . ' on job "' . (string) $job['name'] . '" by ' . (string) $admin['email']);
    cm_flash('Property added and on this job' . ($r['pass'] ? ' — sign-in stored encrypted.' : '.'));
    cm_redirect($back);
}












function cm_job_assets_dialog_html(array $all, array $picked, string $newForm = '', bool $autoopen = false): string
{















    $two = $newForm !== '';

    ob_start();
    if ($all): ?>
    <form method="post">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="save_assets">
      <div class="caps-pick">
        <?php foreach ($all as $a): ?>
          <label class="caps-row">
            <input type="checkbox" name="assets[]" value="<?= (int) $a['id'] ?>"
                   <?= in_array((int) $a['id'], $picked, true) ? 'checked' : '' ?>>
            <span><b><?= e((string) $a['name']) ?></b>
              <span class="muted small"><?= e(cm_asset_types()[(string) $a['type']] ?? '') ?><?php
                if (!empty($a['has_credential'])): ?> &middot; sign-in stored<?php endif; ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
      <?php if ($two): ?>
      <div class="row"><div class="actions">
        <button class="btn" type="submit">Save</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div></div>
      <?php else: ?>
      <div class="actions">
        <button class="btn" type="submit">Save</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
      <?php endif; ?>
    </form>
    <?php else: ?>
    <p class="muted small">None yet<?= $two ? ' — make the first one on the right.' : '.' ?></p>
    <?php endif;
    $tickList = (string) ob_get_clean();

    ob_start(); ?>
<?php 



 ?>
<dialog class="modal<?= $two ? ' modal-widest' : '' ?>" id="dlg-job-assets"<?= $autoopen ? ' data-modal-autoopen' : '' ?>>
  <div class="modal-box">
    <div class="modal-head">
      <h2>Properties for this job</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <?php if ($two): ?>
    <p class="modal-sub">Tick the ones this job touches, or make a new one
    &mdash; it goes onto this job the moment it is added. Unticking only takes
    one off this job.</p>
    <div class="props-3col">
      <div class="form-col">
        <section class="form-step">
          <h3>Existing properties</h3>
          <?= $tickList ?>
        </section>
      </div>
      <div class="form-col">
        <section class="form-step">
          <h3>New property</h3>
          <?= $newForm ?>
        </section>
      </div>
    </div>
    <?php else: ?>
    <p class="modal-sub">Tick the ones this job touches. Their sign-ins appear
    on the job, and unticking one only takes it off this job &mdash; nothing
    stored on the property itself is changed.</p>
    <?= $tickList ?>
    <?php endif; ?>
  </div>
</dialog>
<?php
    return (string) ob_get_clean();
}


















function cm_asset_add_from_post(int $clientId, string $prefix, string $actor): array
{
    $name = trim((string) ($_POST[$prefix . 'name'] ?? ''));
    $type = (string) ($_POST[$prefix . 'type'] ?? 'other');
    $url  = trim((string) ($_POST[$prefix . 'url'] ?? ''));
    $user = trim((string) ($_POST[$prefix . 'user'] ?? ''));
    $pass = (string) ($_POST[$prefix . 'pass'] ?? '');
    $secure   = trim((string) ($_POST[$prefix . 'secure'] ?? ''));
    $recovery = trim((string) ($_POST[$prefix . 'recovery'] ?? ''));
    $notes    = trim((string) ($_POST[$prefix . 'notes'] ?? ''));
    $propertyKind = (string) ($_POST[$prefix . 'property_kind'] ?? cm_property_default_kind());
    if (!isset(cm_property_kinds()[$propertyKind])) $propertyKind = cm_property_default_kind();
    $propertyData = cm_property_data_from_post($prefix, $propertyKind);
    if (!isset(cm_asset_types()[$type])) $type = 'other';




    $categoryId = 0;
    if (cm_asset_categories_on()) {
        $categoryId  = (int) ($_POST[$prefix . 'category_id'] ?? 0);
        $newCategory = trim((string) ($_POST[$prefix . 'category_new'] ?? ''));
        if ($newCategory !== '') {
            $categoryId = cm_asset_category_ensure($newCategory);
        } elseif ($categoryId > 0 && !isset(cm_asset_category_map()[$categoryId])) {
            $categoryId = 0;
        }
    }

    $out = ['ok' => false, 'id' => 0, 'errors' => [], 'ceiling' => false, 'said' => '', 'pass' => $pass !== ''];

    if ($name === '' || mb_strlen($name) > 120) {
        $out['errors'][$prefix . 'name'] = $name === ''
            ? 'Give it a name — "Facebook page", "Main website".'
            : 'Too long (120 characters maximum).';
        return $out;
    }




















    $addr = cm_web_address($url);
    if ($addr['error'] !== '') {
        $out['errors'][$prefix . 'url'] = $addr['error'];
        return $out;
    }
    $url = $addr['url'];
    if (mb_strlen($url) > 255 || mb_strlen($user) > 190) {
        $out['errors'][$prefix . 'url'] = 'One of those boxes is too long.';
        return $out;
    }
    if (cm_client_asset_count($clientId) >= CM_ASSETS_PER_CLIENT) {
        $out['ceiling'] = true;
        return $out;
    }



    $kindCols = cm_property_columns_exist() ? 'property_kind, property_data, ' : '';
    $kindQs   = cm_property_columns_exist() ? '?, ?, ' : '';
    $kindVals = cm_property_columns_exist()
        ? [$propertyKind, cm_property_data_encode($propertyData)] : [];

    $aid = cm_insert('INSERT INTO client_asset (client_id, name, type, ' . $kindCols . 'category_id, url, handle, notes, created_at, updated_at)
                      VALUES (?, ?, ?, ' . $kindQs . '?, ?, ?, ?, ?, ?)',
                     array_merge([$clientId, $name, $type], $kindVals,
                     [$categoryId > 0 ? $categoryId : null, $url, $user,
                      $notes !== '' ? $notes : null, cm_now(), cm_now()]));



    $kindChoices = cm_property_kinds();
    $historyType = $propertyKind === 'web'
        ? cm_asset_types()[$type]
        : (string) ($kindChoices[$propertyKind] ?? 'Property');
    $said = $name . ' (' . $historyType . ') added';
    if ($user !== '' || $pass !== '' || $secure !== '' || $recovery !== '') {


        cm_credential_save($aid, '', $user, $pass, $secure, $recovery, $actor);
        $said .= $pass !== '' ? ' with its sign-in' : ' with its username';
    }
    require_once CM_LIB . '/clients.php';
    cm_client_touch($clientId);

    $out['ok']   = true;
    $out['id']   = $aid;
    $out['said'] = $said;
    return $out;
}



function cm_asset_add_window_html(string $dialogId, string $prefix, string $action,
                                  string $title, string $sub, array $errors): string
{
    $autoopen = cm_asset_add_refused($prefix, $errors);
    ob_start(); ?>
  <?php 
 ?>
  <dialog class="modal modal-wider" id="<?= e($dialogId) ?>"<?= $autoopen ? ' data-modal-autoopen' : '' ?>>
    <div class="modal-box">
      <div class="modal-head">
        <h2><?= e($title) ?></h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub"><?= e($sub) ?></p>
      <?= cm_asset_add_form_html($prefix, $action, $errors) ?>
    </div>
  </dialog>
<?php
    return (string) ob_get_clean();
}




function cm_asset_add_refused(string $prefix, array $errors): bool
{
    return isset($errors[$prefix . 'name']) || isset($errors[$prefix . 'url']);
}























function cm_asset_add_form_html(string $prefix, string $action, array $errors, bool $inPanel = false): string
{
    $p = static fn(string $f): string => e((string) ($_POST[$prefix . $f] ?? ''));
    $pickedKind = (string) ($_POST[$prefix . 'property_kind'] ?? cm_property_default_kind());
    if (!isset(cm_property_kinds()[$pickedKind])) $pickedKind = cm_property_default_kind();
    ob_start();
    echo cm_credential_tools_script_once();
    ?>
      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="<?= e($action) ?>">
        <div class="row">
          <label class="lbl" for="<?= e($prefix) ?>property_kind">Property type</label>
          <select id="<?= e($prefix) ?>property_kind" name="<?= e($prefix) ?>property_kind" class="f-full" data-property-kind-picker>
            <?php foreach (cm_property_kinds() as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $pickedKind === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Choose once to show the details useful for this property.</div>
        </div>
        <div class="form-2col" data-property-fields="web"<?= $pickedKind !== 'web' ? ' hidden' : '' ?>>
        <div class="form-col">
        <div class="row">
          <label class="lbl" for="<?= e($prefix) ?>name">Name</label>
          <input type="text" id="<?= e($prefix) ?>name" name="<?= e($prefix) ?>name" maxlength="120" class="f-full"
                 value="<?= $p('name') ?>" placeholder="Facebook page, Main website, Hosting…">
          <?= cm_field_error($errors, $prefix . 'name') ?>
        </div>
        </div><?php  ?>
        <div class="form-col">
        <div class="row" data-property-fields="web"<?= $pickedKind !== 'web' ? ' hidden' : '' ?>>
          <label class="lbl" for="<?= e($prefix) ?>type">Type</label>
          <select id="<?= e($prefix) ?>type" name="<?= e($prefix) ?>type" class="f-full">
            <?php foreach (cm_asset_types() as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= (string) ($_POST[$prefix . 'type'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if (cm_asset_categories_on()): ?>
        <?php $addCats = cm_asset_category_map(); ?>
        <div class="row">
          <label class="lbl" for="<?= e($prefix) ?>category_id">Subcategory</label>
          <?php if ($addCats): ?>
            <select id="<?= e($prefix) ?>category_id" name="<?= e($prefix) ?>category_id" class="f-full">
              <option value="0">&mdash; none &mdash;</option>
              <?php foreach ($addCats as $cid => $cname): ?>
                <option value="<?= (int) $cid ?>" <?= (int) ($_POST[$prefix . 'category_id'] ?? 0) === (int) $cid ? 'selected' : '' ?>><?= e($cname) ?></option>
              <?php endforeach; ?>
            </select>
            <label class="lbl mt-8" for="<?= e($prefix) ?>category_new">or create custom category</label>
          <?php endif; ?>
          <input type="text" id="<?= e($prefix) ?>category_new" name="<?= e($prefix) ?>category_new" class="f-full" maxlength="80"
                 value="<?= $p('category_new') ?>" <?= $addCats ? '' : 'placeholder="e.g. Client-owned, Ours, SEO"' ?>>
        </div>
        <?php endif; ?>
        </div><?php  ?>
        </div><?php  ?>

        <?= cm_property_fields_html($prefix, $pickedKind) ?>

        <?php 


 ?>
        <div class="form-2col" data-property-fields="web"<?= $pickedKind !== 'web' ? ' hidden' : '' ?>>
        <div class="form-col">
        <div class="row">
          <label class="lbl" for="<?= e($prefix) ?>url">Web address</label>
          <?php 




 ?>
          <input type="text" inputmode="url" id="<?= e($prefix) ?>url" name="<?= e($prefix) ?>url" maxlength="255" class="f-full"
                 autocomplete="off" spellcheck="false" value="<?= $p('url') ?>" placeholder="example.com">
          <?= cm_field_error($errors, $prefix . 'url') ?>
        </div>
        </div>
        <div class="form-col">
        <div class="row">
          <label class="lbl" for="<?= e($prefix) ?>user">Username</label>
          <input type="text" id="<?= e($prefix) ?>user" name="<?= e($prefix) ?>user" maxlength="190" class="f-full"
                 autocomplete="off" value="<?= $p('user') ?>" placeholder="the name you sign in with">
        </div>
        </div>
        </div>

        <fieldset class="cred-set" data-property-fields="web"<?= $pickedKind !== 'web' ? ' hidden' : '' ?>>
          <legend>Sign-in</legend>
          <div class="form-2col">
          <div class="form-col">
            <div class="row">
              <div class="lbl-row">
                <label class="lbl" for="<?= e($prefix) ?>pass">Password</label>
                <span class="small" data-strength-for="<?= e($prefix) ?>pass"></span>
                <span class="spacer"></span>
                <button class="pw-eye" type="button" data-toggle-for="<?= e($prefix) ?>pass"
                        aria-label="Show" title="Show" aria-pressed="false"><?= cm_icon_eye() ?></button>
              </div>
              <input type="password" id="<?= e($prefix) ?>pass" name="<?= e($prefix) ?>pass" class="f-full"
                     autocomplete="new-password" data-strength-meter>
              <div class="hint">Encrypted the moment it is saved.</div>
            </div>
          </div>
          <div class="form-col">
            <label class="lbl" aria-hidden="true">&#8203;</label>
            <button class="btn btn-sm btn-quiet" type="button" data-generate-for="<?= e($prefix) ?>pass">Generate</button>
          </div>
          <div class="form-col">
            <div class="row">
              <label class="lbl" for="<?= e($prefix) ?>secure">Secure notes</label>
              <textarea id="<?= e($prefix) ?>secure" name="<?= e($prefix) ?>secure" rows="3" class="f-full"
                        placeholder="PIN for phone support, security answers…"><?= $p('secure') ?></textarea>
            </div>
          </div>
          <div class="form-col">
            <div class="row">
              <label class="lbl" for="<?= e($prefix) ?>recovery">Recovery details</label>
              <textarea id="<?= e($prefix) ?>recovery" name="<?= e($prefix) ?>recovery" rows="3" class="f-full"
                        placeholder="Recovery email, backup codes the client gave you…"><?= $p('recovery') ?></textarea>
            </div>
          </div>
          </div>
        </fieldset>

        <div class="row">
          <label class="lbl" for="<?= e($prefix) ?>notes">Notes <span class="muted">(anything specific about this property, shows on the active job)</span></label>
          <?php  ?>
          <textarea id="<?= e($prefix) ?>notes" name="<?= e($prefix) ?>notes" rows="2"><?= $p('notes') ?></textarea>
        </div>

        <?php if ($inPanel): ?>
        <div class="row"><div class="actions">
          <button class="btn" type="submit">Add the property</button>
        </div></div>
        <?php else: ?>
        <div class="modal-foot">
          <button class="btn" type="submit">Add the property</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
        <?php endif; ?>
      </form>
      <?= cm_property_kind_script_once() ?>
<?php
    return (string) ob_get_clean();
}
