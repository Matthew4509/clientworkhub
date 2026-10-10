<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0




































declare(strict_types=1);











if (!defined('CM_ROOT')) { http_response_code(404); exit; }



const CM_QR_VERSIONS = [
    1  => [16,  10, [[1, 16]]],
    2  => [28,  16, [[1, 28]]],
    3  => [44,  26, [[1, 44]]],
    4  => [64,  18, [[2, 32]]],
    5  => [86,  24, [[2, 43]]],
    6  => [108, 16, [[4, 27]]],
    7  => [124, 18, [[4, 31]]],
    8  => [154, 22, [[2, 38], [2, 39]]],
    9  => [182, 22, [[3, 36], [2, 37]]],
    10 => [216, 26, [[4, 43], [1, 44]]],
];


const CM_QR_ALIGN = [
    1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
    6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
    10 => [6, 28, 50],
];



function cm_qr_gf(): array
{
    static $t = null;
    if ($t !== null) return $t;
    $exp = array_fill(0, 512, 0);
    $log = array_fill(0, 256, 0);
    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if ($x & 0x100) $x ^= 0x11d;
    }
    for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
    return $t = [$exp, $log];
}


function cm_qr_generator(int $n): array
{
    [$exp, $log] = cm_qr_gf();
    $g = [1];
    for ($i = 0; $i < $n; $i++) {
        $next = array_fill(0, count($g) + 1, 0);
        foreach ($g as $j => $c) {
            $next[$j]     ^= $c;
            $next[$j + 1] ^= ($c === 0 ? 0 : $exp[($log[$c] + $i) % 255]);
        }
        $g = $next;
    }
    return $g;
}


function cm_qr_ecc(array $data, int $n): array
{
    [$exp, $log] = cm_qr_gf();
    $gen = cm_qr_generator($n);
    $rem = array_fill(0, $n, 0);
    foreach ($data as $d) {
        $factor = $d ^ $rem[0];
        array_shift($rem);
        $rem[] = 0;
        if ($factor !== 0) {
            $lf = $log[$factor];
            foreach ($gen as $i => $gc) {
                if ($i === 0 || $gc === 0) continue;
                $rem[$i - 1] ^= $exp[($log[$gc] + $lf) % 255];
            }
        }
    }
    return $rem;
}






