<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\Request;

class TopSettingController extends Controller
{
    public function index(Request $request, UserService $userService)
    {
        if (!session()->has('login_user_id')) {
            return redirect()->route('login');
        }

        $canEditAttendanceDefaults = false;
        $uid = (int) $request->session()->get('login_user_id');
        if ($uid > 0) {
            $user = $userService->GetUser($uid);
            $canEditAttendanceDefaults = $user && (int) $user->permission === 1;
        }

        return view('top.setting')->with([
            'result' => null,
            'can_edit_attendance_defaults' => $canEditAttendanceDefaults,
        ]);
    }
}

