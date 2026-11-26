<?php

namespace Tests\Feature;

use App\Http\Controllers\Genba3dController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Genba3dTermTest extends TestCase
{
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
        (require database_path('migrations/2026_11_26_000001_create_genba3d_terms_table.php'))->up();

        DB::table('m_user')->insert([
            ['id' => 1, 'user_name' => '管理者', 'permission' => 1, 'deleted_at' => null],
            ['id' => 2, 'user_name' => '現場太郎', 'permission' => 3, 'deleted_at' => null],
        ]);
        Carbon::setTestNow('2026-11-26 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_master_saves_the_term_of_the_project_and_the_index_shows_it(): void
    {
        $this->withSession(['login_user_id' => 1])
            ->postJson(route('genba3d.term', ['site' => 'fukushima-tunnel']), ['start' => '2026-03-14', 'end' => '2026-12-18'])
            ->assertOk()
            ->assertJsonPath('term.start', '2026-03-14')
            ->assertJsonPath('term.end', '2026-12-18')
            ->assertJsonPath('term.source', 'saved')
            ->assertJsonPath('term.updatedBy', '管理者');

        $row = DB::table('t_genba3d_terms')->where('project', 'r08-01')->first();
        $this->assertSame('2026-12-18', substr((string) $row->end_date, 0, 10));

        // 工期は工事ごと: 同じ工事の別の現場から見ても同じ値
        $page = $this->withSession(['login_user_id' => 2])->get(route('genba3d.index'))->assertOk();
        $page->assertSee('工期の一覧')
            ->assertSee('2026/3/14')
            ->assertSee('2026/12/18')
            ->assertSee('あと22日');
    }

    public function test_anyone_logged_in_can_save_and_every_change_is_logged(): void
    {
        $url = route('genba3d.term', ['site' => 'omonai-koren']);
        $this->withSession(['login_user_id' => 2])->postJson($url, ['start' => '2026-01-05', 'end' => '2027-02-24'])
            ->assertOk()->assertJsonPath('changed', true)->assertJsonPath('term.updatedBy', '現場太郎');
        // 同じ値をもう一度送っても記録は増えない
        $this->withSession(['login_user_id' => 2])->postJson($url, ['start' => '2026-01-05', 'end' => '2027-02-24'])
            ->assertOk()->assertJsonPath('changed', false);
        $this->withSession(['login_user_id' => 1])->postJson($url, ['start' => '2026-01-05', 'end' => '2027-03-10'])
            ->assertOk()
            ->assertJsonCount(2, 'logs')
            ->assertJsonPath('logs.0.by', '管理者')
            ->assertJsonPath('logs.0.oldEnd', '2027-02-24')
            ->assertJsonPath('logs.0.newEnd', '2027-03-10')
            ->assertJsonPath('logs.1.by', '現場太郎')
            ->assertJsonPath('logs.1.oldEnd', null);

        $this->assertSame(2, DB::table('t_genba3d_term_logs')->where('project', 'r07-20')->count());
        // 工程表のページにも記録が渡る
        $this->withSession(['login_user_id' => 2])
            ->get(route('genba3d.schedule.page', ['site' => 'omonai-hatsukami']))
            ->assertSee('"canEdit":true', false)
            ->assertSee('"by":"管理者"', false);
        // 一覧に最後に変えた人が出る
        $this->withSession(['login_user_id' => 2])->get(route('genba3d.index'))->assertSee('更新：管理者');
    }

    public function test_bad_dates_and_guests_are_rejected(): void
    {
        $url = route('genba3d.term', ['site' => 'omonai-koren']);
        $this->withSession(['login_user_id' => 1])->postJson($url, ['start' => '2026-12-01', 'end' => '2026-01-01'])->assertStatus(422);
        $this->withSession(['login_user_id' => 1])->postJson($url, ['start' => '2026/01/01'])->assertStatus(422);
        $this->withSession(['login_user_id' => 1])->postJson(route('genba3d.term', ['site' => 'no-such-site']), [])->assertNotFound();
        $this->flushSession();
        $this->postJson($url, ['start' => '2026-01-01'])->assertRedirect(route('login'));
        $this->assertSame(0, DB::table('t_genba3d_terms')->count());
    }

    public function test_index_shows_design_document_values_until_a_term_is_saved(): void
    {
        $page = $this->withSession(['login_user_id' => 2])->get(route('genba3d.index'))->assertOk();
        // R08-14豊浜は設計図書の工期（2026/7/16〜2027/3/23）、大沢は未入力
        $html = (string) $page->getContent();
        $this->assertMatchesRegularExpression('/data-term="r08-14".*?設計図書の値.*?2026\/7\/16.*?2027\/3\/23/su', $html);
        $this->assertMatchesRegularExpression('/data-term="r08-16".*?未入力/su', $html);
    }

    public function test_schedule_page_gets_the_term_input_and_uses_the_saved_deadline(): void
    {
        $this->withSession(['login_user_id' => 1])
            ->postJson(route('genba3d.term', ['site' => 'fukushima-tunnel']), ['start' => '2026-04-01', 'end' => '2026-12-18'])
            ->assertOk();

        $gantt = $this->withSession(['login_user_id' => 2])
            ->get(route('genba3d.schedule.page', ['site' => 'fukushima-tunnel', 'part' => 'gantt']))
            ->assertOk();
        $html = (string) $gantt->getContent();
        $this->assertStringContainsString('window.GENBA3D_TERM=', $html);
        $this->assertStringContainsString('js/genba3d-term.js', $html);
        $this->assertStringContainsString('"canEdit":true', $html);
        $this->assertStringContainsString('DEADLINE="2026-12-18"', $html);
        $this->assertStringContainsString('工期末 令和8年12月18日', $html);
        $this->assertStringNotContainsString('工期末 令和8年12月4日', $html);
        $this->assertStringContainsString('DEF={start:"2026-04-01"', $html);

        // 施工手順の側には出さない
        $this->withSession(['login_user_id' => 1])
            ->get(route('genba3d.schedule.page', ['site' => 'fukushima-tunnel', 'part' => 'steps']))
            ->assertDontSee('GENBA3D_TERM', false);
    }

    public function test_apply_term_keeps_design_document_explanations(): void
    {
        $html = 'const DEADLINE="2027-01-04"; <span>工期末 令和9年1月4日（設計図書の工期）</span> 設計図書の工期（令和8年6月18日〜令和9年1月4日） 工期末（1/4）までの余裕 id="start" value="2026-06-18"';
        $out = Genba3dController::applyTerm($html, '2026-07-01', '2027-03-10');
        $this->assertStringContainsString('DEADLINE="2027-03-10"', $out);
        $this->assertStringContainsString('<span>工期末 令和9年3月10日</span>', $out);
        $this->assertStringContainsString('設計図書の工期（令和8年6月18日〜令和9年1月4日）', $out);
        $this->assertStringContainsString('（3/10）までの余裕', $out);
        $this->assertStringContainsString('id="start" value="2026-07-01"', $out);
    }
}
