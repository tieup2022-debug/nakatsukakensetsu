<?php

if (!function_exists('getJapaneseWeekdaysShort')) {
    function getJapaneseWeekdaysShort()
    {
        return ['月', '火', '水', '木', '金', '土', '日'];
    }
}

if (!function_exists('formatJapaneseDate')) {
    function formatJapaneseDate(string $date): string
    {
        $weekdays = getJapaneseWeekdaysShort();
        $timestamp = strtotime($date);
        $weekday = $weekdays[date('N', $timestamp) - 1];

        return date("Y年m月d日({$weekday})", $timestamp);
    }
}

if (!function_exists('defaultWorkDate')) {
    function defaultWorkDate(): string
    {
        $today = date('Y-m-d');
        $dayOfWeek = date('w', strtotime($today));

        if ($dayOfWeek >= 1 && $dayOfWeek <= 4) {
            $defaultDate = date('Y-m-d', strtotime($today . ' + 1 day'));
        } else {
            $defaultDate = date('Y-m-d', strtotime('next Monday'));
        }

        return $defaultDate;
    }
}

