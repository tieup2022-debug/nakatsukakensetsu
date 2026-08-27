<?php

namespace Tests\Feature;

use App\Services\BoardImageAnalysisService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BoardImageAnalysisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.openai.api_key' => 'test-api-key',
            'services.openai.base_url' => 'https://api.openai.test/v1',
            'services.openai.vision_model' => 'test-vision-model',
            'services.board_image_analysis.enabled' => true,
            'services.board_image_analysis.max_dimension' => 800,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        (require database_path('migrations/2026_08_26_000001_create_board_tables.php'))->up();
        (require database_path('migrations/2026_08_26_000002_add_legacy_fields_to_board_tables.php'))->up();
        (require database_path('migrations/2026_08_27_000001_add_ai_analysis_to_board_attachments.php'))->up();

        Storage::fake('local');
    }

    public function test_image_analysis_saves_ocr_description_and_search_terms(): void
    {
        $attachmentId = $this->insertImageAttachment();
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response($this->responsePayload(), 200),
        ]);

        $this->assertTrue(app(BoardImageAnalysisService::class)->analyzeById($attachmentId));

        $attachment = DB::table('t_board_attachments')->where('id', $attachmentId)->first();
        $this->assertSame('completed', $attachment->ai_analysis_status);
        $this->assertSame('赤い油圧ショベルが道路工事をしている。', $attachment->ai_description);
        $this->assertSame('工事看板 R08魚礁 8月27日', $attachment->ai_ocr_text);
        $this->assertStringContainsString('ユンボ', $attachment->ai_search_text);
        $this->assertNotNull($attachment->ai_analyzed_at);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.openai.test/v1/responses'
                && $payload['model'] === 'test-vision-model'
                && $payload['store'] === false
                && $payload['reasoning']['effort'] === 'low'
                && $payload['input'][0]['content'][1]['type'] === 'input_image'
                && str_starts_with($payload['input'][0]['content'][1]['image_url'], 'data:image/jpeg;base64,')
                && $payload['text']['format']['type'] === 'json_schema'
                && $payload['max_output_tokens'] === 3000;
        });
    }

    public function test_analysis_command_processes_new_images_but_not_existing_backfill_by_default(): void
    {
        $newAttachmentId = $this->insertImageAttachment('pending', 'new.jpg');
        $existingAttachmentId = $this->insertImageAttachment('unprocessed', 'legacy.jpg');
        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response($this->responsePayload(), 200),
        ]);

        $this->artisan('board:analyze-images', ['--limit' => 10])->assertSuccessful();

        $this->assertSame('completed', DB::table('t_board_attachments')->where('id', $newAttachmentId)->value('ai_analysis_status'));
        $this->assertSame('unprocessed', DB::table('t_board_attachments')->where('id', $existingAttachmentId)->value('ai_analysis_status'));
        Http::assertSentCount(1);
    }

    private function insertImageAttachment(string $status = 'pending', string $name = 'progress.jpg'): int
    {
        $image = UploadedFile::fake()->image($name, 1200, 900);
        $path = 'board/test/'.$name;
        Storage::disk('local')->put($path, $image->getContent());

        return (int) DB::table('t_board_attachments')->insertGetId([
            'thread_id' => 1,
            'reply_id' => null,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $name,
            'mime_type' => 'image/jpeg',
            'size' => $image->getSize(),
            'ai_analysis_status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function responsePayload(): array
    {
        return [
            'id' => 'resp_test',
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'description' => '赤い油圧ショベルが道路工事をしている。',
                        'ocr_text' => '工事看板 R08魚礁 8月27日',
                        'keywords' => ['赤い重機', '道路工事', '工事看板'],
                        'search_terms' => ['油圧ショベル', 'バックホウ', 'ユンボ', '魚礁'],
                    ], JSON_UNESCAPED_UNICODE),
                ]],
            ]],
        ];
    }
}
