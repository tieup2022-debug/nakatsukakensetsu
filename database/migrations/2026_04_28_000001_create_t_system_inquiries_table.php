<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('t_system_inquiries')) {
            return;
        }

        Schema::create('t_system_inquiries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->string('submitted_by_user_name', 255);
            $table->text('body');
            $table->timestamps();

            $table->index('submitted_by_user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_system_inquiries');
    }
};
