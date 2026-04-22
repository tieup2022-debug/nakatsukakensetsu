<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use App\Services\UserService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class MonthlyAttendanceController extends Controller
{
    private AttendanceService $attendanceService;
    private UserService $userService;

    public function __construct(AttendanceService $attendanceService, UserService $userService)
    {
        $this->attendanceService = $attendanceService;
        $this->userService = $userService;
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
            return redirect()->route('setting.attendance.manage')
                ->with('status', '月次勤怠表/個人別集計は管理者（権限1）のみ利用できます。');
        }

        return null;
    }

    /**
     * 月次勤怠表 出力フォーム
     */
    public function form(Request $request)
    {
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
        }

        $workDate = $request->query('work_date') ?: date('Y-m-d');

        return view('setting.attendance.monthly_form')->with([
            'work_date' => $workDate,
        ]);
    }

    /**
     * 月次勤怠表 PDF ダウンロード
     */
    public function download(Request $request)
    {
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
        }

        $workDate = $request->input('work_date') ?: date('Y-m-d');

        $pdfData = $this->attendanceService->GetPdfData($workDate);
        if ($pdfData === false || empty($pdfData['attendance_table_list'])) {
            return redirect()
                ->route('setting.attendance.monthly.form', ['work_date' => $workDate])
                ->with('status', '出力できる勤怠データがありません。');
        }

        $pdf = Pdf::loadView('pdf.attendance_monthly', $pdfData)->setPaper('A4', 'landscape');
        $fileName = '勤怠月次_' . date('Ymd', strtotime($workDate)) . '.pdf';

        return $pdf->download($fileName);
    }

    /**
     * 月次勤怠表を Web ページとして表示（PDF ビューアではなく HTML）
     */
    public function preview(Request $request)
    {
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
        }

        $workDate = $request->query('work_date') ?: date('Y-m-d');

        $pdfData = $this->attendanceService->GetPdfData($workDate);
        if ($pdfData === false || empty($pdfData['attendance_table_list'])) {
            return redirect()
                ->route('setting.attendance.monthly.form', ['work_date' => $workDate])
                ->with('status', '表示できる勤怠データがありません。');
        }

        $pdfData['work_date'] = $workDate;
        $pdfData['title'] = '勤怠月次一覧';

        return view('setting.attendance.monthly_web', $pdfData);
    }
}

