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
    /**
     * 作業日の既定値。
     * - 15:00 未満: 当日
     * - 15:00 以降: 月〜木は翌日、金・土・日は翌週の月曜（旧システムの曜日ロジック）
     */
    function defaultWorkDate(): string
    {
        $now = now();
        if ((int) $now->format('Hi') < 1500) {
            return $now->format('Y-m-d');
        }

        $today = $now->format('Y-m-d');
        $w = (int) date('w', strtotime($today));
        if ($w >= 1 && $w <= 4) {
            return date('Y-m-d', strtotime($today . ' +1 day'));
        }

        return date('Y-m-d', strtotime($today . ' next Monday'));
    }
}

