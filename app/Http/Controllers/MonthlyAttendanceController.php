<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class MonthlyAttendanceController extends Controller
{
    private AttendanceService $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * 月次勤怠表 出力フォーム
     */
    public function form(Request $request)
    {
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
     * 月次勤怠表 PDF をブラウザ内表示（ダウンロードしない）
     */
    public function preview(Request $request)
    {
        $workDate = $request->query('work_date') ?: date('Y-m-d');

        $pdfData = $this->attendanceService->GetPdfData($workDate);
        if ($pdfData === false || empty($pdfData['attendance_table_list'])) {
            return redirect()
                ->route('setting.attendance.monthly.form', ['work_date' => $workDate])
                ->with('status', '表示できる勤怠データがありません。');
        }

        @ini_set('memory_limit', '512M');

        $pdf = Pdf::loadView('pdf.attendance_monthly', $pdfData)->setPaper('A4', 'landscape');
        $fileName = '勤怠月次_' . date('Ymd', strtotime($workDate)) . '.pdf';

        return $pdf->stream($fileName);
    }
}

