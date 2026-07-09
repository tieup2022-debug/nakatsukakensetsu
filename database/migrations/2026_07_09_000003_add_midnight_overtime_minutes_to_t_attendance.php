<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * t_attendance に時間外（深夜）（分・手入力）を追加する。
 * 深夜（midnight_minutes）と同様に NULL = 未入力で、集計はこの列のみを使う。
 * 出勤表の「時間外（深夜）」（時間外労働のうち22時〜翌5時の分、50%割増）に対応。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('t_attendance', 'midnight_overtime_minutes')) {
            Schema::table('t_attendance', function (Blueprint $table) {
                $table->integer('midnight_overtime_minutes')->nullable()->after('midnight_minutes')->comment('時間外（深夜）(分)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('t_attendance', 'midnight_overtime_minutes')) {
            Schema::table('t_attendance', function (Blueprint $table) {
                $table->dropColumn('midnight_overtime_minutes');
            });
        }
    }
};
