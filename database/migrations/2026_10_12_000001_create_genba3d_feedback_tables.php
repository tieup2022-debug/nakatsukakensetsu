<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 現場3D「現場の声」: 3Dモデルや工程表を見た人が、要望や現場の情報を書き込む。
 * 3Dモデルの上にピンを立てられる（位置とそのときの視点を pin に JSON で保存）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('t_genba3d_feedback')) {
            Schema::create('t_genba3d_feedback', function (Blueprint $table): void {
                $table->id();
                $table->string('site', 64);
                $table->string('tab', 40)->default('model');
                $table->string('kind', 16)->default('request');
                $table->text('body');
                $table->text('pin')->nullable();
                $table->string('status', 16)->default('open');
                $table->unsignedBigInteger('author_user_id')->nullable();
                $table->string('author_name', 255);
                $table->timestamps();

                $table->index(['site', 'status']);
                $table->index('status');
            });
        }

        if (! Schema::hasTable('t_genba3d_feedback_replies')) {
            Schema::create('t_genba3d_feedback_replies', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('feedback_id');
                $table->text('body');
                $table->unsignedBigInteger('author_user_id')->nullable();
                $table->string('author_name', 255);
                $table->boolean('is_master')->default(false);
                $table->timestamps();

                $table->index(['feedback_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('t_genba3d_feedback_replies');
        Schema::dropIfExists('t_genba3d_feedback');
    }
};
