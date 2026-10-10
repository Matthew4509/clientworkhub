<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0






























































































declare(strict_types=1);



if (!defined('CM_ROOT')) { http_response_code(404); exit; }


require_once CM_LIB . '/words.php';
















const CM_SEAL_MAGIC = 'CWHSEAL1';



const CM_SEAL_EXT = '.cwhbak';


const CM_SEAL_WORDS = 4;













const CM_SEAL_ITERATIONS = 600000;


const CM_SEAL_MAX_ITERATIONS = 10000000;

const CM_SEAL_SALT_BYTES  = 16;
const CM_SEAL_NONCE_BYTES = 12;
const CM_SEAL_TAG_BYTES   = 16;








const CM_SEAL_FRAME = 1048576;


const CM_SEAL_MAX_FRAME = 16777216;


const CM_SEAL_FLAG_GZIP   = 1;
const CM_SEAL_FLAG_APPKEY = 2;


const CM_SEAL_HEADER_BYTES = 8 + 1 + 1 + 1 + 1 + 4 + CM_SEAL_SALT_BYTES + CM_SEAL_NONCE_BYTES + 4;


const CM_SEAL_KDF_PBKDF2 = 1;




function cm_seal_keyspace(): string
{



    return '17592186044416';
}











function cm_seal_new_key(): array
{
    $words = cm_seal_words();
    $max   = count($words) - 1;
    $out   = [];
    for ($i = 0; $i < CM_SEAL_WORDS; $i++) {
        $out[] = $words[random_int(0, $max)];
    }
    return $out;
}


function cm_seal_key_text(array $phrase): string
{
    return implode(' ', $phrase);
}






















function cm_seal_read_key(string $text): array
{
    $parts = preg_split('/[^A-Za-z]+/', strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
    $parts = is_array($parts) ? $parts : [];

    if (!$parts) {
        return ['ok' => false, 'error' => 'Type the ' . CM_SEAL_WORDS
            . ' words of the backup key, separated by spaces.'];
    }
    if (count($parts) !== CM_SEAL_WORDS) {
        return ['ok' => false, 'error' => 'A backup key is ' . CM_SEAL_WORDS . ' words, and that is '
            . count($parts) . '. Type all ' . CM_SEAL_WORDS . ', separated by spaces.'];
    }



    $words = cm_seal_words();
    $whole = array_flip($words);
    $stub  = [];
    foreach ($words as $w) {
        $stub[substr($w, 0, 4)] = $w;
    }

    $phrase = [];
    foreach ($parts as $i => $p) {
        if (isset($whole[$p])) { $phrase[] = $p; continue; }
        if (strlen($p) === 4 && isset($stub[$p])) { $phrase[] = $stub[$p]; continue; }

        $pos = $i + 1;


        $near = null;
        for ($n = min(4, strlen($p)); $n >= 2 && $near === null; $n--) {
            $head = substr($p, 0, $n);
            foreach ($words as $w) {
                if (strncmp($w, $head, $n) === 0) { $near = $w; break; }
            }
        }
        return ['ok' => false, 'error' => 'Word ' . $pos . ' of the key, "' . $p
            . '", is not one of the ' . count($words) . ' words a key is made from.'
            . ($near !== null ? ' Did you mean "' . $near . '"?' : '')
            . ' The words are all ordinary English, spelled one way — and the first four'
            . ' letters are enough if the rest is hard to read.'];
    }

    return ['ok' => true, 'phrase' => $phrase];
}








function cm_seal_derive(array $phrase, string $salt, int $iterations): string
{
    return hash_pbkdf2('sha256', cm_seal_key_text($phrase), $salt, $iterations, 32, true);
}










function cm_seal_iv(string $nonce, int $frame): string
{
    return substr($nonce, 0, 8) . pack('V', $frame);
}



















function cm_seal_writer(array $phrase, callable $out, int $flags = 0): array
{
    if (!cm_crypt_available()) {
        throw new RuntimeException('openssl with AES-256-GCM is not available — a sealed backup cannot be made on this host.');
    }
    if (count($phrase) !== CM_SEAL_WORDS) {
        throw new InvalidArgumentException('a key is ' . CM_SEAL_WORDS . ' words');
    }

    $salt  = random_bytes(CM_SEAL_SALT_BYTES);
    $nonce = random_bytes(CM_SEAL_NONCE_BYTES);
    $key   = cm_seal_derive($phrase, $salt, CM_SEAL_ITERATIONS);

    $header = CM_SEAL_MAGIC
        . chr(1)
        . chr(CM_SEAL_KDF_PBKDF2)
        . chr($flags & 0xFF)
        . chr(CM_SEAL_WORDS)
        . pack('V', CM_SEAL_ITERATIONS)
        . $salt
        . $nonce
        . pack('V', CM_SEAL_FRAME);

    $written = 0;
    $emit = static function (string $s) use ($out, &$written): void {
        $written += strlen($s);
        $out($s);
    };
    $emit($header);

    $buf    = '';
    $frame  = 0;
    $sealed = false;



    $put = static function (string $plain, bool $final) use (&$frame, $key, $nonce, $header, $emit): void {
        $tag = '';
        $ct  = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA,
                               cm_seal_iv($nonce, $frame),
                               $tag,
                               $header . pack('V', $frame) . chr($final ? 1 : 0),
                               CM_SEAL_TAG_BYTES);
        if ($ct === false) throw new RuntimeException('Encrypting the backup failed.');
        $body = $ct . $tag;
        $emit(pack('V', strlen($body)) . chr($final ? 1 : 0) . $body);
        $frame++;
    };

    return [
        'write' => static function (string $s) use (&$buf, $put, &$sealed): void {
            if ($sealed) throw new LogicException('write() after finish()');
            $buf .= $s;
            while (strlen($buf) >= CM_SEAL_FRAME) {
                $put(substr($buf, 0, CM_SEAL_FRAME), false);
                $buf = substr($buf, CM_SEAL_FRAME);
            }
        },




        'finish' => static function () use (&$buf, $put, &$sealed, $emit): void {
            if ($sealed) return;
            $sealed = true;
            $put($buf, true);
            $buf = '';
            $emit(pack('V', 0));
        },
        'bytes' => static function () use (&$written): int { return $written; },
    ];
}










