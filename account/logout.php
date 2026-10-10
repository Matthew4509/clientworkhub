<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0




declare(strict_types=1);

const CM_DEMO_SIGNIN_PAGE = true;
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    cm_logout();
    cm_redirect('account/login.php');
}

cm_head('Sign out', '', true);
?>
<h1>Sign out</h1>
<div class="card">
  <form method="post">
    <?= cm_csrf_field() ?>
    <p class="sub">Sign out of Client Work Hub on this device?</p>
    <button class="btn" type="submit">Sign out</button>
    <a class="btn btn-quiet" href="<?= e(cm_url('account/')) ?>">Cancel</a>
  </form>
</div>
<?php cm_foot(); ?>
