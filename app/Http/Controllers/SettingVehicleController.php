<?php

namespace App\Http\Controllers;

use App\Services\VehicleService;
use Illuminate\Http\Request;

class SettingVehicleController extends Controller
{
    private VehicleService $vehicleService;

    public function __construct(VehicleService $vehicleService)
    {
        $this->vehicleService = $vehicleService;
    }

    public function manage()
    {
        return view('setting.vehicle.manage');
    }

    public function showCreate()
    {
        return view('setting.vehicle.create')->with(['result' => null]);
    }

    public function create(Request $request)
    {
        $result = null;

        if ($request->isMethod('POST')) {
            $result = $this->vehicleService->create($request->input('vehicle_name'));
        }

        return view('setting.vehicle.create')->with(['result' => $result]);
    }

    public function list()
    {
        $vehicle_list = $this->vehicleService->GetVehicleList();
        if ($vehicle_list === false || $vehicle_list === null) $vehicle_list = [];

        return view('setting.vehicle.list')->with([
            'vehicle_list' => $vehicle_list,
            'result' => null,
        ]);
    }

    public function sort(Request $request)
    {
        $sortNumberList = $request->input('sort_number');
        if ($sortNumberList) {
            $this->vehicleService->Sort($sortNumberList);
        }

        return redirect()->route('setting.vehicle.list')->with('status', '並び順を保存しました');
    }

    public function showUpdate(Request $request)
    {
        $vehicleId = $request->query('vehicle_id');
        $vehicleData = $this->vehicleService->GetVehicle($vehicleId);

        if (!$vehicleData) {
            return redirect()->route('setting.vehicle.list')->with('status', '対象データが見つかりません');
        }

        return view('setting.vehicle.update')->with([
            'vehicle_data' => $vehicleData,
            'result' => null,
        ]);
    }

    public function update(Request $request)
    {
        $result = null;
        $vehicleId = $request->input('vehicle_id');

        if ($request->isMethod('POST')) {
            $result = $this->vehicleService->update($vehicleId, $request->input('vehicle_name'));
        }

        return redirect()->route('setting.vehicle.list')->with('status', $result ? '更新しました' : '更新に失敗しました');
    }

    public function delete(Request $request)
    {
        $result = $this->vehicleService->delete($request->input('vehicle_id'));

        return redirect()->route('setting.vehicle.list')->with('status', $result ? '削除しました' : '削除に失敗しました');
    }
}

