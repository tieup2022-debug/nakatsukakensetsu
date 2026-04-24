<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InAppNotificationService
{
    public function create(int $userId, string $title, string $body, ?string $type = null, ?int $relatedId = null): bool
    {
        try {
            DB::table('t_in_app_notifications')->insert([
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'related_id' => $relatedId,
                'created_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }

    public function unreadCount(int $userId): int
    {
        try {
            return (int) DB::table('t_in_app_notifications')
                ->where('user_id', '=', $userId)
                ->whereNull('read_at')
                ->count();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return 0;
        }
    }

    /**
     * @return Collection<int, object>|false
     */
    public function listForUser(int $userId, int $limit = 50)
    {
        try {
            return DB::table('t_in_app_notifications')
                ->where('user_id', '=', $userId)
                ->orderByDesc('id')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }

    public function markRead(int $userId, int $notificationId): bool
    {
        try {
            $n = DB::table('t_in_app_notifications')
                ->where('id', '=', $notificationId)
                ->where('user_id', '=', $userId)
                ->first();
            if (! $n) {
                return false;
            }
            DB::table('t_in_app_notifications')
                ->where('id', '=', $notificationId)
                ->update(['read_at' => now()]);

            return true;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }

    public function markAllRead(int $userId): bool
    {
        try {
            DB::table('t_in_app_notifications')
                ->where('user_id', '=', $userId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return true;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }
}
