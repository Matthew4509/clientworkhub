<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0




















declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';

require_once CM_LIB . '/payments.php';

$admin = cm_require_admin();
$spec  = cm_setting_spec();




$groups = cm_billing_groups();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();








    $only = (string) ($_POST['set_group'] ?? '');
    if (!isset($groups[$only])) {
        cm_flash('That form did not say what it was saving.', 'error');
        cm_redirect('admin/billing-settings.php');
    }

    $saved = 0;
    foreach ($spec as $key => $meta) {
        if (($meta['group'] ?? '') !== $only) continue;
        $field = str_replace('.', '__', $key);
        $type  = (string) ($meta['type'] ?? 'text');

        if ($type === 'bool') {
            $value = !empty($_POST[$field]) ? '1' : '0';
        } elseif (!array_key_exists($field, $_POST)) {
            continue;
        } else {
            $value = trim((string) $_POST[$field]);
        }


        if (!empty($meta['secret']) && $value === '') continue;





        if ($key === 'tax.rate' && $value !== '' && !preg_match('/^\d{1,3}(\.\d{1,4})?$/', $value)) {
            cm_flash('The tax rate: a plain number, like 20, 10 or 8.25.', 'error');
            cm_redirect('admin/billing-settings.php?open=tax');
        }

        if ((string) cm_setting($key, '') !== $value) {
            cm_setting_set($key, $value, (string) $admin['email']);
            $saved++;
        }
    }
    cm_audit('settings.saved', '', null,
             'invoicing settings — ' . $only . ' — saved by ' . (string) $admin['email']);
    cm_flash($saved > 0 ? 'Saved.' : 'Nothing changed.');
    cm_redirect('admin/billing-settings.php');
}

cm_head('Invoicing settings', '', true);
cm_render_flashes();
cm_admin_nav('settings');
cm_admin_subnav('settings', 'billing', 'Invoicing');
?>
<p class="sub">Set once, then left alone. These are copied onto each invoice
as it is issued, so changing anything here never rewrites a document a client
has already been sent.</p>

<?php $open = (string) ($_GET['open'] ?? ''); ?>

<?php 












 ?>
<div class="set-grid">
  <?php foreach ($groups as $g => $ginfo): $st = cm_billing_group_state($g); ?>
    <section class="card set-tile">
      <h2><?= e($ginfo['title']) ?></h2>
      <p class="sub"><?= e($ginfo['blurb']) ?></p>

      <?php 

 ?>
      <p class="set-state set-state-<?= e($st['tone']) ?>"><?= e($st['line']) ?></p>

      <button class="btn <?= $st['tone'] === 'unset' ? '' : 'btn-quiet' ?>" type="button"
              data-modal-open="dlg-set-<?= e($g) ?>">
        <?= $st['tone'] === 'unset' ? 'Set ' : 'Change ' ?><?= e(mb_strtolower($ginfo['title'])) ?>
      </button>
    </section>
  <?php endforeach; ?>
</div>

<?php 
 ?>
<?php foreach ($groups as $g => $ginfo): ?>
  <dialog class="modal modal-wide" id="dlg-set-<?= e($g) ?>"<?= $open === $g ? ' data-modal-autoopen' : '' ?>>
    <div class="modal-box">
      <div class="modal-head">
        <h2><?= e($ginfo['title']) ?></h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub"><?= e($ginfo['blurb']) ?></p>

      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="set_group" value="<?= e($g) ?>">

        <?php foreach ($spec as $key => $meta):
          if (($meta['group'] ?? '') !== $g) continue;
          $field = str_replace('.', '__', $key);
          $type  = (string) ($meta['type'] ?? 'text');
        ?>
          <?php if ($type === 'choice'): ?>
            <?php $choices = cm_setting_choices($meta); ?>
            <?php $cur = (string) cm_setting($key, (string) array_key_first($choices)); ?>
            <div class="row">
              <label class="lbl"><?= e($meta['label']) ?></label>
              <div class="caps-pick">
                <?php foreach ($choices as $cval => $c): ?>
                  <label class="caps-row">
                    <input type="radio" name="<?= e($field) ?>" value="<?= e((string) $cval) ?>"
                           <?= $cur === (string) $cval ? 'checked' : '' ?>>
                    <span><b><?= e($c['label']) ?></b>
                      <?php if ($c['blurb'] !== ''): ?>
                        <span class="muted small d-block"><?= e($c['blurb']) ?></span>
                      <?php endif; ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
              <?php if (!empty($meta['hint'])): ?><div class="hint"><?= e($meta['hint']) ?></div><?php endif; ?>
            </div>
          <?php elseif ($type === 'bool'): ?>
            <?php 



 ?>
            <div class="switch-row">
              <label class="switch">
                <input type="checkbox" name="<?= e($field) ?>" value="1"
                       <?= (string) cm_setting($key, $key === 'invoice.enabled' ? '1' : '') === '1' ? 'checked' : '' ?>>
                <span class="switch-track"><span class="switch-dot"></span></span>
                <span class="switch-label"><span class="switch-on">ON</span><span class="switch-off">OFF</span></span>
              </label>
              <span>
                <span class="switch-name"><?= e($meta['label']) ?></span>
                <?php if (!empty($meta['hint'])): ?>
                  <span class="muted small d-block"><?= e($meta['hint']) ?></span>
                <?php endif; ?>
              </span>
            </div>
          <?php elseif (!empty($meta['secret'])): ?>
            <?php 
 ?>
            <div class="row">
              <label class="lbl" for="<?= e($field) ?>"><?= e($meta['label']) ?>
                <?php if (cm_setting_present($key)): ?><span class="pill pill-approved">set</span><?php endif; ?></label>
              <input type="password" id="<?= e($field) ?>" name="<?= e($field) ?>" class="f-full" value=""
                     autocomplete="off" placeholder="<?= cm_setting_present($key) ? 'set — type here to replace' : '' ?>">
              <?php if (!empty($meta['hint'])): ?><div class="hint"><?= e($meta['hint']) ?></div><?php endif; ?>
            </div>
          <?php else: ?>
            <div class="row">
              <label class="lbl" for="<?= e($field) ?>"><?= e($meta['label']) ?></label>
              <input type="text" id="<?= e($field) ?>" name="<?= e($field) ?>" class="f-full"
                     value="<?= e((string) cm_setting($key, '')) ?>" autocomplete="off">
              <?php if (!empty($meta['hint'])): ?><div class="hint"><?= e($meta['hint']) ?></div><?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>

        <div class="modal-foot">
          <button class="btn" type="submit">Save <?= e(mb_strtolower($ginfo['title'])) ?></button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>
<?php endforeach; ?>

<?php cm_foot(); ?>
