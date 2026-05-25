<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * 機械（車両・重機）配置の「機械×日付」マトリクスを扱うサービス。
 *
 * - 取得: 指定期間の m_vehicle 一覧 × 日付 × 配置先（t_assignment）
 * - 更新: 指定期間で「この機械をこの現場に配置」を一括 upsert
 * - クリア: 指定期間で「この機械の配置を全削除」
 *
 * データモデルは既存（t_assignment / m_vehicle / m_workplace）をそのまま使う。
 *  - master_type: 2 = 車両, 3 = 重機 （config('assignments.master_type.*') 参照）
 *  - vehicle_type (m_vehicle): 1 = 車両, 2 = 重機
 */
class MachineScheduleService
{
    private const LOCAL_VEHICLE_FILE = 'app/local_vehicles.json';
    private const LOCAL_EQUIPMENT_FILE = 'app/local_equipments.json';
    private const LOCAL_WORKPLACE_FILE = 'app/local_workplaces.json';
    private const LOCAL_ASSIGNMENT_FILE = 'app/local_assignments.json';

    /**
     * 期間プリセット（ヘッダのプルダウン用）。
     *
     * @return array<string, array{label:string, days:int}>
     */
    public static function rangePresets(): array
    {
        return [
            '2w' => ['label' => '2週間', 'days' => 14],
            '1m' => ['label' => '1ヶ月', 'days' => 31],
            '6w' => ['label' => '6週間', 'days' => 42],
            '2m' => ['label' => '2ヶ月', 'days' => 62],
            '3m' => ['label' => '3ヶ月', 'days' => 92],
        ];
    }

