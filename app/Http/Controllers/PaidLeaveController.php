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

        $sortOptions = ['target_staff', 'starts_at', 'created_at', 'requester', 'status'];
        $sort = (string) $request->query('sort', 'id');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sort, array_merge($sortOptions, ['id']), true)) {
            $sort = 'id';
        }

        $list = $this->paidLeaveService->listRecentWithNames(200, $sort, $direction);
        if ($list === false) {
            $list = collect();
        }

        return view('paid_leave.index', [
            'staff_list' => $staffList,
            'manage_staff_list' => $manageStaffList,
            'requests' => $list,
            'sort' => $sort,
            'direction' => $direction,
            'can_approve_paid_leave' => (bool) $canApprove,
            'can_manage_paid_leave' => (bool) $isMaster,
            'title' => '有給申請',
        ]);
    }

    /**
     * 社員別の有給取得状況サマリー（年度 = 4月〜翌3月）
     */
    public function summary(Request $request)
    {
        $currentFiscalYear = (int) date('n') >= 4 ? (int) date('Y') : (int) date('Y') - 1;
        $fiscalYear = (int) $request->query('fiscal_year', $currentFiscalYear);
        if ($fiscalYear < 2000 || $fiscalYear > 2100) {
            $fiscalYear = $currentFiscalYear;
        }

        $fromDate = sprintf('%04d-04-01', $fiscalYear);
        $toDateExclusive = sprintf('%04d-04-01', $fiscalYear + 1);

        $summary = $this->paidLeaveService->summarizeByStaff($fromDate, $toDateExclusive);
        $staffList = $this->paidLeaveService->filterStaffListForApplicant(
            $this->staffService->GetStaffList()
        );

        return view('paid_leave.summary', [
            'staff_list' => $staffList,
            'summary' => $summary,
            'grants' => $this->paidLeaveService->grantsByStaff($fiscalYear),
            'can_edit_grants' => $this->isMasterUser($request),
            'fiscal_year' => $fiscalYear,
            'current_fiscal_year' => $currentFiscalYear,
            'title' => '有給取得状況',
        ]);
    }

    /**
     * 繰越・当年度付与日数の保存（管理者のみ）
     */
    public function updateGrant(Request $request)
    {
        if (! $this->isMasterUser($request)) {
            return redirect()->route('paid-leave.summary')->with('error', '繰越・付与日数の入力は管理者（権限1）のみ利用できます。');
        }

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'min:1'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'carryover_days' => ['required', 'numeric', 'min:0', 'max:999'],
            'granted_days' => ['required', 'numeric', 'min:0', 'max:999'],
        ]);

        $ok = $this->paidLeaveService->saveGrant(
            (int) $validated['staff_id'],
            (int) $validated['fiscal_year'],
            (float) $validated['carryover_days'],
            (float) $validated['granted_days']
        );

        $redirect = redirect()->route('paid-leave.summary', ['fiscal_year' => (int) $validated['fiscal_year']]);

        return $ok
            ? $redirect->with('status', '繰越・付与日数を保存しました。')
            : $redirect->with('error', '保存に失敗しました。');
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
