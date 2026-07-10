<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Web の「ログイン状態を保持する」トークンを端末ごとに保存する。
 *
 * m_user.access_token_web の単一トークン方式では、新しい端末でログインすると
 * 既存端末の Remember Cookie が無効になるため、端末ごとの行に分離する。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('t_web_remember_tokens')) {
            return;
        }

        Schema::create('t_web_remember_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('selector', 64)->unique();
            $table->string('token_hash', 64);
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at'], 'web_remember_user_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('t_web_remember_tokens');
    }
};
