<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0












































declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }


const CM_CAPTCHA_WORDS = ['zero', 'one', 'two', 'three', 'four', 'five', 'six',
                          'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve'];











const CM_CAPTCHA_KEEP = 3;

const CM_CAPTCHA_TTL = 1800;








function cm_captcha_make(): array
{
    $a = random_int(1, 6);
    $b = random_int(1, 6);
    return ['q' => CM_CAPTCHA_WORDS[$a] . ' plus ' . CM_CAPTCHA_WORDS[$b], 'a' => $a + $b];
}


function cm_captcha_pending(): array
{
    $held = $_SESSION['cm_captcha'] ?? [];
    if (!is_array($held)) return [];
    $now = time();
    $out = [];
    foreach ($held as $row) {
        if (!is_array($row) || !isset($row['a'], $row['at'])) continue;
        if ($now - (int) $row['at'] > CM_CAPTCHA_TTL) continue;
        $out[] = ['a' => (int) $row['a'], 'at' => (int) $row['at']];
    }
    return $out;
}








function cm_captcha_field(): string
{
    $made = cm_captcha_make();
    $held = cm_captcha_pending();
    $held[] = ['a' => $made['a'], 'at' => time()];
    $_SESSION['cm_captcha'] = array_slice($held, -CM_CAPTCHA_KEEP);

    return '<div class="field captcha-field">'
         . '<label class="lbl" for="cm_human">Just checking you are human: what is '
         . '<b>' . e($made['q']) . '</b>?</label>'
         . '<input type="text" id="cm_human" name="cm_human" class="f-sm" required'
         . ' autocomplete="off" spellcheck="false" inputmode="numeric"'
         . ' aria-describedby="cm_human_hint">'
         . '<div class="hint" id="cm_human_hint">A number, or the word &mdash; '
         . 'either is fine.</div>'
         . '</div>';
}

















function cm_captcha_check(): string
{
    $typed = trim((string) ($_POST['cm_human'] ?? ''));
    $held  = cm_captcha_pending();

    if ($held === []) {
        return 'That form sat open too long, or this browser is not keeping cookies, so the check could not be '
             . 'made. Send it again with the new question below — or email us directly, which always works.';
    }
    if ($typed === '') {
        return 'Please answer the "are you human" question under the message.';
    }



    $n = null;
    if (preg_match('/^-?\d+$/', $typed)) {
        $n = (int) $typed;
    } else {
        $i = array_search(strtolower($typed), CM_CAPTCHA_WORDS, true);
        if ($i !== false) $n = (int) $i;
    }

    foreach ($held as $row) {
        if ($n !== null && $n === $row['a']) {



            unset($_SESSION['cm_captcha']);
            return '';
        }
    }



    unset($_SESSION['cm_captcha']);
    return 'That is not the answer to the sum, so the message was not sent. The question below is a new one '
         . '— your message is still in the box.';
}
