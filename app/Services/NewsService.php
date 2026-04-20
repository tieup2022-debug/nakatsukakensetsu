<?php

namespace App\Services;

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
            return DB::table('m_news')
                ->whereNull('deleted_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                return (object) ['news' => $this->readLocalNews()];
            }
            return false;
        }
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

