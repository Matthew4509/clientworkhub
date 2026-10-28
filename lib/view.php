<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0








declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }




require_once CM_LIB . '/migrate.php';


















function cm_head(string $title, string $description = '', bool $noindex = true): void
{
    cm_send_security_headers();
    $u = cm_current_user();
    $base = cm_base_url();


    $v = '?v=' . rawurlencode(cm_asset_stamp());
    ?><!DOCTYPE html>
<html lang="en"<?= cm_theme_attr() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — Client Work Hub</title>
<?php if ($description !== ''): ?>
<meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<?php 
 ?>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e($base) ?>/favicon.svg" type="image/svg+xml">
<meta name="theme-color" content="<?= e(cm_theme_bar()) ?>">
<link rel="stylesheet" href="<?= e($base) ?>/css/site.css<?= e($v) ?>">
<?php 

 ?>
<script defer src="<?= e($base) ?>/js/confirm-submit.js<?= e($v) ?>"></script>
<script defer src="<?= e($base) ?>/js/upload-limit.js<?= e($v) ?>"></script>
<script defer src="<?= e($base) ?>/js/progress-overlay.js<?= e($v) ?>"></script>
<?php 
 ?>
<script defer src="<?= e($base) ?>/js/meters.js<?= e($v) ?>"></script>
<script defer src="<?= e($base) ?>/js/menu.js<?= e($v) ?>"></script>
<script defer src="<?= e($base) ?>/js/admin-nav.js<?= e($v) ?>"></script>
<script defer src="<?= e($base) ?>/js/modal.js<?= e($v) ?>"></script>
<?php 
 ?>
<script defer src="<?= e($base) ?>/js/calendar.js<?= e($v) ?>"></script>
<script defer src="<?= e($base) ?>/js/wizard.js<?= e($v) ?>"></script>
<script defer src="<?= e($base) ?>/js/appt-kind.js<?= e($v) ?>"></script>
<script defer src="<?= e($base) ?>/js/client-picker.js<?= e($v) ?>"></script>
<?php 
 ?>
<script defer src="<?= e($base) ?>/js/job-form.js<?= e($v) ?>"></script>
<?php  ?>
<script defer src="<?= e($base) ?>/js/job-eta.js<?= e($v) ?>"></script>
</head>
<body>
<header class="site-head">
  <div class="wrap nav">
    <?php 





 ?>
    <a class="brand" href="<?= e($base) ?>/admin/">
      <img src="<?= e($base) ?>/favicon.svg" alt="" width="28" height="28">
      <span>Client Work Hub</span>
    </a>
    <nav class="links">
      <?php if ($u && cm_is_admin($u)): ?>
        <?php 
 ?>
        <form method="get" action="<?= e($base) ?>/admin/search.php" class="inline" role="search">
          <input class="f-md" type="search" name="q" placeholder="Search clients, jobs…" aria-label="Search clients and jobs">
        </form>
      <?php endif; ?>
      <?php 













 ?>
      <?php if ($u && cm_is_admin($u)):
          require_once CM_LIB . '/demo.php';
          $demoOn = cm_demo_on();
          $demoBlocked = $demoOn ? [] : cm_demo_blockers(); ?>
        <?php if ($demoOn || !$demoBlocked): ?>
          <form method="post" action="<?= e($base) ?>/admin/settings.php" class="demo-switch"
                <?= $demoOn ? '' : 'data-confirm="Fill this application with fifty invented clients to look around? Nothing can be changed or sent while it is on, and leaving removes every one of them."' ?>>
            <?= cm_csrf_field() ?>
            <input type="hidden" name="action" value="<?= $demoOn ? 'demo_off' : 'demo_on' ?>">
            <button class="switch-btn<?= $demoOn ? ' is-on' : '' ?>" type="submit"
                    title="<?= $demoOn ? 'Leave demo mode and remove everything it invented' : 'Fill the application with invented data to look around' ?>">
              <span class="switch-track"><span class="switch-knob"></span></span>
              <span class="switch-text"><?= $demoOn ? 'Demo mode is on' : 'Enter demo mode' ?></span>
            </button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($u && cm_is_admin($u)): ?>
        <?php 








 ?>
        <details class="newmenu">
          <summary>+ New</summary>
          <?php 









 ?>
          <div class="menu">
            <?php 













 ?>
            <a href="<?= e($base) ?>/admin/client-new.php">Client</a>
            <?php 













 ?>
            <?php 






 ?>
            <a href="<?= e($base) ?>/admin/services.php?open=new-service">Service</a>
            <button type="button" data-modal-open="dlg-new-appt">Appointment</button>
            <button type="button" data-modal-open="dlg-new-reminder">Reminder</button>
            <?php 
























 ?>
            <a href="<?= e($base) ?>/admin/services.php?tab=snippets">Snippet</a>
            <?php 



 ?>
            <span class="menu-note"><b>Appointment</b> and <b>Reminder</b>
              open here. The rest take you to their own page.</span>
          </div>
        </details>
      <?php endif; ?>
      <?php if ($u): ?>
        <a href="<?= e($base) ?>/account/logout.php">Sign out</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="wrap page">
<?php
}

