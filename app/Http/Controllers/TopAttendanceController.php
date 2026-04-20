<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use App\Services\WorkplaceService;
use Illuminate\Http\Request;

class TopAttendanceController extends Controller
{
    private AttendanceService $attendanceService;
    private WorkplaceService $workplaceService;

    public function __construct(AttendanceService $attendanceService, WorkplaceService $workplaceService)
    {
        $this->attendanceService = $attendanceService;
        $this->workplaceService = $workplaceService;
    }

    public function index(Request $request)
    {
        if (!session()->has('login_user_id')) {
            return redirect()->route('login');
        }

        $workplaceId = $request->input('workplace_id');
        $workDate = $request->input('work_date');

        $attendanceData = $this->attendanceService->GetAttendance($workplaceId, $workDate);
        $workplaceList = $this->workplaceService->getWorkplaceList(true);
        if ($workplaceList === false || $workplaceList === null) {
            $workplaceList = [];
        }

        // workplace_id が未決の場合は、現役場の先頭を使う
        if (!$attendanceData || empty($attendanceData['workplace_id'])) {
            $fallbackWorkplaceId = $workplaceList[0]->id ?? null;
            $attendanceData = $this->attendanceService->GetAttendance($fallbackWorkplaceId, $workDate);
        }

        $attendanceItems = [];
        $resolvedWorkplaceId = is_array($attendanceData) ? ($attendanceData['workplace_id'] ?? $workplaceId) : $workplaceId;
        $resolvedWorkDate = is_array($attendanceData) ? ($attendanceData['work_date'] ?? $workDate) : $workDate;

        if (is_array($attendanceData) && isset($attendanceData['attendance_data'])) {
            $attendanceItems = $attendanceData['attendance_data'];
        }

        // attendance が空でも、画面上は編集できるように v_attendance_all を試す
        if ($resolvedWorkplaceId && $resolvedWorkDate && (empty($attendanceItems) || $attendanceItems->count() === 0)) {
            $all = $this->attendanceService->GetAttendanceAllStaff($resolvedWorkplaceId, $resolvedWorkDate);
            if ($all) {
                if (is_array($all)) {
                    if (count($all) > 0) {
                        $attendanceItems = $all;
                    }
                } elseif (is_object($all) && method_exists($all, 'count') && $all->count() > 0) {
                    $attendanceItems = $all;
                }
            }
        }

        // 月次表表示（以前のPDF出力をWebページ表示へ変更）
        if ($request->has('output_pdf')) {
            $monthlyData = $this->attendanceService->GetPdfData($resolvedWorkDate ?: date('Y-m-d'));
            if ($monthlyData === false || empty($monthlyData['attendance_table_list'] ?? [])) {
                return redirect()
                    ->route('top.attendance', [
                        'workplace_id' => $resolvedWorkplaceId,
                        'work_date' => $resolvedWorkDate,
                    ])
                    ->with('status', '表示できる勤怠データがありません。');
            }

            return view('top.attendance_monthly')->with($monthlyData);
        }

        return view('top.attendance')->with([
            'display_date' => $resolvedWorkDate ? formatJapaneseDate($resolvedWorkDate) : '',
            'workplace_list' => $workplaceList,
            'workplace_id' => $resolvedWorkplaceId,
            'work_date' => $resolvedWorkDate,
            'attendance_data' => $attendanceItems,
        ]);
    }

    public function update(Request $request)
    {
        if (!session()->has('login_user_id')) {
            return redirect()->route('login');
        }

        $workplaceId = $request->input('workplace_id');
        $workDate = $request->input('work_date');

        $startTimes = $request->input('start_time', []);
        $endTimes = $request->input('end_time', []);
        $breakTimes = $request->input('break_time', []);
        $absenceFlags = $request->input('absence_flg', []);

        $staffIds = array_values(array_unique(array_merge(
            array_keys($startTimes),
            array_keys($endTimes),
            array_keys($breakTimes),
            array_keys($absenceFlags),
        )));

        $result = true;
        foreach ($staffIds as $staffId) {
            $start = $startTimes[$staffId] ?? '';
            $end = $endTimes[$staffId] ?? '';
            $break = $breakTimes[$staffId] ?? '';

            // チェックされていれば存在する（hidden併用で 0/1 を送ってもOK）
            $absenceFlg = array_key_exists($staffId, $absenceFlags) ? (bool)intval($absenceFlags[$staffId]) : false;

            $ok = $this->attendanceService->AttendanceUpdate(
                $staffId,
                $workplaceId,
                $workDate,
                $start,
                $end,
                $break,
                $absenceFlg
            );

            if (!$ok) {
                $result = false;
            }
        }

        return redirect()
            ->route('top.attendance', ['workplace_id' => $workplaceId, 'work_date' => $workDate])
            ->with('status', $result ? '勤怠を保存しました' : '保存に失敗しました（内容をご確認ください）');
    }
}

