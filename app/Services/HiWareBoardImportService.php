<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class HiWareBoardImportService
{
    /** @var array<string, int|null> */
    private array $userIdByName = [];

    /**
     * @param  array<string, mixed>  $record
     * @return array{thread_id:int,created:bool,replies:int,attachments:int}
     */
    public function import(array $record, string $exportRoot, bool $skipAttachments = false): array
    {
        $legacyId = (string) ($record['legacy_id'] ?? '');
        if (! preg_match('/^\d{15}$/', $legacyId) || trim((string) ($record['title'] ?? '')) === '') {
            throw new RuntimeException('旧投稿IDまたはタイトルが空です。');
        }

        $copiedPaths = [];
        DB::beginTransaction();
        try {
            $existing = DB::table('t_board_threads')->where('legacy_id', $legacyId)->first();
            $created = ! $existing;
            $threadValues = [
                'legacy_id' => $legacyId,
                'title' => mb_substr((string) $record['title'], 0, 255),
                'body' => (string) ($record['body'] ?? ''),
                'author_user_id' => $this->userIdForName((string) ($record['author_name'] ?? '')),
                'author_name' => mb_substr(trim((string) ($record['author_name'] ?? '')) ?: '不明', 0, 255),
                'author_email' => $this->nullableString($record['author_email'] ?? null, 255),
                'view_count' => max((int) ($record['view_count'] ?? 0), (int) ($existing->view_count ?? 0)),
                'legacy_like_count' => (int) ($record['legacy_like_count'] ?? 0),
                'last_activity_at' => $this->lastActivityAt($record),
                'created_at' => $record['created_at'] ?? now(),
                'updated_at' => $record['last_activity_at'] ?? $record['created_at'] ?? now(),
            ];

            if ($existing) {
                DB::table('t_board_threads')->where('id', $existing->id)->update($threadValues);
                $threadId = (int) $existing->id;
            } else {
                $threadId = (int) DB::table('t_board_threads')->insertGetId($threadValues);
            }

            $attachmentCount = 0;
            if (! $skipAttachments) {
                $attachmentCount += $this->importAttachments(
                    $threadId,
                    null,
                    $legacyId,
                    (array) ($record['attachments'] ?? []),
                    $exportRoot,
                    $copiedPaths,
                );
            }

            $replyCount = 0;
            foreach ((array) ($record['replies'] ?? []) as $reply) {
                if (! is_array($reply) || empty($reply['legacy_id'])) {
                    continue;
                }
                $replyLegacyId = (string) $reply['legacy_id'];
                if (! preg_match('/^\d{15}$/', $replyLegacyId)) {
                    throw new RuntimeException('返信の旧投稿IDが不正です: '.$replyLegacyId);
                }
                $replyValues = [
                    'thread_id' => $threadId,
                    'legacy_id' => $replyLegacyId,
                    'body' => (string) ($reply['body'] ?? ''),
                    'author_user_id' => $this->userIdForName((string) ($reply['author_name'] ?? '')),
                    'author_name' => mb_substr(trim((string) ($reply['author_name'] ?? '')) ?: '不明', 0, 255),
                    'author_email' => $this->nullableString($reply['author_email'] ?? null, 255),
                    'legacy_like_count' => (int) ($reply['legacy_like_count'] ?? 0),
                    'created_at' => $reply['created_at'] ?? now(),
                    'updated_at' => $reply['created_at'] ?? now(),
                ];
                $existingReply = DB::table('t_board_replies')->where('legacy_id', $replyLegacyId)->first();
                if ($existingReply) {
                    DB::table('t_board_replies')->where('id', $existingReply->id)->update($replyValues);
                    $replyId = (int) $existingReply->id;
                } else {
                    $replyId = (int) DB::table('t_board_replies')->insertGetId($replyValues);
                }
                $replyCount++;
                if (! $skipAttachments) {
                    $attachmentCount += $this->importAttachments(
                        $threadId,
                        $replyId,
                        $replyLegacyId,
                        (array) ($reply['attachments'] ?? []),
                        $exportRoot,
                        $copiedPaths,
                    );
                }
            }

            DB::commit();

            return [
                'thread_id' => $threadId,
                'created' => $created,
                'replies' => $replyCount,
                'attachments' => $attachmentCount,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Storage::disk('local')->delete($copiedPaths);
            throw $e;
        }
    }

    /**
     * @param  array<int, mixed>  $attachments
     * @param  array<int, string>  $copiedPaths
     */
    private function importAttachments(
        int $threadId,
        ?int $replyId,
        string $legacyPostId,
        array $attachments,
        string $exportRoot,
        array &$copiedPaths,
    ): int {
        $count = 0;
        foreach ($attachments as $position => $attachment) {
            if (! is_array($attachment) || empty($attachment['path'])) {
                throw new RuntimeException('添付ファイルの保存先情報がありません。');
            }
            $index = max(1, (int) ($attachment['index'] ?? $position + 1));
            $attachmentLegacyId = $legacyPostId.':'.str_pad((string) $index, 3, '0', STR_PAD_LEFT);
            if (DB::table('t_board_attachments')->where('legacy_id', $attachmentLegacyId)->exists()) {
                continue;
            }
            $resolvedRoot = realpath($exportRoot);
            $source = realpath($exportRoot.'/'.ltrim((string) $attachment['path'], '/'));
            if ($resolvedRoot === false || $source === false
                || ! str_starts_with($source, $resolvedRoot.DIRECTORY_SEPARATOR)
                || ! is_file($source)) {
                throw new RuntimeException('添付ファイルのパスが不正です: '.(string) $attachment['path']);
            }
            $original = mb_substr(
                preg_replace('/[\x00-\x1F\x7F]+/u', '_', basename((string) ($attachment['original_name'] ?? basename($source)))) ?: 'attachment',
                0,
                255,
            );
            $safe = preg_replace('/[^\pL\pN._-]+/u', '_', $original) ?: 'attachment';
            $destination = sprintf('board/%d/legacy/%s/%03d-%s', $threadId, $legacyPostId, $index, mb_substr($safe, 0, 180));
            $stream = fopen($source, 'rb');
            if ($stream === false || ! Storage::disk('local')->put($destination, $stream)) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                throw new RuntimeException('添付ファイルを保存できません: '.$original);
            }
            fclose($stream);
            $copiedPaths[] = $destination;
            DB::table('t_board_attachments')->insert([
                'legacy_id' => $attachmentLegacyId,
                'thread_id' => $threadId,
                'reply_id' => $replyId,
                'disk' => 'local',
                'path' => $destination,
                'original_name' => $original,
                'mime_type' => $attachment['mime_type'] ?? (mime_content_type($source) ?: null),
                'size' => (int) ($attachment['size'] ?? (filesize($source) ?: 0)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    private function userIdForName(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        if (! array_key_exists($name, $this->userIdByName)) {
            $id = DB::table('m_user')->where('user_name', $name)->whereNull('deleted_at')->value('id');
            $this->userIdByName[$name] = $id !== null ? (int) $id : null;
        }

        return $this->userIdByName[$name];
    }

    /** @param array<string, mixed> $record */
    private function lastActivityAt(array $record): mixed
    {
        $latest = $record['last_activity_at'] ?? $record['created_at'] ?? now();
        foreach ((array) ($record['replies'] ?? []) as $reply) {
            if (is_array($reply) && ! empty($reply['created_at']) && (string) $reply['created_at'] > (string) $latest) {
                $latest = $reply['created_at'];
            }
        }

        return $latest;
    }

    private function nullableString(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? mb_substr($value, 0, $limit) : null;
    }
}
