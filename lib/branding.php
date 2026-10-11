<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0


























































declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }





require_once CM_LIB . '/format.php';



const CM_LOGO_MAX_BYTES = 524288;



const CM_LOGO_MAX_PIXELS = 2000;
const CM_LOGO_MIN_PIXELS = 8;





















function cm_business_block(): array
{
    $s = static function (string $k): string {
        try { return trim((string) (cm_setting($k, '') ?? '')); }
        catch (Throwable $ex) { return ''; }
    };

    $town = trim(implode(' ', array_filter([$s('business.suburb'), $s('business.state'),
                                            $s('business.postcode')])));
    $lines = array_values(array_filter([$s('business.address'), $town, $s('business.country')]));

    return [
        'name'  => cm_business_display_name(),
        'lines' => $lines,
        'phone' => $s('public.phone'),
        'email' => $s('public.email'),
        'logo'  => cm_logo_url(),
    ];
}

function cm_logo_types(): array
{
    return [
        IMAGETYPE_PNG  => ['ext' => 'png', 'mime' => 'image/png',  'label' => 'PNG'],
        IMAGETYPE_JPEG => ['ext' => 'jpg', 'mime' => 'image/jpeg', 'label' => 'JPEG'],
    ];
}


function cm_logo_dir(): string
{
    return CM_STORAGE . '/branding';
}


function cm_logo_ext(): string
{
    $ext = trim((string) cm_setting('public.logo', ''));
    foreach (cm_logo_types() as $t) {
        if ($t['ext'] === $ext) return $ext;
    }
    return '';
}




function cm_logo_file(): ?string
{
    $ext = cm_logo_ext();
    if ($ext === '') return null;
    $path = cm_logo_dir() . '/logo.' . $ext;
    return is_file($path) ? $path : null;
}

function cm_logo_mime(): string
{
    $ext = cm_logo_ext();
    foreach (cm_logo_types() as $t) {
        if ($t['ext'] === $ext) return $t['mime'];
    }
    return 'application/octet-stream';
}









function cm_logo_url(): string
{
    $path = cm_logo_file();
    if ($path === null) return '';
    return cm_url('portal/logo.php') . '?v=' . rawurlencode((string) @filemtime($path));
}


function cm_logo_size(): ?array
{
    $path = cm_logo_file();
    if ($path === null) return null;
    $info = @getimagesize($path);
    if (!is_array($info)) return null;
    return ['w' => (int) $info[0], 'h' => (int) $info[1], 'bytes' => (int) @filesize($path)];
}






function cm_logo_advice(): string
{
    if (cm_home()) {
        return 'About 400 × 100 pixels, and under 200 KB. A PNG with a transparent '
             . 'background sits best on the invoice; a JPEG will show its own white corners.';
    }
    return 'About 400 × 100 pixels, and under 200 KB. It is drawn 36 pixels '
         . 'tall, so 72 covers a retina screen — bigger than that is bytes '
         . 'your clients download and never see. A PNG with a transparent '
         . 'background sits best on the header; a JPEG will show its own '
         . 'white corners.';
}