function cm_foot(): void
{



    if (cm_current_user() !== null) {
        require_once CM_LIB . '/newmodals.php';
        cm_new_modals(cm_base_url());





        require_once CM_LIB . '/tutorial.php';
        cm_tutorial_html();
    }



    if (!empty($GLOBALS['CM_SHELL_OPEN'])) {
        echo '</div></div>';
        unset($GLOBALS['CM_SHELL_OPEN']);
    }
    ?>
</main>
<footer class="site-foot">
  <div class="wrap foot">
    <span>Client Work Hub</span>
    <button class="version-link mono small" type="button" data-modal-open="dlg-whats-new"
            aria-label="What's new in Client Work Hub <?= e(cm_app_label()) ?>">
      <?= e(cm_app_label()) ?>
    </button>
  </div>
</footer>
<dialog class="modal" id="dlg-whats-new">
  <div class="modal-box release-notes">
    <div class="modal-head">
      <div>
        <p class="eyebrow">Release <?= e(cm_app_label()) ?></p>
        <h2>What&rsquo;s new</h2>
      </div>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <p class="modal-sub"><?= cm_home()
        ? 'A small release for web installs. Nothing on this PC changes.'
        : 'Updates in one click: this site can now ask GitHub for the newest release.' ?></p>
    <ul class="release-list">
      <?php if (cm_home()): ?>
      <li><b>Check for updates on the web too.</b> Web installs now get the same Check for updates button this copy already has.</li>
      <?php else: ?>
      <li><b>Check for updates.</b> Settings, Update asks GitHub for the newest release when you press it, checks its signature and every file, and waits for your backup before applying it. Nothing is fetched on its own. If your host blocks outgoing connections, upload the zip as before.</li>
      <?php endif; ?>
      <li class="muted"><b>Earlier releases:</b></li>
      <?php if (cm_home()): ?>
      <li><b>PHP 8.5.12.</b> The home download carries PHP 8.5.12, which fixes two critical faults in PHP itself. Updating from Settings, Update replaces the application but not PHP: download the newest home zip from GitHub and run Install Client Work Hub.cmd again. Your data is kept. Settings, Update warns until you do.</li>
      <li><b>This PC&rsquo;s own address only.</b> A web page that reaches this copy under any other name is refused, which closes a way for a page in your browser to talk to it.</li>
      <li><b>Tighter PHP settings.</b> The bundled PHP cannot run other programs or open web addresses as files, and hidden files stay hidden however their address is spelled.</li>
      <?php else: ?>
      <li><b>PHP that still gets fixes.</b> Settings, Update warns when your hosting&rsquo;s PHP no longer gets security fixes from the PHP project (8.1 since the end of 2025, 8.2 from the end of 2026). Moving to PHP 8.3 or newer is a setting at your host.</li>
      <?php endif; ?>
      <?php if (cm_home()): ?>
      <li><b>Runs on this PC.</b> Its own database and server start with the window and stop when it closes; your data stays in your Windows profile, and uninstalling leaves it there.</li>
      <li><b>Check for updates.</b> Settings, Update asks GitHub only when you press it, checks the release&rsquo;s signature, runs the database updates, and keeps the version before so you can go back.</li>
      <li><b>Nothing reaches out.</b> No client portal or links, no email, no online payments and no work screens: invoices and summaries are PDFs you save.</li>
      <li><b>Reset my password</b> in the Start menu, for the day you forget it.</li>
      <?php else: ?>
      <li><b>A Windows home edition.</b> The same release, installed on one Windows PC with its own PHP and database, for people who do not want hosting. Your web install is unchanged.</li>
      <?php endif; ?>
      <li><b>Property types.</b> Choose Digital/web asset, <?= cm_home() ? '' : 'Piano, ' ?>Salon, General or one of your own property templates.</li>
      <li><b>Custom property templates.</b> Build reusable service records with up to 12 text, long-text, date or number fields.</li>
      <?php if (!cm_home()): ?><li><b>Confirmed signatures.</b> Signature-required client forms also require the signer to type their full name; both are stored with the signing time.</li><?php endif; ?>
      <?php if (!cm_home()): ?><li><b>Piano service records.</b> Keep make, model, serial, room, Damp-Chaser, condition and tuning details together.</li><?php endif; ?>
      <li><b>Mobile-service context.</b> Service address, suburb and access notes are available for <?= cm_home() ? 'Salon' : 'Piano' ?> and General properties.</li>
      <li><b>Your default.</b> Settings can preselect the property type your business uses most.</li>
      <?php if (!cm_home()): ?><li><b>Privacy-safe Work Access.</b> Create staff or public-display screens with an explicit allow-list; first name is optional and off by default.</li>
      <li><b>Customisable screens.</b> Choose Today, Do next or Just done; show business, client, job and estimate; set a maximum; and optionally highlight VIPs.</li><?php endif; ?>
      <li><b>Cash Register.</b> Record walk-in sales, calculate cash change, print numbered receipts and issue append-only partial or full refunds.</li>
      <li><b>Invoice-to-job context.</b> Linked invoices on a client file now open a quick job-details window with dates, progress, status and description.</li>
      <li><b>Shorter, clearer guidance.</b> Reminders, Reports, Settings and task-edit help now state the useful fact directly, while Services folds secondary actions under More.</li>
      <li><b>Less filter clutter.</b> Workflow folds secondary views under More filters, Jobs combines search and sorting, and Messages has a cleaner filter control.</li>
      <li><b>Invoices on the client file.</b> Each client now has an Invoices tab with recent billing, payments, balances, status and a direct creation action.</li>
      <li><b>Clearer task context.</b> Task pages now show the current position, job, client, due date and remaining work without repeating the job name as the task title.</li>
      <li><b>A calmer Clients page.</b> Everyday filters stay visible, less-used views sit under More filters, and client search now takes one compact row.</li>
      <li><b>Navigation that fits.</b> The admin rail becomes a compact menu on smaller screens instead of clipping links off-screen.</li>
      <li><b>Readable lists and schedules.</b> Jobs and schedule tables become labelled stacked rows where a table no longer fits.</li>
      <li><b>Faster job setup.</b> The client-to-job form keeps the essential choices prominent and moves optional detail behind clear disclosures.</li>
      <li><b>Stronger job hierarchy.</b> Dates, progress, value and tracked time are separated into scannable summary fields.</li>
      <li><b>Consistent components.</b> Buttons, cards, spacing, typography, empty states and section actions now follow one shared system.</li>
      <li><b>Clearer language.</b> Labels and helper text are shorter and describe the action or outcome directly.</li>
    </ul>
    <div class="modal-foot">
      <button class="btn" type="button" data-modal-close>Got it</button>
    </div>
  </div>
</dialog>
</body>
</html>
<?php
}











