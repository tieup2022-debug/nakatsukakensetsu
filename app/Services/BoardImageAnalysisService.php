<?php

namespace App\Services;

use GuzzleHttp\Handler\StreamHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BoardImageAnalysisService
{
    private const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public function available(): bool
    {
        return (bool) config('services.board_image_analysis.enabled', true)
            && trim((string) config('services.openai.api_key')) !== '';
    }

    public function isImageAttachment(object $attachment): bool
    {
        if (in_array(strtolower((string) ($attachment->mime_type ?? '')), self::IMAGE_MIME_TYPES, true)) {
            return true;
        }

        return in_array(strtolower(pathinfo((string) ($attachment->original_name ?? ''), PATHINFO_EXTENSION)), [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
        ], true);
    }

    public function analyzeById(int $attachmentId): bool
    {
        $attachment = DB::table('t_board_attachments')->where('id', $attachmentId)->first();
        if (! $attachment || ! $this->isImageAttachment($attachment)) {
            return false;
        }
        if (! $this->available()) {
            throw new RuntimeException('OPENAI_API_KEY が設定されていません。');
        }

        $claimed = DB::table('t_board_attachments')
            ->where('id', $attachmentId)
            ->whereIn('ai_analysis_status', ['pending', 'retry', 'unprocessed', 'processing', 'failed'])
            ->update([
                'ai_analysis_status' => 'processing',
                'ai_analysis_error' => null,
                'updated_at' => now(),
            ]);
        if ($claimed === 0) {
            return false;
        }

        try {
            $analysis = $this->requestAnalysis($attachment);
            $keywords = array_values(array_unique(array_filter(array_map(
                fn (mixed $keyword): string => trim((string) $keyword),
                array_merge($analysis['keywords'], $analysis['search_terms'])
            ))));
            $searchText = trim(implode("\n", array_filter([
                $analysis['description'],
                $analysis['ocr_text'],
                implode(' ', $keywords),
            ])));

            DB::table('t_board_attachments')->where('id', $attachmentId)->update([
                'ai_description' => $analysis['description'],
                'ai_ocr_text' => $analysis['ocr_text'],
                'ai_keywords' => json_encode($keywords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ai_search_text' => $searchText,
                'ai_analysis_status' => 'completed',
                'ai_analysis_error' => null,
                'ai_next_attempt_at' => null,
                'ai_analyzed_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (\Throwable $e) {
            $attempts = (int) ($attachment->ai_analysis_attempts ?? 0) + 1;
            $failed = $attempts >= 5;
            DB::table('t_board_attachments')->where('id', $attachmentId)->update([
                'ai_analysis_status' => $failed ? 'failed' : 'retry',
                'ai_analysis_attempts' => $attempts,
                'ai_analysis_error' => mb_substr($e->getMessage(), 0, 2000),
                'ai_next_attempt_at' => $failed
                    ? null
                    : now()->addMinutes(min(240, 5 * (2 ** max(0, $attempts - 1)))),
                'updated_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array{description:string,ocr_text:string,keywords:list<string>,search_terms:list<string>}
     */
    private function requestAnalysis(object $attachment): array
    {
        $response = Http::withToken((string) config('services.openai.api_key'))
            // The production PHP cURL extension lacks some modern TLS constants.
            // OpenSSL-backed streams provide the same HTTPS verification without
            // relying on those cURL constants.
            ->setHandler(new StreamHandler)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(max(30, (int) config('services.openai.timeout', 90)))
            ->retry(2, 1000)
            ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/responses', [
                'model' => (string) config('services.openai.vision_model', 'gpt-5.6-luna'),
                'store' => false,
                'reasoning' => [
                    'effort' => 'low',
                ],
                'input' => [[
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => implode("\n", [
                                '建設会社の社内掲示板に添付された画像を、日本語の画像検索用に解析してください。',
                                '画像内で読める文字は、工事名・日付・看板・機械名・数値を含め、見える範囲で正確に転記してください。',
                                '内容説明には、工種、重機・車両・資材、色、場所、天候、安全設備、作業状況など、目で確認できる具体的な特徴を含めてください。',
                                '検索語には一般的な言い換えも含めてください（例：バックホウ／油圧ショベル／ユンボ／重機）。',
                                '人物の氏名や不明な場所・日付は推測せず、写っていない情報は作らないでください。',
                            ]),
                        ],
                        [
                            'type' => 'input_image',
                            'image_url' => $this->imageDataUrl($attachment),
                            'detail' => 'high',
                        ],
                    ],
                ]],
                'text' => [
                    'verbosity' => 'low',
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'board_image_analysis',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'description' => ['type' => 'string'],
                                'ocr_text' => ['type' => 'string'],
                                'keywords' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                    'maxItems' => 30,
                                ],
                                'search_terms' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                    'maxItems' => 30,
                                ],
                            ],
                            'required' => ['description', 'ocr_text', 'keywords', 'search_terms'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'max_output_tokens' => 3000,
            ]);

        if (! $response->successful()) {
            $message = (string) $response->json('error.message', 'OpenAI画像解析に失敗しました。');
            throw new RuntimeException('OpenAI API: '.$message);
        }

        $payload = $response->json();
        $outputText = is_string($payload['output_text'] ?? null) ? $payload['output_text'] : '';
        if ($outputText === '') {
            foreach ((array) ($payload['output'] ?? []) as $item) {
                foreach ((array) ($item['content'] ?? []) as $content) {
                    if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                        $outputText .= $content['text'];
                    }
                }
            }
        }
        if ($outputText === '') {
            throw new RuntimeException('OpenAI APIから解析結果が返りませんでした。');
        }

        try {
            $decoded = json_decode($outputText, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('OpenAI APIの解析結果を読み取れませんでした。', previous: $e);
        }
        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI APIの解析結果が不正です。');
        }

        return [
            'description' => trim((string) ($decoded['description'] ?? '')),
            'ocr_text' => trim((string) ($decoded['ocr_text'] ?? '')),
            'keywords' => array_values((array) ($decoded['keywords'] ?? [])),
            'search_terms' => array_values((array) ($decoded['search_terms'] ?? [])),
        ];
    }

    private function imageDataUrl(object $attachment): string
    {
        $disk = Storage::disk((string) $attachment->disk);
        if (! $disk->exists((string) $attachment->path)) {
            throw new RuntimeException('解析対象の画像ファイルが見つかりません。');
        }

        $bytes = $disk->get((string) $attachment->path);
        if ($bytes === '') {
            throw new RuntimeException('解析対象の画像ファイルが空です。');
        }

        $prepared = $this->resizeAsJpeg($bytes);
        if ($prepared !== null) {
            return 'data:image/jpeg;base64,'.base64_encode($prepared);
        }

        $mime = strtolower((string) ($attachment->mime_type ?? 'image/jpeg'));
        if (! in_array($mime, self::IMAGE_MIME_TYPES, true)) {
            $mime = 'image/jpeg';
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function resizeAsJpeg(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }

        try {
            $width = imagesx($source);
            $height = imagesy($source);
            if ($width < 1 || $height < 1) {
                return null;
            }

            $maxDimension = max(512, (int) config('services.board_image_analysis.max_dimension', 1600));
            $scale = min(1, $maxDimension / max($width, $height));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            if ($target === false) {
                return null;
            }

            try {
                $white = imagecolorallocate($target, 255, 255, 255);
                imagefill($target, 0, 0, $white);
                imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
                ob_start();
                imagejpeg($target, null, 84);
                $jpeg = ob_get_clean();

                return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($source);
        }
    }
}
