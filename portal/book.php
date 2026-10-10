<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0
















declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/publicview.php';
require_once CM_LIB . '/emails.php';


require_once CM_LIB . '/captcha.php';

cm_public_require_enabled();










if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    cm_redirect('portal/');
}

if (cm_post_too_large()) cm_require_csrf();

$kind = (string) ($_POST['kind'] ?? 'enquiry') === 'booking' ? 'booking' : 'enquiry';

$name    = trim((string) ($_POST['name'] ?? ''));
$email   = strtolower(trim((string) ($_POST['email'] ?? '')));
$phone   = trim((string) ($_POST['phone'] ?? ''));
$company = trim((string) ($_POST['company'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));











$back = static function (string $why) use ($kind, $name, $email, $phone, $company, $message): never {
    cm_public_draft_keep([
        'name' => $name, 'email' => $email, 'phone' => $phone,
        'company' => $company, 'message' => $message,
    ], $why);
    cm_redirect($kind === 'booking'
        ? 'portal/service.php?id=' . (int) ($_POST['template_id'] ?? 0) . '&bad=1#book'
        : 'portal/?bad=1#contact');
};










if (!cm_csrf_valid(is_string($_POST['cm_csrf'] ?? null) ? $_POST['cm_csrf'] : null)) {
    $back('This page had been open a while, so for your safety it was not sent. '
        . 'Everything you typed is still here — check the question below and press Send again.');
}
cm_require_csrf();



$spam = cm_public_spam_check($kind);
if ($spam === 'refused') {
    cm_redirect('portal/?sent=1');
}
if ($spam !== '') {



    $back($spam);
}



$human = cm_captcha_check();
if ($human !== '') {
    $back($human);
}

if ($name === '' || !cm_email_valid($email)) {
    $back($name === ''
        ? 'Please give us a name to put to the message — nothing else was lost.'
        : 'That email address does not look right, and it is the only way we have of answering you. '
        . 'Check it and send again.');
}

$link = cm_link_resolve((string) ($_POST['k'] ?? ''));
$who  = cm_booking_identify($link, $email);

$service = null;
$quote = null;
if ($kind === 'booking') {
    $service = cm_public_service((int) ($_POST['template_id'] ?? 0));
    if ($service === null) cm_public_404('That service is not available.');

    $from = trim((string) ($_POST['requested_from'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || $from < date('Y-m-d')) $from = null;
    $quote = cm_public_quote($service, $from);
}

$ref = cm_booking_create([
    'template_id'   => $service !== null ? (int) $service['id'] : null,
    'client_id'     => $who['client_id'],
    'link_id'       => $link !== null ? (int) $link['id'] : null,
    'name'          => $name,
    'email'         => $email,
    'phone'         => $phone,
    'company'       => $company,



    'message'       => $message
                     . ($who['note'] !== '' ? "\n\n[" . $who['confidence'] . '] ' . $who['note'] : ''),
    'quoted_cents'  => $quote !== null ? (int) $quote['cents'] : 0,
    'quoted_freq'   => $quote !== null ? (string) $quote['freq'] : 'oneoff',
    'requested_from' => $from ?? null,


    'planned_start' => $quote !== null && $quote['start'] !== '' ? $quote['start'] : null,
    'planned_end'   => $quote !== null && $quote['end'] !== '' ? $quote['end'] : null,
    'plan_note'     => $quote !== null ? (string) $quote['note'] : '',
]);

if ($link !== null) {
    cm_link_touch($link);
}










require_once CM_LIB . '/onboarding.php';
$bookingId = (int) (cm_val('SELECT id FROM booking WHERE reference = ?', [$ref]) ?? 0);
if ($bookingId > 0) {
    try { cm_onboard_on_booking($bookingId); }
    catch (Throwable $ex) { error_log('onboarding on booking failed: ' . $ex->getMessage()); }
}


$to = cm_mail_reply_to() !== '' ? cm_mail_reply_to() : cm_mail_from();
if ($to !== '') {
    $what = $service !== null ? cm_public_service_name($service) : 'General enquiry';
    $body = "A new " . $kind . " came in.\n\n"
          . "Reference: " . $ref . "\n"
          . "Service:   " . $what . "\n"
          . "Name:      " . $name . "\n"
          . "Email:     " . $email . "\n"
          . ($phone !== '' ? "Phone:     " . $phone . "\n" : '')
          . ($company !== '' ? "Business:  " . $company . "\n" : '')
          . "Match:     " . $who['confidence'] . " — " . $who['note'] . "\n"
          . ($quote !== null && $quote['start'] !== ''
              ? "Quoted:    " . $quote['start'] . " to " . $quote['end'] . "\n" : '')
          . ($message !== '' ? "\n" . $message . "\n" : '')
          . "\nOpen it: " . cm_url('admin/bookings.php');
    cm_mail($to, 'New ' . $kind . ' — ' . $what . ' (' . $ref . ')', $body,
            ['kind' => 'booking_alert']);
}

cm_redirect('portal/?sent=1&ref=' . rawurlencode($ref));
