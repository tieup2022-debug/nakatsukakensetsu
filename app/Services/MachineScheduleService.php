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
    private const LOCAL_UNAVAILABLE_FILE = 'app/local_vehicle_unavailable.json';

    /**
     * 使用不可の理由種別。
     * t_vehicle_unavailable.reason_type の値と対応。
     *
     * @return array<int, string>
     */
    public static function unavailableReasons(): array
    {
        return [
            1 => '車検',
            2 => '点検',
            3 => '修理',
            4 => '故障',
            99 => 'その他',
        ];
    }

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
     *   unavailable:array<int,array<string,array{reason_type:int,reason_label:string,start:bool,end:bool}>>,
     *   workplaces:array<int,array{id:int,name:string}>,
     *   reasons:array<int,string>,
     * }
     */
    public function getMatrix(string $startDate, string $endDate, ?int $vehicleType = null): array
    {
        $dates = $this->buildDateList($startDate, $endDate);
        $machines = $this->getMachines($vehicleType);
        $workplaces = $this->getWorkplaces();
        $cells = $this->getAssignmentCells($machines, $startDate, $endDate);
        $unavailable = $this->getUnavailableCells($machines, $startDate, $endDate);

        $cellsWithSpan = $this->markSpanEdges($cells, $dates);
        $unavailableWithSpan = $this->markUnavailableSpanEdges($unavailable, $dates);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'dates' => $dates,
            'machines' => $machines,
            'cells' => $cellsWithSpan,
            'unavailable' => $unavailableWithSpan,
            'workplaces' => $workplaces,
            'reasons' => self::unavailableReasons(),
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
     * 指定機械（vehicle_id ベース）を期間 [startDate, endDate] にわたって使用不可登録する。
     *
     * - 既存の使用不可期間と重複する場合は、その重複部分を吸収して1本の期間に統合する
     *   （同じ理由種別なら結合、違う種別なら別レコードで上書き：シンプル化のため）
     * - reasonType: 1=車検 2=点検 3=修理 4=故障 99=その他
     *
     * @return array{ok:bool, written:int}
     */
    public function setUnavailable(int $vehicleId, int $reasonType, string $startDate, string $endDate): array
    {
        $vehicleId = (int) $vehicleId;
        $reasonType = (int) $reasonType;
        if ($vehicleId <= 0 || ! array_key_exists($reasonType, self::unavailableReasons())) {
            return ['ok' => false, 'written' => 0];
        }
        if (strtotime($endDate) === false || strtotime($startDate) === false || strtotime($endDate) < strtotime($startDate)) {
            return ['ok' => false, 'written' => 0];
        }

        try {
            DB::beginTransaction();

            // 同 vehicle で期間がオーバーラップする既存レコードを取得
            $overlapping = DB::table('t_vehicle_unavailable')
                ->where('vehicle_id', $vehicleId)
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->whereNull('deleted_at')
                ->get();

            // 同種別だけマージし、別種別はそのまま残す（別種別と重なるなら新規の方が優先するため別途上書き）
            $mergedStart = $startDate;
            $mergedEnd = $endDate;
            foreach ($overlapping as $row) {
                if ((int) $row->reason_type === $reasonType) {
                    if ($row->start_date < $mergedStart) $mergedStart = (string) $row->start_date;
                    if ($row->end_date > $mergedEnd) $mergedEnd = (string) $row->end_date;
                    DB::table('t_vehicle_unavailable')->where('id', $row->id)->delete();
                } else {
                    // 別種別: 新規範囲と被る日付部分を既存からカット
                    $this->trimUnavailableRow($row, $startDate, $endDate);
                }
            }

            DB::table('t_vehicle_unavailable')->insert([
                'vehicle_id' => $vehicleId,
                'reason_type' => $reasonType,
                'start_date' => $mergedStart,
                'end_date' => $mergedEnd,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            return ['ok' => true, 'written' => 1];
        } catch (\Exception $e) {
            DB::rollBack();
            if (function_exists('error')) {
                error($e, __FILE__, __METHOD__, __LINE__);
            }
            if (app()->environment('local')) {
                return $this->setUnavailableLocal($vehicleId, $reasonType, $startDate, $endDate);
            }
            return ['ok' => false, 'written' => 0];
        }
    }

    /**
     * 指定機械の使用不可期間を、指定の期間に重なる範囲でクリアする。
     */
    public function clearUnavailable(int $vehicleId, string $startDate, string $endDate): int
    {
        $vehicleId = (int) $vehicleId;
        if ($vehicleId <= 0) return 0;
        if (strtotime($endDate) === false || strtotime($startDate) === false) return 0;

        try {
            $rows = DB::table('t_vehicle_unavailable')
                ->where('vehicle_id', $vehicleId)
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->whereNull('deleted_at')
                ->get();

            $count = 0;
            foreach ($rows as $row) {
                $count += $this->trimUnavailableRow($row, $startDate, $endDate, true);
            }
            return $count;
        } catch (\Exception $e) {
            if (function_exists('error')) {
                error($e, __FILE__, __METHOD__, __LINE__);
            }
            if (app()->environment('local')) {
                return $this->clearUnavailableLocal($vehicleId, $startDate, $endDate);
            }
            return 0;
        }
    }

    /**
     * 1件の既存使用不可レコードに対し、指定範囲 [trimStart, trimEnd] と重なる部分を削除する。
     *  - 完全に重なる → 削除（戻り 1）
     *  - 前後どちらか片側だけ重なる → 該当側を切り詰めて update（戻り 1）
     *  - 中間が重なる → 前半と後半 2本に分割（戻り 2）
     *
     * @return int 影響レコード数
     */
    private function trimUnavailableRow($row, string $trimStart, string $trimEnd, bool $countDeleted = false): int
    {
        $rowStart = (string) $row->start_date;
        $rowEnd = (string) $row->end_date;

        // 完全包含 → 削除
        if ($trimStart <= $rowStart && $rowEnd <= $trimEnd) {
            DB::table('t_vehicle_unavailable')->where('id', $row->id)->delete();
            return $countDeleted ? 1 : 1;
        }

        // 中間切り抜き → 前半 update + 後半 insert
        if ($rowStart < $trimStart && $trimEnd < $rowEnd) {
            DB::table('t_vehicle_unavailable')
                ->where('id', $row->id)
                ->update([
                    'end_date' => date('Y-m-d', strtotime($trimStart . ' -1 day')),
                    'updated_at' => now(),
                ]);
            DB::table('t_vehicle_unavailable')->insert([
                'vehicle_id' => $row->vehicle_id,
                'reason_type' => $row->reason_type,
                'start_date' => date('Y-m-d', strtotime($trimEnd . ' +1 day')),
                'end_date' => $rowEnd,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return 2;
        }

        // 先頭が重なる → 後ろ側を残す
        if ($trimStart <= $rowStart && $rowStart <= $trimEnd) {
            DB::table('t_vehicle_unavailable')
                ->where('id', $row->id)
                ->update([
                    'start_date' => date('Y-m-d', strtotime($trimEnd . ' +1 day')),
                    'updated_at' => now(),
                ]);
            return 1;
        }

        // 末尾が重なる → 前側を残す
        if ($trimStart <= $rowEnd && $rowEnd <= $trimEnd) {
            DB::table('t_vehicle_unavailable')
                ->where('id', $row->id)
                ->update([
                    'end_date' => date('Y-m-d', strtotime($trimStart . ' -1 day')),
                    'updated_at' => now(),
                ]);
            return 1;
        }

        return 0;
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
     * 指定機械セットの使用不可期間を日次にバラして返す。
     *
     * @param  array<int,array{id:int}>  $machines
     * @return array<int,array<string,array{reason_type:int,reason_label:string,start:bool,end:bool}>>
     */
    private function getUnavailableCells(array $machines, string $startDate, string $endDate): array
    {
        if ($machines === []) {
            return [];
        }
        $vehicleIds = array_map(fn ($m) => $m['id'], $machines);
        $labels = self::unavailableReasons();

        try {
            $rows = DB::table('t_vehicle_unavailable')
                ->whereIn('vehicle_id', $vehicleIds)
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->whereNull('deleted_at')
                ->get();

            return $this->expandUnavailableRows($rows->all(), $startDate, $endDate, $labels);
        } catch (\Exception $e) {
            if (function_exists('error')) {
                error($e, __FILE__, __METHOD__, __LINE__);
            }
            if (app()->environment('local')) {
                return $this->getUnavailableCellsLocal($vehicleIds, $startDate, $endDate);
            }
            return [];
        }
    }

    /**
     * @param  array<int,object|array<string,mixed>>  $rows
     * @param  array<int,string>  $labels
     * @return array<int,array<string,array{reason_type:int,reason_label:string,start:bool,end:bool}>>
     */
    private function expandUnavailableRows(array $rows, string $rangeStart, string $rangeEnd, array $labels): array
    {
        $result = [];
        foreach ($rows as $row) {
            $vid = (int) (is_array($row) ? ($row['vehicle_id'] ?? 0) : ($row->vehicle_id ?? 0));
            $rt = (int) (is_array($row) ? ($row['reason_type'] ?? 0) : ($row->reason_type ?? 0));
            $s = (string) (is_array($row) ? ($row['start_date'] ?? '') : ($row->start_date ?? ''));
            $e = (string) (is_array($row) ? ($row['end_date'] ?? '') : ($row->end_date ?? ''));
            if ($vid <= 0 || $s === '' || $e === '') continue;

            // 表示範囲とクリップ
            $clipStart = $s < $rangeStart ? $rangeStart : $s;
            $clipEnd = $e > $rangeEnd ? $rangeEnd : $e;

            $cursor = strtotime($clipStart);
            $end = strtotime($clipEnd);
            $guard = 0;
            while ($cursor <= $end && $guard < 400) {
                $d = date('Y-m-d', $cursor);
                // 同日に複数登録があった場合は後勝ち（基本起きないが念のため）
                $result[$vid][$d] = [
                    'reason_type' => $rt,
                    'reason_label' => $labels[$rt] ?? '不可',
                    'start' => false,
                    'end' => false,
                ];
                $cursor = strtotime('+1 day', $cursor);
                $guard++;
            }
        }
        return $result;
    }

    /**
     * 使用不可セルの連続区間の両端にフラグを立てる。
     *
     * @param  array<int,array<string,array{reason_type:int,reason_label:string,start:bool,end:bool}>>  $cells
     * @param  array<int,array{date:string}>  $dates
     * @return array<int,array<string,array{reason_type:int,reason_label:string,start:bool,end:bool}>>
     */
    private function markUnavailableSpanEdges(array $cells, array $dates): array
    {
        $dateList = array_map(fn ($d) => $d['date'], $dates);
        $count = count($dateList);
        foreach ($cells as $vid => $perDate) {
            for ($i = 0; $i < $count; $i++) {
                $d = $dateList[$i];
                if (! isset($perDate[$d])) continue;
                $rt = $perDate[$d]['reason_type'];
                $prev = $i > 0 ? ($perDate[$dateList[$i - 1]]['reason_type'] ?? null) : null;
                $next = $i < $count - 1 ? ($perDate[$dateList[$i + 1]]['reason_type'] ?? null) : null;
                $cells[$vid][$d]['start'] = ($prev !== $rt);
                $cells[$vid][$d]['end'] = ($next !== $rt);
            }
        }
        return $cells;
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
     * @param  array<int,int>  $vehicleIds
     * @return array<int,array<string,array{reason_type:int,reason_label:string,start:bool,end:bool}>>
     */
    private function getUnavailableCellsLocal(array $vehicleIds, string $startDate, string $endDate): array
    {
        $rows = $this->readLocalJson(self::LOCAL_UNAVAILABLE_FILE);
        $rows = array_values(array_filter($rows, function ($r) use ($vehicleIds, $startDate, $endDate) {
            $vid = (int) ($r['vehicle_id'] ?? 0);
            $s = (string) ($r['start_date'] ?? '');
            $e = (string) ($r['end_date'] ?? '');
            return in_array($vid, $vehicleIds, true) && $s <= $endDate && $e >= $startDate;
        }));
        return $this->expandUnavailableRows($rows, $startDate, $endDate, self::unavailableReasons());
    }

    /**
     * @return array{ok:bool, written:int}
     */
    private function setUnavailableLocal(int $vehicleId, int $reasonType, string $startDate, string $endDate): array
    {
        $rows = $this->readLocalJson(self::LOCAL_UNAVAILABLE_FILE);
        // 同 vehicle 同 reason のオーバーラップを統合
        $mergedStart = $startDate;
        $mergedEnd = $endDate;
        $keep = [];
        foreach ($rows as $r) {
            $vid = (int) ($r['vehicle_id'] ?? 0);
            $rt = (int) ($r['reason_type'] ?? 0);
            $s = (string) ($r['start_date'] ?? '');
            $e = (string) ($r['end_date'] ?? '');
            if ($vid !== $vehicleId || $s > $endDate || $e < $startDate) {
                $keep[] = $r;
                continue;
            }
            if ($rt === $reasonType) {
                if ($s < $mergedStart) $mergedStart = $s;
                if ($e > $mergedEnd) $mergedEnd = $e;
                continue; // 取り込みのため drop
            }
            // 別種別: 重なり部分をカット
            if ($startDate <= $s && $e <= $endDate) {
                continue; // 完全に飲み込む
            }
            if ($s < $startDate && $endDate < $e) {
                // 中間切り抜き
                $keep[] = ['vehicle_id' => $vid, 'reason_type' => $rt, 'start_date' => $s, 'end_date' => date('Y-m-d', strtotime($startDate . ' -1 day'))];
                $keep[] = ['vehicle_id' => $vid, 'reason_type' => $rt, 'start_date' => date('Y-m-d', strtotime($endDate . ' +1 day')), 'end_date' => $e];
                continue;
            }
            if ($startDate <= $s) {
                $keep[] = ['vehicle_id' => $vid, 'reason_type' => $rt, 'start_date' => date('Y-m-d', strtotime($endDate . ' +1 day')), 'end_date' => $e];
                continue;
            }
            $keep[] = ['vehicle_id' => $vid, 'reason_type' => $rt, 'start_date' => $s, 'end_date' => date('Y-m-d', strtotime($startDate . ' -1 day'))];
        }
        $keep[] = ['vehicle_id' => $vehicleId, 'reason_type' => $reasonType, 'start_date' => $mergedStart, 'end_date' => $mergedEnd];
        $this->writeLocalJson(self::LOCAL_UNAVAILABLE_FILE, $keep);
        return ['ok' => true, 'written' => 1];
    }

    private function clearUnavailableLocal(int $vehicleId, string $startDate, string $endDate): int
    {
        $rows = $this->readLocalJson(self::LOCAL_UNAVAILABLE_FILE);
        $count = 0;
        $keep = [];
        foreach ($rows as $r) {
            $vid = (int) ($r['vehicle_id'] ?? 0);
            $s = (string) ($r['start_date'] ?? '');
            $e = (string) ($r['end_date'] ?? '');
            if ($vid !== $vehicleId || $s > $endDate || $e < $startDate) {
                $keep[] = $r;
                continue;
            }
            if ($startDate <= $s && $e <= $endDate) {
                $count++;
                continue;
            }
            if ($s < $startDate && $endDate < $e) {
                $keep[] = array_merge($r, ['end_date' => date('Y-m-d', strtotime($startDate . ' -1 day'))]);
                $keep[] = array_merge($r, ['start_date' => date('Y-m-d', strtotime($endDate . ' +1 day'))]);
                $count++;
                continue;
            }
            if ($startDate <= $s) {
                $keep[] = array_merge($r, ['start_date' => date('Y-m-d', strtotime($endDate . ' +1 day'))]);
                $count++;
                continue;
            }
            $keep[] = array_merge($r, ['end_date' => date('Y-m-d', strtotime($startDate . ' -1 day'))]);
            $count++;
        }
        $this->writeLocalJson(self::LOCAL_UNAVAILABLE_FILE, $keep);
        return $count;
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
