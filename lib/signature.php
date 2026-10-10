<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0


































declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }


const CM_SIGNATURE_MAX = 300000;


function cm_signature_problem(string $dataUri, string $name): string
{
    if (trim($name) === '')                          return 'Type your name beside your signature.';
    if ($dataUri === '')                             return 'Please sign in the box.';
    if (strlen($dataUri) > CM_SIGNATURE_MAX)         return 'That signature is too large — press Clear and sign once more.';
    if (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $dataUri)) return 'That signature could not be read — press Clear and sign once more.';
    $png = base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true);
    if ($png === false || strncmp($png, "\x89PNG\r\n\x1a\n", 8) !== 0) return 'That signature could not be read — press Clear and sign once more.';







    if (strlen($png) < 24 || substr($png, 12, 4) !== 'IHDR') return 'That signature could not be read — press Clear and sign once more.';
    $wh = unpack('Nw/Nh', substr($png, 16, 8));
    if ($wh['w'] < 1 || $wh['h'] < 1 || $wh['w'] > CM_SIGNATURE_MAX_WIDTH || $wh['h'] > CM_SIGNATURE_MAX_HEIGHT) {
        return 'That signature could not be read — press Clear and sign once more.';
    }
    return '';
}


const CM_SIGNATURE_MAX_WIDTH  = 6000;
const CM_SIGNATURE_MAX_HEIGHT = 1200;









function cm_signature_img(?string $dataUri, string $name, string $class = 'ob-signature'): string
{
    $dataUri = trim((string) $dataUri);
    if ($dataUri === '') return '';
    if (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $dataUri)) return '';
    return '<img class="' . e($class) . '" src="' . e($dataUri) . '"'
         . ' alt="Signature of ' . e($name) . '">';
}
