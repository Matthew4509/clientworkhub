<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0



























declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
cm_home_hide();
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/maillog.php';


require_once CM_LIB . '/onboarding.php';

$admin = cm_require_admin();

$filters = cm_mail_filters();
$filter  = (string) ($_GET['f'] ?? 'all');
$isReplies = $filter === 'replies';
if (!$isReplies && !isset($filters[$filter])) $filter = 'all';
$search  = trim((string) ($_GET['q'] ?? ''));







$feed = cm_replies_feed($isReplies ? $search : '');
$repliesCount = $isReplies && $search !== '' ? count(cm_replies_feed()) : count($feed);



if ($isReplies) {
    $total = count($feed);
    [$pageSize, $page] = cm_page_params($total);
    $rows  = array_slice($feed, ($page - 1) * $pageSize, $pageSize);
} else {
    $total = cm_mail_log_count($filter, $search);
    [$pageSize, $page] = cm_page_params($total);
    $rows  = cm_mail_log($filter, $pageSize, $search, ($page - 1) * $pageSize);
}
$counts = cm_mail_counts();
$mailUrl = cm_page_url('admin/mail.php',
    ['f' => $filter === 'all' ? '' : $filter, 'q' => $search, 'n' => (string) $pageSize, 'p' => (string) $page]);


$replyMailRows = [];
if ($isReplies && $rows) {
    $ids = [];
    foreach ($rows as $r) { if ($r['kind'] === 'mailreply') $ids[] = (int) $r['id']; }
    if ($ids) {
        try {
            foreach (cm_all('SELECT id, sent_to, subject, body, created_at FROM mail_log WHERE id IN ('
                            . implode(',', array_fill(0, count($ids), '?')) . ')', $ids) as $m) {
                $replyMailRows[(int) $m['id']] = $m;
            }
        } catch (Throwable $ex) {}
    }
}

cm_head('Messages', '', true);
cm_render_flashes();
cm_admin_nav('mail');
?>
<div class="card-head">
  <h1>Messages</h1>
  <span class="spacer"></span>
  <a class="btn btn-quiet" href="<?= e(cm_url('admin/email-templates.php')) ?>">Email templates</a>
</div>
<?php 

 ?>
<p class="sub"><?= $isReplies
    ? 'What has come back — answered question links, and email replies you have noted against what was sent.'
    : 'A list of emails sent from this platform.' ?></p>

<?php if (!cm_mail_enabled()): ?>
  <div class="note note-warn">
    <?php 








 ?>
    <b>Sending emails is currently off.</b>
    <a href="<?= e(cm_url('admin/settings.php')) ?>">Turn it on under Settings → Email.</a>
  </div>
<?php endif; ?>

<?php






$tabOne = static function (string $key, string $label, int $n) use ($filter, $mailUrl): void { ?>
    <a class="subnav-item<?= $filter === $key ? ' is-on' : '' ?>"
       href="<?= e($mailUrl(['f' => $key === 'all' ? '' : $key, 'p' => ''])) ?>"
       <?= $filter === $key ? 'aria-current="page"' : '' ?>>
      <?= e($label) ?><?php if ($n > 0): ?><span class="subnav-n"><?= $n ?></span><?php endif; ?>
    </a>
<?php };
?>
<nav class="subnav" aria-label="Which messages">
  <?php $tabOne('replies', 'Replies', $repliesCount); ?>
  <?php foreach (cm_mail_filters_open() as $key => $label) $tabOne($key, $label, (int) ($counts[$key] ?? 0)); ?>
  <details class="subnav-more"<?= isset(cm_mail_filters_more()[$filter]) ? ' open' : '' ?>>
    <summary>More filters</summary>
    <?php foreach (cm_mail_filters_more() as $key => $label) $tabOne($key, $label, (int) ($counts[$key] ?? 0)); ?>
  </details>
</nav>

