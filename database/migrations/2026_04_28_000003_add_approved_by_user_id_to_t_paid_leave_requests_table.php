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

        if (! Schema::hasColumn('t_paid_leave_requests', 'approved_by_user_id')) {
            Schema::table('t_paid_leave_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('approved_by_user_id')->nullable()->after('approved_by_staff_id');
                $table->index('approved_by_user_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('t_paid_leave_requests')) {
            return;
        }

        if (Schema::hasColumn('t_paid_leave_requests', 'approved_by_user_id')) {
            Schema::table('t_paid_leave_requests', function (Blueprint $table) {
                $table->dropIndex(['approved_by_user_id']);
                $table->dropColumn('approved_by_user_id');
            });
        }
    }
};

