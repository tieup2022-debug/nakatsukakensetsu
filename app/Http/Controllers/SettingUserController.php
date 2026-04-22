<?php

namespace App\Http\Controllers;

use App\Services\PasswordService;
use App\Services\UserService;
use Illuminate\Http\Request;

class SettingUserController extends Controller
{
    private UserService $userService;

    private PasswordService $passwordService;

    public function __construct(UserService $userService, PasswordService $passwordService)
    {
        $this->userService = $userService;
        $this->passwordService = $passwordService;
    }

    /**
     * マスター・担当者のみ。利用者（3）は不可。
     */
    private function canAdminResetPassword(int $loginUserId): bool
    {
        $admin = $this->userService->GetUser($loginUserId);

        return $admin && (int) $admin->permission <= 2;
    }

    public function manage()
    {
        return view('setting.user.manage');
    }

    public function create(Request $request)
    {
        $result = null;

        if ($request->isMethod('POST')) {
            $result = $this->userService->create(
                $request->input('user_name'),
                $request->input('login_id'),
                $request->input('permission')
            );
        }

        return view('setting.user.create')->with(['result' => $result]);
    }

    public function list()
    {
        $user_list = $this->userService->GetUserList();
        if ($user_list === false || $user_list === null) $user_list = [];

        return view('setting.user.list')->with([
            'user_list' => $user_list,
            'result' => null,
        ]);
    }

    public function update(Request $request)
    {
        $result = null;
        $userId = $request->input('user_id');

        if ($request->isMethod('POST')) {
            $result = $this->userService->update(
                $userId,
                $request->input('user_name'),
                $request->input('login_id'),
                $request->input('permission')
            );
        }

        $userData = $this->userService->GetUser($userId);
        if (!$userData) {
            $user_list = $this->userService->GetUserList();
            if ($user_list === false || $user_list === null) $user_list = [];

            return view('setting.user.list')->with([
                'user_list' => $user_list,
                'result' => false,
            ]);
        }

        return view('setting.user.update')->with([
            'user_data' => $userData,
            'result' => $result,
        ]);
    }

    public function delete(Request $request)
    {
        $result = $this->userService->delete($request->input('user_id'));

        $user_list = $this->userService->GetUserList();
        if ($user_list === false || $user_list === null) $user_list = [];

        return view('setting.user.list')->with([
            'user_list' => $user_list,
            'result' => $result,
        ]);
    }

    public function resetPasswordForm(Request $request)
    {
        $loginUserId = (int) $request->session()->get('login_user_id');
        if (! $this->canAdminResetPassword($loginUserId)) {
            return redirect()->route('top.setting')->with('status', 'パスワード再設定はマスター・担当者のみ行えます。');
        }

        $userId = (int) $request->query('user_id');
        if ($userId <= 0) {
            return redirect()->route('setting.user.list')->with('status', 'ユーザーを指定してください。');
        }

        $target = $this->userService->GetUser($userId);
        if (! $target) {
            return redirect()->route('setting.user.list')->with('status', 'ユーザーが見つかりません。');
        }

        return view('setting.user.password_reset')->with([
            'target' => $target,
        ]);
    }

    public function resetPasswordSubmit(Request $request)
    {
        $loginUserId = (int) $request->session()->get('login_user_id');
        if (! $this->canAdminResetPassword($loginUserId)) {
            return redirect()->route('top.setting')->with('status', 'パスワード再設定はマスター・担当者のみ行えます。');
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'password1' => ['required', 'string', 'min:4', 'max:255'],
            'password2' => ['required', 'same:password1'],
        ]);

        $targetId = (int) $validated['user_id'];
        $target = $this->userService->GetUser($targetId);
        if (! $target) {
            return redirect()->route('setting.user.list')->with('status', 'ユーザーが見つかりません。');
        }

        $ok = $this->passwordService->adminResetPassword(
            $targetId,
            $validated['password1'],
            $validated['password2']
        );

        return redirect()
            ->route('setting.user.list')
            ->with('status', $ok ? 'パスワードを再設定しました。対象ユーザーに新しいパスワードを伝えてください。' : 'パスワードの再設定に失敗しました。');
    }
}

