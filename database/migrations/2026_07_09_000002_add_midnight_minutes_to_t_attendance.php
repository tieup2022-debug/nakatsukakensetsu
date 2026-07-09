<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * t_attendance に深夜時間（分・手入力）を追加する。
 * NULL = 未入力。深夜の集計はこの列のみを使う（出退勤時刻からの自動計算は廃止）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('t_attendance', 'midnight_minutes')) {
            Schema::table('t_attendance', function (Blueprint $table) {
                $table->integer('midnight_minutes')->nullable()->after('break_time')->comment('深夜時間(分)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('t_attendance', 'midnight_minutes')) {
            Schema::table('t_attendance', function (Blueprint $table) {
                $table->dropColumn('midnight_minutes');
            });
        }
    }
};
