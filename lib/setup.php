<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0



















declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }




function cm_setup_steps(): array
{
    $setting = static function (string $key): string {
        try { return trim((string) (cm_setting($key, '') ?? '')); }
        catch (Throwable $ex) { return ''; }
    };
    $count = static function (string $sql, array $args = []): int {
        try { return (int) cm_val($sql, $args); }
        catch (Throwable $ex) { return 0; }
    };

    $admin = cm_current_user();
    $tfaOn = false;
    try {
        require_once CM_LIB . '/tfa.php';
        $tfaOn = $admin !== null && cm_tfa_on($admin);
    } catch (Throwable $ex) {

    }

    $installerGone = true;
    try {
        require_once CM_LIB . '/health.php';
        $installerGone = !cm_installer_present();
    } catch (Throwable $ex) {

    }

    $steps = [

        'sender' => [
            'label' => 'Who your emails come from',
            'blurb' => 'The name and address on every summary you send a client. '
                     . 'Until you set it they arrive from "ClientWorkHub", which is nobody.',
            'href' => 'admin/settings.php',
            'critical' => true,


            'done' => $setting('mail.from') !== '' && $setting('mail.from_name') !== ''
                      && strcasecmp(str_replace(' ', '', $setting('mail.from_name')), 'ClientWorkHub') !== 0,
        ],
        'installer' => [
            'label' => 'Remove install.php',
            'blurb' => 'It deletes itself after a normal install. If it is still on the '
                     . 'server, take it off before anyone finds it.',
            'href' => 'admin/update.php',
            'critical' => true,
            'done' => $installerGone,
        ],




















        'services' => [
            'label' => 'Your services',
            'blurb' => 'The standard jobs you sell — the price, how long they run and '
                     . 'how often. A new job is stamped from one and arrives with all '
                     . 'of it, already on your calendar, so a service that says nothing '
                     . 'about its time makes a job with no days in it.',
            'href' => 'admin/services.php',
            'critical' => true,
            'done' => $count('SELECT COUNT(*) FROM job_template
                               WHERE duration_days > 0 OR per_day_minutes > 0
                                  OR total_minutes > 0') > 0,
        ],
        'invoicing' => [
            'label' => 'How you invoice',
            'blurb' => 'Your business name and address, the currency, and whether your '
                     . 'prices include tax or have it added. Invoices cannot be raised '
                     . 'until you have said.',
            'href' => 'admin/billing-settings.php',
            'critical' => true,







            'done' => (static function () use ($setting): bool {
                try {
                    if (!cm_setting_present('tax.mode')) return false;
                } catch (Throwable $ex) { return false; }
                if ($setting('business.legal_name') === ''
                    && $setting('public.business_name') === '') return false;
                if ($setting('tax.mode') === 'none') return true;

                return $setting('tax.rate') !== '' && $setting('tax.number') !== '';
            })(),
        ],
        'client' => [
            'label' => 'Your first client',
            'blurb' => 'A name is enough to start. Everything else can be filled in later.',
            'href' => 'admin/clients.php',
            'critical' => true,
            'done' => $count("SELECT COUNT(*) FROM client WHERE status <> 'inactive'") > 0,
        ],


        'workday' => [
            'label' => 'Your workday',
            'blurb' => 'The days and hours you work. With them set, daily tasks skip your '
                     . 'days off and the dashboard knows how much day is left.',
            'href' => 'admin/settings.php',
            'critical' => false,


            'done' => (static function () use ($setting): bool {
                try {
                    require_once CM_LIB . '/workday.php';
                    if (cm_workday_schedules()) return true;
                } catch (Throwable $ex) {  }
                return $setting('workday.days') !== '' || $setting('workday.start') !== '';
            })(),
        ],
        'snippets' => [
            'label' => 'Your snippets',
            'blurb' => 'The sentence you type most — "Updated the Facebook page". '
                     . 'Written once, then one tap on every task.',
            'href' => 'admin/services.php?tab=snippets',
            'critical' => false,
            'done' => $count('SELECT COUNT(*) FROM work_snippet WHERE archived = 0') > 0,
        ],
        'emails' => [
            'label' => 'Your email templates',
            'blurb' => 'Your sign-off, and the wording of the messages that go out '
                     . 'automatically. Written once, used everywhere.',
            'href' => 'admin/email-templates.php',
            'critical' => false,
            'done' => $setting('mail.signature') !== '',
        ],
        'twostep' => [
            'label' => 'Two-step sign-in',
            'blurb' => 'This account can read every password you have stored. A second '
                     . 'factor is the difference between a leaked password and a leaked vault.',
            'href' => 'account/security.php',
            'critical' => false,
            'done' => $tfaOn,
        ],
    ];



    if (cm_home()) unset($steps['sender'], $steps['emails'], $steps['installer']);
    return $steps;
}


function cm_setup_progress(): array
{
    $steps = cm_setup_steps();
    $done  = 0;
    $criticalMissing = 0;
    foreach ($steps as $s) {
        if ($s['done']) $done++;
        elseif ($s['critical']) $criticalMissing++;
    }
    $total = count($steps);
    return [
        'done' => $done,
        'total' => $total,
        'pct' => $total > 0 ? (int) round($done / $total * 100) : 100,
        'critical_missing' => $criticalMissing,
    ];
}
