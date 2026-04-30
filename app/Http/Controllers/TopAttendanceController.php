<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use App\Services\WorkplaceService;
use Carbon\Carbon;
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
        if ($resolvedWorkDate) {
            try {
                $resolvedWorkDate = Carbon::parse((string) $resolvedWorkDate, config('app.timezone'))->format('Y-m-d');
            } catch (\Throwable) {
                // そのまま（後段で表示・保存が失敗する場合はユーザーが日付を選び直す）
            }
        }

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

        $hasAttendanceRows = is_array($attendanceItems)
            ? count($attendanceItems) > 0
            : (is_object($attendanceItems) && method_exists($attendanceItems, 'count') && $attendanceItems->count() > 0);
        if ($resolvedWorkDate && $hasAttendanceRows) {
            $attendanceItems = $this->attendanceService->overlayTAttendanceTimes($attendanceItems, $resolvedWorkDate);
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
        $workDateInput = $request->input('work_date');
        try {
            $workDate = Carbon::parse($workDateInput, config('app.timezone'))->format('Y-m-d');
        } catch (\Throwable) {
            return redirect()
                ->route('top.attendance', [
                    'workplace_id' => $workplaceId,
                    'work_date' => is_string($workDateInput) && $workDateInput !== '' ? $workDateInput : date('Y-m-d'),
                ])
                ->with('error', '作業日の形式が正しくありません。');
        }

        $startTimes = $request->input('start_time', []);
        $endTimes = $request->input('end_time', []);
        $breakTimes = $request->input('break_time', []);
        $absenceFlags = $request->input('absence_flg', []);
        $staffIdsFromForm = $request->input('staff_ids', []);

        $defaults = $this->attendanceService->GetDefaults();
        $defaultStart = $this->normalizeTimeInput($defaults->start_time ?? null, '08:00');
        $defaultEnd = $this->normalizeTimeInput($defaults->end_time ?? null, '17:00');
        $defaultBreak = $this->normalizeTimeInput($defaults->break_time ?? null, '01:00');

        $staffIds = array_values(array_unique(array_filter(array_map(
            'intval',
            array_merge(
                array_keys($staffIdsFromForm),
                array_keys($startTimes),
                array_keys($endTimes),
                array_keys($breakTimes),
                array_keys($absenceFlags),
            )
        ), fn ($id) => (int) $id > 0)));

        if ($staffIds === []) {
            return redirect()
                ->route('top.attendance', ['workplace_id' => $workplaceId, 'work_date' => $workDate])
                ->with('error', '保存対象の社員が取得できませんでした。画面を再表示してからお試しください。');
        }

        $result = true;
        foreach ($staffIds as $staffId) {
            $start = $this->normalizeTimeInput($this->timeFromKeyedArray($startTimes, $staffId), $defaultStart);
            $end = $this->normalizeTimeInput($this->timeFromKeyedArray($endTimes, $staffId), $defaultEnd);
            $break = $this->normalizeTimeInput($this->timeFromKeyedArray($breakTimes, $staffId), $defaultBreak);

            // チェックされていれば存在する（hidden併用で 0/1 を送ってもOK）
            $absenceRaw = $this->timeFromKeyedArray($absenceFlags, $staffId);
            $absenceFlg = $absenceRaw !== null ? (bool) intval($absenceRaw) : false;

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

    private function normalizeTimeInput($value, string $fallback): string
    {
        if ($value === null) {
            return $fallback;
        }
        if (is_string($value)) {
            $raw = trim($value);
        } elseif (is_numeric($value)) {
            $raw = (string) $value;
        } else {
            $raw = '';
        }
        if ($raw === '') {
            return $fallback;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2}(?:\.\d+)?)?$/', $raw, $m) === 1) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        if (preg_match('/\d{4}-\d{2}-\d{2}\s+(\d{1,2}):(\d{2})(?::\d{2}(?:\.\d+)?)?/', $raw, $m) === 1) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return $fallback;
    }

    /**
     * POST の name="field[社員ID]" はキーが文字列になることが多い。int/string の両方で参照する。
     *
     * @param  array<string|int, mixed>  $arr
     */
    private function timeFromKeyedArray(array $arr, int $staffId): mixed
    {
        if (array_key_exists($staffId, $arr)) {
            return $arr[$staffId];
        }
        $sk = (string) $staffId;
        if (array_key_exists($sk, $arr)) {
            return $arr[$sk];
        }

        return null;
    }
}

