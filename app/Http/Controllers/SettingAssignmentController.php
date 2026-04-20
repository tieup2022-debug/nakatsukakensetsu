<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AssignmentService;
use App\Services\WorkplaceService;
use Illuminate\Http\Request;

class SettingAssignmentController extends Controller
{
    private AssignmentService $assignmentService;
    private WorkplaceService $workplaceService;

    public function __construct(AssignmentService $assignmentService, WorkplaceService $workplaceService)
    {
        $this->assignmentService = $assignmentService;
        $this->workplaceService = $workplaceService;
    }

    /**
     * 設定 => 配置入力 トップ
     */
    public function manage(Request $request)
    {
        $workplaceList = $this->workplaceService->getWorkplaceList(true);
        if ($workplaceList === false || $workplaceList === null) $workplaceList = [];

        return view('setting.assignment.manage')->with([
            'workplace_list' => $workplaceList,
            'selected_workplace_id' => $request->query('workplace_id'),
            'work_date' => $request->query('work_date') ?: defaultWorkDate(),
            'result' => session('result'),
        ]);
    }

    /**
     * 設定 => 配置入力 => 編集画面
     */
    public function edit(Request $request)
    {
        $workplaceId = $request->input('workplace_id');
        $workDate = $request->input('work_date') ?: defaultWorkDate();
        if (!$workplaceId) {
            return redirect()->route('setting.assignment.manage');
        }

        // 編集・新規登録では「まだ配置が無い日」でも候補（社員・車両）を出す必要があるため
        // getAssignment()（assigned=true 相当）ではなく assigned=false で一覧を取得する
        $assignmentData = [
            'workplace_id' => $workplaceId,
            'work_date' => $workDate,
            'staff_list_first' => $this->ensureList($this->assignmentService->GetStaffList(1, $workplaceId, $workDate, false)),
            'staff_list_second' => $this->ensureList($this->assignmentService->GetStaffList(2, $workplaceId, $workDate, false)),
            'staff_list_third' => $this->ensureList($this->assignmentService->GetStaffList(3, $workplaceId, $workDate, false)),
            'vehicle_list' => $this->ensureList($this->assignmentService->GetVehicleList($workplaceId, $workDate, false)),
            'equipment_list' => $this->ensureList($this->assignmentService->GetEquipmentList($workplaceId, $workDate, false)),
        ];

        $previousDate = $this->assignmentService->CheckAssignmentPreviousDate($workplaceId, $workDate);

        return view('setting.assignment.edit')->with([
            'workplace_id' => $workplaceId,
            'work_date' => $workDate,
            'assignment_data' => $assignmentData,
            'previous_date' => $previousDate,
            'result' => null,
        ]);
    }

    /**
     * 設定 => 配置入力 => 保存
     */
    public function update(Request $request)
    {
        $workplaceId = $request->input('workplace_id');
        $workDate = $request->input('work_date');

        $staffList = $request->input('staff_list', []);
        $vehicleList = $request->input('vehicle_list', []);
        $equipmentList = $request->input('equipment_list', []);

        if (!$workplaceId || !$workDate) {
            return redirect()->route('setting.assignment.manage');
        }

        $ok = $this->assignmentService->AssignmentUpdate($workplaceId, $workDate, $staffList, $vehicleList, $equipmentList);

        return redirect()->route('setting.assignment.edit', [
            'workplace_id' => $workplaceId,
            'work_date' => $workDate,
        ])->with('status', $ok ? '保存しました' : '保存に失敗しました');
    }

    /**
     * 前日コピー
     */
    public function copy(Request $request)
    {
        $workplaceId = $request->input('workplace_id');
        $workDate = $request->input('work_date');

        if (!$workplaceId || !$workDate) {
            return redirect()->route('setting.assignment.manage');
        }

        $previousDate = $request->input('previous_date');
        if (!$previousDate) {
            $previousDate = $this->assignmentService->CheckAssignmentPreviousDate($workplaceId, $workDate);
        }

        if (!$previousDate) {
            return redirect()->route('setting.assignment.edit', [
                'workplace_id' => $workplaceId,
                'work_date' => $workDate,
            ])->with('status', 'コピー元が見つかりません');
        }

        $ok = $this->assignmentService->CopyAssignment($workplaceId, $workDate, $previousDate);

        return redirect()->route('setting.assignment.edit', [
            'workplace_id' => $workplaceId,
            'work_date' => $workDate,
        ])->with('status', $ok ? 'コピーしました' : 'コピーに失敗しました');
    }

    /**
     * @param  mixed  $rows
     * @return array<int, mixed>
     */
    private function ensureList($rows): array
    {
        if ($rows === false || $rows === null) {
            return [];
        }

        return is_array($rows) ? $rows : [];
    }
}