function cm_seal_is_sealed(string $head): bool
{
    return strncmp($head, CM_SEAL_MAGIC, strlen(CM_SEAL_MAGIC)) === 0;
}










function cm_seal_read_header(string $head): array
{
    if (strlen($head) < CM_SEAL_HEADER_BYTES) {
        return ['ok' => false, 'error' => 'That file is too short to be a sealed backup — there is not even a header in it.'];
    }
    if (!cm_seal_is_sealed($head)) {



        return ['ok' => false, 'error' => 'That is not a sealed backup — it does not begin the way one does. '
            . 'A plain .sql or .sql.gz backup is restored without a key, further down this screen.'];
    }

    $raw  = substr($head, 0, CM_SEAL_HEADER_BYTES);
    $p    = strlen(CM_SEAL_MAGIC);
    $gen  = ord($raw[$p]);
    $kdf  = ord($raw[$p + 1]);
    $flag = ord($raw[$p + 2]);
    $words = ord($raw[$p + 3]);
    $iter = unpack('V', substr($raw, $p + 4, 4))[1];
    $salt = substr($raw, $p + 8, CM_SEAL_SALT_BYTES);
    $nonce = substr($raw, $p + 8 + CM_SEAL_SALT_BYTES, CM_SEAL_NONCE_BYTES);
    $frame = unpack('V', substr($raw, $p + 8 + CM_SEAL_SALT_BYTES + CM_SEAL_NONCE_BYTES, 4))[1];

    if ($gen !== 1) {
        return ['ok' => false, 'error' => 'That backup was sealed by a NEWER version of Client Work Hub than this one '
            . '(format ' . $gen . '). Update the site first, then restore it.'];
    }
    if ($kdf !== CM_SEAL_KDF_PBKDF2) {
        return ['ok' => false, 'error' => 'That backup states a key method this version does not have. It was not sealed here.'];
    }


    if ($words < 1 || $words > 24) {
        return ['ok' => false, 'error' => 'That backup states a key length this version will not accept.'];
    }
    if ($iter < 1 || $iter > CM_SEAL_MAX_ITERATIONS) {
        return ['ok' => false, 'error' => 'That backup states an amount of key work this version will not run. It was not sealed here.'];
    }
    if ($frame < 1 || $frame > CM_SEAL_MAX_FRAME) {
        return ['ok' => false, 'error' => 'That backup states a block size this version will not read. It was not sealed here.'];
    }

    return ['ok' => true, 'header' => [
        'raw'        => $raw,
        'generation' => $gen,
        'words'      => $words,
        'iterations' => $iter,
        'salt'       => $salt,
        'nonce'      => $nonce,
        'frame'      => $frame,
        'gzip'       => (bool) ($flag & CM_SEAL_FLAG_GZIP),
        'appkey'     => (bool) ($flag & CM_SEAL_FLAG_APPKEY),
    ]];
}















