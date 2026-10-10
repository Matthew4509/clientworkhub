<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0























declare(strict_types=1);




if (!defined('CM_ROOT')) { http_response_code(404); exit; }

















function cm_mail_hourly_choices(): array
{
    return [
        '10'  => '10 an hour — the cautious rate',
        '25'  => '25 an hour — the default',
        '50'  => '50 an hour',
        '100' => '100 an hour — only if your host allows it',
    ];
}

function cm_property_default_kind_choices(): array
{
    $choices = [
        'web' => 'Digital / web asset',
        'piano' => 'Piano',
        'salon' => 'Salon client / service profile',
        'general' => 'General property',
    ];
    try {
        foreach (cm_all('SELECT id,name FROM property_template ORDER BY name') as $r) {
            $choices['custom_' . (int) $r['id']] = (string) $r['name'];
        }
    } catch (Throwable $ex) {}
    return $choices;
}

function cm_register_visibility_choices(): array
{
    return ['show' => 'Show Cash register', 'hide' => 'Hide Cash register'];
}




















function cm_themes(): array
{
    return [
        'default' => [
            'label' => 'Default',
            'blurb' => 'Warm paper, one navy bar down the side and along the top, '
                     . 'and a terracotta button. Softer card corners.',
            'bar'   => '#1E3350',
        ],
        'basic' => [
            'label' => 'Basic',
            'blurb' => 'The white and deep-blue look this application has always had. '
                     . 'Nothing changes.',
            'bar'   => '#1D4ED8',
        ],
    ];
}

function cm_theme_choices(): array
{
    $out = [];
    foreach (cm_themes() as $k => $t) {
        $out[$k] = ['label' => $t['label'], 'blurb' => $t['blurb']];
    }
    return $out;
}








function cm_theme(): string
{
    static $theme = null;
    if ($theme !== null) return $theme;
    $t = 'default';
    try {
        $t = (string) cm_setting('ui.theme', 'default');
    } catch (Throwable $ex) {

    }
    $known = cm_themes();
    return $theme = isset($known[$t]) ? $t : 'default';
}






function cm_theme_attr(): string
{
    $t = cm_theme();
    return $t === 'basic' ? '' : ' data-theme="' . $t . '"';
}


function cm_theme_bar(): string
{
    return (string) (cm_themes()[cm_theme()]['bar'] ?? '#1D4ED8');
}

