<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0





































declare(strict_types=1);

if (!defined('CM_ROOT') && !defined('CM_REQ_CALLER')) { http_response_code(404); exit; }



const CM_REQ_MIN_PHP = '8.1.0';

















function cm_requirements(): array
{
    return [
        ['ext' => 'pdo_mysql', 'probe' => '', 'required' => true,
         'for'  => 'Connecting to the MySQL/MariaDB database.',
         'lost' => 'Nothing works. The installer refuses to run and says so.'],

        ['ext' => 'mbstring', 'probe' => '', 'required' => true,
         'for'  => 'Multi-byte text handling (UTF-8 names, notes and email).',
         'lost' => 'Nothing works. The installer refuses to run and says so.'],

        ['ext' => 'openssl', 'probe' => '', 'required' => true,
         'for'  => 'AES-256-GCM encryption of stored credentials, and TLS from PHP.',
         'lost' => 'Nothing works — and client passwords could not be stored safely if it did.'],

        ['ext' => 'session', 'probe' => 'session_start()', 'required' => true,
         'for'  => 'Sessions.', 'lost' => 'Nobody can sign in.'],

        ['ext' => 'json', 'probe' => 'json_encode()', 'required' => true,
         'for'  => 'JSON encoding.', 'lost' => 'Nothing works.'],

        ['ext' => 'filter', 'probe' => 'filter_var()', 'required' => true,
         'for'  => 'Validating email and web addresses.', 'lost' => 'Nothing works.'],

        ['ext' => 'ctype', 'probe' => 'ctype_digit()', 'required' => true,
         'for'  => 'Character-class checks used throughout validation.',
         'lost' => 'Nothing works.'],

        ['ext' => 'pcre', 'probe' => 'preg_match()', 'required' => true,
         'for'  => 'Regular expressions.', 'lost' => 'Nothing works.'],

        ['ext' => 'hash', 'probe' => 'hash_hmac()', 'required' => true,
         'for'  => 'HMAC-signed links and SHA-256 file checksums.',
         'lost' => 'Nothing works.'],

        ['ext' => 'zip', 'probe' => 'ZipArchive', 'required' => false,
         'for'  => 'Reading .zip archives.',
         'lost' => 'No software updates from Admin → Update. Files have to be replaced '
                 . 'by hand in File Manager.'],

        ['ext' => 'curl', 'probe' => 'curl_init()', 'required' => false,
         'for'  => 'Outbound HTTPS requests.',
         'lost' => 'Card and PayPal payments cannot work. Updating by upload is unaffected.'],

        ['ext' => 'sodium', 'probe' => 'sodium_crypto_sign_verify_detached()', 'required' => false,
         'for'  => 'Verifying Ed25519 signatures.',
         'lost' => 'An uploaded release is accepted on its checksums alone, and the '
                 . 'screen says its signature could not be checked. Upload only a '
                 . 'release you downloaded yourself.'],

        ['ext' => 'zlib', 'probe' => 'deflate_init()', 'required' => false,
         'for'  => 'Gzip compression.',
         'lost' => 'Database backups still download, just as a plain .sql file rather '
                 . 'than a .sql.gz about a tenth the size.'],

        ['ext' => 'gd', 'probe' => 'imagecreatefrompng()', 'required' => false,
         'for'  => 'Reading and writing PNG and JPEG images.',
         'lost' => 'Logo upload still works and is still validated, but the file is kept '
                 . 'exactly as uploaded rather than re-encoded to strip anything hidden '
                 . 'alongside the picture.'],
    ];
}


function cm_req_have(array $row): bool
{
    if (extension_loaded($row['ext'])) return true;
    $probe = (string) $row['probe'];
    if ($probe === '') return false;
    return str_ends_with($probe, '()')
        ? function_exists(substr($probe, 0, -2))
        : class_exists($probe);
}


function cm_requirements_state(): array
{
    $out = [];
    foreach (cm_requirements() as $row) {
        $row['have'] = cm_req_have($row);
        $out[] = $row;
    }
    return $out;
}

function cm_req_php_ok(): bool
{
    return version_compare(PHP_VERSION, CM_REQ_MIN_PHP, '>=');
}








function cm_requirements_missing(?bool $required = null): array
{
    $out = [];
    foreach (cm_requirements_state() as $row) {
        if ($row['have']) continue;
        if ($required !== null && $row['required'] !== $required) continue;
        $out[] = $row['ext'];
    }
    return $out;
}













function cm_requirements_host_message(string $host = ''): string
{
    $missing = cm_requirements_state();
    $missing = array_values(array_filter($missing, static fn(array $r): bool => !$r['have']));
    $phpOk   = cm_req_php_ok();
    if (!$missing && $phpOk) return '';

    $site = trim($host) !== '' ? trim($host) : 'my website';

    $lines = [];
    $lines[] = 'Hello,';
    $lines[] = '';
    $lines[] = 'I am running an application on ' . $site . ' and it needs a few things';
    $lines[] = 'from PHP that are not switched on at the moment. This domain is';
    $lines[] = 'currently on PHP ' . PHP_VERSION . '.';
    $lines[] = '';

    if (!$phpOk) {
        $lines[] = 'Please could you move this domain to PHP ' . CM_REQ_MIN_PHP . ' or newer.';
        $lines[] = '(On cPanel that is MultiPHP Manager, set per domain — not PHP Selector,';
        $lines[] = 'which only changes the account default.)';
        $lines[] = '';
    }

    if ($missing) {
        $req = array_values(array_filter($missing, static fn(array $r): bool => $r['required']));
        $opt = array_values(array_filter($missing, static fn(array $r): bool => !$r['required']));

        $lines[] = 'Please could you enable these PHP extensions for this domain:';
        $lines[] = '';
        foreach ($req as $r) {
            $lines[] = '  * ' . $r['ext'] . ' - ' . $r['for'] . ' (the application will not run without this)';
        }
        foreach ($opt as $r) {
            $lines[] = '  * ' . $r['ext'] . ' - ' . $r['for'];
        }
        $lines[] = '';
        $lines[] = 'On cPanel these are under Select PHP Version, then the Extensions tab,';
        $lines[] = 'and they need ticking on the PHP version this domain actually uses.';
        $lines[] = '';
    }

    $lines[] = 'Thank you.';

    return implode("\n", $lines) . "\n";
}
