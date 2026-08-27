<?php

namespace App\Console\Commands;

use App\Services\BoardImageAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnalyzeBoardImagesCommand extends Command
{
    protected $signature = 'board:analyze-images
                            {--limit=10 : 今回解析する最大件数}
                            {--include-existing : 取込済みの未解析画像も対象にする}
                            {--retry-failed : 5回失敗した画像も再試行する}';

    protected $description = '掲示板画像からOCR文字・内容説明・検索語を生成します。';

    public function handle(BoardImageAnalysisService $analysis): int
    {
        if (! Schema::hasColumn('t_board_attachments', 'ai_analysis_status')) {
            $this->error('画像解析用マイグレーションが未適用です。');

            return self::FAILURE;
        }
        if (! $analysis->available()) {
            $this->warn('画像解析は停止中です。OPENAI_API_KEY と BOARD_IMAGE_AI_ENABLED を確認してください。');

            return self::FAILURE;
        }

        $limit = min(500, max(1, (int) $this->option('limit')));
        $includeExisting = (bool) $this->option('include-existing');
        $retryFailed = (bool) $this->option('retry-failed');

        $attachments = DB::table('t_board_attachments')
            ->select('id', 'original_name', 'mime_type', 'ai_analysis_status')
            ->where(function (Builder $image): void {
                $image->where('mime_type', 'like', 'image/%')
                    ->orWhereRaw("LOWER(original_name) LIKE '%.jpg'")
                    ->orWhereRaw("LOWER(original_name) LIKE '%.jpeg'")
                    ->orWhereRaw("LOWER(original_name) LIKE '%.png'")
                    ->orWhereRaw("LOWER(original_name) LIKE '%.gif'")
                    ->orWhereRaw("LOWER(original_name) LIKE '%.webp'");
            })
            ->where(function (Builder $status) use ($includeExisting, $retryFailed): void {
                $status->where('ai_analysis_status', 'pending')
                    ->orWhere(function (Builder $retry): void {
                        $retry->where('ai_analysis_status', 'retry')
                            ->where(function (Builder $due): void {
                                $due->whereNull('ai_next_attempt_at')
                                    ->orWhere('ai_next_attempt_at', '<=', now());
                            });
                    })
                    ->orWhere(function (Builder $stale): void {
                        $stale->where('ai_analysis_status', 'processing')
                            ->where('updated_at', '<=', now()->subMinutes(15));
                    });

                if ($includeExisting) {
                    $status->orWhere('ai_analysis_status', 'unprocessed');
                }
                if ($retryFailed) {
                    $status->orWhere('ai_analysis_status', 'failed');
                }
            })
            ->orderByRaw("CASE ai_analysis_status WHEN 'pending' THEN 0 WHEN 'retry' THEN 1 WHEN 'processing' THEN 2 WHEN 'failed' THEN 3 ELSE 4 END")
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($attachments->isEmpty()) {
            $this->info('解析対象の画像はありません。');

            return self::SUCCESS;
        }

        $completed = 0;
        $failed = 0;
        foreach ($attachments as $attachment) {
            try {
                if ($analysis->analyzeById((int) $attachment->id)) {
                    $completed++;
                    $this->line(sprintf('[完了] #%d %s', $attachment->id, $attachment->original_name));
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->warn(sprintf('[失敗] #%d %s: %s', $attachment->id, $attachment->original_name, $e->getMessage()));
            }
        }

        $this->info(sprintf('画像解析: 成功%d件、失敗%d件', $completed, $failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
