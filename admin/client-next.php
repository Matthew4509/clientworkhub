<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0



















declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/jobs.php';
require_once CM_LIB . '/onboarding.php';


require_once CM_LIB . '/assets.php';

$admin  = cm_require_admin();
$id     = (int) ($_GET['id'] ?? 0);
$client = cm_client($id);
if ($client === null) {
    cm_flash('That client is not here.', 'error');
    cm_redirect('admin/clients.php');
}

$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();




    if ((string) ($_POST['action'] ?? '') === 'send_onboarding') {
        require_once CM_LIB . '/onboarding.php';
        $blocked = cm_onboard_links_blocked();
        if ($blocked !== '') {
            cm_flash($blocked, 'error');
            cm_redirect('admin/client-next.php?id=' . $id);
        }
        $tplId = (int) ($_POST['onboard_template'] ?? 0);
        $bid   = (int) ($_POST['booking_id'] ?? 0);







        cm_onboard_start($id, $bid > 0 ? $bid : null, $tplId > 0 ? $tplId : null);
        cm_audit('onboarding.started', 'client', $id,
                 'onboarding form created with the client by ' . (string) $admin['email']
                 . ' (expires in ' . CM_LINK_EXPIRY_DEFAULT_DAYS . ' days)');
        cm_flash('Their form is ready — the link to send is in the list below. '
               . 'It stops working after ' . CM_LINK_EXPIRY_DEFAULT_DAYS . ' days.');
        cm_redirect('admin/onboarding.php');
    }






    if ((string) ($_POST['action'] ?? '') === 'save_core') {
        $changed = cm_core_save_from_post($id, (string) $admin['email'], false);
        cm_flash($changed > 0 ? 'Brief saved.' : 'Nothing had changed.', $changed > 0 ? 'ok' : 'warn');
        cm_redirect('admin/client-next.php?id=' . $id . '#step-props');
    }





    if ((string) ($_POST['action'] ?? '') === 'add_asset') {
        $r = cm_asset_add_from_post($id, 'a_', (string) $admin['email']);
        if ($r['ceiling']) {
            cm_flash('This client already has ' . CM_ASSETS_PER_CLIENT . ' assets — the agreed ceiling.', 'error');
            cm_redirect('admin/client-next.php?id=' . $id . '#step-props');
        }
        if ($r['errors']) {
            $errors += $r['errors'];
        } else {
            cm_audit('client.asset_added', 'client', $id, $r['said'] . ' by ' . (string) $admin['email']);
            cm_flash('Property added' . ($r['pass'] ? ', sign-in stored encrypted.' : '.'));
            cm_redirect('admin/client-next.php?id=' . $id . '#step-props');
        }
    }
}











$fromBooking = null;
$prefill     = [];
$bookingId   = (int) ($_GET['booking'] ?? 0);
if ($bookingId > 0) {
    try { $fromBooking = cm_row('SELECT * FROM booking WHERE id = ?', [$bookingId]); }
    catch (Throwable $ex) {}
}
if ($fromBooking !== null) {
    $tpl = $fromBooking['template_id'] !== null
         ? cm_row('SELECT * FROM job_template WHERE id = ?', [(int) $fromBooking['template_id']]) : null;
    $prefill = [
        'template' => (string) ($fromBooking['template_id'] ?? ''),
        'name'     => $tpl !== null ? (string) $tpl['name'] : '',
        'start'    => (string) ($fromBooking['planned_start'] ?? ''),
        'end'      => (string) ($fromBooking['planned_end'] ?? ''),
        'amount'   => (int) $fromBooking['quoted_cents'] > 0
                    ? number_format((int) $fromBooking['quoted_cents'] / 100, 2, '.', '') : '',
        'freq'     => (string) ($fromBooking['quoted_freq'] ?? 'oneoff'),
        'days'     => $tpl !== null && (int) $tpl['duration_days'] > 0
                    ? (string) (int) $tpl['duration_days'] : '',
        'perday'   => $tpl !== null && (int) $tpl['per_day_minutes'] > 0
                    ? (string) (int) $tpl['per_day_minutes'] : '',
    ];
}

$name    = cm_client_display_name($client);
$props   = [];
$coreN   = 0;
$jobsN   = 0;
try { $props = cm_client_assets($id); } catch (Throwable $ex) {}
try { $coreN = (int) cm_val("SELECT COUNT(*) FROM client_core_detail WHERE client_id = ? AND dkey NOT LIKE '\_%'", [$id]); } catch (Throwable $ex) {}
try { $jobsN = (int) cm_val('SELECT COUNT(*) FROM job WHERE client_id = ?', [$id]); } catch (Throwable $ex) {}

$tpls = [];
try { $tpls = cm_templates(); } catch (Throwable $ex) {}

