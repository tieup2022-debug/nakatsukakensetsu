<?php

namespace App\Services;

use App\Support\DatetimeDisplay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 現場3D: 工事ごとの工期（開始日・工期末）の保存と読み出し。
 *
 * 入力された値（t_genba3d_terms）を優先し、無ければ config/genba3d.php の工事の term
 * （設計図書から拾った値）を「設計図書の値」として返す。
 * ログインした人なら誰でも入力できる。変更のたびに t_genba3d_term_logs へ記録を残す。
 */
class Genba3dTermService
{
    public const TABLE = 't_genba3d_terms';

    public const LOGS = 't_genba3d_term_logs';

    public function ready(): bool
    {
        try {
            return Schema::hasTable(self::TABLE) && Schema::hasTable(self::LOGS);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 工事ごとの工期。
     *
     * @param  list<string>  $projectKeys
     * @return array<string, array{start: ?string, end: ?string, source: ?string, updatedBy: ?string, updatedAt: ?string}>
     */
    public function terms(array $projectKeys): array
    {
        $saved = [];
        if ($this->ready() && $projectKeys !== []) {
            foreach (DB::table(self::TABLE)->whereIn('project', $projectKeys)->get() as $row) {
                $saved[(string) $row->project] = $row;
            }
        }

        $out = [];
        foreach ($projectKeys as $key) {
            $out[$key] = $this->resolve($key, $saved[$key] ?? null);
        }

        return $out;
    }

    /** @return array{start: ?string, end: ?string, source: ?string, updatedBy: ?string, updatedAt: ?string} */
    public function term(string $projectKey): array
    {
        return $this->terms([$projectKey])[$projectKey];
    }

    /**
     * 工期を保存し、変更の記録を残す。前と同じ値なら何もしない（false を返す）。
     */
    public function save(string $projectKey, ?string $start, ?string $end, object $user): bool
    {
        $now = now();
        $before = DB::table(self::TABLE)->where('project', $projectKey)->first();
        $oldStart = $before && $before->start_date ? substr((string) $before->start_date, 0, 10) : null;
        $oldEnd = $before && $before->end_date ? substr((string) $before->end_date, 0, 10) : null;
        if ($before !== null && $oldStart === $start && $oldEnd === $end) {
            return false;
        }

        DB::table(self::LOGS)->insert([
            'project' => $projectKey,
            'old_start' => $oldStart,
            'old_end' => $oldEnd,
            'new_start' => $start,
            'new_end' => $end,
            'user_id' => (int) $user->id,
            'user_name' => (string) ($user->user_name ?? ''),
            'created_at' => $now,
        ]);

        DB::table(self::TABLE)->updateOrInsert(
            ['project' => $projectKey],
            [
                'start_date' => $start,
                'end_date' => $end,
                'updated_user_id' => (int) $user->id,
                'updated_name' => (string) ($user->user_name ?? ''),
                'updated_at' => $now,
            ] + ($before !== null ? [] : ['created_at' => $now]),
        );

        return true;
    }

    /**
     * 変更の記録（新しい順）。
     *
     * @return list<array{at: string, by: string, oldStart: ?string, oldEnd: ?string, newStart: ?string, newEnd: ?string}>
     */
    public function logs(string $projectKey, int $limit = 20): array
    {
        if (! $this->ready()) {
            return [];
        }
        $d = fn ($v): ?string => $v ? substr((string) $v, 0, 10) : null;

        return DB::table(self::LOGS)->where('project', $projectKey)->orderByDesc('id')->limit($limit)->get()
            ->map(fn ($r): array => [
                'at' => DatetimeDisplay::formatStoredAt($r->created_at, 'Y/n/j G:i'),
                'by' => (string) ($r->user_name ?? ''),
                'oldStart' => $d($r->old_start),
                'oldEnd' => $d($r->old_end),
                'newStart' => $d($r->new_start),
                'newEnd' => $d($r->new_end),
            ])->all();
    }

    /**
     * 工期の進み具合（一覧の表示用）。
     *
     * @param  array{start: ?string, end: ?string}  $term
     * @return array{days: ?int, left: ?int, percent: ?int, state: string}
     */
    public static function progress(array $term, ?string $today = null): array
    {
        $today = $today ?? now()->toDateString();
        $end = $term['end'] ?? null;
        $start = $term['start'] ?? null;
        $left = $end ? (int) round((strtotime($end) - strtotime($today)) / 86400) : null;
        $days = $start && $end ? (int) round((strtotime($end) - strtotime($start)) / 86400) + 1 : null;
        $percent = null;
        if ($days) {
            $passed = (int) round((strtotime($today) - strtotime($start)) / 86400) + 1;
            $percent = max(0, min(100, (int) round($passed / $days * 100)));
        }
        $state = $end === null ? 'none' : ($start !== null && $today < $start ? 'before' : ($left < 0 ? 'over' : 'running'));

        return ['days' => $days, 'left' => $left, 'percent' => $percent, 'state' => $state];
    }

    /** @return array{start: ?string, end: ?string, source: ?string, updatedBy: ?string, updatedAt: ?string} */
    private function resolve(string $key, ?object $row): array
    {
        if ($row !== null && ($row->start_date || $row->end_date)) {
            return [
                'start' => $row->start_date ? substr((string) $row->start_date, 0, 10) : null,
                'end' => $row->end_date ? substr((string) $row->end_date, 0, 10) : null,
                'source' => 'saved',
                'updatedBy' => $row->updated_name ?: null,
                'updatedAt' => $row->updated_at ? DatetimeDisplay::formatStoredAt($row->updated_at, 'Y/n/j G:i') : null,
            ];
        }

        $default = (array) config("genba3d.projects.{$key}.term", []);
        $start = $this->date($default['start'] ?? null);
        $end = $this->date($default['end'] ?? null);

        return [
            'start' => $start,
            'end' => $end,
            'source' => $start || $end ? 'default' : null,
            'updatedBy' => null,
            'updatedAt' => null,
        ];
    }

    private function date(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^\d{4}-\d\d-\d\d$/', $v) ? $v : null;
    }
}
