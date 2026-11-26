<?php

namespace App\Support;

/**
 * 現場3D の工事と現場の一覧（config/genba3d.php）を、画面で使いやすい形にまとめる。
 *
 * メニュー・一覧ページ・各現場の画面が同じ並びを使えるよう、ここ1か所で組み立てる。
 */
class Genba3dCatalog
{
    /**
     * 工事ごとに並べる工程表の種類（メニューの3段目）。キーは URL に使う。
     * 計画は施工手順・工程表、変更は3Dモデル（変更後の図面から組み立てたもの）を開く。
     * ほかはまだ中身が無く、「準備中」のページを開く。
     */
    public const PHASES = [
        'keiyaku' => '契約',
        'keikaku' => '計画',
        'henko' => '変更',
        'jisshi' => '実施',
        'network' => 'ネットワーク工程表',
    ];

    /** 準備中のページに出す説明。 */
    public const PHASE_NOTES = [
        'keiyaku' => '契約時に発注者へ出す工程表です。PDFで出力できるようにする予定です。',
        'keikaku' => '施工計画の工程表です。設計図と数量から組んだ施工手順と工程表を開きます。',
        'henko' => '設計変更に合わせた工程です。変更後の図面から組み立てた3Dモデルを開きます。',
        'jisshi' => '実際に進んだ日を記録して、予定と並べて見る実施工程表です。',
        'network' => '作業のつながり（先に終わっている必要がある作業）と、遅れると工期に響く作業（クリティカルパス）を示す工程表です。',
    ];

    /**
     * 工事ごとに、その工事の現場をまとめて返す（設定の並び順のまま）。
     *
     * 設定に無い現場のキーは無視する。どの工事にも入っていない現場は、
     * 画面から消えないよう最後に「その他」としてまとめる。
     *
     * @return array<string, array{name: string, sites: array<string, array<string, mixed>>}>
     */
    public static function projects(): array
    {
        $sites = (array) config('genba3d.sites', []);
        $projects = [];
        $used = [];

        foreach ((array) config('genba3d.projects', []) as $key => $project) {
            $own = [];
            foreach ((array) ($project['sites'] ?? []) as $slug) {
                if (isset($sites[$slug]) && ! isset($used[$slug])) {
                    $own[$slug] = $sites[$slug];
                    $used[$slug] = true;
                }
            }
            $projects[(string) $key] = ['name' => (string) ($project['name'] ?? $key), 'sites' => $own];
        }

        $rest = array_diff_key($sites, $used);
        if ($rest !== []) {
            $projects['other'] = ['name' => 'その他', 'sites' => $rest];
        }

        return $projects;
    }

    /**
     * 現場が1つ以上ある工事だけ。
     *
     * @return array<string, array{name: string, sites: array<string, array<string, mixed>>}>
     */
    public static function activeProjects(): array
    {
        return array_filter(self::projects(), fn (array $project): bool => $project['sites'] !== []);
    }

    /**
     * 工事の年度（令和の年）。設定の year、無ければキー（r08-01）か名前（R08-…）の先頭から読む。
     */
    public static function year(string $key, ?string $name = null): ?int
    {
        $set = config("genba3d.projects.{$key}.year");
        if (is_numeric($set)) {
            return (int) $set;
        }
        if (preg_match('/^r(\d{1,2})-/i', $key, $m) || preg_match('/^R(\d{1,2})[-\s]/u', (string) ($name ?? config("genba3d.projects.{$key}.name", '')), $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * 工事を年度ごとにまとめる（古い年度から。年度の分からない工事は最後）。
     *
     * @param  array<string, array{name: string, sites: array<string, array<string, mixed>>}>  $projects
     * @return array<string, array{label: string, projects: array<string, array{name: string, sites: array<string, array<string, mixed>>}>}>
     */
    public static function byYear(array $projects): array
    {
        $groups = [];
        foreach ($projects as $key => $project) {
            $y = self::year((string) $key, $project['name'] ?? null);
            $gk = $y === null ? 'z' : sprintf('r%02d', $y);
            $groups[$gk] ??= ['label' => $y === null ? 'その他' : '令和'.$y.'年度', 'projects' => []];
            $groups[$gk]['projects'][(string) $key] = $project;
        }
        ksort($groups);

        return $groups;
    }

    /**
     * 工程表の種類ごとの行き先。計画は施工手順・工程表、変更は3Dモデル、ほかは準備中のページ。
     *
     * @param  array{name: string, sites: array<string, array<string, mixed>>}  $project
     */
    public static function phaseUrl(string $key, array $project, string $phase): string
    {
        $first = array_key_first($project['sites']);
        if ($phase === 'henko' && $first !== null) {
            return route('genba3d.show', ['site' => $first]);
        }
        if ($phase === 'keikaku') {
            foreach ($project['sites'] as $slug => $site) {
                if (! empty($site['schedule_file'])) {
                    return route('genba3d.schedule', ['site' => $slug]);
                }
            }
        }

        return route('genba3d.phase', ['project' => $key, 'phase' => $phase]);
    }

    /**
     * いま開いている画面が、どの工程表の種類にあたるか（メニューと画面の印に使う）。
     */
    public static function currentPhase(): ?string
    {
        $r = request();
        if ($r->routeIs('genba3d.phase')) {
            return (string) $r->route('phase');
        }
        if ($r->routeIs('genba3d.show')) {
            return 'henko';
        }
        if ($r->routeIs('genba3d.steps', 'genba3d.schedule', 'genba3d.page')) {
            return 'keikaku';
        }

        return null;
    }

    /**
     * いま開いている画面の工事のキー。
     */
    public static function currentProjectKey(): ?string
    {
        $r = request();
        if ($r->routeIs('genba3d.phase')) {
            return (string) $r->route('project');
        }
        $site = (string) $r->route('site');

        return $site !== '' ? (self::projectOf($site)['key'] ?? null) : null;
    }

    /**
     * その現場が入っている工事（キーと中身）。見つからなければ null。
     *
     * @return array{key: string, name: string, sites: array<string, array<string, mixed>>}|null
     */
    public static function projectOf(string $site): ?array
    {
        foreach (self::projects() as $key => $project) {
            if (isset($project['sites'][$site])) {
                return ['key' => (string) $key] + $project;
            }
        }

        return null;
    }
}
