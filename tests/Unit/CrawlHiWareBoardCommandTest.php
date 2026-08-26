<?php

namespace Tests\Unit;

use App\Console\Commands\CrawlHiWareBoardCommand;
use App\Services\HiWareBoardCrawler;
use App\Services\HiWareBoardParser;
use PHPUnit\Framework\TestCase;

class CrawlHiWareBoardCommandTest extends TestCase
{
    private string $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->output = sys_get_temp_dir().'/hiware-attachment-'.bin2hex(random_bytes(4));
        mkdir($this->output.'/attachments/000000000065203', 0775, true);
        file_put_contents(
            $this->output.'/attachments/000000000065203/001-photo.jpg',
            base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2Q==', true),
        );
    }

    protected function tearDown(): void
    {
        $file = $this->output.'/attachments/000000000065203/001-photo.jpg';
        if (is_file($file)) {
            unlink($file);
        }
        @rmdir($this->output.'/attachments/000000000065203');
        @rmdir($this->output.'/attachments');
        @rmdir($this->output);
        parent::tearDown();
    }

    public function test_download_metadata_is_written_back_to_the_post_record(): void
    {
        $post = [
            'legacy_id' => '000000000065203',
            'attachments' => [[
                'index' => 1,
                'source_url' => 'http://example.test/photo.jpg',
                'original_name' => 'photo.jpg',
            ]],
        ];
        $command = new class extends CrawlHiWareBoardCommand
        {
            /** @param array<string, mixed> $post */
            public function finalize(HiWareBoardCrawler $crawler, string $output, array &$post): void
            {
                $this->downloadPostAttachments($crawler, $output, $post);
            }
        };

        $command->finalize(new HiWareBoardCrawler(new HiWareBoardParser, 0), $this->output, $post);

        $this->assertSame(
            'attachments/000000000065203/001-photo.jpg',
            $post['attachments'][0]['path'],
        );
        $this->assertNotEmpty($post['attachments'][0]['mime_type']);
        $this->assertGreaterThan(0, $post['attachments'][0]['size']);
    }
}
