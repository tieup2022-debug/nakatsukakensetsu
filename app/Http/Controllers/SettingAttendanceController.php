<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use App\Services\UserService;
use App\Services\WorkplaceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SettingAttendanceController extends Controller
{
    private AttendanceService $attendanceService;

    private WorkplaceService $workplaceService;

    private UserService $userService;

    public function __construct(AttendanceService $attendanceService, WorkplaceService $workplaceService, UserService $userService)
    {
        $this->attendanceService = $attendanceService;
        $this->workplaceService = $workplaceService;
        $this->userService = $userService;
    }

    /**
     * マスター（権限1）のみ true
     */
    private function isMasterUser(Request $request): bool
    {
        $uid = (int) $request->session()->get('login_user_id');
        if ($uid <= 0) {
            return false;
        }

        $user = $this->userService->GetUser($uid);

        return $user && (int) $user->permission === 1;
    }

    /**
     * マスター以外は勤怠管理へリダイレクト
     */
    private function redirectUnlessMaster(Request $request): ?\Illuminate\Http\RedirectResponse
    {
        if (! $this->isMasterUser($request)) {
            return redirect()->route('setting.attendance.manage')
                ->with('status', '勤怠の初期時間の設定は管理者（権限1）のみ利用できます。');
        }

        return null;
    }

    private function redirectUnlessMasterForAttendanceReport(Request $request): ?\Illuminate\Http\RedirectResponse
    {
        if (! $this->isMasterUser($request)) {
            return redirect()->route('setting.attendance.manage')
                ->with('status', '月次勤怠表/個人別集計は管理者（権限1）のみ利用できます。');
        }

        return null;
    }

    private function timeToHmForInput(?string $time): string
    {
        $s = (string) ($time ?? '');
        if (preg_match('/^(\d{1,2}):(\d{2})/', $s, $m) === 1) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return '08:00';
    }

    /**
     * 設定 => 勤怠管理 トップ画面
     */
    public function manage(Request $request)
    {
        $workplaceList = $this->workplaceService->getWorkplaceList(true);
        if ($workplaceList === false || $workplaceList === null) $workplaceList = [];

        return view('setting.attendance.manage')->with([
            'workplace_list' => $workplaceList,
            'selected_workplace_id' => $request->query('workplace_id'),
            'work_date' => $request->query('work_date') ?: defaultWorkDate(),
            'result' => session('result'),
            'can_edit_attendance_defaults' => $this->isMasterUser($request),
            'can_use_attendance_reports' => $this->isMasterUser($request),
        ]);
    }

    /**
     * 設定 => 勤怠管理 => 登録/編集 選択画面
     */
    public function edit(Request $request)
    {
        $mode = $request->input('mode');
        $workplaceId = $request->input('workplace_id');
        $workDate = $request->input('work_date') ?: defaultWorkDate();

        if (!$mode || !$workplaceId) {
            return redirect()->route('setting.attendance.manage');
        }

        $workplaceList = $this->workplaceService->getWorkplaceList(true);
        if ($workplaceList === false || $workplaceList === null) $workplaceList = [];

        $assignedStaffList = [];
        if ($mode === 'update') {
            $assignedStaffList = $this->attendanceService->GetAttendanceAllStaff($workplaceId, $workDate);
            if ($assignedStaffList === false || $assignedStaffList === null) $assignedStaffList = [];
        }

        return view('setting.attendance.edit')->with([
            'mode' => $mode,
            'workplace_list' => $workplaceList,
            'selected_workplace_id' => $workplaceId,
            'work_date' => $workDate,
            'assigned_staff_list' => $assignedStaffList,
            'result' => null,
        ]);
    }

    /**
     * 設定 => 勤怠管理 => 勤怠入力フォーム（create/update）
     */
    public function input(Request $request)
    {
        $mode = $request->input('mode');
        $workplaceId = $request->input('workplace_id');
        $workDate = $request->input('work_date');
        $staffId = $request->input('staff_id');

        if (!$mode || !$workplaceId || !$workDate) {
            return redirect()->route('setting.attendance.manage');
        }

        $defaults = $this->attendanceService->GetDefaults();
        if ($defaults === false || $defaults === null) $defaults = (object)[];

        if ($mode === 'create') {
            $assignedStaffList = $this->attendanceService->GetAttendanceAllStaff($workplaceId, $workDate);
            if ($assignedStaffList === false || $assignedStaffList === null) $assignedStaffList = [];

            $startDefault = trim((string) ($defaults->start_time ?? ''));
            $endDefault = trim((string) ($defaults->end_time ?? ''));
            $breakDefault = trim((string) ($defaults->break_time ?? ''));
            if ($startDefault === '') {
                $startDefault = '08:00:00';
            }
            if ($endDefault === '') {
                $endDefault = '17:00:00';
            }
            if ($breakDefault === '') {
                $breakDefault = '01:00:00';
            }

            // 休憩の初期値は「分」で画面に出し、保存時に break_time に変換する
            $breakMinutesDefault = 60;
            $breakTimeStr = $breakDefault;
            if (is_string($breakTimeStr) && $breakTimeStr !== '') {
                if (preg_match('/^(\\d{1,2}):(\\d{2})/', $breakTimeStr, $m) === 1) {
                    $breakMinutesDefault = ((int)$m[1]) * 60 + ((int)$m[2]);
                } elseif (is_numeric($breakTimeStr)) {
                    $breakMinutesDefault = (int)$breakTimeStr;
                }
            }

            return view('setting.attendance.input_create')->with([
                'mode' => 'create',
                'workplace_id' => $workplaceId,
                'work_date' => $workDate,
                'assigned_staff_list' => $assignedStaffList,
                'start_time' => $startDefault,
                'end_time' => $endDefault,
                'break_time' => $breakDefault,
                'break_minutes' => $breakMinutesDefault,
                // 添付イメージの初期状態に合わせて「欠勤者の入力」をデフォルト表示
                'absence_mode' => 1,
            ]);
        }

        if ($mode === 'update') {
            if (!$staffId) {
                return redirect()->route('setting.attendance.manage');
            }

            $attendance = $this->attendanceService->GetAttendanceStaff($staffId, $workplaceId, $workDate);
            if ($attendance === false || $attendance === null) $attendance = null;

            return view('setting.attendance.input_update')->with([
                'mode' => 'update',
                'workplace_id' => $workplaceId,
                'work_date' => $workDate,
                'staff_id' => $staffId,
                'attendance' => $attendance,
                'staff_name' => $attendance?->staff_name ?? '',
                'start_time' => $attendance?->start_time ?? ($defaults->start_time ?? ''),
                'end_time' => $attendance?->end_time ?? ($defaults->end_time ?? ''),
                'break_time' => $attendance?->break_time ?? ($defaults->break_time ?? ''),
                'absence_flg' => $attendance?->absence_flg ?? false,
            ]);
        }

        return redirect()->route('setting.attendance.manage');
    }

    /**
     * 設定 => 勤怠管理 => 登録/更新処理
     */
    public function create(Request $request)
    {
        $mode = $request->input('mode');
        $workplaceId = $request->input('workplace_id');
        $workDate = $request->input('work_date');
        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');
        $breakTime = $request->input('break_time'); // 旧UI用
        $breakMinutes = $request->input('break_minutes'); // 新UI用（分）

        $staffId = $request->input('staff_id');

        $breakEffectivelyMissing = ($breakMinutes === null || $breakMinutes === '') && ($breakTime === null || $breakTime === '');
        if (!$mode || !$workplaceId || !$workDate || $startTime === null || $endTime === null) {
            return redirect()->route('setting.attendance.manage')->with('status', false);
        }
        // 一括登録では休憩が必須。個別編集では未入力可（後段で既定値へ寄せる）
        if ($mode === 'create' && $breakEffectivelyMissing) {
            return redirect()->route('setting.attendance.manage')->with('status', false);
        }

        // 休憩は画面では「分」で受け、DBへは break_time（HH:MM 文字列）として保存する
        $breakTimeFinal = null;
        if ($breakMinutes !== null && $breakMinutes !== '') {
            $mins = max(0, (int)$breakMinutes);
            $hours = intdiv($mins, 60);
            $minutesOnly = $mins % 60;
            $breakTimeFinal = sprintf('%02d:%02d', $hours, $minutesOnly);
        } elseif ($breakTime !== null && $breakTime !== '') {
            $breakTimeFinal = (string)$breakTime;
        } elseif ($mode === 'update') {
            $defaults = $this->attendanceService->GetDefaults();
            $defBreak = is_object($defaults) ? ($defaults->break_time ?? null) : null;
            $breakTimeFinal = $defBreak !== null && (string)$defBreak !== ''
                ? (string)$defBreak
                : '01:00';
        }

        $result = false;
        if ($mode === 'create') {
            $absenceStaffList = $request->input('absenceStaffList', []);
            $result = $this->attendanceService->AttendanceCreate(
                $workplaceId,
                $workDate,
                $startTime,
                $endTime,
                $breakTimeFinal,
                $absenceStaffList
            );
        } elseif ($mode === 'update') {
            if (!$staffId) {
                return redirect()->route('setting.attendance.manage')->with('status', false);
            }

            $absenceFlg = $request->boolean('absence_flg');
            $result = $this->attendanceService->AttendanceUpdate(
                $staffId,
                $workplaceId,
                $workDate,
                (string)$startTime,
                (string)$endTime,
                (string)($breakTimeFinal ?? '01:00'),
                $absenceFlg
            );
        }

        // 成否を result へ
        $workplaceQuery = ['workplace_id' => $workplaceId, 'work_date' => $workDate];
        return redirect()->route('setting.attendance.list', $workplaceQuery)->with('status', $result ? '保存しました' : '保存に失敗しました');
    }

    /**
     * 設定 => 勤怠管理 => 一覧表示
     */
    public function list(Request $request)
    {
        $workplaceId = $request->input('workplace_id');
        $workDate = $request->input('work_date');

        if (!$workplaceId || !$workDate) {
            return redirect()->route('setting.attendance.manage');
        }

        $assignedStaffList = $this->attendanceService->GetAttendanceAllStaff($workplaceId, $workDate);
        if ($assignedStaffList === false || $assignedStaffList === null) {
            $assignedStaffList = collect();
        } else {
            $assignedStaffList = collect($assignedStaffList);
        }

        $defaults = $this->attendanceService->GetDefaults();
        if ($defaults === false || $defaults === null) {
            $defaults = (object) [];
        }

        $assignedStaffList = $this->attendanceService->withListDisplayTimes($assignedStaffList, $defaults);

        return view('setting.attendance.list')->with([
            'workplace_id' => $workplaceId,
            'work_date' => $workDate,
            'assigned_staff_list' => $assignedStaffList,
            'result' => null,
        ]);
    }

    /**
     * 設定 => 勤怠管理 => 削除（個別削除）
     */
    public function delete(Request $request)
    {
        $staffId = $request->input('staff_id');
        $workplaceId = $request->input('workplace_id');
        $workDate = $request->input('work_date');

        if (!$staffId || !$workplaceId || !$workDate) {
            return redirect()->route('setting.attendance.manage');
        }

        $ok = $this->attendanceService->AttendanceDelete($staffId, $workplaceId, $workDate);

        return redirect()->route('setting.attendance.list', [
            'workplace_id' => $workplaceId,
            'work_date' => $workDate,
        ])->with('status', $ok ? '削除しました' : '削除に失敗しました');
    }

    /**
     * 欠勤者管理 => 作業日選択
     */
    public function absenceWorkdate()
    {
        $workDate = request()->query('work_date');

        return view('setting.attendance.absence_workdate')->with([
            'default_date' => $workDate ?: defaultWorkDate(),
        ]);
    }

    /**
     * 欠勤者管理 => 欠勤者一覧
     */
    public function absenceInputStaff(Request $request)
    {
        $workDate = $request->input('work_date');
        if (! $workDate) {
            return view('setting.attendance.absence_workdate')->with([
                'default_date' => defaultWorkDate(),
                'needs_work_date' => true,
            ]);
        }

        $staffList = $this->attendanceService->GetAbsenceStaffList($workDate);
        if ($staffList === false || $staffList === null) $staffList = [];

        return view('setting.attendance.absence_staff')->with([
            'work_date' => $workDate,
            'staff_list' => $staffList,
        ]);
    }

    /**
     * 欠勤者管理 => 更新処理
     */
    public function absenceUpdate(Request $request)
    {
        $workDate = $request->input('work_date');
        $staffList = $request->input('staff_list', []);

        if (! $workDate) {
            return view('setting.attendance.absence_workdate')->with([
                'default_date' => defaultWorkDate(),
                'needs_work_date' => true,
            ]);
        }

        $saveOutcome = $this->attendanceService->UpdateAbsence($workDate, $staffList);

        return view('setting.attendance.absence_workdate')->with([
            'default_date' => $workDate,
            'save_outcome' => $saveOutcome,
        ]);
    }

    /**
     * 個人別集計（月次）
     */
    public function personalSummary(Request $request)
    {
        if ($redirect = $this->redirectUnlessMasterForAttendanceReport($request)) {
            return $redirect;
        }

        $workDate = $request->query('work_date') ?: defaultWorkDate();
        $staffType = $request->query('staff_type');

        $summary = $this->attendanceService->GetPersonalMonthlySummary($workDate, null, $staffType);
        if ($summary === false) {
            return view('setting.attendance.personal_summary')->with([
                'work_date' => $workDate,
                'selected_staff_type' => $staffType,
                'staff_list' => [],
                'date_list' => [],
                'summary_list' => [],
                'status' => '集計データの取得に失敗しました。',
            ]);
        }

        return view('setting.attendance.personal_summary')->with([
            'work_date' => $workDate,
            'selected_staff_type' => $staffType,
            'staff_list' => $summary['staff_list'] ?? [],
            'date_list' => $summary['date_list'] ?? [],
            'summary_list' => $summary['summary_list'] ?? [],
            'status' => null,
        ]);
    }

    /**
     * 個人別集計 PDF（A4縦・3名/ページ）
     */
    public function personalSummaryPdf(Request $request)
    {
        if ($redirect = $this->redirectUnlessMasterForAttendanceReport($request)) {
            return $redirect;
        }

        $workDate = $request->query('work_date') ?: defaultWorkDate();
        $staffType = $request->query('staff_type');

        $summary = $this->attendanceService->GetPersonalMonthlySummary($workDate, null, $staffType);
        if ($summary === false || empty($summary['summary_list'])) {
            return redirect()
                ->route('setting.attendance.personal.summary', array_filter([
                    'work_date' => $workDate,
                    'staff_type' => $staffType,
                ], fn ($v) => $v !== null && $v !== ''))
                ->with('status', 'PDF出力できる集計データがありません。');
        }

        $pages = array_chunk($summary['summary_list'], 3);

        $pdf = Pdf::loadView('pdf.personal_summary', [
            'pages' => $pages,
            'date_list' => $summary['date_list'] ?? [],
            'period_label' => $this->personalSummaryPeriodLabel($workDate),
            'company_name' => '中塚建設株式会社',
        ])->setPaper('a4', 'portrait');

        $ym = date('Ym', strtotime($workDate));

        return $pdf->download('個人別集計_'.$ym.'.pdf');
    }

    private function personalSummaryPeriodLabel(string $workDate): string
    {
        $t = strtotime($workDate) ?: time();
        $year = (int) date('Y', $t);
        $month = (int) date('n', $t);

        if ($year > 2019 || ($year === 2019 && $month >= 5)) {
            return sprintf('令和%d年%d月分', $year - 2018, $month);
        }

        return sprintf('%d年%d月分', $year, $month);
    }

    /**
     * 勤怠一括入力などで使う初期出退勤・休憩（m_attendance_defaults）の編集（マスターのみ）
     */
    public function attendanceDefaults(Request $request)
    {
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
        }

        $settingsRow = $this->attendanceService->getAttendanceDefaultsSettingsRow();
        $defaults = $settingsRow ?? $this->attendanceService->GetDefaults();
        $breakMinutes = $this->attendanceService->breakStoreValueToMinutes($defaults->break_time ?? null);
        $startDisplay = $this->timeToHmForInput($defaults->start_time ?? null);
        $endDisplay = $this->timeToHmForInput($defaults->end_time ?? null);
        $isEnabled = $settingsRow
            ? (bool) $settingsRow->is_enabled
            : true;

        return view('setting.attendance.defaults')->with([
            'start_display' => $startDisplay,
            'end_display' => $endDisplay,
            'break_minutes' => $breakMinutes,
            'is_enabled' => $isEnabled,
        ]);
    }

    public function attendanceDefaultsSubmit(Request $request)
    {
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
        }

        $validated = $request->validate([
            'start_time' => 'required|string|max:16',
            'end_time' => 'required|string|max:16',
            'break_minutes' => 'required|integer|min:0|max:1440',
        ]);

        $isEnabled = (string) $request->input('is_enabled') === '1';

        $ok = $this->attendanceService->saveAttendanceDefaults(
            $validated['start_time'],
            $validated['end_time'],
            (int) $validated['break_minutes'],
            $isEnabled
        );

        if (! $ok) {
            return redirect()->route('setting.attendance.defaults')
                ->withInput()
                ->with('status', '保存に失敗しました。');
        }

        return redirect()->route('setting.attendance.defaults')
            ->with('status', '保存しました。');
    }
}

