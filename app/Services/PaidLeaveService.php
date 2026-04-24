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
            }
        }
    }

    public function approve(int $requestId, int $approverStaffId): bool
    {
        if (! $this->isApproverStaffId($approverStaffId)) {
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

            if ((int) $row->applicant_staff_id === $approverStaffId) {
                DB::rollBack();

                return false;
            }

            DB::table('t_paid_leave_requests')
                ->where('id', '=', $requestId)
                ->update([
                    'status' => 'approved',
                    'approved_by_staff_id' => $approverStaffId,
                    'approved_at' => now(),
                    'updated_at' => now(),
                ]);

            $updated = DB::table('t_paid_leave_requests')->where('id', '=', $requestId)->first();

            DB::commit();

            if ($updated) {
                $this->dispatchApprovedNotifications($updated, $approverStaffId);
            }

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }

    private function dispatchApprovedNotifications(object $request, int $approverStaffId): void
    {
        $applicantStaffId = (int) $request->applicant_staff_id;
        $approverName = $this->staffDisplayName($approverStaffId);
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
}