function cm_admin_nav(string $current = ''): void
{









    $tabs = [
        ''          => ['label' => 'Dashboard', 'href' => 'admin/',              'group' => 'Today'],
        'workflow'  => ['label' => 'Workflow',  'href' => 'admin/workflow.php',  'group' => 'Today'],
        'register'  => ['label' => 'Cash register', 'href' => 'admin/register.php', 'group' => 'Today',
                        'hidden' => (string) cm_setting('register.visibility', 'show') === 'hide'],
        'reminders' => ['label' => 'Reminders', 'href' => 'admin/reminders.php', 'group' => 'Today'],
        'bookings'  => ['label' => 'Bookings',  'href' => 'admin/bookings.php',  'group' => 'Today',
                        'hidden' => cm_home()],
        'clients'   => ['label' => 'Clients',   'href' => 'admin/clients.php',   'group' => 'Your clients'],
        'jobs'      => ['label' => 'Jobs',      'href' => 'admin/jobs.php',      'group' => 'Your clients'],









        'invoices'  => ['label' => 'Invoices',  'href' => 'admin/invoices.php',  'group' => 'Your clients',


                        'off' => !cm_invoicing_enabled()],



        'mail'      => ['label' => 'Messages',  'href' => 'admin/mail.php',      'group' => 'Your clients',
                        'hidden' => cm_home()],
        'reports'   => ['label' => 'Reports',   'href' => 'admin/reports.php',   'group' => 'Your clients'],
        'services'  => ['label' => 'Services',  'href' => 'admin/services.php', 'group' => 'Set up'],
        'onboarding'=> ['label' => 'Onboarding','href' => 'admin/onboarding.php','group' => 'Set up',
                        'hidden' => cm_home()],



        'messages'  => ['label' => 'Email templates', 'href' => 'admin/email-templates.php', 'group' => 'Set up',
                        'hidden' => cm_home()],
        'settings'  => ['label' => 'Settings',  'href' => 'admin/settings.php',  'group' => 'Set up'],
    ];

    $remindersDue = 0;
    try {



        require_once CM_LIB . '/reminders.php';
        $remindersDue = (int) cm_val(
            'SELECT COUNT(*) FROM reminder WHERE ' . cm_reminder_pending_sql(''),
            [date('Y-m-d'), date('Y-m-d')]);
    } catch (Throwable $ex) {

    }


    $overdue = 0;
    try {
        $overdue = (int) cm_val(
            "SELECT COUNT(*) FROM action_occurrence o
             JOIN job j ON j.id = o.job_id
             WHERE o.status = 'scheduled' AND o.scheduled_date < ? AND j.status = 'active'",
            [date('Y-m-d')]);
    } catch (Throwable $ex) {

    }




    $inbox = 0;
    try {
        $inbox = (int) cm_val("SELECT COUNT(*) FROM booking WHERE status = 'new'")
               + (int) cm_val("SELECT COUNT(*) FROM client_feedback WHERE status = 'new'");
    } catch (Throwable $ex) {

    }



    $updates = 0;
    try {
        require_once CM_LIB . '/migrate.php';
        $updates = count(cm_migrations_pending());
    } catch (Throwable $ex) {

    }
    $installer = false;
    try {
        require_once CM_LIB . '/health.php';
        $installer = cm_installer_present();
    } catch (Throwable $ex) {

    }

    echo '<div class="shell">'
       . '<button class="admin-nav-toggle" type="button" aria-expanded="false" aria-controls="admin-main-nav">'
       . '<span>Menu</span><span class="admin-nav-current">' . e((string) ($tabs[$current]['label'] ?? 'Dashboard')) . '</span>'
       . '<span class="admin-nav-chevron" aria-hidden="true">&#9662;</span></button>'
       . '<nav class="admin-nav" id="admin-main-nav" aria-label="Main">';




    $lastGroup = null;
    foreach ($tabs as $key => $t) {
        if (!empty($t['hidden'])) continue;
        $group = (string) ($t['group'] ?? '');
        if ($group !== '' && $group !== $lastGroup) {
            echo '<div class="nav-group">' . e($group) . '</div>';
            $lastGroup = $group;
        }
        $cls = $key === $current ? 'admin-tab is-on' : 'admin-tab';
        $badge = '';
        if ($key === 'settings' && $updates > 0) {
            $badge = ' <span class="tab-count">' . $updates . '</span>';
        }

        if ($key === 'workflow' && $overdue > 0) {
            $badge = ' <span class="tab-count">' . $overdue . '</span>';
        }
        if ($key === 'bookings' && $inbox > 0) {
            $badge = ' <span class="tab-count">' . $inbox . '</span>';
        }
        if ($key === 'reminders' && $remindersDue > 0) {
            $badge = ' <span class="tab-count">' . $remindersDue . '</span>';
        }
        if ($key === 'settings' && $installer) {
            $badge .= ' <span class="tab-warn" aria-label="Warning"'
                    . ' title="install.php is still on the server — open Settings → Update to remove it">!</span>';
        }







        if (!empty($t['soon']) || !empty($t['off'])) {



            $isOff = !empty($t['off']);
            echo '<a class="' . $cls . ' is-soon" aria-disabled="true" title="'
               . ($isOff ? 'Switched off in Settings → Invoicing' : 'Coming soon') . '">'
               . cm_nav_icon($key) . e($t['label'])
               . '<span class="tab-soon">' . ($isOff ? 'off' : 'soon') . '</span></a>';
            continue;
        }



        echo '<a class="' . $cls . '" href="' . e(cm_url($t['href'])) . '">'
           . cm_nav_icon($key) . e($t['label']) . $badge . '</a>';
    }
    echo '</nav><div class="shell-content">';






    try {
        require_once CM_LIB . '/updater.php';
        $known = cm_update_known();
        if (!empty($known['newer'])) {
            echo '<div class="note note-info"><b>Update available.</b> Version '
               . '<span class="mono">' . e($known['version']) . '</span> of Client Work Hub is out; '
               . 'this site runs <span class="mono">' . e(CM_VERSION) . '</span>. '
               . '<a href="' . e(cm_url('admin/update.php')) . '#whatsnew">See what changed and update</a></div>';
        }
    } catch (Throwable $ex) {

    }
    $GLOBALS['CM_SHELL_OPEN'] = true;
}














