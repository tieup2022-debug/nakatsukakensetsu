<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('t_paid_leave_requests')) {
            return;
        }

        Schema::create('t_paid_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('applicant_staff_id');
            $table->unsignedBigInteger('applicant_user_id')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 32)->default('pending'); // pending | approved
            $table->unsignedBigInteger('approved_by_staff_id')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index('applicant_staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_paid_leave_requests');
    }
};
