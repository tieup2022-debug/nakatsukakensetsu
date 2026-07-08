<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('m_paid_leave_grants')) {
            return;
        }

        Schema::create('m_paid_leave_grants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedSmallInteger('fiscal_year'); // 年度（4月始まり。2026 = 2026年4月〜2027年3月）
            $table->decimal('carryover_days', 4, 1)->default(0); // 繰越日数
            $table->decimal('granted_days', 4, 1)->default(0); // 当年度付与日数
            $table->timestamps();

            $table->unique(['staff_id', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_paid_leave_grants');
    }
};
