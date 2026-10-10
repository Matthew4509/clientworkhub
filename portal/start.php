<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0











declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/publicview.php';
require_once CM_LIB . '/onboarding.php';

cm_public_require_enabled();

$res = cm_onboard_resolve((string) ($_GET['k'] ?? ''));
if ($res === null) {
    cm_public_404('That link has expired. Get in touch and we will send a new one.');
}




if ((string) $res['status'] === 'complete') {
    cm_public_head('All done');
    ?>
    <div class="pub-narrow">
      <h1>That is all done</h1>
      <p class="muted">Thank you — we have everything we need and this form is
      closed. If something has changed, just email us and we will update it.</p>
    </div>
    <?php
    cm_public_foot();
    exit;
}




$fields = cm_onboard_fields_for_response($res);
$given  = cm_onboard_answers((int) $res['id']);







$isMessage = (int) ($res['is_message'] ?? 0) === 1;
$asksSig   = cm_onboard_asks_signature() && !$isMessage;
$heading   = trim((string) ($res['heading'] ?? ''));
if ($heading === '') {
    $heading = $isMessage ? ($fields ? 'A few quick questions' : 'A message for you')
                          : 'A few things to get started';
}





if (cm_is_opened_ping()) {
    cm_require_csrf();
    cm_onboard_opened_stamp((int) $res['id']);
    cm_opened_ping_done();
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') cm_onboard_opened_stamp((int) $res['id']);
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $spam = cm_public_spam_check('onboard');
    if ($spam === 'refused') {
        cm_redirect('portal/start.php?k=' . rawurlencode((string) ($_GET['k'] ?? $_POST['k'] ?? '')) . '&done=1');
    }
    if ($spam !== '') {
        $errors['_'] = $spam;
    } else {

        foreach ($fields as $f) {

            if ((int) $f['required'] !== 1 || (string) $f['ftype'] === 'properties'
                || !cm_onboard_asks((string) $f['ftype'])) continue;
            if (trim((string) ($_POST['f_' . $f['fkey']] ?? '')) === '') {
                $errors[(string) $f['fkey']] = 'We do need this one.';
            }
        }




        $sigData = (string) ($_POST['signature'] ?? '');
        $sigName = trim((string) ($_POST['signed_name'] ?? ''));
        if ($asksSig) {
            $sigProblem = cm_onboard_signature_problem($sigData, $sigName);
            if ($sigProblem !== '') $errors['_signature'] = $sigProblem;
        }
        if (!$errors) {
            if ($asksSig) {
                cm_onboard_signature_store((int) $res['id'], $sigData, $sigName);
            }
            foreach ($fields as $f) {
                $key = (string) $f['fkey'];






                if (!cm_onboard_asks((string) $f['ftype'])) continue;

                if ((string) $f['ftype'] === 'properties') {




                    if ($res['client_id'] === null) continue;
                    $names = (array) ($_POST['p_name'] ?? []);
                    $stored = 0;
                    foreach ($names as $i => $n) {
                        if (trim((string) $n) === '') continue;
                        $stored += cm_onboard_property_store((int) $res['client_id'], [
                            'name'     => (string) $n,
                            'link'     => (string) (($_POST['p_link'] ?? [])[$i] ?? ''),
                            'username' => (string) (($_POST['p_user'] ?? [])[$i] ?? ''),
                            'password' => (string) (($_POST['p_pass'] ?? [])[$i] ?? ''),
                        ], 'the client') > 0 ? 1 : 0;
                    }

                    cm_onboard_answer_set((int) $res['id'], $key, (string) $f['label'],
                        $stored === 0 ? 'none given'
                                      : $stored . ' propert' . ($stored === 1 ? 'y' : 'ies') . ' added');
                    continue;
                }





                $answer = (string) ($_POST['f_' . $key] ?? '');
                $answer = (string) $f['ftype'] === 'url'
                        ? cm_web_address_tidy($answer) : trim($answer);
                cm_onboard_answer_set((int) $res['id'], $key, (string) $f['label'], $answer);
            }

            cm_onboard_complete((int) $res['id']);

            $to = cm_mail_reply_to() !== '' ? cm_mail_reply_to() : cm_mail_from();
            if ($to !== '') {
                cm_mail($to,
                    $isMessage ? 'Your message was answered' : 'Onboarding completed',
                    ($isMessage ? "A client has answered the questions on your message.\n\n"
                                : "A client has filled in their onboarding form.\n\n")
                    . 'Read it: ' . cm_url('admin/onboarding.php') . "\n",
                    ['kind' => 'onboarding']);
            }
            cm_redirect('portal/start.php?k=' . rawurlencode((string) ($_GET['k'] ?? $_POST['k'] ?? '')) . '&done=1');
        }
    }
}

