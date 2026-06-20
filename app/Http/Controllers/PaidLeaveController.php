<?php

namespace App\Http\Controllers;

use App\Services\LinkUserService;
use App\Services\PaidLeaveService;
use App\Services\StaffService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PaidLeaveController extends Controller
{
    public function __construct(
        private PaidLeaveService $paidLeaveService,
        private LinkUserService $linkUserService,
        private StaffService $staffService,
        private UserService $userService,
    ) {}

    public function index(Request $request)
    {
        $uid = (int) $request->session()->get('login_user_id');
        $linkedStaff = $this->linkUserService->GetLinkedStaff($uid);
        $user = $uid > 0 ? $this->userService->GetUser($uid) : null;
        $isMaster = $user && (int) $user->permission === 1;
        $canApproveByStaff = $linkedStaff && $this->paidLeaveService->isApproverStaffId((int) $linkedStaff->id);
        $canApprove = $isMaster || $canApproveByStaff;

        $staffList = $this->paidLeaveService->filterStaffListForApplicant(
            $this->staffService->GetStaffList()
        );
        $manageStaffList = $isMaster ? collect($this->staffService->GetStaffList() ?: []) : collect();

        $list = $this->paidLeaveService->listRecentWithNames(200);
        if ($list === false) {
            $list = collect();
        }

        return view('paid_leave.index', [
            'staff_list' => $staffList,
            'manage_staff_list' => $manageStaffList,
            'requests' => $list,
            'can_approve_paid_leave' => (bool) $canApprove,
            'can_manage_paid_leave' => (bool) $isMaster,
            'title' => '有給申請',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'applicant_staff_id' => ['required', 'integer', 'min:1'],
            'leave_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $uid = (int) $request->session()->get('login_user_id');
        $targetStaff = $this->staffService->GetStaff((int) $validated['applicant_staff_id']);
        if (! $targetStaff) {
            return back()->withInput()->with('error', '有給対象者を選択してください。');
        }

        if ($this->paidLeaveService->isExcludedStaffId((int) $validated['applicant_staff_id'])) {
            return back()->withInput()->with('error', '選択された社員は有給申請の対象外です。');
        }

        try {
            $tz = (string) config('app.display_timezone', config('app.timezone'));
            $startsAt = Carbon::parse($validated['leave_date'].' '.$validated['start_time'], $tz);
            $endsAt = Carbon::parse($validated['leave_date'].' '.$validated['end_time'], $tz);
        } catch (\Throwable) {
            return back()->with('error', '日付・時刻の形式が正しくありません。');
        }

        if ($endsAt->lte($startsAt)) {
            return back()->withInput()->with('error', '終了時刻は開始時刻より後を指定してください。');
        }

        $result = $this->paidLeaveService->createRequest(
            (int) $validated['applicant_staff_id'],
            $uid,
            $startsAt,
            $endsAt,
            $validated['reason'] ?? null
        );

        if (! $result) {
            return back()->withInput()->with('error', '申請に失敗しました。時間をご確認ください。');
        }

        return back()->with('status', '有給申請を送信しました。承認者に通知しました。');
    }

    public function update(Request $request, int $id)
    {
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
        }

        $validated = $request->validate([
            'applicant_staff_id' => ['required', 'integer', 'min:1'],
            'leave_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $this->paidLeaveService->findRequest($id)) {
            return back()->with('error', '申請が見つかりません。');
        }

        $targetStaff = $this->staffService->GetStaff((int) $validated['applicant_staff_id']);
        if (! $targetStaff) {
            return back()->withInput()->with('error', '有給対象者を選択してください。');
        }

        if ($this->paidLeaveService->isExcludedStaffId((int) $validated['applicant_staff_id'])) {
            return back()->withInput()->with('error', '選択された社員は有給申請の対象外です。');
        }

        try {
            $tz = (string) config('app.display_timezone', config('app.timezone'));
            $startsAt = Carbon::parse($validated['leave_date'].' '.$validated['start_time'], $tz);
            $endsAt = Carbon::parse($validated['leave_date'].' '.$validated['end_time'], $tz);
        } catch (\Throwable) {
            return back()->with('error', '日付・時刻の形式が正しくありません。');
        }

        if ($endsAt->lte($startsAt)) {
            return back()->withInput()->with('error', '終了時刻は開始時刻より後を指定してください。');
        }

        $ok = $this->paidLeaveService->updateRequest(
            $id,
            (int) $validated['applicant_staff_id'],
            $startsAt,
            $endsAt,
            $validated['reason'] ?? null
        );

        if (! $ok) {
            return back()->withInput()->with('error', '更新に失敗しました。');
        }

        return back()->with('status', '有給申請を更新しました。');
    }

    public function destroy(Request $request, int $id)
    {
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
        }

        if (! $this->paidLeaveService->deleteRequest($id)) {
            return back()->with('error', '削除できませんでした。');
        }

        return back()->with('status', '有給申請を削除しました。');
    }

    public function mine(Request $request)
    {
        return redirect()->route('paid-leave.index');
    }

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
            return redirect()->route('paid-leave.index')->with('error', '有給申請の編集・削除は管理者（権限1）のみ利用できます。');
        }

        return null;
    }
}
