<?php

namespace App\Http\Controllers;

use App\Services\AssignmentService;
use App\Services\NewsService;
use App\Services\UserService;
use App\Services\WorkplaceService;
use App\Support\UserPermission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

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

    public function index(Request $request, UserService $userService)
    {
        if (! session()->has('login_user_id')) {
            return redirect()->route('login');
        }

        $canAccessAssignmentSettings = false;
        $uid = (int) $request->session()->get('login_user_id');
        if ($uid > 0) {
            $user = $userService->GetUser($uid);
            $canAccessAssignmentSettings = $user && UserPermission::isManager($user->permission ?? null);
        }

        // 従来表示を初期表示として残し、view=board の場合だけ2週間ボードを表示する。
        // PDF/ブラウザ出力は従来の1日単位処理をそのまま利用する。
        if ($request->input('view') === 'board' && ! $request->has('output_preview') && ! $request->has('output_pdf')) {
            $startDate = $this->resolveBoardStartDate(
                (string) ($request->input('start_date') ?: $request->input('work_date') ?: defaultWorkDate())
            );
            $boardData = $this->assignmentService->getBoardData($startDate, 14);

            return view('top.assignment_board', [
                'boardData' => $boardData ?: [
                    'start_date' => $startDate,
                    'end_date' => Carbon::parse($startDate)->addDays(13)->toDateString(),
                    'days' => 14,
                    'workplaces' => [],
                    'staff' => [],
                    'assignments' => [],
                    'absences' => [],
                    'machines' => [],
                    'revision' => '',
                    'refreshed_at' => now()->toIso8601String(),
                ],
                'canAccessAssignmentSettings' => (bool) $canAccessAssignmentSettings,
                'boardDataUrl' => route('top.assignment.board.data'),
                'boardPlaceUrl' => route('top.assignment.board.place'),
                'boardRemoveUrl' => route('top.assignment.board.remove'),
                'boardCopyDayUrl' => route('top.assignment.board.copy-day'),
                'boardBaseUrl' => route('top.assignment', ['view' => 'board']),
                'legacyAssignmentUrl' => route('top.assignment', ['work_date' => $startDate]),
                'machineScheduleUrl' => route('top.machine.schedule', [
                    'start_date' => $startDate,
                    'range' => '2w',
                ]),
            ]);
        }

        $workplaceId = $request->input('workplace_id');
        if ($workplaceId === '' || $workplaceId === null) {
            $workplaceId = null;
        }
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

        // ブラウザ表示（PDF と同じレイアウト・同一データ）
        if ($request->has('output_preview')) {
            $viewData = $this->assignmentService->getPdf($resolvedWorkDate);
            if ($viewData === false || empty($viewData['pdf_data_list'] ?? [])) {
                return redirect()
                    ->route('top.assignment', [
                        'workplace_id' => $resolvedWorkplaceId,
                        'work_date' => $resolvedWorkDate,
                    ])
                    ->with('status', '表示できる配置データがありません。');
            }

            $viewData['web_preview'] = true;
            $viewData['assignment_list_url'] = route('top.assignment', [
                'workplace_id' => $resolvedWorkplaceId,
                'work_date' => $resolvedWorkDate,
            ]);
            $viewData['assignment_pdf_url'] = route('top.assignment', [
                'workplace_id' => $resolvedWorkplaceId,
                'work_date' => $resolvedWorkDate,
                'output_pdf' => 1,
            ]);

            return response()->view('pdf.assignment_all', $viewData);
        }

        // PDF出力（旧システムと同じ「指定日の全現場＋欠勤予定者」レイアウト）
        if ($request->has('output_pdf')) {
            // DomPDF + 日本語フォント subsetting は数十秒・数百MB 規模になりがちなので、
            // 出力時のみメモリ/実行時間を引き上げ、途中で切断されても最後まで処理させる
            @ini_set('memory_limit', '512M');
            @set_time_limit(180);
            @ignore_user_abort(true);

            $pdfStartedAt = microtime(true);
            $pages = 0;

            try {
                $viewData = $this->assignmentService->getPdf($resolvedWorkDate);
                if ($viewData === false || empty($viewData['pdf_data_list'] ?? [])) {
                    return redirect()
                        ->route('top.assignment', [
                            'workplace_id' => $resolvedWorkplaceId,
                            'work_date' => $resolvedWorkDate,
                        ])
                        ->with('status', 'PDF出力できる配置データがありません。');
                }

                $dataFetchedAt = microtime(true);
                // pdf_data_list には欠勤情報も同居しているのでページ数からは除外する
                $pages = max(0, count($viewData['pdf_data_list']) - 1);

                $pdf = Pdf::loadView('pdf.assignment_all', $viewData)->setPaper('A4', 'landscape');
                $fileName = "配置一覧_{$resolvedWorkDate}.pdf";
                $response = $pdf->download($fileName);

                Log::info('assignment.pdf generated', [
                    'work_date' => $resolvedWorkDate,
                    'pages' => $pages,
                    'data_fetch_sec' => round($dataFetchedAt - $pdfStartedAt, 3),
                    'render_sec' => round(microtime(true) - $dataFetchedAt, 3),
                    'total_sec' => round(microtime(true) - $pdfStartedAt, 3),
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
                ]);

                return $response;
            } catch (\Throwable $e) {
                Log::error('assignment.pdf failed', [
                    'work_date' => $resolvedWorkDate,
                    'pages' => $pages,
                    'elapsed_sec' => round(microtime(true) - $pdfStartedAt, 3),
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
                    'message' => $e->getMessage(),
                    'trace_top' => array_slice(explode("\n", $e->getTraceAsString()), 0, 6),
                ]);

                return redirect()
                    ->route('top.assignment', [
                        'workplace_id' => $resolvedWorkplaceId,
                        'work_date' => $resolvedWorkDate,
                    ])
                    ->with('status', 'PDF出力に失敗しました。少し時間をおいて再度お試しください（解消しない場合は管理者へご連絡ください）。');
            }
        }

        // getAssignment で既に同一条件の一覧を取っている場合は再クエリしない（重複 SQL が最大のボトルネックだった）
        $reuseLists = is_array($assignmentData)
            && (string) ($assignmentData['workplace_id'] ?? '') === (string) $resolvedWorkplaceId
            && (string) ($assignmentData['work_date'] ?? '') === (string) $resolvedWorkDate;

        if ($reuseLists) {
            $staffListFirst = $assignmentData['staff_list_first'] ?? [];
            $staffListSecond = $assignmentData['staff_list_second'] ?? [];
            $staffListThird = $assignmentData['staff_list_third'] ?? [];
            $vehicleList = $assignmentData['vehicle_list'] ?? [];
            $equipmentList = $assignmentData['equipment_list'] ?? [];
        } else {
            $staffListFirst = $this->assignmentService->GetStaffList(1, $resolvedWorkplaceId, $resolvedWorkDate, true);
            $staffListSecond = $this->assignmentService->GetStaffList(2, $resolvedWorkplaceId, $resolvedWorkDate, true);
            $staffListThird = $this->assignmentService->GetStaffList(3, $resolvedWorkplaceId, $resolvedWorkDate, true);
            $vehicleList = $this->assignmentService->GetVehicleList($resolvedWorkplaceId, $resolvedWorkDate, true);
            $equipmentList = $this->assignmentService->GetEquipmentList($resolvedWorkplaceId, $resolvedWorkDate, true);
        }

        if ($staffListFirst === false || $staffListFirst === null) {
            $staffListFirst = [];
        }
        if ($staffListSecond === false || $staffListSecond === null) {
            $staffListSecond = [];
        }
        if ($staffListThird === false || $staffListThird === null) {
            $staffListThird = [];
        }
        if ($vehicleList === false || $vehicleList === null) {
            $vehicleList = [];
        }
        if ($equipmentList === false || $equipmentList === null) {
            $equipmentList = [];
        }

        $previousDate = $this->assignmentService->CheckAssignmentPreviousDate($resolvedWorkplaceId, $resolvedWorkDate);

        $news = $this->newsService->GetNews();

        $newsBodyHtml = '';
        if ($news && isset($news->news)) {
            $newsBodyHtml = $this->newsService->formatNewsHtmlWithDiffSinceDayStart((string) $news->news);
        }

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
            'news_body_html' => $newsBodyHtml,
            'canAccessAssignmentSettings' => (bool) $canAccessAssignmentSettings,
            'result' => null,
        ]);
    }

    public function update(Request $request)
    {
        if (! session()->has('login_user_id')) {
            return redirect()->route('login');
        }

        $workplaceId = $request->input('workplace_id');
        $workDate = $request->input('work_date');

        $staffFirst = $this->normalizeIntMap($request->input('staff_list_first', []));
        $staffSecond = $this->normalizeIntMap($request->input('staff_list_second', []));
        $staffThird = $this->normalizeIntMap($request->input('staff_list_third', []));

        $vehicleList = $this->normalizeIntMap($request->input('vehicle_list', []));
        $equipmentList = $this->normalizeIntMap($request->input('equipment_list', []));

        $staffList = $staffFirst + $staffSecond + $staffThird;

        $ok = $this->assignmentService->AssignmentUpdate($workplaceId, $workDate, $staffList, $vehicleList, $equipmentList);

        return redirect()
            ->route('top.assignment', ['workplace_id' => $workplaceId, 'work_date' => $workDate])
            ->with('status', $ok ? '配置一覧を保存しました' : '保存に失敗しました。同日の重複配置・勤怠、欠勤予定をご確認ください。');
    }

    public function copy(Request $request)
    {
        if (! session()->has('login_user_id')) {
            return redirect()->route('login');
        }

        $workplaceId = $request->input('workplace_id');
        $workDate = $request->input('work_date');

        if (! $workplaceId || ! $workDate) {
            return redirect()->route('top.assignment');
        }

        $previousDate = $this->assignmentService->CheckAssignmentPreviousDate($workplaceId, $workDate);
        if (! $previousDate) {
            return redirect()
                ->route('top.assignment', ['workplace_id' => $workplaceId, 'work_date' => $workDate])
                ->with('status', 'コピー元となる前日の配置が見つかりませんでした。');
        }

        $ok = $this->assignmentService->CopyAssignment($workplaceId, $workDate, $previousDate);

        return redirect()
            ->route('top.assignment', ['workplace_id' => $workplaceId, 'work_date' => $workDate])
            ->with('status', $ok ? '前日の配置をコピーしました' : 'コピーに失敗しました（欠勤や他現場との重複をご確認ください）');
    }

    public function boardData(Request $request)
    {
        if (! session()->has('login_user_id')) {
            return response()->json(['message' => 'ログインが必要です。'], 401);
        }

        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
        ]);
        $startDate = $this->resolveBoardStartDate($validated['start_date']);
        $boardData = $this->assignmentService->getBoardData($startDate, 14);

        if ($boardData === false) {
            return response()->json(['message' => '配置データを取得できませんでした。'], 500);
        }

        return response()->json($boardData);
    }

    public function boardPlace(Request $request)
    {
        if (! session()->has('login_user_id')) {
            return response()->json(['message' => 'ログインが必要です。'], 401);
        }

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'min:1'],
            'workplace_id' => ['required', 'integer', 'min:1'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'start_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $result = $this->assignmentService->placeStaffOnBoard(
            (int) $validated['staff_id'],
            (int) $validated['workplace_id'],
            $validated['work_date']
        );

        return $this->boardMutationResponse($result, $validated['start_date']);
    }

    public function boardRemove(Request $request)
    {
        if (! session()->has('login_user_id')) {
            return response()->json(['message' => 'ログインが必要です。'], 401);
        }

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'min:1'],
            'workplace_id' => ['required', 'integer', 'min:1'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'start_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $result = $this->assignmentService->removeStaffFromBoard(
            (int) $validated['staff_id'],
            (int) $validated['workplace_id'],
            $validated['work_date']
        );

        return $this->boardMutationResponse($result, $validated['start_date']);
    }

    public function boardCopyDay(Request $request)
    {
        if (! session()->has('login_user_id')) {
            return response()->json(['message' => 'ログインが必要です。'], 401);
        }

        $validated = $request->validate([
            'work_date' => ['required', 'date_format:Y-m-d'],
            'start_date' => ['required', 'date_format:Y-m-d'],
        ]);
        $result = $this->assignmentService->copyPreviousBoardDay($validated['work_date']);

        return $this->boardMutationResponse($result, $validated['start_date']);
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

    private function resolveBoardStartDate(string $date): string
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $date)
                ->startOfWeek(Carbon::MONDAY)
                ->toDateString();
        } catch (\Throwable) {
            return Carbon::parse(defaultWorkDate())
                ->startOfWeek(Carbon::MONDAY)
                ->toDateString();
        }
    }

    /** @param array{ok: bool, message: string} $result */
    private function boardMutationResponse(array $result, string $startDate)
    {
        if (! ($result['ok'] ?? false)) {
            return response()->json(['message' => $result['message'] ?? '処理に失敗しました。'], 409);
        }

        $boardData = $this->assignmentService->getBoardData($this->resolveBoardStartDate($startDate), 14);

        if ($boardData === false) {
            return response()->json(['message' => '保存後の最新配置を取得できませんでした。画面を再読み込みしてください。'], 500);
        }

        return response()->json([
            'message' => $result['message'] ?? '保存しました。',
            'board' => $boardData,
        ]);
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
