<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0



































declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/publicview.php';
require_once CM_LIB . '/branding.php';



if (!cm_home()) cm_public_require_enabled();

$path = cm_logo_file();
if ($path === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Not found\n");
}

$mime  = cm_logo_mime();
$mtime = (int) @filemtime($path);
$size  = (int) @filesize($path);
$etag  = '"' . md5($mime . '|' . $mtime . '|' . $size) . '"';





header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="logo.' . cm_logo_ext() . '"');
header('Cache-Control: public, max-age=86400');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');


if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

readfile($path);
