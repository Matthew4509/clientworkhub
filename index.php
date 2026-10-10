<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0













































declare(strict_types=1);

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
















if (is_readable(__DIR__ . '/lib/headers.php')) {
    require __DIR__ . '/lib/headers.php';
}





if (function_exists('cm_send_security_headers')) {
    cm_send_security_headers();
}

exit("Not found\n");
