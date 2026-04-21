<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NewsService
{
    private const LOCAL_NEWS_FILE = 'app/local_news.txt';

    /**
     * お知らせ取得
     */
    public function GetNews()
    {
        try {
            $row = DB::table('m_news')
                ->whereNull('deleted_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();

            return $this->enrichNewsWithLastEditor($row);
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                return (object) [
                    'news' => $this->readLocalNews(),
                    'updated_at' => null,
                    'last_editor_name' => null,
                ];
            }
            return false;
        }
    }

    /**
     * お知らせマスタ行に、履歴テーブル由来の最終更新者名を付与する
     */
    private function enrichNewsWithLastEditor(?object $row): ?object
    {
        if ($row === null) {
            return null;
        }
        $row->last_editor_name = null;
        try {
            $hist = DB::table('t_news_history as h')
                ->leftJoin('m_user as u', function ($join) {
                    $join->on('u.id', '=', 'h.user_id')
                        ->whereNull('u.deleted_at');
                })
                ->whereNull('h.deleted_at')
                ->orderByDesc('h.id')
                ->select('u.user_name')
                ->first();
            if ($hist && isset($hist->user_name) && (string) $hist->user_name !== '') {
                $row->last_editor_name = (string) $hist->user_name;
            }
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
        }

        return $row;
    }

    /**
     * お知らせ本文を HTML 化する。当日 0 時より前の履歴に保存されていた内容との差分で、
     * 追記・変更された行（行単位の diff）をオレンジ色の span で囲む。
     *
     * @param  string  $currentNews  現在の本文（m_news と同一想定）
     * @return string エスケープ済み HTML（改行はそのまま含み、表示側は white-space: pre-wrap 推奨）
     */
    public function formatNewsHtmlWithDiffSinceDayStart(string $currentNews): string
    {
        $current = str_replace(["\r\n", "\r"], "\n", $currentNews);
        $baseline = $this->getNewsSnapshotBeforeTodayStart();

        if ($baseline === null) {
            return $this->escapeHtmlPreservingNewlines($current);
        }

        $baselineNorm = str_replace(["\r\n", "\r"], "\n", $baseline);
        if ($baselineNorm === $current) {
            return $this->escapeHtmlPreservingNewlines($current);
        }

        return $this->buildNewsHtmlFromLineDiff($baselineNorm, $current);
    }

    /**
     * 行単位 LCS による差分で「現在本文」側の行を組み立て、追加行のみ span で囲む（外部ライブラリ不要）。
     */
    private function buildNewsHtmlFromLineDiff(string $baselineNorm, string $current): string
    {
        $fromLines = $this->splitLinesForDiff($baselineNorm);
        $toLines = $this->splitLinesForDiff($current);
        $ops = $this->lineLcsDiffOperations($fromLines, $toLines);

        $html = '';
        foreach ($ops as $op) {
            if ($op['t'] === 'd') {
                continue;
            }
            $escaped = $this->escapeHtmlPreservingNewlines($op['line']);
            if ($op['t'] === 'a') {
                $html .= '<span class="news-diff-added">'.$escaped."</span>\n";
            } else {
                $html .= $escaped."\n";
            }
        }

        if ($html !== '' && ! str_ends_with($current, "\n")) {
            $html = rtrim($html, "\n");
        }

        return $html;
    }

    /**
     * @return list<string>
     */
    private function splitLinesForDiff(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return explode("\n", $text);
    }

    /**
     * @param  list<string>  $fromLines
     * @param  list<string>  $toLines
     * @return list<array{t: 'k'|'a'|'d', line: string}>
     */
    private function lineLcsDiffOperations(array $fromLines, array $toLines): array
    {
        $m = count($fromLines);
        $n = count($toLines);
        $dp = [];
        for ($i = 0; $i <= $m; $i++) {
            $dp[$i] = array_fill(0, $n + 1, 0);
        }
        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                if ($fromLines[$i - 1] === $toLines[$j - 1]) {
                    $dp[$i][$j] = $dp[$i - 1][$j - 1] + 1;
                } else {
                    $dp[$i][$j] = max($dp[$i - 1][$j], $dp[$i][$j - 1]);
                }
            }
        }

        $ops = [];
        $i = $m;
        $j = $n;
        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $fromLines[$i - 1] === $toLines[$j - 1]) {
                array_unshift($ops, ['t' => 'k', 'line' => $toLines[$j - 1]]);
                $i--;
                $j--;
            } elseif ($i > 0 && $j > 0) {
                if ($dp[$i - 1][$j] >= $dp[$i][$j - 1]) {
                    array_unshift($ops, ['t' => 'd', 'line' => $fromLines[$i - 1]]);
                    $i--;
                } else {
                    array_unshift($ops, ['t' => 'a', 'line' => $toLines[$j - 1]]);
                    $j--;
                }
            } elseif ($i > 0) {
                array_unshift($ops, ['t' => 'd', 'line' => $fromLines[$i - 1]]);
                $i--;
            } else {
                array_unshift($ops, ['t' => 'a', 'line' => $toLines[$j - 1]]);
                $j--;
            }
        }

        return $ops;
    }

    /**
     * アプリのタイムゾーンで「今日 0 時」より前に保存された、直近のお知らせ本文。
     */
    private function getNewsSnapshotBeforeTodayStart(): ?string
    {
        try {
            $start = Carbon::today()->startOfDay();
            $news = DB::table('t_news_history')
                ->whereNull('deleted_at')
                ->where('created_at', '<', $start)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('news');

            return $news !== null ? (string) $news : null;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return null;
        }
    }

    private function escapeHtmlPreservingNewlines(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * お知らせ 編集
     */
    public function update($news, $userId)
    {
        try {
            DB::beginTransaction();

            $latest = DB::table('m_news')
                ->whereNull('deleted_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();

            if ($latest) {
                DB::table('m_news')
                    ->where('id', $latest->id)
                    ->update([
                        'news' => $news,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('m_news')
                    ->insert([
                        'news' => $news,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            DB::table('t_news_history')
                ->where('created_at', '<', now()->subDays(90))
                ->delete();

            DB::table('t_news_history')->insert([
                'news' => $news,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                return $this->writeLocalNews((string) $news);
            }
            return false;
        }
    }

    /**
     * お知らせ更新履歴取得
     */
    public function GetHistory()
    {
        try {
            return DB::table('t_news_history AS n')
                ->select('u.id AS user_id', 'u.user_name AS user_name', 'n.news', 'n.created_at')
                ->join('m_user AS u', function ($join) {
                    $join->on('u.id', '=', 'n.user_id')
                        ->whereNull('u.deleted_at')
                        ->whereNull('n.deleted_at');
                })
                ->orderBy('n.created_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            return false;
        }
    }

    private function readLocalNews(): string
    {
        $path = storage_path(self::LOCAL_NEWS_FILE);
        if (!is_file($path)) {
            return '';
        }
        $content = @file_get_contents($path);
        return is_string($content) ? $content : '';
    }

    private function writeLocalNews(string $news): bool
    {
        $path = storage_path(self::LOCAL_NEWS_FILE);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return @file_put_contents($path, $news) !== false;
    }
}

