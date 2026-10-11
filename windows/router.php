<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0









declare(strict_types=1);

$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

if ($path === '/' || $path === '') {
    header('Location: /admin/', true, 302);
    return true;
}

$deny = '#(^|/)(storage|lib|migrations|tests|tools|windows)(/|$)#i';
$dots = '#(^|/)\.[^/]+(/|$)#';
$files = '#(^|/)(config\.php|config\.sample\.php|install\.php|MANIFEST\.txt|MANIFEST\.sig|VERSION\.txt|[^/]*\.md|[^/]*\.sql|[^/]*\.bat|[^/]*\.cmd|[^/]*\.ps1)$#i';

if (preg_match($deny, $path) || preg_match($dots, $path) || preg_match($files, $path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    return true;
}

return false;
