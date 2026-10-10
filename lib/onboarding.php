<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0
































declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }

require_once CM_LIB . '/format.php';





require_once CM_LIB . '/linkexpiry.php';
require_once CM_LIB . '/migrate.php';


function cm_onboard_types(): array
{
    return [
        'text'       => 'Short answer',
        'textarea'   => 'Long answer',
        'url'        => 'Web address',
        'email'      => 'Email address',
        'phone'      => 'Phone number',
        'choice'     => 'Pick one',
        'properties' => 'Properties they want managed (repeatable: name, link, username, password)',













        'heading'    => 'A section heading (asks nothing)',
        'message'    => 'A message for them to read (asks nothing)',
    ];
}


function cm_onboard_asks(string $ftype): bool
{
    return $ftype !== 'message' && $ftype !== 'heading';
}


function cm_onboard_question_types(): array
{
    return array_filter(cm_onboard_types(),
        static fn(string $k): bool => cm_onboard_asks($k), ARRAY_FILTER_USE_KEY);
}








function cm_onboard_defaults(): array
{
    return [
        ['business_name', 'Your business name', 'text', '', 1],
        ['website', 'Your website', 'url', 'Leave it empty if you do not have one yet.', 0],
        ['keywords', 'Keywords you want to be found for', 'textarea',
         'One per line, or separated by commas. A rough list is fine — we will refine it.', 0],
        ['targeting', 'Who are you targeting?', 'textarea',
         'The customers you actually want. "Homeowners in the eastern suburbs" beats "everyone".', 0],
        ['avoid', 'Anything we should avoid?', 'textarea',
         'Competitors not to mention, topics to stay off, words you hate. This one saves the most trouble.', 0],
        ['properties', 'Properties you would like us to manage', 'properties',
         'Your website, Facebook page, Instagram — anything we will be posting to. '
         . 'Add as many as you need. Change the passwords afterwards if you would rather; '
         . 'we only need them to get set up.', 0],
    ];
}

function cm_onboard_seed(): void
{
    $have = (int) cm_val('SELECT COUNT(*) FROM onboarding_field WHERE template_id IS NULL');
    if ($have > 0) return;
    $sort = 10;
    foreach (cm_onboard_defaults() as [$key, $label, $type, $help, $req]) {
        cm_insert('INSERT INTO onboarding_field (template_id, fkey, label, ftype, help, required, sort,
                                                 created_at, updated_at)
                   VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)',
                  [$key, $label, $type, $help, $req, $sort, cm_now(), cm_now()]);
        $sort += 10;
    }
}


function cm_onboard_generic_fields(): array
{



    return cm_onboard_generic_fields_by_form();
}


function cm_onboard_service_fields(int $templateId): array
{
    return cm_all('SELECT * FROM onboarding_field WHERE template_id = ? ORDER BY sort, id',
                  [$templateId]);
}








function cm_onboard_form(?int $templateId): array
{
    $out = [];
    foreach (cm_onboard_generic_fields() as $f) $out[(string) $f['fkey']] = $f;
    if ($templateId !== null && $templateId > 0) {
        foreach (cm_onboard_service_fields($templateId) as $f) $out[(string) $f['fkey']] = $f;
    }
    return array_values($out);
}








function cm_onboard_fields_for_response(array $res): array
{
    $formId = isset($res['form_id']) && $res['form_id'] !== null ? (int) $res['form_id'] : 0;
    if ($formId > 0) {
        $fields = cm_onboard_form_fields($formId);
        if ($fields) return $fields;



    }



    if ((int) ($res['is_message'] ?? 0) === 1) return [];
    return cm_onboard_form(isset($res['template_id']) && $res['template_id'] !== null ? (int) $res['template_id'] : null);
}






function cm_onboard_state(array $res): array
{
    if ((string) ($res['status'] ?? '') === 'complete') {
        return ['key' => 'answered', 'label' => 'answered', 'pill' => 'pill-approved'];
    }
    if (!empty($res['opened_at'])) {
        return ['key' => 'opened', 'label' => 'opened, not answered', 'pill' => 'pill-live'];
    }
    return ['key' => 'unopened', 'label' => 'not opened yet', 'pill' => ''];
}



function cm_onboard_opened_stamp(int $responseId): void
{





    if (!empty($_SESSION['account_id'])) return;
    try {
        cm_q('UPDATE onboarding_response SET opened_at = ? WHERE id = ? AND opened_at IS NULL',
             [cm_now(), $responseId]);
    } catch (Throwable $ex) {

    }
}

















function cm_onboard_links_blocked(): string
{
    $on = false;
    try {
        require_once CM_LIB . '/publicsite.php';
        $on = cm_public_enabled();
    } catch (Throwable $ex) {

    }
    if ($on) return '';
    return 'Question links need your client portal switched on — the page a link opens '
         . 'answers "not found" while the portal is off. Turn it on under Settings, '
         . 'Your client portal, then make the link.';
}















function cm_onboard_start(?int $clientId, ?int $bookingId, ?int $templateId, ?int $jobId = null,
                          ?int $formId = null, ?string $message = null,
                          ?string $heading = null, bool $isMessage = false,
                          ?int $expiryDays = CM_LINK_EXPIRY_DEFAULT_DAYS): string
{





    require_once CM_LIB . '/publicsite.php';

    $token = bin2hex(random_bytes(32));

















    $message = $message !== null && trim($message) !== '' ? mb_substr(trim($message), 0, 4000) : null;
    $heading = $heading !== null && trim($heading) !== '' ? mb_substr(trim($heading), 0, 190) : null;











    $cols = ['token_hash'  => cm_link_hash($token),
             'token_enc'   => cm_encrypt($token),
             'client_id'   => $clientId,
             'booking_id'  => $bookingId,
             'job_id'      => $jobId,
             'template_id' => $templateId,
             'created_at'  => cm_now()];

    if (cm_schema_has_column('onboarding_response', 'form_id'))    $cols['form_id']    = $formId;
    if (cm_schema_has_column('onboarding_response', 'message'))    $cols['message']    = $message;
    if (cm_schema_has_column('onboarding_response', 'heading'))    $cols['heading']    = $heading;
    if (cm_schema_has_column('onboarding_response', 'is_message')) $cols['is_message'] = $isMessage ? 1 : 0;


    if (cm_schema_has_column('onboarding_response', 'expires_at')) {
        $cols['expires_at'] = cm_link_expires_at($expiryDays);
    }

    $names = array_keys($cols);
    cm_insert('INSERT INTO onboarding_response (' . implode(', ', $names) . ')
               VALUES (' . implode(', ', array_fill(0, count($names), '?')) . ')',
              array_values($cols));

    return $token;
}











