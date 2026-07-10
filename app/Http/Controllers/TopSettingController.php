<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use App\Support\UserPermission;
use Illuminate\Http\Request;

class TopSettingController extends Controller
{
    public function index(Request $request, UserService $userService)
    {
        if (!session()->has('login_user_id')) {
            return redirect()->route('login');
        }

        $canEditAttendanceDefaults = false;
        $canManageUsersAndAccounts = false;
        $canAccessAssignmentSettings = false;
        $uid = (int) $request->session()->get('login_user_id');
        if ($uid > 0) {
            $user = $userService->GetUser($uid);
            $canEditAttendanceDefaults = $user && UserPermission::isMaster($user->permission ?? null);
            $canManageUsersAndAccounts = $user && UserPermission::isMaster($user->permission ?? null);
            $canAccessAssignmentSettings = $user && UserPermission::isManager($user->permission ?? null);
        }

        return view('top.setting')->with([
            'result' => null,
            'can_edit_attendance_defaults' => $canEditAttendanceDefaults,
            'can_manage_users_and_accounts' => $canManageUsersAndAccounts,
            'can_access_assignment_settings' => $canAccessAssignmentSettings,
        ]);
    }
}

