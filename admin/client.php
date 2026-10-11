<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0











declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/assets.php';
require_once CM_LIB . '/reminders.php';
require_once CM_LIB . '/jobs.php';


require_once CM_LIB . '/unsubscribe.php';

$admin = cm_require_admin();

$client = cm_client((int) ($_GET['id'] ?? 0));
if (!$client) {
    cm_flash('There is no client with that number — it may be a stale link.', 'warn');
    cm_redirect('admin/clients.php');
}
$id = (int) $client['id'];














$tabs = [
    'overview' => 'Overview',
    'assets'   => 'Properties',
    'jobs'     => 'Jobs',
    'invoices' => 'Invoices',
    'comms'    => 'Communication',
];
$tab = (string) ($_GET['tab'] ?? 'overview');




$coreOpen = $tab === 'core';
if (!isset($tabs[$tab])) $tab = 'overview';

$self = static fn(string $t): string => cm_url('admin/client.php?id=' . $id . '&tab=' . $t);
$errors = [];


if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $action = (string) ($_POST['action'] ?? '');






    if ($action === 'set_vip') {
        $on = !empty($_POST['vip']) ? 1 : 0;
        cm_q('UPDATE client SET vip = ?, updated_at = ? WHERE id = ?', [$on, cm_now(), $id]);
        cm_audit($on ? 'client.vip_set' : 'client.vip_cleared', 'client', $id,
                 cm_client_display_name($client) . ($on ? ' marked VIP' : ' no longer VIP')
                 . ' by ' . (string) $admin['email']);
        cm_flash($on
            ? cm_client_display_name($client) . ' is a VIP — their work sorts ahead, where you have put VIP in the order.'
            : 'No longer a VIP.');
        cm_redirect('admin/client.php?id=' . $id);
    }











    if ($action === 'unsub_stop') {
        if (cm_unsub_apply($id, 'admin')) {
            cm_flash(cm_client_display_name($client)
                   . ' will not be emailed again — not summaries, not invoices, nothing. '
                   . 'They have to confirm by phone before you can turn it back on.', 'warn');
        } else {
            cm_flash('Their emails were already stopped.', 'warn');
        }
        cm_redirect('admin/client.php?id=' . $id);
    }

    if ($action === 'unsub_start') {
        if (cm_unsub_lift($id, (string) $admin['email'])) {
            cm_flash('Emails are on again for ' . cm_client_display_name($client)
                   . '. That they once unsubscribed stays on the record.');
        } else {
            cm_flash('They were not unsubscribed, so there was nothing to turn on.', 'warn');
        }
        cm_redirect('admin/client.php?id=' . $id);
    }





    if ($action === 'add_category') {
        $name = trim((string) ($_POST['category_new'] ?? ''));
        if ($name === '') {
            cm_flash('Give the subcategory a name.', 'error');
        } else {
            $existing = cm_asset_category_map();
            $cid = cm_asset_category_ensure($name);
            cm_flash($cid > 0 && isset($existing[$cid])
                ? '"' . $name . '" already exists — pick it on any property.'
                : '"' . $name . '" added. Pick it on any property from now on.');
        }
        cm_redirect('admin/client.php?id=' . $id . '&tab=assets');
    }


    if ($action === 'save_details') {












        $fields = [
            'first_name' => 80, 'last_name' => 80, 'company' => 160,
            'email' => 190, 'phone' => 60, 'tags' => 255,
            'address' => 255, 'suburb' => 120, 'state' => 60,
            'postcode' => 20, 'country' => 60,
        ];
        $vals = [];
        foreach ($fields as $f => $max) {
            $v = trim((string) ($_POST[$f] ?? ''));
            if (mb_strlen($v) > $max) $errors[$f] = 'Too long (' . $max . ' characters maximum).';
            $vals[$f] = $v;
        }
        if ($vals['email'] !== '' && !cm_email_valid($vals['email'])) {
            $errors['email'] = 'That does not look like an email address.';
        }
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if ($vals['first_name'] === '' && $vals['last_name'] === '' && $vals['company'] === ''
            && $vals['email'] === '') {
            $errors['first_name'] = 'Keep at least one of name, company or email — a client with none of them cannot be found again.';
        }

        $status = (string) ($_POST['status'] ?? $client['status']);
        if (!cm_client_status_valid($status)) $status = (string) $client['status'];

        if (!$errors) {
            cm_q('UPDATE client SET first_name=?, last_name=?, company=?, email=?, phone=?, tags=?,
                                    address=?, suburb=?, state=?, postcode=?, country=?,
                                    notes=?, status=?, updated_at=? WHERE id=?',
                 [$vals['first_name'], $vals['last_name'], $vals['company'], $vals['email'],
                  $vals['phone'], $vals['tags'], $vals['address'], $vals['suburb'],
                  $vals['state'], $vals['postcode'], $vals['country'],
                  $notes, $status, cm_now(), $id]);





            $cancelled = cm_client_status_cancel($id, (string) $client['status'], $status);

            $said = 'details saved';
            if ($status !== (string) $client['status']) {
                $from = cm_client_statuses()[(string) $client['status']]['label'] ?? $client['status'];
                $to   = cm_client_statuses()[$status]['label'];
                $said = 'status ' . $from . ' → ' . $to . ', details saved';
                if ($cancelled > 0) {
                    $said .= ', ' . $cancelled . ' open reminder' . ($cancelled === 1 ? '' : 's') . ' cancelled';
                }
            }
            cm_audit('client.updated', 'client', $id, $said . ' by ' . (string) $admin['email']);
            cm_flash($cancelled > 0
                ? 'Saved. ' . $cancelled . ' open reminder' . ($cancelled === 1 ? ' was' : 's were')
                  . ' cancelled — an inactive client gets none. They do not come back if the '
                  . 'client is made active again.'
                : 'Saved.', $cancelled > 0 ? 'warn' : 'ok');




            cm_redirect('admin/client.php?id=' . $id);
        }



        $tab = 'overview';
        $detailsRefused = true;
        $client = array_merge($client, $vals, ['notes' => $notes, 'status' => $status]);
    }









    if ($action === 'set_status') {
        $new = (string) ($_POST['status'] ?? '');
        $back = 'admin/client.php?id=' . $id
              . (isset($tabs[(string) ($_POST['return_tab'] ?? '')])
                 && (string) $_POST['return_tab'] !== 'overview'
                 ? '&tab=' . (string) $_POST['return_tab'] : '');
        if (!cm_client_status_valid($new)) {
            cm_flash('That is not a status this application knows.', 'error');
            cm_redirect($back);
        }
        $old = (string) $client['status'];
        if ($new === $old) {
            cm_flash('They are already ' . cm_client_statuses()[$new]['label'] . '.', 'warn');
            cm_redirect($back);
        }
        cm_q('UPDATE client SET status = ?, updated_at = ? WHERE id = ?', [$new, cm_now(), $id]);
        $cancelled = cm_client_status_cancel($id, $old, $new);
        $fromL = cm_client_statuses()[$old]['label'] ?? $old;
        $toL   = cm_client_statuses()[$new]['label'];
        cm_audit('client.updated', 'client', $id, 'status ' . $fromL . ' → ' . $toL
                 . ($cancelled > 0 ? ', ' . $cancelled . ' open reminder' . ($cancelled === 1 ? '' : 's') . ' cancelled' : '')
                 . ', from the tab row, by ' . (string) $admin['email']);
        cm_flash($cancelled > 0
            ? 'Now ' . $toL . '. ' . $cancelled . ' open reminder' . ($cancelled === 1 ? ' was' : 's were')
              . ' cancelled — an inactive client gets none. They do not come back if the client '
              . 'is made active again.'
            : 'Now ' . $toL . '.', $cancelled > 0 ? 'warn' : 'ok');
        cm_redirect($back);
    }





    if ($action === 'jobrem_save') {
        require_once CM_LIB . '/reminders.php';
        $remId  = (int) ($_POST['rem_id'] ?? 0);
        $jobId  = (int) ($_POST['rem_job'] ?? 0);
        $title  = trim((string) ($_POST['rem_title'] ?? ''));
        $due    = trim((string) ($_POST['rem_due'] ?? ''));
        $repeat = (string) ($_POST['rem_repeat'] ?? 'none');
        $job    = $jobId > 0 ? cm_row('SELECT id, name, client_id FROM job WHERE id = ?', [$jobId]) : null;
        if ($job === null || (int) $job['client_id'] !== $id) {
            cm_flash('That job is not on this client\'s record.', 'error');
            cm_redirect('admin/client.php?id=' . $id . '&tab=jobs');
        }
        if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) || strtotime($due) === false) {
            cm_flash('Give the reminder a title and a real date.', 'error');
            cm_redirect('admin/client.php?id=' . $id . '&tab=jobs');
        }
        if (!isset(cm_reminder_repeats()[$repeat])) $repeat = 'none';
        if ($remId > 0) {
            $rem = cm_reminder($remId);
            if ($rem === null || (int) ($rem['job_id'] ?? 0) !== $jobId
                || !in_array((string) $rem['status'], ['pending', 'snoozed'], true)) {
                cm_flash('That reminder is not open on this job any more — nothing was changed.', 'error');
                cm_redirect('admin/client.php?id=' . $id . '&tab=jobs');
            }




            $anchor = in_array($repeat, ['monthly', 'yearly', '2years', '3years'], true)
                ? (int) (new DateTimeImmutable($due))->format('j') : null;
            cm_q("UPDATE reminder SET title = ?, due_date = ?, repeat_every = ?, anchor_day = ?,
                         status = 'pending', snoozed_until = NULL, updated_at = ?
                   WHERE id = ?",
                 [mb_substr($title, 0, 190), $due, $repeat, $anchor, cm_now(), $remId]);
            cm_audit('reminder.updated', 'client', $id, 'reminder on "' . (string) $job['name']
                     . '" set to ' . $due . ' by ' . (string) $admin['email']);
            cm_flash('Reminder saved — ' . date('j M Y', (int) strtotime($due)) . '.');
        } else {
            cm_reminder_create($title, $due, $repeat, 'normal', $id, $jobId);
            cm_audit('reminder.created', 'client', $id, 'reminder set on completed job "'
                     . (string) $job['name'] . '" for ' . $due . ' by ' . (string) $admin['email']);
            cm_flash('Reminder set for ' . date('j M Y', (int) strtotime($due)) . '.');
        }
        cm_redirect('admin/client.php?id=' . $id . '&tab=jobs');
    }


    if ($action === 'save_core') {


        $changed = cm_core_save_from_post($id, (string) $admin['email'], true);
        cm_flash($changed > 0 ? 'Core Details saved.' : 'Nothing had changed.', $changed > 0 ? 'ok' : 'warn');
        cm_redirect('admin/client.php?id=' . $id);
    }

    if ($action === 'save_contact') {
        $cid = (int) ($_POST['contact_id'] ?? 0);
        $existing = $cid > 0 ? cm_contact($cid, $id) : null;
        if ($cid > 0 && !$existing) {
            cm_flash('That contact no longer exists.', 'warn');
            cm_redirect('admin/client.php?id=' . $id);
        }

        $first = trim((string) ($_POST['c_first'] ?? ''));
        $last  = trim((string) ($_POST['c_last'] ?? ''));
        $role  = trim((string) ($_POST['c_role'] ?? ''));
        $email = trim((string) ($_POST['c_email'] ?? ''));
        $phone = trim((string) ($_POST['c_phone'] ?? ''));
        $notes = trim((string) ($_POST['c_notes'] ?? ''));




        $primary  = isset($_POST['c_role_kind'])
                  ? ((string) $_POST['c_role_kind'] === 'primary' ? 1 : 0)
                  : (!empty($_POST['c_primary']) ? 1 : 0);
        $approval = !empty($_POST['c_approval']) ? 1 : 0;

        if ($first === '' && $last === '') {
            $errors['c_first'] = 'A contact needs at least a name.';
        }
        if ($email !== '' && !cm_email_valid($email)) {
            $errors['c_email'] = 'That does not look like an email address.';
        }
        if (mb_strlen($first) > 80 || mb_strlen($last) > 80 || mb_strlen($role) > 120
            || mb_strlen($email) > 190 || mb_strlen($phone) > 60) {
            $errors['c_first'] = 'One of those boxes is too long.';
        }

        if (!$errors) {


            if ($primary) {
                cm_q('UPDATE client_contact SET is_primary = 0 WHERE client_id = ?', [$id]);
            }
            if ($existing) {
                cm_q('UPDATE client_contact SET first_name=?, last_name=?, role=?, email=?, phone=?,
                                                notes=?, is_primary=?, has_approval=?, updated_at=?
                      WHERE id=? AND client_id=?',
                     [$first, $last, $role, $email, $phone, $notes, $primary, $approval, cm_now(), $cid, $id]);
                cm_audit('client.contact_updated', 'client', $id,
                         trim($first . ' ' . $last) . ' updated by ' . (string) $admin['email']);
                cm_flash('Contact saved.');
            } else {
                cm_insert('INSERT INTO client_contact (client_id, first_name, last_name, role, email,
                                                       phone, notes, is_primary, has_approval,
                                                       created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                          [$id, $first, $last, $role, $email, $phone, $notes, $primary, $approval,
                           cm_now(), cm_now()]);
                cm_audit('client.contact_added', 'client', $id,
                         trim($first . ' ' . $last) . ' added by ' . (string) $admin['email']);
                cm_flash('Contact added.');
            }
            cm_client_touch($id);
            cm_redirect('admin/client.php?id=' . $id);
        }
        $tab = 'overview';
    }







    if ($action === 'add_asset') {


        $r = cm_asset_add_from_post($id, 'a_', (string) $admin['email']);
        if ($r['ceiling']) {
            cm_flash('This client already has ' . CM_ASSETS_PER_CLIENT
                   . ' assets — the agreed ceiling. Archive one that is gone before adding more.', 'error');
            cm_redirect('admin/client.php?id=' . $id . '&tab=assets');
        }
        if ($r['errors']) {
            $errors += $r['errors'];
            $tab = 'assets';
        } else {
            cm_audit('client.asset_added', 'client', $id, $r['said'] . ' by ' . (string) $admin['email']);
            cm_flash('Property added' . ($r['pass'] ? ', sign-in stored encrypted.' : '.'));
            cm_redirect('admin/client.php?id=' . $id . '&tab=assets');
        }
    }









    if ($action === 'sendmsg_create') {
        require_once CM_LIB . '/onboarding.php';


        $blocked = cm_onboard_links_blocked();
        if ($blocked !== '') {
            cm_flash($blocked, 'error');
            cm_redirect('admin/client.php?id=' . $id . '&tab=comms');
        }










        $heading = trim((string) ($_POST['sm_heading'] ?? ''));
        $note    = trim((string) ($_POST['sm_message'] ?? ''));
        $useSet  = !empty($_POST['sm_use_set']);
        $typed   = false;
        foreach ((array) ($_POST['q_label'] ?? []) as $ql) {
            if (trim((string) $ql) !== '') { $typed = true; break; }
        }
        $setId = null;
        if ($useSet) {
            $setId = (int) ($_POST['sm_form'] ?? 0);
            if ($setId > 0 && cm_onboard_form_row($setId) === null) {
                cm_flash('That question set no longer exists — pick another.', 'error');
                cm_redirect('admin/client.php?id=' . $id . '&tab=comms');
            }
            if ($setId <= 0) $setId = cm_onboard_general_form_id();
            if ($setId <= 0) {
                cm_flash('There are no question sets yet — untick it and write the questions '
                       . 'here, or build sets on the Onboarding screen.', 'error');
                cm_redirect('admin/client.php?id=' . $id . '&tab=comms');
            }




            if (!cm_onboard_form_fields($setId)) {
                cm_flash('That set has no questions yet — add some on the Onboarding screen, '
                       . 'or write the questions here.', 'error');
                cm_redirect('admin/client.php?id=' . $id . '&tab=comms');
            }
        }
        if (!$useSet && !$typed) {

            cm_flash('Questions have to be asked — this link opens a page the client answers. '
                   . 'Tick the standard set, or write at least one question of your own. '
                   . '(A message with nothing to answer belongs in the email itself.)', 'error');
            cm_redirect('admin/client.php?id=' . $id . '&tab=comms');
        }
        if ($typed) {
            $formId = cm_onboard_one_off_form(cm_client_display_name($client), $_POST,
                                              $useSet ? $setId : null);
            if ($formId <= 0) {
                cm_flash('The questions could not be created — nothing was sent.', 'error');
                cm_redirect('admin/client.php?id=' . $id . '&tab=comms');
            }
        } else {
            $formId = $setId;
        }


        $smDays = cm_link_expiry_days($_POST['expiry_days'] ?? null);
        $token = cm_onboard_start($id, null, null, null, $formId,
                                  $note !== '' ? $note : null,
                                  $heading !== '' ? $heading : null, true, $smDays);
        if ($token === '') {
            cm_flash('The link could not be created.', 'error');
        } else {
            cm_audit('onboarding.started', 'client', $id,
                     'message link created ('
                     . ($typed && $useSet ? 'a set plus own questions'
                        : ($typed ? 'own questions' : 'a standard set'))
                     . ') from the Send-message window by ' . (string) $admin['email']);
            cm_flash('Link created — copy it from the record below and send it however you '
                   . 'like. It stops working after ' . $smDays . ' days. '
                   . 'Open it yourself first if you want a look: while you are signed '
                   . 'in, your visit is never counted as the client opening it.');
        }
        cm_redirect('admin/client.php?id=' . $id . '&tab=comms#record');
    }







    if ($action === 'reply_add') {
        require_once CM_LIB . '/maillog.php';
        $mailId  = (int) ($_POST['reply_mail'] ?? 0);
        $note    = trim((string) ($_POST['reply_note'] ?? ''));
        $mailRow = $mailId > 0 ? cm_row('SELECT id, client_id FROM mail_log WHERE id = ?', [$mailId]) : null;
        if ($mailRow === null || (int) ($mailRow['client_id'] ?? 0) !== $id) {
            cm_flash('That email is not on this client\'s record.', 'error');
            cm_redirect('admin/client.php?id=' . $id . '&tab=comms');
        }
        if ($note === '') {
            cm_flash('Write what they said first — the note is the record.', 'error');
            cm_redirect('admin/client.php?id=' . $id . '&tab=comms');
        }
        if (cm_reply_add($mailId, $note, (string) $admin['email']) > 0) {
            cm_audit('client.reply_noted', 'client', $id,
                     'reply noted on mail #' . $mailId . ' by ' . (string) $admin['email']);
            cm_flash('Reply noted on the record.');
        } else {
            cm_flash('The note could not be saved.', 'error');
        }
        cm_redirect('admin/client.php?id=' . $id . '&tab=comms#record');
    }


    if ($action === 'add_job_here') {
        $jname = trim((string) ($_POST['j_name'] ?? ''));



        $r = cm_job_create([
            'client_id'       => $id,
            'name'            => $jname,
            'description'     => (string) ($_POST['j_description'] ?? ''),
            'start'           => trim((string) ($_POST['j_start'] ?? '')),
            'template_id'     => (int) ($_POST['j_template'] ?? 0),
            'amount'          => (string) ($_POST['j_amount'] ?? ''),
            'freq'            => (string) ($_POST['j_freq'] ?? 'oneoff'),
            'duration_days'   => (string) ($_POST['j_days'] ?? ''),
            'per_day_minutes' => (string) ($_POST['j_perday'] ?? ''),



            'frequency'       => (string) ($_POST['j_frequency'] ?? ''),
            'hours'           => (string) ($_POST['j_hours'] ?? ''),







            'end'                => trim((string) ($_POST['j_end'] ?? '')),
            'priority'           => (string) ($_POST['j_priority'] ?? ''),
            'tags'               => (string) ($_POST['j_tags'] ?? ''),
            'notes'              => (string) ($_POST['j_notes'] ?? ''),
            'setup_minutes'      => (string) ($_POST['j_setup'] ?? ''),
            'completion_minutes' => (string) ($_POST['j_completion'] ?? ''),
            'completion_mode'    => (string) ($_POST['j_completion_mode'] ?? ''),
        'assets'          => (array) ($_POST['job_assets'] ?? []),
        ], (string) $admin['email']);

        if (!$r['ok']) {




            cm_flash($r['error'], 'error');
            cm_redirect('admin/client.php?id=' . $id . '&tab=' . $tab);
        }
        cm_flash($r['stamped'] > 0
            ? 'Job created and running, with ' . $r['stamped'] . ' task'
              . ($r['stamped'] === 1 ? '' : 's') . " from the service's schedule."
            : 'Job created. It has no tasks yet, so it is a draft until you add some.');
        cm_redirect('admin/job.php?id=' . $r['id'] . '&flow=new');
    }







    if ($action === 'send_email' && !cm_home()) {
        require_once CM_LIB . '/emails.php';
        require_once CM_LIB . '/unsubscribe.php';
        $to   = strtolower(trim((string) ($_POST['em_to'] ?? '')));
        $subj = trim((string) ($_POST['em_subject'] ?? ''));
        $body = (string) ($_POST['em_body'] ?? '');
        $tplKey = (string) ($_POST['em_template'] ?? '');
        if (!cm_email_valid($to)) {
            cm_flash('That does not look like an email address.', 'error');
        } elseif ($subj === '' || trim($body) === '') {
            cm_flash('The message needs a subject and a body.', 'error');
        } else {
            $ok = cm_mail($to, $subj, $body, ['kind' => cm_email_is_custom_key($tplKey) ? 'custom_message' : ($tplKey !== '' ? $tplKey : 'client_message'),
                                              'client_id' => $id]);
            if (!$ok && cm_client_is_unsubscribed($id)) {
                cm_flash('Not sent — this client has unsubscribed from all emails. They have to call and confirm before you can turn it back on.', 'error');
            } elseif ($ok) {
                cm_insert('INSERT INTO communication (client_id, contact, kind, subject, notes, happened_at, follow_up, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, NULL, ?)',
                          [$id, $to, 'email', mb_substr($subj, 0, 190), 'Sent from the Communication tab.', cm_now(), cm_now()]);
                cm_audit('client.emailed', 'client', $id, '"' . $subj . '" emailed to ' . $to . ' by ' . (string) $admin['email']
                         . (cm_mail_can_deliver() ? '' : ' (spooled — email is off)'));
                cm_flash(cm_mail_can_deliver()
                    ? 'Sent to ' . $to . '.'
                    : 'Saved on the server, not sent: email sending is off, so nothing has gone to the client. Turn it on under Settings, Email.');
            } else {
                cm_flash('The email could not be sent. Check Settings → Email.', 'error');
            }
        }
        cm_redirect('admin/client.php?id=' . $id . '&tab=comms');
    }

    if ($action === 'add_comm') {
        $kind    = isset(cm_communication_kinds()[(string) ($_POST['m_kind'] ?? '')]) ? (string) $_POST['m_kind'] : 'other';
        $subject = trim((string) ($_POST['m_subject'] ?? ''));
        $contact = trim((string) ($_POST['m_contact'] ?? ''));
        $notes   = trim((string) ($_POST['m_notes'] ?? ''));
        $when    = trim((string) ($_POST['m_when'] ?? ''));
        $follow  = trim((string) ($_POST['m_follow'] ?? ''));

        if ($subject === '' || mb_strlen($subject) > 190) {
            $errors['m_subject'] = 'Say what it was about, briefly.';
            $tab = 'comms';
        } else {
            $at = preg_match('/^\d{4}-\d{2}-\d{2}$/', $when)
                ? $when . ' ' . date('H:i:s') : cm_now();
            cm_insert('INSERT INTO communication (client_id, contact, kind, subject, notes,
                                                  happened_at, follow_up, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                      [$id, mb_substr($contact, 0, 160), $kind, $subject, $notes, $at,
                       preg_match('/^\d{4}-\d{2}-\d{2}$/', $follow) ? $follow : null, cm_now()]);
            cm_client_touch($id);
            cm_audit('client.contacted', 'client', $id,
                     cm_communication_kinds()[$kind] . ': ' . mb_substr($subject, 0, 80));

            $said = 'Logged.';
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $follow)) {


                cm_reminder_create('Follow up: ' . $subject, $follow, 'none', 'normal', $id);
                $said .= ' A follow-up reminder is set for ' . date('j M Y', strtotime($follow)) . '.';
            }
            cm_flash($said);
            cm_redirect('admin/client.php?id=' . $id . '&tab=comms');
        }
    }

    if ($action === 'delete_contact') {
        $cid = (int) ($_POST['contact_id'] ?? 0);
        $existing = cm_contact($cid, $id);
        if ($existing) {



            cm_q('DELETE FROM client_contact WHERE id = ? AND client_id = ?', [$cid, $id]);
            cm_client_touch($id);
            cm_audit('client.contact_removed', 'client', $id,
                     trim((string) $existing['first_name'] . ' ' . (string) $existing['last_name'])
                     . ' removed by ' . (string) $admin['email']);
            cm_flash('Contact removed.');
        }
        cm_redirect('admin/client.php?id=' . $id);
    }
}





