function cm_qr_matrix(string $text, int $forceMask = -1): array
{
    $len = strlen($text);
    $version = 0;
    foreach (CM_QR_VERSIONS as $v => [$dataCw, , ]) {

        $header = ($v >= 10) ? 3 : 2;
        if ($len + $header <= $dataCw) { $version = $v; break; }
    }
    if ($version === 0) {
        throw new RuntimeException('QR: ' . $len . ' bytes is more than this encoder handles (213 max).');
    }

    [$dataCw, $ecPerBlock, $groups] = CM_QR_VERSIONS[$version];


    $bits = '';
    $bits .= '0100';
    $bits .= str_pad(decbin($len), $version >= 10 ? 16 : 8, '0', STR_PAD_LEFT);
    for ($i = 0; $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
    }
    $capacity = $dataCw * 8;
    $bits .= str_repeat('0', min(4, $capacity - strlen($bits)));
    if (strlen($bits) % 8) $bits .= str_repeat('0', 8 - strlen($bits) % 8);
    $pad = ['11101100', '00010001'];
    for ($i = 0; strlen($bits) < $capacity; $i++) $bits .= $pad[$i % 2];

    $codewords = [];
    for ($i = 0; $i < strlen($bits); $i += 8) $codewords[] = bindec(substr($bits, $i, 8));


    $dataBlocks = [];
    $eccBlocks  = [];
    $at = 0;
    foreach ($groups as [$count, $size]) {
        for ($b = 0; $b < $count; $b++) {
            $block = array_slice($codewords, $at, $size);
            $at += $size;
            $dataBlocks[] = $block;
            $eccBlocks[]  = cm_qr_ecc($block, $ecPerBlock);
        }
    }


    $out = [];
    $maxData = max(array_map('count', $dataBlocks));
    for ($i = 0; $i < $maxData; $i++) {
        foreach ($dataBlocks as $b) if (isset($b[$i])) $out[] = $b[$i];
    }
    for ($i = 0; $i < $ecPerBlock; $i++) {
        foreach ($eccBlocks as $b) if (isset($b[$i])) $out[] = $b[$i];
    }

    $final = '';
    foreach ($out as $cw) $final .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);


    $size = 17 + 4 * $version;
    $m    = array_fill(0, $size, array_fill(0, $size, 0));
    $fn   = array_fill(0, $size, array_fill(0, $size, 0));

    $finder = static function (int $r, int $c) use (&$m, &$fn, $size): void {
        for ($dr = -1; $dr <= 7; $dr++) {
            for ($dc = -1; $dc <= 7; $dc++) {
                $rr = $r + $dr; $cc = $c + $dc;
                if ($rr < 0 || $cc < 0 || $rr >= $size || $cc >= $size) continue;
                $in = $dr >= 0 && $dr <= 6 && $dc >= 0 && $dc <= 6;
                $on = $in && (
                    $dr === 0 || $dr === 6 || $dc === 0 || $dc === 6 ||
                    ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4)
                );
                $m[$rr][$cc]  = $on ? 1 : 0;
                $fn[$rr][$cc] = 1;
            }
        }
    };
    $finder(0, 0);
    $finder(0, $size - 7);
    $finder($size - 7, 0);


    for ($i = 8; $i < $size - 8; $i++) {
        $v = ($i % 2 === 0) ? 1 : 0;
        $m[6][$i] = $v; $fn[6][$i] = 1;
        $m[$i][6] = $v; $fn[$i][6] = 1;
    }


    $centres = CM_QR_ALIGN[$version];
    foreach ($centres as $r) {
        foreach ($centres as $c) {
            if (($r === 6 && $c === 6)
             || ($r === 6 && $c === $size - 7)
             || ($r === $size - 7 && $c === 6)) continue;
            for ($dr = -2; $dr <= 2; $dr++) {
                for ($dc = -2; $dc <= 2; $dc++) {
                    $on = max(abs($dr), abs($dc)) !== 1;
                    $m[$r + $dr][$c + $dc]  = $on ? 1 : 0;
                    $fn[$r + $dr][$c + $dc] = 1;
                }
            }
        }
    }


    $m[$size - 8][8] = 1; $fn[$size - 8][8] = 1;
    for ($i = 0; $i < 9; $i++) {
        if (!$fn[8][$i])          { $fn[8][$i] = 1; }
        if (!$fn[$i][8])          { $fn[$i][8] = 1; }
    }
    for ($i = 0; $i < 8; $i++) {
        $fn[8][$size - 1 - $i] = 1;
        $fn[$size - 1 - $i][8] = 1;
    }

    if ($version >= 7) {
        for ($i = 0; $i < 6; $i++) {
            for ($j = 0; $j < 3; $j++) {
                $fn[$i][$size - 11 + $j] = 1;
                $fn[$size - 11 + $j][$i] = 1;
            }
        }
    }


    $bitAt = 0;
    $total = strlen($final);
    $up = true;
    for ($col = $size - 1; $col > 0; $col -= 2) {
        if ($col === 6) $col--;
        for ($i = 0; $i < $size; $i++) {
            $row = $up ? $size - 1 - $i : $i;
            for ($c = 0; $c < 2; $c++) {
                $cc = $col - $c;
                if ($fn[$row][$cc]) continue;
                $m[$row][$cc] = $bitAt < $total ? (int) $final[$bitAt] : 0;
                $bitAt++;
            }
        }
        $up = !$up;
    }


    $best = null; $bestScore = PHP_INT_MAX; $bestMask = 0;
    $masks = $forceMask >= 0 ? [$forceMask] : range(0, 7);
    foreach ($masks as $mask) {
        $cand = cm_qr_apply_mask($m, $fn, $size, $mask);
        cm_qr_place_format($cand, $size, $mask);
        if ($version >= 7) cm_qr_place_version($cand, $size, $version);
        $score = $forceMask >= 0 ? 0 : cm_qr_penalty($cand, $size);
        if ($score < $bestScore) { $bestScore = $score; $best = $cand; $bestMask = $mask; }
    }
    return $best;
}

function cm_qr_apply_mask(array $m, array $fn, int $size, int $mask): array
{
    for ($r = 0; $r < $size; $r++) {
        for ($c = 0; $c < $size; $c++) {
            if ($fn[$r][$c]) continue;
            $flip = match ($mask) {
                0 => ($r + $c) % 2 === 0,
                1 => $r % 2 === 0,
                2 => $c % 3 === 0,
                3 => ($r + $c) % 3 === 0,
                4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
                5 => (($r * $c) % 2) + (($r * $c) % 3) === 0,
                6 => ((($r * $c) % 2) + (($r * $c) % 3)) % 2 === 0,
                7 => ((($r + $c) % 2) + (($r * $c) % 3)) % 2 === 0,
            };
            if ($flip) $m[$r][$c] ^= 1;
        }
    }
    return $m;
}


