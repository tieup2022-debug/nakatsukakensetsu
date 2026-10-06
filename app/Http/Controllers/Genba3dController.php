<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * 現場3D: 設計図から組み立てた3Dモデルと、施工手順・工程表を、ログイン済みのユーザーに見せる。
 *
 * ページ本体は resources/genba3d/ に置いた1枚ものの HTML で、公開フォルダには置かない
 * （ログインなしで URL を直接開かれないようにするため）。一覧は config/genba3d.php。
 */
class Genba3dController extends Controller
{
    /** メニューの「現場3D」直下: 先頭の現場へ送る。 */
    public function index(): RedirectResponse
    {
        $slug = array_key_first($this->sites());
        abort_if($slug === null, 404);

        return redirect()->route('genba3d.show', ['site' => $slug]);
    }

    /** 3Dモデルのタブ。 */
    public function show(string $site): View
    {
        return $this->page($site, 'model');
    }

    /** 施工手順と工程表のタブ。 */
    public function schedule(string $site): View
    {
        return $this->page($site, 'schedule');
    }

    /** 3Dモデル本体の HTML（画面内の iframe と「全画面で開く」の両方から使う）。 */
    public function model(string $site): Response
    {
        return $this->document($site, 'file');
    }

    /** 施工手順と工程表の本体の HTML。 */
    public function schedulePage(string $site): Response
    {
        return $this->document($site, 'schedule_file');
    }

    /** 現場とタブを切り替える枠と、本体を埋め込んだ画面。 */
    private function page(string $site, string $tab): View
    {
        $sites = $this->sites();
        abort_unless(isset($sites[$site]), 404);
        abort_if($tab === 'schedule' && empty($sites[$site]['schedule_file']), 404);

        return view('genba3d.show', [
            'title' => '現場3D｜'.$sites[$site]['name'],
            'sites' => $sites,
            'currentSlug' => $site,
            'current' => $sites[$site],
            'tab' => $tab,
        ]);
    }

    private function document(string $site, string $key): Response
    {
        $sites = $this->sites();
        abort_unless(isset($sites[$site]) && ! empty($sites[$site][$key]), 404);

        // ファイル名は設定からだけ決める（URL の文字列をパスに使わない）。
        $path = resource_path('genba3d/'.basename((string) $sites[$site][$key]));
        abort_unless(is_file($path), 404);

        $html = (string) file_get_contents($path);
        // アプリ本体が明るい配色なので、端末の設定に関わらず明るい配色に固定する。
        $html = (string) preg_replace('/<html(?=[\s>])/i', '<html lang="ja" data-theme="light"', $html, 1);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * @return array<string, array{name: string, summary: string, file: string, schedule_file?: string}>
     */
    private function sites(): array
    {
        return (array) config('genba3d.sites', []);
    }
}