function cm_setting_spec(): array
{
    return [
        'property.default_kind' => [
            'group' => 'properties', 'label' => 'Default property type',
            'type' => 'choice', 'choices' => 'cm_property_default_kind_choices',
            'hint' => 'Preselects the most common kind when you add a property. You can change it in one click on any property.',
        ],






        'ui.theme' => [
            'group' => 'appearance', 'label' => 'Theme', 'type' => 'choice',
            'choices' => 'cm_theme_choices',
            'hint' => 'Saving applies it straight away, everywhere. Pick the other one and save '
                    . 'to change back — nothing else is affected, and nothing is stored differently.',
        ],
        'register.visibility' => [
            'group' => 'register', 'label' => 'Navigation', 'type' => 'choice',
            'choices' => 'cm_register_visibility_choices',
            'hint' => 'Hide it if this business never takes immediate counter sales. Existing receipts and refunds are kept.',
        ],

        'mail.from' => [
            'group' => 'mail', 'label' => 'From address', 'type' => 'text',
            'hint' => 'Must be an address ON THIS DOMAIN, or SPF fails and everything lands in spam.',
        ],
        'mail.from_name' => [
            'group' => 'mail', 'label' => 'From name', 'type' => 'text',




            'hint' => 'Who the email appears to be from. Clients read it, and it '
                    . 'signs your messages unless a business name is set.',
        ],
        'mail.reply_to' => [
            'group' => 'mail', 'label' => 'Reply-To', 'type' => 'text',
            'hint' => 'Where a human answers. Your real inbox.',
        ],






















        'mail.signature' => [
            'group' => 'mail', 'label' => 'Your sign-off', 'type' => 'textarea',



            'hint' => 'This is assigned to the merge code {{signature}}. When sending '
                    . 'emails to clients you can use {{signature}}, {{business.name}} '
                    . 'or even {{business.email}}.',
        ],
        'mail.enabled' => [
            'group' => 'mail', 'label' => 'Send email', 'type' => 'bool',
            'hint' => 'Off spools messages to storage/mail/ instead of sending them. '
                    . 'Two-step sign-in by email and password resets need this on.',
        ],




        'mail.auto_hourly' => [
            'group' => 'mail', 'label' => 'Automatic emails an hour',
            'type' => 'choice', 'choices' => 'cm_mail_hourly_choices',
            'hint' => 'Follow-up emails wait their turn once this many have left in the '
                    . 'last hour — whatever triggers the sending. Emails you send by hand '
                    . 'are never held. Shared hosts freeze a domain\'s mail over hourly '
                    . 'caps, and that costs more than a follow-up arriving late.',
        ],




























        'help.enabled' => [
            'group' => 'help', 'label' => 'Show the Help button', 'type' => 'bool',
            'hint' => 'Off by default while the walkthrough wording is being rewritten. '
                    . 'On, a Help button sits in the corner of every screen and explains '
                    . 'the one you are standing on.',
        ],

        'public.enabled' => [
            'group' => 'public', 'label' => 'Turn the client portal on', 'type' => 'bool',
            'hint' => 'Off, the front page redirects to sign-in and nothing else is reachable. '
                    . 'On, clients can reach the services you have marked Published — to enquire, '
                    . 'and to pay once a payment method is set up. Nothing else appears.',
        ],
        'public.business_name' => [
            'group' => 'public', 'label' => 'Business name', 'type' => 'text',
            'hint' => 'The heading on the portal. Falls back to your email From name.',
        ],
        'public.intro' => [
            'group' => 'public', 'label' => 'One line about what you do', 'type' => 'text',
            'hint' => 'Sits under the heading and becomes the page description a search engine '
                    . 'shows. One sentence.',
        ],
        'public.email' => [
            'group' => 'public', 'label' => 'Contact email shown on the portal', 'type' => 'text',
        ],
        'public.phone' => [
            'group' => 'public', 'label' => 'Contact phone shown on the portal', 'type' => 'text',
            'hint' => 'Both optional, and both appear in the footer of every portal page. '
                    . 'Leave empty to show neither.',
        ],

















        'onboard.signature' => [
            'group' => 'public', 'label' => 'Ask the client to sign the onboarding form', 'type' => 'bool',
            'hint' => 'A signature pad and a printed name at the end of every onboarding form. The '
                    . 'strokes are kept with their answers and shown on the reply. On unless you turn it off.',
        ],
        'public.no_link' => [
            'group' => 'public', 'label' => 'When somebody arrives without a service link',
            'type' => 'choice', 'choices' => 'cm_portal_arrival_modes',
            'hint' => 'Only affects the portal\'s own front page. A direct link to a '
                    . 'service always works, whichever of these is chosen.',
        ],




        'public.finder_heading' => [
            'group' => 'public', 'label' => 'Service finder: the question you ask',
            'type' => 'text',
            'hint' => 'Shown at the top of the finder and on the link to it. '
                    . 'Empty uses "Which service is right for me?".',
        ],
        'public.finder_intro' => [
            'group' => 'public', 'label' => 'Service finder: the line underneath',
            'type' => 'text',
            'hint' => 'Empty uses "Three quick questions and we will point you to the right '
                    . 'one." The finder only appears once an answer points at a published '
                    . 'service — write the questions on the Service finder screen.',
        ],
        'public.home_url' => [
            'group' => 'public', 'label' => 'Your main website',
            'type' => 'text',
            'hint' => 'Where "Send them to my main website" sends them — https://yoursite.com. '
                    . 'Needed only for that choice; if it is empty the portal lists your '
                    . 'services instead, because sending somebody to nowhere is worse than '
                    . 'showing them something.',
        ],





        'capacity.mode' => [
            'group' => 'capacity', 'label' => 'When the diary is full', 'type' => 'choice',



            'choices' => 'cm_capacity_modes',
            'hint' => 'Only the last one can refuse a booking. If the dates look wrong, the '
                    . 'usual cause is the time set on a Service, not this setting.',
        ],
        'capacity.overtime_minutes' => [
            'group' => 'capacity', 'label' => 'Overtime allowed each day (minutes)', 'type' => 'text',
            'hint' => 'Extra minutes a day may absorb on top of your hours before any limit '
                    . 'applies. A 9-to-5 that looks full can still fit eight half-hour jobs, '
                    . 'and that is a lot of money to decline over arithmetic.',
        ],
















        'capacity.overtime_extends_day' => [
            'group' => 'capacity', 'label' => 'Overtime makes the day longer', 'type' => 'bool',
            'hint' => 'On: your day runs to your finish time PLUS the allowance above — a '
                    . '9-to-5 with 4 hours allowed reads 9am to 9pm, and work is planned into '
                    . 'those hours before any date is pushed back. It does mean the plan will '
                    . 'put work in your evenings, and it marks it as overtime where it does. '
                    . 'Off: the allowance is only a cushion in the arithmetic.',
        ],




        'priority.order' => [
            'group' => 'priority', 'label' => 'Work comes to you in this order',
            'type' => 'order', 'choices' => 'cm_priority_factors',
            'hint' => 'Number them. Whatever is 1 is offered first, and the rest '
                    . 'follow — so a short Saturday does the important work first '
                    . 'without anything ever being refused.',
        ],






        'appt.kinds' => [
            'group' => 'appt', 'label' => 'Appointment description', 'type' => 'lines',
            'hint' => 'One per line. Add " | off" to one that is NOT work — the school '
                    . 'run, the dentist — and it will be kept out of the day\'s hours and '
                    . 'value automatically. Empty restores the four defaults.',
        ],
        'appt.repeat_weeks' => [
            'group' => 'appt', 'label' => 'How far ahead repeats are created', 'type' => 'choice',
            'choices' => 'cm_repeat_horizons',
            'hint' => 'A repeating appointment becomes real entries in the diary, one per '
                    . 'ticked day. Each can be moved or removed on its own.',
        ],
        'appt.buffer_before' => [
            'group' => 'appt', 'label' => 'Usual travel before (minutes)', 'type' => 'text',
        ],
        'appt.buffer_after' => [
            'group' => 'appt', 'label' => 'Usual travel after (minutes)', 'type' => 'text',
            'hint' => 'Prefilled on the add form so you do not retype them. Both optional, '
                    . 'and both editable on every appointment.',
        ],









        'business.legal_name' => [
            'group' => 'business', 'label' => 'Legal or trading name', 'type' => 'text',
            'hint' => 'The name on your invoices and receipts. May differ from the name on '
                    . 'your public page; falls back to it when empty.',
        ],
        'business.address' => [
            'group' => 'business', 'label' => 'Street address', 'type' => 'text',
        ],
        'business.suburb' => [
            'group' => 'business', 'label' => 'Suburb or town', 'type' => 'text',
        ],
        'business.state' => [
            'group' => 'business', 'label' => 'State or county', 'type' => 'text',
        ],
        'business.postcode' => [
            'group' => 'business', 'label' => 'Postcode or ZIP', 'type' => 'text',
        ],
        'business.country' => [
            'group' => 'business', 'label' => 'Country', 'type' => 'text',
            'hint' => 'Printed on the invoice, and nothing else. It does NOT set your tax '
                    . 'rules - those are yours to choose below, whatever country you are in.',
        ],





        'tax.mode' => [
            'group' => 'tax', 'label' => 'How your prices treat tax', 'type' => 'choice',
            'choices' => 'cm_tax_modes',
            'hint' => 'Whichever you pick applies to every price you have already typed, so '
                    . 'check your service list after changing it.',
        ],
        'tax.name' => [
            'group' => 'tax', 'label' => 'What your tax is called', 'type' => 'text',
            'hint' => 'GST, VAT, Sales tax - whatever your invoices should say. This is the '
                    . 'word your clients read on the total line.',
        ],
        'tax.rate' => [
            'group' => 'tax', 'label' => 'Rate (%)', 'type' => 'text',
            'hint' => 'A number, decimals allowed: 20, 10, 8.25. YOUR rate, not one we look '
                    . 'up - this application does not work out anyone\'s tax for them.',
        ],
        'tax.number_label' => [
            'group' => 'tax', 'label' => 'Registration number is called', 'type' => 'text',
            'hint' => 'ABN, VAT No., EIN. Printed in front of the number below.',
        ],
        'tax.number' => [
            'group' => 'tax', 'label' => 'Your registration number', 'type' => 'text',
            'hint' => 'Required on a tax invoice in most places, which is why the setup '
                    . 'checklist will not tick until this is filled in or you have said you '
                    . 'are not registered.',
        ],


        'invoice.currency' => [
            'group' => 'invoice', 'label' => 'Currency', 'type' => 'choice',
            'choices' => 'cm_currency_choices',
            'hint' => 'One currency per installation. It is written onto each invoice as it '
                    . 'is issued, so changing it later never rewrites an old document.',
        ],
        'invoice.terms_days' => [
            'group' => 'invoice', 'label' => 'Payment terms (days)', 'type' => 'text',
            'hint' => 'How long a client has to pay. Sets the due date, and stays editable on '
                    . 'any individual invoice. Empty or 0 means due on receipt.',
        ],





        'stripe.mode' => [
            'group' => 'payments', 'label' => 'Stripe — mode', 'type' => 'choice', 'choices' => 'cm_stripe_modes',
            'hint' => 'Match the keys below: test keys in Test, live keys in Live.',
        ],
        'stripe.publishable_key' => [
            'group' => 'payments', 'label' => 'Stripe — publishable key', 'type' => 'text',
            'hint' => 'pk_test_… or pk_live_…, from Stripe → Developers → API keys.',
        ],
        'stripe.secret_key' => [
            'group' => 'payments', 'label' => 'Stripe — secret key', 'type' => 'text', 'secret' => true,
            'hint' => 'sk_test_… or sk_live_…. Stored encrypted; never shown again.',
        ],
        'stripe.webhook_secret' => [
            'group' => 'payments', 'label' => 'Stripe — webhook signing secret', 'type' => 'text', 'secret' => true,
            'hint' => 'whsec_…, from the webhook endpoint you add in Stripe pointing at '
                    . 'portal/stripe-webhook.php on this site, for the event checkout.session.completed.',
        ],
        'paypal.mode' => [
            'group' => 'payments', 'label' => 'PayPal — mode', 'type' => 'choice', 'choices' => 'cm_paypal_modes',
            'hint' => 'Sandbox keys in Sandbox, live keys in Live.',
        ],
        'paypal.client_id' => [
            'group' => 'payments', 'label' => 'PayPal — client ID', 'type' => 'text',
            'hint' => 'From a REST app in the PayPal developer dashboard.',
        ],
        'paypal.client_secret' => [
            'group' => 'payments', 'label' => 'PayPal — secret', 'type' => 'text', 'secret' => true,
            'hint' => 'Stored encrypted; never shown again.',
        ],
        'paypal.webhook_id' => [
            'group' => 'payments', 'label' => 'PayPal — webhook ID', 'type' => 'text',
            'hint' => 'From the webhook you add in the PayPal dashboard pointing at portal/paypal-webhook.php '
                    . 'on this site, for CHECKOUT.ORDER.APPROVED and PAYMENT.CAPTURE.COMPLETED.',
        ],
        'invoice.enabled' => [
            'group' => 'invoice', 'label' => 'Invoicing', 'type' => 'bool',
            'hint' => 'On: Invoices in the menu and "Invoice this job" on every job. Off: both go, '
                    . 'and money is tracked against the job instead — for work a platform has '
                    . 'already invoiced, so the same money is not declared twice. Anything '
                    . 'already issued keeps its number either way.',
        ],
        'invoice.number_prefix' => [
            'group' => 'invoice', 'label' => 'Invoice number prefix', 'type' => 'text',
            'hint' => 'Numbers run PREFIX-YEAR-0001 and are allocated when an invoice is '
                    . 'issued, never when a draft is started - an abandoned draft must not '
                    . 'eat a number and leave a gap your accountant asks about.',
        ],
        'invoice.footer' => [
            'group' => 'invoice', 'label' => 'Footer line', 'type' => 'text',
            'hint' => 'One line at the bottom of every invoice. A thank-you, a late-payment '
                    . 'note, a licence number.',
        ],








        'bank.account_name' => [
            'group' => 'bank', 'label' => 'Account name', 'type' => 'text',
        ],
        'bank.code_label' => [
            'group' => 'bank', 'label' => 'Bank code is called', 'type' => 'text',
            'hint' => 'BSB, Sort code, Routing number, IBAN - whatever yours is called.',
        ],
        'bank.code' => [
            'group' => 'bank', 'label' => 'Bank code', 'type' => 'text',
        ],
        'bank.account_no' => [
            'group' => 'bank', 'label' => 'Account number', 'type' => 'text',
            'hint' => 'Nothing prints on an invoice unless the account name AND the number '
                    . 'are both set. Half a set of bank details is worse than none - it '
                    . 'produces a payment nobody can trace.',
        ],
    ];
}