function cm_logo_store(?array $file): array
{
    $fail = static fn (string $why): array => ['ok' => false, 'error' => $why];




    if ($file === null) {
        $sent = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $cap  = cm_ini_bytes((string) ini_get('post_max_size'));
        if ($sent > 0 && $cap > 0 && $sent > $cap) {
            return $fail('That file is larger than this server accepts in one request ('
                       . cm_bytes_label($cap) . '). ' . cm_logo_advice());
        }
        return $fail('No file arrived. Choose one and try again.');
    }


    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        return $fail(match ($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                'That file is too big — the limit is '
                . cm_bytes_label(min(CM_LOGO_MAX_BYTES, cm_ini_bytes((string) ini_get('upload_max_filesize'))))
                . '. ' . cm_logo_advice(),
            UPLOAD_ERR_PARTIAL   => 'Only part of the file arrived. Try again.',
            UPLOAD_ERR_NO_FILE   => 'No file was chosen.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE =>
                'This server could not write the upload to disk. That is a hosting '
                . 'setting, not something you did — a host will know what it means.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension refused the upload.',
            default              => 'The upload failed (code ' . $err . ').',
        });
    }



    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return $fail('That upload did not arrive properly. Try again.');
    }


    $bytes = (int) @filesize($tmp);
    if ($bytes <= 0) {
        return $fail('That file is empty.');
    }
    if ($bytes > CM_LOGO_MAX_BYTES) {
        return $fail('That file is ' . cm_bytes_label($bytes) . ' and the limit is '
                   . cm_bytes_label(CM_LOGO_MAX_BYTES) . '. ' . cm_logo_advice());
    }





    $info = @getimagesize($tmp);
    if (!is_array($info) || !isset($info[0], $info[1], $info[2])) {
        return $fail('That is not an image file, whatever its name says. '
                   . 'A PNG or a JPEG, please.');
    }

    $types = cm_logo_types();
    $type  = (int) $info[2];
    if (!isset($types[$type])) {
        return $fail('Only PNG and JPEG can be used. An SVG is refused on purpose: '
                   . 'it can contain scripts, and it would be served from your own '
                   . 'address, which is the one place a browser trusts.');
    }







    $w = (int) $info[0];
    $h = (int) $info[1];
    if ($w > CM_LOGO_MAX_PIXELS || $h > CM_LOGO_MAX_PIXELS) {
        return $fail('That image is ' . $w . ' × ' . $h . ' pixels, which is far larger '
                   . 'than a logo needs. ' . cm_logo_advice());
    }
    if ($w < CM_LOGO_MIN_PIXELS || $h < CM_LOGO_MIN_PIXELS) {
        return $fail('That image is only ' . $w . ' × ' . $h . ' pixels. ' . cm_logo_advice());
    }








    if ($type === IMAGETYPE_JPEG && cm_logo_jpeg_truncated($tmp)) {
        return $fail('That JPEG is cut short — the file ends before the picture does, usually because '
                   . 'a download or a copy did not finish. Save it again and upload the new copy.');
    }







    $checked = true;
    if ($type === IMAGETYPE_PNG) {
        $png = cm_logo_png_decodes($tmp);
        if ($png === false) {
            return $fail('That PNG is damaged — it starts like a picture but the rest cannot be read. '
                       . 'Open it in an image editor, save it again as PNG, and upload the new copy.');
        }
        $checked = $png === true;
    }


    $dir = cm_logo_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return $fail('Could not create the folder for it. Check that storage/ is writable.');
    }

    $ext  = (string) $types[$type]['ext'];
    $dest = $dir . '/logo.' . $ext;

    if (!@move_uploaded_file($tmp, $dest)) {
        return $fail('Could not save the file. Check that storage/ is writable.');
    }
    @chmod($dest, 0644);





    cm_logo_reencode($dest, $type);





    foreach ($types as $t) {
        if ($t['ext'] !== $ext) @unlink($dir . '/logo.' . $t['ext']);
    }

    cm_setting_set('public.logo', $ext, 'logo upload');
    $size = @getimagesize($dest);
    if (is_array($size)) { $w = (int) $size[0]; $h = (int) $size[1]; }
    return ['ok' => true, 'error' => '', 'w' => $w, 'h' => $h,
            'bytes' => (int) @filesize($dest), 'label' => (string) $types[$type]['label'],
            'note' => $checked ? '' : 'This server has no image library (GD), so the picture itself could not be checked.'];
}







function cm_logo_jpeg_truncated(string $path): bool
{
    if (!function_exists('imagecreatefromjpeg')) return false;
    $was = ini_get('gd.jpeg_ignore_warning');
    $premature = false;
    ini_set('gd.jpeg_ignore_warning', '0');
    set_error_handler(static function (int $no, string $msg) use (&$premature): bool {
        if (stripos($msg, 'Premature end') !== false) $premature = true;
        return true;
    });
    try {
        $img = imagecreatefromjpeg($path);
        unset($img);
    } catch (Throwable $ex) {

    } finally {
        restore_error_handler();
        ini_set('gd.jpeg_ignore_warning', $was === false ? '1' : (string) $was);
    }
    return $premature;
}





function cm_logo_png_decodes(string $path): ?bool
{
    if (!function_exists('imagecreatefrompng')) return null;
    $warned = false;
    set_error_handler(static function () use (&$warned): bool { $warned = true; return true; });
    try {
        $img = imagecreatefrompng($path);
    } catch (Throwable $ex) {
        $img = false;
    } finally {
        restore_error_handler();
    }
    $ok = $img !== false && !$warned;
    unset($img);
    return $ok;
}





function cm_logo_orient(GdImage $img, string $path): GdImage
{
    if (!function_exists('exif_read_data')) return $img;
    $exif = @exif_read_data($path);
    $o = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    $turned = match ($o) {
        3, 4 => @imagerotate($img, 180, 0),
        5, 6 => @imagerotate($img, -90, 0),
        7, 8 => @imagerotate($img, 90, 0),
        default => $img,
    };
    if (!$turned instanceof GdImage) return $img;
    if (in_array($o, [2, 4, 5, 7], true)) @imageflip($turned, IMG_FLIP_HORIZONTAL);
    return $turned;
}





function cm_logo_reencode(string $path, int $type): void
{
    if (!function_exists('imagecreatefrompng') || !function_exists('imagecreatefromjpeg')) return;
    try {
        $img = $type === IMAGETYPE_PNG ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);
        if ($img === false) return;
        if ($type === IMAGETYPE_JPEG) $img = cm_logo_orient($img, $path);
        if ($type === IMAGETYPE_PNG) {
            @imagealphablending($img, false);
            @imagesavealpha($img, true);
            @imagepng($img, $path, 6);
        } else {
            @imagejpeg($img, $path, 88);
        }
        @imagedestroy($img);
    } catch (Throwable $ex) {

    }
}


function cm_logo_delete(): void
{
    $dir = cm_logo_dir();
    foreach (cm_logo_types() as $t) {
        @unlink($dir . '/logo.' . $t['ext']);
    }
    cm_setting_clear('public.logo');
}


