<?php

namespace Tests\Feature;

use App\Services\HiWareBoardImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HiWareBoardImportTest extends TestCase
{
    private string $exportRoot;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Storage::fake('local');

        Schema::create('m_user', function (Blueprint $table): void {
            $table->id();
            $table->string('user_name');
            $table->timestamp('deleted_at')->nullable();
        });
        (require database_path('migrations/2026_08_26_000001_create_board_tables.php'))->up();
        (require database_path('migrations/2026_08_26_000002_add_legacy_fields_to_board_tables.php'))->up();

        DB::table('m_user')->insert(['id' => 7, 'user_name' => '住吉 秀美', 'deleted_at' => null]);
        $this->exportRoot = storage_path('framework/testing/hiware-import-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->exportRoot.'/attachments/000000000000014');
        file_put_contents($this->exportRoot.'/attachments/000000000000014/001-test.pdf', 'PDF fixture');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->exportRoot);
        parent::tearDown();
    }

    public function test_it_imports_and_updates_legacy_thread_without_duplicates(): void
    {
        $record = [
            'legacy_id' => '000000000000014',
            'title' => '中塚建設掲示板について',
            'body' => '旧本文',
            'author_name' => '住吉 秀美',
            'author_email' => 'sumiyoshi@example.test',
            'created_at' => '2001-04-19 17:54:39',
            'last_activity_at' => '2001-04-19 18:35:00',
            'view_count' => 18,
            'legacy_like_count' => 3,
            'attachments' => [[
                'index' => 1,
                'path' => 'attachments/000000000000014/001-test.pdf',
                'original_name' => 'test.pdf',
                'mime_type' => 'application/pdf',
                'size' => 11,
            ]],
            'replies' => [[
                'legacy_id' => '000000000000018',
                'body' => '旧返信',
                'author_name' => '木村 修',
                'author_email' => 'osamu@example.test',
                'created_at' => '2001-04-19 18:35:00',
                'legacy_like_count' => 2,
                'attachments' => [],
            ]],
        ];

        $service = app(HiWareBoardImportService::class);
        $first = $service->import($record, $this->exportRoot);
        $record['view_count'] = 25;
        $second = $service->import($record, $this->exportRoot);

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame(1, DB::table('t_board_threads')->count());
        $this->assertSame(1, DB::table('t_board_replies')->count());
        $this->assertSame(1, DB::table('t_board_attachments')->count());
        $this->assertDatabaseHas('t_board_threads', [
            'legacy_id' => '000000000000014',
            'author_user_id' => 7,
            'view_count' => 25,
            'legacy_like_count' => 3,
        ]);
        $this->assertDatabaseHas('t_board_replies', [
            'legacy_id' => '000000000000018',
            'author_user_id' => null,
            'legacy_like_count' => 2,
        ]);
        $attachment = DB::table('t_board_attachments')->first();
        Storage::disk('local')->assertExists($attachment->path);
    }
}
