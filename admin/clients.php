<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0







declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';

$admin = cm_require_admin();


$term   = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
if ($status !== '' && $status !== 'all' && !cm_client_status_valid($status)) $status = '';
$page   = max(1, (int) ($_GET['p'] ?? 1));






$vipOnly = (string) ($_GET['vip'] ?? '') === '1';

$result = cm_client_search($term, $status, $page, 50, $vipOnly);
$rows   = $result['rows'];
$total  = $result['total'];
$pages  = max(1, (int) ceil($total / 50));


$counts = ['' => 0, 'all' => 0, 'vip' => 0];
try {
    $counts[''] = (int) cm_val("SELECT COUNT(*) FROM client WHERE status <> 'inactive'");
    $counts['all'] = (int) cm_val('SELECT COUNT(*) FROM client');




    $counts['vip'] = (int) cm_val("SELECT COUNT(*) FROM client WHERE vip = 1 AND status <> 'inactive'");
    foreach (cm_all('SELECT status, COUNT(*) AS n FROM client GROUP BY status') as $r) {
        $counts[(string) $r['status']] = (int) $r['n'];
    }
} catch (Throwable $ex) {

}

cm_head('Clients', '', true);
cm_render_flashes();
cm_admin_nav('clients');

$primaryTabs = [
    '' => 'Working',
    'prospect' => 'Prospect',
    'active' => 'Active',
    'reminders' => 'Reminders',
];
$moreTabs = [
    'paused' => 'Paused',
    'inactive' => 'Inactive',
    'all' => 'Everyone',
];
$moreOpen = !$vipOnly && isset($moreTabs[$status]);
?>
<?php 


 ?>
<div class="topbar">
  <h1>Clients</h1>
  <a class="btn" href="<?= e(cm_url('admin/client-new.php')) ?>">+ New client</a>
</div>

<nav class="subnav" aria-label="Client status">
  <?php $q = $term !== '' ? '&q=' . rawurlencode($term) : ''; ?>
  <?php foreach ($primaryTabs as $key => $label):
      $href = cm_url('admin/clients.php' . ($key === '' ? ($q !== '' ? '?' . ltrim($q, '&') : '') : '?status=' . $key . $q));



      $on = $status === $key && !$vipOnly; ?>
    <a class="subnav-item<?= $on ? ' is-on' : '' ?>" href="<?= e($href) ?>"
       <?= $on ? 'aria-current="page"' : '' ?>>
      <?= e($label) ?> <span class="subnav-n"><?= (int) ($counts[$key] ?? 0) ?></span>
    </a>
  <?php endforeach; ?>
  <details class="subnav-more"<?= $moreOpen ? ' open' : '' ?>>
    <summary class="subnav-item<?= $moreOpen ? ' is-on' : '' ?>">
      More filters<?php if ($moreOpen): ?>: <?= e($moreTabs[$status]) ?><?php endif; ?>
    </summary>
    <div class="subnav-more-body">
      <?php foreach ($moreTabs as $key => $label):
          $href = cm_url('admin/clients.php?status=' . $key . $q);
          $on = $status === $key && !$vipOnly; ?>
        <a class="subnav-item<?= $on ? ' is-on' : '' ?>" href="<?= e($href) ?>"
           <?= $on ? 'aria-current="page"' : '' ?>>
          <?= e($label) ?> <span class="subnav-n"><?= (int) ($counts[$key] ?? 0) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </details>
  <?php 



 ?>
  <a class="subnav-item subnav-vip<?= $vipOnly ? ' is-on' : '' ?>"
     href="<?= e(cm_url('admin/clients.php?vip=1' . $q)) ?>"
     <?= $vipOnly ? 'aria-current="page"' : '' ?>>
    VIP <span class="subnav-n"><?= (int) ($counts['vip'] ?? 0) ?></span>
  </a>
</nav>

<div class="card client-search-card">
  <?php 

 ?>
  <form class="client-search" method="get" action="<?= e(cm_url('admin/clients.php')) ?>">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <?php if ($vipOnly): ?><input type="hidden" name="vip" value="1"><?php endif; ?>
    <div class="client-search-field">
      <label class="lbl" for="q">Find a client</label>
      <input type="search" id="q" name="q" value="<?= e($term) ?>"
             placeholder="Name, company, email, phone, tag, or anything in the notes">
    </div>
    <div class="actions client-search-actions">
      <button class="btn btn-sm" type="submit">Search</button>
      <?php if ($term !== '' || $vipOnly): ?>
        <a class="btn btn-sm btn-quiet" href="<?= e(cm_url('admin/clients.php' . ($status !== '' ? '?status=' . $status : ''))) ?>">Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <?php 




 ?>
    <?php
      $narrow = [];
      if ($status !== '' && $status !== 'all') $narrow[] = 'with that status';
      if ($vipOnly) $narrow[] = 'among VIPs';
      $narrow = $narrow ? ' ' . implode(' and ', $narrow) : '';
    ?>
    <p class="muted small">
      <?php if ($term !== ''): ?>
        Nothing matches “<?= e($term) ?>”<?= $narrow ?>.
        <?php if ($vipOnly): ?><b>Search</b> looks at everybody.<?php endif; ?>
      <?php elseif ($total === 0 && ($counts['all'] ?? 0) === 0): ?>
        No clients yet — add the first one below.
      <?php elseif ($vipOnly): ?>
        Nobody is a VIP yet — <b>Make VIP</b> on a client's own page marks them.
      <?php else: ?>
        Nobody has this status.
      <?php endif; ?>
    </p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Client</th><th>Contact</th><th>Tags</th><th>Status</th><th>Last touched</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $c):
            $st = cm_client_statuses()[$c['status']] ?? null;



            $rowVip = (int) ($c['vip'] ?? 0) === 1; ?>
          <?php 


 ?>
          <tr<?= $rowVip ? ' class="is-vip"' : '' ?>>
            <td>
              <a href="<?= e(cm_url('admin/client.php?id=' . (int) $c['id'])) ?>"><b><?= e(cm_client_display_name($c)) ?></b></a>
              <?php if (trim((string) $c['company']) !== '' && trim((string) $c['first_name'] . $c['last_name']) !== ''): ?>
                <div class="muted small"><?= e((string) $c['company']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small">
              <?= e((string) $c['email']) ?>
              <?php if ((string) $c['phone'] !== ''): ?>
                <div class="muted"><?= e((string) $c['phone']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small muted"><?= e((string) $c['tags']) ?></td>
            <?php 







 ?>
            <td><?php if ($rowVip && (string) $c['status'] === 'active'): ?>
                <span class="pill pill-vip">VIP</span>
              <?php elseif ($st): ?>
                <span class="pill <?= e($st['pill']) ?>"><?= e($st['label']) ?></span>
              <?php endif; ?></td>
            <td class="small muted"><?= e(date('j M Y', strtotime((string) $c['updated_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <p class="muted small mt-12">
        Page <?= $page ?> of <?= $pages ?> (<?= $total ?> client<?= $total === 1 ? '' : 's' ?>)
        <?php 

 ?>
        <?php $qs = ($status !== '' ? '&status=' . $status : '') . ($term !== '' ? '&q=' . rawurlencode($term) : '')
                  . ($vipOnly ? '&vip=1' : ''); ?>
        <?php if ($page > 1): ?>
          · <a href="<?= e(cm_url('admin/clients.php?p=' . ($page - 1) . $qs)) ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $pages): ?>
          · <a href="<?= e(cm_url('admin/clients.php?p=' . ($page + 1) . $qs)) ?>">Next</a>
        <?php endif; ?>
      </p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php cm_foot(); ?>
