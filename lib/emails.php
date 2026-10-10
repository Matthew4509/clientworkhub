<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0


































declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }

const CM_DEFAULT_SIGNATURE = "Kind regards,\n{{business.name}}";











function cm_email_defaults(): array
{
    return [
        'job_summary' => [
            'label'  => 'Work summary for a client',
            'when'   => 'To send this to a client, review the client job summary sheet first.',
            'locked' => false,
            'tokens' => [],
            'subject' => 'Work summary — {{job.name}}',
            'body' => "Hello {{client.first_name}},\n\n"
                    . "Here is a summary of the work completed on {{job.name}}.\n\n"
                    . "{{summary}}\n\n"
                    . "Any questions at all, just reply to this email. If you would like something "
                    . "different next time, or the same again, everything is here:\n\n"
                    . "{{my_link}}\n\n"
                    . "{{signature}}",
        ],

        'client_followup' => [
            'label'  => 'Follow-up to a client',
            'when'   => 'This is only sent if you choose to send it manually, or choose '
                      . 'to schedule a follow up with a client.',
            'locked' => false,
            'tokens' => [],
            'subject' => 'Following up — {{job.name}}',
            'body' => "Hello {{client.first_name}},\n\n"
                    . "It has been a little while since we finished {{job.name}}, and I wanted to "
                    . "check how everything has been going.\n\n"
                    . "If anything was not quite right, or you would like something different next "
                    . "time, you can tell us here — and book the same again in one click if you "
                    . "are ready:\n\n"
                    . "{{my_link}}\n\n"
                    . "Or just reply to this email and I will sort it out.\n\n"
                    . "{{signature}}",
        ],

        'invoice_issued' => [
            'label'  => 'Invoice to a client',
            'when'   => 'When you email an issued invoice from its page. The link opens their copy, '
                      . 'with the PDF and any pay-online buttons you have switched on.',
            'locked' => false,
            'tokens' => [],
            'subject' => 'Invoice {{invoice.number}} from {{business.name}}',
            'body' => "Hello {{client.first_name}},\n\n"
                    . "Please find invoice {{invoice.number}} for {{invoice.total}}"
                    . "{{invoice.due_line}}.\n\n"
                    . "You can view it, download a PDF, and see how to pay here:\n\n"
                    . "{{invoice.link}}\n\n"
                    . "Thank you.\n\n"
                    . "{{signature}}",
        ],

        'invoice_paid' => [
            'label'  => 'Receipt to a client',
            'when'   => 'When you email a paid invoice from its page — it is a receipt by then.',
            'locked' => false,
            'tokens' => [],
            'subject' => 'Receipt for {{invoice.number}} — thank you',
            'body' => "Hello {{client.first_name}},\n\n"
                    . "Thank you — {{invoice.number}} for {{invoice.total}} is paid in full. "
                    . "Your receipt is here:\n\n"
                    . "{{invoice.link}}\n\n"
                    . "{{signature}}",
        ],

        'password_reset' => [
            'label'  => 'Password reset',
            'when'   => 'When you ask for a reset link on the sign-in screen.',
            'locked' => true,
            'tokens' => ['{{reset_link}}'],
            'subject' => 'Reset your Client Work Hub password',
            'body' => "Somebody asked to reset the password on your Client Work Hub account.\n\n"
                    . "{{reset_link}}\n\n"
                    . "The link stops working in one hour, and using it signs out every other device.\n\n"
                    . "If this was not you, you can ignore this email — nothing has changed, and "
                    . "your password still works.",
        ],

        'signin_code' => [
            'label'  => 'Two-step sign-in code',
            'when'   => 'When two-step sign-in is set to email and you sign in.',
            'locked' => true,
            'tokens' => ['{{code}}'],
            'subject' => 'Your Client Work Hub sign-in code',
            'body' => "Your sign-in code is {{code}}\n\n"
                    . "It lasts ten minutes and can be used once.\n\n"
                    . "If you are not signing in right now, somebody has your password. "
                    . "Change it as soon as you can.",
        ],
    ];
}


function cm_emails_seed(): void
{


    static $adopted = false;
    if (!$adopted) {
        $adopted = true;
        try { cm_emails_adopt(); } catch (Throwable $ex) {  }
    }

    foreach (cm_email_defaults() as $key => $d) {
        $exists = cm_val('SELECT ekey FROM email_template WHERE ekey = ?', [$key]);
        if ($exists === null || $exists === false) {
            cm_q('INSERT INTO email_template (ekey, subject, body, locked, updated_at, updated_by)
                  VALUES (?, ?, ?, ?, ?, ?)',
                 [$key, $d['subject'], $d['body'], $d['locked'] ? 1 : 0, cm_now(), '']);
        }
    }
}








