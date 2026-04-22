<?php

namespace App\Http\Middleware;

use App\Services\WebRememberService;
use Closure;
use Illuminate\Http\Request;

class RestoreWebSessionFromRemember
{
    public function __construct(private WebRememberService $webRememberService) {}

    public function handle(Request $request, Closure $next)
    {
        // ログイン試行中は復元しない（誤った ID/パスで入力しても別ユーザーに入らないようにする）
        if ($request->isMethod('POST') && $request->routeIs('login.attempt')) {
            return $next($request);
        }

        if (! $request->session()->has('login_user_id')) {
            $this->webRememberService->attemptRestore($request);
        }

        return $next($request);
    }
}
