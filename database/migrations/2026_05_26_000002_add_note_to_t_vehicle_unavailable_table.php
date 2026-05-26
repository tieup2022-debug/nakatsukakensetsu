<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * t_vehicle_unavailable に note カラム（任意メモ）を追加。
 * ガント上でセルにホバーすると表示される。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('t_vehicle_unavailable')) {
            return;
        }
        if (Schema::hasColumn('t_vehicle_unavailable', 'note')) {
            return;
        }

        Schema::table('t_vehicle_unavailable', function (Blueprint $table) {
            $table->string('note', 255)->nullable()->after('reason_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('t_vehicle_unavailable')) {
            return;
        }
        if (! Schema::hasColumn('t_vehicle_unavailable', 'note')) {
            return;
        }

        Schema::table('t_vehicle_unavailable', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
