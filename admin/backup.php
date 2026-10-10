<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0












declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';




require_once CM_LIB . '/migrate.php';


require_once CM_LIB . '/view.php';
require_once CM_LIB . '/backup.php';
require_once CM_LIB . '/recovery.php';




$admin = cm_require_admin_or_recovery();




if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    cm_flash('A backup has to be started from the button — the link cannot be bookmarked, '
           . 'because a URL that downloads the database is a URL that ends up in a log.', 'warn');
    cm_redirect('admin/update.php');
}
cm_require_csrf();








$phrase = [];
if (!empty($_POST['sealed'])) {
    require_once CM_LIB . '/seal.php';
    $held = cm_seal_pending();
    if ($held === null || empty($held['confirmed'])) {
        cm_flash('That backup key expired before the download started, so nothing was sealed with it. '
               . 'Press "Download an encrypted backup instead" again for a new one.', 'warn');
        cm_redirect('admin/update.php#backup');
    }
    $phrase = (array) $held['phrase'];
}
$sealed = $phrase !== [];












$withPasswords = !empty($_POST['passwords']);






$gzip = cm_backup_can_gzip() && empty($_POST['plain']);
$name = $sealed ? cm_backup_sealed_filename() : cm_backup_filename($gzip);





while (ob_get_level() > 0) ob_end_clean();
@ini_set('zlib.output_compression', '0');





header('Content-Type: ' . ($sealed ? 'application/octet-stream'
                                   : ($gzip ? 'application/gzip' : 'application/sql')) . '; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');



if ($sealed) {






    $phraseForStream = $phrase;
    cm_seal_forget();

    $writer = cm_seal_writer($phraseForStream,
        static function (string $s): void { echo $s; flush(); },
        ($gzip ? CM_SEAL_FLAG_GZIP : 0) | (cm_app_key_source()['where'] !== 'none' ? CM_SEAL_FLAG_APPKEY : 0));

    $bytes = cm_backup_stream($gzip, $writer, $withPasswords);
} else {
    $bytes = cm_backup_stream($gzip, null, $withPasswords);
}






cm_backup_mark_taken($bytes);







cm_audit($sealed ? 'db.backup.sealed' : 'db.backup', '', null,
         $name . ' — ' . cm_backup_size_label($bytes)
         . ($sealed ? ' sealed and' : '') . ' downloaded by ' . (string) $admin['email']
         . ' — client passwords ' . ($withPasswords ? 'INCLUDED' : 'not included'));
