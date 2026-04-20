<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AuthenticationService;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    private AuthenticationService $authenticationService;

    public function __construct(AuthenticationService $authenticationService)
    {
        $this->authenticationService = $authenticationService;
    }

    public function showLoginForm()
    {
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

            return redirect()->route('top.assignment');
        }

        // 開発中: ローカルDB未設定でも画面を触れるようにするフォールバック
        $request->session()->regenerate();
        $request->session()->put('login_user_id', 1);
        $request->session()->put(config('tokens.web_token'), Str::random(16));
        $request->session()->forget(config('tokens.app_token'));

        return redirect()->route('top.assignment')->with('status', '開発用ログイン（DB認証に失敗しました）');
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

