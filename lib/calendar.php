<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0




































declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }


const CM_CAL_WEEK_START = 1;








function cm_calendar_html(string $name, string $selected, string $id): string
{



    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected) || strtotime($selected) === false) {
        $selected = date('Y-m-d');
    }

    $h = '<div class="cal" data-cal data-cal-name="' . e($name) . '" id="' . e($id) . '">'
       . '<input type="hidden" name="' . e($name) . '" value="' . e($selected) . '" data-cal-input>'
       . cm_calendar_month_html($selected, substr($selected, 0, 7))
       . '</div>';
    return $h;
}









function cm_calendar_month_html(string $selected, string $month): string
{
    $first = strtotime($month . '-01');
    if ($first === false) $first = strtotime(date('Y-m') . '-01');

    $daysIn = (int) date('t', $first);


    $lead   = ((int) date('N', $first) - CM_CAL_WEEK_START + 7) % 7;
    $today  = date('Y-m-d');

    $h = '<div class="cal-head">'
       . '<button class="cal-nav" type="button" data-cal-prev'
       . ' aria-label="Previous month">&lsaquo;</button>'
       . '<span class="cal-title" data-cal-title>' . e(date('F Y', $first)) . '</span>'
       . '<button class="cal-nav" type="button" data-cal-next'
       . ' aria-label="Next month">&rsaquo;</button>'
       . '</div><div class="cal-grid">';




    foreach (['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'] as $dow) {
        $h .= '<span class="cal-dow" aria-hidden="true">' . $dow . '</span>';
    }



    for ($i = 0; $i < $lead; $i++) $h .= '<span class="cal-pad"></span>';

    for ($d = 1; $d <= $daysIn; $d++) {
        $date = $month . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
        $cls  = 'cal-day';
        if ($date === $selected) $cls .= ' is-selected';
        if ($date === $today)    $cls .= ' is-today';
        $h .= '<button class="' . $cls . '" type="button" data-cal-day="' . e($date) . '"'
            . ' aria-label="' . e(date('l j F Y', strtotime($date))) . '"'
            . ($date === $selected ? ' aria-pressed="true"' : ' aria-pressed="false"')
            . '>' . $d . '</button>';
    }

    return $h . '</div>';
}
