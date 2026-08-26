<?php

namespace App\Services;

use App\Exceptions\HiWareAuthenticationException;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class HiWareBoardCrawler
{
    public const BASE_URL = 'http://intra.e-nakatsuka.com';

    public const FIRST_LIST_PATH = '/cgi-bin/Board/wb_Grouplist.exe?board/gyomu.ini+g+THREAD+000000000000000+ALL';

    private Client $client;

    public function __construct(
        private readonly HiWareBoardParser $parser,
        private int $delayMilliseconds = 500,
    ) {
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'cookies' => new CookieJar,
            'allow_redirects' => true,
            'connect_timeout' => 15,
            'timeout' => 60,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => 'Nakatsuka-Legacy-Board-Migration/1.0',
            ],
        ]);
    }

    public function login(string $userId, string $password): void
    {
        $this->request('POST', '/cgi-bin/cug.exe?cug.ini', [
            'form_params' => [
                'UserId' => $userId,
                'PassWord' => $password,
                'APP' => '/cgi-bin/CUG.exe?cug.ini',
            ],
        ], false);

        $response = $this->request('GET', self::FIRST_LIST_PATH, [], false);
        $this->assertAuthenticated($this->parser->decode((string) $response->getBody()));
    }

    /**
     * @return array{page:int,total_pages:int,threads:array<int, array<string, mixed>>}
     */
    public function listPage(int $page, ?string $diagnosticPath = null): array
    {
        $path = $page <= 1
            ? self::FIRST_LIST_PATH
            : '/cgi-bin/board/wb_Grouplist.exe?board/gyomu.ini+g+THREAD+0+ALL+'.(($page - 1) * 50 + 1);
        $response = $this->request('GET', $path);
        $raw = (string) $response->getBody();
        $this->assertAuthenticated($this->parser->decode($raw));

        $parsed = $this->parser->parseListPage($raw, $page);
        if ($parsed['threads'] === [] && $diagnosticPath !== null) {
            $directory = dirname($diagnosticPath);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException('診断ファイル保存先を作成できません: '.$directory);
            }
            if (file_put_contents($diagnosticPath, $this->parser->decode($raw)) === false) {
                throw new RuntimeException('診断ファイルを保存できません: '.$diagnosticPath);
            }
        }

        return $parsed;
    }

    /** @return array<string, mixed> */
    public function thread(string $url): array
    {
        $response = $this->request('GET', $url);
        $raw = (string) $response->getBody();
        $this->assertAuthenticated($this->parser->decode($raw));

        return $this->parser->parseThreadDetail($raw, $url);
    }

    public function likeCount(string $legacyPostId): ?int
    {
        $response = $this->request('POST', '/module/board_like.php', [
            'form_params' => [
                'DATATYPE' => 'CNT',
                'KEIJINO' => $legacyPostId,
            ],
        ]);
        $text = trim($this->parser->decode((string) $response->getBody()));
        $this->assertAuthenticated($text);

        return preg_match('/^\d+$/', $text) ? (int) $text : null;
    }

    /**
     * @return array{mime_type:?string,size:int}
     */
    public function download(string $url, string $destination): array
    {
        $directory = dirname($destination);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('添付ファイル保存先を作成できません: '.$directory);
        }
        $partial = $destination.'.part';
        $response = $this->request('GET', $url, ['sink' => $partial]);
        $contentType = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0] ?? ''));
        if (str_contains($contentType, 'text/html')) {
            $body = is_file($partial) ? (string) file_get_contents($partial) : '';
            @unlink($partial);
            $this->assertAuthenticated($this->parser->decode($body));
            throw new RuntimeException('添付URLからHTMLが返されました: '.$url);
        }
        if (! is_file($partial) || filesize($partial) === 0) {
            @unlink($partial);
            throw new RuntimeException('添付ファイルを取得できませんでした: '.$url);
        }
        if (! rename($partial, $destination)) {
            @unlink($partial);
            throw new RuntimeException('添付ファイルを確定できませんでした: '.$destination);
        }

        return [
            'mime_type' => $contentType !== '' ? $contentType : null,
            'size' => (int) filesize($destination),
        ];
    }

    private function request(string $method, string $url, array $options = [], bool $delay = true): ResponseInterface
    {
        $lastError = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            if ($delay && $this->delayMilliseconds > 0) {
                usleep($this->delayMilliseconds * 1000);
            }
            try {
                $response = $this->client->request($method, $url, $options);
                if ($response->getStatusCode() < 500 && $response->getStatusCode() !== 429) {
                    if ($response->getStatusCode() >= 400) {
                        throw new RuntimeException('HTTP '.$response->getStatusCode().': '.$url);
                    }

                    return $response;
                }
                $lastError = new RuntimeException('HTTP '.$response->getStatusCode().': '.$url);
            } catch (GuzzleException|RuntimeException $e) {
                $lastError = $e;
            }
            usleep($attempt * 1000000);
        }

        throw new RuntimeException('旧掲示板へのアクセスに3回失敗しました: '.$url, 0, $lastError);
    }

    private function assertAuthenticated(string $htmlOrText): void
    {
        if (str_contains($htmlOrText, 'グループウェアHi-WAREログイン')
            || str_contains($htmlOrText, 'セッションがタイムアウトしました')
            || (str_contains($htmlOrText, 'NAME="UserId"') && str_contains($htmlOrText, 'NAME="PassWord"'))) {
            throw new HiWareAuthenticationException('旧掲示板のログインに失敗したか、セッションが切れました。');
        }
    }
}