function cm_setting_choices(array $meta): array
{
    $raw = $meta['choices'] ?? [];
    if (is_string($raw)) {
        $raw = function_exists($raw) ? $raw() : [];
    }
    $out = [];
    foreach ((array) $raw as $value => $row) {
        $out[(string) $value] = is_array($row)
            ? ['label' => (string) ($row['label'] ?? $value), 'blurb' => (string) ($row['blurb'] ?? '')]
            : ['label' => (string) $row, 'blurb' => ''];
    }
    return $out;
}























function cm_public_enabled_safe(): bool
{
    try {
        require_once CM_LIB . '/publicsite.php';
        return cm_public_enabled();
    } catch (Throwable $ex) {
        return false;
    }
}

function cm_general_groups(): array
{
    return [





    'appearance' => ['title' => 'Appearance',
                     'blurb' => 'How this application looks to you. It changes nothing about '
                              . 'what it does, and nothing your clients see in an email.'],
    'properties' => ['title' => 'Properties',
                     'blurb' => 'Choose the kind of property you work with most. Each kind shows only the details useful for that work.'],
    'register' => ['title' => 'Cash register',
                   'blurb' => 'An immediate paid sale and receipt, separate from formal invoicing.'],





    'workday' => ['title' => 'Your workday',
                  'blurb' => 'All optional. With days ticked, daily tasks land only on days you '
                           . 'work; with times set, the dashboard suggests what fits before your '
                           . 'next appointment, break, or the end of the day.'],

    'appt' => ['title' => 'Appointments',
               'blurb' => 'This allows you to add a recurring appointment to your schedule.'],








    'priority' => ['title' => 'What you do first',
                   'blurb' => 'The order work is offered in. Nothing is ever refused '
                            . 'because of it — a short day simply does the important '
                            . 'things first.'],
    'capacity' => ['title' => 'Workload',
                   'blurb' => 'Off by default, and that is the right setting for most people: '
                            . 'refusing paid work because an ESTIMATE says a day is full costs '
                            . 'real money to protect a guess. Turn it up only when you actually '
                            . 'want the diary to push back.'],

    'mail' => ['title' => 'Email',
               'blurb' => 'Where messages come from. Until "Send email" is on, everything the '
                        . 'application would send is spooled to storage/mail/ instead — open that '
                        . 'folder to read what would have gone out.'],







    'help' => ['title' => 'Help',
               'blurb' => 'The Help button in the bottom-right corner of every screen opens a '
                        . 'short walkthrough of the screen you are on.'],












    'public' => ['title' => 'Your client portal',
                 'parked' => !cm_public_enabled_safe(),
                 'blurb' => 'Off by default, and most installs leave it off: '
                          . 'many people take payment through Fiverr or '
                          . 'elsewhere and use this to run their day. Switched on, it is the '
                          . 'billing and signup side of a business whose real website is '
                          . 'somewhere else: your published services, a contact form, and a '
                          . 'booking form. There is deliberately no sign-in link on it.'],
    ];
}














