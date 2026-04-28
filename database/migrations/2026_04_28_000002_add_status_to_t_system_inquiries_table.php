<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('t_system_inquiries')) {
            return;
        }

        if (! Schema::hasColumn('t_system_inquiries', 'status')) {
            Schema::table('t_system_inquiries', function (Blueprint $table) {
                $table->string('status', 32)->default('pending')->after('body');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('t_system_inquiries')) {
            return;
        }

        if (Schema::hasColumn('t_system_inquiries', 'status')) {
            Schema::table('t_system_inquiries', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
