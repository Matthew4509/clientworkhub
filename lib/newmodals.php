<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0

























declare(strict_types=1);

if (!defined('CM_ROOT')) { http_response_code(404); exit; }





function cm_new_modals(string $base): void
{








    require_once CM_LIB . '/clients.php';
    require_once CM_LIB . '/format.php';
    require_once CM_LIB . '/tax.php';
    require_once CM_LIB . '/appointments.php';
    require_once CM_LIB . '/reminders.php';
    require_once CM_LIB . '/jobs.php';
    ?>
<?php 





 ?>
<script defer src="<?= e(cm_url('js/task-rows.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>


<?php 















 ?>

<?php 







 ?>
<dialog class="modal" id="dlg-new-appt">
  <div class="modal-box">
    <div class="modal-head">
      <h2>Add an appointment</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <?php 




 ?>
    <form method="post" action="<?= e($base) ?>/admin/">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="appt_add">

      <?php 

 ?>
      <div class="row">
        <label class="lbl" for="ap_kind">Appointment description</label>
        <select id="ap_kind" name="ap_kind" class="f-full">
          <?php foreach (cm_appointment_kinds() as $kind => $isWork): ?>
            <option value="<?= e($kind) ?>" data-work="<?= $isWork ? '1' : '0' ?>"><?= e($kind) ?></option>
          <?php endforeach; ?>
          <option value="">&mdash; something else &mdash;</option>
        </select>
        <?php 



 ?>
        <div class="hint">The list is yours &mdash;
        <a href="<?= e(cm_url('admin/settings.php')) ?>">Settings &rarr; Appointments</a>.<br>
        A client meeting counts as work; the school run does not.</div>
      </div>

      <div class="row">
        <label class="lbl" for="ap_title">Description</label>
        <input type="text" id="ap_title" name="ap_title" class="f-full" maxlength="160"
               placeholder="Optional — left empty it just says what it is">
      </div>

      <div class="row">
        <label class="lbl" for="ap_date">When</label>
        <input class="f-auto" type="date" id="ap_date" name="ap_date" value="<?= e(date('Y-m-d')) ?>">
        <?php 
















 ?>
        <input class="f-auto" type="time" name="ap_from" aria-label="Start time">
        <input class="f-auto" type="time" name="ap_to" aria-label="End time">
      </div>

      <?php 


 ?>
      <div class="row">
        <label class="lbl" for="ap_before">Block out either side (ie, travel time, optional)</label>
        <input class="f-xs" type="text" id="ap_before" name="ap_before" inputmode="numeric"
               placeholder="0" aria-label="Minutes before">
        <span class="muted small">min before</span>
        <input class="f-xs" type="text" name="ap_after" inputmode="numeric"
               placeholder="0" aria-label="Minutes after">
        <span class="muted small">min after</span>
      </div>

      <div class="row">
        <label class="lbl" for="ap_client">About a client</label>
        <?= cm_client_picker('ap_client', 0, false, 'ap_client') ?>
      </div>

      <?php 

 ?>
      <div class="row">
        <label class="lbl">Repeating</label>
        <?php 


 ?>
        <div class="days mb-8">
          <?php foreach (['none' => 'One-off', 'daily' => 'Daily',
                          'weekdays' => 'Weekdays', 'weekend' => 'Weekend'] as $k => $label): ?>
            <button class="day-preset" type="button" data-days-preset="<?= e($k) ?>"><?= e($label) ?></button>
          <?php endforeach; ?>
        </div>
        <div class="days">
          <?php foreach (cm_weekdays() as $n => $label): ?>
            <label class="day-tick">
              <input type="checkbox" name="ap_days[]" value="<?= (int) $n ?>">
              <span><?= e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <?php 

 ?>
      <input type="hidden" name="ap_workset" value="1">
      <label class="tick mb-12">
        <input type="checkbox" name="ap_offwork" value="1">
        <span>Not work &mdash; keep it out of the day&rsquo;s hours and value</span>
      </label>

      <div class="modal-foot">
        <button class="btn" type="submit">Add it</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
  </div>
</dialog>

<?php 







 ?>
<dialog class="modal" id="dlg-new-reminder">
  <div class="modal-box">
    <div class="modal-head">
      <h2>Set a reminder</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <form method="post" action="<?= e($base) ?>/admin/reminders.php">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="row">
        <label class="lbl" for="n_title">Remind me to</label>
        <input type="text" id="n_title" name="title" class="f-full" maxlength="190" required
               placeholder="Chase the hosting invoice">
      </div>
      <div class="row">
        <label class="lbl" for="n_due">On</label>
        <input class="f-base" type="date" id="n_due" name="due" value="<?= e(date('Y-m-d')) ?>" required>
        <select class="f-auto" name="repeat">
          <?php foreach (cm_reminder_repeats() as $k => $label): ?>
            <option value="<?= e($k) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint">A repeating reminder makes the next one the moment
        this one is done.</div>
      </div>
      <div class="row">
        <label class="lbl" for="n_client">About</label>
        <?= cm_client_picker('client_id', 0, false, 'n_client') ?>
        <div class="hint">Name, email, phone or website. Leave it empty for a
        reminder about nobody in particular.</div>
      </div>
      <div class="row">
        <label class="lbl" for="n_desc">Details</label>
        <input type="text" id="n_desc" name="description" class="f-full"
               placeholder="Anything future-you needs to know.">
      </div>
      <div class="row">
        <label class="lbl">Priority</label>
        <div class="caps-pick">
          <?php foreach (cm_job_priorities() as $k => $pr): ?>
            <label class="caps-row">
              <input type="radio" name="priority" value="<?= e($k) ?>" <?= $k === 'normal' ? 'checked' : '' ?>>
              <span><b><?= e($pr['label']) ?></b></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn" type="submit">Set the reminder</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
  </div>
</dialog>

<?php 



















 ?>

<?php 







 ?>
<?php 









 ?>
<dialog class="modal modal-widest" id="dlg-new-service"<?= (string) ($_GET['open'] ?? '') === 'new-service' ? ' data-modal-autoopen' : '' ?>>
  <div class="modal-box">
    <div class="modal-head">
      <h2>New service</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <p class="modal-sub">A standard job, with its price and its length. New
    jobs get stamped from it.</p>
    <?php require_once CM_LIB . '/services.php'; ?>

    <?php 













 ?>
    <form method="post" action="<?= e($base) ?>/admin/services.php" data-wizard>
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="add_template">

      <div data-step data-step-title="New service">
      <div class="form-2col">
        <div class="form-col">

          <section class="form-step">
            <h3><span class="step-n">1</span> Service details</h3>
            <div class="row">
              <label class="lbl" for="s_name">Service name</label>
              <input type="text" id="s_name" name="name" class="f-full" maxlength="160" required
                     placeholder="e.g. Update website daily for 7 days">
            </div>
            <div class="row">
              <label class="lbl" for="s_desc">Description <span class="muted">(optional)</span></label>
              <textarea id="s_desc" name="public_blurb" rows="3" class="f-full" maxlength="500"
                        placeholder="Briefly describe what this service includes…"></textarea>
              <div class="hint">Shown on the client portal, if you use it.</div>
            </div>
            <?php 







 ?>
            <?php $newCats = function_exists('cm_service_category_map') ? cm_service_category_map() : []; ?>
            <div class="row">
              <label class="lbl" for="s_cat">Category</label>
              <div class="snip-add">
                <select id="s_cat" name="category_id" class="f-lg">
                  <option value="0">&mdash; none &mdash;</option>
                  <?php foreach ($newCats as $cid => $cname): ?>
                    <option value="<?= (int) $cid ?>"><?= e($cname) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-svc-cat">Add new</button>
              </div>
            </div>
          </section>

          <section class="form-step">
            <h3><span class="step-n">2</span> Work required</h3>

            <div class="row">
              <label class="lbl">How is this work completed?</label>
              <div class="caps-pick caps-2 caps-abreast" data-shows="svc-span">
                <label class="caps-row">
                  <input type="radio" name="frequency" value="oneoff" checked>
                  <span><b>One-off</b>
                    <span class="muted small d-block">Done in one day</span></span>
                </label>
                <label class="caps-row">
                  <input type="radio" name="frequency" value="daily">
                  <span><b>Spread over several days</b>
                    <span class="muted small d-block">A share each working day.</span></span>
                </label>
              </div>
            </div>

            <?php 










 ?>
            <?php 















 ?>
            <div>
              <h4 class="work-h">Schedule</h4>
              <?php 



 ?>
              <div class="form-row row-even">
                <div class="field field-tight">
                  <?php 







 ?>
                  <label class="lbl" for="s_days">Working days</label>
                  <input class="f-sm" type="text" id="s_days" name="duration_days" inputmode="numeric"
                         value="1" data-avg-days>
                </div>
                <div class="field field-tight">
                  <label class="lbl" for="s_total">Total work (hours)</label>
                  <input class="f-sm" type="text" id="s_total" name="total_hours" inputmode="decimal"
                         data-exclusive-with="s_perday" data-avg-total>
                </div>
                <div class="field field-tight">
                  <label class="lbl" for="s_avg">Average per day</label>
                  <input class="f-sm f-derived" type="text" id="s_avg" tabindex="-1" readonly
                         data-avg-out placeholder="&mdash;">
                </div>
              </div>

              <?php 




 ?>
              <div class="row">
                <label class="lbl" for="s_perday">Optional: time per day (minutes)</label>
                <input class="f-sm" type="text" id="s_perday" name="per_day_minutes" inputmode="numeric"
                       data-exclusive-with="s_total">
                <div class="hint">Enter total work time <b>or</b> a time per
                day, never both.</div>
              </div>
            </div>

          </section>

        </div><?php  ?>

        <div class="form-col">

          <?php 


 ?>
          <?php 









 ?>
          <section class="form-step" data-when="svc-span" data-when-value="daily"
                   data-when-effect="dim">
            <h3><span class="step-n">3</span> Additional time</h3>
            <?php 










 ?>
            <div class="form-row row-even">
              <div class="field field-tight">
                <label class="lbl" for="s_setup">Setup time (minutes)</label>
                <input class="f-full" type="text" id="s_setup" name="setup_minutes" inputmode="numeric">
                <div class="hint">Extra time for the 1st working day.</div>
              </div>
              <div class="field field-tight">
                <label class="lbl" for="s_compl">Wrap-up time (minutes)</label>
                <input class="f-full" type="text" id="s_compl" name="completion_minutes" inputmode="numeric">
                <div class="hint">Added to the end of the project.</div>
              </div>
            </div>
            <div class="row">
              <label class="lbl">When the wrap-up happens</label>
              <div class="caps-pick caps-2 caps-abreast">
                <label class="caps-row">
                  <input type="radio" name="completion_mode" value="last_day" checked>
                  <span><b>Add to the last day</b>
                    <span class="muted small d-block">Rolled into the last day.</span></span>
                </label>
                <label class="caps-row">
                  <input type="radio" name="completion_mode" value="own_day">
                  <span><b>Give it its own day</b>
                    <span class="muted small d-block">Adds one day to the run.</span></span>
                </label>
              </div>
            </div>
          </section>

          <section class="form-step">
            <h3><span class="step-n">4</span> Pricing</h3>
            <div class="row">
              <label class="lbl" for="s_amount">Standard price</label>
              <input class="f-sm" type="text" id="s_amount" name="amount" inputmode="decimal">
              <div class="hint">Set default price. <b>NOTE:</b> Customizable when setting up jobs.</div>
            </div>
            <?php $sn = cm_tax_price_note(); ?>
            <?php if ($sn !== ''): ?><p class="hint"><b><?= e($sn) ?></b></p><?php endif; ?>
            <?php if (cm_tax_charges()): ?>
              <label class="tick">
                <input type="checkbox" name="taxable" value="1" checked>
                <span><?= e(cm_tax_settings()['name']) ?> applies to this service</span>
              </label>
            <?php endif; ?>
          </section>

          <?php 




 ?>
          <section class="form-step">
            <h3><span class="step-n">5</span> Snippets
              <span class="pill pill-soon">coming soon</span></h3>
            <div class="row">
              <label class="lbl" for="s_snipcat">Snippet library</label>
              <select id="s_snipcat" name="snippet_category" class="f-full" disabled>
                <option value="all" selected>All snippets</option>
              </select>
              <div class="hint">Custom snippet categories coming soon.</div>
            </div>
          </section>

        </div><?php  ?>
      </div>
      </div><?php  ?>

      <div class="wiz-bar">
        <span class="wiz-count" data-wizard-count></span>
        <span class="spacer"></span>
        <?php 



 ?>
        <button class="btn" type="button" data-wizard-next>Next</button>
        <button class="btn" type="submit" data-wizard-submit>Create service</button>
        <button class="btn btn-quiet" type="button" data-wizard-back>Back</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
  </div>
</dialog>

<?php 





















 ?>
    <?php
}
