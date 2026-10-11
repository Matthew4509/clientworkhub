<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0


























declare(strict_types=1);

if (!defined('CM_ROOT') && !defined('CM_REQ_CALLER')) { http_response_code(404); exit; }



const CM_TERMS_VERSION = '2';




const CM_TERMS_VERSION_HOME = 'home-1';


function cm_terms_version(bool $home = false): string
{
    return $home ? CM_TERMS_VERSION_HOME : CM_TERMS_VERSION;
}


function cm_terms_title(bool $home = false): string
{
    return $home ? 'Before you start — please read this' : 'Before you install — please read this';
}









function cm_terms_sections(bool $home = false): array
{
    if ($home) return cm_terms_sections_home();
    return [
        [
            'heading' => 'It is yours to run, and free',
            'body' => 'Client Work Hub is free to use. Your licence covers every '
                    . '0.3.x version — installing it once entitles you to all of them, '
                    . 'for as long as you want to run it, at no cost and with nothing '
                    . 'to renew.',
        ],
        [
            'heading' => 'Free to use, not to resell',
            'body' => 'It is licensed under the PolyForm Shield License 1.0.0 (the LICENSE '
                    . 'file that comes with it). You may run your own business on it, change '
                    . 'it and share copies of it. You may not sell it, repackage it as your '
                    . 'own product, or offer it as a paid or competing service.',
        ],
        [
            'heading' => 'It comes with no warranty of any kind',
            'body' => 'The software is provided as it is. There is no guarantee that it '
                    . 'is free of faults, that it will suit your business, or that it '
                    . 'will keep working after a change to your hosting. Nobody is '
                    . 'liable to you for lost data, lost time, lost income, or any other '
                    . 'loss arising from using it — you install it at your own risk.',
        ],
        [
            'heading' => 'Your backups are your responsibility',
            'body' => 'Admin gives you a one-press database backup and asks for one '
                    . 'before every update, but it will never take one for you and it '
                    . 'does not store copies anywhere. If the only copy of your data is '
                    . 'on this server, you have no backup. Take one regularly and keep '
                    . 'it somewhere else.',
        ],
        [
            'heading' => 'The server, and everything on it, is yours',
            'body' => 'This runs entirely on hosting you control. Nothing about your '
                    . 'clients, your work or this installation is sent anywhere — there '
                    . 'is no telemetry, no account with us, and no remote service it '
                    . 'depends on. That also means the security of the server, the '
                    . 'hosting account and its passwords is yours to get right, and '
                    . 'nobody else can recover it for you if it is lost.',
        ],
        [
            'heading' => 'You are responsible for your clients\' data',
            'body' => 'This application stores real personal information, and it can '
                    . 'store website and account passwords belonging to your clients. '
                    . 'Credentials are encrypted at rest and never appear in an email or '
                    . 'a search result, but you are the one accountable to your clients '
                    . 'and to data-protection law for what you keep, how long you keep '
                    . 'it, and who you let near it.',
        ],
        [
            'heading' => 'Email and payments go through other people',
            'body' => 'Email leaves through your own hosting\'s mail server, and card or '
                    . 'PayPal payments — if you switch them on — go through Stripe or '
                    . 'PayPal under your own account and their terms. What those '
                    . 'services do, charge, or refuse is between you and them.',
        ],
    ];
}









function cm_terms_sections_home(): array
{
    $web = cm_terms_sections(false);
    return [
        $web[0],
        $web[1],
        [
            'heading' => 'It comes with no warranty of any kind',
            'body' => 'The software is provided as it is. There is no guarantee that it '
                    . 'is free of faults, that it will suit your business, or that it '
                    . 'will keep working after a change to your PC or to Windows. Nobody is '
                    . 'liable to you for lost data, lost time, lost income, or any other '
                    . 'loss arising from using it — you install it at your own risk.',
        ],
        [
            'heading' => 'Your backups are your responsibility',
            'body' => 'Settings gives you a one-press database backup and asks for one '
                    . 'before every update, but it will never take one for you and it '
                    . 'does not store copies anywhere. If the only copy of your data is '
                    . 'on this PC, you have no backup. Take one regularly and keep '
                    . 'it somewhere else.',
        ],
        [
            'heading' => 'This PC, and everything on it, is yours',
            'body' => 'This runs entirely on this PC. Nothing about your clients, your '
                    . 'work or this installation is sent anywhere — there is no '
                    . 'telemetry, no account with us, and no remote service it depends '
                    . 'on. It goes online only when you press Check for updates or '
                    . 'download one, and then only to GitHub. That also means the '
                    . 'security of this PC, your Windows account and its passwords is '
                    . 'yours to get right, and nobody else can recover it for you if it '
                    . 'is lost.',
        ],
        $web[5],
        [
            'heading' => 'Sending is up to you',
            'body' => 'The home edition sends no email and takes no online payments. '
                    . 'Invoices and summaries are PDFs you save and send yourself, by '
                    . 'whatever means you choose.',
        ],
    ];
}


function cm_terms_confirm_label(): string
{
    return 'I have read the above. I understand Client Work Hub comes with no '
         . 'warranty and that nobody is liable for any loss arising from using it, '
         . 'and I am responsible for my own backups and for my clients\' data.';
}


function cm_terms_text(bool $home = false): string
{
    $out = cm_terms_title($home) . "\n" . str_repeat('=', strlen(cm_terms_title($home))) . "\n\n";
    foreach (cm_terms_sections($home) as $s) {
        $out .= $s['heading'] . "\n" . wordwrap($s['body'], 72) . "\n\n";
    }
    return $out . wordwrap(cm_terms_confirm_label(), 72) . "\n";
}