if (isset($_GET['done'])) {
    cm_public_head('Thank you');
    ?>
    <div class="pub-narrow">
      <h1>Thank you</h1>
      <?php if ($isMessage): ?>
        <p class="pub-intro">Your answers are on their way — we will be in
        touch if anything needs a follow-up.</p>
      <?php else: ?>
        <p class="pub-intro">That is everything we need to get started. We will
        be in touch if anything is unclear.</p>
        <p class="muted small">If you gave us passwords, feel free to change
        them once we are set up — just let us know when you do.</p>
      <?php endif; ?>
    </div>
    <?php
    cm_public_foot();
    exit;
}

cm_public_head($isMessage ? 'A message' : 'Getting started');
echo cm_opened_ping_marker();
?>
<section class="pub-hero pub-hero-tight">
  <h1><?= e($heading) ?></h1>
  <?php if (!$isMessage): ?>
    <?php 

 ?>
    <p class="pub-intro">This form helps us ensure we have all the information
    we need. You can add in extra details, or skip anything that&rsquo;s not
    relevant.</p>
  <?php endif; ?>
</section>

<?php if (trim((string) ($res['message'] ?? '')) !== ''): ?>
  <?php 
 ?>
  <div class="pub-message">
    <p class="prewrap m-0"><?= e((string) $res['message']) ?></p>
  </div>
<?php endif; ?>

<?php if (isset($errors['_'])): ?>
  <div class="note note-error"><?= e($errors['_']) ?></div>
<?php elseif ($errors): ?>
  <div class="note note-error">Just one or two things missing below.</div>
<?php endif; ?>

<?php if ($isMessage && !$fields && !$asksSig): ?>
  <?php 

 ?>
