<?php

namespace App\Http\Controllers;

use App\Services\StaffService;
use Illuminate\Http\Request;

class SettingStaffController extends Controller
{
    private StaffService $staffService;

    public function __construct(StaffService $staffService)
    {
        $this->staffService = $staffService;
    }

    public function manage()
    {
        return view('setting.staff.manage');
    }

    public function showCreate()
    {
        return view('setting.staff.create')->with(['result' => null]);
    }

    public function create(Request $request)
    {
        $result = null;

        $staffName = $request->input('staff_name');
        $staffType = $request->input('staff_type');

        if ($request->isMethod('POST')) {
            $result = $this->staffService->create($staffName, $staffType);
        }

        return view('setting.staff.create')->with(['result' => $result]);
    }

    public function list()
    {
        $staff_list = $this->staffService->GetStaffList();
        if ($staff_list === false || $staff_list === null) {
            $staff_list = [];
        }

        return view('setting.staff.list')->with([
            'staff_list' => $staff_list,
            'result' => null,
        ]);
    }

    public function sort(Request $request)
    {
        $sortNumberList = $request->input('sort_number');
        if ($sortNumberList) {
            $this->staffService->Sort($sortNumberList);
        }

        return redirect()->route('setting.staff.list')->with('status', '並び順を保存しました');
    }

    public function showUpdate(Request $request)
    {
        $staffId = $request->query('staff_id');
        $staffData = $this->staffService->GetStaff($staffId);

        if (!$staffData) {
            return redirect()->route('setting.staff.list')->with('status', '対象データが見つかりません');
        }

        return view('setting.staff.update')->with([
            'staff_data' => $staffData,
            'result' => null,
        ]);
    }

    public function update(Request $request)
    {
        $result = null;

        $staffId = $request->input('staff_id');
        $staffName = $request->input('staff_name');
        $staffType = $request->input('staff_type');

        if ($request->isMethod('POST')) {
            $result = $this->staffService->update($staffId, $staffName, $staffType);
        }

        return redirect()->route('setting.staff.list')->with('status', $result ? '更新しました' : '更新に失敗しました');
    }

    public function delete(Request $request)
    {
        $staffId = $request->input('staff_id');
        $result = $this->staffService->delete($staffId);

        return redirect()->route('setting.staff.list')->with('status', $result ? '削除しました' : '削除に失敗しました');
    }
}

