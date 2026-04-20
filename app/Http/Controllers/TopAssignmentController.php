<?php

namespace App\Http\Controllers;

use App\Services\AssignmentService;
use App\Services\NewsService;
use App\Services\WorkplaceService;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class TopAssignmentController extends Controller
{
    private AssignmentService $assignmentService;
    private WorkplaceService $workplaceService;
    private NewsService $newsService;

    public function __construct(AssignmentService $assignmentService, WorkplaceService $workplaceService, NewsService $newsService)
    {
        $this->assignmentService = $assignmentService;
        $this->workplaceService = $workplaceService;
        $this->newsService = $newsService;
    }

    public function index(Request $request)
    {
        if (!session()->has('login_user_id')) {
            return redirect()->route('login');
        }

        $workplaceId = $request->input('workplace_id');
        $workDate = $request->input('work_date');

        $workplaceList = $this->workplaceService->getWorkplaceList(true);
        if ($workplaceList === false || $workplaceList === null) {
            $workplaceList = [];
        }

        // デフォルト決定（旧挙動を踏襲）
        $assignmentData = $this->assignmentService->getAssignment($workplaceId, $workDate);
        $resolvedWorkplaceId = is_array($assignmentData) ? ($assignmentData['workplace_id'] ?? $workplaceId) : $workplaceId;
        $resolvedWorkDate = is_array($assignmentData) ? ($assignmentData['work_date'] ?? $workDate) : $workDate;

        if (empty($resolvedWorkplaceId)) {
            $resolvedWorkplaceId = $workplaceList[0]->id ?? null;
        }

        if (empty($resolvedWorkDate)) {
            $resolvedWorkDate = defaultWorkDate();
        }

        // PDF出力（旧システムと同じ「指定日の全現場＋欠勤予定者」レイアウト）
        if ($request->has('output_pdf')) {
            // DomPDF + 日本語フォントでメモリ不足になりやすいため、出力時のみ上限を引き上げる
            @ini_set('memory_limit', '512M');

            $viewData = $this->assignmentService->getPdf($resolvedWorkDate);
            if ($viewData === false || empty($viewData['pdf_data_list'] ?? [])) {
                return redirect()
                    ->route('top.assignment', [
                        'workplace_id' => $resolvedWorkplaceId,
                        'work_date' => $resolvedWorkDate,
                    ])
                    ->with('status', 'PDF出力できる配置データがありません。');
            }

            $pdf = Pdf::loadView('pdf.assignment_all', $viewData)->setPaper('A4', 'landscape');
            $fileName = "配置一覧_{$resolvedWorkDate}.pdf";

            return $pdf->download($fileName);
        }

        // 選択した現場・作業日に対して配置が付いているメンバー・車両・重機のみ表示（設定画面の一覧編集は従来どおり全件）
        $staffListFirst = $this->assignmentService->GetStaffList(1, $resolvedWorkplaceId, $resolvedWorkDate, true);
        $staffListSecond = $this->assignmentService->GetStaffList(2, $resolvedWorkplaceId, $resolvedWorkDate, true);
        $staffListThird = $this->assignmentService->GetStaffList(3, $resolvedWorkplaceId, $resolvedWorkDate, true);

        $vehicleList = $this->assignmentService->GetVehicleList($resolvedWorkplaceId, $resolvedWorkDate, true);
        $equipmentList = $this->assignmentService->GetEquipmentList($resolvedWorkplaceId, $resolvedWorkDate, true);

        if ($staffListFirst === false || $staffListFirst === null) $staffListFirst = [];
        if ($staffListSecond === false || $staffListSecond === null) $staffListSecond = [];
        if ($staffListThird === false || $staffListThird === null) $staffListThird = [];
        if ($vehicleList === false || $vehicleList === null) $vehicleList = [];
        if ($equipmentList === false || $equipmentList === null) $equipmentList = [];

        $previousDate = $this->assignmentService->CheckAssignmentPreviousDate($resolvedWorkplaceId, $resolvedWorkDate);

        $news = $this->newsService->GetNews();

        return view('top.assignment')->with([
            'display_date' => formatJapaneseDate($resolvedWorkDate),
            'workplace_list' => $workplaceList,
            'workplace_id' => $resolvedWorkplaceId,
            'work_date' => $resolvedWorkDate,
            'staff_list_first' => $staffListFirst,
            'staff_list_second' => $staffListSecond,
            'staff_list_third' => $staffListThird,
            'vehicle_list' => $vehicleList,
            'equipment_list' => $equipmentList,
            'previous_date' => $previousDate,
            'news' => $news,
            'result' => null,
        ]);
    }

    public function update(Request $request)
    {
        if (!session()->has('login_user_id')) {
            return redirect()->route('login');
        }

        $workplaceId = $request->input('workplace_id');
        $workDate = $request->input('work_date');

        $staffFirst = $this->normalizeIntMap($request->input('staff_list_first', []));
        $staffSecond = $this->normalizeIntMap($request->input('staff_list_second', []));
        $staffThird = $this->normalizeIntMap($request->input('staff_list_third', []));

        $vehicleList = $this->normalizeIntMap($request->input('vehicle_list', []));
        $equipmentList = $this->normalizeIntMap($request->input('equipment_list', []));

        $staffList = array_merge($staffFirst, $staffSecond, $staffThird);

        $ok = $this->assignmentService->AssignmentUpdate($workplaceId, $workDate, $staffList, $vehicleList, $equipmentList);

        return redirect()
            ->route('top.assignment', ['workplace_id' => $workplaceId, 'work_date' => $workDate])
            ->with('status', $ok ? '配置一覧を保存しました' : '保存に失敗しました（内容をご確認ください）');
    }

    public function copy(Request $request)
    {
        if (!session()->has('login_user_id')) {
            return redirect()->route('login');
        }

        $workplaceId = $request->input('workplace_id');
        $workDate = $request->input('work_date');

        if (!$workplaceId || !$workDate) {
            return redirect()->route('top.assignment');
        }

        $previousDate = $this->assignmentService->CheckAssignmentPreviousDate($workplaceId, $workDate);
        if (!$previousDate) {
            return redirect()
                ->route('top.assignment', ['workplace_id' => $workplaceId, 'work_date' => $workDate])
                ->with('status', 'コピー元となる前日の配置が見つかりませんでした。');
        }

        $ok = $this->assignmentService->CopyAssignment($workplaceId, $workDate, $previousDate);

        return redirect()
            ->route('top.assignment', ['workplace_id' => $workplaceId, 'work_date' => $workDate])
            ->with('status', $ok ? '前日の配置をコピーしました' : 'コピーに失敗しました（欠勤や他現場との重複をご確認ください）');
    }

    private function normalizeIntMap(array $map): array
    {
        $result = [];
        foreach ($map as $k => $v) {
            if (is_array($v)) {
                $v = end($v);
            }
            $result[$k] = intval($v);
        }
        return $result;
    }

    private function ensureArray($value): array
    {
        if ($value === false || $value === null) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        // DB::select returns array; Eloquent/Collection also implement count.
        return [];
    }
}

