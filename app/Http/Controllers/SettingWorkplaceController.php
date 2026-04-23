<?php

namespace App\Http\Controllers;

use App\Services\WorkplaceService;
use Illuminate\Http\Request;

class SettingWorkplaceController extends Controller
{
    private WorkplaceService $workplaceService;

    public function __construct(WorkplaceService $workplaceService)
    {
        $this->workplaceService = $workplaceService;
    }

    public function manage()
    {
        return view('setting.workplace.manage');
    }

    public function showCreate()
    {
        return view('setting.workplace.create')->with(['result' => null]);
    }

    public function create(Request $request)
    {
        $result = null;
        $workplaceName = $request->input('workplace_name');

        if ($request->isMethod('POST')) {
            $result = $this->workplaceService->create($workplaceName);
        }

        return view('setting.workplace.create')->with(['result' => $result]);
    }

    public function list()
    {
        $workplace_list = $this->workplaceService->getWorkplaceList(true);
        if ($workplace_list === false || $workplace_list === null) {
            $workplace_list = [];
        }
        $ids = collect($workplace_list)->pluck('id')->filter()->all();
        $deletion_stats = $this->workplaceService->getDeletionImpactCounts($ids);

        return view('setting.workplace.list')->with([
            'workplace_list' => $workplace_list,
            'deletion_stats' => $deletion_stats,
            'mode' => 'active',
            'result' => null,
        ]);
    }

    public function listCompleted()
    {
        $workplace_list = $this->workplaceService->getWorkplaceList(false);
        if ($workplace_list === false || $workplace_list === null) {
            $workplace_list = [];
        }
        $ids = collect($workplace_list)->pluck('id')->filter()->all();
        $deletion_stats = $this->workplaceService->getDeletionImpactCounts($ids);

        return view('setting.workplace.list')->with([
            'workplace_list' => $workplace_list,
            'deletion_stats' => $deletion_stats,
            'mode' => 'completed',
            'result' => null,
        ]);
    }

    public function showUpdate(Request $request)
    {
        $workplaceId = $request->query('workplace_id');
        $workplaceData = $this->workplaceService->getWorkplace($workplaceId);

        if (! $workplaceData) {
            return redirect()->route('setting.workplace.list')->with('status', '対象データが見つかりません');
        }

        return view('setting.workplace.update')->with([
            'workplace_data' => $workplaceData,
            'result' => null,
        ]);
    }

    public function update(Request $request)
    {
        $result = null;

        $workplaceId = $request->input('workplace_id');
        $workplaceName = $request->input('workplace_name');
        $activeFlg = $request->input('active_flg');

        if ($request->isMethod('POST')) {
            // active_flg=1なら稼働中、0なら完了済み
            $result = $this->workplaceService->update($workplaceId, $workplaceName, intval($activeFlg) === 1);
        }

        return redirect()->route('setting.workplace.list')->with('status', $result ? '更新しました' : '更新に失敗しました');
    }

    public function delete(Request $request)
    {
        $workplaceId = $request->input('workplace_id');
        $result = $this->workplaceService->delete($workplaceId);

        $msg = $result
            ? '現場と紐づく勤怠・配置を非表示にしました（DB上は保持されます）'
            : '削除に失敗しました';

        return redirect()->route('setting.workplace.list')->with('status', $msg);
    }
}