<?php else: ?>
<form method="post" class="pub-form">
  <?= cm_csrf_field() ?>
  <div class="hp-field" aria-hidden="true">
    <label for="company_website">Leave this empty</label>
    <input type="text" id="company_website" name="company_website" tabindex="-1" autocomplete="off">
  </div>

  <?php foreach ($fields as $f):
      $key = (string) $f['fkey'];
      $val = (string) ($given[$key] ?? '');
      $bad = isset($errors[$key]); ?>

    <?php 


 ?>
    <?php if ((string) $f['ftype'] === 'heading'): ?>
      <h2 class="ob-heading"><?= e((string) $f['label']) ?></h2>
      <?php if (trim((string) $f['help']) !== ''): ?>
        <p class="muted small"><?= e((string) $f['help']) ?></p>
      <?php endif; ?>

    <?php elseif ((string) $f['ftype'] === 'message'): ?>
      <div class="ob-message">
        <p class="prewrap m-0"><?= e((string) $f['label']) ?></p>
        <?php if (trim((string) $f['help']) !== ''): ?>
          <p class="muted small mt-6 m-0"><?= e((string) $f['help']) ?></p>
        <?php endif; ?>
      </div>

    <?php elseif ((string) $f['ftype'] === 'properties'): ?>
      <?php 



 ?>
      <fieldset class="prop-set">
        <legend><?= e((string) $f['label']) ?></legend>
        <?php if ((string) $f['help'] !== ''): ?>
          <p class="muted small mb-12"><?= e((string) $f['help']) ?></p>
        <?php endif; ?>
        <?php if ($res['client_id'] === null): ?>
          <p class="note note-warn small">We could not match this form to your
          record, so please email these to us instead — we would rather not
          file them under the wrong account.</p>
        <?php else: ?>
          <div data-prop-list>
            <div class="prop-row" data-prop-row>
              <div class="form-row">
                <div class="field">
                  <label class="lbl">What is it</label>
                  <input type="text" name="p_name[]" class="f-full" maxlength="160"
                         placeholder="Facebook page, main website…">
                </div>
                <div class="field">
                  <label class="lbl">Link</label>
                  <?php 
 ?>
                  <input type="text" inputmode="url" autocapitalize="none" spellcheck="false"
                         name="p_link[]" class="f-full" placeholder="facebook.com/yourpage">
                </div>
              </div>
              <div class="form-row">
                <div class="field">
                  <label class="lbl">Username</label>
                  <input type="text" name="p_user[]" class="f-full" maxlength="190" autocomplete="off">
                </div>
                <div class="field">
                  <label class="lbl">Password</label>
                  <input type="password" name="p_pass[]" class="f-full" autocomplete="new-password">
                </div>
              </div>
            </div>
          </div>
          <button type="button" class="btn btn-quiet btn-sm" data-prop-add>+ Add another property</button>
          <p class="hint">Stored encrypted. Only we can read them, and every
          time one is looked at it is written to our own log.</p>
        <?php endif; ?>
      </fieldset>

    <?php else: ?>
      <div class="field">
        <label class="lbl" for="f_<?= e($key) ?>">
          <?= e((string) $f['label']) ?>
          <?php if ((int) $f['required'] === 1): ?><span class="muted">(needed)</span><?php endif; ?>
        </label>
        <?php if ((string) $f['ftype'] === 'textarea'): ?>
          <textarea id="f_<?= e($key) ?>" name="f_<?= e($key) ?>" rows="4"
                    class="f-full"><?= e($val) ?></textarea>
        <?php elseif ((string) $f['ftype'] === 'choice'): ?>
          <select id="f_<?= e($key) ?>" name="f_<?= e($key) ?>" class="f-full">
            <option value="">— pick one —</option>
            <?php foreach (preg_split('/\r?\n/', (string) ($f['choices'] ?? '')) ?: [] as $c):
                $c = trim($c); if ($c === '') continue; ?>
              <option value="<?= e($c) ?>" <?= $val === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <?php 






 ?>
          <input type="<?= e(match ((string) $f['ftype']) {
                    'email' => 'email', 'phone' => 'tel', default => 'text' }) ?>"
                 <?= (string) $f['ftype'] === 'url' ? 'inputmode="url" autocapitalize="none" spellcheck="false"' : '' ?>
                 id="f_<?= e($key) ?>" name="f_<?= e($key) ?>" class="f-full"
                 value="<?= e($val) ?>">
        <?php endif; ?>
        <?php if ((string) $f['help'] !== ''): ?>
          <div class="hint"><?= e((string) $f['help']) ?></div>
        <?php endif; ?>
        <?php if ($bad): ?><span class="field-err"><?= e($errors[$key]) ?></span><?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>

  <?php if ($asksSig): ?>
    <?php 



 ?>
    <div class="field ob-sign">
      <label class="lbl" for="signed_name">Confirm your full name <span class="muted">(as you sign it)</span></label>
      <input type="text" id="signed_name" name="signed_name" class="f-full" maxlength="120" autocomplete="name"
             value="<?= e((string) ($_POST['signed_name'] ?? '')) ?>">
      <label class="lbl mt-8">Your signature</label>
      <div class="sig-wrap">
        <canvas id="sig" aria-label="Sign here with your mouse or finger"></canvas>
        <button type="button" class="sig-clear" id="sig-clear">Clear</button>
      </div>
      <input type="hidden" id="sig-data" name="signature" value="<?= e(preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', (string) ($_POST['signature'] ?? '')) ? (string) $_POST['signature'] : '') ?>">
      <div class="hint">Type your name to confirm who is signing, then sign in the box with your mouse or finger. Both are required.</div>
      <?php if (isset($errors['_signature'])): ?><span class="field-err"><?= e($errors['_signature']) ?></span><?php endif; ?>
    </div>
    <script defer src="<?= e(cm_url('js/signature-pad.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>
  <?php endif; ?>

  <button class="btn" type="submit">Send it over</button>
</form>
<?php endif; ?>

<?php cm_public_foot(); ?>
