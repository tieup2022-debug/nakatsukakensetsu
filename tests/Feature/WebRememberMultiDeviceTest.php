<?php

namespace Tests\Feature;

use App\Services\PasswordService;
use App\Services\WebRememberService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebRememberMultiDeviceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'remember_web.lifetime_minutes' => 43200,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('m_user', function (Blueprint $table): void {
            $table->id();
            $table->string('login_id')->nullable();
            $table->string('password')->nullable();
            $table->string('access_token_web')->nullable();
            $table->string('access_token_app')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $migration = require database_path('migrations/2026_07_10_000003_create_t_web_remember_tokens.php');
        $migration->up();

        DB::table('m_user')->insert([
            'id' => 1,
            'login_id' => 'multi-device-user',
            'password' => Hash::make('password123'),
            'access_token_web' => null,
            'access_token_app' => null,
            'deleted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_two_devices_restore_independently_and_one_device_logout_keeps_the_other(): void
    {
        $service = app(WebRememberService::class);
        $phone = $service->issueDeviceToken(1, 'Phone Browser');
        $pc = $service->issueDeviceToken(1, 'PC Browser');

        $this->assertNotNull($phone);
        $this->assertNotNull($pc);
        $this->assertNotSame($phone['sel'], $pc['sel']);
        $this->assertSame(2, DB::table('t_web_remember_tokens')->where('user_id', 1)->count());

        $phoneRequest = $this->requestWithRememberCookie($phone);
        $pcRequest = $this->requestWithRememberCookie($pc);
        $service->attemptRestore($phoneRequest);
        $service->attemptRestore($pcRequest);

        $this->assertSame(1, $phoneRequest->session()->get('login_user_id'));
        $this->assertSame(1, $pcRequest->session()->get('login_user_id'));

        $service->revokeCurrentDevice($pcRequest, 1);

        $this->assertSame(1, DB::table('t_web_remember_tokens')->where('user_id', 1)->count());
        $this->assertTrue(DB::table('t_web_remember_tokens')->where('selector', $phone['sel'])->exists());
        $this->assertFalse(DB::table('t_web_remember_tokens')->where('selector', $pc['sel'])->exists());

        $phoneAfterPcLogout = $this->requestWithRememberCookie($phone);
        $pcAfterLogout = $this->requestWithRememberCookie($pc);
        $service->attemptRestore($phoneAfterPcLogout);
        $service->attemptRestore($pcAfterLogout);

        $this->assertSame(1, $phoneAfterPcLogout->session()->get('login_user_id'));
        $this->assertNull($pcAfterLogout->session()->get('login_user_id'));
    }

    public function test_web_login_from_two_devices_creates_two_independent_tokens(): void
    {
        $legacyToken = Str::random(64);
        DB::table('m_user')->where('id', 1)->update(['access_token_web' => $legacyToken]);

        $phoneResponse = $this->withHeader('User-Agent', 'Phone Browser')->post('/login', [
            'login_id' => 'multi-device-user',
            'password' => 'password123',
            'remember' => '1',
        ]);
        $phoneResponse->assertRedirect(route('top.assignment'));
        $phoneResponse->assertCookie(config('remember_web.cookie'));

        $this->flushSession();

        $pcResponse = $this->withHeader('User-Agent', 'PC Browser')->post('/login', [
            'login_id' => 'multi-device-user',
            'password' => 'password123',
            'remember' => '1',
        ]);
        $pcResponse->assertRedirect(route('top.assignment'));
        $pcResponse->assertCookie(config('remember_web.cookie'));

        $this->assertSame(2, DB::table('t_web_remember_tokens')->where('user_id', 1)->count());
        $this->assertSame(
            ['PC Browser', 'Phone Browser'],
            DB::table('t_web_remember_tokens')->where('user_id', 1)->orderBy('user_agent')->pluck('user_agent')->all()
        );
        $this->assertSame($legacyToken, DB::table('m_user')->where('id', 1)->value('access_token_web'));
    }

    public function test_expired_device_token_cannot_restore_session(): void
    {
        $service = app(WebRememberService::class);
        $token = $service->issueDeviceToken(1, 'Expired Browser');
        $this->assertNotNull($token);

        DB::table('t_web_remember_tokens')
            ->where('selector', $token['sel'])
            ->update(['expires_at' => now()->subMinute()]);

        $request = $this->requestWithRememberCookie($token);
        $service->attemptRestore($request);

        $this->assertNull($request->session()->get('login_user_id'));
    }

    public function test_legacy_cookie_remains_valid_during_migration(): void
    {
        $legacyToken = Str::random(64);
        DB::table('m_user')->where('id', 1)->update(['access_token_web' => $legacyToken]);

        $request = $this->requestWithRememberCookie([
            'uid' => 1,
            'tok' => $legacyToken,
        ]);
        app(WebRememberService::class)->attemptRestore($request);

        $this->assertSame(1, $request->session()->get('login_user_id'));
    }

    public function test_password_change_revokes_all_device_and_legacy_tokens(): void
    {
        $service = app(WebRememberService::class);
        $service->issueDeviceToken(1, 'Phone Browser');
        $service->issueDeviceToken(1, 'PC Browser');
        DB::table('m_user')->where('id', 1)->update([
            'access_token_web' => Str::random(64),
            'access_token_app' => Str::random(64),
        ]);

        $updated = app(PasswordService::class)->update(1, 'new-password-123', 'new-password-123');

        $this->assertTrue($updated);
        $this->assertSame(0, DB::table('t_web_remember_tokens')->where('user_id', 1)->count());
        $user = DB::table('m_user')->where('id', 1)->first();
        $this->assertNull($user->access_token_web);
        $this->assertNull($user->access_token_app);
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    /** @param array<string, mixed> $payload */
    private function requestWithRememberCookie(array $payload): Request
    {
        $cookie = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
        $request = Request::create('/', 'GET', [], [config('remember_web.cookie') => $cookie]);
        $session = new Store('test-session', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);

        return $request;
    }
}
