<?php

namespace Tests\Feature;

use Tests\TestCase;

class Genba3dTest extends TestCase
{
    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('genba3d.index'))->assertRedirect(route('login'));
        $this->get(route('genba3d.show', ['site' => 'asahi-funaageba']))->assertRedirect(route('login'));
        $this->get(route('genba3d.model', ['site' => 'asahi-funaageba']))->assertRedirect(route('login'));
    }

    public function test_index_opens_the_first_site(): void
    {
        $first = array_key_first(config('genba3d.sites'));

        $this->withSession(['login_user_id' => 1])
            ->get(route('genba3d.index'))
            ->assertRedirect(route('genba3d.show', ['site' => $first]));
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

    public function test_unknown_site_is_not_found(): void
    {
        $this->withSession(['login_user_id' => 1])
            ->get(route('genba3d.show', ['site' => 'no-such-site']))
            ->assertNotFound();
        $this->withSession(['login_user_id' => 1])
            ->get(route('genba3d.model', ['site' => 'no-such-site']))
            ->assertNotFound();
    }

    public function test_menu_and_page_list_all_sites(): void
    {
        $sites = config('genba3d.sites');
        $current = array_key_first($sites);

        // レイアウトと同じメニュー部品を、PC用・スマホ用の両方の設定で描く。
        foreach ([false, true] as $dismiss) {
            $menu = $this->withSession(['login_user_id' => 1])
                ->view('layouts.partials.app-sidebar-nav', ['dismissOffcanvas' => $dismiss]);

            $menu->assertSee('現場3D');
            $menu->assertSeeInOrder(['現場3D', 'お問い合わせ']);
            foreach ($sites as $slug => $site) {
                $menu->assertSee($site['name']);
                $menu->assertSee(route('genba3d.show', ['site' => $slug]), false);
            }
        }

        $page = $this->view('genba3d.show', [
            'sites' => $sites,
            'currentSlug' => $current,
            'current' => $sites[$current],
        ]);
        foreach ($sites as $site) {
            $page->assertSee($site['name']);
        }
        $page->assertSee(route('genba3d.model', ['site' => $current]), false);
    }
}