function cm_onboard_resolve(?string $token): ?array
{
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    require_once CM_LIB . '/publicsite.php';
    require_once CM_LIB . '/linkexpiry.php';

    $row = cm_row('SELECT * FROM onboarding_response WHERE token_hash = ?',
                  [cm_link_hash($token)]);
    if ($row === null) return null;

    if ($row['client_id'] !== null
        && (string) cm_val('SELECT status FROM client WHERE id = ?', [(int) $row['client_id']]) === 'inactive') {
        return null;
    }



    if (array_key_exists('expires_at', $row)
        && cm_link_expired($row['expires_at'] === null ? null : (string) $row['expires_at'])) {
        return null;
    }
    return $row;
}

function cm_onboard_url(string $token): string
{
    return cm_url('portal/start.php?k=' . $token);
}


function cm_onboard_answers(int $responseId): array
{
    $out = [];
    foreach (cm_all('SELECT fkey, value FROM onboarding_answer WHERE response_id = ?',
                    [$responseId]) as $r) {
        $out[(string) $r['fkey']] = (string) ($r['value'] ?? '');
    }
    return $out;
}


function cm_onboard_answer_set(int $responseId, string $fkey, string $label, string $value): void
{
    cm_q('INSERT INTO onboarding_answer (response_id, fkey, label_at_the_time, value, created_at)
          VALUES (?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE value = VALUES(value), label_at_the_time = VALUES(label_at_the_time)',




         [$responseId, $fkey, mb_substr($label, 0, 190), cm_text_fit($value), cm_now()]);
}













function cm_onboard_property_store(int $clientId, array $p, string $actor): int
{
    $name = trim((string) ($p['name'] ?? ''));
    if ($name === '') return 0;



    $url  = cm_web_address_tidy((string) ($p['link'] ?? ''));
    $user = trim((string) ($p['username'] ?? ''));
    $pass = (string) ($p['password'] ?? '');




    $count = (int) cm_val('SELECT COUNT(*) FROM client_asset WHERE client_id = ?', [$clientId]);
    if ($count >= 99) return 0;

    $assetId = cm_insert(
        'INSERT INTO client_asset (client_id, name, type, url, notes, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$clientId, mb_substr($name, 0, 160), 'other', mb_substr($url, 0, 255),
         'Added by the client when they filled in their onboarding form.',
         'active', cm_now(), cm_now()]);

    if ($user !== '' || $pass !== '') {




        cm_q('INSERT INTO client_credential (asset_id, login_url, username, password_enc,
                                             password_strength, notes_enc, recovery_enc,
                                             updated_at, updated_by)
              VALUES (?, ?, ?, ?, ?, NULL, NULL, ?, ?)',
             [$assetId, mb_substr($url, 0, 255), mb_substr($user, 0, 190),
              $pass !== '' ? cm_encrypt($pass) : null,
              $pass !== '' ? cm_onboard_strength($pass) : null,
              cm_now(), $actor]);
    }

    cm_audit('asset.created', 'client', $clientId,
             'property "' . $name . '" added from the client\'s own onboarding form'
             . ($pass !== '' ? ' (with a sign-in, sealed)' : ''));
    return $assetId;
}











function cm_onboard_strength(string $password): ?int
{
    return function_exists('cm_password_strength') ? cm_password_strength($password) : null;
}


function cm_onboard_complete(int $responseId): void
{
    cm_q("UPDATE onboarding_response SET status = 'complete', completed_at = ? WHERE id = ?",
         [cm_now(), $responseId]);
}






function cm_onboard_asks_signature(): bool
{
    try { return (string) cm_setting('onboard.signature', '1') !== '0'; }
    catch (Throwable $ex) { return true; }
}









function cm_onboard_signature_problem(string $dataUri, string $name): string
{
    require_once CM_LIB . '/signature.php';
    return cm_signature_problem($dataUri, $name);
}

function cm_onboard_signature_store(int $responseId, string $dataUri, string $name): void
{
    cm_q('UPDATE onboarding_response SET signature = ?, signed_name = ?, signed_at = ? WHERE id = ?',
         [$dataUri, mb_substr(trim($name), 0, 120), cm_now(), $responseId]);
}


