<?php

namespace App\Http\Controllers;

use App\Services\LinkUserService;
use App\Services\PasswordService;
use App\Services\UserService;
use Illuminate\Http\Request;

class SettingAccountController extends Controller
{
    private LinkUserService $linkUserService;
    private PasswordService $passwordService;
    private UserService $userService;

    public function __construct(LinkUserService $linkUserService, PasswordService $passwordService, UserService $userService)
    {
        $this->linkUserService = $linkUserService;
        $this->passwordService = $passwordService;
        $this->userService = $userService;
    }

    public function linkUserUpdate(Request $request)
    {
        $result = null;
        $loginUserId = $request->session()->get('login_user_id');

        if ($request->isMethod('POST')) {
            $result = $this->linkUserService->GetLinkUser($request->input('user_id'), $request->input('staff_id'));
        }

        $staffList = $this->linkUserService->GetUnlinkedStaff();
        if ($staffList === false || $staffList === null) $staffList = [];

        $staffData = $this->linkUserService->GetLinkedStaff($loginUserId);

        $userList = $this->userService->GetUserList();
        if ($userList === false || $userList === null) $userList = [];

        return view('setting.linkuser.update')->with([
            'user_list' => $userList,
            'staff_list' => $staffList,
            'login_user_id' => $loginUserId,
            'staff_data' => $staffData,
            'result' => $result,
        ]);
    }

    public function passwordUpdate(Request $request)
    {
        $result = null;

        if ($request->isMethod('POST')) {
            $result = $this->passwordService->update(
                $request->session()->get('login_user_id'),
                $request->input('password1'),
                $request->input('password2')
            );
        }

        return view('setting.password.update')->with(['result' => $result]);
    }
}

