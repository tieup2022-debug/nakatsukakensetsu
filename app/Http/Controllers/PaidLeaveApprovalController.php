<?php

namespace App\Http\Controllers;

use App\Services\LinkUserService;
use App\Services\PaidLeaveService;
use App\Services\UserService;
use Illuminate\Http\Request;

class PaidLeaveApprovalController extends Controller
{
    public function __construct(
        private PaidLeaveService $paidLeaveService,
        private LinkUserService $linkUserService,
        private UserService $userService,
    ) {}

    public function index(Request $request)
    {
        $uid = (int) $request->session()->get('login_user_id');
        $staff = $this->linkUserService->GetLinkedStaff($uid);
        $isMaster = $this->isMasterUser($uid);
        $canApproveByStaff = $staff && $this->paidLeaveService->isApproverStaffId((int) $staff->id);
        if (! $isMaster && ! $canApproveByStaff) {
            abort(403, '承認権限がありません。');
        }

        $list = $this->paidLeaveService->listPendingForApprovers();
        if ($list === false) {
            $list = collect();
        }

        return view('paid_leave.approvals', [
            'requests' => $list,
        ]);
    }

    public function approve(Request $request, int $id)
    {
        $uid = (int) $request->session()->get('login_user_id');
        $staff = $this->linkUserService->GetLinkedStaff($uid);
        $isMaster = $this->isMasterUser($uid);
        $canApproveByStaff = $staff && $this->paidLeaveService->isApproverStaffId((int) $staff->id);
        if (! $isMaster && ! $canApproveByStaff) {
            abort(403, '承認権限がありません。');
        }

        $ok = $this->paidLeaveService->approve($id, (int) ($staff->id ?? 0), $uid, $isMaster);

        if ($ok) {
            return redirect()->route('paid-leave.index')->with('status', '承認しました。');
        }

        return redirect()->route('paid-leave.index')->with('error', '承認できませんでした（既に処理済み、またはご自身の申請です）。');
    }

    private function isMasterUser(int $uid): bool
    {
        if ($uid <= 0) {
            return false;
        }

        $user = $this->userService->GetUser($uid);

        return $user && (int) $user->permission === 1;
    }
}
