<?php

namespace App\Http\Controllers;

use App\Services\LinkUserService;
use App\Services\PaidLeaveService;
use App\Services\StaffService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PaidLeaveController extends Controller
{
    public function __construct(
        private PaidLeaveService $paidLeaveService,
        private LinkUserService $linkUserService,
        private StaffService $staffService,
    ) {}

    public function index(Request $request)
    {
        $uid = (int) $request->session()->get('login_user_id');
        $linkedStaff = $this->linkUserService->GetLinkedStaff($uid);
        $canApprove = $linkedStaff && $this->paidLeaveService->isApproverStaffId((int) $linkedStaff->id);

        $staffList = $this->staffService->GetStaffList();
        if ($staffList === false || $staffList === null) {
            $staffList = collect();
        }

        $list = $this->paidLeaveService->listRecentWithNames(200);
        if ($list === false) {
            $list = collect();
        }

        return view('paid_leave.index', [
            'staff_list' => $staffList,
            'requests' => $list,
            'can_approve_paid_leave' => (bool) $canApprove,
            'title' => '有給申請',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'applicant_staff_id' => ['required', 'integer', 'min:1'],
            'starts_at' => ['required', 'string', 'max:32'],
            'ends_at' => ['required', 'string', 'max:32'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $uid = (int) $request->session()->get('login_user_id');
        $targetStaff = $this->staffService->GetStaff((int) $validated['applicant_staff_id']);
        if (! $targetStaff) {
            return back()->withInput()->with('error', '有給対象者を選択してください。');
        }

        try {
            $startsAt = Carbon::parse($validated['starts_at'], config('app.timezone'));
            $endsAt = Carbon::parse($validated['ends_at'], config('app.timezone'));
        } catch (\Throwable) {
            return back()->with('error', '日時の形式が正しくありません。');
        }

        $result = $this->paidLeaveService->createRequest(
            (int) $validated['applicant_staff_id'],
            $uid,
            $startsAt,
            $endsAt,
            $validated['reason'] ?? null
        );

        if (! $result) {
            return back()->withInput()->with('error', '申請に失敗しました。終了日は開始日以降を指定してください。');
        }

        return back()->with('status', '有給申請を送信しました。承認者に通知しました。');
    }

    public function mine(Request $request)
    {
        return redirect()->route('paid-leave.index');
    }
}
