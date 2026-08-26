<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('t_board_threads')) {
            Schema::create('t_board_threads', function (Blueprint $table): void {
                $table->id();
                $table->string('legacy_id', 64)->nullable()->unique();
                $table->string('title', 255);
                $table->longText('body');
                $table->unsignedBigInteger('author_user_id')->nullable();
                $table->string('author_name', 255);
                $table->string('author_email', 255)->nullable();
                $table->unsignedBigInteger('view_count')->default(0);
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamps();

                $table->index(['last_activity_at', 'id']);
                $table->index('author_user_id');
            });
        }

        if (! Schema::hasTable('t_board_replies')) {
            Schema::create('t_board_replies', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('thread_id');
                $table->string('legacy_id', 64)->nullable()->unique();
                $table->longText('body');
                $table->unsignedBigInteger('author_user_id')->nullable();
                $table->string('author_name', 255);
                $table->string('author_email', 255)->nullable();
                $table->timestamps();

                $table->index(['thread_id', 'created_at']);
                $table->index('author_user_id');
            });
        }

        if (! Schema::hasTable('t_board_attachments')) {
            Schema::create('t_board_attachments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('thread_id');
                $table->unsignedBigInteger('reply_id')->nullable();
                $table->string('disk', 32)->default('local');
                $table->string('path', 1024);
                $table->string('original_name', 255);
                $table->string('mime_type', 255)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->timestamps();

                $table->index(['thread_id', 'reply_id']);
            });
        }

        if (! Schema::hasTable('t_board_likes')) {
            Schema::create('t_board_likes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('thread_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['thread_id', 'user_id']);
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('t_board_likes');
        Schema::dropIfExists('t_board_attachments');
        Schema::dropIfExists('t_board_replies');
        Schema::dropIfExists('t_board_threads');
    }
};