    /**
     * 機械×日付のマトリクスを返す。
     *
     * @param  string  $startDate  Y-m-d
     * @param  string  $endDate    Y-m-d
     * @param  int|null  $vehicleType  1=車両のみ, 2=重機のみ, null=両方
     * @return array{
     *   start_date:string,
     *   end_date:string,
     *   dates:array<int,array{date:string,d:string,wd:string,is_sun:bool,is_sat:bool}>,
     *   machines:array<int,array{id:int,name:string,vehicle_type:int,master_type:int,sort:int}>,
     *   cells:array<int,array<string,array{workplace_id:int,workplace_name:string,start:bool,end:bool}>>,
     *   workplaces:array<int,array{id:int,name:string}>,
     * }
     */
    public function getMatrix(string $startDate, string $endDate, ?int $vehicleType = null): array
    {
        $dates = $this->buildDateList($startDate, $endDate);
        $machines = $this->getMachines($vehicleType);
        $workplaces = $this->getWorkplaces();
        $cells = $this->getAssignmentCells($machines, $startDate, $endDate);

        $cellsWithSpan = $this->markSpanEdges($cells, $dates);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'dates' => $dates,
            'machines' => $machines,
            'cells' => $cellsWithSpan,
            'workplaces' => $workplaces,
        ];
    }

    /**
     * 指定の機械を期間 [startDate, endDate] にわたって workplaceId に配置する。
     *
     * - 他現場に既に配置されている日は overwrite=false ならスキップ（skipped に記録）
     * - 既に同現場に配置済みの日は何もしない（更新日時のみ更新）
     * - overwrite=true は「他現場の配置を取り消して上書き」する
     *
     * @return array{ok:bool, written:int, skipped:array<int,array{date:string,reason:string}>}
     */
    public function placeRange(int $masterId, int $masterType, int $workplaceId, string $startDate, string $endDate, bool $overwrite = false): array
    {
        $masterId = (int) $masterId;
        $masterType = (int) $masterType;
        $workplaceId = (int) $workplaceId;

        if ($masterId <= 0 || $masterType <= 0 || $workplaceId <= 0) {
            return ['ok' => false, 'written' => 0, 'skipped' => []];
        }

        $dates = array_map(fn ($d) => $d['date'], $this->buildDateList($startDate, $endDate));
        if ($dates === []) {
            return ['ok' => false, 'written' => 0, 'skipped' => []];
        }

        try {
            DB::beginTransaction();

            // 既存配置（this master）の状況を一括取得
            $existing = DB::table('t_assignment')
                ->where('master_id', $masterId)
                ->where('master_type', $masterType)
                ->whereIn('work_date', $dates)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy(fn ($row) => (string) $row->work_date);

            $written = 0;
            $skipped = [];

            foreach ($dates as $date) {
                $current = $existing->get($date);
                if ($current) {
                    if ((int) $current->workplace_id === $workplaceId) {
                        // 同一現場 → 何もしない
                        continue;
                    }

                    if (! $overwrite) {
                        $skipped[] = ['date' => $date, 'reason' => '他現場に配置済み'];
                        continue;
                    }

                    // 上書き
                    DB::table('t_assignment')
                        ->where('id', $current->id)
                        ->update([
                            'workplace_id' => $workplaceId,
                            'updated_at' => now(),
                        ]);
                    $written++;
                    continue;
                }

                DB::table('t_assignment')->insert([
                    'workplace_id' => $workplaceId,
                    'work_date' => $date,
                    'master_id' => $masterId,
                    'master_type' => $masterType,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $written++;
            }

            DB::commit();

            return ['ok' => true, 'written' => $written, 'skipped' => $skipped];
        } catch (\Exception $e) {
            DB::rollBack();
            if (function_exists('error')) {
                error($e, __FILE__, __METHOD__, __LINE__);
            }
            if (app()->environment('local')) {
                return $this->placeRangeLocal($masterId, $masterType, $workplaceId, $dates, $overwrite);
            }
            return ['ok' => false, 'written' => 0, 'skipped' => []];
        }
    }

    /**
     * 指定機械の期間内の配置を削除する。
     */
    public function clearRange(int $masterId, int $masterType, string $startDate, string $endDate): int
    {
        $masterId = (int) $masterId;
        $masterType = (int) $masterType;
        $dates = array_map(fn ($d) => $d['date'], $this->buildDateList($startDate, $endDate));
        if ($masterId <= 0 || $masterType <= 0 || $dates === []) {
            return 0;
        }

        try {
            $deleted = DB::table('t_assignment')
                ->where('master_id', $masterId)
                ->where('master_type', $masterType)
                ->whereIn('work_date', $dates)
                ->whereNull('deleted_at')
                ->delete();

            return (int) $deleted;
        } catch (\Exception $e) {
            if (function_exists('error')) {
                error($e, __FILE__, __METHOD__, __LINE__);
            }
            if (app()->environment('local')) {
                return $this->clearRangeLocal($masterId, $masterType, $dates);
            }
            return 0;
        }
    }

    // ----------------------------------------------------------------------
    // 内部
    // ----------------------------------------------------------------------

    /**
     * @return array<int,array{date:string,d:string,wd:string,is_sun:bool,is_sat:bool}>
     */
    private function buildDateList(string $startDate, string $endDate): array
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        if ($start === false || $end === false || $end < $start) {
            return [];
        }

        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        $list = [];
        $cursor = $start;
        $guard = 0;
        while ($cursor <= $end && $guard < 366) {
            $w = (int) date('w', $cursor);
            $list[] = [
                'date' => date('Y-m-d', $cursor),
                'd' => date('n/j', $cursor),
                'wd' => $weekdays[$w] ?? '',
                'is_sun' => $w === 0,
                'is_sat' => $w === 6,
            ];
            $cursor = strtotime('+1 day', $cursor);
            $guard++;
        }
        return $list;
    }

    /**
     * @return array<int,array{id:int,name:string,vehicle_type:int,master_type:int,sort:int}>
     */
    private function getMachines(?int $vehicleType): array
    {
        try {
            $query = DB::table('m_vehicle')
                ->whereNull('deleted_at')
                ->orderBy('vehicle_type')
                ->orderBy('sort_number');

            if ($vehicleType === 1 || $vehicleType === 2) {
                $query->where('vehicle_type', $vehicleType);
            }

            return $query->get()->map(function ($row) {
                $vt = (int) ($row->vehicle_type ?? 1);
                return [
                    'id' => (int) $row->id,
                    'name' => (string) ($row->vehicle_name ?? ''),
                    'vehicle_type' => $vt,
                    'master_type' => $this->masterTypeForVehicleType($vt),
                    'sort' => (int) ($row->sort_number ?? 0),
                ];
            })->all();
        } catch (\Exception $e) {
            if (function_exists('error')) {
                error($e, __FILE__, __METHOD__, __LINE__);
            }
            if (app()->environment('local')) {
                return $this->getMachinesLocal($vehicleType);
            }
            return [];
        }
    }

    /**
     * @return array<int,array{id:int,name:string}>
     */
    private function getWorkplaces(): array
    {
        try {
            return DB::table('m_workplace')
                ->where('active_flg', true)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => ['id' => (int) $row->id, 'name' => (string) ($row->workplace_name ?? '')])
                ->all();
        } catch (\Exception $e) {
            if (function_exists('error')) {
                error($e, __FILE__, __METHOD__, __LINE__);
            }
            if (app()->environment('local')) {
                return $this->getWorkplacesLocal();
            }
            return [];
        }
    }

    /**
     * @param  array<int,array{id:int}>  $machines
     * @return array<int,array<string,array{workplace_id:int,workplace_name:string,start:bool,end:bool}>>
     */
    private function getAssignmentCells(array $machines, string $startDate, string $endDate): array
    {
        if ($machines === []) {
            return [];
        }

        $masterIds = array_map(fn ($m) => $m['id'], $machines);
        $masterTypes = [
            (int) config('assignments.master_type.vehicle'),
            (int) config('assignments.master_type.equipment'),
        ];

        try {
            $rows = DB::table('t_assignment as ta')
                ->leftJoin('m_workplace as mw', 'mw.id', '=', 'ta.workplace_id')
                ->select('ta.master_id', 'ta.master_type', 'ta.workplace_id', 'ta.work_date', 'mw.workplace_name')
                ->whereIn('ta.master_id', $masterIds)
                ->whereIn('ta.master_type', $masterTypes)
                ->whereBetween('ta.work_date', [$startDate, $endDate])
                ->whereNull('ta.deleted_at')
                ->get();

            $cells = [];
            foreach ($rows as $row) {
                $mid = (int) $row->master_id;
                $date = (string) $row->work_date;
                $cells[$mid][$date] = [
                    'workplace_id' => (int) $row->workplace_id,
                    'workplace_name' => (string) ($row->workplace_name ?? ''),
                    'start' => false,
                    'end' => false,
                ];
            }
            return $cells;
        } catch (\Exception $e) {
            if (function_exists('error')) {
                error($e, __FILE__, __METHOD__, __LINE__);
            }
            if (app()->environment('local')) {
                return $this->getAssignmentCellsLocal($masterIds, $masterTypes, $startDate, $endDate);
            }
            return [];
        }
    }

    /**
     * 連続した同一現場セルの両端（start/end）にフラグを立てる。
     *
     * @param  array<int,array<string,array{workplace_id:int,workplace_name:string,start:bool,end:bool}>>  $cells
     * @param  array<int,array{date:string}>  $dates
     * @return array<int,array<string,array{workplace_id:int,workplace_name:string,start:bool,end:bool}>>
     */
    private function markSpanEdges(array $cells, array $dates): array
    {
        $dateList = array_map(fn ($d) => $d['date'], $dates);
        $count = count($dateList);

        foreach ($cells as $mid => $perDate) {
            for ($i = 0; $i < $count; $i++) {
                $d = $dateList[$i];
                if (! isset($perDate[$d])) {
                    continue;
                }
                $wid = $perDate[$d]['workplace_id'];

                $prev = $i > 0 ? ($perDate[$dateList[$i - 1]]['workplace_id'] ?? null) : null;
                $next = $i < $count - 1 ? ($perDate[$dateList[$i + 1]]['workplace_id'] ?? null) : null;

                $cells[$mid][$d]['start'] = ($prev !== $wid);
                $cells[$mid][$d]['end'] = ($next !== $wid);
            }
        }
        return $cells;
    }

    private function masterTypeForVehicleType(int $vehicleType): int
    {
        return $vehicleType === 2
            ? (int) config('assignments.master_type.equipment')
            : (int) config('assignments.master_type.vehicle');
    }

    // ----------------------------------------------------------------------
    // ローカル開発用フォールバック（DB 未起動時に storage/app/local_*.json を使う）
    // ----------------------------------------------------------------------

    /**
     * @return array<int,array{id:int,name:string,vehicle_type:int,master_type:int,sort:int}>
     */
    private function getMachinesLocal(?int $vehicleType): array
    {
        $list = [];
        foreach ($this->readLocalJson(self::LOCAL_VEHICLE_FILE) as $row) {
            $vt = (int) ($row['vehicle_type'] ?? 1);
            if ($vehicleType !== null && $vt !== $vehicleType) continue;
            if (! empty($row['deleted_at'])) continue;
            $list[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['vehicle_name'] ?? ''),
                'vehicle_type' => $vt,
                'master_type' => $this->masterTypeForVehicleType($vt),
                'sort' => (int) ($row['sort_number'] ?? 0),
            ];
        }
        foreach ($this->readLocalJson(self::LOCAL_EQUIPMENT_FILE) as $row) {
            $vt = (int) ($row['vehicle_type'] ?? 2);
            if ($vehicleType !== null && $vt !== $vehicleType) continue;
            if (! empty($row['deleted_at'])) continue;
            $list[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['vehicle_name'] ?? ''),
                'vehicle_type' => $vt,
                'master_type' => $this->masterTypeForVehicleType($vt),
                'sort' => (int) ($row['sort_number'] ?? 0),
            ];
        }

        usort($list, function ($a, $b) {
            return [$a['vehicle_type'], $a['sort']] <=> [$b['vehicle_type'], $b['sort']];
        });
        return $list;
    }

    /**
     * @return array<int,array{id:int,name:string}>
     */
    private function getWorkplacesLocal(): array
    {
        $rows = [];
        foreach ($this->readLocalJson(self::LOCAL_WORKPLACE_FILE) as $row) {
            if (! ($row['active_flg'] ?? true)) continue;
            if (! empty($row['deleted_at'])) continue;
            $rows[] = ['id' => (int) ($row['id'] ?? 0), 'name' => (string) ($row['workplace_name'] ?? '')];
        }
        return $rows;
    }

    /**
     * @param  array<int,int>  $masterIds
     * @param  array<int,int>  $masterTypes
     * @return array<int,array<string,array{workplace_id:int,workplace_name:string,start:bool,end:bool}>>
     */
    private function getAssignmentCellsLocal(array $masterIds, array $masterTypes, string $startDate, string $endDate): array
    {
        $assignments = $this->readLocalJson(self::LOCAL_ASSIGNMENT_FILE);
        $workplaces = [];
        foreach ($this->readLocalJson(self::LOCAL_WORKPLACE_FILE) as $w) {
            $workplaces[(int) ($w['id'] ?? 0)] = (string) ($w['workplace_name'] ?? '');
        }

        $cells = [];
        foreach ($assignments as $row) {
            $mid = (int) ($row['master_id'] ?? 0);
            $mt = (int) ($row['master_type'] ?? 0);
            $date = (string) ($row['work_date'] ?? '');
            if (! in_array($mid, $masterIds, true)) continue;
            if (! in_array($mt, $masterTypes, true)) continue;
            if ($date < $startDate || $date > $endDate) continue;
            $wid = (int) ($row['workplace_id'] ?? 0);
            $cells[$mid][$date] = [
                'workplace_id' => $wid,
                'workplace_name' => $workplaces[$wid] ?? '',
                'start' => false,
                'end' => false,
            ];
        }
        return $cells;
    }

    /**
     * @param  array<int,string>  $dates
     * @return array{ok:bool, written:int, skipped:array<int,array{date:string,reason:string}>}
     */
    private function placeRangeLocal(int $masterId, int $masterType, int $workplaceId, array $dates, bool $overwrite): array
    {
        $rows = $this->readLocalJson(self::LOCAL_ASSIGNMENT_FILE);
        $written = 0;
        $skipped = [];
        $nextId = 1;
        foreach ($rows as $r) {
            $nextId = max($nextId, (int) ($r['id'] ?? 0) + 1);
        }

        foreach ($dates as $date) {
            $idx = null;
            foreach ($rows as $i => $r) {
                if ((int) ($r['master_id'] ?? 0) === $masterId
                    && (int) ($r['master_type'] ?? 0) === $masterType
                    && (string) ($r['work_date'] ?? '') === $date) {
                    $idx = $i;
                    break;
                }
            }

            if ($idx !== null) {
                $curWid = (int) ($rows[$idx]['workplace_id'] ?? 0);
                if ($curWid === $workplaceId) continue;
                if (! $overwrite) {
                    $skipped[] = ['date' => $date, 'reason' => '他現場に配置済み'];
                    continue;
                }
                $rows[$idx]['workplace_id'] = $workplaceId;
                $written++;
                continue;
            }

            $rows[] = [
                'id' => $nextId++,
                'workplace_id' => $workplaceId,
                'work_date' => $date,
                'master_id' => $masterId,
                'master_type' => $masterType,
            ];
            $written++;
        }

        $this->writeLocalJson(self::LOCAL_ASSIGNMENT_FILE, array_values($rows));
        return ['ok' => true, 'written' => $written, 'skipped' => $skipped];
    }

    /**
     * @param  array<int,string>  $dates
     */
    private function clearRangeLocal(int $masterId, int $masterType, array $dates): int
    {
        $rows = $this->readLocalJson(self::LOCAL_ASSIGNMENT_FILE);
        $before = count($rows);
        $rows = array_values(array_filter($rows, function ($r) use ($masterId, $masterType, $dates) {
            return ! ((int) ($r['master_id'] ?? 0) === $masterId
                && (int) ($r['master_type'] ?? 0) === $masterType
                && in_array((string) ($r['work_date'] ?? ''), $dates, true));
        }));
        $this->writeLocalJson(self::LOCAL_ASSIGNMENT_FILE, $rows);
        return $before - count($rows);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function readLocalJson(string $relativePath): array
    {
        $path = storage_path($relativePath);
        if (! is_file($path)) return [];
        $json = @file_get_contents($path);
        $data = is_string($json) ? json_decode($json, true) : [];
        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function writeLocalJson(string $relativePath, array $rows): bool
    {
        $path = storage_path($relativePath);
        $dir = dirname($path);
        if (! is_dir($dir)) @mkdir($dir, 0775, true);
        return @file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
    }
}
