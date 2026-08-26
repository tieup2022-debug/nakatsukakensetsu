<?php

namespace App\Console\Commands;

use App\Exceptions\HiWareAuthenticationException;
use App\Services\HiWareBoardCrawler;
use App\Services\HiWareBoardParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class CrawlHiWareBoardCommand extends Command
{
    protected $signature = 'board:crawl-hiware
                            {--user= : 旧Hi-WAREのログインID（パスワードは必ず対話入力）}
                            {--password-file= : パスワード1行だけを保存した権限0600のファイル}
                            {--output= : 保存先。既定は storage/app/private/legacy-board-export}
                            {--phase=all : all / lists / details}
                            {--start-page=1 : 開始ページ}
                            {--end-page=1232 : 終了ページ}
                            {--limit=0 : 詳細取得件数の上限。0は無制限}
                            {--delay-ms=500 : リクエスト間隔（ミリ秒）}
                            {--fresh : 既存のページ・投稿JSONを再取得}
                            {--no-attachments : 添付ファイルを取得しない}
                            {--no-likes : いいね数を詳細APIから取得しない}';

    protected $description = '旧Hi-WARE掲示板を読み取り専用で巡回し、再開可能な移行データを作成します。';

    public function handle(HiWareBoardCrawler $crawler): int
    {
        $phase = (string) $this->option('phase');
        if (! in_array($phase, ['all', 'lists', 'details'], true)) {
            $this->error('--phase は all / lists / details のいずれかです。');

            return self::FAILURE;
        }

        $startPage = max(1, (int) $this->option('start-page'));
        $endPage = max($startPage, (int) $this->option('end-page'));
        $limit = max(0, (int) $this->option('limit'));
        $delay = max(250, (int) $this->option('delay-ms'));
        $output = $this->option('output') ?: storage_path('app/private/legacy-board-export');
        $output = rtrim((string) $output, DIRECTORY_SEPARATOR);
        $fresh = (bool) $this->option('fresh');

        foreach ([$output, $output.'/lists', $output.'/threads', $output.'/attachments'] as $directory) {
            File::ensureDirectoryExists($directory);
            if (! is_dir($directory)) {
                $this->error('保存先を作成できません: '.$directory);

                return self::FAILURE;
            }
        }

        $user = trim((string) ($this->option('user') ?: $this->ask('旧Hi-WAREのログインID')));
        try {
            $password = $this->readPassword();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        if ($user === '' || $password === '') {
            $this->error('ログインIDとパスワードが必要です。');

            return self::FAILURE;
        }

        $crawler = new HiWareBoardCrawler(app(HiWareBoardParser::class), $delay);
        $this->info('旧掲示板へログインしています（認証情報は保存しません）...');
        try {
            $crawler->login($user, $password);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            unset($password);
        }
        $this->info('ログイン成功。読み取り専用クロールを開始します。');

        try {
            if (in_array($phase, ['all', 'lists'], true)) {
                $this->crawlLists($crawler, $output, $startPage, $endPage, $fresh);
            }
            if (in_array($phase, ['all', 'details'], true)) {
                $this->crawlDetails($crawler, $output, $startPage, $endPage, $limit, $fresh);
            }
        } catch (HiWareAuthenticationException $e) {
            $this->error($e->getMessage().' 同じコマンドを再実行すれば続きから再開できます。');

            return self::FAILURE;
        }

        $this->info('完了: '.$output);

        return self::SUCCESS;
    }

    private function crawlLists(HiWareBoardCrawler $crawler, string $output, int $startPage, int $endPage, bool $fresh): void
    {
        $this->info(sprintf('一覧ページ %d〜%d を取得します。', $startPage, $endPage));
        for ($page = $startPage; $page <= $endPage; $page++) {
            $path = sprintf('%s/lists/page-%04d.json', $output, $page);
            if (! $fresh && is_file($path)) {
                continue;
            }
            try {
                $data = $crawler->listPage($page);
                $data['crawled_at'] = now()->toIso8601String();
                $this->writeJson($path, $data);
                $this->line(sprintf('[一覧 %d/%d] %d件', $page, $endPage, count($data['threads'])));
                if (($data['total_pages'] ?? 0) > 0 && $page >= (int) $data['total_pages']) {
                    break;
                }
            } catch (\Throwable $e) {
                if ($e instanceof HiWareAuthenticationException) {
                    throw $e;
                }
                $this->appendFailure($output, 'list', (string) $page, $e);
                $this->warn(sprintf('[一覧 %d] 失敗: %s', $page, $e->getMessage()));
            }
        }
    }

    private function crawlDetails(
        HiWareBoardCrawler $crawler,
        string $output,
        int $startPage,
        int $endPage,
        int $limit,
        bool $fresh,
    ): void {
        $index = $this->loadThreadIndex($output, $startPage, $endPage);
        if ($index === []) {
            throw new RuntimeException('取得済み一覧がありません。先に --phase=lists を実行してください。');
        }
        $this->info(number_format(count($index)).'件の投稿詳細を処理します。');
        $completed = 0;
        foreach ($index as $legacyId => $listRecord) {
            if ($limit > 0 && $completed >= $limit) {
                break;
            }
            $threadPath = $output.'/threads/'.$legacyId.'.json';
            if (! $fresh && is_file($threadPath)) {
                continue;
            }
            try {
                $record = $crawler->thread((string) $listRecord['detail_url']);
                $record['title'] = $record['title'] ?: ($listRecord['title'] ?? '');
                $record['view_count'] = max((int) ($record['view_count'] ?? 0), (int) ($listRecord['view_count'] ?? 0));
                $record['last_activity_at'] = $listRecord['last_activity_at'] ?? null;
                $record['legacy_like_count'] = $this->option('no-likes')
                    ? (int) ($listRecord['legacy_like_count'] ?? 0)
                    : ($crawler->likeCount($legacyId) ?? (int) ($listRecord['legacy_like_count'] ?? 0));

                if (! $this->option('no-attachments')) {
                    $this->downloadPostAttachments($crawler, $output, $record);
                }
                foreach ($record['replies'] as &$reply) {
                    if (! $this->option('no-likes')) {
                        $reply['legacy_like_count'] = $crawler->likeCount((string) $reply['legacy_id']) ?? 0;
                    }
                    if (! $this->option('no-attachments')) {
                        $this->downloadPostAttachments($crawler, $output, $reply);
                    }
                }
                unset($reply);
                $record['crawled_at'] = now()->toIso8601String();
                $this->writeJson($threadPath, $record);
                $completed++;
                if ($completed <= 10 || $completed % 25 === 0) {
                    $this->line(sprintf('[詳細 %s] 完了（今回 %d件）', $legacyId, $completed));
                }
            } catch (\Throwable $e) {
                if ($e instanceof HiWareAuthenticationException) {
                    throw $e;
                }
                $this->appendFailure($output, 'thread', $legacyId, $e);
                $this->warn(sprintf('[詳細 %s] 失敗: %s', $legacyId, $e->getMessage()));
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function loadThreadIndex(string $output, int $startPage, int $endPage): array
    {
        $index = [];
        for ($page = $startPage; $page <= $endPage; $page++) {
            $path = sprintf('%s/lists/page-%04d.json', $output, $page);
            if (! is_file($path)) {
                continue;
            }
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            foreach ($data['threads'] ?? [] as $thread) {
                $legacyId = (string) ($thread['legacy_id'] ?? '');
                if ($legacyId !== '') {
                    $index[$legacyId] = $thread;
                }
            }
        }

        return $index;
    }

    /** @param array<string, mixed> $post */
    private function downloadPostAttachments(HiWareBoardCrawler $crawler, string $output, array &$post): void
    {
        foreach ($post['attachments'] ?? [] as &$attachment) {
            $index = (int) ($attachment['index'] ?? 0);
            $original = $this->safeFilename((string) ($attachment['original_name'] ?? 'attachment'));
            $legacyId = (string) $post['legacy_id'];
            $relative = sprintf('attachments/%s/%03d-%s', $legacyId, $index, $original);
            $destination = $output.'/'.$relative;
            if (is_file($destination) && filesize($destination) > 0) {
                $metadata = [
                    'mime_type' => mime_content_type($destination) ?: null,
                    'size' => (int) filesize($destination),
                ];
            } else {
                $metadata = $crawler->download((string) $attachment['source_url'], $destination);
            }
            $attachment['path'] = $relative;
            $attachment['mime_type'] = $metadata['mime_type'];
            $attachment['size'] = $metadata['size'];
        }
        unset($attachment);
    }

    private function safeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[^\pL\pN._-]+/u', '_', $filename) ?: 'attachment';

        return mb_substr($filename, 0, 180);
    }

    /** @param array<string, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        $temporary = $path.'.tmp';
        file_put_contents($temporary, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        if (! rename($temporary, $path)) {
            throw new RuntimeException('JSONを確定できません: '.$path);
        }
    }

    private function appendFailure(string $output, string $type, string $id, \Throwable $e): void
    {
        file_put_contents($output.'/failures.jsonl', json_encode([
            'at' => now()->toIso8601String(),
            'type' => $type,
            'id' => $id,
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND | LOCK_EX);
    }

    private function readPassword(): string
    {
        $passwordFile = trim((string) $this->option('password-file'));
        if ($passwordFile === '') {
            return (string) $this->secret('旧Hi-WAREのパスワード（保存されません）');
        }
        $resolved = realpath($passwordFile);
        if ($resolved === false || ! is_file($resolved)) {
            throw new RuntimeException('パスワードファイルが見つかりません。');
        }
        $permissions = fileperms($resolved);
        if ($permissions === false || ($permissions & 0077) !== 0) {
            throw new RuntimeException('パスワードファイルの権限を0600にしてください。');
        }

        return rtrim((string) file_get_contents($resolved), "\r\n");
    }
}
