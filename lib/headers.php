<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0


























declare(strict_types=1);

function cm_send_security_headers(): void
{

    if (!headers_sent()) header_remove('X-Powered-By');
    $csp = "default-src 'self'; "
         . "script-src 'self'; "

         . "style-src 'self'; "
         . "font-src 'self'; "
         . "img-src 'self' data:; "
         . "connect-src 'self'; "
         . "frame-src 'none'; "
         . "form-action 'self'; "
         . "frame-ancestors 'none'; "
         . "base-uri 'none'; "
         . "object-src 'none'";
    header('Content-Security-Policy: ' . $csp);
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');


    header('X-Frame-Options: DENY');






    if (function_exists('cm_is_https') && cm_is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    header('Permissions-Policy: geolocation=(), camera=(), microphone=(), '
         . 'payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=(), '
         . 'interest-cohort=()');
}