function cm_billing_groups(): array
{
    return [
        'business' => ['title' => 'Your business',
                       'blurb' => 'Who you are on a financial document. Often not the name on '
                                . 'your shop window — both exist, and both are used.'],
        'tax' => ['title' => 'Tax',
                  'blurb' => 'One tax, named and rated by you, applied on top of your prices or '
                           . 'already inside them. Neither is tied to a country.'],
        'invoice' => ['title' => 'Invoices',
                      'blurb' => 'Currency, terms and numbering.'],
        'payments' => ['title' => 'Online payment',
                       'blurb' => 'Stripe (cards) and PayPal, on the client\'s copy of an issued invoice. '
                                . 'A rail is offered only once both of its keys are in.'],
        'bank' => ['title' => 'Bank Transfer',
                   'blurb' => 'Printed at the foot of an invoice so a client can pay without '
                            . 'asking how.'],
    ];
}





















function cm_billing_group_state(string $group): array
{



    require_once CM_LIB . '/tax.php';

    $s = static function (string $k): string {
        try { return trim((string) (cm_setting($k, '') ?? '')); }
        catch (Throwable $ex) { return ''; }
    };

    switch ($group) {
        case 'business':
            $name = $s('business.legal_name');
            $where = implode(' ', array_filter([$s('business.suburb'), $s('business.state'),
                                                $s('business.postcode')]));
            if ($name === '' && $where === '') {
                return ['tone' => 'unset', 'line' => 'Nothing set. Invoices will use the name from '
                      . 'your public page and carry no address.'];
            }
            if ($name === '') {
                return ['tone' => 'warn', 'line' => 'An address, but no legal name — invoices fall '
                      . 'back to the name on your public page.'];
            }
            return ['tone' => $where === '' ? 'warn' : 'ok',
                    'line' => 'Invoices go out as ' . $name
                            . ($where !== '' ? ', ' . $where . '.' : ', with no address on them.')];

        case 'tax':
            $mode = $s('tax.mode');
            if ($mode === '' || $mode === 'none') {
                return ['tone' => 'unset',
                        'line' => 'Not registered for tax, so no tax line appears on anything.'];
            }
            $name = $s('tax.name') !== '' ? $s('tax.name') : 'Tax';
            $rate = $s('tax.rate');
            $num  = $s('tax.number');
            $how  = $mode === 'inclusive' ? 'already inside your prices' : 'added on top of your prices';
            if ($rate === '' || (float) $rate <= 0) {
                return ['tone' => 'warn',
                        'line' => $name . ' is ' . $how . ' — but the rate is not set, so nothing '
                                . 'is being charged.'];
            }





            $rateLabel = str_contains($rate, '.') ? rtrim(rtrim($rate, '0'), '.') : $rate;
            $line = $name . ' at ' . $rateLabel . '%, ' . $how . '.';
            if ($num === '') {
                return ['tone' => 'warn',
                        'line' => $line . ' No registration number, which most places require on '
                                . 'a tax invoice.'];
            }
            return ['tone' => 'ok',
                    'line' => $line . ' ' . ($s('tax.number_label') ?: 'Number') . ' ' . $num . '.'];

        case 'invoice':
            $cur  = $s('invoice.currency');
            $curLabel = cm_currencies()[$cur]['label'] ?? '';
            $days = $s('invoice.terms_days');
            $terms = ($days === '' || (int) $days === 0)
                   ? 'due on receipt' : 'due in ' . (int) $days . ' days';
            $prefix = $s('invoice.number_prefix');
            if ($curLabel === '') {
                return ['tone' => 'unset',
                        'line' => 'No currency chosen yet, so invoices use Australian dollars until you pick one.'];
            }
            return ['tone' => 'ok',
                    'line' => $curLabel . ', ' . $terms . ', numbered '
                            . ($prefix !== '' ? $prefix . '-' : '') . date('Y') . '-0001.'];

        case 'bank':
            $acct = $s('bank.account_name');
            $no   = $s('bank.account_no');
            if ($acct === '' && $no === '') {
                return ['tone' => 'unset',
                        'line' => 'Nothing set, so an invoice does not tell a client how to pay '
                                . 'you by transfer.'];
            }



            if ($acct === '' || $no === '') {
                return ['tone' => 'warn',
                        'line' => 'Half set, so nothing will print. An invoice needs the account '
                                . 'name AND the number before it shows either.'];
            }
            $code = $s('bank.code');
            return ['tone' => 'ok',
                    'line' => 'Printed on every invoice: ' . $acct
                            . ($code !== '' ? ', ' . ($s('bank.code_label') ?: 'code') . ' ' . $code : '')
                            . ', account ' . $no . '.'];
    }
    return ['tone' => 'unset', 'line' => ''];
}







