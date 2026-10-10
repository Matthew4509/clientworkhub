<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0




























declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/jobs.php';
require_once CM_LIB . '/onboarding.php';

$admin = cm_require_admin();
$errors = [];
$in = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();





    foreach (['first_name', 'last_name', 'company', 'email', 'phone',
              'address', 'suburb', 'state', 'postcode', 'country', 'notes'] as $k) {
        $in[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $status = cm_client_status_valid((string) ($_POST['status'] ?? '')) ? (string) $_POST['status'] : 'prospect';

    if ($in['first_name'] === '' && $in['last_name'] === '' && $in['company'] === '') {
        $errors['first_name'] = 'A first name, a last name or a company — any one is enough.';
    }
    if ($in['email'] !== '' && !cm_email_valid($in['email'])) {
        $errors['email'] = 'That does not look like an email address.';
    }




    $dupe = null;
    if (!$errors && $in['email'] !== '') {
        $dupe = cm_row("SELECT id, first_name, last_name, company FROM client
                         WHERE LOWER(email) = ? AND status <> 'inactive' LIMIT 1",
                       [mb_strtolower($in['email'])]);
        if ($dupe !== null && empty($_POST['anyway'])) {
            $errors['email'] = 'You already have somebody on that email address.';
        }
    }

    if (!$errors) {
        $id = cm_insert(
            'INSERT INTO client (first_name, last_name, company, email, phone,
                                 address, suburb, state, postcode, country, status, notes,
                                 created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [mb_substr($in['first_name'], 0, 80), mb_substr($in['last_name'], 0, 80),
             mb_substr($in['company'], 0, 160), mb_substr($in['email'], 0, 190),
             mb_substr($in['phone'], 0, 60), mb_substr($in['address'], 0, 255),
             mb_substr($in['suburb'], 0, 120), mb_substr($in['state'], 0, 60),
             mb_substr($in['postcode'], 0, 20), mb_substr($in['country'], 0, 60),
             $status, $in['notes'], cm_now(), cm_now()]);

        $name = cm_client_display_name(['id' => $id, 'first_name' => $in['first_name'],
            'last_name' => $in['last_name'], 'company' => $in['company'], 'email' => $in['email']]);
        cm_audit('client.created', 'client', $id, $name . ' created by ' . (string) $admin['email']);




        if (($in['first_name'] !== '' || $in['last_name'] !== '')
            && ($in['email'] !== '' || $in['phone'] !== '')) {
            cm_insert('INSERT INTO client_contact (client_id, first_name, last_name, role,
                                                   email, phone, is_primary, is_billing,
                                                   address, suburb, state, postcode, country,
                                                   notes, created_at, updated_at)
                       VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?, ?, ?, ?, ?, ?, ?, ?)',
                      [$id, $in['first_name'], $in['last_name'], '',
                       $in['email'], $in['phone'], $in['address'], $in['suburb'],
                       $in['state'], $in['postcode'], mb_substr($in['country'], 0, 60),
                       '', cm_now(), cm_now()]);
        }








        cm_flash('Client added.');
        cm_redirect('admin/client-next.php?id=' . $id);
    }
}

$v = static fn(string $k): string => (string) ($in[$k] ?? '');

cm_head('New client', '', true);
cm_render_flashes();
cm_admin_nav('clients');
?>
<p class="muted small mb-4"><a href="<?= e(cm_url('admin/clients.php')) ?>">&larr; All clients</a></p>
<div class="topbar">
  <h1>New client</h1>
</div>
<?php 



 ?>
<p class="sub">It&rsquo;s essential we have a name or a business name for a
client. You can add more details later.</p>

<?php if ($errors): ?>
  <div class="note note-error">Have a look at the boxes marked below.</div>
<?php endif; ?>

<?php 

 ?>
<div class="page-grid">
<div>
<form method="post" class="mw-42">
  <?= cm_csrf_field() ?>

  <div class="card">
    <h2>Who they are</h2>
    <?php 


 ?>
    <p class="note note-warn"><b>(!)</b> One of these must be filled in —
    a first name, a last name <i>or</i> a business name. A person, a
    company, or both.</p>
    <div class="form-row">
      <div class="field">
        <label class="lbl" for="first_name">First name</label>
        <input type="text" id="first_name" name="first_name" class="f-full"
               maxlength="80" value="<?= e($v('first_name')) ?>" autocomplete="off" autofocus>
        <?= cm_field_error($errors, 'first_name') ?>
      </div>
      <div class="field">
        <label class="lbl" for="last_name">Last name</label>
        <input type="text" id="last_name" name="last_name" class="f-full"
               maxlength="80" value="<?= e($v('last_name')) ?>" autocomplete="off">
      </div>
    </div>
    <div class="form-row">
      <div class="field">
        <label class="lbl" for="company">Business <span class="muted">(if there is one)</span></label>
        <input type="text" id="company" name="company" class="f-full"
               maxlength="160" value="<?= e($v('company')) ?>" autocomplete="off">
      </div>
      <div class="field">
        <label class="lbl" for="status">Where they are up to</label>
        <select id="status" name="status" class="f-full">
          <?php 

 ?>
          <?php foreach (cm_client_statuses() as $k => $meta):
              if ($k === 'inactive') continue; ?>
            <?php 




 ?>
            <option value="<?= e($k) ?>" <?= $k === 'active' ? 'selected' : '' ?>>
              <?= e($meta['label']) ?> — <?= e($meta['blurb']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <div class="card">
    <h2>How to reach them <span class="muted">(optional)</span></h2>
    <div class="form-row">
      <div class="field">
        <label class="lbl" for="email">Email</label>
        <input type="email" id="email" name="email" class="f-full"
               maxlength="190" value="<?= e($v('email')) ?>" autocomplete="off">
        <?= cm_field_error($errors, 'email') ?>
        <?php if (isset($errors['email']) && str_contains($errors['email'], 'already')): ?>
          <?php 
 ?>
          <label class="tick mt-6">
            <input type="checkbox" name="anyway" value="1">
            <span>Add them anyway — this really is somebody new</span>
          </label>
        <?php endif; ?>
      </div>
      <div class="field">
        <label class="lbl" for="phone">Phone</label>
        <input type="text" id="phone" name="phone" class="f-full"
               maxlength="60" value="<?= e($v('phone')) ?>" autocomplete="off">
      </div>
    </div>
    <?php 











 ?>
    <div class="field">
      <label class="lbl" for="notes">Anything worth remembering</label>
      <textarea id="notes" name="notes" rows="3" class="f-full"
                placeholder="How you met them, what they asked about."><?= e($v('notes')) ?></textarea>
    </div>
  </div>

  <div class="actions">
    <button class="btn" type="submit">Add the client &rarr;</button>
    <a class="btn btn-quiet" href="<?= e(cm_url('admin/clients.php')) ?>">Cancel</a>
  </div>
</form>
</div>
<aside class="side-rail">
<?= cm_flow_chart_html(1) ?>
</aside>
</div>
<?php cm_foot(); ?>
