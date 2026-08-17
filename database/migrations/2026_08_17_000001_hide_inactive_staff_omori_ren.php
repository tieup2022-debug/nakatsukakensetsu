<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 「使用無 大森 恋」を社員マスタ上で休止扱いにする。
     *
     * 勤怠・配置・有給・ユーザーなどの関連データは変更せず、
     * 通常画面が参照する m_staff.deleted_at だけを設定する。
     */
    public function up(): void
    {
        if (! Schema::hasTable('m_staff') || ! Schema::hasColumn('m_staff', 'deleted_at')) {
            return;
        }

        $now = now();

        DB::table('m_staff')
            ->whereNull('deleted_at')
            ->whereRaw("REPLACE(REPLACE(staff_name, ' ', ''), '　', '') = ?", ['使用無大森恋'])
            ->update([
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * 業務データの表示状態を誤って戻さないため、ロールバックでは復元しない。
     */
    public function down(): void
    {
        // No-op: related historical data is preserved and the staff can be restored manually if needed.
    }
};
