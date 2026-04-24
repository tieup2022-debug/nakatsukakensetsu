<?php

namespace App\Http\Controllers;

use App\Services\InAppNotificationService;
use Illuminate\Http\Request;

class InAppNotificationController extends Controller
{
    public function __construct(
        private InAppNotificationService $notifications,
    ) {}

    public function index(Request $request)
    {
        $uid = (int) $request->session()->get('login_user_id');
        $list = $this->notifications->listForUser($uid);
        if ($list === false) {
            $list = collect();
        }

        return view('notifications.index', [
            'notifications' => $list,
        ]);
    }

    public function markRead(Request $request, int $id)
    {
        $uid = (int) $request->session()->get('login_user_id');
        $this->notifications->markRead($uid, $id);

        return redirect()->route('notifications.index');
    }

    public function markAllRead(Request $request)
    {
        $uid = (int) $request->session()->get('login_user_id');
        $this->notifications->markAllRead($uid);

        return redirect()->route('notifications.index')->with('status', 'すべて既読にしました。');
    }
}
