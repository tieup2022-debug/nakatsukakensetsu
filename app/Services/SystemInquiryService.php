<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemInquiryService
{
    /**
     * DB の datetime（タイムゾーンなし）をアプリの表示用タイムゾーンとして解釈して整形する。
     * （サーバの date.timezone が UTC でも、保存値は APP_TIMEZONE 基準で解釈する）
     */
    public static function formatStoredAt(mixed $value, string $pattern = 'Y/m/d H:i'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $tz = config('app.timezone');

        return Carbon::parse($value, $tz)->format($pattern);
    }

    public function create(int $userId, string $userName, string $body): ?object
    {
        if (! Schema::hasTable('t_system_inquiries')) {
            return null;
        }

        $id = DB::table('t_system_inquiries')->insertGetId([
            'submitted_by_user_id' => $userId,
            'submitted_by_user_name' => $userName,
            'body' => $body,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('t_system_inquiries')->where('id', '=', $id)->first();
    }

    /**
     * @return Collection<int, object>
     */
    public function listRecent(int $perPage = 30): Collection
    {
        if (! Schema::hasTable('t_system_inquiries')) {
            return collect();
        }

        return DB::table('t_system_inquiries')
            ->orderByDesc('id')
            ->limit($perPage)
            ->get();
    }

    public function deleteById(int $id): bool
    {
        if (! Schema::hasTable('t_system_inquiries') || $id <= 0) {
            return false;
        }

        return DB::table('t_system_inquiries')->where('id', '=', $id)->delete() > 0;
    }
}
