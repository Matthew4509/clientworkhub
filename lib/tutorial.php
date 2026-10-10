<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0























































declare(strict_types=1);

if (!defined('CM_ROOT')) { http_response_code(404); exit; }









function cm_tutorial_enabled(): bool
{








    try { return (string) (cm_setting('help.enabled', '0') ?? '0') === '1'; }
    catch (Throwable $ex) { return false; }
}










function cm_tutorial_screen(): string
{
    $path  = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $parts = array_values(array_filter(explode('/', $path), static fn($p) => $p !== ''));
    $n     = count($parts);
    if ($n === 0) return '';
    return $n === 1 ? $parts[0] : $parts[$n - 2] . '/' . $parts[$n - 1];
}












function cm_tours(): array
{
    return [

    'general' => [
        'title' => 'How Client Work Hub works',
        'sub'   => 'The short version — about a minute',
        'steps' => [
            ['title' => 'Three words, and that is most of it',
             'body'  => 'A <b>client</b> is who you work for. A <b>job</b> is a piece of work for '
                      . 'them. A <b>property</b> is a thing of theirs you work on — a website, a '
                      . 'Facebook page, a registrar. Almost everything you do here is one of those '
                      . 'three.',
             'tip'   => 'A property is also where a sign-in is kept, which is what the password '
                      . 'vault is for.'],

            ['title' => 'Add anything from anywhere',
             'body'  => '<b>+ New</b> in the header opens a window over whatever you are looking '
                      . 'at. Client, job, service, appointment, reminder, snippet.',
             'tip'   => 'It is a window, not a new page — Cancel puts you back exactly where you '
                      . 'were, with nothing lost.'],

            ['title' => 'It will not fight you',
             'body'  => 'Leave a job\'s name empty and it names itself. Leave the date empty and '
                      . 'it starts today. Nothing is refused over something the application could '
                      . 'work out.',
             'tip'   => 'And if it does have to stop and ask, everything you typed is still there '
                      . 'when it does.'],

            ['title' => 'The dashboard is your day',
             'body'  => 'What is running, what is next, and one door into it. Start the timer, do '
                      . 'the work, close it out — and the time lands against the job.',
             'tip'   => 'The arrows change the day, so you can look at tomorrow without leaving '
                      . 'the screen.'],

            ['title' => 'Set-up can wait',
             'body'  => 'Services, onboarding, email templates and settings are all under '
                      . '<b>Set up</b> in the sidebar. None of it is needed to add a client and '
                      . 'get on with the work.',
             'tip'   => 'Build a service when you notice yourself typing the same job twice.'],

            ['title' => 'This button follows you',
             'body'  => 'It is on every screen, and on the dashboard, a client, a job and a task '
                      . 'it explains <b>that</b> screen rather than this general tour.',
             'tip'   => 'Settings &rsaquo; Help turns it off if you would rather not see it.'],
        ],
    ],

    'admin/index.php' => [
        'title' => 'Your dashboard',
        'sub'   => 'What today looks like',
        'steps' => [
            ['title' => 'Do next',
             'body'  => 'The work the application thinks you should pick up now, in the order you '
                      . 'chose under Settings &rsaquo; What you do first.',
             'tip'   => 'Nothing is ever refused because of that order — a short day simply does '
                      . 'the important things first.'],

            ['title' => 'One door, not two',
             'body'  => '<b>Start work</b> begins the timer and takes you to the task screen, '
                      . 'which is where the work is actually done. This card deliberately does '
                      . 'not try to be a second copy of it.',
             'tip'   => 'While a timer is running, a red bar sits at the top of every screen with '
                      . 'one click back to it.'],

            ['title' => 'Just done, and Coming up',
             'body'  => 'What you finished, and what is on the way. The arrows at the top move '
                      . 'the whole day — yesterday, tomorrow, next week.',
             'tip'   => 'Only daily tasks are listed. With three clients on daily work, a full '
                      . 'list would be forty rows.'],

            ['title' => 'Reminders',
             'body'  => 'Anything you asked to be reminded about, plus the client follow-ups that '
                      . 'are due.',
             'tip'   => 'Scheduled emails go out when you open this screen. A message due on a '
                      . 'day you never sign in goes the next day you do — late, not lost.'],

            ['title' => 'What the work is worth',
             'body'  => 'Value against time, so the day has a number on it as well as a list.',
             'tip'   => 'Reports goes further: clients by effective rate, lowest first. It is '
                      . 'usually not the client you expected.'],
        ],
    ],

    'admin/client.php' => [
        'title' => 'The client file',
        'sub'   => 'Everything about one client',
        'steps' => [
            ['title' => 'The tabs are the file',
             'body'  => 'Details, core, properties, jobs, conversations and contacts. The overview '
                      . 'you land on is a summary of all of them.',
             'tip'   => 'Nothing here needs filling in before you can work. A half-filled client '
                      . 'is a real client.'],

            ['title' => 'Add job and Add property',
             'body'  => 'Both lead the right-hand rail, above the job list, and both open a '
                      . 'window. They are the two things you will do here most.',
             'tip'   => 'A property is a website, a social account, a registrar — anything of '
                      . 'theirs you work on.'],

            ['title' => 'Standing instructions',
             'body'  => 'The permanent rules for this client — <i>prefers 500-word articles, not '
                      . '300</i>. They follow their work around and show wherever it is being '
                      . 'done.',
             'tip'   => 'You can add to them from inside a task, which is where you usually find '
                      . 'them out. They are only ever added to, never replaced.'],

            ['title' => 'Shown beside jobs',
             'body'  => 'You choose which facts about this client ride along beside their work, so '
                      . 'the things you need mid-task are there and the rest is not.',
             'tip'   => 'The same facts appear on the job and the task screen.'],

            ['title' => 'Value, and the history',
             'body'  => 'What their work is worth, and a record that merges the emails the '
                      . 'application sent with the conversations you logged yourself.',
             'tip'   => 'If a client has unsubscribed, every tab here says so in red.'],
        ],
    ],

    'admin/job.php' => [
        'title' => 'A job',
        'sub'   => 'How a piece of work is set up and run',
        'steps' => [
            ['title' => 'The brief comes first',
             'body'  => 'Whatever the client sent — keywords, targets, links. It is at the top '
                      . 'because it is what you need before you start, and it shows wherever the '
                      . 'work is done.',
             'tip'   => 'The card is drawn even when it is empty, because that is where the '
                      . 'button to fill it lives.'],

            ['title' => 'The properties this job touches',
             'body'  => 'Which of their websites or accounts this work is on. Their sign-ins '
                      . 'appear here — the address, the username with a Copy button, and the '
                      . 'password one deliberate click away.',
             'tip'   => 'Every reveal is written to the audit log before it happens.'],

            ['title' => 'Actions — what this job repeats',
             'body'  => 'The daily, weekly or monthly steps this job generates. They are what '
                      . 'turns into the tasks on your dashboard.',
             'tip'   => 'A job with no actions still exists and still holds value; it just does '
                      . 'not generate a day\'s work.'],

            ['title' => 'Start work',
             'body'  => 'Names today\'s task and starts the clock on it. When today\'s is done it '
                      . 'becomes <i>today\'s is done — start the next one</i>.',
             'tip'   => 'Pausing keeps you on this page rather than throwing you back to the '
                      . 'dashboard.'],

            ['title' => 'Put this job on hold',
             'body'  => 'Opens a window that asks <b>why</b> and <b>until when</b> — waiting on '
                      . 'the client or paused by you, until you resume or with a nudge in seven '
                      . 'days.',
             'tip'   => 'Waiting and paused stay separate words with separate tabs on the Jobs '
                      . 'screen.'],

            ['title' => 'The schedule, and editing',
             'body'  => 'Dates and length sit at the bottom, shut, because you set them once. '
                      . '<b>Edit the job</b> opens a window rather than taking you somewhere '
                      . 'else.',
             'tip'   => 'Going to a job to work on it is the common case; editing it is not.'],
        ],
    ],

    'admin/task.php' => [
        'title' => 'Doing the work',
        'sub'   => 'The task screen',
        'steps' => [
            ['title' => 'This is where work happens',
             'body'  => 'Everything you need for one task, on one screen — so nothing sends you '
                      . 'back to the job or the client to find something mid-job.',
             'tip'   => 'The dashboard deliberately does not duplicate this. One place, not two.'],

            ['title' => 'Who this is for',
             'body'  => 'Top right: the client, and the standing rules that apply to their work.',
             'tip'   => '<b>Remember something</b> adds to those rules from here — which is where '
                      . 'you usually learn them. It appends; it can never overwrite.'],

            ['title' => 'What you already did',
             'body'  => 'The previous days on this job, so you can pick up where you left off '
                      . 'without opening anything.',
             'tip'   => 'Above the schedule on purpose — what you did matters more than what is '
                      . 'planned.'],

            ['title' => 'Sign in and go',
             'body'  => 'The property\'s address, a link that opens it, the username with Copy, '
                      . 'and the password behind one click.',
             'tip'   => 'If a property has no address stored it says so, rather than quietly '
                      . 'drawing nothing.'],

            ['title' => 'What was done',
             'body'  => 'Write up the task and close it out. Snippets drop in text you send often '
                      . '— one for the client\'s record, one for your own note.',
             'tip'   => '<b>Add to this job\'s brief</b> appends what you learned to the job '
                      . 'itself, so it is there next time.'],
        ],
    ],

    ];
}








