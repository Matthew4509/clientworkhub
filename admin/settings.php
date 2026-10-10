<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0











declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/capacity.php';
require_once CM_LIB . '/schedule.php';






require_once CM_LIB . '/appointments.php';

$admin = cm_require_admin();
















if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && in_array((string) ($_POST['action'] ?? ''),
                ['workday_save', 'workday_remove', 'break_add', 'break_remove'], true)) {
    cm_require_csrf();
    require_once CM_LIB . '/workday.php';
    $act = (string) $_POST['action'];

    if ($act === 'workday_save') {
        $problem = cm_workday_save((int) ($_POST['workday_id'] ?? 0), $_POST);
        cm_flash($problem !== '' ? $problem : 'Saved.', $problem !== '' ? 'error' : 'ok');
    } elseif ($act === 'workday_remove') {
        cm_workday_remove((int) ($_POST['workday_id'] ?? 0));
        cm_flash('Removed. Days it covered have no stated hours again.');
    } elseif ($act === 'break_add') {
        $problem = cm_workday_break_add((int) ($_POST['workday_id'] ?? 0), $_POST);
        cm_flash($problem !== '' ? $problem : 'Break added.', $problem !== '' ? 'error' : 'ok');
    } else {
        cm_workday_break_remove((int) ($_POST['break_id'] ?? 0));
        cm_flash('Break removed.');
    }
    cm_redirect('admin/settings.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && (string) ($_POST['action'] ?? '') === 'appt_series_remove') {
    cm_require_csrf();
    require_once CM_LIB . '/appointments.php';
    $gone = cm_appointment_series_remove((int) ($_POST['series_id'] ?? 0));
    cm_flash($gone > 0
        ? $gone . ' upcoming ' . ($gone === 1 ? 'entry' : 'entries') . ' removed. The past is kept.'
        : 'Nothing to remove.');
    cm_redirect('admin/settings.php');
}

require_once CM_LIB . '/priority.php';
require_once CM_LIB . '/demo.php';





require_once CM_LIB . '/publicsite.php';

require_once CM_LIB . '/branding.php';

require_once CM_LIB . '/reminders.php';











$logoError = '';





if (cm_post_too_large()) {
    $logoError = cm_logo_store(null)['error'];
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && in_array((string) ($_POST['action'] ?? ''), ['logo_upload', 'logo_remove'], true)) {
    cm_require_csrf();
    if ((string) $_POST['action'] === 'logo_remove') {
        cm_logo_delete();




        cm_audit('settings.changed', '', null,
                 'portal logo removed by ' . (string) $admin['email']);
        cm_flash('Logo removed — the business name is the mark again.');
        cm_redirect('admin/settings.php');
    }
    $r = cm_logo_store($_FILES['logo_file'] ?? null);
    if ($r['ok']) {




        cm_audit('settings.changed', '', null,
                 'portal logo set by ' . (string) $admin['email'] . ' — '
                 . $r['label'] . ' ' . $r['w'] . 'x' . $r['h'] . ', ' . cm_bytes_label($r['bytes']));
        cm_flash('Logo saved — it is on every portal page now.' . (($r['note'] ?? '') !== '' ? ' ' . $r['note'] : ''));
        cm_redirect('admin/settings.php');
    }
    $logoError = (string) $r['error'];
}





if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && in_array((string) ($_POST['action'] ?? ''), ['demo_on', 'demo_off'], true)) {
    cm_require_csrf();
    if ((string) $_POST['action'] === 'demo_on') {
        $why = cm_demo_blockers();
        if ($why) {
            cm_flash('Demo mode refused: ' . $why[0], 'error');
        } else {
            cm_flash('Demo mode is on. ' . cm_demo_enable((string) $admin['email'])
                   . ' Nothing can be changed or sent while it is on.');
        }
    } else {
        cm_flash(cm_demo_disable((string) $admin['email']));
    }
    cm_redirect('admin/settings.php');
}

$spec = cm_setting_spec();