function cm_email_is_custom_key(string $key): bool
{
    return (bool) preg_match('/^custom_\d+$/', $key);
}

function cm_email_custom_rows(): array
{
    try {
        return cm_all('SELECT * FROM email_template WHERE custom = 1 ORDER BY sort, ekey');
    } catch (Throwable $ex) {
        return [];
    }
}


function cm_email_custom_add(string $label, string $subject, string $body, string $actor, ?string &$why = null): string
{
    $label = trim($label); $subject = trim($subject); $body = trim($body);
    if ($label === '')                 { $why = 'Give the message a name — it is how you will pick it from the list.'; return ''; }
    if (mb_strlen($label) > 120)       { $why = 'That name is too long (120 characters maximum).'; return ''; }
    if ($subject === '')               { $why = 'Give the message a subject line.'; return ''; }
    if ($body === '')                  { $why = 'The message has no text.'; return ''; }
    try {
        cm_emails_seed();
        $n = (int) cm_val("SELECT COALESCE(MAX(CAST(SUBSTRING(ekey, 8) AS UNSIGNED)), 0) FROM email_template WHERE ekey LIKE 'custom\\_%'") + 1;
        $key = 'custom_' . $n;
        $sort = (int) cm_val('SELECT COALESCE(MAX(sort), 0) FROM email_template') + 1;
        cm_q('INSERT INTO email_template (ekey, label, subject, body, locked, custom, sort, updated_at, updated_by)
              VALUES (?, ?, ?, ?, 0, 1, ?, ?, ?)',
             [$key, mb_substr($label, 0, 120), mb_substr($subject, 0, 200), $body, $sort, cm_now(), mb_substr($actor, 0, 190)]);
    } catch (Throwable $ex) {
        $why = 'The database needs updating before a message can be added (Admin → Update).';
        return '';
    }
    cm_audit('email_template.added', '', null, 'message "' . $label . '" (' . $key . ') added by ' . $actor);
    return $key;
}

function cm_email_custom_delete(string $key, string $actor): bool
{
    if (!cm_email_is_custom_key($key)) return false;
    $row = cm_row('SELECT ekey, label FROM email_template WHERE ekey = ? AND custom = 1', [$key]);
    if (!$row) return false;
    cm_q('DELETE FROM email_template WHERE ekey = ? AND custom = 1', [$key]);
    cm_audit('email_template.deleted', '', null, 'message "' . (string) $row['label'] . '" (' . $key . ') deleted by ' . $actor);
    return true;
}


function cm_email_template(string $key): ?array
{
    if (cm_email_is_custom_key($key)) {
        $row = null;
        try { $row = cm_row('SELECT * FROM email_template WHERE ekey = ? AND custom = 1', [$key]); }
        catch (Throwable $ex) {}
        if (!$row) return null;
        return [
            'ekey' => $key, 'label' => (string) $row['label'],
            'when' => 'Only when you choose it from a client\'s page — Communication → Send an email.',
            'locked' => false, 'custom' => true, 'tokens' => [],
            'subject' => (string) $row['subject'], 'body' => (string) $row['body'],
            'edited' => false, 'updated_at' => $row['updated_at'] ?? null, 'updated_by' => $row['updated_by'] ?? '',
        ];
    }
    $defaults = cm_email_defaults();
    if (!isset($defaults[$key])) return null;

    $row = null;
    try {
        cm_emails_seed();
        $row = cm_row('SELECT * FROM email_template WHERE ekey = ?', [$key]);
    } catch (Throwable $ex) {

    }
    $d = $defaults[$key];
    return [
        'ekey'    => $key,
        'label'   => $d['label'],
        'when'    => $d['when'],
        'locked'  => $d['locked'],
        'tokens'  => $d['tokens'],
        'subject' => $row['subject'] ?? $d['subject'],
        'body'    => $row['body'] ?? $d['body'],
        'edited'  => $row !== null && ((string) ($row['subject'] ?? '') !== $d['subject']
                                    || (string) ($row['body'] ?? '') !== $d['body']),
        'updated_at' => $row['updated_at'] ?? null,
        'updated_by' => $row['updated_by'] ?? '',
        'custom'  => false,
    ];
}


