function cm_admin_subnav(string $area, string $current = '', string $title = ''): void
{
    $areas = [










        'settings' => [
            'general'  => ['label' => 'Settings', 'href' => 'admin/settings.php', 'badge' => null],
            'billing'  => ['label' => 'Invoicing', 'href' => 'admin/billing-settings.php', 'badge' => null],

            'smart'    => ['label' => 'Smart reminders', 'href' => 'admin/smart-reminders.php', 'badge' => null,
                           'hidden' => cm_home()],





            'work_access' => ['label' => 'Work screens', 'href' => 'admin/work-access.php', 'badge' => null,
                              'hidden' => cm_home()],
            'property_templates' => ['label' => 'Property templates', 'href' => 'admin/property-templates.php', 'badge' => null],
            'audit'    => ['label' => 'Audit log', 'href' => 'admin/audit.php',   'badge' => null],
            'update'   => ['label' => 'Update',   'href' => 'admin/update.php',   'badge' => 'update'],
            'account'  => ['label' => 'My account', 'href' => 'account/',         'badge' => null],
        ],
    ];
    if (!isset($areas[$area])) return;

    $counts = [];
    try {
        require_once CM_LIB . '/migrate.php';
        $counts['update'] = count(cm_migrations_pending());
    } catch (Throwable $ex) {

    }
    $installerWarn = false;
    $releaseNew = false;
    if ($area === 'settings') {
        try {
            require_once CM_LIB . '/health.php';
            $installerWarn = cm_installer_present();
        } catch (Throwable $ex) {

        }
        try {


            require_once CM_LIB . '/updater.php';
            $releaseNew = !empty(cm_update_known()['newer']);
        } catch (Throwable $ex) {

        }
    }

    if ($title !== '') echo '<h1>' . e($title) . '</h1>';
    echo '<nav class="subnav" aria-label="' . e(ucfirst($area)) . ' sections">';
    foreach ($areas[$area] as $key => $t) {
        if (!empty($t['hidden'])) continue;
        $n   = (int) ($counts[$t['badge'] ?? ''] ?? 0);
        $cls = 'subnav-item' . ($key === $current ? ' is-on' : '');
        $cur = $key === $current ? ' aria-current="page"' : '';
        echo '<a class="' . $cls . '" href="' . e(cm_url($t['href'])) . '"' . $cur . '>'
           . e($t['label']);
        if ($n > 0) echo ' <span class="subnav-n is-work">' . $n . '</span>';
        if ($key === 'update' && $releaseNew) {
            echo ' <span class="subnav-n is-work">new</span>';
        }
        if ($key === 'update' && $installerWarn) {
            echo ' <span class="subnav-warn" aria-label="Warning"'
               . ' title="install.php is still on the server — remove it in the Cleanup section">!</span>';
        }
        echo '</a>';
    }
    echo '</nav>';
}