function cm_qr_place_format(array &$m, int $size, int $mask): void
{
    $data = (0b00 << 3) | $mask;
    $rem  = $data;
    for ($i = 0; $i < 10; $i++) {
        $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
    }
    $bits = (($data << 10) | $rem) ^ 0x5412;






    for ($i = 0; $i <= 5; $i++) $m[8][$i] = ($bits >> (14 - $i)) & 1;
    $m[8][7] = ($bits >> 8) & 1;
    $m[8][8] = ($bits >> 7) & 1;
    $m[7][8] = ($bits >> 6) & 1;
    for ($r = 0; $r <= 5; $r++) $m[$r][8] = ($bits >> $r) & 1;





    for ($i = 0; $i <= 6; $i++) $m[$size - 1 - $i][8] = ($bits >> (14 - $i)) & 1;
    for ($i = 0; $i <= 7; $i++) $m[8][$size - 8 + $i] = ($bits >> (7 - $i)) & 1;
    $m[$size - 8][8] = 1;
}


function cm_qr_place_version(array &$m, int $size, int $version): void
{
    $rem = $version;
    for ($i = 0; $i < 12; $i++) {
        $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1f25);
    }
    $bits = ($version << 12) | $rem;
    for ($i = 0; $i < 18; $i++) {
        $bit = ($bits >> $i) & 1;
        $a = intdiv($i, 3);
        $b = $size - 11 + ($i % 3);
        $m[$a][$b] = $bit;
        $m[$b][$a] = $bit;
    }
}


function cm_qr_penalty(array $m, int $size): int
{
    $score = 0;


    for ($i = 0; $i < $size; $i++) {
        foreach ([true, false] as $byRow) {
            $run = 1;
            for ($j = 1; $j < $size; $j++) {
                $a = $byRow ? $m[$i][$j]     : $m[$j][$i];
                $b = $byRow ? $m[$i][$j - 1] : $m[$j - 1][$i];
                if ($a === $b) {
                    $run++;
                } else {
                    if ($run >= 5) $score += 3 + ($run - 5);
                    $run = 1;
                }
            }
            if ($run >= 5) $score += 3 + ($run - 5);
        }
    }


    for ($r = 0; $r < $size - 1; $r++) {
        for ($c = 0; $c < $size - 1; $c++) {
            $v = $m[$r][$c];
            if ($v === $m[$r][$c + 1] && $v === $m[$r + 1][$c] && $v === $m[$r + 1][$c + 1]) {
                $score += 3;
            }
        }
    }


    $p1 = [1,0,1,1,1,0,1,0,0,0,0];
    $p2 = [0,0,0,0,1,0,1,1,1,0,1];
    for ($r = 0; $r < $size; $r++) {
        for ($c = 0; $c <= $size - 11; $c++) {
            $row = array_slice($m[$r], $c, 11);
            if ($row === $p1 || $row === $p2) $score += 40;
            $col = [];
            for ($k = 0; $k < 11; $k++) $col[] = $m[$c + $k][$r];
            if ($col === $p1 || $col === $p2) $score += 40;
        }
    }


    $dark = 0;
    foreach ($m as $row) $dark += array_sum($row);
    $pct = ($dark * 100) / ($size * $size);
    $score += (int) (abs(intdiv((int) floor($pct), 5) * 5 - 50) / 5) * 10;

    return $score;
}







function cm_qr_svg(string $text, int $scale = 5, int $quiet = 4): string
{
    $m = cm_qr_matrix($text);
    $n = count($m);
    $dim = ($n + $quiet * 2) * $scale;

    $path = '';
    foreach ($m as $r => $row) {
        foreach ($row as $c => $on) {
            if (!$on) continue;
            $path .= 'M' . (($c + $quiet) * $scale) . ' ' . (($r + $quiet) * $scale)
                   . 'h' . $scale . 'v' . $scale . 'h-' . $scale . 'z';
        }
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dim . '" height="' . $dim . '" '
         . 'viewBox="0 0 ' . $dim . ' ' . $dim . '" role="img" '
         . 'aria-label="QR code for setting up your authenticator app">'
         . '<rect width="' . $dim . '" height="' . $dim . '" fill="#fff"/>'
         . '<path d="' . $path . '" fill="#000"/>'
         . '</svg>';
}
