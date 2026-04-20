<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\Request;

class SettingUserController extends Controller
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
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
}

