<?php

namespace App\Console\Commands;

use App\Services\HiWareBoardImportService;
use DirectoryIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ImportHiWareBoardCommand extends Command
{
    protected $signature = 'board:import-hiware
                            {path : board:crawl-hiware の出力ディレクトリ}
                            {--commit : 実際にDBと添付ストレージへ書き込む}
                            {--limit=0 : 処理件数上限。0は無制限}
                            {--skip-attachments : 添付を取り込まない}';

    protected $description = 'クロール済みHi-WARE掲示板データを現行掲示板へ再実行可能な形で取り込みます。';

    public function handle(HiWareBoardImportService $importer): int
    {
        $root = realpath((string) $this->argument('path'));
        if ($root === false || ! is_dir($root.'/threads')) {
            $this->error('有効なクロール出力ディレクトリではありません。');

            return self::FAILURE;
        }
        $limit = max(0, (int) $this->option('limit'));

        $valid = 0;
        $replies = 0;
        $attachments = 0;
        foreach ($this->threadFiles($root.'/threads', $limit) as $file) {
            try {
                $record = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
                if (empty($record['legacy_id']) || empty($record['title'])) {
                    throw new \RuntimeException('legacy_id または title が空です。');
                }
                $valid++;
                $replies += count((array) ($record['replies'] ?? []));
                foreach (array_merge([$record], (array) ($record['replies'] ?? [])) as $post) {
                    if (is_array($post)) {
                        $attachments += count((array) ($post['attachments'] ?? []));
                    }
                }
            } catch (\Throwable $e) {
                $this->error(basename($file).': '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $this->table(['投稿', '返信', '添付'], [[number_format($valid), number_format($replies), number_format($attachments)]]);
        if (! $this->option('commit')) {
            $this->warn('検証のみでDBには書き込んでいません。実行時は --commit を付けてください。');

            return self::SUCCESS;
        }
        foreach (['t_board_threads', 't_board_replies', 't_board_attachments'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error('掲示板テーブルがありません。先に php artisan migrate を実行してください。');

                return self::FAILURE;
            }
        }
        if (! Schema::hasColumn('t_board_threads', 'legacy_like_count')
            || ! Schema::hasColumn('t_board_replies', 'legacy_like_count')
            || ! Schema::hasColumn('t_board_attachments', 'legacy_id')) {
            $this->error('旧掲示板取込用の追加マイグレーションが未適用です。');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $processed = 0;
        foreach ($this->threadFiles($root.'/threads', $limit) as $file) {
            try {
                $record = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
                $result = $importer->import($record, $root, (bool) $this->option('skip-attachments'));
                $result['created'] ? $created++ : $updated++;
                $processed++;
                if ($processed <= 10 || $processed % 100 === 0) {
                    $this->line(sprintf('[%d/%d] %s', $processed, $valid, $record['legacy_id']));
                }
            } catch (\Throwable $e) {
                $this->error(basename($file).': '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $this->info(sprintf('取込完了: 新規%d件、更新%d件', $created, $updated));

        return self::SUCCESS;
    }

    /** @return \Generator<int, string> */
    private function threadFiles(string $directory, int $limit): \Generator
    {
        $count = 0;
        foreach (new DirectoryIterator($directory) as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'json') {
                continue;
            }
            yield $file->getPathname();
            $count++;
            if ($limit > 0 && $count >= $limit) {
                break;
            }
        }
    }
}