function cm_account_nav(string $current = '', string $title = ''): void
{




    $map = ['home' => 'account', 'security' => 'account', 'password' => 'account'];
    cm_admin_nav('settings');
    cm_admin_subnav('settings', $map[$current] ?? 'account', $title);
}







function cm_upload_limit_attrs(): string
{
    require_once CM_LIB . '/format.php';
    $cap = cm_upload_cap();
    return $cap > 0 ? ' data-max-bytes="' . $cap . '" data-max-label="' . e(cm_bytes_label($cap)) . '"' : '';
}

function cm_flash(string $message, string $kind = 'ok'): void
{
    $_SESSION['flash'][] = ['kind' => $kind, 'message' => $message];
}

function cm_render_flashes(): void
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    foreach ($items as $f) {
        $cls = $f['kind'] === 'error' ? 'note note-error' : ($f['kind'] === 'warn' ? 'note note-warn' : 'note note-ok');
        echo '<div class="' . $cls . '">' . e($f['message']) . '</div>';
    }
}















const CM_HISTORY_SHOWN = 5;
const CM_HISTORY_FETCH = 25;


















function cm_credential_tools_script_once(): string
{
    static $out = false;
    if ($out) return '';
    $out = true;
    return '<script defer src="' . e(cm_url('js/credential-tools.js'))
         . '?v=' . e(rawurlencode(cm_asset_stamp())) . '"></script>';
}