function cm_seal_reader(callable $read, array $phrase): array
{
    if (!cm_crypt_available()) {
        return ['ok' => false, 'error' => 'openssl with AES-256-GCM is not available — this host cannot open a sealed backup.'];
    }

    $head = '';
    while (strlen($head) < CM_SEAL_HEADER_BYTES) {
        $more = $read(CM_SEAL_HEADER_BYTES - strlen($head));
        if ($more === '') break;
        $head .= $more;
    }
    $parsed = cm_seal_read_header($head);
    if (!$parsed['ok']) return $parsed;
    $h = $parsed['header'];

    if (count($phrase) !== $h['words']) {
        return ['ok' => false, 'error' => 'That backup was sealed with a ' . $h['words']
            . '-word key, and ' . count($phrase) . ' words were typed.'];
    }

    $key   = cm_seal_derive($phrase, $h['salt'], $h['iterations']);
    $frame = 0;
    $ended = false;




    $any = false;

    $exact = static function (int $n) use ($read): string {
        $s = '';
        while (strlen($s) < $n) {
            $more = $read($n - strlen($s));
            if ($more === '') break;
            $s .= $more;
        }
        return $s;
    };

    $chunk = static function () use (&$frame, &$ended, &$any, $exact, $key, $h) {
        if ($ended) return null;

        $len = $exact(4);
        if (strlen($len) < 4) {
            throw new RuntimeException('That sealed backup stops in the middle of a block — the file is incomplete. '
                . 'Use another copy: there is nothing on this end that can finish it.');
        }
        $n = unpack('V', $len)[1];
        if ($n === 0) {


            throw new RuntimeException('That sealed backup ends without its last block. The file has been altered or truncated.');
        }
        if ($n < CM_SEAL_TAG_BYTES || $n > $h['frame'] + CM_SEAL_TAG_BYTES) {
            throw new RuntimeException('That sealed backup states a block size its own header does not allow. The file has been altered.');
        }

        $final = $exact(1);
        $body  = $exact($n);
        if (strlen($final) < 1 || strlen($body) < $n) {
            throw new RuntimeException('That sealed backup stops in the middle of a block — the file is incomplete. '
                . 'Use another copy: there is nothing on this end that can finish it.');
        }
        $isFinal = ord($final) === 1;

        $ct  = substr($body, 0, -CM_SEAL_TAG_BYTES);
        $tag = substr($body, -CM_SEAL_TAG_BYTES);
        $plain = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA,
                                 cm_seal_iv($h['nonce'], $frame), $tag,
                                 $h['raw'] . pack('V', $frame) . chr($isFinal ? 1 : 0));

        if ($plain === false) {
            if (!$any) {
                throw new RuntimeException('That key does not open this backup. Check the ' . $h['words']
                    . ' words you wrote down — the first four letters of each are enough. '
                    . 'If the file itself has been altered since it was made, nothing will open it.');
            }
            throw new RuntimeException('That sealed backup is damaged — it opened at the start and then failed part way through, '
                . 'which is the file and not the key. Use another copy; nothing has been written to the database.');
        }
        $any = true;
        $frame++;
        if ($isFinal) $ended = true;
        return $plain;
    };

    return ['ok' => true, 'header' => $h, 'chunk' => $chunk];
}





function cm_seal_string(string $plain, array $phrase, int $flags = 0): string
{
    $out = '';
    $w = cm_seal_writer($phrase, static function (string $s) use (&$out): void { $out .= $s; }, $flags);
    $w['write']($plain);
    $w['finish']();
    return $out;
}






function cm_seal_open_string(string $sealed, array $phrase): array
{
    $pos  = 0;
    $read = static function (int $n) use ($sealed, &$pos): string {
        $s = substr($sealed, $pos, $n);
        $pos += strlen($s);
        return $s;
    };
    $r = cm_seal_reader($read, $phrase);
    if (!$r['ok']) return $r;

    $text = '';
    try {
        while (($c = ($r['chunk'])()) !== null) $text .= $c;
    } catch (RuntimeException $ex) {
        return ['ok' => false, 'error' => $ex->getMessage()];
    }
    return ['ok' => true, 'text' => $text, 'header' => $r['header']];
}
