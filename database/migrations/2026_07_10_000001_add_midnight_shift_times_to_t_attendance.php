<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * t_attendance に深夜出勤・深夜退勤（TIME・手入力）を追加する。
 * 深夜時間は保存せず、昼（start/end）と夜（midnight_start/end）それぞれの
 * 22:00〜翌5:00 との重なりの合計を常に自動計算する（midnight_minutes は廃止・未使用化）。
 *
 * あわせて start_time / end_time を NULL 許可にする。
 * 夜勤のみの日（例 18:00〜翌3:30）は深夜出勤・退勤のみを持ち、昼の出退勤は NULL になる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_attendance', function (Blueprint $table) {
            if (! Schema::hasColumn('t_attendance', 'midnight_start_time')) {
                $table->time('midnight_start_time')->nullable()->after('break_time')->comment('深夜出勤');
            }
            if (! Schema::hasColumn('t_attendance', 'midnight_end_time')) {
                $table->time('midnight_end_time')->nullable()->after('midnight_start_time')->comment('深夜退勤');
            }
        });

        Schema::table('t_attendance', function (Blueprint $table) {
            $table->time('start_time')->nullable()->comment('出勤時間')->change();
            $table->time('end_time')->nullable()->comment('退勤時間')->change();
        });
    }

    public function down(): void
    {
        Schema::table('t_attendance', function (Blueprint $table) {
            if (Schema::hasColumn('t_attendance', 'midnight_start_time')) {
                $table->dropColumn('midnight_start_time');
            }
            if (Schema::hasColumn('t_attendance', 'midnight_end_time')) {
                $table->dropColumn('midnight_end_time');
            }
        });
        // start_time / end_time の NOT NULL への復元は NULL 行が存在するとできないため行わない
    }
};
