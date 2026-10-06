<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Genba3dChatTest extends TestCase
{
    private const API = 'https://api.anthropic.test/v1/messages';

    private const SITE = 'asahi-higashi-gogan';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.anthropic.api_key' => 'test-key',
            'services.anthropic.base_url' => 'https://api.anthropic.test/v1',
            'genba3d.chat.enabled' => true,
            'genba3d.chat.model' => 'test-model',
            'genba3d.chat.max_tokens' => 1200,
            'genba3d.chat.daily_limit_per_user' => 30,
            'genba3d.chat.daily_limit_total' => 200,
        ]);
    }

    public function test_guest_cannot_ask(): void
    {
        Http::fake();

        $this->postJson(route('genba3d.ask', ['site' => self::SITE]), $this->question('高さは？'))
            ->assertRedirect(route('login'));

        Http::assertNothingSent();
    }

    public function test_every_site_has_a_knowledge_file_about_itself(): void
    {
        foreach (config('genba3d.sites') as $site) {
            $path = resource_path('genba3d/knowledge/'.$site['knowledge_file']);
            $this->assertFileExists($path);

            $text = (string) file_get_contents($path);
            $this->assertStringContainsString($site['name'], $text);
            $this->assertStringContainsString('## 施工手順', $text);
            // 料金の見込みを保つため、資料が膨らみすぎていないこと
            $this->assertLessThan(14000, mb_strlen($text));
        }
    }

    public function test_without_an_api_key_the_button_is_hidden_and_asking_is_refused(): void
    {
        config(['services.anthropic.api_key' => '']);
        Http::fake();

        $this->withSession(['login_user_id' => 1])
            ->get(route('genba3d.show', ['site' => self::SITE]))
            ->assertOk()
            ->assertDontSee('AIに質問')
            ->assertDontSee('genba3dChat', false);

        $this->withSession(['login_user_id' => 1])
            ->postJson(route('genba3d.ask', ['site' => self::SITE]), $this->question('高さは？'))
            ->assertStatus(503);

        Http::assertNothingSent();
    }

    public function test_switching_the_feature_off_hides_it_even_with_a_key(): void
    {
        config(['genba3d.chat.enabled' => false]);

        $this->withSession(['login_user_id' => 1])
            ->get(route('genba3d.show', ['site' => self::SITE]))
            ->assertOk()
            ->assertDontSee('AIに質問')
            ->assertDontSee('genba3dChat', false);
    }

    public function test_site_without_a_knowledge_file_has_no_button(): void
    {
        $sites = config('genba3d.sites');
        unset($sites[self::SITE]['knowledge_file']);
        config(['genba3d.sites' => $sites]);
        Http::fake();

        $this->withSession(['login_user_id' => 1])
            ->get(route('genba3d.schedule', ['site' => self::SITE]))
            ->assertOk()
            ->assertDontSee('AIに質問')
            ->assertDontSee('genba3dChat', false);

        $this->withSession(['login_user_id' => 1])
            ->postJson(route('genba3d.ask', ['site' => self::SITE]), $this->question('高さは？'))
            ->assertStatus(503);

        Http::assertNothingSent();
    }

    public function test_page_shows_the_ask_button_on_both_tabs(): void
    {
        foreach (['genba3d.show', 'genba3d.schedule'] as $name) {
            $this->withSession(['login_user_id' => 1])
                ->get(route($name, ['site' => self::SITE]))
                ->assertOk()
                ->assertSee('AIに質問')
                ->assertSee('data-ask-url="'.route('genba3d.ask', ['site' => self::SITE]).'"', false);
        }
    }

    public function test_answer_comes_from_the_api_with_the_site_knowledge(): void
    {
        Http::fake([
            self::API => Http::response([
                'model' => 'test-model',
                'stop_reason' => 'end_turn',
                'content' => [
                    ['type' => 'thinking', 'thinking' => '表示しない内容'],
                    ['type' => 'text', 'text' => '盛土の高さは1.30mです。'],
                    ['type' => 'text', 'text' => '（根拠：仮設工平面図）'],
                ],
                'usage' => ['input_tokens' => 9000, 'output_tokens' => 40],
            ], 200),
        ]);

        $this->withSession(['login_user_id' => 7])
            ->postJson(route('genba3d.ask', ['site' => self::SITE]), $this->question('重機用足場の高さは？'))
            ->assertOk()
            ->assertExactJson([
                'answer' => '盛土の高さは1.30mです。（根拠：仮設工平面図）',
                'truncated' => false,
                'remaining' => 29,
            ]);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();
            $knowledge = $payload['system'][1];

            return $request->url() === self::API
                && $request->hasHeader('x-api-key', 'test-key')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && $payload['model'] === 'test-model'
                && $payload['max_tokens'] === 1200
                && str_contains($payload['system'][0]['text'], '朝日地区 東護岸')
                && str_contains($knowledge['text'], '盛土の高さは 1.30m')
                && ! str_contains($knowledge['text'], '船揚場 平面図')   // 別の現場の資料は渡さない
                && $knowledge['cache_control'] === ['type' => 'ephemeral']
                && $payload['messages'] === [['role' => 'user', 'content' => '重機用足場の高さは？']];
        });
    }

    public function test_long_answer_is_marked_as_truncated(): void
    {
        Http::fake([
            self::API => Http::response([
                'stop_reason' => 'max_tokens',
                'content' => [['type' => 'text', 'text' => '途中まで']],
            ], 200),
        ]);

        $this->withSession(['login_user_id' => 1])
            ->postJson(route('genba3d.ask', ['site' => self::SITE]), $this->question('全部教えて'))
            ->assertOk()
            ->assertJson(['answer' => '途中まで', 'truncated' => true]);
    }

    public function test_history_is_cleaned_before_it_is_sent(): void
    {
        Http::fake([self::API => Http::response(['content' => [['type' => 'text', 'text' => 'はい']]], 200)]);

        $this->withSession(['login_user_id' => 1])
            ->postJson(route('genba3d.ask', ['site' => self::SITE]), ['messages' => [
                ['role' => 'assistant', 'content' => '先頭の回答（質問がないので捨てる）'],
                ['role' => 'user', 'content' => '最初の質問'],
                ['role' => 'system', 'content' => '認めない役割'],
                ['role' => 'assistant', 'content' => str_repeat('長', 3000)],
                ['role' => 'user', 'content' => '  '],
                ['role' => 'user', 'content' => '次の質問'],
            ]])
            ->assertOk();

        Http::assertSent(function ($request): bool {
            $messages = $request->data()['messages'];

            return array_column($messages, 'role') === ['user', 'assistant', 'user']
                && $messages[0]['content'] === '最初の質問'
                && mb_strlen($messages[1]['content']) === 2000
                && $messages[2]['content'] === '次の質問';
        });
    }

    public function test_only_the_latest_exchanges_are_sent(): void
    {
        Http::fake([self::API => Http::response(['content' => [['type' => 'text', 'text' => 'はい']]], 200)]);

        $messages = [];
        for ($i = 1; $i <= 10; $i++) {
            $messages[] = ['role' => 'user', 'content' => "質問{$i}"];
            $messages[] = ['role' => 'assistant', 'content' => "回答{$i}"];
        }
        $messages[] = ['role' => 'user', 'content' => '今回の質問'];

        $this->withSession(['login_user_id' => 1])
            ->postJson(route('genba3d.ask', ['site' => self::SITE]), ['messages' => $messages])
            ->assertOk();

        Http::assertSent(function ($request): bool {
            $sent = $request->data()['messages'];

            return count($sent) === 9
                && $sent[0] === ['role' => 'user', 'content' => '質問7']
                && end($sent) === ['role' => 'user', 'content' => '今回の質問'];
        });
    }

    public function test_bad_questions_are_rejected_without_calling_the_api(): void
    {
        Http::fake();
        $url = route('genba3d.ask', ['site' => self::SITE]);

        $bad = [
            [],
            ['messages' => 'テキスト'],
            ['messages' => []],
            $this->question('   '),
            $this->question(str_repeat('あ', 601)),
            ['messages' => [['role' => 'user', 'content' => '質問'], ['role' => 'assistant', 'content' => '回答で終わる']]],
        ];
        foreach ($bad as $payload) {
            $this->withSession(['login_user_id' => 1])->postJson($url, $payload)->assertStatus(422);
        }

        Http::assertNothingSent();
    }

    public function test_daily_limit_per_user_stops_further_questions(): void
    {
        config(['genba3d.chat.daily_limit_per_user' => 2]);
        Http::fake([self::API => Http::response(['content' => [['type' => 'text', 'text' => 'はい']]], 200)]);
        $url = route('genba3d.ask', ['site' => self::SITE]);

        $this->withSession(['login_user_id' => 3])->postJson($url, $this->question('1回目'))->assertOk()->assertJson(['remaining' => 1]);
        $this->withSession(['login_user_id' => 3])->postJson($url, $this->question('2回目'))->assertOk()->assertJson(['remaining' => 0]);
        $this->withSession(['login_user_id' => 3])->postJson($url, $this->question('3回目'))->assertStatus(429);

        // 別の人はまだ使える
        $this->withSession(['login_user_id' => 4])->postJson($url, $this->question('1回目'))->assertOk();

        Http::assertSentCount(3);
    }

    public function test_daily_limit_for_everyone_stops_further_questions(): void
    {
        config(['genba3d.chat.daily_limit_total' => 1]);
        Http::fake([self::API => Http::response(['content' => [['type' => 'text', 'text' => 'はい']]], 200)]);
        $url = route('genba3d.ask', ['site' => self::SITE]);

        $this->withSession(['login_user_id' => 5])->postJson($url, $this->question('1回目'))->assertOk();
        $this->withSession(['login_user_id' => 6])->postJson($url, $this->question('1回目'))->assertStatus(429);

        Http::assertSentCount(1);
    }

    public function test_api_failure_is_reported_and_not_counted(): void
    {
        config(['genba3d.chat.daily_limit_per_user' => 1]);
        Http::fakeSequence(self::API)
            ->push(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']], 529)
            ->push(['content' => []], 200)
            ->push(['content' => [['type' => 'text', 'text' => 'はい']]], 200);
        $url = route('genba3d.ask', ['site' => self::SITE]);

        // API のエラーと、本文が空の回答は、どちらも「受け取れなかった」として扱う
        $this->withSession(['login_user_id' => 8])->postJson($url, $this->question('質問'))->assertStatus(502);
        $this->withSession(['login_user_id' => 8])->postJson($url, $this->question('質問'))->assertStatus(502);
        // 失敗は回数に数えないので、上限1回でもまだ使える
        $this->withSession(['login_user_id' => 8])->postJson($url, $this->question('質問'))->assertOk();
    }

    public function test_unknown_site_is_not_found(): void
    {
        Http::fake();

        $this->withSession(['login_user_id' => 1])
            ->postJson(route('genba3d.ask', ['site' => 'no-such-site']), $this->question('質問'))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    /** @return array{messages: list<array{role: string, content: string}>} */
    private function question(string $text): array
    {
        return ['messages' => [['role' => 'user', 'content' => $text]]];
    }
}
