<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('t_board_attachments')
            || Schema::hasColumn('t_board_attachments', 'ai_analysis_status')) {
            return;
        }

        Schema::table('t_board_attachments', function (Blueprint $table): void {
            $table->longText('ai_description')->nullable()->after('size');
            $table->longText('ai_ocr_text')->nullable()->after('ai_description');
            $table->text('ai_keywords')->nullable()->after('ai_ocr_text');
            $table->longText('ai_search_text')->nullable()->after('ai_keywords');
            $table->string('ai_analysis_status', 32)->default('unprocessed')->after('ai_search_text');
            $table->unsignedSmallInteger('ai_analysis_attempts')->default(0)->after('ai_analysis_status');
            $table->text('ai_analysis_error')->nullable()->after('ai_analysis_attempts');
            $table->timestamp('ai_next_attempt_at')->nullable()->after('ai_analysis_error');
            $table->timestamp('ai_analyzed_at')->nullable()->after('ai_next_attempt_at');

            $table->index(['ai_analysis_status', 'ai_next_attempt_at'], 'board_attachment_ai_status_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('t_board_attachments')
            || ! Schema::hasColumn('t_board_attachments', 'ai_analysis_status')) {
            return;
        }

        Schema::table('t_board_attachments', function (Blueprint $table): void {
            $table->dropIndex('board_attachment_ai_status_idx');
            $table->dropColumn([
                'ai_description',
                'ai_ocr_text',
                'ai_keywords',
                'ai_search_text',
                'ai_analysis_status',
                'ai_analysis_attempts',
                'ai_analysis_error',
                'ai_next_attempt_at',
                'ai_analyzed_at',
            ]);
        });
    }
};