function cm_invoicing_enabled(): bool
{
    try { return (string) cm_setting('invoice.enabled', '1') !== '0'; }
    catch (Throwable $ex) { return true; }
}

function cm_setting_is_secret(string $key): bool
{
    return !empty(cm_setting_spec()[$key]['secret']);
}



function cm_settings_all(): array
{
    if (isset($GLOBALS['CM_SETTINGS_CACHE'])) {
        return $GLOBALS['CM_SETTINGS_CACHE'];
    }
    $rows = [];
    try {
        foreach (cm_all('SELECT skey, sval, is_secret FROM app_setting') as $r) {
            $rows[$r['skey']] = $r;
        }
    } catch (Throwable $ex) {

        $rows = [];
    }
    return $GLOBALS['CM_SETTINGS_CACHE'] = $rows;
}








function cm_setting(string $key, $default = null)
{
    $rows = cm_settings_all();
    if (isset($rows[$key])) {
        $raw = (string) $rows[$key]['sval'];
        $val = ((int) $rows[$key]['is_secret'] === 1) ? cm_decrypt($raw) : $raw;
        if ($val !== '' && $val !== null) return $val;
    }
    return cm_config($key, $default);
}


function cm_setting_present(string $key): bool
{
    $v = cm_setting($key, '');
    return is_string($v) ? trim($v) !== '' : !empty($v);
}


