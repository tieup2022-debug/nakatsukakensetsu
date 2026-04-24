<?php

namespace App\Http\Controllers;

use App\Services\LinkUserService;
use App\Services\PaidLeaveService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PaidLeaveController extends Controller
{
    public function __construct(
        private PaidLeaveService $paidLeaveService,
        private LinkUserService $linkUserService,
    ) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'starts_at' => ['required', 'string', 'max:32'],
            'ends_at' => ['required', 'string', 'max:32'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $uid = (int) $request->session()->get('login_user_id');
        $staff = $this->linkUserService->GetLinkedStaff($uid);
        if (! $staff) {
            return back()->with('error', '社員情報がユーザーに紐づいていません。管理者に連絡してください。');
        }

        try {
            $startsAt = Carbon::parse($validated['starts_at'], config('app.timezone'));
            $endsAt = Carbon::parse($validated['ends_at'], config('app.timezone'));
        } catch (\Throwable) {
            return back()->with('error', '日時の形式が正しくありません。');
        }

        $result = $this->paidLeaveService->createRequest(
            (int) $staff->id,
            $uid,
            $startsAt,
            $endsAt,
            $validated['reason'] ?? null
        );

        if (! $result) {
            return back()->with('error', '申請に失敗しました。終了日時は開始日時より後にしてください。');
        }

        return back()->with('status', '有給申請を送信しました。承認者に通知しました。');
    }

    public function mine(Request $request)
    {
        $uid = (int) $request->session()->get('login_user_id');
        $staff = $this->linkUserService->GetLinkedStaff($uid);
        if (! $staff) {
            return redirect()->route('top.assignment')->with('error', '社員情報がユーザーに紐づいていません。');
        }

        $list = $this->paidLeaveService->listMyRequests((int) $staff->id);
        if ($list === false) {
            $list = collect();
        }

        return view('paid_leave.mine', [
            'requests' => $list,
        ]);
    }
}
