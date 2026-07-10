<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * マスター（permission=1）または担当者（permission=2）のみ通過を許可するミドルウェア。
 *
 * nakatsuka.auth（ログイン確認）の後段に付与して使う。
 */
class NakatsukaManager
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

        $permission = $user ? (int) $user->permission : 0;
        if ($permission !== 1 && $permission !== 2) {
            return redirect()->route('top.setting')
                ->with('status', 'この操作は管理者・担当者（権限1・2）のみ利用できます。');
        }

        return $next($request);
    }
}
