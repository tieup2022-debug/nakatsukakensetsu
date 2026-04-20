<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AssignmentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class MonthlyAssignmentController extends Controller
{
    private AssignmentService $assignmentService;

    public function __construct(AssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    /**
     * 月次配置表 出力フォーム
     */
    public function form(Request $request)
    {
        $workDate = $request->query('work_date') ?: date('Y-m-d');

        return view('setting.assignment.monthly_form')->with([
            'work_date' => $workDate,
        ]);
    }

    /**
     * 日次ベースの配置PDFを出力（旧ロジックに合わせて1日分）
     *
     * ※旧システムの getPdf は「指定日の全現場＋欠勤者」を1つのPDFにまとめる仕様。
     * ここでも同じ仕様で実装します。
     */
    public function download(Request $request)
    {
        $workDate = $request->input('work_date') ?: date('Y-m-d');

        // DomPDF + 日本語フォントでメモリ不足になりやすいため、出力時のみ上限を引き上げる
        @ini_set('memory_limit', '512M');

        $viewData = $this->assignmentService->getPdf($workDate);
        if ($viewData === false || empty($viewData['pdf_data_list'] ?? [])) {
            return redirect()
                ->route('setting.assignment.monthly.form', ['work_date' => $workDate])
                ->with('status', '出力できる配置データがありません。');
        }

        $pdf = Pdf::loadView('pdf.assignment_all', $viewData)->setPaper('A4', 'landscape');
        $fileName = '配置一覧_' . date('Ymd', strtotime($workDate)) . '.pdf';

        return $pdf->download($fileName);
    }
}

