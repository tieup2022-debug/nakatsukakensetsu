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
     * マスター（権限1）のみ。
     */
    private function isMasterUser(Request $request): bool
    {
        $uid = (int) $request->session()->get('login_user_id');
        if ($uid <= 0) {
            return false;
        }

        $user = $this->userService->GetUser($uid);

        return $user && (int) $user->permission === 1;
    }

    private function redirectUnlessMaster(Request $request): ?\Illuminate\Http\RedirectResponse
    {
        if (! $this->isMasterUser($request)) {
            return redirect()->route('top.setting')->with('status', 'ユーザー管理/アカウントは管理者（権限1）のみ利用できます。');
        }

        return null;
    }

    public function manage(Request $request)
    {
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
        }

        return view('setting.user.manage');
    }

    public function create(Request $request)
    {
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
        }

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

    public function list(Request $request)
    {
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
        }

        $user_list = $this->userService->GetUserList();
        if ($user_list === false || $user_list === null) $user_list = [];

        return view('setting.user.list')->with([
            'user_list' => $user_list,
            'result' => null,
        ]);
    }

    public function update(Request $request)
    {
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
        }

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
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
        }

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
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
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
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
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

