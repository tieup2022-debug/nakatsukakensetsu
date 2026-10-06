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
