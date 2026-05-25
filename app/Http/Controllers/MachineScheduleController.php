<?php

namespace App\Http\Controllers;

use App\Services\MachineScheduleService;
use Illuminate\Http\Request;

/**
 * 機械（車両・重機）配置予定表（ガント形式）
 *
 * - GET  /top/machine-schedule         閲覧
 * - POST /top/machine-schedule/place   期間配置（指定機械を期間にわたって現場に配置）
 * - POST /top/machine-schedule/clear   期間クリア（指定機械の期間配置を解除）
 */
class MachineScheduleController extends Controller
{
    private MachineScheduleService $service;

    public function __construct(MachineScheduleService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        if (! session()->has('login_user_id')) {
            return redirect()->route('login');
        }

        $presets = MachineScheduleService::rangePresets();
        $rangeKey = (string) $request->query('range', '1m');
        if (! isset($presets[$rangeKey])) {
            $rangeKey = '1m';
        }
        $days = $presets[$rangeKey]['days'];

        $startDate = (string) $request->query('start_date', '');
        if ($startDate === '' || strtotime($startDate) === false) {
            // 既定: 今日の前日の月曜（直近月曜）から
            $startDate = $this->defaultStartDate();
        }

        $endDate = date('Y-m-d', strtotime($startDate . ' +' . ($days - 1) . ' days'));

        $vehicleTypeParam = $request->query('vehicle_type', 'all');
        $vehicleType = null;
        if ($vehicleTypeParam === '1' || $vehicleTypeParam === '2') {
            $vehicleType = (int) $vehicleTypeParam;
        }

        $matrix = $this->service->getMatrix($startDate, $endDate, $vehicleType);

        // 前後ナビ
        $prevStart = date('Y-m-d', strtotime($startDate . ' -' . $days . ' days'));
        $nextStart = date('Y-m-d', strtotime($startDate . ' +' . $days . ' days'));

        return view('top.machine_schedule')->with([
            'matrix' => $matrix,
            'presets' => $presets,
            'range_key' => $rangeKey,
            'vehicle_type_param' => $vehicleTypeParam,
            'prev_start' => $prevStart,
            'next_start' => $nextStart,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    public function place(Request $request)
    {
        if (! session()->has('login_user_id')) {
            return redirect()->route('login');
        }

        $masterId = (int) $request->input('master_id');
        $masterType = (int) $request->input('master_type');
        $workplaceId = (int) $request->input('workplace_id');
        $startDate = (string) $request->input('start_date');
        $endDate = (string) $request->input('end_date');
        $overwrite = (bool) $request->boolean('overwrite');

        if ($masterId <= 0 || $masterType <= 0 || $workplaceId <= 0 || ! $startDate || ! $endDate) {
            return $this->backToIndex($request, '入力に不足があります');
        }
        if (strtotime($endDate) < strtotime($startDate)) {
            return $this->backToIndex($request, '終了日が開始日より前になっています');
        }

        $result = $this->service->placeRange($masterId, $masterType, $workplaceId, $startDate, $endDate, $overwrite);

        $msg = $result['ok']
            ? sprintf('配置を保存しました（%d 日分）', $result['written'])
            : '保存に失敗しました';
        if (! empty($result['skipped'])) {
            $skippedDates = array_map(fn ($s) => $s['date'], $result['skipped']);
            $msg .= sprintf(' / 他現場に配置済みのためスキップ: %s', implode(', ', $skippedDates));
        }

        return $this->backToIndex($request, $msg);
    }

    public function clear(Request $request)
    {
        if (! session()->has('login_user_id')) {
            return redirect()->route('login');
        }

        $masterId = (int) $request->input('master_id');
        $masterType = (int) $request->input('master_type');
        $startDate = (string) $request->input('start_date');
        $endDate = (string) $request->input('end_date');

        if ($masterId <= 0 || $masterType <= 0 || ! $startDate || ! $endDate) {
            return $this->backToIndex($request, '入力に不足があります');
        }
        if (strtotime($endDate) < strtotime($startDate)) {
            return $this->backToIndex($request, '終了日が開始日より前になっています');
        }

        $deleted = $this->service->clearRange($masterId, $masterType, $startDate, $endDate);

        return $this->backToIndex($request, sprintf('配置をクリアしました（%d 日分）', $deleted));
    }

    private function backToIndex(Request $request, string $status)
    {
        return redirect()->route('top.machine.schedule', [
            'start_date' => $request->input('view_start_date'),
            'range' => $request->input('view_range'),
            'vehicle_type' => $request->input('view_vehicle_type'),
        ])->with('status', $status);
    }

    private function defaultStartDate(): string
    {
        $today = date('Y-m-d');
        $w = (int) date('w', strtotime($today));
        // 月曜=1。直近の月曜（今日が月曜ならそのまま）
        $offset = $w === 0 ? -6 : -($w - 1);
        return date('Y-m-d', strtotime($today . ' ' . ($offset >= 0 ? '+' : '') . $offset . ' days'));
    }
}
