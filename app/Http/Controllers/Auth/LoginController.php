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

            if ($appFlag) {
                $request->session()->put(
                    config('tokens.app_token'),
                    $this->authenticationService->GenerateToken($user->id, true)
                );
                $request->session()->forget(config('tokens.web_token'));
            } else {
                // Web の長期ログイントークンは redirectAfterWebLogin で端末ごとに発行する。
                $request->session()->forget(config('tokens.app_token'));
                $request->session()->forget(config('tokens.web_token'));
            }

            return $this->redirectAfterWebLogin($request, $user->id, $appFlag);
        }

        // 開発用フォールバックは、local かつ明示的に有効化した場合のみ許可する。
        if (app()->environment('local') && (bool) config('auth.allow_dev_login_fallback', false)) {
            $request->session()->regenerate();
            $request->session()->put('login_user_id', 1);
            $request->session()->put(config('tokens.web_token'), Str::random(16));
            $request->session()->forget(config('tokens.app_token'));

            Cookie::queue(cookie()->forget(config('remember_web.cookie'), '/', config('session.domain')));

            return redirect()->route('top.assignment')->with('status', '開発用ログイン（DB認証に失敗しました）');
        }

        return back()
            ->withInput($request->only('login_id'))
            ->with('error', 'ログインIDまたはパスワードが正しくありません。データベースに接続できない場合は管理者に連絡してください。');
    }

    public function logout(Request $request)
    {
        $uid = (int) $request->session()->get('login_user_id');
        if ($uid > 0) {
            // 他の端末はログイン状態を維持し、この端末の Remember Token だけを無効化する。
            app(WebRememberService::class)->revokeCurrentDevice($request, $uid);
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
        $remember = app(WebRememberService::class);

        if ($appFlag) {
            $remember->revokeCurrentDevice($request);
            Cookie::queue(cookie()->forget(config('remember_web.cookie'), '/', config('session.domain')));

            return $response;
        }

        if (! $request->boolean('remember')) {
            $remember->revokeCurrentDevice($request);
            Cookie::queue(cookie()->forget(config('remember_web.cookie'), '/', config('session.domain')));

            return $response;
        }

        // 同じ端末で再ログインした場合は古い行を消し、新しい端末用トークンに入れ替える。
        $remember->revokeCurrentDevice($request);
        $deviceToken = $remember->issueDeviceToken($userId, $request->userAgent());
        if ($deviceToken === null) {
            return $response;
        }

        $request->session()->put(
            config('tokens.web_token'),
            $deviceToken['sel'] !== '' ? $deviceToken['sel'] : $deviceToken['tok']
        );

        $minutes = (int) config('remember_web.lifetime_minutes', 43200);
        $payload = Crypt::encryptString(json_encode($deviceToken, JSON_THROW_ON_ERROR));

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
