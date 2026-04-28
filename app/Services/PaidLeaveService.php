<?php

namespace App\Services;

use App\Mail\PaidLeaveAppliedMail;
use App\Mail\PaidLeaveApprovedMail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class PaidLeaveService
{
    public function __construct(
        private InAppNotificationService $notifications,
        private LinkUserService $linkUserService,
        private StaffService $staffService,
    ) {}

    /**
     * @return array<int, int> staff_id => m_user.id
     */
    public function approverUserIdsByStaffId(): array
    {
        $ids = $this->approverStaffIds();
        if ($ids === []) {
            return [];
        }

        try {
            $rows = DB::table('m_user')
                ->whereIn('staff_id', $ids)
                ->whereNotNull('staff_id')
                ->whereNull('deleted_at')
                ->get(['id', 'staff_id']);

            $map = [];
            foreach ($rows as $row) {
                $map[(int) $row->staff_id] = (int) $row->id;
            }

            return $map;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return [];
        }
    }

    /**
     * @return list<int>
     */
    public function approverStaffIds(): array
    {
        $raw = config('paid_leave.approver_staff_ids', []);

        return array_values(array_unique(array_map('intval', is_array($raw) ? $raw : [])));
    }

    public function isApproverStaffId(int $staffId): bool
    {
        return in_array($staffId, $this->approverStaffIds(), true);
    }

    public function resolveEmailForStaff(int $staffId): ?string
    {
        $map = config('paid_leave.staff_emails', []);
        if (isset($map[$staffId]) && is_string($map[$staffId]) && $map[$staffId] !== '') {
            return $map[$staffId];
        }

        try {
            if (Schema::hasColumn('m_user', 'email')) {
                $email = DB::table('m_user')
                    ->where('staff_id', '=', $staffId)
                    ->whereNull('deleted_at')
                    ->value('email');
                if (is_string($email) && $email !== '') {
                    return $email;
                }
            }
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
        }

        return null;
    }

    public function staffDisplayName(int $staffId): string
    {
        $s = $this->staffService->GetStaff($staffId);
        if ($s && ! empty($s->staff_name)) {
            return (string) $s->staff_name;
        }

        return '社員ID '.$staffId;
    }

    /**
     * @return object|false inserted row shape
     */
    public function createRequest(int $applicantStaffId, int $applicantUserId, Carbon $startsAt, Carbon $endsAt, ?string $reason)
    {
        if ($endsAt->lte($startsAt)) {
            return false;
        }

        try {
            DB::beginTransaction();

            $id = DB::table('t_paid_leave_requests')->insertGetId([
                'applicant_staff_id' => $applicantStaffId,
                'applicant_user_id' => $applicantUserId,
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $endsAt->format('Y-m-d H:i:s'),
                'status' => 'pending',
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('t_paid_leave_requests')->where('id', '=', $id)->first();

            DB::commit();

            if ($row) {
                $this->dispatchAppliedNotifications($row);
            }

            return $row ?: false;
        } catch (\Exception $e) {
            DB::rollBack();
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }

    private function dispatchAppliedNotifications(object $request): void
    {
        $applicantName = $this->staffDisplayName((int) $request->applicant_staff_id);
        $range = $this->formatRange($request);
        $approverMap = $this->approverUserIdsByStaffId();
        $notifiedUserIds = [];

        foreach ($this->approverStaffIds() as $approverStaffId) {
            $email = $this->resolveEmailForStaff($approverStaffId);
            if ($email) {
                try {
                    Mail::to($email)->send(new PaidLeaveAppliedMail(
                        $applicantName,
                        $range,
                        isset($request->reason) ? (string) $request->reason : null
                    ));
                } catch (\Throwable $e) {
                    error($e, __FILE__, __METHOD__, __LINE__);
                }
            }

            $uid = $approverMap[$approverStaffId] ?? null;
            if ($uid) {
                $this->notifications->create(
                    $uid,
                    '有給休暇の申請があります',
                    $applicantName." さんが有給を申請しました。\n".$range.($request->reason ? "\n\n事由: ".$request->reason : ''),
                    'paid_leave_applied',
                    (int) $request->id
                );
                $notifiedUserIds[$uid] = true;
            }
        }

        // 承認者設定に含まれていない管理者（権限1）にも通知する。
        try {
            $adminUserIds = DB::table('m_user')
                ->where('permission', '=', 1)
                ->whereNull('deleted_at')
                ->pluck('id');
            foreach ($adminUserIds as $adminUid) {
                $adminUid = (int) $adminUid;
                if ($adminUid <= 0 || isset($notifiedUserIds[$adminUid])) {
                    continue;
                }
                $this->notifications->create(
                    $adminUid,
                    '有給申請がきました',
                    $applicantName." さんから有給申請が届きました。\n".$range.($request->reason ? "\n\n事由: ".$request->reason : ''),
                    'paid_leave_applied',
                    (int) $request->id
                );
            }
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
        }
    }

    public function approve(int $requestId, int $approverStaffId, ?int $approverUserId = null, bool $allowBypassApproverCheck = false): bool
    {
        if (! $allowBypassApproverCheck && ! $this->isApproverStaffId($approverStaffId)) {
            return false;
        }

        try {
            DB::beginTransaction();

            $row = DB::table('t_paid_leave_requests')
                ->where('id', '=', $requestId)
                ->where('status', '=', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $row) {
                DB::rollBack();

                return false;
            }

            if ($approverStaffId > 0 && (int) $row->applicant_staff_id === $approverStaffId) {
                DB::rollBack();

                return false;
            }

            DB::table('t_paid_leave_requests')
                ->where('id', '=', $requestId)
                ->update(array_merge([
                    'status' => 'approved',
                    'approved_by_staff_id' => $approverStaffId > 0 ? $approverStaffId : null,
                    'approved_at' => now(),
                    'updated_at' => now(),
                ], Schema::hasColumn('t_paid_leave_requests', 'approved_by_user_id') ? [
                    'approved_by_user_id' => ($approverUserId && $approverUserId > 0) ? $approverUserId : null,
                ] : []));

            $updated = DB::table('t_paid_leave_requests')->where('id', '=', $requestId)->first();

            DB::commit();

            if ($updated) {
                $this->dispatchApprovedNotifications($updated, $approverStaffId, $approverUserId);
            }

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }

    private function dispatchApprovedNotifications(object $request, int $approverStaffId, ?int $approverUserId = null): void
    {
        $applicantStaffId = (int) $request->applicant_staff_id;
        $approverName = $this->resolveApproverDisplayName($approverStaffId, $approverUserId);
        $range = $this->formatRange($request);

        $applicantUserId = (int) ($request->applicant_user_id ?? 0);
        if ($applicantUserId > 0) {
            $this->notifications->create(
                $applicantUserId,
                '有給休暇が承認されました',
                $approverName." さんが承認しました。\n".$range,
                'paid_leave_approved',
                (int) $request->id
            );
        }

        $applicantEmail = $this->resolveEmailForStaff($applicantStaffId);
        if ($applicantEmail) {
            try {
                Mail::to($applicantEmail)->send(new PaidLeaveApprovedMail(
                    $approverName,
                    $range,
                    isset($request->reason) ? (string) $request->reason : null
                ));
            } catch (\Throwable $e) {
                error($e, __FILE__, __METHOD__, __LINE__);
            }
        }
    }

    private function formatRange(object $request): string
    {
        $s = Carbon::parse($request->starts_at)->timezone(config('app.timezone'))->format('Y/m/d H:i');
        $e = Carbon::parse($request->ends_at)->timezone(config('app.timezone'))->format('Y/m/d H:i');

        return $s.' 〜 '.$e;
    }

    /**
     * @return Collection<int, object>|false
     */
    public function listPendingForApprovers()
    {
        try {
            return DB::table('t_paid_leave_requests')
                ->where('status', '=', 'pending')
                ->orderByDesc('id')
                ->get();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }

    /**
     * @return Collection<int, object>|false
     */
    public function listMyRequests(int $applicantStaffId)
    {
        try {
            return DB::table('t_paid_leave_requests')
                ->where('applicant_staff_id', '=', $applicantStaffId)
                ->orderByDesc('id')
                ->limit(30)
                ->get();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }

    /**
     * 全員に見せる有給申請一覧（最新）
     *
     * @return Collection<int, object>|false
     */
    public function listRecentWithNames(int $limit = 100)
    {
        try {
            return DB::table('t_paid_leave_requests as r')
                ->leftJoin('m_staff as s', function ($join) {
                    $join->on('s.id', '=', 'r.applicant_staff_id')
                        ->whereNull('s.deleted_at');
                })
                ->leftJoin('m_user as u', function ($join) {
                    $join->on('u.id', '=', 'r.applicant_user_id')
                        ->whereNull('u.deleted_at');
                })
                ->leftJoin('m_staff as ap', function ($join) {
                    $join->on('ap.id', '=', 'r.approved_by_staff_id')
                        ->whereNull('ap.deleted_at');
                })
                ->leftJoin('m_user as au', function ($join) {
                    $join->on('au.id', '=', 'r.approved_by_user_id')
                        ->whereNull('au.deleted_at');
                })
                ->orderByDesc('r.id')
                ->limit($limit)
                ->get([
                    'r.*',
                    DB::raw('COALESCE(s.staff_name, CONCAT("社員ID ", r.applicant_staff_id)) as target_staff_name'),
                    DB::raw('COALESCE(u.user_name, CONCAT("ユーザーID ", r.applicant_user_id)) as requester_user_name'),
                    DB::raw('COALESCE(ap.staff_name, au.user_name, "") as approver_display_name'),
                ]);
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }

    private function resolveApproverDisplayName(int $approverStaffId, ?int $approverUserId = null): string
    {
        if ($approverStaffId > 0) {
            return $this->staffDisplayName($approverStaffId);
        }

        if ($approverUserId && $approverUserId > 0) {
            try {
                $userName = DB::table('m_user')
                    ->where('id', '=', $approverUserId)
                    ->whereNull('deleted_at')
                    ->value('user_name');
                if (is_string($userName) && $userName !== '') {
                    return $userName;
                }
            } catch (\Exception $e) {
                error($e, __FILE__, __METHOD__, __LINE__);
            }

            return 'ユーザーID '.$approverUserId;
        }

        return '承認者';
    }
}