<div class="inv-toolbar">
  <?php 


 ?>
  <form method="get" action="<?= e(cm_url('admin/mail.php')) ?>" class="inline">
    <input type="hidden" name="f" value="<?= e($filter) ?>">
    <input type="search" name="q" value="<?= e($search) ?>" class="f-lg"
           placeholder="address, subject or words in the message">
    <button class="btn btn-sm btn-quiet" type="submit">Search</button>
    <?php if ($search !== ''): ?>
      <a class="btn btn-sm btn-quiet" href="<?= e(cm_url('admin/mail.php?f=' . $filter)) ?>">Clear</a>
    <?php endif; ?>
  </form>
  <span class="spacer"></span>
  <span class="inv-count">
    <?= $total ?> message<?= $total === 1 ? '' : 's' ?>
  </span>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <p class="muted small m-0">
      <?php if ($search !== ''): ?>
        Nothing matches &ldquo;<?= e($search) ?>&rdquo;.
      <?php elseif ($isReplies): ?>
        Nothing has come back yet. Answered question links land here on their
        own; an email reply lands here when you note it on the client&rsquo;s
        Communication tab — <b>Client replied</b> on the sent email.
      <?php elseif ($filter !== 'all'): ?>
        Nothing under <?= e(mb_strtolower($filters[$filter])) ?>.
      <?php else: ?>
        Nothing has been sent yet. Every message — a work summary, a
        follow-up, a sign-in code — is recorded here as it goes, including
        any this application refuses to send.
      <?php endif; ?>
    </p>
  <?php elseif ($isReplies): ?>
    <?php 

 ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th class="f-base">Date</th><th class="f-base">Kind</th><th>From</th><th>About</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="mono small nowrap"><?= $r['sort'] ? e(date('j M, g:ia', $r['sort'])) : '&mdash;' ?></td>
            <td class="small"><?= $r['kind'] === 'questions' ? 'Answers to your questions' : 'Email reply — noted' ?></td>
            <td class="small">
              <?php if ($r['client_id'] !== null): ?>
                <a href="<?= e(cm_url('admin/client.php?id=' . (int) $r['client_id'] . '&tab=comms')) ?>"><?= e($r['who']) ?></a>
              <?php else: ?><span class="muted">&mdash;</span><?php endif; ?>
            </td>
            <td class="small"><b><?= e($r['title']) ?></b><?php if ($r['kind'] === 'mailreply' && $r['notes'] > 1): ?>
              <span class="muted"> — <?= (int) $r['notes'] ?> notes</span><?php endif; ?></td>
            <td class="num">
              <button class="btn btn-sm btn-quiet" type="button"
                      data-modal-open="dlg-r-<?= $r['kind'] === 'questions' ? 'q' : 'm' ?>-<?= (int) $r['id'] ?>">Read it</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= cm_pager_html($total, $page, $pageSize, $mailUrl) ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th class="f-base">Type</th>
            <th class="f-base">Status</th>
            <th class="f-base">Date</th>
            <th>Recipient</th>
            <th>Summary</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $out  = cm_mail_outcome((string) $r['result']);
            $kind = cm_mail_kind_label((string) $r['kind']);
            $who  = (int) ($r['client_id'] ?? 0) > 0
                  ? cm_client_display_name([
                      'id' => $r['client_id'], 'first_name' => $r['first_name'],
                      'last_name' => $r['last_name'], 'company' => $r['company'],
                      'email' => $r['client_email']])
                  : ''; ?>
          <tr>
            <td class="small"><?= e($kind) ?></td>
            <td>
              <span class="pill <?= e($out['pill']) ?>"><?= e($out['label']) ?></span>
            </td>
            <td class="mono small nowrap"><?= e(date('j M, g:ia', strtotime((string) $r['created_at']))) ?></td>
            <td class="small">
              <?php if ($who !== ''): ?>
                <a href="<?= e(cm_url('admin/client.php?id=' . (int) $r['client_id'] . '&tab=comms')) ?>"><?= e($who) ?></a><br>
              <?php endif; ?>
              <span class="mono muted"><?= e((string) $r['sent_to']) ?></span>
            </td>
            <td class="small">
              <details class="mail-row">
                <summary><?= e(cm_mail_summary($r)) ?></summary>
                <div class="mail-body">
                  <?php if ($out['note'] !== ''): ?>
                    <p class="small <?= $out['key'] === 'sent' ? 'muted' : 'txt-err' ?> mb-6">
                      <b><?= e($out['label']) ?>:</b> <?= e($out['note']) ?></p>
                  <?php endif; ?>
                  <?php if (trim((string) $r['subject']) !== ''): ?>
                    <p class="small mb-6"><b>Subject:</b> <?= e((string) $r['subject']) ?></p>
                  <?php endif; ?>
                  <?php if (trim((string) ($r['body'] ?? '')) !== ''): ?>
                    <?php 

 ?>
                    <pre class="small prewrap mail-text"><?= e((string) $r['body']) ?></pre>
                  <?php else: ?>
                    <p class="muted small m-0">No copy of the body was kept —
                    this went out before the log recorded one.</p>
                  <?php endif; ?>
                </div>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= cm_pager_html($total, $page, $pageSize, $mailUrl) ?>
    <div>
    </div>
  <?php endif; ?>
</div>

<?php if ($isReplies): ?>
  <?php 



 ?>
  <?php foreach ($rows as $r): ?>
    <?= cm_reply_window_html($r, $r['kind'] === 'mailreply' ? ($replyMailRows[(int) $r['id']] ?? null) : null) ?>
  <?php endforeach; ?>
<?php endif; ?>
<?php cm_foot(); ?>
