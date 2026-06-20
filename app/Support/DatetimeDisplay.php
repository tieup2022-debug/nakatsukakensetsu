<?php

namespace App\Support;

use Carbon\Carbon;

class DatetimeDisplay
{
    /**
     * DB の datetime（タイムゾーンなし）を、保存時の意味づけ（datetime_storage_timezone）で読み、
     * 画面用（display_timezone）へ変換して整形する。
     *
     * created_at / approved_at など Laravel の now() で保存した値向け。
     */
    public static function formatStoredAt(mixed $value, string $pattern = 'Y/m/d H:i'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $storedTz = (string) config('app.datetime_storage_timezone', config('app.timezone'));
        $displayTz = (string) config('app.display_timezone', config('app.timezone'));

        return Carbon::parse($value, $storedTz)->timezone($displayTz)->format($pattern);
    }

    /**
     * ユーザー入力のローカル日時（有給の開始・終了など）。TZ変換せずそのまま表示。
     */
    public static function formatWallClock(mixed $value, string $pattern = 'Y/m/d H:i'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return Carbon::parse($value)->format($pattern);
    }
}