function cm_setting_source(string $key): string
{
    $rows = cm_settings_all();
    if (isset($rows[$key])) {
        $raw = (string) $rows[$key]['sval'];
        $val = ((int) $rows[$key]['is_secret'] === 1) ? cm_decrypt($raw) : $raw;
        if ($val !== '' && $val !== null) return 'db';
    }
    $cfg = cm_config($key, '');
    return (is_string($cfg) ? trim($cfg) !== '' : !empty($cfg)) ? 'config' : 'unset';
}


function cm_setting_set(string $key, string $value, string $actor = ''): void
{
    $secret = cm_setting_is_secret($key);
    $store  = $secret && $value !== '' ? cm_encrypt($value) : $value;

    cm_q(
        'INSERT INTO app_setting (skey, sval, is_secret, updated_at, updated_by)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE sval = VALUES(sval), is_secret = VALUES(is_secret),
                                 updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)',
        [$key, $store, $secret ? 1 : 0, cm_now(), mb_substr($actor, 0, 190)]
    );
    cm_settings_forget();
}

function cm_setting_clear(string $key): void
{
    cm_q('DELETE FROM app_setting WHERE skey = ?', [$key]);
    cm_settings_forget();
}


function cm_settings_forget(): void
{
    unset($GLOBALS['CM_SETTINGS_CACHE']);
}















