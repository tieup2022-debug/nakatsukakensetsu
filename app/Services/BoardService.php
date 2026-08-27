<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BoardService
{
    public const ATTACHMENT_LIMIT = 5;

    public function paginateThreads(?string $keyword, int $perPage = 50): LengthAwarePaginator
    {
        $query = DB::table('t_board_threads as threads')
            ->select('threads.*')
            ->selectSub(
                DB::table('t_board_replies')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('thread_id', 'threads.id'),
                'reply_count'
            )
            ->selectSub(
                DB::table('t_board_likes')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('thread_id', 'threads.id'),
                'like_count'
            )
            ->orderByDesc('threads.last_activity_at')
            ->orderByDesc('threads.id');

        $keyword = trim((string) $keyword);
        if ($keyword !== '') {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword);
            $query->where(function ($search) use ($escaped): void {
                $pattern = '%'.$escaped.'%';
                $search->whereRaw("threads.title LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("threads.body LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("threads.author_name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereExists(function ($attachments) use ($pattern): void {
                        $attachments->selectRaw('1')
                            ->from('t_board_attachments as search_attachments')
                            ->whereColumn('search_attachments.thread_id', 'threads.id')
                            ->where(function ($attachmentSearch) use ($pattern): void {
                                $attachmentSearch
                                    ->whereRaw("search_attachments.original_name LIKE ? ESCAPE '!'", [$pattern])
                                    ->orWhereRaw("search_attachments.ai_search_text LIKE ? ESCAPE '!'", [$pattern]);
                            });
                    });
            });
        }

        $paginator = $query->paginate($perPage)->withQueryString();
        $paginator->getCollection()->transform(function (object $thread): object {
            $thread->like_count = (int) $thread->like_count + (int) ($thread->legacy_like_count ?? 0);

            return $thread;
        });

        return $paginator;
    }

    public function findThread(int $threadId, int $viewerUserId = 0, bool $incrementViews = false): ?object
    {
        if ($incrementViews) {
            DB::table('t_board_threads')->where('id', $threadId)->increment('view_count');
        }

        $thread = DB::table('t_board_threads as threads')
            ->select('threads.*')
            ->selectSub(
                DB::table('t_board_likes')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('thread_id', 'threads.id'),
                'like_count'
            )
            ->where('threads.id', $threadId)
            ->first();

        if (! $thread) {
            return null;
        }

        $thread->like_count = (int) $thread->like_count + (int) ($thread->legacy_like_count ?? 0);

        $thread->liked_by_viewer = $viewerUserId > 0 && DB::table('t_board_likes')
            ->where('thread_id', $threadId)
            ->where('user_id', $viewerUserId)
            ->exists();
        $thread->attachments = $this->attachmentsFor($threadId, null);
        $thread->replies = DB::table('t_board_replies')
            ->where('thread_id', $threadId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $replyAttachments = DB::table('t_board_attachments')
            ->where('thread_id', $threadId)
            ->whereNotNull('reply_id')
            ->orderBy('id')
            ->get()
            ->groupBy('reply_id');

        foreach ($thread->replies as $reply) {
            $reply->attachments = $replyAttachments->get($reply->id, collect());
        }

        return $thread;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function createThread(
        int $userId,
        string $userName,
        string $title,
        string $body,
        array $files = [],
    ): int {
        $storedPaths = [];

        DB::beginTransaction();
        try {
            $now = now();
            $threadId = DB::table('t_board_threads')->insertGetId([
                'title' => $title,
                'body' => $body,
                'author_user_id' => $userId,
                'author_name' => $userName,
                'view_count' => 0,
                'last_activity_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->storeAttachments($threadId, null, $files, $storedPaths);
            DB::commit();

            return $threadId;
        } catch (\Throwable $e) {
            DB::rollBack();
            Storage::disk('local')->delete($storedPaths);
            throw $e;
        }
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function createReply(
        int $threadId,
        int $userId,
        string $userName,
        string $body,
        array $files = [],
    ): int {
        $storedPaths = [];

        DB::beginTransaction();
        try {
            if (! DB::table('t_board_threads')->where('id', $threadId)->exists()) {
                throw new RuntimeException('掲示板の投稿が見つかりません。');
            }

            $now = now();
            $replyId = DB::table('t_board_replies')->insertGetId([
                'thread_id' => $threadId,
                'body' => $body,
                'author_user_id' => $userId,
                'author_name' => $userName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->storeAttachments($threadId, $replyId, $files, $storedPaths);
            DB::table('t_board_threads')->where('id', $threadId)->update([
                'last_activity_at' => $now,
                'updated_at' => $now,
            ]);
            DB::commit();

            return $replyId;
        } catch (\Throwable $e) {
            DB::rollBack();
            Storage::disk('local')->delete($storedPaths);
            throw $e;
        }
    }

    /**
     * @return array{liked: bool, count: int}
     */
    public function toggleLike(int $threadId, int $userId): array
    {
        $liked = DB::transaction(function () use ($threadId, $userId): bool {
            $existing = DB::table('t_board_likes')
                ->where('thread_id', $threadId)
                ->where('user_id', $userId);

            if ($existing->exists()) {
                $existing->delete();

                return false;
            }

            DB::table('t_board_likes')->insert([
                'thread_id' => $threadId,
                'user_id' => $userId,
                'created_at' => now(),
            ]);

            return true;
        });

        return [
            'liked' => $liked,
            'count' => DB::table('t_board_likes')->where('thread_id', $threadId)->count()
                + (int) DB::table('t_board_threads')->where('id', $threadId)->value('legacy_like_count'),
        ];
    }

    public function findAttachment(int $attachmentId): ?object
    {
        return DB::table('t_board_attachments')->where('id', $attachmentId)->first();
    }

    public function deleteThread(int $threadId): bool
    {
        $attachments = DB::table('t_board_attachments')
            ->where('thread_id', $threadId)
            ->get(['disk', 'path']);

        $deleted = DB::transaction(function () use ($threadId): bool {
            DB::table('t_board_likes')->where('thread_id', $threadId)->delete();
            DB::table('t_board_attachments')->where('thread_id', $threadId)->delete();
            DB::table('t_board_replies')->where('thread_id', $threadId)->delete();

            return DB::table('t_board_threads')->where('id', $threadId)->delete() > 0;
        });

        if ($deleted) {
            $this->deleteStoredFiles($attachments);
        }

        return $deleted;
    }

    public function deleteReply(int $threadId, int $replyId): bool
    {
        $attachments = DB::table('t_board_attachments')
            ->where('thread_id', $threadId)
            ->where('reply_id', $replyId)
            ->get(['disk', 'path']);

        $deleted = DB::transaction(function () use ($threadId, $replyId): bool {
            DB::table('t_board_attachments')
                ->where('thread_id', $threadId)
                ->where('reply_id', $replyId)
                ->delete();

            $deletedReply = DB::table('t_board_replies')
                ->where('thread_id', $threadId)
                ->where('id', $replyId)
                ->delete() > 0;

            $latestReply = DB::table('t_board_replies')
                ->where('thread_id', $threadId)
                ->max('created_at');
            $threadCreated = DB::table('t_board_threads')->where('id', $threadId)->value('created_at');
            DB::table('t_board_threads')->where('id', $threadId)->update([
                'last_activity_at' => $latestReply ?: $threadCreated,
                'updated_at' => now(),
            ]);

            return $deletedReply;
        });

        if ($deleted) {
            $this->deleteStoredFiles($attachments);
        }

        return $deleted;
    }

    public function findReply(int $threadId, int $replyId): ?object
    {
        return DB::table('t_board_replies')
            ->where('thread_id', $threadId)
            ->where('id', $replyId)
            ->first();
    }

    private function attachmentsFor(int $threadId, ?int $replyId): Collection
    {
        $query = DB::table('t_board_attachments')->where('thread_id', $threadId);
        $replyId === null ? $query->whereNull('reply_id') : $query->where('reply_id', $replyId);

        return $query->orderBy('id')->get();
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, string>  $storedPaths
     */
    private function storeAttachments(int $threadId, ?int $replyId, array $files, array &$storedPaths): void
    {
        foreach (array_slice($files, 0, self::ATTACHMENT_LIMIT) as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $filename = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');
            $directory = 'board/'.$threadId.($replyId ? '/replies/'.$replyId : '/thread');
            $path = $file->storeAs($directory, $filename, 'local');
            if (! is_string($path)) {
                throw new RuntimeException('添付ファイルを保存できませんでした。');
            }
            $storedPaths[] = $path;

            $mimeType = strtolower((string) $file->getMimeType());
            $isImage = str_starts_with($mimeType, 'image/');

            DB::table('t_board_attachments')->insert([
                'thread_id' => $threadId,
                'reply_id' => $replyId,
                'disk' => 'local',
                'path' => $path,
                'original_name' => mb_substr(
                    preg_replace('/[\x00-\x1F\x7F]+/u', '_', $file->getClientOriginalName()) ?: 'attachment',
                    0,
                    255,
                ),
                'mime_type' => $mimeType,
                'size' => $file->getSize() ?: 0,
                'ai_analysis_status' => $isImage ? 'pending' : 'not_applicable',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function deleteStoredFiles(Collection $attachments): void
    {
        foreach ($attachments->groupBy('disk') as $disk => $rows) {
            Storage::disk((string) $disk)->delete($rows->pluck('path')->all());
        }
    }
}
