<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('t_paid_leave_requests')) {
            return;
        }

        if (! Schema::hasColumn('t_paid_leave_requests', 'leave_days')) {
            Schema::table('t_paid_leave_requests', function (Blueprint $table) {
                // 既存申請は従来どおり1日として扱う。
                $table->decimal('leave_days', 3, 1)->default(1.0)->after('ends_at');
            });
        }

        if (! Schema::hasColumn('t_paid_leave_requests', 'entry_type')) {
            Schema::table('t_paid_leave_requests', function (Blueprint $table) {
                $table->string('entry_type', 32)->default('application')->after('leave_days');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('t_paid_leave_requests')) {
            return;
        }

        if (Schema::hasColumn('t_paid_leave_requests', 'entry_type')) {
            Schema::table('t_paid_leave_requests', function (Blueprint $table) {
                $table->dropColumn('entry_type');
            });
        }

        if (Schema::hasColumn('t_paid_leave_requests', 'leave_days')) {
            Schema::table('t_paid_leave_requests', function (Blueprint $table) {
                $table->dropColumn('leave_days');
            });
        }
    }
};