$spec = array_filter($spec, static fn ($m) => !isset(cm_billing_groups()[$m['group'] ?? '']));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && isset($_POST['save_settings'])) {
    cm_require_csrf();

    $saved = 0;
    foreach ($spec as $key => $meta) {
        $field = str_replace('.', '__', $key);

        if (($meta['type'] ?? 'text') === 'bool') {


            $value = isset($_POST[$field]) ? '1' : '';
            if (cm_setting($key, '') !== $value || cm_setting_source($key) !== 'db') {
                cm_setting_set($key, $value, (string) $admin['email']);
                $saved++;
            }
            continue;
        }

        if (($meta['type'] ?? 'text') === 'order') {


            $ranks = (array) ($_POST[$field] ?? []);
            $keys  = array_keys(cm_priority_factors());
            $pairs = [];
            foreach ($keys as $i => $k) {
                $pairs[] = [(int) ($ranks[$k] ?? ($i + 1)), $i, $k];
            }
            usort($pairs, static fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            $value = implode(',', array_column($pairs, 2));
            if ((string) cm_setting($key, '') !== $value) {
                cm_setting_set($key, $value, (string) $admin['email']);
                $saved++;
            }
            continue;
        }

        if (($meta['type'] ?? 'text') === 'days') {


            $picked = array_values(array_intersect(
                array_map('strval', (array) ($_POST[$field] ?? [])),
                array_keys(cm_day_keys())));
            $value = implode(',', $picked);
            if ((string) cm_setting($key, '') !== $value) {
                cm_setting_set($key, $value, (string) $admin['email']);
                $saved++;
            }
            continue;
        }

        if (($meta['type'] ?? 'text') === 'choice') {























            $choices = cm_setting_choices($meta);
            $posted  = (string) ($_POST[$field] ?? '');
            if ($posted === '') continue;
            if (!isset($choices[$posted])) {


                cm_flash(($meta['label'] ?? $key) . ': that is not one of the options. Not saved.', 'error');
                continue;
            }
            if ((string) cm_setting($key, '') !== $posted) {
                cm_setting_set($key, $posted, (string) $admin['email']);
                $saved++;
            }
            continue;
        }

        if (!isset($_POST[$field])) continue;
        $value = trim((string) $_POST[$field]);

        if (($meta['type'] ?? 'text') === 'time'
            && $value !== '' && !preg_match('/^\d{1,2}:\d{2}$/', $value)) {
            cm_flash(($meta['label'] ?? $key) . ': use a time like 9:00 or 13:30. Not saved.', 'error');
            continue;
        }

        if (!empty($meta['secret']) && $value === '') {
            continue;
        }
        if ((string) cm_setting($key, '') === $value && cm_setting_source($key) === 'db') {
            continue;
        }
        cm_setting_set($key, $value, (string) $admin['email']);
        $saved++;
    }

    if ($saved > 0) {
        cm_audit('settings.saved', '', null, $saved . ' setting(s) changed by ' . (string) $admin['email']);
        cm_flash('Saved.');
    } else {
        cm_flash('Nothing had changed.', 'warn');
    }
    cm_redirect('admin/settings.php');
}

$groups = cm_general_groups();

cm_head('Settings', '', true);
cm_render_flashes();
cm_admin_nav('settings');
cm_admin_subnav('settings', 'general', 'Settings');
?>
<?php 









 ?>
<p class="sub">Review the settings that shape your workday. Business, tax and bank details are under
<a href="<?= e(cm_url('admin/billing-settings.php')) ?>">Invoicing</a>.</p>

<?php 











 ?>
<form method="post" id="set-form">
  <?= cm_csrf_field() ?>
  <?php 
 ?>
  <input type="hidden" name="save_settings" value="1">

  <?php 







 ?>
  <div class="set-grid">
  <?php foreach ($groups as $g => $ginfo): ?>
    <?php 




 ?>
    <?php if ($g === 'help') continue; ?>
    <?php $parked = !empty($ginfo['parked']); ?>
    <?php 









 ?>
    <?php 


 ?>
    <div class="card set-tile<?= $g === 'public' ? ' set-tile-wide' : '' ?><?= $parked ? ' set-parked' : '' ?>"
         id="set-<?= e($g) ?>">
      <?php if ($parked): ?>
        <?php 

 ?>
        <details>
          <summary><h2 class="d-ib m-0"><?= e($ginfo['title']) ?></h2>
            <span class="pill pill-pending">not in use</span></summary>
          <div class="mt-12">
      <?php else: ?>
        <h2><?= e($ginfo['title']) ?></h2>
      <?php endif; ?>
      <?php if ($ginfo['blurb'] !== ''): ?>
        <p class="muted small mb-12"><?= e($ginfo['blurb']) ?></p>
      <?php endif; ?>

      <?php 








 ?>
      <?php if ($g === 'workday'): ?>
        <?php require_once CM_LIB . '/workday.php'; ?>
        <?php $scheds = cm_workday_schedules(); $GLOBALS['CM_WORKDAYS'] = $scheds; ?>
        <?php 







 ?>
        <?php if (!$scheds): ?>
          <p class="muted small">No work hours set. Daily tasks can land on any day and capacity suggestions stay off.</p>
        <?php else: ?>
          <ul class="focus-list mb-12">
            <?php foreach ($scheds as $s): ?>
              <li>
                <span class="focus-name"><?= e((string) $s['label'] !== '' ? (string) $s['label'] : 'Working day') ?></span>
                <div class="focus-sub"><?= e(cm_workday_summary($s)) ?></div>
                <?php if ($s['breaks']): ?>
                  <ul class="brk-list">
                    <?php foreach ($s['breaks'] as $b): ?>
                      <li>
                        <span class="mono small"><?= e(substr((string) $b['starts'], 0, 5)) ?>&ndash;<?= e(substr((string) $b['ends'], 0, 5)) ?></span>
                        <span><?= e((string) $b['label']) ?></span>
                        <?php if ((int) $b['flexible'] === 1): ?>
                          <span class="pill">can slide</span>
                        <?php endif; ?>
                        <span class="spacer"></span>
                        <button class="btn btn-sm btn-quiet" type="submit"
                                form="rmbrk<?= (int) $b['id'] ?>">&times;</button>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
                <div class="focus-meta">
                  <button class="btn btn-sm btn-quiet" type="button"
                          data-modal-open="dlg-wd-<?= (int) $s['id'] ?>">Edit</button>
                  <button class="btn btn-sm btn-quiet" type="button"
                          data-modal-open="dlg-brk-<?= (int) $s['id'] ?>">Add a break</button>
                  <span class="spacer"></span>
                  <button class="btn btn-sm btn-quiet" type="submit"
                          form="rmwd<?= (int) $s['id'] ?>">Remove</button>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <p><button class="btn" type="button" data-modal-open="dlg-wd-0">Add a working day</button></p>
      <?php endif; ?>

      <?php if ($g === 'appt'): ?>
        <?php require_once CM_LIB . '/appointments.php'; ?>
        <?php $series = cm_appointment_series(); $GLOBALS['CM_APPT_SERIES'] = $series; ?>
        <?php 







 ?>
        <?php $apptState = cm_appointment_state($series); ?>
        <p class="set-state set-state-<?= e($apptState['tone']) ?>"><?= e($apptState['line']) ?></p>
        <p>
          <button class="btn" type="button" data-modal-open="dlg-new-appt">Add a recurring appointment</button>
          <button class="btn btn-quiet" type="button" data-modal-open="dlg-appt-defaults">Appointment defaults</button>
        </p>
        <?php if ($series): ?>
          <ul class="focus-list mb-12">
            <?php foreach ($series as $s): ?>
              <li>
                <span class="focus-name"><?= e((string) $s['title']) ?></span>
                <div class="focus-sub"><?= e($s['days']) ?> &middot;
                  <?= e(substr((string) $s['start_time'], 0, 5)) ?>&ndash;<?= e(substr((string) $s['end_time'], 0, 5)) ?>
                  <?php $bn = cm_appointment_buffer_note($s); ?>
                  <?php if ($bn !== ''): ?>, <?= e($bn) ?><?php endif; ?>
                </div>
                <div class="focus-meta">
                  <span class="muted small"><?= (int) $s['upcoming'] ?> still to come,
                    to <?= e(date('j M Y', strtotime((string) $s['last_on']))) ?></span>
                  <span class="spacer"></span>
                  <?php 





 ?>
                  <button class="btn btn-sm btn-quiet" type="submit"
                          form="rmseries<?= (int) $s['series_id'] ?>">Remove</button>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php 

 ?>
      <?php endif; ?>

      <?php 





 ?>
      <?php if ($g === 'appt') ob_start(); ?>
      <?php 
 ?>
      <?php if ($g === 'public'): ?><div class="set-fields-2"><?php endif; ?>
      <?php foreach ($spec as $key => $meta): if (($meta['group'] ?? '') !== $g) continue;
          $field  = str_replace('.', '__', $key);
          $secret = !empty($meta['secret']);
          $type   = $meta['type'] ?? 'text';
          $source = cm_setting_source($key);
      ?>
        <?php if ($type === 'order'): ?>
          <?php 


 ?>
          <?php $factors = cm_setting_choices($meta);
                $current = array_flip(cm_priority_order()); ?>
          <div class="row">
            <label class="lbl"><?= e($meta['label']) ?></label>
            <ul class="focus-list">
              <?php foreach ($factors as $fk => $f): ?>
                <li>
                  <div class="focus-meta">
                    <select class="f-xs" name="<?= e($field) ?>[<?= e((string) $fk) ?>]"
                            aria-label="Position of <?= e($f['label']) ?>">
                      <?php for ($i = 1; $i <= count($factors); $i++): ?>
                        <option value="<?= $i ?>" <?= (($current[$fk] ?? 99) + 1) === $i ? 'selected' : '' ?>><?= $i ?></option>
                      <?php endfor; ?>
                    </select>
                    <span class="focus-name"><?= e($f['label']) ?></span>
                  </div>
                  <?php if ($f['blurb'] !== ''): ?>
                    <div class="focus-sub"><?= e($f['blurb']) ?></div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
            <?php if (!empty($meta['hint'])): ?><div class="hint"><?= e($meta['hint']) ?></div><?php endif; ?>
            <p class="muted small mt-8">Right now:
              <b><?= e(cm_priority_sentence()) ?></b>.</p>
          </div>
        <?php elseif ($type === 'lines' || $type === 'textarea'): ?>
          <?php 









 ?>
          <div class="row">
            <label class="lbl" for="<?= e($field) ?>"><?= e($meta['label']) ?></label>
            <textarea id="<?= e($field) ?>" name="<?= e($field) ?>" rows="5" class="f-full"
                      spellcheck="false"><?= e((string) cm_setting($key, '')) ?></textarea>
            <?php if (!empty($meta['hint'])): ?><div class="hint"><?= e($meta['hint']) ?></div><?php endif; ?>
          </div>
        <?php elseif ($type === 'days'): ?>
          <div class="row">
            <label class="lbl"><?= e($meta['label']) ?></label>
            <?php $picked = array_flip(cm_workdays()); ?>
            <?php foreach (cm_day_keys() as $dk => $dl): ?>
              <label class="tick d-if mr-14">
                <input type="checkbox" name="<?= e($field) ?>[]" value="<?= e($dk) ?>"
                       <?= isset($picked[$dk]) ? 'checked' : '' ?>>
                <span><?= e(substr($dl, 0, 3)) ?></span>
              </label>
            <?php endforeach; ?>
            <?php if (!empty($meta['hint'])): ?><div class="hint"><?= e($meta['hint']) ?></div><?php endif; ?>
          </div>
        <?php elseif ($type === 'time'): ?>
          <div class="row">
            <label class="lbl" for="<?= e($field) ?>"><?= e($meta['label']) ?></label>
            <input class="f-sm" type="text" id="<?= e($field) ?>" name="<?= e($field) ?>" inputmode="numeric"
                   value="<?= e((string) cm_setting($key, '')) ?>" placeholder="9:00">
            <?php if (!empty($meta['hint'])): ?><div class="hint"><?= e($meta['hint']) ?></div><?php endif; ?>
          </div>
        <?php elseif ($type === 'choice'): ?>
          <?php 


 ?>
          <div class="row">
            <label class="lbl"><?= e($meta['label']) ?></label>
            <?php 


 ?>
            <?php $choices = cm_setting_choices($meta); ?>
            <?php $cur = (string) cm_setting($key, (string) array_key_first($choices)); ?>
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
                     <?= cm_setting($key, '') === '1' ? 'checked' : '' ?>>
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
        <?php else: ?>
          <div class="row">
            <label class="lbl" for="<?= e($field) ?>"><?= e($meta['label']) ?>
              <?php if ($source === 'config'): ?>
                <span class="muted small">(from config.php)</span>
              <?php elseif ($source === 'unset'): ?>
                <span class="muted small">(not set)</span>
              <?php endif; ?>
            </label>
            <?php if ($secret): ?>
              <input type="password" id="<?= e($field) ?>" name="<?= e($field) ?>" value=""
                     autocomplete="off"
                     placeholder="<?= cm_setting_present($key) ? 'set — type here to replace' : '' ?>">
            <?php else: ?>
              <input type="text" id="<?= e($field) ?>" name="<?= e($field) ?>"
                     value="<?= e((string) cm_setting($key, '')) ?>" autocomplete="off">
            <?php endif; ?>
            <?php if (!empty($meta['hint'])): ?>
              <div class="hint"><?= e($meta['hint']) ?></div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($g === 'public'): ?></div><?php endif; ?>
      <?php if ($g === 'mail'): ?>
      <?php 










 ?>
      <?php
        $cronLast  = (int) cm_setting('cron.last_run', 0);
        $cronSent  = (int) cm_setting('cron.last_sent', 0);
        $cronAgo   = $cronLast > 0 ? time() - $cronLast : 0;
        $waiting   = 0;
        try { $waiting = cm_followups_waiting(); } catch (Throwable $ex) {}


        $cronPath  = str_replace('\\', '/', dirname(__DIR__)) . '/tools/cron.php';
      ?>
      <div class="row">
        <label class="lbl">Automatic emails</label>

        <?php if ($cronLast > 0 && $cronAgo < 172800): ?>
          <p class="set-state m-0"><b>A scheduled run is working.</b>
          It last ran <?= $cronAgo < 3600
              ? (max(1, (int) round($cronAgo / 60)) . ' minutes ago')
              : (max(1, (int) round($cronAgo / 3600)) . ' hours ago') ?>
          and sent <?= $cronSent ?>.</p>
        <?php elseif ($cronLast > 0): ?>
          <p class="set-state set-state-warn m-0"><b>A scheduled run was set up
          but has not run for <?= (int) round($cronAgo / 86400) ?> days.</b>
          Either the cron was removed or it is failing — check the command below.</p>
        <?php else: ?>
          <?php 








 ?>
          <p class="set-state m-0"><b>A cron is optional.</b>
          Without one, follow-up emails go out when you open the
          Dashboard &mdash; late, not lost. With one, they go on time.</p>
        <?php endif; ?>

        <?php if ($waiting > 0): ?>
          <p class="muted small mt-8"><?= $waiting ?> follow-up<?= $waiting === 1 ? ' is' : 's are' ?>
          waiting to go out<?= $waiting > CM_FOLLOWUP_BATCH
              ? ' — ' . CM_FOLLOWUP_BATCH . ' per run, so that is '
                . (int) ceil($waiting / CM_FOLLOWUP_BATCH) . ' runs to clear'
              : '' ?>.</p>
        <?php endif; ?>

        <div class="actions mt-10">
          <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-cron">Set up a scheduled run&hellip;</button>
        </div>
        <div class="hint">Only worth doing if messages must go out on the day
        they are due whether or not you sign in.</div>
      </div>
      <?php endif; ?>
      <?php if ($g === 'public'): ?>
      <?php 





 ?>
      <?php $logoSize = cm_logo_size(); ?>
      <div class="row">
        <label class="lbl">Your logo</label>
        <?php if ($logoSize !== null): ?>
          <div class="logo-preview">
            <img src="<?= e(cm_logo_url()) ?>" alt="Your logo">
            <span class="muted small mono"><?= (int) $logoSize['w'] ?> &times; <?= (int) $logoSize['h'] ?>
              &middot; <?= e(cm_bytes_label((int) $logoSize['bytes'])) ?></span>
          </div>
          <div class="actions mt-8">
            <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-logo">Replace it</button>
            <button class="btn btn-sm btn-quiet" type="submit" form="logo-remove">Remove it</button>
          </div>
        <?php else: ?>
          <p class="muted small m-0 mb-8">No logo &mdash; the business name above
          is the mark on every portal page.</p>
          <button class="btn btn-sm" type="button" data-modal-open="dlg-logo">Upload a logo</button>
        <?php endif; ?>
        <div class="hint"><?= e(cm_logo_advice()) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($g === 'appt') { $GLOBALS['CM_APPT_FIELDS'] = (string) ob_get_clean(); } ?>
      <?php if ($parked): ?></div></details><?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>

  <?php 


 ?>
  <div class="set-save">
    <button class="btn" type="submit">Save settings</button>
    <span class="muted small">Saves every group on this page.</span>
  </div>
</form>

<?php 



 ?>
<?php $demoNow = cm_demo_on(); $demoWhy = $demoNow ? [] : cm_demo_blockers(); ?>
<?php 









 ?>
<?php $helpMeta = $spec['help.enabled']; ?>
<div class="card" id="demo">
  <div class="card-head">
    <h2>Help and demo data</h2>
    <span class="spacer"></span>
    <?php if ($demoNow): ?><span class="pill pill-pending">demo on</span><?php endif; ?>
  </div>
  <p class="sub">Two things for finding your way around, rather than for
  running the business.</p>

  <h3 class="work-h">The Help button</h3>
  <label class="tick mb-10">
    <input type="checkbox" name="help__enabled" value="1" form="set-form"
           <?= cm_setting('help.enabled', '') === '1' ? 'checked' : '' ?>>
    <span><b><?= e($helpMeta['label']) ?></b>
      <span class="muted small d-block"><?= e($helpMeta['hint']) ?></span>
    </span>
  </label>
  <div class="actions mt-0 mb-16">
    <button class="btn btn-sm" type="submit" form="set-form">Save</button>
    <span class="muted small">saves this page, like the Save at the top</span>
  </div>

  <h3 class="work-h">Demo mode</h3>
  <p class="muted small mb-12">Fills the application with fifty invented
  clients, two services and fifty jobs spread across the last fifty days, so
  you can look around a working diary. <b>Nothing can be changed, added or
  sent while it is on</b> — it is there to be read.</p>

  <?php if ($demoNow): ?>
    <div class="note note-warn">
      <b>Demo mode is on.</b> Everything you can see was invented
      <?= e(cm_demo_generated_at() !== ''
            ? 'on ' . date('j M Y, g:ia', strtotime(cm_demo_generated_at()))
            : 'when you turned it on') ?>, and every save, send and delete is
      refused until you turn it off.
      <p class="m-0 mt-8">Leaving removes every invented row and puts your own
      workday and workload settings back exactly as they were.</p>
    </div>
    <form method="post">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="demo_off">
      <button class="btn" type="submit">Turn demo mode off and clear it</button>
    </form>
  <?php elseif ($demoWhy): ?>
    <div class="note note-info">
      <b>Not available — there is real data here.</b>
      Demo mode invents clients, and they must never sit beside yours where
      one could be mistaken for the other.
      <ul class="mb-0 mt-8">
        <?php foreach ($demoWhy as $w): ?><li class="small"><?= e($w) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <p class="muted small m-0">It is offered on an empty install — a fresh
    copy, or one where you have removed everything to start again.</p>
  <?php else: ?>
    <p class="muted small mb-12">The dates are generated when you turn it on
    and are relative to today, so it never looks stale.</p>
    <form method="post"
          data-confirm="Fill this application with fifty invented clients? Nothing can be changed or sent while demo mode is on, and leaving removes every one of them.">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="demo_on">
      <button class="btn" type="submit">Enter demo mode</button>
    </form>
  <?php endif; ?>
</div>
<?php 









 ?>
<?php if (!empty($GLOBALS['CM_APPT_FIELDS'])): ?>
  <dialog class="modal modal-wide" id="dlg-appt-defaults">
    <div class="modal-box">
      <div class="modal-head">
        <h2>Appointment defaults</h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">What a NEW appointment starts with, and how far
      ahead a repeating one is written into the diary. Changing these never
      touches an appointment already in there.</p>
      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="save_settings" value="1">
        <?= $GLOBALS['CM_APPT_FIELDS'] ?>
        <div class="modal-foot">
          <button class="btn" type="submit">Save defaults</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>
<?php endif; ?>

<?php 


 ?>
<?php require_once CM_LIB . '/workday.php'; ?>
<?php $wdList = (array) ($GLOBALS['CM_WORKDAYS'] ?? []); ?>
<?php foreach (array_merge([null], $wdList) as $s): ?>
  <?php $wid = $s ? (int) $s['id'] : 0; ?>
  <dialog class="modal" id="dlg-wd-<?= $wid ?>">
    <div class="modal-box">
      <div class="modal-head">
        <h2><?= $s ? 'Edit this working day' : 'Add a working day' ?></h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="workday_save">
        <input type="hidden" name="workday_id" value="<?= $wid ?>">
        <div class="row">
          <label class="lbl" for="wdl<?= $wid ?>">Call it</label>
          <input type="text" id="wdl<?= $wid ?>" name="label" class="f-full" maxlength="60"
                 value="<?= $s ? e((string) $s['label']) : '' ?>"
                 placeholder="Weekdays, Saturday, Sunday evening…">
        </div>
        <div class="row">
          <label class="lbl">Applies to</label>
          <div class="days">
            <?php foreach (cm_workday_weekdays() as $n => $label): ?>
              <label class="day-tick">
                <input type="checkbox" name="weekdays[]" value="<?= (int) $n ?>"
                       <?= $s && isset($s['days'][$n]) ? 'checked' : '' ?>>
                <span><?= e($label) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="row">
          <label class="lbl" for="wds<?= $wid ?>">From</label>
          <input class="f-sm" type="text" id="wds<?= $wid ?>" name="starts" placeholder="7:00"
                 value="<?= $s ? e(substr((string) $s['starts'], 0, 5)) : '' ?>">
          <label class="lbl" for="wde<?= $wid ?>">to</label>
          <input class="f-sm" type="text" id="wde<?= $wid ?>" name="ends" placeholder="20:00"
                 value="<?= $s ? e(substr((string) $s['ends'], 0, 5)) : '' ?>">
          <div class="hint">The outer edges of the day. The gaps inside it are
          breaks, added separately &mdash; a day that starts at 7, stops for the
          school run and finishes at 8 is one working day with breaks in it.</div>
        </div>
        <div class="modal-foot">
          <button class="btn" type="submit">Save</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>
<?php endforeach; ?>

<?php foreach ($wdList as $s): ?>
  <dialog class="modal" id="dlg-brk-<?= (int) $s['id'] ?>">
    <div class="modal-box">
      <div class="modal-head">
        <h2>Add a break</h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">To <?= e((string) $s['label'] !== '' ? (string) $s['label'] : 'this working day') ?>
      &mdash; <?= e(implode(', ', $s['days'])) ?>.</p>
      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="break_add">
        <input type="hidden" name="workday_id" value="<?= (int) $s['id'] ?>">
        <div class="row">
          <label class="lbl" for="bl<?= (int) $s['id'] ?>">What it is</label>
          <input type="text" id="bl<?= (int) $s['id'] ?>" name="label" class="f-full" maxlength="60"
                 placeholder="School run, lunch, dinner with the kids…">
        </div>
        <div class="row">
          <label class="lbl" for="bs<?= (int) $s['id'] ?>">From</label>
          <input class="f-sm" type="text" id="bs<?= (int) $s['id'] ?>" name="starts" placeholder="8:45">
          <label class="lbl" for="be<?= (int) $s['id'] ?>">to</label>
          <input class="f-sm" type="text" id="be<?= (int) $s['id'] ?>" name="ends" placeholder="9:15">
        </div>
        <?php 


 ?>
        <label class="tick mb-12">
          <input type="checkbox" name="flexible" value="1">
          <span>This one can slide
            <span class="muted small d-block">Lunch "around 1:00, depending on
            workload" can move. A school pickup cannot.</span></span>
        </label>
        <div class="modal-foot">
          <button class="btn" type="submit">Add the break</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>
<?php endforeach; ?>

<?php foreach ($wdList as $s): ?>
  <form method="post" id="rmwd<?= (int) $s['id'] ?>"
        data-confirm="Remove this working day? The days it covers go back to having no stated hours.">
    <?= cm_csrf_field() ?>
    <input type="hidden" name="action" value="workday_remove">
    <input type="hidden" name="workday_id" value="<?= (int) $s['id'] ?>">
  </form>
  <?php foreach ($s['breaks'] as $b): ?>
    <form method="post" id="rmbrk<?= (int) $b['id'] ?>">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="break_remove">
      <input type="hidden" name="break_id" value="<?= (int) $b['id'] ?>">
    </form>
  <?php endforeach; ?>
<?php endforeach; ?>

<?php 




 ?>
<?= cm_credential_tools_script_once() ?>

<?php 



 ?>
<dialog class="modal modal-wide" id="dlg-cron">
  <div class="modal-box">
    <div class="modal-head">
      <h2>Send follow-ups on a schedule</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <p class="modal-sub">Optional. Without it, follow-ups go out when you open
    the Dashboard &mdash; which is fine for most people. Set this up if a
    message must go on the day it is due even when you do not sign in.</p>

    <h3 class="work-h">1 &middot; The command</h3>
    <p class="muted small">In cPanel: <b>Advanced → Cron Jobs</b>. Paste this
    into the Command box exactly as it is.</p>
    <div class="lbl-row">
      <input type="text" id="cron_cmd" class="mono f-full small" readonly
             value="php <?= e($cronPath) ?>">
      <button class="btn btn-sm btn-quiet" type="button" data-copy-for="cron_cmd">Copy</button>
    </div>
    <p class="hint">If your host wants a full path to PHP, it is usually
    <span class="mono">/usr/local/bin/php</span> &mdash; their support will
    tell you. Nothing here is reachable from a browser, so there is no URL
    and no secret key to keep safe.</p>

    <h3 class="work-h mt-16">2 &middot; How often &mdash; the maths</h3>
    <p class="muted small"><b><?= CM_FOLLOWUP_BATCH ?> messages go out per
    run</b>, oldest first. That cap is deliberate: it keeps one page load or
    one run from trying to send a whole backlog at once, and it keeps you
    under the hourly outbound limit almost every shared host enforces
    (commonly 200&ndash;500 an hour &mdash; go over it and the host freezes
    the whole domain's mail, not just one message).</p>
    <p class="muted small">So the schedule is the throttle:</p>
    <div class="table-wrap">
      <table>
        <thead><tr><th>How often</th><th>cPanel setting</th><th>Runs a day</th><th>Messages a day</th></tr></thead>
        <tbody>
          <tr><td>Once a day</td><td class="mono small">Once Per Day</td>
              <td class="mono">1</td><td class="mono"><?= CM_FOLLOWUP_BATCH ?></td></tr>
          <tr><td><b>Every hour</b></td><td class="mono small">Once Per Hour</td>
              <td class="mono">24</td>
              <td class="mono"><b><?= CM_FOLLOWUP_BATCH * 24 ?></b></td></tr>
          <tr><td>Every 15 minutes</td><td class="mono small">Every 15 Minutes</td>
              <td class="mono">96</td><td class="mono"><?= CM_FOLLOWUP_BATCH * 96 ?></td></tr>
        </tbody>
      </table>
    </div>
    <p class="muted small mt-8"><b>Hourly is the right answer for almost
    everybody</b> &mdash; <?= CM_FOLLOWUP_BATCH ?> &times; 24 =
    <b><?= CM_FOLLOWUP_BATCH * 24 ?> a day</b>, which is far more than a
    one-person business will ever schedule, and comfortably under every host
    limit. Only go to 15 minutes if you are clearing a large backlog and you
    know your host allows it.</p>
    <p class="muted small">A run with nothing due does nothing at all and
    costs no email, so a frequent schedule is not wasteful.</p>

    <h3 class="work-h mt-16">3 &middot; Check it worked</h3>
    <p class="muted small">This screen will say <b>"A scheduled run is
    working"</b> with the time of the last one, within an hour of setting it
    up. To try it by hand first, from a shell:</p>
    <div class="lbl-row">
      <input type="text" id="cron_dry" class="mono f-full small" readonly
             value="php <?= e($cronPath) ?> --dry-run">
      <button class="btn btn-sm btn-quiet" type="button" data-copy-for="cron_dry">Copy</button>
    </div>
    <p class="hint"><span class="mono">--dry-run</span> lists what would be
    sent and sends nothing.</p>

    <div class="modal-foot">
      <button class="btn" type="button" data-modal-close>Done</button>
    </div>
  </div>
</dialog>

<?php 








 ?>
<dialog class="modal" id="dlg-logo"<?= !empty($logoError) ? ' data-modal-autoopen' : '' ?>>
  <div class="modal-box">
    <div class="modal-head">
      <h2>Your logo</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <p class="modal-sub">It replaces the business name in the header of every
    portal page. Nothing else on your site changes.</p>

    <?php if (!empty($logoError)): ?>
      <div class="note note-error"><?= e((string) $logoError) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="logo_upload">
      <div class="row">
        <label class="lbl" for="logo_file">Choose the file</label>
        <input type="file" id="logo_file" name="logo_file" accept="image/png,image/jpeg"<?= cm_upload_limit_attrs() ?>>
        <div class="hint"><?= e(cm_logo_advice()) ?></div>
      </div>
      <?php 


 ?>
      <p class="muted small">PNG or JPEG, up to
        <?= e(cm_bytes_label(CM_LOGO_MAX_BYTES)) ?> and
        <?= CM_LOGO_MAX_PIXELS ?> pixels on a side. <b>SVG is not accepted</b>
        &mdash; an SVG can carry scripts, and it would be served from your own
        address, which is the one place a browser trusts. Ask whoever made
        your logo for a PNG with a transparent background.</p>
      <div class="modal-foot">
        <button class="btn" type="submit">Upload it</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
  </div>
</dialog>

<form method="post" id="logo-remove" data-confirm="Remove the logo? The business name goes back in the header.">
  <?= cm_csrf_field() ?>
  <input type="hidden" name="action" value="logo_remove">
</form>

<?php 

 ?>
<?php foreach ((array) ($GLOBALS['CM_APPT_SERIES'] ?? []) as $s): ?>
  <form method="post" id="rmseries<?= (int) $s['series_id'] ?>"
        data-confirm="Remove the rest of this repeating appointment? Ones already in the past are kept.">
    <?= cm_csrf_field() ?>
    <input type="hidden" name="action" value="appt_series_remove">
    <input type="hidden" name="series_id" value="<?= (int) $s['series_id'] ?>">
  </form>
<?php endforeach; ?>

<?php cm_foot(); ?>
