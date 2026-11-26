<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 現場3D: 工事ごとの工期（開始日・工期末）。工程表の画面で入力し、一覧ページにも出す。
 * ログインした人なら誰でも入力できるので、変更のたびに誰が・いつ・何から何へ変えたかを残す。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('t_genba3d_terms')) {
            Schema::create('t_genba3d_terms', function (Blueprint $table): void {
                $table->id();
                $table->string('project', 64)->unique();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->unsignedBigInteger('updated_user_id')->nullable();
                $table->string('updated_name', 255)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('t_genba3d_term_logs')) {
            Schema::create('t_genba3d_term_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('project', 64);
                $table->date('old_start')->nullable();
                $table->date('old_end')->nullable();
                $table->date('new_start')->nullable();
                $table->date('new_end')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name', 255)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['project', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('t_genba3d_term_logs');
        Schema::dropIfExists('t_genba3d_terms');
    }
};