function cm_crypt_available(): bool
{
    return function_exists('openssl_encrypt') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true);
}

















function cm_app_key_source(): array
{
    $configured = trim((string) cm_config('secret_key', ''));
    if ($configured !== '') {
        return ['where' => 'config', 'value' => $configured];
    }





    $file = CM_ROOT . '/storage/appkey.php';
    if (is_file($file)) {
        $stored = @include $file;
        if (is_string($stored) && strlen(trim($stored)) >= 32) {
            return ['where' => 'file', 'value' => trim($stored)];
        }
    }
    return ['where' => 'none', 'value' => ''];
}



function cm_app_key_from(string $source): string
{
    return hash('sha256', 'cm-settings-v1|' . $source, true);
}











function cm_storage_seal(): void
{
    $dir = CM_ROOT . '/storage';
    if (!is_dir($dir)) @mkdir($dir, 0770, true);
    if (!is_dir($dir)) return;
    $deny = $dir . '/.htaccess';
    if (!is_file($deny)) {
        @file_put_contents($deny, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }


    if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');


    foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $sub) {
        if (!is_file($sub . '/index.html')) @file_put_contents($sub . '/index.html', '');
    }
}

function cm_app_key(): string
{
    static $key = null;
    if ($key !== null) return $key;

    $have = cm_app_key_source();
    if ($have['where'] !== 'none') {
        return $key = cm_app_key_from($have['value']);
    }

    $file = CM_ROOT . '/storage/appkey.php';
    cm_storage_seal();

    $new = bin2hex(random_bytes(32));
    if (@file_put_contents($file, "<?php return '" . $new . "';\n") === false) {


        throw new RuntimeException('Cannot create storage/appkey.php — set secret_key in config.php instead.');
    }
    @chmod($file, 0600);
    return $key = cm_app_key_from($new);
}

function cm_encrypt(string $plain): string
{
    if (!cm_crypt_available()) {
        throw new RuntimeException('openssl is not available — cannot store secrets safely.');
    }
    $iv  = random_bytes(12);
    $tag = '';
    $ct  = openssl_encrypt($plain, 'aes-256-gcm', cm_app_key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($ct === false) throw new RuntimeException('Encryption failed.');
    return 'v1:' . base64_encode($iv . $tag . $ct);
}

function cm_decrypt(string $stored): string
{
    if ($stored === '') return '';
    if (strncmp($stored, 'v1:', 3) !== 0) {

        return $stored;
    }
    if (!cm_crypt_available()) return '';

    $raw = base64_decode(substr($stored, 3), true);
    if ($raw === false || strlen($raw) < 29) return '';

    $iv  = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ct  = substr($raw, 28);
    $out = openssl_decrypt($ct, 'aes-256-gcm', cm_app_key(), OPENSSL_RAW_DATA, $iv, $tag);

    if ($out === false) {

        error_log('cm_decrypt: a stored secret could not be decrypted (key changed?)');
        return '';
    }
    return $out;
}