function cm_icon_eye(): string
{
    $o = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"'
       . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
       . '<path d="M2 12s3.8-6.5 10-6.5S22 12 22 12s-3.8 6.5-10 6.5S2 12 2 12Z"/>'
       . '<circle cx="12" cy="12" r="2.6"/>';
    return '<span class="eye-open">' . $o . '</svg></span>'
         . '<span class="eye-shut">' . $o . '<path d="M4 20 20 4"/></svg></span>';
}





function cm_icon_external(): string
{
    return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"'
         . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
         . '<path d="M14 4h6v6"/><path d="M20 4 11 13"/>'
         . '<path d="M18 14v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/></svg>';
}



function cm_icon_copy(): string
{
    return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"'
         . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
         . '<rect x="9" y="9" width="11" height="11" rx="2"/>'
         . '<path d="M6 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1"/></svg>';
}






























function cm_nav_icon(string $key): string
{


    $paths = [

        ''            => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/>'
                       . '<rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',

        'workflow'    => '<path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/>'
                       . '<path d="m3 6 1.5 1.5L7.5 4.5"/><path d="m3 12 1.5 1.5L7.5 10.5"/><path d="M3.5 17.5h1"/>',




        'register'    => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/>'
                       . '<path d="M6 9.6v4.8"/><path d="M18 9.6v4.8"/>',

        'reminders'   => '<path d="M18 8a6 6 0 1 0-12 0c0 6-2.5 7-2.5 7h17S18 14 18 8"/>'
                       . '<path d="M13.7 20a2 2 0 0 1-3.4 0"/>',

        'bookings'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18"/>'
                       . '<path d="M8 3v4"/><path d="M16 3v4"/>',

        'clients'     => '<path d="M16 20v-1.5a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V20"/><circle cx="9" cy="7.5" r="3.5"/>'
                       . '<path d="M22 20v-1.5a4 4 0 0 0-3-3.87"/><path d="M16.5 4.13a4 4 0 0 1 0 6.74"/>',

        'jobs'        => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V18a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',

        'invoices'    => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/>'
                       . '<path d="M14 3v5h5"/><path d="M9 13h6"/><path d="M9 17h4"/>',

        'mail'        => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',

        'reports'     => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M21 20H3"/>',

        'services'    => '<path d="M20.5 12.5 12 21 3 12V4h8Z"/><circle cx="7.5" cy="7.5" r="1.4"/>',

        'onboarding'  => '<rect x="5" y="4" width="14" height="17" rx="2"/>'
                       . '<path d="M9 4V3.2A1.2 1.2 0 0 1 10.2 2h3.6A1.2 1.2 0 0 1 15 3.2V4"/>'
                       . '<path d="M9 11h6"/><path d="M9 15h4"/>',

        'messages'    => '<path d="M20 12v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h7"/>'
                       . '<path d="m3.5 7 8 5.5"/><path d="M17 3.5 21 7.5l-5 5H12v-4Z"/>',





        'settings'    => '<path d="M4 8h9"/><path d="M17 8h3"/><circle cx="15" cy="8" r="2"/>'
                       . '<path d="M4 16h3"/><path d="M11 16h9"/><circle cx="9" cy="16" r="2"/>',
    ];

    if (!isset($paths[$key])) return '';

    return '<span class="tab-ico" aria-hidden="true">'
         . '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"'
         . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">'
         . $paths[$key] . '</svg></span>';
}






















