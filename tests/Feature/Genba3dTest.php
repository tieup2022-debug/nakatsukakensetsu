<?php

namespace Tests\Feature;

use Tests\TestCase;

class Genba3dTest extends TestCase
{
    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('genba3d.index'))->assertRedirect(route('login'));

        foreach (['genba3d.show', 'genba3d.model', 'genba3d.schedule', 'genba3d.schedule.page'] as $name) {
            $this->get(route($name, ['site' => 'asahi-funaageba']))->assertRedirect(route('login'));
        }
        foreach (['genba3d.page', 'genba3d.page.raw'] as $name) {
            $this->get(route($name, ['site' => 'asahi-higashi-gogan', 'page' => 'ashiba']))->assertRedirect(route('login'));
        }
    }

    public function test_index_lists_every_project_with_links_to_each_page(): void
    {
        $page = $this->withSession(['login_user_id' => 1])->get(route('genba3d.index'));
        $page->assertOk();

        // 現場のある工事: 現場ごとに、3Dモデル・工程表・資料へ直接行ける
        $page->assertSeeInOrder(['R08-16大沢', '3現場', '朝日地区 船揚場', '朝日地区 東護岸', '大沢漁港海岸 護岸工']);
        foreach (config('genba3d.projects.r08-16.sites') as $slug) {
            $site = config("genba3d.sites.{$slug}");
            $page->assertSee($site['summary']);
            $page->assertSee('href="'.route('genba3d.show', ['site' => $slug]).'"', false);
            $page->assertSee('href="'.route('genba3d.schedule', ['site' => $slug]).'"', false);
        }
        $page->assertSee('href="'.route('genba3d.page', ['site' => 'asahi-higashi-gogan', 'page' => 'ashiba']).'"', false);
        $page->assertSee('重機用足場');

        // 現場のまだ無い工事は「準備中」に名前だけ出る
        $page->assertSeeInOrder(['準備中の工事', 'R08-01福島トンネル', 'R08-02魚礁', 'R08-03桧倉', 'R08-04軌道施設', 'R08-06桧倉維持', 'R08-08滝ノ下', 'R08-11吉岡', 'R08-12岩部線', 'R08-14豊浜']);
    }

    public function test_every_site_belongs_to_exactly_one_project(): void
    {
        $listed = [];
        foreach (config('genba3d.projects') as $key => $project) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', (string) $key);
            foreach ($project['sites'] as $slug) {
                $this->assertArrayHasKey($slug, config('genba3d.sites'), "工事 {$key} の現場 {$slug} が sites に無い");
                $listed[] = $slug;
            }
        }
        sort($listed);
        $all = array_keys(config('genba3d.sites'));
        sort($all);

        $this->assertSame($all, $listed);
    }

    public function test_site_outside_any_project_is_still_listed(): void
    {
        config(['genba3d.projects' => [
            'r08-16' => ['name' => 'R08-16大沢', 'sites' => ['asahi-funaageba', 'no-such-site']],
        ]]);

        $page = $this->withSession(['login_user_id' => 1])->get(route('genba3d.index'));
        $page->assertOk();
        // 工事に入れ忘れた現場は「その他」に出る。設定に無い現場のキーは無視する
        $page->assertSeeInOrder(['R08-16大沢', '1現場', '朝日地区 船揚場', 'その他', '2現場', '朝日地区 東護岸', '大沢漁港海岸 護岸工']);
        $page->assertDontSee('準備中の工事');
    }

    public function test_every_site_serves_its_model_as_html(): void
    {
        $sites = config('genba3d.sites');
        $this->assertCount(3, $sites);

        foreach ($sites as $slug => $site) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
            $this->assertFileExists(resource_path('genba3d/'.$site['file']));

            $response = $this->withSession(['login_user_id' => 1])
                ->get(route('genba3d.model', ['site' => $slug]));

            $response->assertOk();
            $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
            $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $response->assertSee('<html lang="ja" data-theme="light">', false);
            $response->assertSee('three.min.js', false);
        }
    }

    public function test_every_site_serves_its_schedule_as_html(): void
    {
        foreach (config('genba3d.sites') as $slug => $site) {
            $this->assertFileExists(resource_path('genba3d/'.$site['schedule_file']));

            $response = $this->withSession(['login_user_id' => 1])
                ->get(route('genba3d.schedule.page', ['site' => $slug]));

            $response->assertOk();
            $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
            $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $response->assertSee('<html lang="ja" data-theme="light">', false);
            $response->assertSee('工程表');
        }
    }

    public function test_site_without_a_schedule_has_no_schedule_page(): void
    {
        $sites = config('genba3d.sites');
        $slug = array_key_first($sites);
        unset($sites[$slug]['schedule_file']);
        config(['genba3d.sites' => $sites]);

        $this->withSession(['login_user_id' => 1])
            ->get(route('genba3d.schedule', ['site' => $slug]))
            ->assertNotFound();
        $this->withSession(['login_user_id' => 1])
            ->get(route('genba3d.schedule.page', ['site' => $slug]))
            ->assertNotFound();

        $page = $this->view('genba3d.show', [
            'sites' => $sites,
            'currentSlug' => $slug,
            'current' => $sites[$slug],
            'tab' => 'model',
        ]);
        $page->assertSee('3Dモデル');
        $page->assertDontSee('施工手順と工程表');
    }

    public function test_unknown_site_is_not_found(): void
    {
        foreach (['genba3d.show', 'genba3d.model', 'genba3d.schedule', 'genba3d.schedule.page'] as $name) {
            $this->withSession(['login_user_id' => 1])
                ->get(route($name, ['site' => 'no-such-site']))
                ->assertNotFound();
        }
    }

    public function test_menu_shows_one_entry_per_project(): void
    {
        $first = config('genba3d.projects.r08-16.sites.0');

        // レイアウトと同じメニュー部品を、PC用・スマホ用の両方の設定で描く。
        foreach ([false, true] as $dismiss) {
            $menu = $this->withSession(['login_user_id' => 1])
                ->view('layouts.partials.app-sidebar-nav', ['dismissOffcanvas' => $dismiss]);

            $menu->assertSeeInOrder(['現場3D', '一覧', 'R08-16大沢', 'お問い合わせ']);
            $menu->assertSee('href="'.route('genba3d.index').'"', false);
            $menu->assertSee('href="'.route('genba3d.show', ['site' => $first]).'"', false);
            // 現場の名前と、準備中の工事はメニューに並べない
            foreach (config('genba3d.sites') as $site) {
                $menu->assertDontSee($site['name']);
            }
            $menu->assertDontSee('R08-01福島トンネル');
        }
    }

    public function test_menu_marks_the_project_of_the_open_site(): void
    {
        $this->withSession(['login_user_id' => 1])
            ->get(route('genba3d.schedule', ['site' => 'osawa-kaigan-gogan']))
            ->assertOk()
            ->assertSee('class="nav-link py-1 small active"', false);

        $index = $this->withSession(['login_user_id' => 1])->get(route('genba3d.index'));
        $this->assertMatchesRegularExpression('/class="nav-link py-1 small active">\s*一覧/u', (string) $index->getContent());
    }

    public function test_site_page_offers_only_the_sites_of_its_project(): void
    {
        // 別の工事に現場を1つ足しても、ほかの工事の画面には並ばない
        $sites = config('genba3d.sites');
        $sites['fukushima-tunnel'] = ['name' => '福島トンネル 坑口', 'summary' => 'テスト用', 'file' => $sites['asahi-funaageba']['file']];
        $projects = config('genba3d.projects');
        $projects['r08-01']['sites'] = ['fukushima-tunnel'];
        config(['genba3d.sites' => $sites, 'genba3d.projects' => $projects]);

        $osawa = $this->withSession(['login_user_id' => 1])->get(route('genba3d.show', ['site' => 'asahi-higashi-gogan']));
        $osawa->assertOk();
        $osawa->assertSee('<h1 class="h4 mb-1 fw-semibold">R08-16大沢</h1>', false);
        $osawa->assertSee('href="'.route('genba3d.index').'"', false);
        foreach (['asahi-funaageba', 'asahi-higashi-gogan', 'osawa-kaigan-gogan'] as $slug) {
            $osawa->assertSee('href="'.route('genba3d.show', ['site' => $slug]).'"', false);
        }
        $osawa->assertDontSee('福島トンネル 坑口');

        $tunnel = $this->withSession(['login_user_id' => 1])->get(route('genba3d.show', ['site' => 'fukushima-tunnel']));
        $tunnel->assertOk();
        $tunnel->assertSee('<h1 class="h4 mb-1 fw-semibold">R08-01福島トンネル</h1>', false);
        $tunnel->assertSee('福島トンネル 坑口');
        $tunnel->assertDontSee('朝日地区 船揚場');
        // メニューには、現場ができた工事が設定の並び順で出る
        $tunnel->assertSeeInOrder(['一覧', 'R08-01福島トンネル', 'R08-16大沢', 'お問い合わせ']);
    }

    public function test_schedule_tab_embeds_the_schedule_and_keeps_the_tab_across_sites(): void
    {
        $sites = config('genba3d.sites');
        $current = array_key_first($sites);

        $page = $this->view('genba3d.show', [
            'sites' => $sites,
            'currentSlug' => $current,
            'current' => $sites[$current],
            'tab' => 'schedule',
        ]);

        $page->assertSee('src="'.route('genba3d.schedule.page', ['site' => $current]).'"', false);
        foreach (array_keys($sites) as $slug) {
            $page->assertSee('href="'.route('genba3d.schedule', ['site' => $slug]).'"', false);
        }
    }

    public function test_extra_page_is_shown_as_a_tab_of_its_site(): void
    {
        $site = 'asahi-higashi-gogan';
        $this->assertFileExists(resource_path('genba3d/'.config("genba3d.sites.{$site}.pages.ashiba.file")));

        // 追加資料のタブ: 本体を枠に入れ、説明文を出し、タブを選択中にする
        $tab = $this->withSession(['login_user_id' => 1])
            ->get(route('genba3d.page', ['site' => $site, 'page' => 'ashiba']));
        $tab->assertOk()
            ->assertSee('src="'.route('genba3d.page.raw', ['site' => $site, 'page' => 'ashiba']).'"', false)
            ->assertSee('重機用足場の形・数量とクレーンの位置をまとめた資料')
            ->assertSee('is-document', false);
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>重機用足場<\/a>/u', (string) $tab->getContent());

        // 本体
        $raw = $this->withSession(['login_user_id' => 1])
            ->get(route('genba3d.page.raw', ['site' => $site, 'page' => 'ashiba']));
        $raw->assertOk();
        $raw->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $raw->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $raw->assertSee('<html lang="ja" data-theme="light">', false);
        $raw->assertSee('重機用足場');

        // ほかのタブからも入口が見える。資料の無い現場には出ない
        foreach (['genba3d.show', 'genba3d.schedule'] as $name) {
            $this->withSession(['login_user_id' => 1])
                ->get(route($name, ['site' => $site]))
                ->assertOk()
                ->assertSee(route('genba3d.page', ['site' => $site, 'page' => 'ashiba']), false);
        }
        $this->withSession(['login_user_id' => 1])
            ->get(route('genba3d.show', ['site' => 'asahi-funaageba']))
            ->assertOk()
            ->assertDontSee('/shiryo/', false);
    }

    public function test_unknown_extra_page_is_not_found(): void
    {
        foreach (['genba3d.page', 'genba3d.page.raw'] as $name) {
            $this->withSession(['login_user_id' => 1])
                ->get(route($name, ['site' => 'asahi-higashi-gogan', 'page' => 'no-such-page']))
                ->assertNotFound();
            // ほかの現場の資料は、その現場の URL では開けない
            $this->withSession(['login_user_id' => 1])
                ->get(route($name, ['site' => 'asahi-funaageba', 'page' => 'ashiba']))
                ->assertNotFound();
            $this->withSession(['login_user_id' => 1])
                ->get(route($name, ['site' => 'no-such-site', 'page' => 'ashiba']))
                ->assertNotFound();
        }
    }
}