cm_head('Set up ' . $name, '', true);
cm_render_flashes();
cm_admin_nav('clients');




echo cm_crumbs_html([
    ['label' => 'Clients', 'href' => cm_url('admin/clients.php')],
    ['label' => cm_client_display_name($client),
     'href'  => cm_url('admin/client.php?id=' . (int) $client['id'])],
    ['label' => 'Client set up'],
]);

?>
<p class="muted small mb-4">
  <a href="<?= e(cm_url('admin/clients.php')) ?>">&larr; All clients</a>
</p>
<div class="topbar">
  <h1><?= e($name) ?></h1>
  <a class="btn btn-quiet" href="<?= e(cm_url('admin/client.php?id=' . $id)) ?>">Skip &mdash; open their page</a>
</div>
<?php if ($fromBooking !== null): ?>
  <div class="note note-info">
    <b>From booking <span class="mono"><?= e((string) $fromBooking['reference']) ?></span>.</b>
    Their core details and properties are not on file yet, so they are worth
    doing before the job &mdash; but every step here can be skipped, and the
    job at the bottom is already filled in from what they asked for.
    <a href="#step-job">Straight to the job</a>.
  </div>
<?php else: ?>
  <p class="sub">They are added. Here is what usually comes next &mdash; none of
  it is compulsory, and all of it can wait.</p>
<?php endif; ?>

<div class="next-steps">

  <?php  ?>
  <section class="next-step">
    <div class="next-n">1</div>
    <div class="next-body">
      <h2>Client core details
        <?php if ($coreN > 0): ?><span class="pill pill-approved"><?= $coreN ?> saved</span><?php endif; ?>
      </h2>
      <?php 

 ?>
      <p class="muted small">This is information about the company that
      displays when you are working on their tasks. For instance: what does
      their company do, brands they promote.</p>
      <?php 

 ?>
      <button class="btn btn-quiet" type="button" data-modal-open="dlg-brief">
        <?= $coreN > 0 ? 'Edit the brief' : 'Write the brief' ?></button>
    </div>
  </section>

  <?php  ?>
  <section class="next-step" id="step-props">
    <div class="next-n">2</div>
    <div class="next-body">
      <h2>Their properties (web assets)
        <?php if ($props): ?><span class="pill pill-approved"><?= count($props) ?></span><?php endif; ?>
      </h2>
      <?php 

 ?>
      <p class="muted small">If you need to manage a website, Facebook account,
      or more, you can store those details here. Store passwords securely. When
      you are working on a job, these will be accessible from the job page.</p>

      <?php if ($props): ?>
        <ul class="next-list">
          <?php foreach ($props as $p): ?>
            <li><a href="<?= e(cm_url('admin/asset.php?id=' . (int) $p['id'])) ?>"><?= e((string) $p['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <div class="actions">
        <button class="btn btn-quiet" type="button" data-modal-open="dlg-property">
          <?= $props ? 'Add another' : 'Add their properties' ?></button>

        <?php 


 ?>
        <?php if (!cm_home()): ?>
        <form method="post" class="inline">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="send_onboarding">
          <?php if ($tpls): ?>
            <select name="onboard_template" class="f-lg">
              <option value="0">&mdash; the generic questions &mdash;</option>
              <?php foreach ($tpls as $t): ?>
                <option value="<?= (int) $t['id'] ?>"><?= e((string) $t['name']) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
          <button class="btn btn-quiet" type="submit">Ask them for it instead</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php  ?>
  <section class="next-step next-step-main" id="step-job">
    <div class="next-n">3</div>
    <div class="next-body">
      <h2>Create a job (select a service)
        <?php if ($jobsN > 0): ?><span class="pill pill-approved"><?= $jobsN ?></span><?php endif; ?>
      </h2>
      <?php 








 ?>
      <p class="muted small">Select a service, choose a start date. You can
      override the pricing and duration.</p>

      <form method="post" action="<?= e(cm_url('admin/jobs.php')) ?>">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="client_id" value="<?= $id ?>">
        <?php if ($fromBooking !== null): ?>
          <input type="hidden" name="booking_id" value="<?= (int) $fromBooking['id'] ?>">
        <?php endif; ?>
        <?= cm_job_form_fields_html('', null, false, $prefill) ?>
        <?= cm_job_properties_picker_html($id) ?>
        <button class="btn" type="submit" name="add_job" value="1">Create the job</button>
      </form>
    </div>
  </section>

</div>
<?= cm_core_brief_window_html($id, $name) ?>
<?= cm_asset_add_window_html('dlg-property', 'a_', 'add_asset', 'Add a property',
                             'Every field is optional, and can be edited later', $errors) ?>
<?php cm_foot(); ?>