function cm_onboard_signature_html(int $responseId): string
{
    try {
        $r = cm_row('SELECT signature, signed_name, signed_at FROM onboarding_response WHERE id = ?', [$responseId]);
    } catch (Throwable $ex) { return ''; }
    if (!$r || trim((string) ($r['signature'] ?? '')) === '') return '';
    if (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', (string) $r['signature'])) return '';
    return '<div class="ob-signed">'
         . '<img class="ob-signature" src="' . e((string) $r['signature']) . '" alt="Signature of ' . e((string) $r['signed_name']) . '">'
         . '<p class="small m-0"><b>Signed</b> by ' . e((string) $r['signed_name'])
         . ($r['signed_at'] ? ' on ' . e(date('j M Y, g:ia', strtotime((string) $r['signed_at']))) : '') . '</p>'
         . '</div>';
}


function cm_onboard_pending(): array
{
    return cm_all(
        "SELECT r.*, c.first_name, c.last_name, c.company, c.email AS client_email,
                t.name AS service_name
           FROM onboarding_response r
      LEFT JOIN client c ON c.id = r.client_id
      LEFT JOIN job_template t ON t.id = r.template_id
          WHERE r.status = 'pending'
          ORDER BY r.created_at");
}


function cm_onboard_completed_for_client(int $clientId): array
{
    return cm_all(
        "SELECT r.*, t.name AS service_name FROM onboarding_response r
      LEFT JOIN job_template t ON t.id = r.template_id
          WHERE r.client_id = ? AND r.status = 'complete'
          ORDER BY r.completed_at DESC", [$clientId]);
}






function cm_onboard_field_form_html(int $templateId): string
{
    ob_start();
    ?>
  <form method="post" class="mt-10">
    <?= cm_csrf_field() ?>
    <input type="hidden" name="action" value="add_field">
    <input type="hidden" name="template_id" value="<?= (int) $templateId ?>">
    <div class="form-row">
      <div class="field flex-1">
        <label class="lbl">The question</label>
        <input type="text" name="label" class="f-full" maxlength="190"
               placeholder="<?= $templateId > 0
                 ? 'Which competitors should we look at?'
                 : 'What is your business name?' ?>">
      </div>
      <div class="field">
        <label class="lbl">Kind of answer</label>
        <select name="ftype" class="f-full">
          <?php foreach (cm_onboard_types() as $k => $lab): ?>
            <option value="<?= e($k) ?>"><?= e($lab) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="field">
      <label class="lbl">Help underneath it <span class="muted">(optional)</span></label>
      <input type="text" name="help" class="f-full" maxlength="400">
    </div>
    <div class="field">
      <label class="lbl">If it is "pick one", the choices — one per line</label>
      <textarea name="choices" rows="3" class="f-full"></textarea>
    </div>
    <label class="tick mb-10">
      <input type="checkbox" name="required" value="1">
      <span>They cannot send the form without it</span>
    </label>
    <button class="btn btn-sm" type="submit">Add the question</button>
  </form>
    <?php
    return (string) ob_get_clean();
}













function cm_onboard_handle_field_post(string $action, string $actor): bool
{







    require_once CM_LIB . '/view.php';
    if (!in_array($action, ['add_field', 'save_field', 'delete_field', 'move_field'], true)) {
        return false;
    }





    if ($action === 'move_field') {
        $fid = (int) ($_POST['field_id'] ?? 0);
        $dir = (string) ($_POST['dir'] ?? '');
        $f   = cm_row('SELECT * FROM onboarding_field WHERE id = ?', [$fid]);
        if ($f !== null && ($dir === 'up' || $dir === 'down')) {
            $moved = cm_onboard_field_move($fid, $dir);
            cm_flash($moved ? 'Moved ' . $dir . '.'
                            : 'It is already ' . ($dir === 'up' ? 'first' : 'last') . '.',
                     $moved ? 'ok' : 'warn');
        }
        cm_redirect(cm_onboard_field_return($f));
    }
    if ($action === 'add_field') {
            $label = trim((string) ($_POST['label'] ?? ''));
            $type  = isset(cm_onboard_types()[(string) ($_POST['ftype'] ?? '')])
                   ? (string) $_POST['ftype'] : 'text';
            $tplId = (int) ($_POST['template_id'] ?? 0);

            $formId = (int) ($_POST['form_id'] ?? 0);
            if ($label === '') {
                cm_flash(cm_onboard_asks($type) ? 'Write the question.' : 'Write the text.', 'error');
            } else {
                $newId = cm_onboard_field_add($tplId > 0 ? $tplId : null, $label, $type,
                                              (string) ($_POST['help'] ?? ''),
                                              (string) ($_POST['choices'] ?? ''),
                                              !empty($_POST['required']));



                if ($newId > 0 && $formId > 0) {
                    cm_q('UPDATE onboarding_field SET form_id = ? WHERE id = ?', [$formId, $newId]);
                }
                cm_flash(cm_onboard_asks($type) ? 'Question added.' : 'Added.');
            }
            cm_redirect($tplId > 0
                ? 'admin/services.php?open=svco-' . $tplId
                : 'admin/onboarding.php' . ($formId > 0 ? '?form=' . $formId : ''));
        }

        if ($action === 'save_field') {
            $fid = (int) ($_POST['field_id'] ?? 0);
            $f = cm_row('SELECT * FROM onboarding_field WHERE id = ?', [$fid]);
            if ($f) {
                $type = isset(cm_onboard_types()[(string) ($_POST['ftype'] ?? '')])
                      ? (string) $_POST['ftype'] : (string) $f['ftype'];
                cm_q('UPDATE onboarding_field SET label = ?, ftype = ?, help = ?, choices = ?,
                                                  required = ?, sort = ?, updated_at = ?
                       WHERE id = ?',
                     [mb_substr(trim((string) ($_POST['label'] ?? '')), 0, 190) ?: (string) $f['label'],
                      $type,
                      mb_substr(trim((string) ($_POST['help'] ?? '')), 0, 400),
                      trim((string) ($_POST['choices'] ?? '')) ?: null,
                      !empty($_POST['required']) ? 1 : 0,
                      ctype_digit((string) ($_POST['sort'] ?? '')) ? (int) $_POST['sort'] : (int) $f['sort'],
                      cm_now(), $fid]);
                cm_flash('Saved. Answers already given keep the wording they were asked under.');
            }
            cm_redirect(cm_onboard_field_return($f));
        }

        if ($action === 'delete_field') {
            $fid = (int) ($_POST['field_id'] ?? 0);
            $f = cm_row('SELECT * FROM onboarding_field WHERE id = ?', [$fid]);
            if ($f) {
                cm_q('DELETE FROM onboarding_field WHERE id = ?', [$fid]);
                cm_flash('Question removed. Answers already given are kept — they are a record of '
                       . 'what a client told you, and deleting the question does not unsay it.');
            }
            cm_redirect(cm_onboard_field_return($f));
        }
    return true;
}

















function cm_onboard_when_options(): array
{
    return [
        'accept' => ['When you accept the booking',
                     'They are asked once you have said yes. Fewer forms abandoned half way.'],
        'immediate' => ['As soon as they book',
                        'They are asked straight away, before you have looked at it.'],








        'paid' => ['Once they have paid',
                   'They are asked when the invoice for the work is settled in full.'],
        'never' => ['Not relevant — never ask',
                    'No onboarding form for this service, and none appears on the Onboarding screen.'],
    ];
}


















function cm_onboard_feature_ready(): bool
{
    return false;
}







function cm_onboard_when_effective(?int $templateId): string
{
    if (!cm_onboard_feature_ready()) return 'never';
    if ($templateId === null || $templateId < 1) return 'accept';
    $when = (string) (cm_val('SELECT onboard_when FROM job_template WHERE id = ?', [$templateId]) ?? 'accept');
    return isset(cm_onboard_when_options()[$when]) ? $when : 'accept';
}


function cm_onboard_skipped(?int $templateId): bool
{
    return cm_onboard_when_effective($templateId) === 'never';
}




















function cm_onboard_on_booking(int $bookingId): bool
{
    if (!cm_onboard_feature_ready()) return false;

    $b = cm_row('SELECT * FROM booking WHERE id = ?', [$bookingId]);
    if ($b === null || $b['client_id'] === null) return false;

    $tplId = $b['template_id'] !== null ? (int) $b['template_id'] : null;
    if (cm_onboard_when_effective($tplId) !== 'immediate') return false;

    if (cm_val('SELECT id FROM onboarding_response WHERE booking_id = ?', [$bookingId]) !== null) {
        return false;
    }










    $clientEmail = strtolower(trim((string) cm_val('SELECT email FROM client WHERE id = ?', [(int) $b['client_id']])));
    if ($clientEmail === '' || $clientEmail !== strtolower(trim((string) $b['email']))) {
        cm_audit('onboarding.held', 'client', (int) $b['client_id'],
                 'onboarding form NOT sent on booking ' . (string) $b['reference'] . ': the email typed on the '
               . 'booking is not the one on the client record — check the booking, then send it from the client page');
        return false;
    }

    $token = cm_onboard_start((int) $b['client_id'], $bookingId, $tplId);
    if ($token === '') return false;

    require_once CM_LIB . '/mail.php';
    $sent = cm_mail_onboarding_link(
        (string) $b['email'], (string) $b['name'], cm_onboard_url($token),
        (int) $b['client_id']);

    cm_audit('onboarding.started', 'client', (int) $b['client_id'],
             'onboarding form created and emailed on booking ' . (string) $b['reference']
           . ($sent ? '' : ' (the email did not send)'));
    return $sent;
}





















function cm_onboard_on_payment(int $invoiceId): bool
{
    if (!cm_onboard_feature_ready()) return false;

    $inv = cm_row('SELECT id, number, client_id, job_id, booking_id FROM invoice WHERE id = ?',
                  [$invoiceId]);
    if ($inv === null || $inv['client_id'] === null) return false;

    $jobId = $inv['job_id'] !== null ? (int) $inv['job_id'] : null;
    $tplId = null;
    if ($jobId !== null) {
        $t = cm_val('SELECT template_id FROM job WHERE id = ?', [$jobId]);
        $tplId = $t !== null ? (int) $t : null;
    } elseif ($inv['booking_id'] !== null) {
        $t = cm_val('SELECT template_id FROM booking WHERE id = ?', [(int) $inv['booking_id']]);
        $tplId = $t !== null ? (int) $t : null;
    }
    if ($tplId === null) return false;

    if (cm_onboard_when_effective($tplId) !== 'paid') return false;





    $already = $jobId !== null
        ? cm_val('SELECT id FROM onboarding_response WHERE job_id = ?', [$jobId])
        : cm_val('SELECT id FROM onboarding_response
                   WHERE client_id = ? AND template_id = ? AND job_id IS NULL',
                 [(int) $inv['client_id'], $tplId]);
    if ($already !== null) return false;

    require_once CM_LIB . '/clients.php';
    $client = cm_client((int) $inv['client_id']);
    if ($client === null) return false;

    $r = cm_onboard_send_to_client($client, $tplId, $jobId,
                                   'payment of ' . (string) ($inv['number'] ?? 'an invoice'));
    return $r['ok'] && $r['sent'];
}













function cm_onboard_for_client(int $clientId): array
{
    try {
        return cm_all(
            "SELECT r.*, t.name AS service_name, j.name AS job_name, f.name AS form_name
               FROM onboarding_response r
          LEFT JOIN job_template t ON t.id = r.template_id
          LEFT JOIN job j ON j.id = r.job_id
          LEFT JOIN onboarding_form f ON f.id = r.form_id
              WHERE r.client_id = ?
              ORDER BY r.created_at DESC", [$clientId]);
    } catch (Throwable $ex) {

        return cm_all(
            "SELECT r.*, t.name AS service_name, j.name AS job_name, NULL AS form_name
               FROM onboarding_response r
          LEFT JOIN job_template t ON t.id = r.template_id
          LEFT JOIN job j ON j.id = r.job_id
              WHERE r.client_id = ?
              ORDER BY r.created_at DESC", [$clientId]);
    }
}
















function cm_onboard_send_to_client(array $client, ?int $templateId, ?int $jobId, string $actor): array
{
    $token = cm_onboard_start((int) $client['id'], null, $templateId, $jobId);
    if ($token === '') return ['ok' => false, 'sent' => false, 'token' => ''];

    $to = trim((string) ($client['email'] ?? ''));
    $sent = false;
    if ($to !== '') {
        require_once CM_LIB . '/mail.php';
        require_once CM_LIB . '/clients.php';
        $sent = cm_mail_onboarding_link($to, cm_client_display_name($client),
                                        cm_onboard_url($token), (int) $client['id']);
    }

    cm_audit('onboarding.started', 'client', (int) $client['id'],
             'onboarding form created by ' . $actor . ($sent ? ' and emailed' : ' (not emailed)'));

    return ['ok' => true, 'sent' => $sent, 'token' => $token];
}
















function cm_onboard_field_add(?int $templateId, string $label, string $type,
                              string $help = '', string $choices = '',
                              bool $required = false): int
{
    $label = trim($label);
    if ($label === '') return 0;
    if (!isset(cm_onboard_types()[$type])) $type = 'text';



    if (!cm_onboard_asks($type)) $required = false;

    $base = preg_replace('/[^a-z0-9]+/', '_', strtolower($label)) ?: 'field';
    $key  = trim(mb_substr($base, 0, 50), '_');
    $n = 1;
    $scope = $templateId !== null && $templateId > 0 ? $templateId : null;
    while (cm_val('SELECT id FROM onboarding_field WHERE fkey = ? AND '
                . ($scope === null ? 'template_id IS NULL' : 'template_id = ?'),
                $scope === null ? [$key] : [$key, $scope]) !== null) {
        $key = mb_substr($base, 0, 46) . '_' . (++$n);
    }
    $sort = (int) cm_val('SELECT COALESCE(MAX(sort), 0) + 10 FROM onboarding_field WHERE '
                       . ($scope === null ? 'template_id IS NULL' : 'template_id = ?'),
                       $scope === null ? [] : [$scope]);

    return cm_insert('INSERT INTO onboarding_field (template_id, fkey, label, ftype, help, choices,
                                                    required, sort, created_at, updated_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                     [$scope, $key, mb_substr($label, 0, 190), $type,
                      mb_substr(trim($help), 0, 400),
                      trim($choices) !== '' ? trim($choices) : null,
                      $required ? 1 : 0, $sort, cm_now(), cm_now()]);
}











function cm_onboard_field_suggestions(): array
{



    return [
        'Which competitors should we look at?',
        'What does a good week look like to you?',
        'Who signs off before anything goes out?',
        'Any dates or seasons we should plan around?',
        'Anything else we should know?',
    ];
}










function cm_onboard_field_siblings(array $f): array
{
    $formId = $f['form_id'] !== null ? (int) $f['form_id'] : null;
    $tplId  = $f['template_id'] !== null ? (int) $f['template_id'] : null;
    $where  = ($formId === null ? 'form_id IS NULL' : 'form_id = ?')
            . ' AND ' . ($tplId === null ? 'template_id IS NULL' : 'template_id = ?');
    $args = [];
    if ($formId !== null) $args[] = $formId;
    if ($tplId !== null)  $args[] = $tplId;
    return cm_all("SELECT id, sort FROM onboarding_field WHERE {$where} ORDER BY sort, id", $args);
}

function cm_onboard_field_move(int $fieldId, string $dir): bool
{
    $f = cm_row('SELECT * FROM onboarding_field WHERE id = ?', [$fieldId]);
    if ($f === null) return false;




    $rows = cm_onboard_field_siblings($f);
    $n = 0;
    foreach ($rows as $i => $r) {
        $n += 10;
        if ((int) $r['sort'] !== $n) {
            cm_q('UPDATE onboarding_field SET sort = ? WHERE id = ?', [$n, (int) $r['id']]);
        }
        $rows[$i]['sort'] = $n;
    }

    $at = null;
    foreach ($rows as $i => $r) if ((int) $r['id'] === $fieldId) { $at = $i; break; }
    if ($at === null) return false;
    $to = $dir === 'up' ? $at - 1 : $at + 1;
    if ($to < 0 || $to >= count($rows)) return false;

    cm_q('UPDATE onboarding_field SET sort = ? WHERE id = ?', [(int) $rows[$to]['sort'], $fieldId]);
    cm_q('UPDATE onboarding_field SET sort = ? WHERE id = ?', [(int) $rows[$at]['sort'], (int) $rows[$to]['id']]);
    return true;
}












function cm_onboard_builder_html(array $fields, string $idPrefix, string $confirmWhat): string
{
    if (!$fields) return '';
    $last = count($fields) - 1;
    ob_start(); ?>
          <ul class="blocklist">
            <?php foreach ($fields as $i => $f): $fid = (int) $f['id']; ?>
              <?php $asks = cm_onboard_asks((string) $f['ftype']); ?>
              <li class="blockrow<?= $asks ? '' : ' is-prose' ?>">
                <span class="blockrow-move">
                  <?php 

 ?>
                  <form method="post" class="inline">
                    <?= cm_csrf_field() ?>
                    <input type="hidden" name="action" value="move_field">
                    <input type="hidden" name="field_id" value="<?= $fid ?>">
                    <button class="blockrow-arrow" type="submit" name="dir" value="up"
                            aria-label="Move up"<?= $i === 0 ? ' disabled' : '' ?>>&uarr;</button>
                  </form>
                  <form method="post" class="inline">
                    <?= cm_csrf_field() ?>
                    <input type="hidden" name="action" value="move_field">
                    <input type="hidden" name="field_id" value="<?= $fid ?>">
                    <button class="blockrow-arrow" type="submit" name="dir" value="down"
                            aria-label="Move down"<?= $i === $last ? ' disabled' : '' ?>>&darr;</button>
                  </form>
                </span>
                <span class="blockrow-body">
                  <b><?= e((string) $f['label']) ?></b>
                  <span class="muted small d-block"><?=
                    e(cm_onboard_types()[(string) $f['ftype']] ?? (string) $f['ftype']) ?><?=
                    $asks && (int) $f['required'] === 1 ? ' · needed' : '' ?><?=
                    trim((string) $f['help']) !== '' ? ' · has a note' : '' ?></span>
                </span>
                <button class="btn btn-sm btn-quiet" type="button"
                        data-modal-open="dlg-fld-<?= e($idPrefix) ?>-<?= $fid ?>">Edit</button>
                <form method="post" class="inline"
                      data-confirm="Remove &quot;<?= e((string) $f['label']) ?>&quot; from <?= e($confirmWhat) ?>? Answers already given are kept.">
                  <?= cm_csrf_field() ?>
                  <input type="hidden" name="action" value="delete_field">
                  <input type="hidden" name="field_id" value="<?= $fid ?>">
                  <button class="btn btn-sm btn-quiet" type="submit">Remove</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
<?php
    return (string) ob_get_clean();
}















function cm_onboard_field_edit_window_html(array $f, string $idPrefix): string
{
    $fid  = (int) $f['id'];
    $asks = cm_onboard_asks((string) $f['ftype']);
    ob_start(); ?>
  <dialog class="modal" id="dlg-fld-<?= e($idPrefix) ?>-<?= $fid ?>">
    <div class="modal-box">
      <div class="modal-head">
        <h2><?= $asks ? 'Edit the question' : 'Edit the text' ?></h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="save_field">
        <input type="hidden" name="field_id" value="<?= $fid ?>">
        <div class="row">
          <label class="lbl" for="fl<?= e($idPrefix) ?><?= $fid ?>"><?= $asks ? 'The question' : 'The text' ?></label>
          <?php if ($asks): ?>
            <input type="text" id="fl<?= e($idPrefix) ?><?= $fid ?>" name="label" class="f-full"
                   maxlength="190" value="<?= e((string) $f['label']) ?>">
          <?php else: ?>
            <textarea id="fl<?= e($idPrefix) ?><?= $fid ?>" name="label" rows="4"
                      class="f-full" maxlength="190"><?= e((string) $f['label']) ?></textarea>
          <?php endif; ?>
        </div>
        <?php if ($asks): ?>
          <div class="row">
            <label class="lbl" for="ft<?= e($idPrefix) ?><?= $fid ?>">Kind of answer</label>
            <select id="ft<?= e($idPrefix) ?><?= $fid ?>" name="ftype" class="f-full">
              <?php foreach (cm_onboard_question_types() as $k => $lab): ?>
                <option value="<?= e($k) ?>" <?= (string) $f['ftype'] === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <input type="hidden" name="ftype" value="<?= e((string) $f['ftype']) ?>">
        <?php endif; ?>
        <div class="row">
          <label class="lbl" for="fh<?= e($idPrefix) ?><?= $fid ?>">A note underneath <span class="muted">(optional)</span></label>
          <input type="text" id="fh<?= e($idPrefix) ?><?= $fid ?>" name="help" class="f-full"
                 maxlength="400" value="<?= e((string) $f['help']) ?>">
        </div>
        <?php if ($asks): ?>
          <div class="row">
            <label class="lbl" for="fc<?= e($idPrefix) ?><?= $fid ?>">If it is &ldquo;pick one&rdquo;, the choices &mdash; one per line</label>
            <textarea id="fc<?= e($idPrefix) ?><?= $fid ?>" name="choices" rows="3"
                      class="f-full"><?= e((string) ($f['choices'] ?? '')) ?></textarea>
          </div>
          <label class="tick mb-10">
            <input type="checkbox" name="required" value="1" <?= (int) $f['required'] === 1 ? 'checked' : '' ?>>
            <span>They cannot send the form without it</span>
          </label>
        <?php endif; ?>
        <p class="hint">Re-wording keeps every answer already given attached to
        this question &mdash; and those answers keep the wording they were
        asked under.</p>
        <div class="modal-foot">
          <button class="btn" type="submit">Save</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>
<?php
    return (string) ob_get_clean();
}


function cm_onboard_field_windows_html(array $fields, string $idPrefix): string
{
    $out = '';
    foreach ($fields as $f) $out .= cm_onboard_field_edit_window_html($f, $idPrefix);
    return $out;
}






function cm_onboard_add_block_window_html(string $dialogId, array $scope): string
{
    $isForm  = isset($scope['form']);
    $scopeId = (int) ($scope['form'] ?? $scope['template'] ?? 0);
    ob_start(); ?>
  <dialog class="modal" id="<?= e($dialogId) ?>">
    <div class="modal-box">
      <div class="modal-head">
        <h2>Add to the form</h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">A question they answer, or something for them to read.</p>
      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="add_field">
        <input type="hidden" name="<?= $isForm ? 'form_id' : 'template_id' ?>" value="<?= $scopeId ?>">
        <div class="row">
          <label class="lbl" for="ab<?= e($dialogId) ?>">Kind of block</label>
          <select id="ab<?= e($dialogId) ?>" name="ftype" class="f-full">
            <?php foreach (cm_onboard_types() as $k => $lab): ?>
              <option value="<?= e($k) ?>" <?= $k === 'textarea' ? 'selected' : '' ?>><?= e($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row">
          <label class="lbl" for="al<?= e($dialogId) ?>">The question, heading or message</label>
          <textarea id="al<?= e($dialogId) ?>" name="label" rows="3" class="f-full" maxlength="190"></textarea>
        </div>
        <div class="row">
          <label class="lbl" for="ah<?= e($dialogId) ?>">A note underneath <span class="muted">(optional)</span></label>
          <input type="text" id="ah<?= e($dialogId) ?>" name="help" class="f-full" maxlength="400">
        </div>
        <div class="row">
          <label class="lbl" for="ac<?= e($dialogId) ?>">If it is &ldquo;pick one&rdquo;, the choices &mdash; one per line</label>
          <textarea id="ac<?= e($dialogId) ?>" name="choices" rows="3" class="f-full"></textarea>
        </div>
        <label class="tick mb-10">
          <input type="checkbox" name="required" value="1">
          <span>They cannot send the form without it</span>
        </label>
        <div class="modal-foot">
          <button class="btn" type="submit">Add it</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>
<?php
    return (string) ob_get_clean();
}









function cm_onboard_field_return(?array $f): string
{
    if ($f === null) return 'admin/onboarding.php';
    if ($f['template_id'] !== null) {
        return 'admin/services.php?open=svco-' . (int) $f['template_id'];
    }
    return 'admin/onboarding.php' . ($f['form_id'] !== null ? '?form=' . (int) $f['form_id'] : '');
}










function cm_onboard_forms(): array
{
    try {



        return cm_all('SELECT f.*, c.name AS category_name,
                              (SELECT COUNT(*) FROM onboarding_field x WHERE x.form_id = f.id) AS questions
                         FROM onboarding_form f
                    LEFT JOIN onboarding_form_category c ON c.id = f.category_id
                        WHERE f.one_off = 0
                     ORDER BY f.sort, f.name');
    } catch (Throwable $ex) {
        return [];
    }
}

function cm_onboard_form_row(int $id): ?array
{
    try { return cm_row('SELECT * FROM onboarding_form WHERE id = ?', [$id]); }
    catch (Throwable $ex) { return null; }
}


function cm_onboard_general_form_id(): int
{
    static $id = null;
    if ($id !== null) return $id;
    try { $id = (int) (cm_val('SELECT id FROM onboarding_form WHERE is_general = 1 LIMIT 1') ?? 0); }
    catch (Throwable $ex) { $id = 0; }
    return $id;
}

function cm_onboard_form_categories(): array
{
    try { return cm_all('SELECT * FROM onboarding_form_category ORDER BY sort, name'); }
    catch (Throwable $ex) { return []; }
}


function cm_onboard_form_category_map(): array
{
    $out = [];
    foreach (cm_onboard_form_categories() as $c) $out[(int) $c['id']] = (string) $c['name'];
    return $out;
}


function cm_onboard_form_category_ensure(string $name): int
{
    $name = trim($name);
    if ($name === '') return 0;
    $found = cm_val('SELECT id FROM onboarding_form_category WHERE name = ?', [$name]);
    if ($found !== null) return (int) $found;
    return cm_insert('INSERT INTO onboarding_form_category (name, sort, created_at) VALUES (?, ?, ?)',
                     [mb_substr($name, 0, 80), 100, cm_now()]);
}


function cm_onboard_forms_grouped(array $forms): array
{
    $names  = cm_onboard_form_category_map();
    $groups = []; $loose = [];
    foreach ($forms as $f) {
        $cid = (int) ($f['category_id'] ?? 0);
        if ($cid > 0 && isset($names[$cid])) $groups[$cid][] = $f;
        else                                 $loose[] = $f;
    }
    $out = [];
    foreach ($names as $cid => $name) {
        if (!empty($groups[$cid])) $out[] = ['id' => $cid, 'name' => $name, 'forms' => $groups[$cid]];
    }
    if ($loose) $out[] = ['id' => 0, 'name' => $out ? 'Everything else' : '', 'forms' => $loose];
    return $out;
}






function cm_onboard_form_create(string $name, string $description, ?int $categoryId, string &$problem): int
{
    $name = trim($name);
    if ($name === '')            { $problem = 'Give the form a name.'; return 0; }
    if (mb_strlen($name) > 160)  { $problem = 'That name is too long (160 characters maximum).'; return 0; }

    $sort = (int) cm_val('SELECT COALESCE(MAX(sort), 0) + 10 FROM onboarding_form');
    $problem = '';
    return cm_insert('INSERT INTO onboarding_form (name, description, category_id, sort, is_general,
                                                   created_at, updated_at)
                      VALUES (?, ?, ?, ?, 0, ?, ?)',
                     [$name, trim($description), $categoryId > 0 ? $categoryId : null,
                      $sort, cm_now(), cm_now()]);
}


















function cm_onboard_one_off_form(string $clientName, array $post, ?int $copyFrom = null): int
{
    $problem = '';
    $name = mb_substr('Message — ' . $clientName . ' — ' . date('j M Y'), 0, 160);
    $fid = cm_onboard_form_create($name, 'Built in the Send-message window.', null, $problem);
    if ($fid <= 0) return 0;
    try {
        cm_q('UPDATE onboarding_form SET one_off = 1 WHERE id = ?', [$fid]);
    } catch (Throwable $ex) {



    }
    $copied = 0;
    if ($copyFrom !== null && $copyFrom > 0) {
        foreach (cm_onboard_form_fields($copyFrom) as $f) {
            $nid = cm_onboard_field_add(null, (string) $f['label'], (string) $f['ftype'],
                                        (string) ($f['help'] ?? ''), (string) ($f['choices'] ?? ''),
                                        (int) ($f['required'] ?? 0) === 1);
            if ($nid > 0) {
                cm_q('UPDATE onboarding_field SET form_id = ? WHERE id = ?', [$fid, $nid]);
                $copied++;
            }
        }
    }
    $made = cm_onboard_questions_save(['form' => $fid], $post);
    if ($copied + $made === 0) {


        cm_q('DELETE FROM onboarding_field WHERE form_id = ?', [$fid]);
        cm_q('DELETE FROM onboarding_form WHERE id = ?', [$fid]);
        return 0;
    }
    return $fid;
}



function cm_onboard_form_save(int $id, string $name, string $description, ?int $categoryId): string
{
    $f = cm_onboard_form_row($id);
    if ($f === null) return 'That form no longer exists.';
    $name = trim($name);
    if ($name === '')           return 'Give the form a name.';
    if (mb_strlen($name) > 160) return 'That name is too long (160 characters maximum).';

    cm_q('UPDATE onboarding_form SET name = ?, description = ?, category_id = ?, updated_at = ?
           WHERE id = ?',
         [$name, trim($description), $categoryId > 0 ? $categoryId : null, cm_now(), $id]);
    return '';
}








function cm_onboard_form_delete(int $id): string
{
    $f = cm_onboard_form_row($id);
    if ($f === null) return 'That form no longer exists.';
    if ((int) $f['is_general'] === 1) {
        return 'The general questions cannot be deleted — every client is asked them. '
             . 'Remove the questions inside it instead.';
    }
    cm_q('DELETE FROM onboarding_form WHERE id = ?', [$id]);
    return '';
}




function cm_onboard_form_renumber(?int $categoryId): array
{
    $rows = cm_all('SELECT id, sort, name FROM onboarding_form WHERE '
                 . ($categoryId === null ? 'category_id IS NULL' : 'category_id = ?')
                 . ' ORDER BY sort, name, id', $categoryId === null ? [] : [$categoryId]);
    $n = 0;
    foreach ($rows as $i => $r) {
        $n += 10;
        if ((int) $r['sort'] !== $n) {
            cm_q('UPDATE onboarding_form SET sort = ? WHERE id = ?', [$n, (int) $r['id']]);
        }
        $rows[$i]['sort'] = $n;
    }
    return $rows;
}

function cm_onboard_form_move(int $id, string $dir): bool
{
    $f = cm_onboard_form_row($id);
    if ($f === null) return false;
    $cid  = $f['category_id'] === null ? null : (int) $f['category_id'];
    $rows = cm_onboard_form_renumber($cid);

    $at = null;
    foreach ($rows as $i => $r) if ((int) $r['id'] === $id) { $at = $i; break; }
    if ($at === null) return false;

    $to = $dir === 'up' ? $at - 1 : $at + 1;
    if ($to < 0 || $to >= count($rows)) return false;

    cm_q('UPDATE onboarding_form SET sort = ? WHERE id = ?', [(int) $rows[$to]['sort'], $id]);
    cm_q('UPDATE onboarding_form SET sort = ? WHERE id = ?', [(int) $rows[$at]['sort'], (int) $rows[$to]['id']]);
    return true;
}



function cm_onboard_form_set_category(int $id, ?int $categoryId): bool
{
    $f = cm_onboard_form_row($id);
    if ($f === null) return false;
    $was = $f['category_id'] === null ? null : (int) $f['category_id'];
    if ($was === $categoryId) return false;
    if ($categoryId !== null && !isset(cm_onboard_form_category_map()[$categoryId])) return false;

    $rows = cm_onboard_form_renumber($categoryId);
    $last = $rows ? (int) $rows[count($rows) - 1]['sort'] : 0;
    cm_q('UPDATE onboarding_form SET category_id = ?, sort = ?, updated_at = ? WHERE id = ?',
         [$categoryId, $last + 10, cm_now(), $id]);
    cm_onboard_form_renumber($was);
    return true;
}


function cm_onboard_form_fields(int $formId): array
{
    try {
        return cm_all('SELECT * FROM onboarding_field WHERE form_id = ? ORDER BY sort, id', [$formId]);
    } catch (Throwable $ex) {
        return [];
    }
}







function cm_onboard_questions_save(array $scope, array $post): int
{
    $labels = (array) ($post['q_label'] ?? []);
    $types  = (array) ($post['q_type'] ?? []);
    $reqs   = (array) ($post['q_required'] ?? []);

    $made = 0;
    foreach ($labels as $i => $raw) {
        if (trim((string) $raw) === '') continue;
        $id = cm_onboard_field_add(
            isset($scope['template']) ? (int) $scope['template'] : null,
            (string) $raw, (string) ($types[$i] ?? 'text'), '', '', !empty($reqs[$i]));
        if ($id > 0 && isset($scope['form'])) {
            cm_q('UPDATE onboarding_field SET form_id = ? WHERE id = ?', [(int) $scope['form'], $id]);
        }
        if ($id > 0) $made++;
    }
    return $made;
}






function cm_onboard_generic_fields_by_form(): array
{
    cm_onboard_seed();
    $fid = cm_onboard_general_form_id();
    if ($fid > 0) {
        return cm_all('SELECT * FROM onboarding_field WHERE form_id = ? ORDER BY sort, id', [$fid]);
    }
    return cm_all('SELECT * FROM onboarding_field WHERE template_id IS NULL ORDER BY sort, id');
}





function cm_onboard_question_grid_html(string $idPrefix, array $scope, int $rows = 10): string
{
    $sugg = cm_onboard_field_suggestions();
    $isForm = isset($scope['form']);
    $scopeId = (int) ($scope['form'] ?? $scope['template'] ?? 0);
    ob_start(); ?>
          <input type="hidden" name="<?= $isForm ? 'form_id' : 'template_id' ?>" value="<?= $scopeId ?>">
          <div class="qgrid" id="qgrid<?= e($idPrefix) ?>">
            <div class="qgrid-head"><span>The question</span><span>Kind of answer</span><span>Needed</span></div>
            <?php for ($r = 0; $r < $rows; $r++): ?>
              <div class="qgrid-row" data-task-row>
                <input type="text" name="q_label[<?= $r ?>]" id="ql<?= e($idPrefix) ?>-r<?= $r ?>"
                       class="f-full" maxlength="190" aria-label="Question <?= $r + 1 ?>"
                       placeholder="<?= e($sugg[$r] ?? '') ?>">
                <select name="q_type[<?= $r ?>]" id="qt<?= e($idPrefix) ?>-r<?= $r ?>"
                        aria-label="Kind of answer, question <?= $r + 1 ?>">
                  <?php foreach (cm_onboard_question_types() as $k => $lab): ?>
                    <option value="<?= e($k) ?>" <?= $k === 'textarea' ? 'selected' : '' ?>><?= e($lab) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php 


 ?>
                <input type="checkbox" name="q_required[<?= $r ?>]" value="1"
                       aria-label="Needed, question <?= $r + 1 ?>">
              </div>
            <?php endfor; ?>
          </div>
          <div class="actions mt-8">
            <button class="btn btn-quiet btn-sm" type="button"
                    data-task-add="qgrid<?= e($idPrefix) ?>">+ Add another row</button>
            <span class="muted small ml-8">Empty lines are ignored.</span>
          </div>
<?php
    return (string) ob_get_clean();
}




















function cm_web_address_tidy(string $raw): string
{
    $v = trim($raw);
    if ($v === '') return '';


    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $v)) return $v;

    if (preg_match('#^(mailto|tel|sms):#i', $v)) return $v;




    if (!preg_match('#^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}(?![a-z])#i', $v)) return $v;

    return 'https://' . ltrim($v, '/');
}












function cm_onboard_answers_html(int $responseId): string
{
    $rows = cm_all('SELECT label_at_the_time, value FROM onboarding_answer
                     WHERE response_id = ? ORDER BY id', [$responseId]);
    $signed = cm_onboard_signature_html($responseId);
    if (!$rows && $signed === '') return '<p class="muted small">Nothing was filled in.</p>';
    ob_start(); ?>
    <?php if ($rows): ?>
    <div class="table-wrap">
      <table>
        <tbody>
        <?php foreach ($rows as $a): ?>
          <tr>
            <th class="f-lg"><?= e((string) $a['label_at_the_time']) ?></th>
            <td class="prewrap"><?= trim((string) ($a['value'] ?? '')) !== ''
                  ? e((string) $a['value']) : '<span class="muted">&mdash; left empty &mdash;</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <?= $signed ?>
<?php
    return (string) ob_get_clean();
}















function cm_onboard_preview_html(int $formId): string
{
    $fields = cm_onboard_form_fields($formId);
    ob_start(); ?>
      <div class="ob-preview">
        <?php if (!$fields): ?>
          <p class="muted small m-0">This form has no questions yet.</p>
        <?php endif; ?>
        <?php foreach ($fields as $f): $ty = (string) $f['ftype']; ?>
          <?php if ($ty === 'heading'): ?>
            <h2 class="ob-heading"><?= e((string) $f['label']) ?></h2>
            <?php if (trim((string) $f['help']) !== ''): ?>
              <p class="muted small"><?= e((string) $f['help']) ?></p>
            <?php endif; ?>
          <?php elseif ($ty === 'message'): ?>
            <div class="ob-message">
              <p class="prewrap m-0"><?= e((string) $f['label']) ?></p>
              <?php if (trim((string) $f['help']) !== ''): ?>
                <p class="muted small mt-6 m-0"><?= e((string) $f['help']) ?></p>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="field">
              <label class="lbl"><?= e((string) $f['label']) ?>
                <?php if ((int) $f['required'] === 1): ?><span class="muted">(needed)</span><?php endif; ?>
              </label>
              <?php if ($ty === 'textarea'): ?>
                <textarea rows="3" class="f-full" disabled></textarea>
              <?php elseif ($ty === 'choice'): ?>
                <?php foreach (array_filter(array_map('trim', explode("\n", (string) ($f['choices'] ?? '')))) as $ch): ?>
                  <label class="tick"><input type="radio" disabled> <span><?= e($ch) ?></span></label>
                <?php endforeach; ?>
              <?php elseif ($ty === 'properties'): ?>
                <p class="muted small m-0">A repeatable block: name, link, username and
                password, as many as they have. Any password given is sealed into the vault.</p>
              <?php else: ?>
                <input type="text" class="f-full" disabled>
              <?php endif; ?>
              <?php if (trim((string) $f['help']) !== ''): ?>
                <div class="hint"><?= e((string) $f['help']) ?></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
<?php
    return (string) ob_get_clean();
}
