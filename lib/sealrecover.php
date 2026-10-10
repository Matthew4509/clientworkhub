<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0



































declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }

require_once CM_LIB . '/seal.php';










function cm_seal_read_key_partial(string $text): array
{
    $parts = preg_split('/[^a-z?]+/', strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
    $parts = is_array($parts) ? $parts : [];

    if (!$parts) {
        return ['ok' => false, 'error' => 'Type the ' . CM_SEAL_WORDS . ' words of the backup key. '
            . 'If one of them cannot be read, put a ? in its place.'];
    }
    if (count($parts) !== CM_SEAL_WORDS) {
        return ['ok' => false, 'error' => 'A backup key is ' . CM_SEAL_WORDS . ' words, and that is '
            . count($parts) . '. Type all ' . CM_SEAL_WORDS . ' &mdash; a ? for any one you cannot read.'];
    }

    $blanks = array_keys($parts, '?', true);
    if (count($blanks) > 1) {
        $n = count(cm_seal_words());
        return ['ok' => false, 'error' => 'Only ONE word can be left out. With two missing there are '
            . number_format($n * $n) . ' keys to try, which on a server takes months rather than minutes, '
            . 'so it is refused rather than left running for ever. Look at the paper once more &mdash; '
            . 'the first four letters of a word are enough to identify it.'];
    }

    $words = cm_seal_words();
    $whole = array_flip($words);
    $stub  = [];
    foreach ($words as $w) $stub[substr($w, 0, 4)] = $w;

    $phrase  = [];
    $unknown = null;
    foreach ($parts as $i => $p) {
        if ($p === '?') { $phrase[] = null; $unknown = $i; continue; }
        if (isset($whole[$p])) { $phrase[] = $p; continue; }
        if (strlen($p) === 4 && isset($stub[$p])) { $phrase[] = $stub[$p]; continue; }
        return ['ok' => false, 'error' => 'Word ' . ($i + 1) . ' of the key, "' . $p . '", is not one of the '
            . count($words) . ' words a key is made from. Check what you wrote down &mdash; the first four '
            . 'letters are enough &mdash; or put a ? in its place and let the search find it.'];
    }

    return ['ok' => true, 'phrase' => $phrase, 'unknown' => $unknown];
}


function cm_seal_derive_seconds(int $iterations = CM_SEAL_ITERATIONS): float
{
    $salt = random_bytes(CM_SEAL_SALT_BYTES);
    $t = microtime(true);
    hash_pbkdf2('sha256', 'measuring the cost of one guess', $salt, $iterations, 32, true);
    return max(0.000001, microtime(true) - $t);
}






function cm_seal_first_frame(string $head): array
{
    $parsed = cm_seal_read_header($head);
    if (!$parsed['ok']) return $parsed;



    $env = $parsed['header'];

    $at = CM_SEAL_HEADER_BYTES;
    if (strlen($head) < $at + 5) {
        return ['ok' => false, 'error' => 'That sealed backup is cut short — there is not enough of it to open.'];
    }
    $n = unpack('V', substr($head, $at, 4))[1];
    if ($n < CM_SEAL_TAG_BYTES || $n > $env['frame'] + CM_SEAL_TAG_BYTES) {
        return ['ok' => false, 'error' => 'That sealed backup states a block size its own header does not allow.'];
    }
    $final = ord($head[$at + 4]) === 1;
    $body  = substr($head, $at + 5, $n);
    if (strlen($body) < $n) {
        return ['ok' => false, 'error' => 'That sealed backup is cut short — its first block is incomplete.'];
    }
    return ['ok' => true, 'envelope' => $env, 'frame' => $body, 'final' => $final];
}


















function cm_seal_recover_slice(array $envelope, string $firstFrame, bool $firstIsFinal,
                               array $phrase, int $unknown, int $from, float $budget): array
{
    $words = cm_seal_words();
    $total = count($words);
    $ct    = substr($firstFrame, 0, -CM_SEAL_TAG_BYTES);
    $tag   = substr($firstFrame, -CM_SEAL_TAG_BYTES);
    $aad   = $envelope['raw'] . pack('V', 0) . chr($firstIsFinal ? 1 : 0);
    $iv    = cm_seal_iv($envelope['nonce'], 0);

    $started = microtime(true);
    $i = max(0, $from);
    for (; $i < $total; $i++) {
        $try = $phrase;
        $try[$unknown] = $words[$i];
        $key = cm_seal_derive($try, $envelope['salt'], $envelope['iterations']);
        if (openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad) !== false) {
            return ['found' => $words[$i], 'next' => $i + 1, 'tried' => $i - $from + 1, 'done' => true];
        }


        if (microtime(true) - $started >= $budget) { $i++; break; }
    }
    return ['found' => null, 'next' => $i, 'tried' => $i - $from, 'done' => $i >= $total];
}


function cm_seal_wait_label(float $seconds): string
{
    if ($seconds < 90) return 'about ' . max(1, (int) round($seconds)) . ' seconds';
    $m = (int) round($seconds / 60);
    if ($m < 90) return 'about ' . $m . ' minute' . ($m === 1 ? '' : 's');
    $h = $seconds / 3600;
    return 'about ' . number_format($h, 1) . ' hours';
}
