<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * t_attendance に深夜休憩（分・手入力、深夜作業時の既定は60分）と
 * 「深夜休憩を深夜時間から差し引く」フラグを追加する。
 *
 * - 休憩(break_time)＝昼の勤務の休憩、深夜休憩＝夜勤の休憩として役割分担する
 * - 深夜休憩は実働から常に控除。フラグONのときのみ深夜時間（22時〜翌5時）からも控除する
 *   （休憩が深夜帯の中で取られたかどうかを表す）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_attendance', function (Blueprint $table) {
            if (! Schema::hasColumn('t_attendance', 'midnight_break_time')) {
                $table->integer('midnight_break_time')->nullable()->after('midnight_end_time')->comment('深夜休憩(分)');
            }
            if (! Schema::hasColumn('t_attendance', 'midnight_break_deduct_flg')) {
                $table->boolean('midnight_break_deduct_flg')->default(false)->after('midnight_break_time')->comment('深夜休憩を深夜時間から差引');
            }
        });
    }

    public function down(): void
    {
        Schema::table('t_attendance', function (Blueprint $table) {
            if (Schema::hasColumn('t_attendance', 'midnight_break_deduct_flg')) {
                $table->dropColumn('midnight_break_deduct_flg');
            }
            if (Schema::hasColumn('t_attendance', 'midnight_break_time')) {
                $table->dropColumn('midnight_break_time');
            }
        });
    }
};
