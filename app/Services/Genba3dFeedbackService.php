<?php

namespace App\Services;

use App\Support\DatetimeDisplay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 現場3D「現場の声」: 書き込み・返信・対応状況の保存と読み出し。
 *
 * 書き込みはログインした全員が読める（現場の情報を共有するため）。
 * 対応状況の変更は管理者（m_user.permission === 1）だけ。
 */
class Genba3dFeedbackService
{
    public const TABLE = 't_genba3d_feedback';

    public const REPLIES = 't_genba3d_feedback_replies';

    /** @return array<string, string> */
    public static function kinds(): array
    {
        return [
            'request' => 'こうしてほしい',
            'info' => '現場の情報',
            'other' => 'その他',
        ];
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            'open' => '未対応',
            'doing' => '対応中',
            'done' => '対応済',
            'skip' => '見送り',
        ];
    }

    public function ready(): bool
    {
        return Schema::hasTable(self::TABLE) && Schema::hasTable(self::REPLIES);
    }

    /**
     * @param  array{p: list<float>, cam: list<float>, tgt: list<float>, part?: string}|null  $pin
     */
    public function create(string $site, string $tab, string $kind, string $body, ?array $pin, object $user): int
    {
        return (int) DB::table(self::TABLE)->insertGetId([
            'site' => $site,
            'tab' => $tab,
            'kind' => $kind,
            'body' => $body,
            'pin' => $pin === null ? null : json_encode($pin, JSON_UNESCAPED_UNICODE),
            'status' => 'open',
            'author_user_id' => (int) $user->id,
            'author_name' => (string) $user->user_name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function find(int $id): ?object
    {
        return DB::table(self::TABLE)->where('id', $id)->first();
    }

    public function reply(object $feedback, string $body, object $user, bool $isMaster): int
    {
        $id = (int) DB::table(self::REPLIES)->insertGetId([
            'feedback_id' => (int) $feedback->id,
            'body' => $body,
            'author_user_id' => (int) $user->id,
            'author_name' => (string) $user->user_name,
            'is_master' => $isMaster,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(self::TABLE)->where('id', $feedback->id)->update(['updated_at' => now()]);

        return $id;
    }

    public function setStatus(object $feedback, string $status): void
    {
        DB::table(self::TABLE)->where('id', $feedback->id)->update(['status' => $status, 'updated_at' => now()]);
    }

    public function delete(object $feedback): void
    {
        DB::table(self::REPLIES)->where('feedback_id', $feedback->id)->delete();
        DB::table(self::TABLE)->where('id', $feedback->id)->delete();
    }

    public function replyCount(int $feedbackId): int
    {
        return (int) DB::table(self::REPLIES)->where('feedback_id', $feedbackId)->count();
    }

    /**
     * 画面に渡す形に整える。新しい順。
     *
     * @param  array<string, array<string, mixed>>  $sites  config('genba3d.sites')
     * @param  array{site?: string, status?: string}  $filter
     * @return list<array<string, mixed>>
     */
    public function list(array $sites, array $filter, int $viewerId, bool $viewerIsMaster, int $limit = 300): array
    {
        $q = DB::table(self::TABLE)->orderByDesc('id')->limit($limit);
        if (! empty($filter['site'])) {
            $q->where('site', $filter['site']);
        }
        if (! empty($filter['status'])) {
            $q->where('status', $filter['status']);
        }
        $rows = $q->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $replies = DB::table(self::REPLIES)
            ->whereIn('feedback_id', $rows->pluck('id')->all())
            ->orderBy('id')
            ->get()
            ->groupBy('feedback_id');

        $kinds = self::kinds();
        $statuses = self::statuses();

        return $rows->map(function (object $r) use ($replies, $kinds, $statuses, $sites, $viewerId, $viewerIsMaster): array {
            $pin = $r->pin ? json_decode((string) $r->pin, true) : null;
            $site = $sites[$r->site] ?? null;
            $own = (int) $r->author_user_id === $viewerId;
            $rs = $replies->get($r->id, collect());

            return [
                'id' => (int) $r->id,
                'site' => (string) $r->site,
                'siteName' => (string) ($site['name'] ?? $r->site),
                'tab' => (string) $r->tab,
                'tabLabel' => $this->tabLabel($site, (string) $r->tab),
                'kind' => (string) $r->kind,
                'kindLabel' => $kinds[$r->kind] ?? 'その他',
                'status' => (string) $r->status,
                'statusLabel' => $statuses[$r->status] ?? '未対応',
                'body' => (string) $r->body,
                'pin' => is_array($pin) ? $pin : null,
                'author' => (string) $r->author_name,
                'own' => $own,
                'createdAt' => DatetimeDisplay::formatStoredAt($r->created_at, 'n/j G:i'),
                'createdDate' => DatetimeDisplay::formatStoredAt($r->created_at, 'Y/m/d'),
                // 自分の書き込みは、まだ返信が無いうちだけ消せる。管理者はいつでも消せる。
                'canDelete' => $viewerIsMaster || ($own && $rs->isEmpty()),
                'replies' => $rs->map(fn (object $x): array => [
                    'id' => (int) $x->id,
                    'body' => (string) $x->body,
                    'author' => (string) $x->author_name,
                    'isMaster' => (bool) $x->is_master,
                    'createdAt' => DatetimeDisplay::formatStoredAt($x->created_at, 'n/j G:i'),
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * 現場ごとの未対応・対応中の件数。
     *
     * @return array<string, int>
     */
    public function openCounts(): array
    {
        if (! $this->ready()) {
            return [];
        }

        return DB::table(self::TABLE)
            ->whereIn('status', ['open', 'doing'])
            ->selectRaw('site, count(*) as n')
            ->groupBy('site')
            ->pluck('n', 'site')
            ->map(fn ($n): int => (int) $n)
            ->all();
    }

    /**
     * 管理者（権限1）の一覧。通知の送り先に使う。
     *
     * @return list<int>
     */
    public function masterIds(): array
    {
        return DB::table('m_user')
            ->where('permission', 1)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @param  array<string, mixed>|null  $site */
    private function tabLabel(?array $site, string $tab): string
    {
        if ($tab === 'model') {
            return '3Dモデル';
        }
        if ($tab === 'schedule') {
            return '施工手順と工程表';
        }

        return (string) ($site['pages'][$tab]['label'] ?? $tab);
    }
}
