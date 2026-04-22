<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthenticationService;
use App\Services\WebRememberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    private AuthenticationService $authenticationService;

    public function __construct(AuthenticationService $authenticationService)
    {
        $this->authenticationService = $authenticationService;
    }

    public function showLoginForm(Request $request)
    {
        if ($request->session()->has('login_user_id')) {
            return redirect()->route('top.assignment');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginId = $credentials['login_id'];
        $password = $credentials['password'];

        // Web版: app_flag は null なら false扱い
        $appFlag = $request->input('app_flag') !== null;

        $user = $this->authenticationService->Login($loginId, $password);
        if ($user) {
            $request->session()->regenerate();
            $request->session()->put('login_user_id', $user->id);

            $request->session()->put(
                config('tokens.app_token'),
                $appFlag ? $this->authenticationService->GenerateToken($user->id, true) : null
            );
            $request->session()->put(
                config('tokens.web_token'),
                !$appFlag ? $this->authenticationService->GenerateToken($user->id, false) : null
            );
            $request->session()->forget($appFlag ? config('tokens.web_token') : config('tokens.app_token'));

            return $this->redirectAfterWebLogin($request, $user->id, $appFlag);
        }

        // 開発中: ローカルDB未設定でも画面を触れるようにするフォールバック
        $request->session()->regenerate();
        $request->session()->put('login_user_id', 1);
        $request->session()->put(config('tokens.web_token'), Str::random(16));
        $request->session()->forget(config('tokens.app_token'));

        Cookie::queue(cookie()->forget(config('remember_web.cookie'), '/', config('session.domain')));

        return redirect()->route('top.assignment')->with('status', '開発用ログイン（DB認証に失敗しました）');
    }

    public function logout(Request $request)
    {
        $uid = (int) $request->session()->get('login_user_id');
        if ($uid > 0) {
            app(WebRememberService::class)->invalidateWebToken($uid);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Cookie::queue(cookie()->forget(config('remember_web.cookie'), '/', config('session.domain')));

        return redirect()->route('login');
    }

    /**
     * Web ログイン後: 「ログイン状態を保持する」なら Remember Cookie を付与する。
     */
    private function redirectAfterWebLogin(Request $request, int $userId, bool $appFlag)
    {
        $response = redirect()->route('top.assignment');

        if ($appFlag) {
            Cookie::queue(cookie()->forget(config('remember_web.cookie'), '/', config('session.domain')));

            return $response;
        }

        if (! $request->boolean('remember')) {
            Cookie::queue(cookie()->forget(config('remember_web.cookie'), '/', config('session.domain')));

            return $response;
        }

        $webToken = $request->session()->get(config('tokens.web_token'));
        if (! is_string($webToken) || $webToken === '') {
            return $response;
        }

        $remember = app(WebRememberService::class);
        $minutes = (int) config('remember_web.lifetime_minutes', 43200);
        $payload = Crypt::encryptString(json_encode(['uid' => $userId, 'tok' => $webToken]));

        return $response->withCookie(cookie(
            config('remember_web.cookie'),
            $payload,
            $minutes,
            '/',
            config('session.domain'),
            $remember->cookieSecure($request),
            true,
            false,
            config('session.same_site', 'lax')
        ));
    }
}

