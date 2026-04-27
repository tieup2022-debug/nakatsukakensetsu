<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemInquiryService
{
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
}
