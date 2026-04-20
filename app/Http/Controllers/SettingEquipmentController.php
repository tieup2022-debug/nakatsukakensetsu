<?php

namespace App\Http\Controllers;

use App\Services\EquipmentService;
use Illuminate\Http\Request;

class SettingEquipmentController extends Controller
{
    private EquipmentService $equipmentService;

    public function __construct(EquipmentService $equipmentService)
    {
        $this->equipmentService = $equipmentService;
    }

    public function manage()
    {
        return view('setting.equipment.manage');
    }

    public function showCreate()
    {
        return view('setting.equipment.create')->with(['result' => null]);
    }

    public function create(Request $request)
    {
        $result = null;

        if ($request->isMethod('POST')) {
            $result = $this->equipmentService->create($request->input('vehicle_name'));
        }

        return view('setting.equipment.create')->with(['result' => $result]);
    }

    public function list()
    {
        $equipment_list = $this->equipmentService->GetEquipmentList();
        if ($equipment_list === false || $equipment_list === null) $equipment_list = [];

        return view('setting.equipment.list')->with([
            'equipment_list' => $equipment_list,
            'result' => null,
        ]);
    }

    public function sort(Request $request)
    {
        $sortNumberList = $request->input('sort_number');
        if ($sortNumberList) {
            $this->equipmentService->Sort($sortNumberList);
        }

        return redirect()->route('setting.equipment.list')->with('status', '並び順を保存しました');
    }

    public function showUpdate(Request $request)
    {
        $equipmentId = $request->query('vehicle_id');
        $equipmentData = $this->equipmentService->GetEquipment($equipmentId);

        if (!$equipmentData) {
            return redirect()->route('setting.equipment.list')->with('status', '対象データが見つかりません');
        }

        return view('setting.equipment.update')->with([
            'equipment_data' => $equipmentData,
            'result' => null,
        ]);
    }

    public function update(Request $request)
    {
        $result = null;
        $equipmentId = $request->input('vehicle_id');

        if ($request->isMethod('POST')) {
            $result = $this->equipmentService->update($equipmentId, $request->input('vehicle_name'));
        }

        return redirect()->route('setting.equipment.list')->with('status', $result ? '更新しました' : '更新に失敗しました');
    }

    public function delete(Request $request)
    {
        $result = $this->equipmentService->delete($request->input('vehicle_id'));

        return redirect()->route('setting.equipment.list')->with('status', $result ? '削除しました' : '削除に失敗しました');
    }
}