function cm_tutorial_html(): void
{
    if (!cm_tutorial_enabled()) return;

    $tours  = cm_tours();
    $screen = cm_tutorial_screen();
    $tour   = $tours[$screen] ?? $tours['general'];
    $steps  = $tour['steps'];
    if (!$steps) return;
    ?>

<button class="tut-fab" type="button" data-modal-open="dlg-tutorial"
        aria-haspopup="dialog" title="How this screen works">
  <?php 


 ?>
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
       stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <circle cx="12" cy="12" r="9.2"/>
    <path d="M9.3 9.3a2.8 2.8 0 1 1 3.6 2.7c-.6.2-.9.7-.9 1.3v.6"/>
    <path d="M12 17.1h.01"/>
  </svg>
  <span class="tut-fab-label">Help</span>
</button>

<dialog class="modal modal-wide" id="dlg-tutorial" aria-labelledby="tut-title">
  <div class="modal-box">
    <div data-wizard>
      <div class="modal-head">
        <h2 class="m-0" id="tut-title"><?= e((string) $tour['title']) ?></h2>
      </div>
      <p class="modal-sub"><?= e((string) $tour['sub']) ?></p>

      <?php 





 ?>
      <?php foreach ($steps as $s): ?>
        <div data-step data-step-title="<?= e((string) $s['title']) ?>">
          <h3 class="tut-h"><?= e((string) $s['title']) ?></h3>
          <?php 

 ?>
          <p class="tut-body"><?= $s['body'] ?></p>
          <?php if (($s['tip'] ?? '') !== ''): ?>
            <p class="tut-tip"><?= $s['tip'] ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="wiz-bar">
        <span class="wiz-count" data-wizard-count></span>
        <span class="spacer"></span>
        <button class="btn btn-quiet" type="button" data-wizard-back>Back</button>
        <button class="btn" type="button" data-wizard-next>Next</button>
        <button class="btn" type="button" data-wizard-submit data-modal-close>Done</button>
      </div>
    </div>
  </div>
</dialog>
<?php
}