function cm_email_templates(): array
{
    $order = [];
    try {
        foreach (cm_all('SELECT ekey, sort FROM email_template') as $r) {
            $order[(string) $r['ekey']] = (int) $r['sort'];
        }
    } catch (Throwable $ex) {

    }

    $keys = array_keys(cm_email_defaults());
    if ($order) {
        usort($keys, static function (string $a, string $b) use ($order, $keys): int {
            $sa = $order[$a] ?? 0; $sb = $order[$b] ?? 0;
            if ($sa !== $sb) return $sa <=> $sb;


            return array_search($a, $keys, true) <=> array_search($b, $keys, true);
        });
    }

    $out = [];
    foreach ($keys as $key) {
        $t = cm_email_template($key);
        if ($t !== null) $out[$key] = $t;
    }



    foreach (cm_email_custom_rows() as $row) {
        $t = cm_email_template((string) $row['ekey']);
        if ($t !== null) $out[(string) $row['ekey']] = $t;
    }
    return $out;
}








function cm_email_move(string $key, string $dir): bool
{
    if ($dir !== 'up' && $dir !== 'down') return false;
    $all = cm_email_templates();
    if (!isset($all[$key])) return false;

    $locked = (bool) $all[$key]['locked'];
    $group  = [];
    foreach ($all as $k => $t) if ((bool) $t['locked'] === $locked) $group[] = $k;

    $at = array_search($key, $group, true);
    if ($at === false) return false;
    $to = $dir === 'up' ? $at - 1 : $at + 1;
    if ($to < 0 || $to >= count($group)) return false;




    foreach ($group as $i => $k) {
        cm_q('UPDATE email_template SET sort = ? WHERE ekey = ?', [($i + 1) * 10, $k]);
    }
    cm_q('UPDATE email_template SET sort = ? WHERE ekey = ?', [($to + 1) * 10, $key]);
    cm_q('UPDATE email_template SET sort = ? WHERE ekey = ?', [($at + 1) * 10, $group[$to]]);
    return true;
}






function cm_email_save(string $key, string $subject, string $body, string $actor): string
{
    $t = cm_email_template($key);
    if ($t === null) return 'There is no message by that name.';
    if ($t['locked']) {
        return 'That one is a security message and its wording is fixed — see the note beside it.';
    }
    $subject = trim($subject);
    $body    = trim($body);
    if ($subject === '') return 'Give the message a subject line.';
    if ($body === '')    return 'The message has no text.';

    foreach ($t['tokens'] as $tok) {
        if (!str_contains($body, $tok)) {
            return 'The message must still contain ' . $tok . ' — without it, it does not work.';
        }
    }

    cm_emails_seed();
    cm_q('UPDATE email_template SET subject = ?, body = ?, updated_at = ?, updated_by = ? WHERE ekey = ?',
         [$subject, $body, cm_now(), $actor, $key]);
    cm_audit('email_template.changed', '', null, 'message "' . $key . '" reworded by ' . $actor);
    return '';
}


function cm_email_reset(string $key, string $actor): void
{
    if (cm_email_is_custom_key($key)) return;
    $d = cm_email_defaults()[$key] ?? null;
    if ($d === null) return;
    cm_emails_seed();
    cm_q('UPDATE email_template SET subject = ?, body = ?, updated_at = ?, updated_by = ? WHERE ekey = ?',
         [$d['subject'], $d['body'], cm_now(), $actor, $key]);
    cm_audit('email_template.reset', '', null, 'message "' . $key . '" put back to the original by ' . $actor);
}

function cm_email_signature(): string
{
    try {
        $s = trim((string) (cm_setting('mail.signature', '') ?? ''));
        return $s !== '' ? $s : CM_DEFAULT_SIGNATURE;
    } catch (Throwable $ex) {
        return CM_DEFAULT_SIGNATURE;
    }
}