$detailsForm = static function (string $returnTab) use (&$client, &$errors, &$statuses, &$inactiveWarn, $id): void {



























    ?>
    <form method="post">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="save_details">
      <input type="hidden" name="return_tab" value="<?= e($returnTab) ?>">

      <div class="form-row">
        <div class="field">
          <label class="lbl" for="first_name">First name</label>
          <input type="text" id="first_name" name="first_name" class="f-full"
                 value="<?= e((string) $client['first_name']) ?>" maxlength="80" autocomplete="off">
          <?= cm_field_error($errors, 'first_name') ?>
        </div>
        <div class="field">
          <label class="lbl" for="last_name">Last name</label>
          <input type="text" id="last_name" name="last_name" class="f-full"
                 value="<?= e((string) $client['last_name']) ?>" maxlength="80" autocomplete="off">
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label class="lbl" for="company">Business <span class="muted">(if there is one)</span></label>
          <input type="text" id="company" name="company" class="f-full"
                 value="<?= e((string) $client['company']) ?>" maxlength="160" autocomplete="off">
        </div>
        <div class="field">
          <label class="lbl" for="status">Where they are up to</label>
          <select id="status" name="status" class="f-full">
            <?php foreach ($statuses as $key => $stMeta): ?>
              <option value="<?= e($key) ?>" <?= (string) $client['status'] === $key ? 'selected' : '' ?>
                      <?= $key === 'inactive' && $inactiveWarn !== '' ? 'data-confirm="' . e($inactiveWarn) . '"' : '' ?>>
                <?= e($stMeta['label']) ?> &mdash; <?= e($stMeta['blurb']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label class="lbl" for="email">Email</label>
          <input type="email" id="email" name="email" class="f-full"
                 value="<?= e((string) $client['email']) ?>" maxlength="190" autocomplete="off">
          <?= cm_field_error($errors, 'email') ?>
        </div>
        <div class="field">
          <label class="lbl" for="phone">Phone</label>
          <input type="text" id="phone" name="phone" class="f-full"
                 value="<?= e((string) $client['phone']) ?>" maxlength="60" autocomplete="off">
        </div>
      </div>

      <div class="field">
        <label class="lbl" for="address">Address</label>
        <input type="text" id="address" name="address" class="f-full"
               value="<?= e((string) ($client['address'] ?? '')) ?>" maxlength="255"
               autocomplete="off" placeholder="10 Longview Cl">
      </div>

      <div class="form-row">
        <div class="field">
          <label class="lbl" for="suburb">Suburb</label>
          <input type="text" id="suburb" name="suburb" class="f-full"
                 value="<?= e((string) ($client['suburb'] ?? '')) ?>" maxlength="120" autocomplete="off">
        </div>
        <div class="field field-tight">
          <label class="lbl" for="state">State</label>
          <input type="text" id="state" name="state" class="f-base"
                 value="<?= e((string) ($client['state'] ?? '')) ?>" maxlength="60" autocomplete="off">
        </div>
        <div class="field field-tight">
          <label class="lbl" for="postcode">Postcode</label>
          <input type="text" id="postcode" name="postcode" class="f-base"
                 value="<?= e((string) ($client['postcode'] ?? '')) ?>" maxlength="20" autocomplete="off">
        </div>
      </div>

      <div class="field">
        <label class="lbl" for="country">Country</label>
        <input type="text" id="country" name="country" class="f-full" maxlength="60"
               list="cm-countries" autocomplete="country-name"
               value="<?= e((string) ($client['country'] ?? '')) ?>"
               placeholder="Start typing &mdash; or leave it blank">
        <?= cm_country_datalist_html() ?>
      </div>

      <div class="field">
        <label class="lbl" for="tags">Tags</label>
        <input type="text" id="tags" name="tags" class="f-full"
               value="<?= e((string) $client['tags']) ?>" maxlength="255"
               placeholder="retail, social-only, monthly">
        <div class="hint">Free words, separated by commas. Search finds them.</div>
        <?php 



 ?>
        <?php $tagsInUse = cm_client_tags_all(); ?>
        <?php if ($tagsInUse): ?>
          <div class="snips mt-6" data-tag-target="tags">
            <span class="snips-lbl">Add tags</span>
            <?php foreach ($tagsInUse as $t): ?>
              <button class="snip" type="button" data-tag="<?= e($t) ?>"><?= e($t) ?></button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="field">
        <label class="lbl" for="notes">Internal notes</label>
        <textarea id="notes" name="notes" class="f-full" rows="3"><?= e((string) $client['notes']) ?></textarea>
        <div class="hint">Internal only &mdash; never shown to the client.</div>
      </div>

      <div class="actions">
        <button class="btn" type="submit">Save details</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
    <?php
};


$statuses = cm_client_statuses();
$st       = $statuses[(string) $client['status']] ?? $statuses['active'];



$inactiveWarn = cm_client_inactive_warning($id, (string) $client['status']);



$unsub    = cm_home() ? null : cm_unsub_state($id);
$core     = cm_core_details($id);
$contacts = cm_client_contacts($id);
$history  = cm_client_history($id, CM_HISTORY_FETCH);
$coreFilled = count($core);
$assets   = cm_client_assets($id);
$credLast = cm_client_credential_last_update($id);
$invoiceCount = 0;
try { $invoiceCount = (int) cm_val('SELECT COUNT(*) FROM invoice WHERE client_id = ?', [$id]); } catch (Throwable $ex) {}


$detailsRefused = $detailsRefused ?? false;
$editContact = null;
if (isset($_GET['edit'])) {
    $editContact = cm_contact((int) $_GET['edit'], $id);
}

cm_head(cm_client_display_name($client), '', true);
cm_render_flashes();
cm_admin_nav('clients');
?>
<?php 


 ?>
<script defer src="<?= e(cm_url('js/snippets.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>
<?php 

 ?>
<?= cm_credential_tools_script_once() ?>
<?php
?>
<?php $isVip = (int) ($client['vip'] ?? 0) === 1; ?>
<?php 













 ?>
<?php 



 ?>
<?= cm_crumbs_html([
      ['label' => 'Clients', 'href' => cm_url('admin/clients.php')],
      ['label' => cm_client_display_name($client)],
    ]) ?>
<div class="page-hero<?= $isVip ? ' is-vip' : '' ?>">
<div class="card-head align-baseline">
  <h1 class="mb-4"><?= e(cm_client_display_name($client)) ?></h1>
  <span class="pill <?= e($st['pill']) ?>"><?= e($st['label']) ?></span>
  <?php if ((int) ($client['vip'] ?? 0) === 1): ?>
    <span class="pill pill-vip">VIP</span>
  <?php endif; ?>
  <?php if ($unsub !== null && $unsub['unsubscribed']): ?>
    <span class="pill pill-spam">no emails</span>
  <?php endif; ?>
  <span class="spacer"></span>
  <form method="post" class="inline">
    <?= cm_csrf_field() ?>
    <input type="hidden" name="action" value="set_vip">
    <input type="hidden" name="vip" value="<?= (int) ($client['vip'] ?? 0) === 1 ? '0' : '1' ?>">
    <?php 




 ?>
    <button class="btn btn-sm <?= $isVip ? 'btn-quiet' : 'btn-vip' ?>" type="submit"><?=
      $isVip ? 'Remove VIP' : 'Make VIP' ?></button>
  </form>
  <?php 








 ?>
  <?php  ?>
</div>
<p class="sub">
  <?php if (trim((string) $client['company']) !== ''): ?><?= e((string) $client['company']) ?> · <?php endif; ?>
  <?php 



 ?>
  client since <?= e(date('j M Y', strtotime((string) $client['created_at']))) ?>
</p>
</div>

<?php 






 ?>
<?php if ($unsub !== null && $unsub['unsubscribed']): ?>
  <div class="note note-error">
    <b>Client has unsubscribed from all emails.</b>
    <?php if ($unsub['unsubscribed_at']): ?>
      They asked on <?= e(date('j M Y', strtotime((string) $unsub['unsubscribed_at']))) ?><?php
      ?><?= $unsub['how'] === 'admin' ? ', recorded by you' : ' using the link in an email' ?>.
    <?php endif; ?>
    Nothing is sent to them — work summaries and invoices included — and it
    stays that way until they call, confirm, and you turn it back on here.

    <form method="post" class="mt-8"
          data-confirm="Only do this if <?= e(cm_client_display_name($client)) ?> has asked you to. Turn their emails back on?">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="unsub_start">
      <button class="btn btn-sm" type="submit">They have confirmed — turn emails back on</button>
    </form>
  </div>
<?php endif; ?>

<nav class="subnav" aria-label="Client sections">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="subnav-item<?= $tab === $key ? ' is-on' : '' ?>" href="<?= e($self($key)) ?>"
       <?= $tab === $key ? 'aria-current="page"' : '' ?>>
      <?= e($label) ?><?php if ($key === 'assets' && $assets): ?> <span class="subnav-n"><?= count($assets) ?></span><?php elseif ($key === 'invoices' && $invoiceCount > 0): ?> <span class="subnav-n"><?= $invoiceCount ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
  <?php 





 ?>
  <form method="post" class="subnav-status">
    <?= cm_csrf_field() ?>
    <input type="hidden" name="action" value="set_status">
    <input type="hidden" name="return_tab" value="<?= e($tab) ?>">
    <select name="status" class="f-auto" aria-label="Client status">
      <?php foreach ($statuses as $key => $stMeta): ?>
        <option value="<?= e($key) ?>" <?= (string) $client['status'] === $key ? 'selected' : '' ?>
                <?= $key === 'inactive' && $inactiveWarn !== '' ? 'data-confirm="' . e($inactiveWarn) . '"' : '' ?>>
          <?= e($stMeta['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm" type="submit">Set</button>
  </form>
</nav>

<?php 

 ?>
<div class="day-grid">
<div>

<?php  ?>
  <?php 





















 ?>
  <?php




  $refused = ($errors && (string) ($_POST['action'] ?? '') === 'save_contact')
           ? (int) ($_POST['contact_id'] ?? 0) : null;
  $askedFor = $editContact ? (int) $editContact['id'] : null;
  $contactWindow = static function (?array $c) use ($errors, $refused, $askedFor): void {
      $cid  = $c ? (int) $c['id'] : 0;
      $mine = $refused !== null && $refused === $cid;



      $open = $mine || ($refused === null && $askedFor !== null && $askedFor === $cid);

      $val = static function (string $post, string $col) use ($c, $mine) {
          if ($mine) return (string) ($_POST[$post] ?? '');
          return (string) ($c[$col] ?? '');
      };
      $tick = static function (string $post, string $col) use ($c, $mine): bool {




          if ($mine && $post === 'c_primary') {
              return (string) ($_POST['c_role_kind'] ?? '') === 'primary';
          }
          if ($mine) return !empty($_POST[$post]);
          return !empty($c[$col] ?? 0);
      };
      $name = $c ? trim((string) $c['first_name'] . ' ' . (string) $c['last_name']) : '';
  ?>
  <dialog class="modal" id="dlg-contact-<?= $cid ?>"<?= $open ? ' data-modal-autoopen' : '' ?>>
    <div class="modal-box">
      <div class="modal-head">
        <h2><?= $c ? 'Edit ' . e($name !== '' ? $name : 'this contact') : 'Add a contact' ?></h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="save_contact">
        <input type="hidden" name="contact_id" value="<?= $cid ?>">
        <div class="row">
          <label class="lbl" for="c_first-<?= $cid ?>">First name</label>
          <input type="text" id="c_first-<?= $cid ?>" name="c_first" maxlength="80"
                 value="<?= e($val('c_first', 'first_name')) ?>">
          <?php if ($mine): ?><?= cm_field_error($errors, 'c_first') ?><?php endif; ?>
        </div>
        <div class="row">
          <label class="lbl" for="c_last-<?= $cid ?>">Last name</label>
          <input type="text" id="c_last-<?= $cid ?>" name="c_last" maxlength="80"
                 value="<?= e($val('c_last', 'last_name')) ?>">
        </div>
        <div class="row">
          <label class="lbl" for="c_role-<?= $cid ?>">Role</label>
          <input type="text" id="c_role-<?= $cid ?>" name="c_role" maxlength="120"
                 value="<?= e($val('c_role', 'role')) ?>"
                 placeholder="Owner, marketing manager, accounts&hellip;">
        </div>
        <div class="row">
          <label class="lbl" for="c_email-<?= $cid ?>">Email</label>
          <input type="email" id="c_email-<?= $cid ?>" name="c_email" maxlength="190"
                 value="<?= e($val('c_email', 'email')) ?>">
          <?php if ($mine): ?><?= cm_field_error($errors, 'c_email') ?><?php endif; ?>
        </div>
        <div class="row">
          <label class="lbl" for="c_phone-<?= $cid ?>">Phone</label>
          <input type="text" id="c_phone-<?= $cid ?>" name="c_phone" maxlength="60"
                 value="<?= e($val('c_phone', 'phone')) ?>">
        </div>
        <div class="row">
          <label class="lbl" for="c_notes-<?= $cid ?>">Notes</label>
          <input type="text" id="c_notes-<?= $cid ?>" name="c_notes"
                 value="<?= e($val('c_notes', 'notes')) ?>"
                 placeholder="Best before 3pm. Prefers a call to an email.">
        </div>
        <?php 

















 ?>
        <div class="row">
          <label class="lbl">This person is</label>
          <label class="tick">
            <input type="radio" name="c_role_kind" value="primary"
                   <?= $tick('c_primary', 'is_primary') ? 'checked' : '' ?>>
            <span><b>The primary contact</b> &mdash; the person to reach first.
            Choosing this moves it off whoever holds it now.</span>
          </label>
          <label class="tick">
            <input type="radio" name="c_role_kind" value="other"
                   <?= $tick('c_primary', 'is_primary') ? '' : 'checked' ?>>
            <span><b>Another contact</b> &mdash; on the file, reachable, not the
            first call. A client can have as many as it needs.</span>
          </label>
        </div>
        <label class="tick mb-14">
          <input type="checkbox" name="c_approval" value="1"
                 <?= $tick('c_approval', 'has_approval') ? 'checked' : '' ?>>
          <span><b>Approves work</b> &mdash; content and summaries wait on this person.</span>
        </label>
        <div class="actions">
          <button class="btn" type="submit"><?= $c ? 'Save contact' : 'Add contact' ?></button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>
  <?php }; ?>

<?php if ($tab === 'overview'): ?>

  <?php 

 ?>
  <dialog class="modal modal-widest" id="dlg-details"<?= $detailsRefused ? ' data-modal-autoopen' : '' ?>>
    <div class="modal-box">
      <div class="modal-head">
        <h2>Basic details</h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">Everything is optional.</p>
      <?php $detailsForm('overview'); ?>
    </div>
  </dialog>
  <?php foreach ($contacts as $c) $contactWindow($c); ?>
  <?php $contactWindow(null); ?>

  <?php 



 ?>
  <?php
  $ownName = trim(trim((string) $client['first_name']) . ' ' . trim((string) $client['last_name']));
  $primary = null;
  foreach ($contacts as $pc) {
      if ((int) $pc['is_primary'] === 1) { $primary = $pc; break; }
  }
  $primaryName = $primary
      ? trim((string) $primary['first_name'] . ' ' . (string) $primary['last_name']) : '';
  ?>


  <?php 










 ?>

  <div class="ov-grid">
  <?php 

















 ?>

    <section class="ov-tile">
      <div class="ov-head">
        <h2>Business details</h2>
        <span class="spacer"></span>
        <span class="pill <?= e($st['pill']) ?>"><?= e($st['label']) ?></span>
        <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-details">Edit</button>
      </div>

      <?php 





 ?>
      <p class="ov-biz"><?= trim((string) $client['company']) !== ''
            ? e((string) $client['company'])
            : '<span class="muted">No business name</span>' ?></p>
      <p class="ov-primary small">
        <?php if ($primaryName !== ''): ?>
          <span class="ov-sub-h">Primary contact</span>
          <?= e($primaryName) ?>
          <?php if (trim((string) ($primary['role'] ?? '')) !== ''): ?>
            <span class="muted"><?= e((string) $primary['role']) ?></span>
          <?php endif; ?>
        <?php elseif ($ownName !== ''): ?>
          <span class="ov-sub-h">Primary contact</span>
          <?= e($ownName) ?>
          <span class="muted">&mdash; the client's own name</span>
        <?php else: ?>
          <span class="muted">No primary contact yet.</span>
          <button class="btn-link" type="button" data-modal-open="dlg-contact-0">Add one</button>.
        <?php endif; ?>
      </p>

      <?php if (trim((string) ($client['notes'] ?? '')) !== ''): ?>
        <p class="small prewrap"><?= e(trim((string) $client['notes'])) ?></p>
      <?php else: ?>
        <p class="muted small">Nothing written about them yet.
          <button class="btn-link" type="button" data-modal-open="dlg-details">Add a description</button>.</p>
      <?php endif; ?>

      <div class="ov-sub">
        <span class="ov-sub-h">The brief</span>
        <?php if ($coreFilled > 0): ?>
          <p class="small m-0"><?= (int) $coreFilled ?> Core Detail field<?= $coreFilled === 1 ? '' : 's' ?> filled in —
            <button class="btn-link" type="button" data-modal-open="dlg-core">read or edit</button>.</p>
        <?php else: ?>
          <?php 

 ?>
          <p class="muted small m-0">Not written yet.
            <button class="btn-link" type="button" data-modal-open="dlg-core">Write the brief</button> —<br>
            (This displays beside every task while you work.)</p>
        <?php endif; ?>
      </div>
    </section>
    <?php  ?>
    <?php 














 ?>
    <section class="ov-tile">
      <div class="ov-head">
        <h2>About the business</h2>
        <span class="spacer"></span>
        <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-core">Edit</button>
      </div>
      <?php
      $aboutKey  = (string) array_key_first(cm_core_detail_spec());
      $aboutText = trim((string) ($core[$aboutKey] ?? ''));
      ?>
      <?php if ($aboutText !== ''): ?>
        <p class="small prewrap m-0"><?= e($aboutText) ?></p>
      <?php else: ?>
        <p class="muted small m-0">Nothing written yet.
          <button class="btn-link" type="button" data-modal-open="dlg-core">Write it</button> &mdash; it shows here and
          beside every task while you work.</p>
      <?php endif; ?>
    </section>

    <?php 




 ?>
    <dialog class="modal modal-wide" id="dlg-core"<?= $coreOpen ? ' data-modal-autoopen' : '' ?>>
      <div class="modal-box">
        <div class="modal-head">
          <h2>About the business</h2>
          <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
        </div>
        <p class="modal-sub">Optional, and read wherever the work is: the first
        box is the tile on this page, and both sit beside every task while you
        work on their jobs.</p>
        <form method="post">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="save_core">
          <?php foreach (cm_core_detail_spec() as $key => $meta): ?>
            <div class="row">
              <label class="lbl" for="cd_<?= e($key) ?>"><?= e($meta['label']) ?></label>
              <textarea id="cd_<?= e($key) ?>" name="cd_<?= e($key) ?>" class="f-full"
                        rows="<?= isset($core[$key]) && substr_count($core[$key], "\n") > 3 ? 8 : 5 ?>"><?= e($core[$key] ?? '') ?></textarea>
            </div>
          <?php endforeach; ?>
          <div class="modal-foot">
            <button class="btn" type="submit">Save</button>
            <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
          </div>
        </form>
      </div>
    </dialog>

    <section class="ov-tile">
      <div class="ov-head">
        <h2>Contact</h2>
        <span class="spacer"></span>
        <?php 


 ?>
        <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-details">Edit</button>
        <?php 


 ?>
        <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-contact-0">Add a contact</button>
      </div>

      <?php 













 ?>
      <p class="ov-biz">
        <?php if ($ownName !== ''): ?>
          <?= e($ownName) ?>
        <?php elseif ($primaryName !== ''): ?>
          <?= e($primaryName) ?> <span class="muted small">— primary contact</span>
        <?php else: ?>
          <span class="muted">No name yet</span>
        <?php endif; ?>
      </p>

      <?php
        $hasContact = trim((string) $client['email']) !== ''
                   || trim((string) $client['phone']) !== ''
                   || trim((string) ($client['address'] ?? '')) !== '';
        $place = trim(implode(' ', array_filter([
            trim((string) ($client['suburb'] ?? '')),
            trim((string) ($client['state'] ?? '')),
            trim((string) ($client['postcode'] ?? ''))])));
      ?>
      <?php if (!$hasContact): ?>
        <p class="muted small">No email, phone or address yet.
          <button class="btn-link" type="button" data-modal-open="dlg-details">Add them</button>.</p>
      <?php else: ?>
        <dl class="ov-dl">
          <?php if (trim((string) $client['email']) !== ''): ?>
            <dt>Email</dt>
            <dd><a href="mailto:<?= e((string) $client['email']) ?>"><?= e((string) $client['email']) ?></a></dd>
          <?php endif; ?>
          <?php if (trim((string) $client['phone']) !== ''): ?>
            <dt>Phone</dt>
            <dd><a href="tel:<?= e(preg_replace('/\s+/', '', (string) $client['phone'])) ?>"><?= e((string) $client['phone']) ?></a></dd>
          <?php endif; ?>
          <?php if (trim((string) ($client['address'] ?? '')) !== '' || $place !== ''): ?>
            <dt>Address</dt>
            <dd>
              <?php if (trim((string) ($client['address'] ?? '')) !== ''): ?>
                <?= e((string) $client['address']) ?><br>
              <?php endif; ?>
              <?= e($place) ?>
            </dd>
          <?php endif; ?>
        </dl>
      <?php endif; ?>

      <?php 










 ?>
      <?php if ($contacts): ?>
        <div class="ov-sub">
          <span class="ov-sub-h">Contacts</span>
          <?php foreach ($contacts as $c): $cn = trim((string) $c['first_name'] . ' ' . (string) $c['last_name']); ?>
            <div class="ov-contact small">
              <span><?= e($cn !== '' ? $cn : 'Unnamed') ?>
                <?php if ((int) $c['is_primary'] === 1): ?><span class="pill pill-approved">primary</span><?php endif; ?>
                <?php if ((int) ($c['is_billing'] ?? 0) === 1): ?><span class="pill pill-live">billing</span><?php endif; ?>
              </span>
              <span class="spacer"></span>
              <button class="btn btn-sm btn-quiet" type="button"
                      data-modal-open="dlg-contact-<?= (int) $c['id'] ?>">Edit</button>
              <form method="post" class="inline"
                    data-confirm="Remove <?= e($cn !== '' ? $cn : 'this contact') ?> from this client's contacts?">
                <?= cm_csrf_field() ?>
                <input type="hidden" name="action" value="delete_contact">
                <input type="hidden" name="contact_id" value="<?= (int) $c['id'] ?>">
                <button class="btn btn-sm btn-quiet" type="submit">Delete</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="ov-tile">
      <div class="ov-head">
        <h2>Properties and jobs</h2>
      </div>

      <?php $nAssets = count($assets); ?>
      <div class="ov-stat">
        <p class="ov-count">
          <?php if ($nAssets === 0): ?>
            <span class="muted">No properties yet</span>
          <?php else: ?>
            <a href="<?= e(cm_url('admin/client.php?id=' . $id . '&tab=assets')) ?>"><b><?= $nAssets ?></b>
              propert<?= $nAssets === 1 ? 'y' : 'ies' ?></a>
          <?php endif; ?>
        </p>
      </div>

      <?php
        $ovJobs = [];
        try { $ovJobs = cm_client_jobs($id); } catch (Throwable $ex) {}
        $nJobs = count($ovJobs);
        $nRunning = 0;
        foreach ($ovJobs as $ovJ) {
            if ((string) $ovJ['status'] === 'active') $nRunning++;
        }
      ?>
      <div class="ov-stat ov-stat-next">
        <p class="ov-count">
          <?php if ($nJobs === 0): ?>
            <span class="muted">No jobs yet</span>
          <?php else: ?>
            <a href="<?= e(cm_url('admin/client.php?id=' . $id . '&tab=jobs')) ?>"><b><?= $nJobs ?></b>
              job<?= $nJobs === 1 ? '' : 's' ?></a>
            <span class="muted small">— <?= (int) $nRunning ?> running</span>
          <?php endif; ?>
        </p>
      </div>
    </section>
    <?php  ?>

  </div>

  <?php
  $val = null;
  try { $val = cm_client_value($id); } catch (Throwable $ex) {}
  if ($val !== null && ($val['jobs_total'] > 0 || $val['minutes'] > 0)): ?>
    <div class="card">
      <h2>Value</h2>
      <div class="mini-grid">
        <div class="mini"><span class="n mono"><?= e(cm_money($val['active_annual'])) ?></span> active jobs, per year</div>
        <div class="mini"><span class="n mono"><?= e(cm_money((int) round($val['active_annual'] / 12))) ?></span> per month</div>
        <div class="mini"><span class="n mono"><?= e(cm_money($val['jobs_total'])) ?></span> all jobs on the books</div>
        <div class="mini"><span class="n mono"><?= e(cm_minutes_label($val['billable'])) ?></span> billable time</div>
        <div class="mini"><span class="n mono"><?= $val['rate'] !== null ? e(cm_money($val['rate'])) . '/hr' : '—' ?></span> effective rate</div>
      </div>
      <?php 



 ?>
      <?php if ($val['rate'] === null): ?>
        <p class="hint">The effective rate appears once billable time has been
        tracked on this client's jobs.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($coreFilled === 0): ?>
    <div class="note note-info">
    <?php 



 ?>
    Optional, and read wherever the work is: <b>About the business</b> is the
    tile on this client&rsquo;s home page, and both boxes sit beside every
    task while you work on their jobs.
  </div>
  <?php endif; ?>

  <?php if (trim((string) $client['notes']) !== ''): ?>
    <div class="card">
      <h2>Internal notes</h2>
      <p class="small prewrap"><?= e((string) $client['notes']) ?></p>
      <?php 


 ?>
      <p class="hint">Internal only &mdash; nothing on this tab is ever shown to the
      client. <button class="btn-link" type="button" data-modal-open="dlg-details">Edit them</button>.</p>
    </div>
  <?php endif; ?>

  <details class="card disclosure-card">
    <summary class="disclosure-summary">
      <span>
        <span class="t-h2">History</span>
        <span class="muted small">Recent changes to this client</span>
      </span>
      <span class="disclosure-action">Show</span>
    </summary>
    <div class="disclosure-body">
    <?php 


 ?>
    <?php if (!$history): ?>
      <p class="muted small">Nothing yet beyond creation.</p>
    <?php else: ?>
      <?php $hShown = array_slice($history, 0, CM_HISTORY_SHOWN);
            $hRest  = array_slice($history, CM_HISTORY_SHOWN); ?>
      <?php foreach ($hShown as $h): ?>
        <p class="hist-row"><span class="hist-when mono"><?= e(date('j M, H:i', strtotime((string) $h['created_at']))) ?></span>
          <span><?= e((string) $h['note']) ?></span></p>
      <?php endforeach; ?>
      <?php if ($hRest): ?>
        <details class="mt-8">
          <summary class="small">Show <?= count($hRest) ?> older</summary>
          <div class="mt-8">
            <?php foreach ($hRest as $h): ?>
              <p class="hist-row"><span class="hist-when mono"><?= e(date('j M, H:i', strtotime((string) $h['created_at']))) ?></span>
                <span><?= e((string) $h['note']) ?></span></p>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endif; ?>
      <p class="hint">History is never edited or deleted — it is read straight
      from the audit log.</p>
    <?php endif; ?>
    </div>
  </details>

<?php  ?>
<?php 
 ?>
<?php  ?>
<?php elseif ($tab === 'assets'): ?>

  <div class="card">
    <div class="card-head">
      <h2>Websites and accounts</h2>
      <span class="spacer"></span>
      <?php 



 ?>
      <button class="btn" type="button" data-modal-open="dlg-property">Add property</button>
    </div>
    <?php if (!$assets): ?>
      <p class="muted small">Nothing yet. Each website, social account,
      registrar or hosting login is a property — add one below, then store its
      sign-in details on its own page, where they are encrypted.</p>
    <?php else: ?>
      <div class="table-wrap">
        <?php 

 ?>
        <table class="asset-table" data-cred-endpoint="<?= e(cm_url('admin/credential.php')) ?>"
               data-cred-csrf="<?= e(cm_csrf_token()) ?>">
          <thead><tr><th>Property</th><th class="col-addr">Address</th>
            <th class="col-signin">Sign-in details</th><th class="col-tight">Status</th>
            <th class="num col-tight"></th></tr></thead>
          <tbody>
          <?php 



 ?>
          <?php foreach (cm_assets_grouped($assets) as $grp): ?>
          <?php if ($grp['name'] !== ''): ?>
            <tr class="grouprow"><th colspan="5"><?= e($grp['name']) ?></th></tr>
          <?php endif; ?>
          <?php foreach ($grp['assets'] as $a): $ast = cm_asset_statuses()[(string) $a['status']] ?? null; ?>
            <tr>
              <?php 



 ?>
              <td>
                <a href="<?= e(cm_url('admin/asset.php?id=' . (int) $a['id'])) ?>"><b><?= e((string) $a['name']) ?></b></a>
              </td>
              <td class="small">
                <?php if ((string) $a['url'] !== ''): ?>
                  <a href="<?= e(cm_safe_href((string) $a['url'])) ?>" target="_blank" rel="noopener noreferrer"><?= e(preg_replace('#^https?://#', '', (string) $a['url'])) ?></a>
                <?php else: ?><span class="muted">—</span><?php endif; ?>
              </td>
              <?php 














 ?>
              <td class="small col-signin">
                <?php $hasHandle = (string) $a['handle'] !== ''; ?>
                <?php if (!$hasHandle && empty($a['has_credential'])): ?>
                  <span class="muted">none</span>
                <?php else: ?>
                  <?php if ($hasHandle): ?>
                    <input type="hidden" id="au<?= (int) $a['id'] ?>" value="<?= e((string) $a['handle']) ?>">
                    <button class="btn btn-sm btn-quiet" type="button" data-copy-for="au<?= (int) $a['id'] ?>">Copy username</button>
                  <?php endif; ?>
                  <?php if (!empty($a['has_credential'])): ?>
                    <button class="btn btn-sm btn-quiet" type="button" data-cred-copy="<?= (int) $a['id'] ?>">Copy password</button>
                    <?php $aw = cm_credential_warnings($a); ?>
                    <?php if ($aw): ?>
                      <span class="pill pill-spam" title="<?= e(implode('; ', $aw)) ?>">!</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="muted">no password</span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td class="col-tight"><?php if ($ast): ?><span class="pill <?= e($ast['pill']) ?>"><?= e($ast['label']) ?></span><?php endif; ?></td>
              <td class="num col-tight"><a class="btn btn-sm btn-quiet" href="<?= e(cm_url('admin/asset.php?id=' . (int) $a['id'])) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php 












 ?>
      <?php 









 ?>
      <?php if (cm_asset_categories_on()): ?>
      <div class="tile-admin-foot mt-8">
        <span class="spacer"></span>
        <button class="btn" type="button" data-modal-open="dlg-category">Add subcategory</button>
      </div>
      <?php endif; ?>
      <?php 





 ?>
    <?php endif; ?>
  </div>

  <?php 



 ?>

  <?php 


 ?>
  <?php if (cm_asset_categories_on()): ?>
  <dialog class="modal" id="dlg-category">
    <div class="modal-box">
      <div class="modal-head">
        <h2>Add a subcategory</h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">Optional. Groups a client's properties when not
      everything here belongs to them &mdash; your own assets, a
      subcontractor's, work of different kinds. Subcategories are shared across
      every client, so you only type each one once.</p>
      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="add_category">
        <div class="row">
          <label class="lbl" for="category_new">Subcategory name</label>
          <input type="text" id="category_new" name="category_new" maxlength="80" class="f-full"
                 placeholder="Client-owned, Ours, SEO…">
        </div>
        <div class="modal-foot">
          <button class="btn" type="submit">Add</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>
  <?php endif; ?>

<?php  ?>
<?php elseif ($tab === 'jobs'): ?>
  <?php require_once CM_LIB . '/jobs.php';
        require_once CM_LIB . '/reminders.php';
        $cjobs = [];
        try { $cjobs = cm_client_jobs($id); } catch (Throwable $ex) {}









        $cjobsOpen = [];
        $cjobsDone = [];
        foreach ($cjobs as $j) {
            if ((string) $j['status'] === 'completed') $cjobsDone[] = $j;
            else $cjobsOpen[] = $j;
        }

        $jobRem = [];
        if ($cjobsDone) {
            try {
                $doneIds = implode(',', array_map(static fn(array $j): int => (int) $j['id'], $cjobsDone));
                foreach (cm_all("SELECT * FROM reminder WHERE status IN ('pending','snoozed')
                                  AND job_id IN ({$doneIds}) ORDER BY due_date, id") as $r) {
                    $jid = (int) $r['job_id'];
                    if (!isset($jobRem[$jid])) $jobRem[$jid] = $r;
                }
            } catch (Throwable $ex) {}
        }


        $remIsDue = static function (array $r): bool {
            $today = date('Y-m-d');
            return ((string) $r['status'] === 'pending' && (string) $r['due_date'] <= $today)
                || ((string) $r['status'] === 'snoozed' && (string) ($r['snoozed_until'] ?? '') !== ''
                    && (string) $r['snoozed_until'] <= $today);
        };
  ?>
  <div class="card">
    <h2>Jobs for <?= e(cm_client_display_name($client)) ?></h2>
    <?php if (!$cjobsOpen): ?>
      <p class="muted small"><?= $cjobsDone
          ? 'Nothing running — the finished work is below.'
          : 'None yet — <a href="' . e(cm_url('admin/jobs.php')) . '">create one on the Jobs screen</a>.' ?></p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Job</th><th>Dates</th><th>Progress</th><th>Value</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($cjobsOpen as $j):
              $jst = cm_job_statuses()[(string) $j['status']] ?? null;
              $prog = cm_job_progress((int) $j['id']); ?>
            <tr>
              <td><a href="<?= e(cm_url('admin/job.php?id=' . (int) $j['id'])) ?>"><b><?= e((string) $j['name']) ?></b></a></td>
              <td class="small mono"><?= $j['start_date'] ? e(date('j M', strtotime((string) $j['start_date']))) : '—' ?> → <?= $j['end_date'] ? e(date('j M', strtotime((string) $j['end_date']))) : 'open' ?></td>
              <td class="small mono"><?= $prog['total'] > 0 ? $prog['done'] . ' / ' . $prog['total'] : '—' ?></td>
              <td class="small mono"><?= (int) $j['amount_cents'] > 0 ? e(cm_money((int) $j['amount_cents'])) : '—' ?></td>
              <td><?php if ($jst): ?><span class="pill <?= e($jst['pill']) ?>"><?= e($jst['label']) ?></span><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($cjobsDone): ?>
  <div class="card">
    <h2>Completed</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Job</th><th>Dates</th><th>Value</th><th>Reminder</th></tr></thead>
        <tbody>
        <?php foreach ($cjobsDone as $j): $jid = (int) $j['id']; $r = $jobRem[$jid] ?? null; ?>
          <tr>
            <td><a href="<?= e(cm_url('admin/job.php?id=' . $jid)) ?>"><b><?= e((string) $j['name']) ?></b></a></td>
            <td class="small mono"><?= $j['start_date'] ? e(date('j M', strtotime((string) $j['start_date']))) : '—' ?> → <?= $j['end_date'] ? e(date('j M', strtotime((string) $j['end_date']))) : 'open' ?></td>
            <td class="small mono"><?= (int) $j['amount_cents'] > 0 ? e(cm_money((int) $j['amount_cents'])) : '—' ?></td>
            <td>
              <?php if ($r === null): ?>
                <span class="muted small">None</span>
              <?php elseif ($remIsDue($r)): ?>
                <span class="pill pill-pending">Due</span>
                <span class="small mono"><?= e(date('j M', strtotime((string) $r['due_date']))) ?></span>
              <?php else: ?>
                <span class="pill pill-approved">Set</span>
                <span class="small mono"><?= e(date('j M Y', strtotime((string) $r['due_date']))) ?></span>
              <?php endif; ?>
              <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-jrem-<?= $jid ?>"
                      aria-label="<?= $r === null ? 'Set a reminder' : 'Edit the reminder' ?>"
                      title="<?= $r === null ? 'Set a reminder' : 'Edit the reminder' ?>">&#9998;</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="hint">The earliest open reminder on each job. The pencil edits it
      here — or sets one, where a job has none.</p>
  </div>

  <?php 
 ?>
  <?php foreach ($cjobsDone as $j): $jid = (int) $j['id']; $r = $jobRem[$jid] ?? null; ?>
    <dialog class="modal" id="dlg-jrem-<?= $jid ?>">
      <div class="modal-box">
        <div class="modal-head">
          <h2><?= $r === null ? 'Set a reminder' : 'Edit the reminder' ?> — <?= e((string) $j['name']) ?></h2>
          <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
        </div>
        <form method="post">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="jobrem_save">
          <input type="hidden" name="rem_id" value="<?= $r !== null ? (int) $r['id'] : 0 ?>">
          <input type="hidden" name="rem_job" value="<?= $jid ?>">
          <div class="field">
            <label class="lbl" for="jrem-title-<?= $jid ?>">What about</label>
            <input type="text" id="jrem-title-<?= $jid ?>" name="rem_title" class="f-full" maxlength="190" required
                   value="<?= e($r !== null ? (string) $r['title'] : 'Follow up — ' . (string) $j['name']) ?>">
          </div>
          <div class="form-row">
            <div class="field">
              <label class="lbl" for="jrem-due-<?= $jid ?>">When</label>
              <input type="date" id="jrem-due-<?= $jid ?>" name="rem_due" class="f-auto" required
                     value="<?= e($r !== null ? (string) $r['due_date'] : '') ?>">
            </div>
            <div class="field">
              <label class="lbl" for="jrem-repeat-<?= $jid ?>">Repeat</label>
              <select id="jrem-repeat-<?= $jid ?>" name="rem_repeat" class="f-auto">
                <?php foreach (cm_reminder_repeats() as $rk => $rlabel): ?>
                  <option value="<?= e($rk) ?>" <?= $r !== null && (string) $r['repeat_every'] === $rk ? 'selected' : '' ?>>
                    <?= e($rlabel) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <?php if ($r !== null && (string) $r['status'] === 'snoozed'): ?>
            <p class="hint">This reminder is snoozed — saving a new date wakes it:
              it becomes a plain pending reminder for the day you pick.</p>
          <?php endif; ?>
          <div class="modal-foot">
            <button class="btn" type="submit"><?= $r === null ? 'Set the reminder' : 'Save' ?></button>
            <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
          </div>
        </form>
      </div>
    </dialog>
  <?php endforeach; ?>
  <?php endif; ?>

<?php  ?>
<?php elseif ($tab === 'invoices'): ?>
  <?php
    require_once CM_LIB . '/invoices.php';
    $clientInvoices = [];
    try {
        $clientInvoices = cm_all(
            'SELECT i.*, j.name AS linked_job_name, j.description AS linked_job_description, '
          . 'j.start_date AS linked_job_start, j.end_date AS linked_job_end, '
          . 'j.status AS linked_job_status '
          . 'FROM invoice i LEFT JOIN job j ON j.id = i.job_id AND j.client_id = i.client_id '
          . 'WHERE i.client_id = ? '
          . 'ORDER BY COALESCE(i.issued_on, i.created_at) DESC, i.id DESC LIMIT 50', [$id]);
    } catch (Throwable $ex) {}
    $invoiceStatuses = cm_invoice_statuses();
  ?>
  <div class="card">
    <div class="card-head">
      <div>
        <h2 class="mb-4">Recent invoices</h2>
        <p class="muted small m-0"><?= count($clientInvoices) ?> invoice<?= count($clientInvoices) === 1 ? '' : 's' ?> for <?= e(cm_client_display_name($client)) ?></p>
      </div>
      <span class="spacer"></span>
      <a class="btn btn-sm btn-quiet" href="<?= e(cm_url('admin/invoices.php?f=all')) ?>">All invoices</a>
      <a class="btn btn-sm" href="<?= e(cm_url('admin/invoices.php?new_for=' . $id)) ?>">+ New invoice</a>
    </div>

    <?php if (!$clientInvoices): ?>
      <div class="empty-state">
        <h3>No invoices yet</h3>
        <p>When this client is billed, their drafts, outstanding invoices and payments will appear here.</p>
        <a class="btn btn-sm" href="<?= e(cm_url('admin/invoices.php?new_for=' . $id)) ?>">Create first invoice</a>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Number</th><th>Status</th><th>Invoice date</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Amount due</th></tr></thead>
          <tbody>
          <?php foreach ($clientInvoices as $inv):
              $balance = cm_invoice_balance_cents($inv);
              $overdue = (string) $inv['status'] === 'sent' && $inv['due_on'] !== null
                      && (string) $inv['due_on'] < date('Y-m-d');
              $pill = $overdue ? 'pill-spam'
                    : ((string) $inv['status'] === 'paid' ? 'pill-approved'
                    : ((string) $inv['status'] === 'void' ? 'pill'
                    : ((string) $inv['status'] === 'draft' ? 'pill-pending' : 'pill-live')));
              $statusLabel = $overdue ? 'Overdue'
                           : (string) ($invoiceStatuses[(string) $inv['status']]['label'] ?? $inv['status']); ?>
            <tr>
              <td class="mono"><a href="<?= e(cm_url('admin/invoice.php?id=' . (int) $inv['id'])) ?>"><b><?= e((string) ($inv['number'] ?? 'draft')) ?></b></a>
                <?php if ((int) ($inv['job_id'] ?? 0) > 0 && (string) ($inv['linked_job_name'] ?? '') !== ''): ?>
                  <button class="invoice-job-info" type="button" data-modal-open="dlg-invjob-<?= (int) $inv['id'] ?>"
                          aria-label="View job details for <?= e((string) $inv['linked_job_name']) ?>"
                          title="View linked job details">&#9432;</button>
                <?php endif; ?>
              </td>
              <td><span class="pill <?= e($pill) ?>"><?= e($statusLabel) ?></span></td>
              <td class="small"><?= $inv['issued_on'] ? e(date('j M Y', strtotime((string) $inv['issued_on']))) : '&mdash;' ?></td>
              <td class="num mono"><?= e(cm_invoice_money($inv, (int) $inv['total_cents'])) ?></td>
              <td class="num mono"><?= (int) $inv['paid_cents'] > 0 ? e(cm_invoice_money($inv, (int) $inv['paid_cents'])) : '&mdash;' ?></td>
              <td class="num mono<?= $balance > 0 && (string) $inv['status'] === 'sent' ? ' inv-due' : '' ?>"><?= $balance > 0 && (string) $inv['status'] === 'sent' ? e(cm_invoice_money($inv, $balance)) : '&mdash;' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php foreach ($clientInvoices as $inv): ?>
        <?php if ((int) ($inv['job_id'] ?? 0) <= 0 || (string) ($inv['linked_job_name'] ?? '') === '') continue;
              $jobProgress = cm_job_progress((int) $inv['job_id']);
              $linkedStatus = cm_job_statuses()[(string) ($inv['linked_job_status'] ?? '')] ?? null; ?>
        <dialog class="modal" id="dlg-invjob-<?= (int) $inv['id'] ?>">
          <div class="modal-box">
            <div class="modal-head">
              <div>
                <p class="eyebrow">Linked job</p>
                <h2><?= e((string) $inv['linked_job_name']) ?></h2>
              </div>
              <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
            </div>
            <div class="mini-grid mb-16">
              <div class="mini"><span class="n"><?= $inv['linked_job_start'] ? e(date('j M Y', strtotime((string) $inv['linked_job_start']))) : '&mdash;' ?></span>starts</div>
              <div class="mini"><span class="n"><?= $inv['linked_job_end'] ? e(date('j M Y', strtotime((string) $inv['linked_job_end']))) : 'Open ended' ?></span>ends</div>
              <div class="mini"><span class="n"><?= $jobProgress['total'] > 0 ? $jobProgress['done'] . ' of ' . $jobProgress['total'] : '&mdash;' ?></span>tasks complete</div>
              <div class="mini"><span class="n"><?php if ($linkedStatus): ?><span class="pill <?= e($linkedStatus['pill']) ?>"><?= e($linkedStatus['label']) ?></span><?php else: ?>&mdash;<?php endif; ?></span>status</div>
            </div>
            <h3>Job description</h3>
            <p><?= trim((string) ($inv['linked_job_description'] ?? '')) !== '' ? nl2br(e((string) $inv['linked_job_description'])) : '<span class="muted">No description added.</span>' ?></p>
            <div class="modal-foot">
              <a class="btn" href="<?= e(cm_url('admin/job.php?id=' . (int) $inv['job_id'])) ?>">Open job</a>
              <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
            </div>
          </div>
        </dialog>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

<?php  ?>
<?php elseif ($tab === 'comms'): ?>
  <?php
  $comms = [];
  $clientReminders = [];
  try {
      $comms = cm_client_communications($id);



      require_once CM_LIB . '/maillog.php';
      $sentMail = cm_mail_for_client($id, 50);
      $clientReminders = cm_all("SELECT * FROM reminder WHERE client_id = ? AND status IN ('pending','snoozed')
                                 ORDER BY due_date", [$id]);
  } catch (Throwable $ex) {}
  ?>

  <?php 








 ?>
  <?php
  require_once CM_LIB . '/onboarding.php';




  require_once CM_LIB . '/publicsite.php';
  $obForms = [];
  try {
      $obForms = cm_onboard_for_client($id);
  } catch (Throwable $ex) {}


  $repliedMap = [];
  try {
      $repliedMap = cm_mail_replied_counts(array_map(static fn(array $m): int => (int) $m['id'], $sentMail));
  } catch (Throwable $ex) {}
  ?>
  <?php 



 ?>
  <?php
  require_once CM_LIB . '/emails.php';
  $composeCtx = [
      'client_name'       => cm_client_display_name($client),
      'client_first_name' => trim((string) ($client['first_name'] ?? '')) !== '' ? (string) $client['first_name'] : cm_client_display_name($client),
      'my_link'           => cm_public_enabled() ? cm_link_url(cm_link_for($id)) : '',
  ];
  $composeTpls = [];
  foreach (cm_email_templates() as $tk => $tt) {
      if ($tt['locked']) continue;
      if (in_array($tk, ['job_summary', 'invoice_issued', 'invoice_paid'], true)) continue;
      $composeTpls[$tk] = ['label' => (string) $tt['label'], 'custom' => !empty($tt['custom']),
                           'subject' => cm_email_render((string) $tt['subject'], $composeCtx),
                           'body'    => cm_email_render((string) $tt['body'], $composeCtx)];
  }
  $composeFirst = $composeTpls ? (string) array_key_first($composeTpls) : '';
  $composeUnsub = cm_unsub_state($id);
  ?>
  <?php 

 ?>
  <div class="card" id="send-email">
    <div class="card-head">
      <h2>Get in touch</h2>
      <span class="spacer"></span>
      <?php if (!cm_home() && ($composeUnsub === null || !$composeUnsub['unsubscribed'])): ?>
        <button class="btn btn-sm" type="button" data-modal-open="dlg-compose">Send an email</button>
      <?php endif; ?>
      <?php if (!cm_home()): ?>
      <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-sendmsg">Quick questions</button>
      <?php endif; ?>
      <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-logcomm">Log a conversation</button>
    </div>
    <?php if (cm_home()): ?>
      <p class="muted small m-0">A phone call or a visit, put on the record &mdash; it opens in its own window.</p>
    <?php elseif ($composeUnsub !== null && $composeUnsub['unsubscribed']): ?>
      <p class="muted small m-0">Nothing can be <b>emailed</b> to them &mdash; they have unsubscribed; the
      card below says how that is undone. A questions link is not an email &mdash; you hand it to them
      yourself &mdash; and logging a conversation is always open.</p>
    <?php else: ?>
      <p class="muted small m-0">An email from your templates, a link with questions to answer,
      or a phone call put on the record &mdash; each opens in its own window.</p>
    <?php endif; ?>
  </div>

  <?php if (!cm_home() && ($composeUnsub === null || !$composeUnsub['unsubscribed'])): ?>
  <dialog class="modal modal-wide" id="dlg-compose">
    <div class="modal-box">
      <div class="modal-head">
        <h2>Send an email to <?= e(cm_client_display_name($client)) ?></h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">Pick a message and it fills in below, already worded for this client. What is in the boxes
      is exactly what goes.<?= cm_mail_can_deliver() ? '' : ' <b>Email sending is currently off</b>, so this will be written to storage/mail/ instead of sent.' ?></p>
      <form method="post" data-confirm="Email this to <?= e((string) ($client['email'] ?: 'the address above')) ?>?">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="send_email">
        <div class="row">
          <label class="lbl" for="em_template">Message</label>
          <select id="em_template" name="em_template" class="f-full" data-compose-pick>
            <?php foreach ($composeTpls as $tk => $tt): ?>
              <option value="<?= e($tk) ?>"><?= e($tt['label']) ?><?= $tt['custom'] ? ' (yours)' : '' ?></option>
            <?php endforeach; ?>
            <?php if (!$composeTpls): ?><option value="">— no message to pick; write one below —</option><?php endif; ?>
          </select>
        </div>
        <div class="row">
          <label class="lbl" for="em_to">To</label>
          <input type="email" id="em_to" name="em_to" class="f-full" value="<?= e((string) $client['email']) ?>" required>
        </div>
        <div class="row">
          <label class="lbl" for="em_subject">Subject</label>
          <input type="text" id="em_subject" name="em_subject" class="f-full" maxlength="200" data-compose-subject
                 value="<?= e($composeFirst !== '' ? $composeTpls[$composeFirst]['subject'] : '') ?>" required>
        </div>
        <div class="row">
          <label class="lbl" for="em_body">Message</label>
          <textarea id="em_body" name="em_body" rows="12" class="f-full small" data-compose-body><?= e($composeFirst !== '' ? $composeTpls[$composeFirst]['body'] : '') ?></textarea>
          <div class="hint">An unsubscribe link is included in all emails sent by this platform.</div>
        </div>
        <div class="modal-foot">
          <button class="btn" type="submit">Send</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>
  <script type="application/json" id="compose-data"><?= json_encode(array_map(static fn(array $x): array => ['subject' => $x['subject'], 'body' => $x['body']], $composeTpls), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?></script>
  <script defer src="<?= e(cm_url('js/compose.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>
  <?php endif; ?>

  <?php 





 ?>
  <?php foreach ($obForms as $r): ?>
    <?php $obTitle = $r['form_name'] ?? null ? (string) $r['form_name']
            : ($r['job_name'] ? (string) $r['job_name']
            : ($r['service_name'] ? (string) $r['service_name'] : 'The general questions')); ?>
    <?php if ((string) $r['status'] === 'complete'): ?>
    <dialog class="modal modal-wider" id="dlg-creply-<?= (int) $r['id'] ?>">
      <div class="modal-box">
        <div class="modal-head">
          <h2><?= e($obTitle) ?></h2>
          <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
        </div>
        <p class="modal-sub">Answered <?= e(date('j M Y',
          strtotime((string) ($r['completed_at'] ?: $r['created_at'])))) ?>. Any sign-in they gave
        is not here &mdash; it went straight into the vault, on the
        <b>Properties</b> tab.</p>
        <?= cm_onboard_answers_html((int) $r['id']) ?>
        <div class="modal-foot">
          <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
        </div>
      </div>
    </dialog>
    <?php else: ?>
    <dialog class="modal modal-wider" id="dlg-cq-<?= (int) $r['id'] ?>">
      <div class="modal-box">
        <div class="modal-head">
          <h2><?= e($obTitle) ?></h2>
          <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
        </div>
        <p class="modal-sub">What the link asks &mdash; sent <?= e(date('j M Y', strtotime((string) $r['created_at']))) ?>,
          <?= empty($r['opened_at']) ? 'not opened yet' : 'opened ' . e(date('j M Y', strtotime((string) $r['opened_at']))) . ', not answered yet' ?>.</p>
        <?php if (trim((string) ($r['message'] ?? '')) !== ''): ?>
          <p class="small prewrap mb-12"><b>Your note at the top:</b><br><?= e((string) $r['message']) ?></p>
        <?php endif; ?>
        <?php $obAsk = cm_onboard_fields_for_response($r); ?>
        <?php if (!$obAsk): ?>
          <p class="muted small">No questions on this form.</p>
        <?php else: ?>
          <ul class="tile-list">
            <?php foreach ($obAsk as $f): ?>
              <?php if (!cm_onboard_asks((string) $f['ftype'])) { ?>
                <li class="muted small prewrap"><?= e((string) ($f['help'] !== '' ? $f['help'] : $f['label'])) ?></li>
              <?php } else { ?>
                <li><?= e((string) $f['label']) ?>
                  <?php if ((int) $f['required'] === 1): ?><span class="muted small">— needed</span><?php endif; ?></li>
              <?php } ?>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <div class="modal-foot">
          <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
        </div>
      </div>
    </dialog>
    <?php endif; ?>
  <?php endforeach; ?>

  <?php 



 ?>
  <?php foreach ($sentMail as $m): ?>
    <?php $mNotes = ($repliedMap[(int) $m['id']] ?? 0) > 0 ? cm_replies_for_mail((int) $m['id']) : []; ?>
    <dialog class="modal modal-widest" id="dlg-reply-<?= (int) $m['id'] ?>">
      <div class="modal-box">
        <div class="modal-head">
          <h2><?= trim((string) $m['subject']) !== '' ? e((string) $m['subject']) : 'The email' ?></h2>
          <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
        </div>
        <p class="modal-sub">Their reply arrives in your inbox, not here &mdash; paste it, or note
          what it said, and the record keeps the two side by side.</p>
        <div class="split-2">
          <div>
            <h3>What you sent</h3>
            <p class="small muted mb-6"><?= e(date('j M Y, g:ia', strtotime((string) $m['created_at']))) ?>
              to <span class="mono"><?= e((string) $m['sent_to']) ?></span></p>
            <?php if (trim((string) ($m['body'] ?? '')) !== ''): ?>
              <pre class="small prewrap mail-text"><?= e((string) $m['body']) ?></pre>
            <?php else: ?>
              <p class="muted small">No copy of the body was kept.</p>
            <?php endif; ?>
          </div>
          <div>
            <h3>What came back</h3>
            <?php foreach ($mNotes as $n): ?>
              <p class="small muted mb-4 mt-10"><b>Noted <?= e(date('j M, g:ia', strtotime((string) $n['created_at']))) ?></b></p>
              <p class="small prewrap m-0"><?= e((string) $n['note']) ?></p>
            <?php endforeach; ?>
            <form method="post" class="<?= $mNotes ? 'mt-14' : 'mt-6' ?>">
              <?= cm_csrf_field() ?>
              <input type="hidden" name="action" value="reply_add">
              <input type="hidden" name="reply_mail" value="<?= (int) $m['id'] ?>">
              <label class="lbl" for="rn-<?= (int) $m['id'] ?>"><?= $mNotes ? 'Add more at the end' : 'What did they say?' ?></label>
              <textarea id="rn-<?= (int) $m['id'] ?>" name="reply_note" rows="<?= $mNotes ? 3 : 6 ?>" class="f-full small"
                        placeholder="Paste their reply, or note it in your own words."></textarea>
              <div class="mt-8">
                <button class="btn btn-sm" type="submit"><?= $mNotes ? 'Add it' : 'Client replied — save it' ?></button>
              </div>
            </form>
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
        </div>
      </div>
    </dialog>
  <?php endforeach; ?>

  <?php 







 ?>
  <?php if (!cm_home() && ($unsub === null || !$unsub['unsubscribed'])): ?>
    <div class="card">
      <div class="card-head">
        <h2>Email</h2>
        <span class="spacer"></span>
        <span class="pill pill-approved">emails on</span>
      </div>
      <p class="muted small mb-12">Every email we send them carries an
      unsubscribe link — that is a legal requirement, not a setting. Use this
      only when they have asked you directly, on the phone or in a reply.
      <?php if ($unsub !== null && $unsub['resubscribed_at']): ?>
        <br><b>They unsubscribed once before</b> and it was turned back on
        <?= e(date('j M Y', strtotime((string) $unsub['resubscribed_at']))) ?>
        <?= (string) $unsub['resubscribed_by'] !== ''
            ? 'by ' . e((string) $unsub['resubscribed_by']) : '' ?>.
      <?php endif; ?></p>
      <form method="post"
            data-confirm="Stop ALL emails to <?= e(cm_client_display_name($client)) ?> — summaries and invoices included? Only they can ask for this, and only a phone call undoes it.">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="unsub_stop">
        <button class="btn btn-sm btn-quiet" type="submit">They asked us to stop emailing</button>
      </form>
    </div>
  <?php endif; ?>

  <?php 


 ?>
  <dialog class="modal modal-wide" id="dlg-logcomm">
    <div class="modal-box">
      <div class="modal-head">
        <h2>Log a conversation</h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">What was said and agreed, in your own words &mdash; the
      record that makes coming back to this client after a year painless.</p>
      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="add_comm">
        <div class="row">
          <label class="lbl" for="m_subject">What it was about</label>
          <input type="text" id="m_subject" name="m_subject" maxlength="190"
                 value="<?= e((string) ($_POST['m_subject'] ?? '')) ?>" required>
          <?= cm_field_error($errors, 'm_subject') ?>
        </div>
        <div class="row">
          <label class="lbl" for="m_kind">How</label>
          <select id="m_kind" name="m_kind">
            <?php foreach (cm_communication_kinds() as $key => $label): ?>
              <option value="<?= e($key) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <input class="f-base" type="date" name="m_when" value="<?= e(date('Y-m-d')) ?>">
          <input class="f-lg" type="text" name="m_contact" placeholder="who, if not the client">
        </div>
        <div class="row">
          <label class="lbl" for="m_notes">Notes</label>
          <textarea id="m_notes" name="m_notes" rows="3"></textarea>
        </div>
        <div class="row">
          <label class="lbl" for="m_follow">Follow up on</label>
          <input class="f-base" type="date" id="m_follow" name="m_follow">
          <div class="hint">A date here sets a reminder automatically &mdash; one list
          to watch, not two.</div>
        </div>
        <div class="modal-foot">
          <button class="btn" type="submit">Log it</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>

  <?php if ($clientReminders): ?>
    <div class="card">
      <h2>Reminders about this client</h2>
      <ul class="tile-list">
        <?php foreach ($clientReminders as $r): ?>
          <li><span class="mono small"><?= e(date('j M Y', strtotime((string) $r['due_date']))) ?></span>
            — <?= e((string) $r['title']) ?>
            <?php if ((string) $r['status'] === 'snoozed'): ?><span class="pill pill-live">snoozed</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="hint">Done/snooze them on the <a href="<?= e(cm_url('admin/reminders.php')) ?>">Reminders screen</a>
      or the dashboard when they fall due.</p>
    </div>
  <?php endif; ?>

  <?php 








 ?>
  <?php
  $record = [];
  foreach ($comms as $m) {
      $record[] = [
          'at'    => (string) $m['happened_at'],
          'sort'  => strtotime((string) $m['happened_at']) ?: 0,
          'how'   => cm_communication_kinds()[(string) $m['kind']] ?? 'Other',
          'who'   => (string) $m['contact'],
          'about' => (string) $m['subject'],
          'notes' => (string) $m['notes'],
          'follow'=> $m['follow_up'] ?? null,
          'mail'  => null,
      ];
  }
  foreach ($sentMail as $m) {
      $out = cm_mail_outcome((string) $m['result']);
      $record[] = [
          'at'    => (string) $m['created_at'],
          'sort'  => strtotime((string) $m['created_at']) ?: 0,
          'how'   => cm_mail_kind_label((string) $m['kind']),
          'who'   => (string) $m['sent_to'],
          'about' => (string) $m['subject'],
          'notes' => (string) ($m['body'] ?? ''),
          'follow'=> null,
          'mail'  => $out,
          'mailid'  => (int) $m['id'],
          'replied' => (int) ($repliedMap[(int) $m['id']] ?? 0),
      ];
  }



  foreach ($obForms as $r) {
      $record[] = [
          'at'    => (string) $r['created_at'],
          'sort'  => strtotime((string) $r['created_at']) ?: 0,
          'how'   => 'Questions link',
          'who'   => '',
          'about' => (string) (($r['form_name'] ?? null) ?: ($r['job_name'] ?: ($r['service_name'] ?: 'General questions'))),
          'notes' => '',
          'follow'=> null,
          'mail'  => null,
          'ob'    => $r,
      ];
  }
  usort($record, static fn ($a, $b) => $b['sort'] <=> $a['sort']);
  ?>
  <div class="card" id="record">
    <div class="card-head">
      <h2>The record — <?= count($record) ?></h2>
      <span class="spacer"></span>
      <?php if ($sentMail && !cm_home()): ?>
        <a class="small" href="<?= e(cm_url('admin/mail.php?q=' . rawurlencode((string) $client['email']))) ?>">all their emails</a>
      <?php endif; ?>
    </div>
    <?php if (!$record): ?>
      <p class="muted small">Nothing yet — no conversations logged, and
      nothing emailed to them.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>When</th><th>How</th><th>About</th><th>Notes</th></tr></thead>
          <tbody>
          <?php foreach ($record as $m): ?>
            <tr>
              <td class="mono small nowrap"><?= e(date('j M Y', $m['sort'] ?: time())) ?></td>
              <td class="small">
                <?= e($m['how']) ?>
                <?php if ($m['mail'] !== null): ?>
                  <div><span class="pill <?= e($m['mail']['pill']) ?>"><?= e($m['mail']['label']) ?></span></div>
                <?php endif; ?>
                <?php if ($m['who'] !== ''): ?><div class="muted"><?= e($m['who']) ?></div><?php endif; ?>
              </td>
              <td class="small"><b><?= e($m['about']) ?></b>
                <?php if ($m['follow']): ?>
                  <div class="muted">follow up <?= e(date('j M', strtotime((string) $m['follow']))) ?></div>
                <?php endif; ?>
                <?php if ($m['mail'] !== null && $m['mail']['note'] !== ''): ?>
                  <div class="<?= $m['mail']['key'] === 'sent' ? 'muted' : 'txt-err' ?>"><?= e($m['mail']['note']) ?></div>
                <?php endif; ?>
                <?php if (($m['ob'] ?? null) !== null): ?>
                  <?php $obState = cm_onboard_state($m['ob']); ?>
                  <div><span class="pill <?= e($obState['pill']) ?>"><?= e($obState['label']) ?></span></div>
                <?php endif; ?>
              </td>
              <td class="small">
                <?php if (($m['ob'] ?? null) !== null): ?>
                  <?php 
 ?>
                  <?php $obr = $m['ob']; ?>
                  <?php if ((string) $obr['status'] === 'complete'): ?>
                    <button class="btn btn-sm btn-quiet" type="button"
                            data-modal-open="dlg-creply-<?= (int) $obr['id'] ?>">Read the answers</button>
                  <?php else: ?>
                    <?php $tk = cm_home() ? '' : cm_token_clear($obr); ?>
                    <?php if ($tk !== ''): ?>
                      <div class="linkcopy">
                        <input type="text" class="mono small" readonly
                               value="<?= e(cm_onboard_url($tk)) ?>"
                               id="obl<?= (int) $obr['id'] ?>"
                               aria-label="The link to this form">
                        <button class="btn btn-sm btn-quiet" type="button"
                                data-copy-for="obl<?= (int) $obr['id'] ?>">Copy</button>
                      </div>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-quiet mt-6" type="button"
                            data-modal-open="dlg-cq-<?= (int) $obr['id'] ?>">The questions</button>
                  <?php endif; ?>
                <?php elseif ($m['mail'] !== null): ?>
                  <?php 
 ?>
                  <?php if (trim($m['notes']) !== ''): ?>
                    <details class="mail-row">
                      <summary>what was sent</summary>
                      <div class="mail-body"><pre class="small prewrap mail-text"><?= e($m['notes']) ?></pre></div>
                    </details>
                  <?php else: ?>
                    <span class="muted">no copy kept</span>
                  <?php endif; ?>
                  <?php if (($m['mailid'] ?? 0) > 0): ?>
                    <?php 

 ?>
                    <div class="mt-6">
                      <?php if (($m['replied'] ?? 0) > 0): ?>
                        <span class="pill pill-approved">replied</span>
                        <button class="btn btn-sm btn-quiet" type="button"
                                data-modal-open="dlg-reply-<?= (int) $m['mailid'] ?>">Open</button>
                      <?php else: ?>
                        <button class="btn btn-sm btn-quiet" type="button"
                                data-modal-open="dlg-reply-<?= (int) $m['mailid'] ?>">Client replied?</button>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="prewrap"><?= e($m['notes']) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<?php endif; ?>

</div><?php  ?>
<?php 



 ?>
  <?php 


 ?>
  <?= cm_asset_add_window_html('dlg-property', 'a_', 'add_asset', 'Add a property',
                               'Every field is optional, and can be edited later', $errors) ?>


<?php 




 ?>
<dialog class="modal modal-widest" id="dlg-job"<?= cm_job_draft_pending() ? ' data-modal-autoopen' : '' ?>>
  <div class="modal-box">
    <div class="modal-head">
      <h2>New job for <?= e(cm_client_display_name($client)) ?></h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <p class="modal-sub">Choose a service, confirm the schedule and price, then create the job. Optional details can be added now or later.</p>
    <form method="post">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="add_job_here">
      <?php 
 ?>
      <?= cm_job_form_fields_html('j_', null, true) ?>
      <div class="modal-foot">
        <button class="btn" type="submit">Create the job</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
  </div>
</dialog>

<?php 








 ?>
<?php
require_once CM_LIB . '/onboarding.php';
$smForms = [];
try { $smForms = cm_onboard_forms(); } catch (Throwable $ex) {}
$smGeneral = 0;
try { $smGeneral = cm_onboard_general_form_id(); } catch (Throwable $ex) {}




$smBlocked = cm_onboard_links_blocked();
?>
<?php if (!cm_home()): ?>
<dialog class="modal modal-widest" id="dlg-sendmsg">
  <div class="modal-box">
    <div class="modal-head">
      <h2>Send a message to <?= e(cm_client_display_name($client)) ?></h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <?php  ?>
    <p class="modal-sub">This tool lets you create a message, and questions for the client
    to answer. It creates a unique link: you give it to them, they click it and answer,
    and the answers are saved against their client page.</p>
    <form method="post">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="sendmsg_create">

      <?php 

 ?>
      <div class="form-row">
        <div class="field">
          <label class="lbl" for="sm-heading">Heading on their page <span class="muted">(optional)</span></label>
          <input type="text" id="sm-heading" name="sm_heading" class="f-full" maxlength="190"
                 placeholder="Started on your project">
        </div>
      </div>
      <div class="field">
        <label class="lbl" for="sm-message">Message to client</label>
        <textarea id="sm-message" name="sm_message" rows="4" class="f-full" maxlength="4000"
                  placeholder="Your words, shown under the heading — a quick note, a question, an update."></textarea>
      </div>

      <?php 




 ?>
      <div class="split-2 mt-12">
        <div>
          <h3>The standard questions</h3>
          <label class="caps-row">
            <input type="checkbox" name="sm_use_set" value="1" checked>
            <span><b>Include a standard set</b>
              <span class="muted small">Untick only if you are writing every question yourself
              on the right.</span></span>
          </label>
          <?php if (!$smForms): ?>
            <p class="muted small">Only the general questions exist so far. Build more sets on
            the <a href="<?= e(cm_url('admin/onboarding.php')) ?>">Onboarding screen</a>.</p>
            <input type="hidden" name="sm_form" value="0">
          <?php else: ?>
            <div class="caps-pick">
              <?php foreach ($smForms as $f): ?>
                <label class="caps-row">
                  <input type="radio" name="sm_form" value="<?= (int) $f['id'] ?>"
                         <?= (int) $f['id'] === $smGeneral || (!$smGeneral && $f === $smForms[0]) ? 'checked' : '' ?>>
                  <span>
                    <b><?= e((string) $f['name']) ?></b>
                    <?php 




                          $smDesc = (int) $f['id'] === $smGeneral
                              ? 'Asked of every client, whatever they have booked.'
                              : trim((string) $f['description']); ?>
                    <span class="muted small"><?= (int) $f['questions'] ?> question<?= (int) $f['questions'] === 1 ? '' : 's' ?><?php
                      if ($smDesc !== ''): ?> &mdash; <?= e($smDesc) ?><?php endif; ?></span>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
            <?php  ?>
            <p class="hint">Create, or edit the standard questions
            <a href="<?= e(cm_url('admin/onboarding.php')) ?>">here</a>.</p>
          <?php endif; ?>
        </div>
        <div>
          <h3>Add your own</h3>
          <?php 




 ?>
          <?= cm_onboard_question_grid_html('sm', ['form' => 0], 5) ?>
        </div>
      </div>
      <?php if ($smBlocked !== ''): ?>
        <div class="note note-warn mt-12">
          <?= e($smBlocked) ?>
          <a href="<?= e(cm_url('admin/settings.php')) ?>">Open Settings</a>.
        </div>
        <div class="modal-foot">
          <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
        </div>
      <?php else: ?>
      <?php 



 ?>
      <div class="field">
        <span class="lbl">How long the link works</span>
        <?= cm_link_expiry_radios('expiry_days') ?>
        <div class="hint">After that it stops opening and the client is asked to get in touch.</div>
      </div>

      <div class="modal-foot">
        <button class="btn" type="submit">Generate the link</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
      <?php endif; ?>
    </form>
  </div>
</dialog>
<?php endif; ?>

<aside class="day-rail">

  <?php 








 ?>
  <?php 







 ?>
  <?php 









 ?>
  <div class="card rail-actions">
    <button class="btn btn-quiet btn-full" type="button" data-modal-open="dlg-property">Add property</button>
    <button class="btn btn-full mt-8" type="button" data-modal-open="dlg-job">Add job</button>
    <?php 
 ?>
    <?php if (!cm_home()): ?>
    <button class="btn btn-quiet btn-full mt-8" type="button" data-modal-open="dlg-sendmsg">Send message</button>
    <?php endif; ?>
  </div>

  <?php 




 ?>
    <section class="card">
      <div class="card-head">
        <h2 class="t-ui">Where things stand</h2>
        <span class="spacer"></span>
        <a class="btn btn-sm btn-quiet" href="<?= e(cm_url('admin/client.php?id=' . $id . '&tab=jobs')) ?>">Jobs</a>
      </div>
      <?php
      require_once CM_LIB . '/jobs.php';
      $ctime = ['total' => 0, 'billable' => 0];
      try { $ctime = cm_time_totals('client', $id); } catch (Throwable $ex) {}
      $lastComm = null;
      try { $lastComm = cm_client_last_communication($id); } catch (Throwable $ex) {}
      ?>
      <dl class="ov-dl">
        <dt>Last spoke</dt>
        <dd><?= $lastComm ? e(date('j M Y', strtotime((string) $lastComm['happened_at'])))
              : '<span class="muted">never</span>' ?></dd>
        <dt>Time tracked</dt>
        <dd class="mono"><?= e(cm_minutes_label($ctime['total'])) ?></dd>
        <dt>Sign-ins changed</dt>
        <dd><?= $credLast !== null ? e(date('j M Y', strtotime($credLast)))
              : '<span class="muted">never</span>' ?></dd>
        <dt>On file since</dt>
        <dd><?= e(date('j M Y', strtotime((string) $client['created_at']))) ?></dd>
      </dl>
    </section>
    <?php  ?>

  <div class="card">
    <div class="card-head">
      <h2 class="t-ui">Jobs</h2>
      <span class="spacer"></span>
      <a class="small" href="<?= e(cm_url('admin/client.php?id=' . $id . '&tab=jobs')) ?>">full list</a>
    </div>
    <?php
    $railJobs = [];
    try { $railJobs = cm_client_jobs($id); } catch (Throwable $ex) {}
    ?>
    <?php if (!$railJobs): ?>
      <p class="muted small">No jobs yet — <b>Add job</b> above starts the first.</p>
    <?php else: ?>
      <?php 












 ?>
      <div class="rail">
        <?php foreach ($railJobs as $rj):
            $rjst = cm_job_statuses()[(string) $rj['status']] ?? null;
            $rprog = cm_job_progress((int) $rj['id']);
            $rjDone = [];
            try {
                $rjDone = cm_all(
                    "SELECT o.scheduled_date, o.note, o.internal_note, o.content_url,
                            o.minutes_spent, " . cm_occurrence_name_sql('a', 'j') . " AS action_name
                       FROM action_occurrence o
                       LEFT JOIN job_action a ON a.id = o.action_id
                       JOIN job j ON j.id = o.job_id
                      WHERE o.job_id = ? AND o.status IN ('completed', 'skipped')
                      ORDER BY o.scheduled_date DESC, o.id DESC
                      LIMIT 60", [(int) $rj['id']]);
            } catch (Throwable $ex) {}
        ?>
          <details class="rail-job<?= in_array((string) $rj['status'], ['completed', 'cancelled'], true) ? ' rail-passed' : '' ?>">
            <summary>
              <span class="small flex-1">
                <b><?= e((string) $rj['name']) ?></b>
                <?php if ($rjst): ?><span class="pill <?= e($rjst['pill']) ?>"><?= e($rjst['label']) ?></span><?php endif; ?>
                <span class="muted d-block">
                  <?= $rj['start_date'] ? e(date('j M', strtotime((string) $rj['start_date']))) : '' ?><?= $rj['end_date'] ? '–' . e(date('j M', strtotime((string) $rj['end_date']))) : '' ?>
                  <?php if ($rprog['total'] > 0): ?> · <span class="mono"><?= $rprog['done'] ?>/<?= $rprog['total'] ?></span><?php endif; ?>
                  <?php if ((int) $rj['amount_cents'] > 0): ?> · <span class="mono"><?= e(cm_money((int) $rj['amount_cents'])) ?></span><?php endif; ?>
                </span>
              </span>
            </summary>

            <div class="rail-job-body">
              <p class="small m-0 mb-8">
                <a href="<?= e(cm_url('admin/job.php?id=' . (int) $rj['id'])) ?>">Open the job</a>
                <?php 




 ?>
                · <a href="<?= e(cm_url('admin/summary.php?id=' . (int) $rj['id'])) ?>">Client summary</a>
              </p>

              <?php if (!$rjDone): ?>
                <p class="muted small m-0">Nothing done on it yet.</p>
              <?php else: ?>
                <?php foreach ($rjDone as $o): ?>
                  <div class="hist">
                    <div class="hist-when mono small">
                      <?= e(date('j M', strtotime((string) $o['scheduled_date']))) ?>
                    </div>
                    <div class="hist-what">
                      <div class="small"><b><?= e((string) $o['action_name']) ?></b>
                        <?php if ((int) $o['minutes_spent'] > 0): ?>
                          <span class="muted mono"><?= e(cm_minutes_label((int) $o['minutes_spent'])) ?></span>
                        <?php endif; ?>
                      </div>
                      <?php if (trim((string) ($o['note'] ?? '')) !== ''): ?>
                        <div class="hist-note hist-client">
                          <span class="hist-tag">on their receipt</span>
                          <span class="prewrap"><?= e(trim((string) $o['note'])) ?></span>
                        </div>
                      <?php endif; ?>
                      <?php if (trim((string) ($o['internal_note'] ?? '')) !== ''): ?>
                        <div class="hist-note hist-internal">
                          <span class="hist-tag">yours only</span>
                          <span class="prewrap"><?= e(trim((string) $o['internal_note'])) ?></span>
                        </div>
                      <?php endif; ?>
                      <?php if (trim((string) ($o['content_url'] ?? '')) !== ''): ?>
                        <a class="small mono" href="<?= e(cm_safe_href((string) $o['content_url'])) ?>"
                           target="_blank" rel="noopener noreferrer">the link</a>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</aside>
</div><?php  ?>
<?php cm_foot(); ?>
