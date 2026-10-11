<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0












declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/assets.php';





require_once CM_LIB . '/tfa.php';

$admin = cm_require_admin();

$asset = cm_asset((int) ($_GET['id'] ?? 0));
if (!$asset) {
    cm_flash('There is no asset with that number — it may be a stale link.', 'warn');
    cm_redirect('admin/clients.php');
}
$client = cm_client((int) $asset['client_id']);
if (!$client) { cm_redirect('admin/clients.php'); }

$id      = (int) $asset['id'];
$back    = cm_url('admin/client.php?id=' . (int) $client['id'] . '&tab=assets');
$errors  = [];
$reveal  = null;
$credential = cm_credential($id);


if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $action = (string) ($_POST['action'] ?? '');













    if ($action === 'save_asset') {
        $name   = trim((string) ($_POST['name'] ?? ''));
        $type   = (string) ($_POST['type'] ?? 'other');
        $url    = trim((string) ($_POST['url'] ?? ''));
        $handle = trim((string) ($_POST['handle'] ?? ''));
        $notes  = trim((string) ($_POST['notes'] ?? ''));
        $propertyKind = (string) ($_POST['property_kind'] ?? ($asset['property_kind'] ?? 'web'));
        if (!isset(cm_property_kinds_offered((string) ($asset['property_kind'] ?? ''))[$propertyKind])) $propertyKind = 'web';
        $propertyData = cm_property_data_from_post('', $propertyKind);













        $status = (string) $asset['status'];











        $categoryId = (int) ($asset['category_id'] ?? 0);
        $newCategory = '';
        if (cm_asset_categories_on()) {
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $newCategory = trim((string) ($_POST['category_new'] ?? ''));
        }







        if (cm_asset_categories_on()) {
            if ($newCategory !== '') {
                $categoryId = cm_asset_category_ensure($newCategory);
            } elseif ($categoryId > 0 && !isset(cm_asset_category_map()[$categoryId])) {
                $categoryId = 0;
            }
        }

        if ($name === '') $errors['name'] = 'Give it a name — "Facebook page", "Main website".';
        if (mb_strlen($name) > 120) $errors['name'] = 'Too long (120 characters maximum).';
        if (!isset(cm_asset_types()[$type])) $type = 'other';
        if (!isset(cm_asset_statuses()[$status])) $status = (string) $asset['status'];





        if ($url !== '') {
            $addr = cm_web_address($url);
            if ($addr['error'] !== '') $errors['url'] = $addr['error'];
            else $url = $addr['url'];
        }
        if (mb_strlen($url) > 255 || mb_strlen($handle) > 190) {
            $errors['url'] = 'One of those boxes is too long.';
        }





        $loginUrl = trim((string) ($_POST['login_url'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $secure   = trim((string) ($_POST['secure_notes'] ?? ''));
        $recovery = trim((string) ($_POST['recovery'] ?? ''));






        $storedLoginUrl = trim((string) ($credential['login_url'] ?? ''));
        if ($loginUrl !== '' && $loginUrl !== $storedLoginUrl
            && !preg_match('#^https?://#i', $loginUrl)) {
            $errors['login_url'] = 'Start with https:// — this is the link you click to go and sign in.';
        }

        if (!$errors) {




            $kindSet  = cm_property_columns_exist() ? 'property_kind=?, property_data=?, ' : '';
            $kindVals = cm_property_columns_exist()
                ? [$propertyKind, cm_property_data_encode($propertyData)] : [];
            cm_q('UPDATE client_asset SET name=?, type=?, ' . $kindSet . 'category_id=?, url=?, handle=?, notes=?, status=?, updated_at=?
                  WHERE id=?',
                 array_merge([$name, $type], $kindVals,
                 [$categoryId > 0 ? $categoryId : null, $url, $handle, $notes, $status, cm_now(), $id]));
            cm_client_touch((int) $client['id']);
            $said = $name . ' updated';
            if ($status !== (string) $asset['status']) {
                $said .= ' (status → ' . cm_asset_statuses()[$status]['label'] . ')';
            }
            cm_audit('client.asset_updated', 'client', (int) $client['id'],
                     $said . ' by ' . (string) $admin['email']);








            $hasSecret = $password !== '' || $secure !== '' || $recovery !== '';
            if ($credential !== null || $hasSecret || $loginUrl !== '') {
                $existed = $credential !== null;
                cm_credential_save($id, $loginUrl, $handle, $password, $secure, $recovery,
                                   (string) $admin['email']);
                $what = array_keys(array_filter([
                    'password' => $password !== '', 'secure notes' => $secure !== '',
                    'recovery' => $recovery !== '',
                ]));
                if (!$existed || $what) {
                    cm_audit('credential.changed', 'client', (int) $client['id'],
                             $name . ': ' . ($existed ? 'credential updated' : 'credential added')
                             . ($what ? ' (' . implode(', ', $what) . ')' : ' (login details only)')
                             . ' by ' . (string) $admin['email']);
                }
            }

            cm_flash('Saved.');




            $return = cm_safe_return((string) ($_POST['return'] ?? ''));
            cm_redirect($return !== '' ? $return : 'admin/asset.php?id=' . $id);
        }
        $asset = array_merge($asset, ['name' => $name, 'type' => $type,
                                      'property_kind' => $propertyKind,
                                      'property_data' => cm_property_data_encode($propertyData), 'url' => $url,
                                      'handle' => $handle, 'notes' => $notes, 'status' => $status]);
    }











    if ($action === 'reveal' && $credential !== null) {
        $attempt = cm_reveal_attempt($admin, $credential,
                                     (string) $asset['name'], (int) $client['id']);
        if ($attempt['ok']) {
            $reveal = $attempt['values'];
        } else {
            $errors['reveal'] = $attempt['error'];
        }
    }


    if ($action === 'save_status') {
        $new  = (string) ($_POST['status'] ?? $asset['status']);
        $defs = cm_asset_statuses();
        if (!isset($defs[$new])) $new = (string) $asset['status'];
        $said = '';
        if ($new !== (string) $asset['status']) {
            cm_q('UPDATE client_asset SET status = ?, updated_at = ? WHERE id = ?',
                 [$new, cm_now(), $id]);
            cm_client_touch((int) $client['id']);
            cm_audit('client.asset_updated', 'client', (int) $client['id'],
                     (string) $asset['name'] . ': status — ' . $defs[$new]['label']
                     . ' by ' . (string) $admin['email']);
            $said = 'Status saved.';
        }





        if (!empty($defs[$new]['erases_password'])
            && $credential !== null && $credential['password_enc'] !== null) {
            cm_credential_erase_password($id, (string) $admin['email']);
            cm_audit('credential.password_erased', 'client', (int) $client['id'],
                     (string) $asset['name'] . ': stored password erased with the status change by '
                     . (string) $admin['email']);
            $said = trim($said . ' The stored password was removed; the username, secure notes'
                       . ' and recovery details are untouched.');
        }
        if ($said !== '') cm_flash($said);
        cm_redirect('admin/asset.php?id=' . $id);
    }


    if ($action === 'erase_password' && $credential !== null) {
        cm_credential_erase_password($id, (string) $admin['email']);
        cm_client_touch((int) $client['id']);
        cm_audit('credential.password_erased', 'client', (int) $client['id'],
                 (string) $asset['name'] . ': stored password erased by ' . (string) $admin['email']);
        cm_flash('Password erased. The username, secure notes and recovery details are untouched.');
        cm_redirect('admin/asset.php?id=' . $id);
    }












}

$credential = cm_credential($id);
$types    = cm_asset_types();
$statuses = cm_asset_statuses();
$st       = $statuses[(string) $asset['status']] ?? $statuses['active'];


header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

cm_head($asset['name'] . ' — ' . cm_client_display_name($client), '', true);
cm_render_flashes();
cm_admin_nav('clients');





echo cm_crumbs_html([
    ['label' => 'Clients', 'href' => cm_url('admin/clients.php')],
    ['label' => cm_client_display_name($client),
     'href'  => cm_url('admin/client.php?id=' . (int) $client['id'])],










    ['label' => 'Properties', 'href' => $back],
]);

?>
<?php 
 ?>
<?= cm_credential_tools_script_once() ?>
<?php 









 ?>
<h1 class="mb-4"><span class="h1-label">Property Name:</span>
  <?= e((string) $asset['name']) ?></h1>

<?php 




 ?>
<div class="page-grid">
<div>

<?php 






 ?>
<?= cm_asset_edit_window_html($asset, $credential, $errors) ?>


<?php 














 ?>
<div class="card">
  <div class="card-head">
    <?php 

 ?>
    <h2><?= e((string) $asset['name']) ?></h2>
    <span class="pill <?= e($st['pill']) ?>"><?= e($st['label']) ?></span>
    <span class="spacer"></span>
    <?php  ?>
    <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-status">Edit status</button>
    <button class="btn btn-sm" type="button" data-modal-open="dlg-property-edit">Edit</button>
  </div>

  <?php 


















 ?>
  <?php $propertyKindNow = (string) ($asset['property_kind'] ?? 'web');
        if (!isset(cm_property_kinds()[$propertyKindNow])) $propertyKindNow = 'web';
        $propertyNow = cm_property_data_decode((string) ($asset['property_data'] ?? '')); ?>
  <p class="small muted mb-12"><?= e(cm_property_kinds()[$propertyKindNow]) ?></p>
  <?php if ($propertyKindNow === 'web'): ?>
  <?php $catsNow = cm_asset_category_map(); $catNow = (int) ($asset['category_id'] ?? 0); ?>
  <div class="cred-line mb-8">
    <?php if ($catNow > 0 && isset($catsNow[$catNow])): ?>
      <div class="cred-pair">
        <span class="cred-key">Category</span>
        <span class="cred-val"><?= e($catsNow[$catNow]) ?></span>
      </div>
    <?php endif; ?>
    <div class="cred-pair">
      <span class="cred-key"><?= e($types[(string) $asset['type']] ?? 'Other') ?></span>
      <?php if ((string) $asset['url'] !== ''): ?>
        <a class="cred-val mono" href="<?= e(cm_safe_href((string) $asset['url'])) ?>"
           target="_blank" rel="noopener noreferrer"><?= e((string) $asset['url']) ?></a>
      <?php else: ?>
        <span class="muted small">no address</span>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($reveal !== null): ?>
    <div class="note note-warn">
      <b>Unlocked for you just now, and recorded in the audit log.</b>
      The password stays hidden until you choose otherwise — copy it without
      ever showing it. Nothing here survives a reload.
    </div>
    <div class="table-wrap">
      <table>
        <tbody>
          <tr><th class="f-base">Login page</th>
              <td><?php if ($reveal['login_url'] !== ''): ?><a href="<?= e(cm_safe_href($reveal['login_url'])) ?>" target="_blank" rel="noopener noreferrer" class="mono small"><?= e($reveal['login_url']) ?></a><?php else: ?><span class="muted small">—</span><?php endif; ?></td></tr>
          <tr><th>Username</th>
              <td class="mono"><?= $reveal['username'] !== '' ? e($reveal['username']) : '<span class="muted small">—</span>' ?>
                <?php if ($reveal['username'] !== ''): ?>
                  <input type="hidden" id="revealed-user" value="<?= e($reveal['username']) ?>">
                  <button class="btn btn-sm btn-quiet" type="button" data-copy-for="revealed-user">Copy</button>
                <?php endif; ?>
              </td></tr>
          <tr><th>Password</th>
              <td>
                <?php if ($reveal['password'] !== ''): ?>
                  <input type="password" id="revealed-pass" class="mono t-body f-xl f-plain" readonly
                         value="<?= e($reveal['password']) ?>">
                  <button class="btn btn-sm" type="button" data-copy-for="revealed-pass">Copy</button>
                  <button class="btn btn-sm btn-quiet" type="button" data-toggle-for="revealed-pass">Show</button>
                  <noscript><span class="mono t-body"><?= e($reveal['password']) ?></span></noscript>
                <?php else: ?>
                  <span class="muted small">none stored</span>
                <?php endif; ?>
              </td></tr>
          <?php if ($reveal['notes'] !== ''): ?>
            <tr><th>Secure notes</th><td class="small prewrap"><?= e($reveal['notes']) ?></td></tr>
          <?php endif; ?>
          <?php if ($reveal['recovery'] !== ''): ?>
            <tr><th>Recovery</th><td class="small prewrap"><?= e($reveal['recovery']) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <p class="hint"><a href="<?= e(cm_url('admin/asset.php?id=' . $id)) ?>">Done — lock it again</a>.</p>

  <?php elseif ($credential !== null): ?>
    <?php $warnings = cm_credential_warnings($credential); ?>
    <?php if ($warnings): ?>
      <?php 

 ?>
      <div class="note note-warn">
        <b>! Worth a look:</b> <?= e(implode('; ', $warnings)) ?>.
        <?= in_array('password is weak', $warnings, true)
            ? 'Change password opens the edit window, which has a generator — one click makes a strong one to set on the account.'
            : 'If it is still what the account uses, that is fine — this is a nudge, not a rule.' ?>
      </div>
    <?php endif; ?>

    <?php if (isset($errors['reveal'])): ?>
      <div class="note note-error"><?= e($errors['reveal']) ?></div>
    <?php endif; ?>

    <?php 

 ?>
    <div class="cred-line"
         data-cred-endpoint="<?= e(cm_url('admin/credential.php')) ?>"
         data-cred-csrf="<?= e(cm_csrf_token()) ?>">
      <div class="cred-pair">
        <span class="cred-key">Username</span>
        <?php if ((string) $credential['username'] !== ''): ?>
          <?php 



 ?>
          <input type="text" id="cred-user<?= $id ?>" class="mono cred-box" readonly
                 tabindex="-1" aria-label="Username"
                 value="<?= e((string) $credential['username']) ?>">
          <button class="pw-eye cred-copy" type="button" data-copy-for="cred-user<?= $id ?>"
                  aria-label="Copy the username" title="Copy the username"><?= cm_icon_copy() ?></button>
        <?php else: ?>
          <span class="muted small">none stored</span>
        <?php endif; ?>
      </div>

      <div class="cred-pair">
        <span class="cred-key">Password</span>
        <?php if ($credential['password_enc'] !== null): ?>
          <input type="text" id="cred<?= $id ?>" class="mono cred-box" readonly
                 tabindex="-1" aria-label="Password, hidden"
                 value="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
          <button class="pw-eye" type="button" data-cred-show="<?= $id ?>"
                  aria-label="Show" title="Show" aria-pressed="false"><?= cm_icon_eye() ?></button>
          <button class="pw-eye cred-copy" type="button" data-cred-copy="<?= $id ?>"
                  aria-label="Copy the password" title="Copy the password"><?= cm_icon_copy() ?></button>
        <?php else: ?>
          <span class="muted small">none stored</span>
        <?php endif; ?>
      </div>

      <div class="muted small prewrap mt-4" id="crednotes<?= $id ?>" hidden></div>

      <?php if ($credential['password_enc'] !== null): ?>
        <noscript>
          <form class="mt-10" method="post">
            <?= cm_csrf_field() ?>
            <input type="hidden" name="action" value="reveal">
            <button class="btn" type="submit">Show the password</button>
          </form>
        </noscript>
      <?php endif; ?>
    </div>

    <?php 



 ?>
    <?php 
 ?>
    <div class="actions mt-10">
      <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-property-edit">Change password</button>
      <?php if ($credential['password_enc'] !== null): ?>
        <form method="post"
              data-confirm="Erase the stored password for <?= e((string) $asset['name']) ?>? The username, secure notes and recovery details stay. There is no way to read the password back afterwards.">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="erase_password">
          <button class="btn btn-sm btn-quiet" type="submit">Erase password</button>
        </form>
      <?php endif; ?>
    </div>

  <?php else: ?>
    <p class="muted small">Nothing stored for this property yet. The password,
    secure notes and recovery details are encrypted with a key that lives
    outside the database — a stolen copy of the database cannot read them.</p>
    <div class="actions mt-10">
      <button class="btn btn-sm" type="button" data-modal-open="dlg-property-edit">Add a sign-in</button>
    </div>
  <?php endif; ?>
  <?php else: ?>
    <?php $propertyLabels = [
      'service_address' => 'Service address', 'suburb' => 'Suburb or town',
      'access_notes' => 'Access notes', 'make' => 'Make', 'model' => 'Model',
      'serial_number' => 'Serial number', 'room_location' => 'Room location',
      'damp_chaser' => 'Damp-Chaser', 'overall_condition' => 'Overall condition',
      'last_tuned' => 'Last tuned', 'tuning_frequency' => 'Tuning frequency',
      'technician_notes' => 'Technician notes', 'preferred_stylist' => 'Preferred stylist',
      'usual_service' => 'Usual service', 'colour_formula' => 'Colour / formula',
      'hair_length' => 'Hair length', 'last_visit' => 'Last visit', 'salon_notes' => 'Service notes']; ?>
    <?php if ($customTemplate = cm_property_template($propertyKindNow)) {
      foreach ($customTemplate['fields'] as $field) {
        if (!empty($field['key']) && !empty($field['label'])) $propertyLabels[(string)$field['key']] = (string)$field['label'];
      }
    } ?>
    <dl class="summary-list">
      <?php foreach ($propertyLabels as $field => $label): ?>
        <?php if (($propertyNow[$field] ?? '') === '') continue; ?>
        <dt><?= e($label) ?></dt><dd class="prewrap"><?= e(ucfirst((string) $propertyNow[$field])) ?></dd>
      <?php endforeach; ?>
      <?php if (!empty($propertyNow['condition_action'])): ?><dt>Attention</dt><dd>Action needs attention</dd><?php endif; ?>
      <?php if (!empty($propertyNow['condition_outside'])): ?><dt>Attention</dt><dd>Outside/case needs attention</dd><?php endif; ?>
    </dl>
    <?php if (!$propertyNow): ?><p class="muted small">No service details recorded yet.</p><?php endif; ?>
  <?php endif; ?>
</div>

<?php 






 ?>
<dialog class="modal" id="dlg-status">
  <div class="modal-box">
    <div class="modal-head">
      <h2>Status &mdash; <?= e((string) $asset['name']) ?></h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <form method="post">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="save_status">
      <div class="row">
        <label class="lbl" for="status_pick">Status</label>
        <select id="status_pick" name="status" class="f-full">
          <?php 

 ?>
          <?php foreach ($statuses as $key => $sopt): ?>
            <option value="<?= e($key) ?>" <?= (string) $asset['status'] === $key ? 'selected' : '' ?>
              <?= isset($sopt['confirm']) ? 'data-confirm="' . e($sopt['confirm']) . '"' : '' ?>><?=
              e($sopt['do_label'] ?? $sopt['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-foot">
        <button class="btn" type="submit">Save</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
  </div>
</dialog>

<?php 
 ?>

<?php 






 ?>
<?php $hist = cm_asset_history($asset, CM_HISTORY_FETCH); ?>
<div class="card">
  <div class="card-head">
    <h2>History</h2>
    <span class="spacer"></span>
    <?php if ($hist): ?><span class="muted small"><?= count($hist) ?><?= count($hist) === 25 ? '+' : '' ?> entries</span><?php endif; ?>
  </div>
  <?php if (!$hist): ?>
    <p class="muted small m-0">Nothing recorded against this property yet.
    Edits, status changes and anything done to the sign-in appear here.</p>
  <?php else: ?>
    <?php $shown = array_slice($hist, 0, CM_HISTORY_SHOWN);
          $rest  = array_slice($hist, CM_HISTORY_SHOWN); ?>
    <?php foreach ($shown as $h): ?>
      <p class="hist-row"><span class="hist-when mono"><?= e(date('j M, H:i', strtotime((string) $h['created_at']))) ?></span>
        <span><?= e(cm_asset_history_line((string) $h['note'], (string) $asset['name'])) ?></span></p>
    <?php endforeach; ?>
    <?php if ($rest): ?>
      <details class="mt-8">
        <summary class="small">Show <?= count($rest) ?> older</summary>
        <div class="mt-8">
          <?php foreach ($rest as $h): ?>
            <p class="hist-row"><span class="hist-when mono"><?= e(date('j M, H:i', strtotime((string) $h['created_at']))) ?></span>
              <span><?= e(cm_asset_history_line((string) $h['note'], (string) $asset['name'])) ?></span></p>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endif; ?>
  <?php endif; ?>
</div>

</div>
<aside class="side-rail">
  <?php 




 ?>
  <div class="card">
    <h2 class="t-ui">Property Notes</h2>
    <?php $noteText = trim((string) $asset['notes']); ?>
    <?php if ($noteText === ''): ?>
      <p class="muted small">Nothing noted yet — the short operational aside
      that belongs with this property.
      <a href="#" data-modal-open="dlg-property-edit">Write one</a>.</p>
    <?php else: ?>
      <p class="small prewrap m-0"><?= e($noteText) ?></p>
    <?php endif; ?>
  </div>
<?php cm_client_context_cards($client); ?>
</aside>
</div>

<?php cm_foot(); ?>