function cm_email_render(string $text, array $context = []): string
{
    $codes = [















        'business.name'      => cm_business_display_name(),
        'business.email'     => cm_mail_reply_to() !== '' ? cm_mail_reply_to() : cm_mail_from(),
        'client.name'        => (string) ($context['client_name'] ?? ''),
        'client.first_name'  => (string) ($context['client_first_name'] ?? ($context['client_name'] ?? '')),
        'job.name'           => (string) ($context['job_name'] ?? ''),
        'summary'            => (string) ($context['summary'] ?? ''),
        'reset_link'         => (string) ($context['reset_link'] ?? ''),



        'my_link'            => (string) ($context['my_link'] ?? ''),
        'code'               => (string) ($context['code'] ?? ''),


        'invoice.number'     => (string) ($context['invoice_number'] ?? ''),
        'invoice.total'      => (string) ($context['invoice_total'] ?? ''),
        'invoice.due'        => (string) ($context['invoice_due'] ?? ''),
        'invoice.due_line'   => (string) ($context['invoice_due'] ?? '') !== '' ? ', due by ' . (string) $context['invoice_due'] : '',
        'invoice.link'       => (string) ($context['invoice_link'] ?? ''),
    ];



    $text = str_replace('{{signature}}', cm_email_signature(), $text);

    $out = preg_replace_callback('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i',
        static fn(array $m): string => (string) ($codes[strtolower($m[1])] ?? ''), $text);



    return trim((string) preg_replace("/\n{3,}/", "\n\n", (string) $out));
}


function cm_email_compose(string $key, array $context = []): array
{
    $t = cm_email_template($key);
    if ($t === null) return ['subject' => '', 'body' => ''];

    $body = (string) $t['body'];










    return [
        'subject' => cm_email_render((string) $t['subject'], $context),
        'body'    => cm_email_render($body, $context),
    ];
}


function cm_email_codes(): array
{
    return [
        '{{client.first_name}}' => 'the client\'s first name',
        '{{client.name}}'       => 'their full name, or the company',
        '{{job.name}}'          => 'the job this message is about',
        '{{business.name}}'     => 'your From name (Settings → Email)',
        '{{business.email}}'    => 'your reply-to address',
        '{{summary}}'           => 'the generated work summary (that message only)',
        '{{invoice.number}}'    => 'the invoice number (invoice messages only)',
        '{{invoice.total}}'     => 'its total, in its currency (invoice messages only)',
        '{{invoice.link}}'      => 'their copy of the invoice, with the PDF and pay buttons (invoice messages only)',
        '{{my_link}}'           => 'their own page — feedback, a tailored quote, or book again',
        '{{signature}}'         => 'your sign-off, if you want it somewhere other than the end',
    ];
}
























function cm_email_prior_bodies(): array
{
    return [




        'job_summary' => [
            "Hello {{client.first_name}},\n\n"
            . "Here is a summary of the work completed on {{job.name}}.\n\n"
            . "{{summary}}\n\n"
            . "Any questions at all, just reply to this email.\n\n"
            . "Kind regards,\n{{business.name}}",





            "Hello {{client.first_name}},\n\n"
            . "Here is a summary of the work completed on {{job.name}}.\n\n"
            . "{{summary}}\n\n"
            . "Any questions at all, just reply to this email. If you would like something "
            . "different next time, or the same again, everything is here:\n\n"
            . "{{my_link}}\n\n"
            . "Kind regards,\n{{business.name}}",
        ],
        'client_followup' => [
            "Hello {{client.first_name}},\n\n"
            . "It has been a little while since we finished {{job.name}}, and I wanted to "
            . "check how everything has been going.\n\n"
            . "If there is anything else you would like a hand with, or anything from the "
            . "last job you would like looked at again, just reply to this email and I will "
            . "sort it out.\n\n"
            . "Kind regards,\n{{business.name}}",















            "Hello {{client.first_name}},\n\n"
            . "It has been a little while since we finished {{job.name}}, and I wanted to "
            . "check how everything has been going.\n\n"
            . "If anything was not quite right, or you would like something different next "
            . "time, you can tell us here — and book the same again in one click if you "
            . "are ready:\n\n"
            . "{{my_link}}\n\n"
            . "Or just reply to this email and I will sort it out.\n\n"
            . "Kind regards,\n{{business.name}}",
        ],
    ];
}










function cm_emails_adopt(): int
{
    $defaults = cm_email_defaults();
    $priors   = cm_email_prior_bodies();
    $moved = 0;

    foreach ($priors as $key => $olds) {
        if (!isset($defaults[$key])) continue;
        $row = cm_row('SELECT subject, body FROM email_template WHERE ekey = ?', [$key]);
        if ($row === null) continue;
        $body = (string) $row['body'];
        if ($body === $defaults[$key]['body']) continue;

        foreach ($olds as $old) {
            if ($body === $old) {
                cm_q('UPDATE email_template SET subject = ?, body = ?, updated_at = ? WHERE ekey = ?',
                     [$defaults[$key]['subject'], $defaults[$key]['body'], cm_now(), $key]);
                $moved++;
                break;
            }
        }
    }
    return $moved;
}
