<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Genba3dFeedbackTest extends TestCase
{
    private const SITE = 'omonai-koren';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('m_user', function (Blueprint $table): void {
            $table->id();
            $table->string('user_name');
            $table->unsignedTinyInteger('permission')->default(3);
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('t_in_app_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('body');
            $table->string('type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        (require database_path('migrations/2026_10_12_000001_create_genba3d_feedback_tables.php'))->up();

        DB::table('m_user')->insert([
            ['id' => 1, 'user_name' => '管理者', 'permission' => 1, 'deleted_at' => null],
            ['id' => 2, 'user_name' => '現場太郎', 'permission' => 3, 'deleted_at' => null],
            ['id' => 3, 'user_name' => '現場花子', 'permission' => 3, 'deleted_at' => null],
        ]);
    }

    public function test_guest_cannot_read_or_write(): void
    {
        $this->get(route('genba3d.koe.index'))->assertRedirect(route('login'));
        $this->get(route('genba3d.koe.list'))->assertRedirect(route('login'));
        $this->postJson(route('genba3d.koe.store', ['site' => self::SITE]), ['kind' => 'request', 'body' => 'x', 'tab' => 'model'])
            ->assertRedirect(route('login'));
        $this->assertSame(0, DB::table('t_genba3d_feedback')->count());
    }

    public function test_site_page_has_the_feedback_panel_and_pin_button_on_the_model_tab_only(): void
    {
        $this->withSession(['login_user_id' => 2])->get(route('genba3d.show', ['site' => self::SITE]))
            ->assertOk()
            ->assertSee('📌 現場の声', false)
            ->assertSee('3Dにピンを立てる')
            ->assertSee('data-store-url="'.route('genba3d.koe.store', ['site' => self::SITE]).'"', false)
            ->assertSee('js/genba3d-koe.js', false);

        $this->withSession(['login_user_id' => 2])->get(route('genba3d.schedule', ['site' => self::SITE]))
            ->assertOk()
            ->assertSee('📌 現場の声', false)
            ->assertSee('data-tab="schedule"', false)
            ->assertDontSee('3Dにピンを立てる');
    }

    public function test_worker_writes_with_a_pin_and_the_masters_are_notified(): void
    {
        $this->withSession(['login_user_id' => 2])
            ->postJson(route('genba3d.koe.store', ['site' => self::SITE]), [
                'kind' => 'info',
                'body' => "  G2の当て板、実際は2枚ある  ",
                'tab' => 'model',
                'pin' => ['p' => [1.23456, 2, -3], 'cam' => [10, 11, 12], 'tgt' => [0, 0, 0], 'part' => '当て板補修（G2）'],
            ])
            ->assertCreated();

        $row = DB::table('t_genba3d_feedback')->first();
        $this->assertSame(self::SITE, $row->site);
        $this->assertSame('G2の当て板、実際は2枚ある', $row->body);
        $this->assertSame('open', $row->status);
        $this->assertSame('現場太郎', $row->author_name);
        $this->assertSame(['p' => [1.235, 2, -3], 'cam' => [10, 11, 12], 'tgt' => [0, 0, 0], 'part' => '当て板補修（G2）'], json_decode($row->pin, true));

        $this->assertSame([1], DB::table('t_in_app_notifications')->pluck('user_id')->map(fn ($v) => (int) $v)->all());

        // 他の人にも見える（現場の情報の共有）
        $items = $this->withSession(['login_user_id' => 3])
            ->getJson(route('genba3d.koe.list', ['site' => self::SITE]))
            ->assertOk()
            ->assertJsonPath('isMaster', false)
            ->json('items');
        $this->assertCount(1, $items);
        $this->assertSame('現場の情報', $items[0]['kindLabel']);
        $this->assertSame('3Dモデル', $items[0]['tabLabel']);
        $this->assertSame('幸連橋', $items[0]['siteName']);
        $this->assertFalse($items[0]['canDelete']);
    }

    public function test_bad_input_is_rejected(): void
    {
        $url = route('genba3d.koe.store', ['site' => self::SITE]);
        $bad = [
            ['kind' => 'request', 'body' => '', 'tab' => 'model'],
            ['kind' => 'request', 'body' => '   ', 'tab' => 'model'],
            ['kind' => 'spam', 'body' => 'x', 'tab' => 'model'],
            ['kind' => 'request', 'body' => str_repeat('あ', 1001), 'tab' => 'model'],
            ['kind' => 'request', 'body' => 'x', 'tab' => 'no-such-tab'],
            ['kind' => 'request', 'body' => 'x', 'tab' => '../etc'],
            ['kind' => 'request', 'body' => 'x', 'tab' => 'model', 'pin' => ['p' => [1, 2]]],
            ['kind' => 'request', 'body' => 'x', 'tab' => 'model', 'pin' => ['p' => [1, 2, 'a']]],
        ];
        foreach ($bad as $payload) {
            $this->withSession(['login_user_id' => 2])->postJson($url, $payload)->assertStatus(422);
        }
        $this->withSession(['login_user_id' => 2])
            ->postJson(route('genba3d.koe.store', ['site' => 'no-such-site']), ['kind' => 'request', 'body' => 'x', 'tab' => 'model'])
            ->assertNotFound();

        $this->assertSame(0, DB::table('t_genba3d_feedback')->count());
    }

    public function test_extra_page_tab_is_accepted_and_labelled(): void
    {
        $this->withSession(['login_user_id' => 2])
            ->postJson(route('genba3d.koe.store', ['site' => 'asahi-higashi-gogan']), ['kind' => 'request', 'body' => '足場の図を大きく', 'tab' => 'ashiba'])
            ->assertCreated();

        $this->withSession(['login_user_id' => 2])
            ->getJson(route('genba3d.koe.list', ['site' => 'asahi-higashi-gogan']))
            ->assertJsonPath('items.0.tabLabel', '重機用足場');
    }

    public function test_master_replies_and_changes_status_and_the_author_is_notified(): void
    {
        $id = $this->post_as(2, 'request', 'ここの寸法も出してほしい');
        DB::table('t_in_app_notifications')->delete();

        $this->withSession(['login_user_id' => 1])
            ->postJson(route('genba3d.koe.reply', ['id' => $id]), ['body' => '次の更新で入れます'])
            ->assertCreated();
        $this->withSession(['login_user_id' => 1])
            ->patchJson(route('genba3d.koe.status', ['id' => $id]), ['status' => 'doing'])
            ->assertOk();

        $this->assertSame('doing', DB::table('t_genba3d_feedback')->where('id', $id)->value('status'));
        $this->assertSame([2, 2], DB::table('t_in_app_notifications')->orderBy('id')->pluck('user_id')->map(fn ($v) => (int) $v)->all());

        $item = $this->withSession(['login_user_id' => 2])
            ->getJson(route('genba3d.koe.list', ['site' => self::SITE]))
            ->json('items.0');
        $this->assertSame('対応中', $item['statusLabel']);
        $this->assertSame([['author' => '管理者', 'body' => '次の更新で入れます', 'isMaster' => true]],
            array_map(fn ($r) => ['author' => $r['author'], 'body' => $r['body'], 'isMaster' => $r['isMaster']], $item['replies']));
    }

    public function test_only_masters_change_status(): void
    {
        $id = $this->post_as(2, 'request', 'お願い');

        $this->withSession(['login_user_id' => 2])
            ->patchJson(route('genba3d.koe.status', ['id' => $id]), ['status' => 'done'])
            ->assertForbidden();
        $this->withSession(['login_user_id' => 1])
            ->patchJson(route('genba3d.koe.status', ['id' => $id]), ['status' => 'nope'])
            ->assertStatus(422);

        $this->assertSame('open', DB::table('t_genba3d_feedback')->where('id', $id)->value('status'));
    }

    public function test_worker_reply_notifies_author_and_masters(): void
    {
        $id = $this->post_as(2, 'info', '岩が出ている');
        DB::table('t_in_app_notifications')->delete();

        $this->withSession(['login_user_id' => 3])
            ->postJson(route('genba3d.koe.reply', ['id' => $id]), ['body' => 'こっちにもある'])
            ->assertCreated();

        $this->assertEqualsCanonicalizing([1, 2], DB::table('t_in_app_notifications')->pluck('user_id')->map(fn ($v) => (int) $v)->all());
    }

    public function test_author_can_delete_until_someone_replies_and_master_always_can(): void
    {
        $mine = $this->post_as(2, 'other', '消すつもり');
        $this->withSession(['login_user_id' => 3])->deleteJson(route('genba3d.koe.destroy', ['id' => $mine]))->assertForbidden();
        $this->withSession(['login_user_id' => 2])->deleteJson(route('genba3d.koe.destroy', ['id' => $mine]))->assertOk();
        $this->assertNull(DB::table('t_genba3d_feedback')->where('id', $mine)->first());

        $answered = $this->post_as(2, 'other', '返信がついた');
        $this->withSession(['login_user_id' => 1])->postJson(route('genba3d.koe.reply', ['id' => $answered]), ['body' => '了解'])->assertCreated();
        $this->withSession(['login_user_id' => 2])->deleteJson(route('genba3d.koe.destroy', ['id' => $answered]))->assertForbidden();
        $this->withSession(['login_user_id' => 1])->deleteJson(route('genba3d.koe.destroy', ['id' => $answered]))->assertOk();
        $this->assertSame(0, DB::table('t_genba3d_feedback_replies')->count());
    }

    public function test_overview_lists_every_site_and_filters_by_status(): void
    {
        $a = $this->post_as(2, 'request', 'A');
        $this->post_as(2, 'info', 'B', 'asahi-funaageba');
        $this->withSession(['login_user_id' => 1])->patchJson(route('genba3d.koe.status', ['id' => $a]), ['status' => 'done']);

        $this->withSession(['login_user_id' => 2])->get(route('genba3d.koe.index'))
            ->assertOk()
            ->assertSee('現場の声')
            ->assertSee('幸連橋')
            ->assertSee('data-list-url="'.route('genba3d.koe.list').'"', false);

        $this->assertCount(2, $this->withSession(['login_user_id' => 2])->getJson(route('genba3d.koe.list'))->json('items'));
        $done = $this->withSession(['login_user_id' => 2])->getJson(route('genba3d.koe.list', ['status' => 'done']))->json('items');
        $this->assertSame(['A'], array_column($done, 'body'));

        // 一覧の入口に、未対応・対応中の件数が出る
        $this->withSession(['login_user_id' => 2])->get(route('genba3d.index'))
            ->assertSee('href="'.route('genba3d.koe.index').'"', false);
    }

    private function post_as(int $user, string $kind, string $body, string $site = self::SITE): int
    {
        return (int) $this->withSession(['login_user_id' => $user])
            ->postJson(route('genba3d.koe.store', ['site' => $site]), ['kind' => $kind, 'body' => $body, 'tab' => 'model'])
            ->assertCreated()
            ->json('id');
    }
}
