<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemInquiryService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => '未対応',
            self::STATUS_IN_PROGRESS => '対応中',
            self::STATUS_DONE => '完了',
        ];
    }

    public static function isValidStatus(string $status): bool
    {
        return array_key_exists($status, self::statusLabels());
    }

    public static function normalizeStatus(?string $status): string
    {
        if ($status !== null && self::isValidStatus($status)) {
            return $status;
        }

        return self::STATUS_PENDING;
    }

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

        $insert = [
            'submitted_by_user_id' => $userId,
            'submitted_by_user_name' => $userName,
            'body' => $body,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('t_system_inquiries', 'status')) {
            $insert['status'] = self::STATUS_PENDING;
        }

        $id = DB::table('t_system_inquiries')->insertGetId($insert);

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

    public function updateStatus(int $id, string $status): bool
    {
        if (! Schema::hasTable('t_system_inquiries') || $id <= 0 || ! self::isValidStatus($status)) {
            return false;
        }

        if (! Schema::hasColumn('t_system_inquiries', 'status')) {
            return false;
        }

        return DB::table('t_system_inquiries')
            ->where('id', '=', $id)
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]) > 0;
    }
}
