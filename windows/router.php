<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0









declare(strict_types=1);




$host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
if (!in_array($host, ['127.0.0.1', 'localhost', '[::1]'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Client Work Hub (home edition) only answers on this PC.\n";
    return true;
}






$path = str_replace('\\', '/', rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH)));
$path = (string) preg_replace('#[. ]+(?=/|$)#', '', $path);

if ($path === '/' || $path === '') {
    header('Location: /admin/', true, 302);
    return true;
}

$deny = '#(^|/)(storage|lib|migrations|tests|tools|windows)(/|$)#i';
$dots = '#(^|/)\.[^/]+(/|$)#';

$files = '#(^|/)(config\.php|config\.sample\.php|install\.php|MANIFEST\.txt|MANIFEST\.sig|VERSION\.txt|[^/]*\.md|[^/]*\.sql|[^/]*\.bat|[^/]*\.cmd|[^/]*\.ps1)(/|$)#i';

if (str_contains($path, ':') || str_contains($path, "\0")
    || preg_match($deny, $path) || preg_match($dots, $path) || preg_match($files, $path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    return true;
}

return false;
