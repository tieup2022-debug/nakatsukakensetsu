<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 車両・重機の「使用不可期間」管理テーブル。
 * 車検 / 点検 / 修理 / 故障 / その他 で一定期間使えない場合に登録する。
 *
 * - 1レコード = 期間（start_date〜end_date）
 * - reason_type は config 不要の固定値:
 *     1=車検, 2=点検, 3=修理, 4=故障, 99=その他
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('t_vehicle_unavailable')) {
            return;
        }

        Schema::create('t_vehicle_unavailable', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedTinyInteger('reason_type')->default(1);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
            $table->softDeletes();

            $table->index('vehicle_id');
            $table->index(['start_date', 'end_date']);
            $table->index(['vehicle_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_vehicle_unavailable');
    }
};