function cm_crumbs_html(array $trail): string
{
    $trail = array_values(array_filter($trail, static fn($c) => trim((string) ($c['label'] ?? '')) !== ''));
    if (!$trail) return '';

    $out  = '<nav class="crumbs no-print" aria-label="Breadcrumb">';
    foreach ($trail as $i => $c) {
        if ($i > 0) $out .= '<span class="crumbs-sep" aria-hidden="true">&rarr;</span>';
        $label = e((string) $c['label']);
        $out .= empty($c['href'])
            ? '<span class="crumbs-now" aria-current="page">' . $label . '</span>'
            : '<a href="' . e((string) $c['href']) . '">' . $label . '</a>';
    }
    return $out . '</nav>';
}

function cm_field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<span class="field-err">' . e($errors[$field]) . '</span>' : '';
}










const CM_PAGE_SIZES   = [10, 25, 50];
const CM_PAGE_DEFAULT = 25;



function cm_page_params(int $total = PHP_INT_MAX): array
{
    $size = (int) ($_GET['n'] ?? CM_PAGE_DEFAULT);
    if (!in_array($size, CM_PAGE_SIZES, true)) $size = CM_PAGE_DEFAULT;
    $pages = max(1, (int) ceil($total / $size));
    $page  = min($pages, max(1, (int) ($_GET['p'] ?? 1)));
    return [$size, $page];
}








