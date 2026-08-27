<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BoardTest extends TestCase
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

        $migration = require database_path('migrations/2026_08_26_000001_create_board_tables.php');
        $migration->up();
        $legacyMigration = require database_path('migrations/2026_08_26_000002_add_legacy_fields_to_board_tables.php');
        $legacyMigration->up();
        $imageAnalysisMigration = require database_path('migrations/2026_08_27_000001_add_ai_analysis_to_board_attachments.php');
        $imageAnalysisMigration->up();

        DB::table('m_user')->insert([
            [
                'id' => 1,
                'user_name' => '管理者',
                'permission' => 1,
                'deleted_at' => null,
            ],
            [
                'id' => 2,
                'user_name' => '投稿者',
                'permission' => 3,
                'deleted_at' => null,
            ],
            [
                'id' => 3,
                'user_name' => '一般ユーザー',
                'permission' => 3,
                'deleted_at' => null,
            ],
        ]);
    }

    public function test_board_requires_login(): void
    {
        $this->get(route('board.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_create_search_and_view_a_thread_with_an_attachment(): void
    {
        Storage::fake('local');

        $response = $this->withSession(['login_user_id' => 2])
            ->post(route('board.store'), [
                'title' => '豊浜急傾斜 工事日報',
                'body' => "本日の作業内容です。\nhttps://example.com/report\n<script>alert('xss')</script>",
                'attachments' => [UploadedFile::fake()->createWithContent('daily.txt', 'daily report')],
            ]);

        $threadId = (int) DB::table('t_board_threads')->value('id');
        $response->assertRedirect(route('board.show', ['thread' => $threadId]));
        $this->assertDatabaseHas('t_board_threads', [
            'id' => $threadId,
            'author_user_id' => 2,
            'author_name' => '投稿者',
            'title' => '豊浜急傾斜 工事日報',
        ]);

        $attachment = DB::table('t_board_attachments')->first();
        $this->assertNotNull($attachment);
        Storage::disk('local')->assertExists($attachment->path);

        $this->withSession(['login_user_id' => 3])
            ->get(route('board.index', ['q' => '豊浜']))
            ->assertOk()
            ->assertSee('豊浜急傾斜 工事日報');

        $this->withSession(['login_user_id' => 3])
            ->get(route('board.show', ['thread' => $threadId]))
            ->assertOk()
            ->assertSee('https://example.com/report')
            ->assertDontSee("<script>alert('xss')</script>", false)
            ->assertSee('daily.txt');

        $this->assertSame(1, (int) DB::table('t_board_threads')->where('id', $threadId)->value('view_count'));
    }

    public function test_image_attachment_opens_as_an_inline_lightbox_instead_of_a_download(): void
    {
        Storage::fake('local');

        $this->withSession(['login_user_id' => 2])
            ->post(route('board.store'), [
                'title' => '現場写真',
                'body' => '作業状況です。',
                'attachments' => [UploadedFile::fake()->image('progress.jpg', 800, 600)],
            ]);

        $threadId = (int) DB::table('t_board_threads')->value('id');
        $attachment = DB::table('t_board_attachments')->first();

        $this->withSession(['login_user_id' => 3])
            ->get(route('board.show', ['thread' => $threadId]))
            ->assertOk()
            ->assertSee('data-board-image-preview', false)
            ->assertSee('data-board-lightbox', false)
            ->assertSee(route('board.attachments.show', [
                'attachment' => $attachment->id,
                'inline' => 1,
            ]), false);

        $this->withSession(['login_user_id' => 3])
            ->get(route('board.attachments.show', [
                'attachment' => $attachment->id,
                'inline' => 1,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertHeader('content-disposition', 'inline');
    }

    public function test_incremental_search_returns_only_the_filtered_list_partial(): void
    {
        $matchingThreadId = $this->insertThread(authorUserId: 2);
        DB::table('t_board_threads')->where('id', $matchingThreadId)->update([
            'title' => '豊浜 工事日報',
        ]);
        DB::table('t_board_threads')->insert([
            'title' => '社内連絡',
            'body' => '別の投稿です。',
            'author_user_id' => 3,
            'author_name' => '一般ユーザー',
            'view_count' => 0,
            'last_activity_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['login_user_id' => 3])
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('board.index', ['q' => '豊浜']))
            ->assertOk()
            ->assertSee('「豊浜」の検索結果：1件')
            ->assertSee('豊浜 工事日報')
            ->assertDontSee('社内連絡')
            ->assertDontSee('board-search-form');
    }

    public function test_search_finds_a_thread_by_analyzed_image_content(): void
    {
        $threadId = $this->insertThread(authorUserId: 2);
        DB::table('t_board_attachments')->insert([
            'thread_id' => $threadId,
            'reply_id' => null,
            'disk' => 'local',
            'path' => 'board/test/red-excavator.jpg',
            'original_name' => 'IMG_0001.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'ai_description' => '赤い重機が魚礁の工事現場で作業している。',
            'ai_ocr_text' => 'R08魚礁',
            'ai_keywords' => '["赤い重機","魚礁"]',
            'ai_search_text' => '赤い重機 魚礁 油圧ショベル バックホウ ユンボ',
            'ai_analysis_status' => 'completed',
            'ai_analyzed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['login_user_id' => 3])
            ->get(route('board.index', ['q' => '赤い重機']))
            ->assertOk()
            ->assertSee('テスト投稿');

        $this->withSession(['login_user_id' => 3])
            ->get(route('board.index', ['q' => 'ユンボ']))
            ->assertOk()
            ->assertSee('テスト投稿');
    }

    public function test_user_can_reply_and_toggle_like_once_per_account(): void
    {
        $threadId = $this->insertThread(authorUserId: 2);

        $this->withSession(['login_user_id' => 3])
            ->post(route('board.replies.store', ['thread' => $threadId]), [
                'body' => '確認しました。',
            ])
            ->assertRedirect(route('board.show', ['thread' => $threadId]).'#replies');

        $this->assertDatabaseHas('t_board_replies', [
            'thread_id' => $threadId,
            'author_user_id' => 3,
            'author_name' => '一般ユーザー',
            'body' => '確認しました。',
        ]);

        $this->withSession(['login_user_id' => 3])
            ->post(route('board.like', ['thread' => $threadId]))
            ->assertRedirect(route('board.show', ['thread' => $threadId]));
        $this->assertSame(1, DB::table('t_board_likes')->count());

        $this->withSession(['login_user_id' => 3])
            ->post(route('board.like', ['thread' => $threadId]));
        $this->assertSame(0, DB::table('t_board_likes')->count());
    }

    public function test_only_author_or_admin_can_delete_a_thread(): void
    {
        $threadId = $this->insertThread(authorUserId: 2);

        $this->withSession(['login_user_id' => 3])
            ->delete(route('board.destroy', ['thread' => $threadId]))
            ->assertForbidden();
        $this->assertDatabaseHas('t_board_threads', ['id' => $threadId]);

        $this->withSession(['login_user_id' => 1])
            ->delete(route('board.destroy', ['thread' => $threadId]))
            ->assertRedirect(route('board.index'));
        $this->assertDatabaseMissing('t_board_threads', ['id' => $threadId]);
    }

    public function test_attachment_count_and_file_type_are_validated(): void
    {
        Storage::fake('local');
        $tooMany = [];
        for ($i = 0; $i < 6; $i++) {
            $tooMany[] = UploadedFile::fake()->createWithContent('file-'.$i.'.txt', 'test');
        }

        $this->withSession(['login_user_id' => 2])
            ->from(route('board.create'))
            ->post(route('board.store'), [
                'title' => '添付数テスト',
                'body' => '本文',
                'attachments' => $tooMany,
            ])
            ->assertSessionHasErrors('attachments');

        $this->withSession(['login_user_id' => 2])
            ->from(route('board.create'))
            ->post(route('board.store'), [
                'title' => '添付形式テスト',
                'body' => '本文',
                'attachments' => [UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;')],
            ])
            ->assertSessionHasErrors('attachments.0');

        $this->assertSame(0, DB::table('t_board_threads')->count());
    }

    private function insertThread(int $authorUserId): int
    {
        return DB::table('t_board_threads')->insertGetId([
            'title' => 'テスト投稿',
            'body' => 'テスト本文',
            'author_user_id' => $authorUserId,
            'author_name' => '投稿者',
            'author_email' => null,
            'view_count' => 0,
            'last_activity_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
