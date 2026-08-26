<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('t_board_threads') && ! Schema::hasColumn('t_board_threads', 'legacy_like_count')) {
            Schema::table('t_board_threads', function (Blueprint $table): void {
                $table->unsignedBigInteger('legacy_like_count')->default(0)->after('view_count');
            });
        }

        if (Schema::hasTable('t_board_replies') && ! Schema::hasColumn('t_board_replies', 'legacy_like_count')) {
            Schema::table('t_board_replies', function (Blueprint $table): void {
                $table->unsignedBigInteger('legacy_like_count')->default(0)->after('author_email');
            });
        }

        if (Schema::hasTable('t_board_attachments') && ! Schema::hasColumn('t_board_attachments', 'legacy_id')) {
            Schema::table('t_board_attachments', function (Blueprint $table): void {
                $table->string('legacy_id', 128)->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('t_board_attachments') && Schema::hasColumn('t_board_attachments', 'legacy_id')) {
            Schema::table('t_board_attachments', function (Blueprint $table): void {
                $table->dropUnique(['legacy_id']);
                $table->dropColumn('legacy_id');
            });
        }

        if (Schema::hasTable('t_board_replies') && Schema::hasColumn('t_board_replies', 'legacy_like_count')) {
            Schema::table('t_board_replies', function (Blueprint $table): void {
                $table->dropColumn('legacy_like_count');
            });
        }

        if (Schema::hasTable('t_board_threads') && Schema::hasColumn('t_board_threads', 'legacy_like_count')) {
            Schema::table('t_board_threads', function (Blueprint $table): void {
                $table->dropColumn('legacy_like_count');
            });
        }
    }
};
