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
        $page->assertSeeInOrder(['R07-20重内', '6現場', '幸連橋', '重内橋', '神馬橋（右歩道）', '原口大橋', '初神大橋', '寅の沢橋', 'R08-01福島トンネル', '1現場', '福島トンネル補修工事', 'R08-03桧倉', '1現場', '桧倉川支線 開設工事（SP1051〜SP1580）', 'R08-06桧倉維持', '1現場', '桧倉川林道（路盤工・伐開）', 'R08-08滝ノ下', '1現場', '滝ノ下覆道地先 緊急総合治山工事', 'R08-11吉岡', '3現場', '吉岡漁港 -4.5m岸壁', '吉岡漁港 第2船揚場', '吉岡漁港 -3.0m岸壁の側溝', 'R08-12岩部線', '7現場', 'A140 高エネルギー吸収型落石防護柵', 'A150 ポケット式落石防護網', 'A170 落石防護柵（支柱3本）', '滝の下覆道（1・2・7）', '浦和覆道1', '板橋覆道', '第三塩釜覆道', 'R08-14豊浜', '2現場', '福島豊浜 急傾斜地（土留柵工）', '福島川 管理用通路（転落防止柵）', 'R08-16大沢', '3現場', '朝日地区 船揚場', '朝日地区 東護岸', '大沢漁港海岸 護岸工', 'R08-木古内漁港海岸', '1現場', '木古内漁港海岸 緩傾斜護岸（R8施工）']);
        foreach (config('genba3d.sites') as $slug => $site) {
            $page->assertSee($site['summary']);
            $page->assertSee('href="'.route('genba3d.show', ['site' => $slug]).'"', false);
            $page->assertSee('href="'.route('genba3d.schedule', ['site' => $slug]).'"', false);
        }
        $page->assertSee('href="'.route('genba3d.page', ['site' => 'asahi-higashi-gogan', 'page' => 'ashiba']).'"', false);
        $page->assertSee('重機用足場');

        // 現場のまだ無い工事は「準備中」に名前だけ出る
        $page->assertSeeInOrder(['準備中の工事', 'R08-02魚礁', 'R08-04軌道施設']);
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
        $page->assertSeeInOrder(['R08-16大沢', '1現場', '朝日地区 船揚場', 'その他', '25現場', '幸連橋', '重内橋', '神馬橋（右歩道）', '原口大橋', '初神大橋', '寅の沢橋', '朝日地区 東護岸', '大沢漁港海岸 護岸工', '福島トンネル補修工事', '滝ノ下覆道地先 緊急総合治山工事', '吉岡漁港 -4.5m岸壁', '吉岡漁港 第2船揚場', '吉岡漁港 -3.0m岸壁の側溝', '福島豊浜 急傾斜地（土留柵工）', '福島川 管理用通路（転落防止柵）', 'A140 高エネルギー吸収型落石防護柵', 'A150 ポケット式落石防護網', 'A170 落石防護柵（支柱3本）', '滝の下覆道（1・2・7）', '浦和覆道1', '板橋覆道', '第三塩釜覆道', '桧倉川林道（路盤工・伐開）', '桧倉川支線 開設工事（SP1051〜SP1580）', '木古内漁港海岸 緩傾斜護岸（R8施工）']);
        $page->assertDontSee('準備中の工事');
    }

    public function test_every_site_serves_its_model_as_html(): void
    {
        $sites = config('genba3d.sites');
        $this->assertCount(26, $sites);

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
        $page->assertDontSee('>施工手順</a>', false);
        $page->assertDontSee('>工程表</a>', false);
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

        // レイアウトと同じメニュー部品を、PC用・スマホ用の両方の設定で描く。
        foreach ([false, true] as $dismiss) {
            $menu = $this->withSession(['login_user_id' => 1])
                ->view('layouts.partials.app-sidebar-nav', ['dismissOffcanvas' => $dismiss]);

            // 現場のある工事が、設定の並び順で出る。行き先はその工事の最初の現場
            $menu->assertSeeInOrder(['現場3D', '一覧', 'R07-20重内', 'R08-01福島トンネル', 'R08-03桧倉', 'R08-06桧倉維持', 'R08-08滝ノ下', 'R08-11吉岡', 'R08-12岩部線', 'R08-14豊浜', 'R08-16大沢', 'R08-木古内漁港海岸', 'お問い合わせ']);
            $menu->assertSee('href="'.route('genba3d.index').'"', false);
            foreach (['r07-20', 'r08-01', 'r08-03', 'r08-06', 'r08-08', 'r08-11', 'r08-12', 'r08-14', 'r08-16', 'kikonai'] as $key) {
                $menu->assertSee('href="'.route('genba3d.show', ['site' => config("genba3d.projects.{$key}.sites.0")]).'"', false);
            }
            // 現場の名前と、準備中の工事はメニューに並べない
            foreach (config('genba3d.sites') as $site) {
                $menu->assertDontSee($site['name']);
            }
            $menu->assertDontSee('R08-02魚礁');
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
        // 現場の切替えに並ぶのは同じ工事の現場だけ。別の工事の現場は並ばない
        $osawa = $this->withSession(['login_user_id' => 1])->get(route('genba3d.show', ['site' => 'asahi-higashi-gogan']));
        $osawa->assertOk();
        $osawa->assertSee('<h1 class="h4 mb-1 fw-semibold">R08-16大沢</h1>', false);
        $osawa->assertSee('href="'.route('genba3d.index').'"', false);
        foreach (['asahi-funaageba', 'asahi-higashi-gogan', 'osawa-kaigan-gogan'] as $slug) {
            $osawa->assertSee('href="'.route('genba3d.show', ['site' => $slug]).'"', false);
        }
        $osawa->assertDontSee('福島トンネル補修工事');

        $tunnel = $this->withSession(['login_user_id' => 1])->get(route('genba3d.show', ['site' => 'fukushima-tunnel']));
        $tunnel->assertOk();
        $tunnel->assertSee('<h1 class="h4 mb-1 fw-semibold">R08-01福島トンネル</h1>', false);
        $tunnel->assertSee('福島トンネル補修工事');
        $tunnel->assertDontSee('朝日地区 船揚場');
        // メニューには、現場のある工事が設定の並び順で出る
        $tunnel->assertDontSee('滝ノ下覆道地先 緊急総合治山工事');
        $tunnel->assertSeeInOrder(['一覧', 'R07-20重内', 'R08-01福島トンネル', 'R08-03桧倉', 'R08-06桧倉維持', 'R08-08滝ノ下', 'R08-11吉岡', 'R08-12岩部線', 'R08-14豊浜', 'R08-16大沢', 'R08-木古内漁港海岸', 'お問い合わせ']);

        $slope = $this->withSession(['login_user_id' => 1])->get(route('genba3d.show', ['site' => 'takinoshita-chisan']));
        $slope->assertOk();
        $slope->assertSee('<h1 class="h4 mb-1 fw-semibold">R08-08滝ノ下</h1>', false);
        $slope->assertSee('滝ノ下覆道地先 緊急総合治山工事');
        $slope->assertDontSee('福島トンネル補修工事');

        // 1つの工事に3現場。切替えには同じ工事の3現場だけが並び、工程表は3現場で同じものを開く
        $quay = $this->withSession(['login_user_id' => 1])->get(route('genba3d.show', ['site' => 'yoshioka-funaage']));
        $quay->assertOk();
        $quay->assertSee('<h1 class="h4 mb-1 fw-semibold">R08-11吉岡</h1>', false);
        foreach (['yoshioka-ganpeki', 'yoshioka-funaage', 'yoshioka-sokkou'] as $slug) {
            $quay->assertSee('href="'.route('genba3d.show', ['site' => $slug]).'"', false);
            $this->assertSame('yoshioka-kotei.html', config("genba3d.sites.{$slug}.schedule_file"));
        }
        $quay->assertSeeInOrder(['吉岡漁港 -4.5m岸壁', '吉岡漁港 第2船揚場', '吉岡漁港 -3.0m岸壁の側溝']);
        $quay->assertDontSee('朝日地区 船揚場');
        $quay->assertDontSee('滝ノ下覆道地先 緊急総合治山工事');

        // 1つの工事に2現場（急傾斜地と福島川）。工程表は2現場で同じものを開く
        $river = $this->withSession(['login_user_id' => 1])->get(route('genba3d.show', ['site' => 'toyohama-fukushimagawa']));
        $river->assertOk();
        $river->assertSee('<h1 class="h4 mb-1 fw-semibold">R08-14豊浜</h1>', false);
        foreach (['toyohama-kyukeisha', 'toyohama-fukushimagawa'] as $slug) {
            $river->assertSee('href="'.route('genba3d.show', ['site' => $slug]).'"', false);
            $this->assertSame('toyohama-kotei.html', config("genba3d.sites.{$slug}.schedule_file"));
        }
        $river->assertSeeInOrder(['福島豊浜 急傾斜地（土留柵工）', '福島川 管理用通路（転落防止柵）']);
        $river->assertDontSee('吉岡漁港 第2船揚場');

        // 1つの工事に6橋。工程表は6橋で同じものを開く
        $arch = $this->withSession(['login_user_id' => 1])->get(route('genba3d.show', ['site' => 'omonai-haraguchi']));
        $arch->assertOk();
        $arch->assertSee('<h1 class="h4 mb-1 fw-semibold">R07-20重内</h1>', false);
        foreach (['omonai-koren', 'omonai-omonai', 'omonai-shinma', 'omonai-haraguchi', 'omonai-hatsukami', 'omonai-toranosawa'] as $slug) {
            $arch->assertSee('href="'.route('genba3d.show', ['site' => $slug]).'"', false);
            $this->assertSame('omonai-kotei.html', config("genba3d.sites.{$slug}.schedule_file"));
        }
        $arch->assertSeeInOrder(['幸連橋', '重内橋', '神馬橋（右歩道）', '原口大橋', '初神大橋', '寅の沢橋']);
        $arch->assertDontSee('福島川 管理用通路（転落防止柵）');
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

        $page->assertSee('src="'.route('genba3d.schedule.page', ['site' => $current, 'part' => 'gantt']).'"', false);
        foreach (array_keys($sites) as $slug) {
            $page->assertSee('href="'.route('genba3d.schedule', ['site' => $slug]).'"', false);
        }
    }

    public function test_steps_tab_embeds_the_steps_side_and_keeps_the_tab_across_sites(): void
    {
        $sites = config('genba3d.sites');
        $current = array_key_first($sites);

        $page = $this->view('genba3d.show', [
            'sites' => $sites,
            'currentSlug' => $current,
            'current' => $sites[$current],
            'tab' => 'steps',
        ]);

        $page->assertSee('src="'.route('genba3d.schedule.page', ['site' => $current, 'part' => 'steps']).'"', false);
        foreach (array_keys($sites) as $slug) {
            $page->assertSee('href="'.route('genba3d.steps', ['site' => $slug]).'"', false);
        }
    }

    public function test_site_page_shows_the_schedule_kinds_and_the_tabs_of_the_open_kind(): void
    {
        $site = 'kikonai-kyukeisha';
        // 3Dモデルは「変更」、施工手順・工程表は「計画」の中のタブ
        $model = (string) $this->withSession(['login_user_id' => 1])->get(route('genba3d.show', ['site' => $site]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>3Dモデル<\/a>/u', $model);
        $this->assertMatchesRegularExpression('/class="active"\s*aria-current="page"\s*>変更<\/a>/u', $model);
        $this->assertStringNotContainsString('>施工手順</a>', $model);
        $this->assertStringContainsString('令和8年度</a>', $model);

        foreach (['genba3d.steps' => '施工手順', 'genba3d.schedule' => '工程表'] as $name => $label) {
            $html = (string) $this->withSession(['login_user_id' => 1])->get(route($name, ['site' => $site]))->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/aria-current="page"\s*>'.$label.'<\/a>/u', $html);
            $this->assertMatchesRegularExpression('/class="active"\s*aria-current="page"\s*>計画<\/a>/u', $html);
            $this->assertTrue(mb_strpos($html, '>施工手順</a>') < mb_strpos($html, '>工程表</a>'));
            $this->assertStringNotContainsString('>3Dモデル</a>', $html);
        }

        // 工程表の種類は5つ、この並び
        $pos = array_map(fn ($l) => mb_strpos($model, '>'.$l), ['契約', '計画', '変更', '実施', 'ネットワーク工程表']);
        $this->assertSame($pos, array_values(array_filter($pos, fn ($p) => $p !== false)));
        $sorted = $pos;
        sort($sorted);
        $this->assertSame($sorted, $pos);

        // 一覧ページにも、施工手順と工程表へのボタンが別々に出る
        $this->withSession(['login_user_id' => 1])->get(route('genba3d.index'))
            ->assertSee('href="'.route('genba3d.steps', ['site' => $site]).'"', false)
            ->assertSee('href="'.route('genba3d.schedule', ['site' => $site]).'"', false);
    }

    public function test_schedule_file_shows_only_the_requested_side(): void
    {
        foreach (config('genba3d.sites') as $slug => $site) {
            foreach (['steps', 'gantt'] as $part) {
                $this->withSession(['login_user_id' => 1])
                    ->get(route('genba3d.schedule.page', ['site' => $slug, 'part' => $part]))
                    ->assertOk()
                    ->assertSee('data-genba3d-part', false)
                    ->assertSee('var part="'.$part.'"', false);
            }
        }
        // 指定が無い・知らない値なら、これまでどおり全部を見せる
        $this->withSession(['login_user_id' => 1])
            ->get(route('genba3d.schedule.page', ['site' => 'kikonai-kyukeisha', 'part' => 'zzz']))
            ->assertOk()
            ->assertDontSee('data-genba3d-part', false);
        $this->withSession(['login_user_id' => 1])
            ->get(route('genba3d.steps', ['site' => 'no-such-site']))
            ->assertNotFound();
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

        // 計画のほかのタブからも入口が見える。資料の無い現場には出ない
        foreach (['genba3d.steps', 'genba3d.schedule'] as $name) {
            $this->withSession(['login_user_id' => 1])
                ->get(route($name, ['site' => $site]))
                ->assertOk()
                ->assertSee(route('genba3d.page', ['site' => $site, 'page' => 'ashiba']), false);
        }
        $this->withSession(['login_user_id' => 1])
            ->get(route('genba3d.show', ['site' => 'fukushima-tunnel']))
            ->assertOk()
            ->assertDontSee('/shiryo/', false);
    }

    public function test_osawa_sites_have_the_construction_management_tab_third(): void
    {
        foreach (['asahi-funaageba', 'asahi-higashi-gogan', 'osawa-kaigan-gogan'] as $site) {
            $this->assertSame('sekokanri', array_key_first(config("genba3d.sites.{$site}.pages")));
            $this->assertFileExists(resource_path('genba3d/'.config("genba3d.sites.{$site}.pages.sekokanri.file")));

            $tab = $this->withSession(['login_user_id' => 1])
                ->get(route('genba3d.page', ['site' => $site, 'page' => 'sekokanri']));
            $tab->assertOk()->assertSee('is-document', false);
            $this->assertMatchesRegularExpression('/aria-current="page"\s*>施工管理<\/a>/u', (string) $tab->getContent());
            // タブの並び: 3Dモデル → 施工手順 → 工程表 → 施工管理
            $html = (string) $tab->getContent();
            $this->assertNotFalse(mb_strpos($html, '>工程表</a>'));
            $this->assertTrue(mb_strpos($html, '>施工手順</a>') < mb_strpos($html, '>工程表</a>'));
            $this->assertTrue(mb_strpos($html, '>工程表</a>') < mb_strpos($html, '>施工管理</a>'));

            $raw = $this->withSession(['login_user_id' => 1])
                ->get(route('genba3d.page.raw', ['site' => $site, 'page' => 'sekokanri']));
            $raw->assertOk()
                ->assertSee('北海道建設部土木工事共通仕様書')
                ->assertSee('出来形管理')
                ->assertSee('品質管理')
                ->assertSee('段階確認');
        }
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

    public function test_menu_groups_projects_by_year_and_lists_the_schedule_kinds(): void
    {
        $menu = $this->withSession(['login_user_id' => 1])
            ->view('layouts.partials.app-sidebar-nav', ['dismissOffcanvas' => false]);
        $menu->assertSeeInOrder(['現場3D', '一覧', '令和7年度', 'R07-20重内', '令和8年度', 'R08-01福島トンネル', 'R08-木古内漁港海岸']);
        $menu->assertSeeInOrder(['R07-20重内', '契約', '計画', '変更', '実施', 'ネットワーク工程表']);
        // 計画は工程表、変更は3Dモデル、ほかは準備中のページへ
        $menu->assertSee('href="'.route('genba3d.schedule', ['site' => 'omonai-koren']).'"', false);
        $menu->assertSee('href="'.route('genba3d.show', ['site' => 'omonai-koren']).'"', false);
        $menu->assertSee('href="'.route('genba3d.phase', ['project' => 'r07-20', 'phase' => 'network']).'"', false);
        $menu->assertSee('href="'.route('genba3d.phase', ['project' => 'kikonai', 'phase' => 'keiyaku']).'"', false);
    }

    public function test_phase_pages_that_are_not_ready_say_so_and_ready_ones_redirect(): void
    {
        foreach (['keiyaku' => 'PDFで出力', 'jisshi' => '実施工程表', 'network' => 'クリティカルパス'] as $phase => $text) {
            $this->withSession(['login_user_id' => 1])
                ->get(route('genba3d.phase', ['project' => 'r08-14', 'phase' => $phase]))
                ->assertOk()
                ->assertSee('準備中')
                ->assertSee($text)
                ->assertSee('R08-14豊浜')
                ->assertSee('令和8年度');
        }
        $this->withSession(['login_user_id' => 1])->get(route('genba3d.phase', ['project' => 'r08-14', 'phase' => 'keikaku']))
            ->assertRedirect(route('genba3d.schedule', ['site' => 'toyohama-kyukeisha']));
        $this->withSession(['login_user_id' => 1])->get(route('genba3d.phase', ['project' => 'r08-14', 'phase' => 'henko']))
            ->assertRedirect(route('genba3d.show', ['site' => 'toyohama-kyukeisha']));
        $this->withSession(['login_user_id' => 1])->get(route('genba3d.phase', ['project' => 'r08-14', 'phase' => 'zzz']))->assertNotFound();
        $this->withSession(['login_user_id' => 1])->get(route('genba3d.phase', ['project' => 'r08-02', 'phase' => 'keiyaku']))->assertNotFound();
    }

    public function test_year_is_read_from_the_project_key_or_name(): void
    {
        $this->assertSame(7, \App\Support\Genba3dCatalog::year('r07-20'));
        $this->assertSame(8, \App\Support\Genba3dCatalog::year('kikonai'));
        $this->assertNull(\App\Support\Genba3dCatalog::year('other', 'その他'));
        $groups = \App\Support\Genba3dCatalog::byYear(\App\Support\Genba3dCatalog::activeProjects());
        $this->assertSame(['r07', 'r08'], array_keys($groups));
        $this->assertSame(['r07-20'], array_keys($groups['r07']['projects']));
    }
}
