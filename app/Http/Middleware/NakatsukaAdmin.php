<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 管理者（m_user.permission === 1）のみ通過を許可するミドルウェア。
 *
 * nakatsuka.auth（ログイン確認）の後段に付与して使う。ログインしていなければ
 * ログイン画面へ、ログイン済みだが管理者でなければ設定トップへ戻す。
 */
class NakatsukaAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $uid = (int) $request->session()->get('login_user_id');
        if ($uid <= 0) {
            return redirect()->route('login');
        }

        $user = DB::table('m_user')
            ->where('id', '=', $uid)
            ->whereNull('deleted_at')
            ->first();

        if (! $user || (int) $user->permission !== 1) {
            return redirect()->route('top.setting')
                ->with('status', 'この操作は管理者（権限1）のみ利用できます。');
        }

        return $next($request);
    }
}
