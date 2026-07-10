<?php

namespace App\Http\Middleware;

use App\Services\UserService;
use App\Support\UserPermission;
use Closure;
use Illuminate\Http\Request;

/**
 * マスター（permission=1）または担当者（permission=2）のみ通過を許可するミドルウェア。
 *
 * nakatsuka.auth（ログイン確認）の後段に付与して使う。
 */
class NakatsukaManager
{
    public function __construct(private UserService $userService) {}

    public function handle(Request $request, Closure $next)
    {
        $uid = (int) $request->session()->get('login_user_id');
        if ($uid <= 0) {
            return redirect()->route('login');
        }

        $user = $this->userService->GetUser($uid);
        if (! $user || ! UserPermission::isManager($user->permission ?? null)) {
            $permissionLabel = UserPermission::label($user->permission ?? null);

            return redirect()->route('top.setting')
                ->with('status', '配置入力は管理者・担当者（権限1・2）のみ利用できます。'
                    ." 現在の権限: {$permissionLabel}。"
                    .' 担当者として利用する場合は、管理者にユーザー管理で権限を「担当者（2）」へ変更してもらってください。');
        }

        return $next($request);
    }
}