function cm_pager_html(int $total, int $page, int $size, callable $url): string
{
    $pages = max(1, (int) ceil($total / $size));
    if ($pages <= 1 && $size === CM_PAGE_DEFAULT) return '';
    $from = $total === 0 ? 0 : ($page - 1) * $size + 1;
    $to   = min($total, $page * $size);
    $h  = '<nav class="pager" aria-label="Pages">';
    $h .= '<span class="pager-at">Showing <b>' . $from . '&ndash;' . $to . '</b> of <b>' . $total . '</b></span>';
    if ($page > 1)      $h .= '<a class="btn btn-quiet" href="' . e($url(['p' => $page - 1])) . '">&lsaquo; Previous</a>';
    if ($page < $pages) $h .= '<a class="btn btn-quiet" href="' . e($url(['p' => $page + 1])) . '">Next &rsaquo;</a>';
    $h .= '<span class="spacer"></span><span class="pager-at">Show ';
    foreach (CM_PAGE_SIZES as $n) {
        $h .= $n === $size ? '<b>' . $n . '</b> '
                           : '<a href="' . e($url(['n' => $n, 'p' => ''])) . '">' . $n . '</a> ';
    }
    return $h . 'per page</span></nav>';
}







function cm_page_url(string $base, array $keep): callable
{
    return static function (array $over = []) use ($base, $keep): string {
        $q = $keep;
        foreach ($over as $k => $v) $q[$k] = (string) $v;
        if (($q['n'] ?? '') === (string) CM_PAGE_DEFAULT) $q['n'] = '';
        if (($q['p'] ?? '') === '1') $q['p'] = '';
        $q = array_filter($q, static fn($v): bool => (string) $v !== '');
        return cm_url($base . ($q ? '?' . http_build_query($q) : ''));
    };
}














function cm_schema_gate(string $feature, string $navTab = ''): void
{
    require_once CM_LIB . '/migrate.php';
    $missing = cm_feature_schema_missing($feature);
    if ($missing === []) return;

    $spec    = cm_feature_requirements()[$feature] ?? ['label' => 'This feature', 'since' => ''];
    $label   = (string) $spec['label'];
    $pending = [];
    try { $pending = cm_migrations_pending(); } catch (Throwable $ex) {}
    $note = '';
    try { $note = cm_migration_fail_note(); } catch (Throwable $ex) {}

    cm_head($label . ' needs a database update', '', true);
    cm_render_flashes();
    if ($navTab !== '') cm_admin_nav($navTab);
    ?>
    <div class="page-hero">
      <div class="card-head align-baseline">
        <div>
          <span class="eyebrow">Database update required</span>
          <h1 class="mb-0"><?= e($label) ?> is not switched on yet</h1>
        </div>
      </div>
    </div>
    <div class="card narrow-card">
      <p>The program files for <?= e($label) ?> are installed, but the database
      has not been brought up to date with them yet. Nothing is broken and no
      data is at risk &mdash; this screen simply cannot work until the update
      has run.</p>

      <?php if ($pending): ?>
        <p><b><?= count($pending) ?> database update<?= count($pending) === 1 ? '' : 's' ?></b>
        <?= count($pending) === 1 ? 'is' : 'are' ?> waiting. Applying
        <?= count($pending) === 1 ? 'it' : 'them' ?> takes a few seconds.</p>
      <?php else: ?>
        <p class="note note-error">No update is waiting on this server, which means the
        database is behind the files but the update files did not arrive with them.
        Upload the release again &mdash; the whole of it, not only the changed pages
        &mdash; and then run the update.</p>
      <?php endif; ?>

      <?php if ($note !== ''): ?>
        <div class="note note-error"><b>A previous update stopped part way.</b>
        <span class="mono small"><?= e($note) ?></span>
        Restore your most recent backup before running it again.</div>
      <?php endif; ?>

      <div class="actions mt-16">
        <a class="btn" href="<?= e(cm_url('admin/update.php')) ?>">Open Update &amp; backup</a>
      </div>

      <?php 

 ?>
      <details class="mt-16">
        <summary>What exactly is missing</summary>
        <p class="mono small"><?= e(cm_feature_missing_label($missing)) ?></p>
        <?php if ((string) $spec['since'] !== ''): ?>
          <p class="muted small">Brought in by <span class="mono"><?= e((string) $spec['since']) ?></span>.</p>
        <?php endif; ?>
      </details>
    </div>
    <?php
    cm_foot();
    exit;
}
