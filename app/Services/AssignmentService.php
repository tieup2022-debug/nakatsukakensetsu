<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AssignmentService
{
    private const LOCAL_ASSIGNMENT_FILE = 'app/local_assignments.json';

    /** PDF: 1ページあたりの現場列数（旧システムと同じ） */
    private const PDF_WORKPLACES_PER_PAGE = 8;

    private const PDF_MAX_TECHNITIANS = 3;

    private const PDF_MAX_WORKERS = 20;

    private const PDF_MAX_VEHICLES_EQUIPMENTS = 14;

    private const PDF_MAX_ABSENCE_STAFF = 36;

    /** @var array<int, string> */
    private const PDF_STAFF_HEADER = [
        1 => '担当',
        2 => 'OP',
        3 => '作業員',
    ];

    /**
     * 配置一覧 取得
     */
    public function getAssignment($workplaceId = null, $workDate = null)
    {
        try {
            if (is_null($workDate)) {
                $workDate = defaultWorkDate();
            }

            if ($workplaceId === '' || $workplaceId === null) {
                $workplaceId = null;
            }

            if (is_null($workplaceId)) {
                $assinmentData = DB::table('t_assignment')
                    ->where('work_date', '=', $workDate)
                    ->whereNull('deleted_at')
                    ->orderBy('workplace_id', 'asc')
                    ->first();

                if ($assinmentData) {
                    $workplaceId = $assinmentData->workplace_id;
                }
            }

            return [
                'workplace_id' => $workplaceId,
                'work_date' => $workDate,
                'staff_list_first' => $this->GetStaffList(1, $workplaceId, $workDate, true) ? $this->GetStaffList(1, $workplaceId, $workDate, true) : [],
                'staff_list_second' => $this->GetStaffList(2, $workplaceId, $workDate, true) ? $this->GetStaffList(2, $workplaceId, $workDate, true) : [],
                'staff_list_third' => $this->GetStaffList(3, $workplaceId, $workDate, true) ? $this->GetStaffList(3, $workplaceId, $workDate, true) : [],
                'vehicle_list' => $this->GetVehicleList($workplaceId, $workDate, true) ? $this->GetVehicleList($workplaceId, $workDate, true) : [],
                'equipment_list' => $this->GetEquipmentList($workplaceId, $workDate, true) ? $this->GetEquipmentList($workplaceId, $workDate, true) : [],
            ];
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            return false;
        }
    }

    /**
     * 配置一覧 社員リスト取得 (属性ごと)
     */
    public function GetStaffList($staffType, $workplaceId, $workDate, $assigned = false)
    {
        try {
            if (isset($staffType) && isset($workDate) && isset($workplaceId)) {
                // 選択現場の配置行だけ JOIN（他現場の行は拾わない）
                // 他現場に既に配置されている人は、この現場では出さない（NOT EXISTS）
                $query = '
                    SELECT
                        stf.id                  AS staff_id
                        , stf.staff_name        AS staff_name
                        , stf.staff_type        AS staff_type
                        , asl.assignment_flg    AS assignment_flg
                    FROM
                        m_staff stf
                        LEFT JOIN (
                            SELECT
                                staff_id
                                , workplace_id
                                , 1 AS assignment_flg
                                , work_date
                            FROM
                                v_assignment_staff
                            WHERE
                                work_date = :work_date
                                AND workplace_id = :workplace_id
                        ) asl
                            ON stf.id = asl.staff_id
                            AND stf.deleted_at IS NULL
                        LEFT JOIN t_absence tab
                            ON tab.staff_id = stf.id
                            AND tab.work_date = :absence_work_date
                            AND tab.deleted_at IS NULL
                    WHERE
                        stf.staff_type = :staff_type
                        AND stf.deleted_at IS NULL
                        AND (tab.absence_flg = 0 OR tab.absence_flg IS NULL)
                        AND NOT EXISTS (
                            SELECT 1 FROM t_assignment ta
                            WHERE ta.master_id = stf.id
                            AND ta.master_type = :master_type_staff
                            AND ta.work_date = :work_date_other
                            AND ta.workplace_id <> :workplace_id_other
                            AND ta.deleted_at IS NULL
                        )
                ';

                $bindings = [
                    'work_date' => $workDate,
                    'absence_work_date' => $workDate,
                    'staff_type' => $staffType,
                    'workplace_id' => $workplaceId,
                    'work_date_other' => $workDate,
                    'workplace_id_other' => $workplaceId,
                    'master_type_staff' => config('assignments.master_type.staff'),
                ];

                if ($assigned) {
                    $query .= ' AND asl.assignment_flg = 1';
                }

                return DB::select($query, $bindings);
            }

            return false;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                return $this->getLocalStaffList((int) $staffType, (int) $workplaceId, (string) $workDate, (bool) $assigned);
            }
            return false;
        }
    }

    /**
     * 配置一覧 車両リスト取得
     */
    public function GetVehicleList($workplaceId, $workDate, $assigned = false)
    {
        try {
            if (isset($workDate) && isset($workplaceId)) {
                $query = '
                    SELECT
                        vhc.id                  AS vehicle_id
                        , vhc.vehicle_name      AS vehicle_name
                        , avl.assignment_flg    AS assignment_flg
                    FROM
                        m_vehicle vhc
                        LEFT JOIN (
                            SELECT
                                vehicle_id
                                , workplace_id
                                , 1 AS assignment_flg
                                , work_date
                            FROM
                                v_assignment_vehicle
                            WHERE
                                work_date = :work_date
                                AND workplace_id = :workplace_id
                        ) avl
                            ON vhc.id = avl.vehicle_id
                            AND vhc.deleted_at IS NULL
                    WHERE 1=1
                        AND vhc.deleted_at IS NULL
                        AND vhc.vehicle_type = 1
                        AND NOT EXISTS (
                            SELECT 1 FROM t_assignment ta
                            WHERE ta.master_id = vhc.id
                            AND ta.master_type = :master_type_vehicle
                            AND ta.work_date = :work_date_other
                            AND ta.workplace_id <> :workplace_id_other
                            AND ta.deleted_at IS NULL
                        )
                ';

                $bindings = [
                    'work_date' => $workDate,
                    'workplace_id' => $workplaceId,
                    'work_date_other' => $workDate,
                    'workplace_id_other' => $workplaceId,
                    'master_type_vehicle' => config('assignments.master_type.vehicle'),
                ];

                if ($assigned) {
                    $query .= ' AND avl.assignment_flg = 1';
                }

                return DB::select($query, $bindings);
            }

            return false;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                return $this->getLocalVehicleList((int) $workplaceId, (string) $workDate, (bool) $assigned, 1);
            }
            return false;
        }
    }

    /**
     * 配置一覧 重機リスト取得
     */
    public function GetEquipmentList($workplaceId, $workDate, $assigned = false)
    {
        try {
            if (isset($workDate) && isset($workplaceId)) {
                $query = '
                    SELECT
                        vhc.id                  AS vehicle_id
                        , vhc.vehicle_name      AS vehicle_name
                        , avl.assignment_flg    AS assignment_flg
                    FROM
                        m_vehicle vhc
                        LEFT JOIN (
                            SELECT
                                vehicle_id
                                , workplace_id
                                , 1 AS assignment_flg
                                , work_date
                            FROM
                                v_assignment_equipment
                            WHERE
                                work_date = :work_date
                                AND workplace_id = :workplace_id
                        ) avl
                            ON vhc.id = avl.vehicle_id
                            AND vhc.deleted_at IS NULL
                    WHERE 1=1
                        AND vhc.deleted_at IS NULL
                        AND vhc.vehicle_type = 2
                        AND NOT EXISTS (
                            SELECT 1 FROM t_assignment ta
                            WHERE ta.master_id = vhc.id
                            AND ta.master_type = :master_type_equipment
                            AND ta.work_date = :work_date_other
                            AND ta.workplace_id <> :workplace_id_other
                            AND ta.deleted_at IS NULL
                        )
                ';

                $bindings = [
                    'work_date' => $workDate,
                    'workplace_id' => $workplaceId,
                    'work_date_other' => $workDate,
                    'workplace_id_other' => $workplaceId,
                    'master_type_equipment' => config('assignments.master_type.equipment'),
                ];

                if ($assigned) {
                    $query .= ' AND avl.assignment_flg = 1';
                }

                return DB::select($query, $bindings);
            }

            return false;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                return $this->getLocalVehicleList((int) $workplaceId, (string) $workDate, (bool) $assigned, 2);
            }
            return false;
        }
    }

    /**
     * 配置一覧 更新
     */
    public function AssignmentUpdate($workplaceId, $workDate, $staffList, $vehicleList, $equipmentList)
    {
        try {
            if (isset($workplaceId) && isset($workDate) && isset($staffList) && isset($vehicleList) && isset($equipmentList)) {
                foreach ($staffList as $staffId => $assignFlg) {
                    $duplicateStaffCheck = DB::table('t_assignment')
                        ->where('master_id', '=', $staffId)
                        ->where('master_type', '=', config('assignments.master_type.staff'))
                        ->where('work_date', '=', $workDate)
                        ->where('workplace_id', '<>', $workplaceId)
                        ->whereNull('deleted_at')
                        ->exists();

                    $absenceCheck = DB::table('t_absence')
                        ->where('work_date', '=', $workDate)
                        ->where('staff_id', '=', $staffId)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (($absenceCheck || $duplicateStaffCheck) && intval($assignFlg) === 1) {
                        return false;
                    }
                }

                DB::beginTransaction();

                foreach ($staffList as $staffId => $assignFlg) {
                    $exsitsStaffCheck = DB::table('t_assignment')
                        ->where('master_id', '=', $staffId)
                        ->where('master_type', '=', config('assignments.master_type.staff'))
                        ->where('work_date', '=', $workDate)
                        ->where('workplace_id', '=', $workplaceId)
                        ->whereNull('deleted_at')
                        ->first();

                    if ($exsitsStaffCheck && $assignFlg == 1) {
                        DB::table('t_assignment')
                            ->where('id', '=', $exsitsStaffCheck->id)
                            ->update(['updated_at' => now()]);
                    } elseif ($exsitsStaffCheck && $assignFlg == 0) {
                        DB::table('t_assignment')
                            ->where('id', '=', $exsitsStaffCheck->id)
                            ->delete();

                        DB::table('t_attendance')
                            ->where('staff_id', '=', $staffId)
                            ->where('work_date', '=', $workDate)
                            ->where('workplace_id', '=', $workplaceId)
                            ->delete();
                    } elseif (!$exsitsStaffCheck && $assignFlg == 1) {
                        DB::table('t_assignment')
                            ->insert([
                                'workplace_id' => $workplaceId,
                                'work_date' => $workDate,
                                'master_id' => $staffId,
                                'master_type' => config('assignments.master_type.staff'),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }
                }

                foreach ($vehicleList as $vehicleId => $assignFlg) {
                    $exsitsVehicleCheck = DB::table('t_assignment')
                        ->where('master_id', '=', $vehicleId)
                        ->where('master_type', '=', config('assignments.master_type.vehicle'))
                        ->where('work_date', '=', $workDate)
                        ->where('workplace_id', '=', $workplaceId)
                        ->whereNull('deleted_at')
                        ->first();

                    if ($exsitsVehicleCheck && $assignFlg == 1) {
                        DB::table('t_assignment')
                            ->where('id', '=', $exsitsVehicleCheck->id)
                            ->update(['updated_at' => now()]);
                    } elseif ($exsitsVehicleCheck && $assignFlg == 0) {
                        DB::table('t_assignment')
                            ->where('id', '=', $exsitsVehicleCheck->id)
                            ->delete();
                    } elseif (!$exsitsVehicleCheck && $assignFlg == 1) {
                        DB::table('t_assignment')
                            ->insert([
                                'workplace_id' => $workplaceId,
                                'work_date' => $workDate,
                                'master_id' => $vehicleId,
                                'master_type' => config('assignments.master_type.vehicle'),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }
                }

                foreach ($equipmentList as $vehicleId => $assignFlg) {
                    $exsitsEquipmentCheck = DB::table('t_assignment')
                        ->where('master_id', '=', $vehicleId)
                        ->where('master_type', '=', config('assignments.master_type.equipment'))
                        ->where('work_date', '=', $workDate)
                        ->where('workplace_id', '=', $workplaceId)
                        ->whereNull('deleted_at')
                        ->first();

                    if ($exsitsEquipmentCheck && $assignFlg == 1) {
                        DB::table('t_assignment')
                            ->where('id', '=', $exsitsEquipmentCheck->id)
                            ->update(['updated_at' => now()]);
                    } elseif ($exsitsEquipmentCheck && $assignFlg == 0) {
                        DB::table('t_assignment')
                            ->where('id', '=', $exsitsEquipmentCheck->id)
                            ->delete();
                    } elseif (!$exsitsEquipmentCheck && $assignFlg == 1) {
                        DB::table('t_assignment')
                            ->insert([
                                'workplace_id' => $workplaceId,
                                'work_date' => $workDate,
                                'master_id' => $vehicleId,
                                'master_type' => config('assignments.master_type.equipment'),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }
                }

                DB::commit();

                return true;
            }

            return false;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                return $this->updateLocalAssignment((int) $workplaceId, (string) $workDate, $staffList, $vehicleList, $equipmentList);
            }
            return false;
        }
    }

    /**
     * 前日に同現場の配置一覧が存在するか確認
     */
    public function CheckAssignmentPreviousDate($workplaceId, $workDate)
    {
        try {
            if (isset($workplaceId) && isset($workDate)) {
                $previousDate = null;

                if (date('w', strtotime($workDate)) == 6 || date('w', strtotime($workDate)) == 0 || date('w', strtotime($workDate)) == 1) {
                    $previousDate = date('Y-m-d', strtotime('last Friday', strtotime($workDate)));
                } else {
                    $previousDate = date('Y-m-d', strtotime('-1 day', strtotime($workDate)));
                }

                $exists = DB::table('t_assignment')
                    ->where('workplace_id', '=', $workplaceId)
                    ->where('work_date', '=', $previousDate)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($exists) {
                    return $previousDate;
                }

                return false;
            }

            return false;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            return false;
        }
    }

    /**
     * 前日の同現場の配置一覧を複製
     */
    public function CopyAssignment($workplaceId, $workDate, $previousDate)
    {
        try {
            if (isset($workplaceId) && isset($workDate) && isset($previousDate)) {
                DB::beginTransaction();

                DB::table('t_assignment')
                    ->where('workplace_id', '=', $workplaceId)
                    ->where('work_date', '=', $workDate)
                    ->whereNull('deleted_at')
                    ->delete();

                $array = DB::table('t_assignment')
                    ->where('workplace_id', '=', $workplaceId)
                    ->where('work_date', '=', $previousDate)
                    ->whereNull('deleted_at')
                    ->get();

                foreach ($array as $value) {
                    $existsCheck = DB::table('t_assignment')
                        ->where('master_id', '=', $value->master_id)
                        ->where('master_type', '=', $value->master_type)
                        ->where('workplace_id', '<>', $workplaceId)
                        ->where('work_date', '=', $workDate)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($existsCheck) {
                        DB::rollback();
                        return false;
                    }

                    if ($value->master_type == config('assignments.master_type.staff')) {
                        $absenceCheck = DB::table('t_absence')
                            ->where('work_date', '=', $workDate)
                            ->where('staff_id', '=', $value->master_id)
                            ->whereNull('deleted_at')
                            ->exists();

                        if ($absenceCheck) {
                            continue;
                        }
                    }

                    DB::table('t_assignment')->insert([
                        'master_id' => $value->master_id,
                        'master_type' => $value->master_type,
                        'workplace_id' => $workplaceId,
                        'work_date' => $workDate,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::commit();
                return true;
            }

            return false;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            return false;
        }
    }

    /**
     * その日、配置がある現場を全取得（PDF用）
     */
    public function GetAssignedWorkplace($workDate)
    {
        try {
            return DB::table('v_assignment_staff')
                ->select('workplace_id', 'workplace_name')
                ->where('work_date', '=', $workDate)
                ->distinct()
                ->orderBy('workplace_id')
                ->get();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                return $this->getAssignedWorkplaceLocal((string) $workDate);
            }

            return false;
        }
    }

    /**
     * 配置一覧 PDF 用データ（旧システム getPdf と同仕様：全現場を8枠/ページで出力）
     *
     * @return array<string, mixed>|false
     */
    public function getPdf($workDate)
    {
        $assignedWorkplaceList = $this->GetAssignedWorkplace($workDate);
        if ($assignedWorkplaceList === false || $assignedWorkplaceList->isEmpty()) {
            return false;
        }

        try {
            $pdfDataList = $this->createBasePdfDataList($assignedWorkplaceList->all());

            $result = [];
            foreach ($pdfDataList as $pdfKey => $pdfValue) {
                $result[$pdfKey] = $this->fetchAndFormatWorkplaceDetails($pdfValue, $workDate);
            }

            $absenceStaffList = $this->getAbsenceStaffList($workDate);
            $result['absence_list'] = $this->formatAbsenceStaffList($absenceStaffList);

            return [
                'pdf_data_list' => $result,
                'display_date' => formatJapaneseDate($workDate),
                'today' => formatJapaneseDate(date('Y-m-d')),
            ];
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }

    /**
     * 現場リストをページ単位に分割し、基本のPDFデータ構造を作成する
     *
     * @param  array<int, object>  $assignedWorkplaceList
     * @return array<int, array<string, mixed>>
     */
    private function createBasePdfDataList(array $assignedWorkplaceList)
    {
        $pdfDataList = [];
        $chunkedWorkplaces = array_chunk($assignedWorkplaceList, self::PDF_WORKPLACES_PER_PAGE);

        foreach ($chunkedWorkplaces as $chunk) {
            $pdfData = [];
            for ($i = 0; $i < self::PDF_WORKPLACES_PER_PAGE; $i++) {
                $key = 'workplace'.($i + 1);
                $workplace = $chunk[$i] ?? null;
                $pdfData[$key]['workplace_id'] = $workplace->workplace_id ?? '';
                $pdfData[$key]['workplace_name'] = $workplace->workplace_name ?? '';
            }
            $pdfDataList[] = $pdfData;
        }

        return $pdfDataList;
    }

    /**
     * PDFページの各現場の詳細情報を取得・整形する
     *
     * @param  array<string, mixed>  $pdfData
     * @return array<string, mixed>
     */
    private function fetchAndFormatWorkplaceDetails(array $pdfData, $workDate)
    {
        foreach ($pdfData as $key => $value) {
            if (! empty($value['workplace_id'])) {
                $workplaceId = $value['workplace_id'];

                $technitianList = $this->GetStaffList(1, $workplaceId, $workDate, true);
                $workerList = $this->getAndMergeWorkerLists($workplaceId, $workDate);
                $vehicleList = $this->GetVehicleList($workplaceId, $workDate, true);
                $equipmentList = $this->GetEquipmentList($workplaceId, $workDate, true);

                $pdfData[$key]['technitian_list'] = $this->formatStaffArray($technitianList ?: [], self::PDF_MAX_TECHNITIANS);
                $pdfData[$key]['worker_list'] = $this->formatWorkerArray($workerList);
                $pdfData[$key]['vehicle_list'] = $this->formatVehicleEquipmentArray($vehicleList ?: [], 'vehicle_name');
                $pdfData[$key]['equipment_list'] = $this->formatVehicleEquipmentArray($equipmentList ?: [], 'vehicle_name');
            } else {
                $this->fillEmptyWorkplaceData($pdfData[$key]);
            }
        }

        return $pdfData;
    }

    /**
     * OP と作業員リストを取得しマージする
     *
     * @return array<int, object>
     */
    private function getAndMergeWorkerLists($workplaceId, $workDate)
    {
        $workerList1 = $this->GetStaffList(2, $workplaceId, $workDate, true);
        $workerList2 = $this->GetStaffList(3, $workplaceId, $workDate, true);
        $a = is_array($workerList1) ? $workerList1 : [];
        $b = is_array($workerList2) ? $workerList2 : [];

        return array_merge($a, $b);
    }

    /**
     * 欠席者（勤怠）と欠勤予定（t_absence）を結合
     *
     * @return array<int, object>
     */
    private function getAbsenceStaffList($workDate)
    {
        try {
            $absenceStaffList = DB::table('v_attendance_all')
                ->where('work_date', $workDate)
                ->where('absence_flg', 1)
                ->get()
                ->all();

            $plannedAbsenceList = DB::table('t_absence AS tab')
                ->join('m_staff AS msf', function ($join) {
                    $join->on('msf.id', '=', 'tab.staff_id')
                        ->whereNull('msf.deleted_at')
                        ->whereNull('tab.deleted_at');
                })
                ->where('tab.work_date', $workDate)
                ->select('msf.staff_name')
                ->get()
                ->all();

            return array_merge($absenceStaffList, $plannedAbsenceList);
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);

            return [];
        }
    }

    /**
     * 欠勤者リストを指定サイズに整形する
     *
     * @param  array<int, object>  $absenceStaffList
     * @return array<int, string>
     */
    private function formatAbsenceStaffList(array $absenceStaffList)
    {
        $formatted = [];
        for ($i = 0; $i < self::PDF_MAX_ABSENCE_STAFF; $i++) {
            $formatted[$i] = isset($absenceStaffList[$i]) ? ($absenceStaffList[$i]->staff_name ?? '') : '';
        }

        return $formatted;
    }

    /**
     * @param  array<int, object>  $sourceList
     * @return array<int, string>
     */
    private function formatStaffArray(array $sourceList, int $maxSize): array
    {
        $targetArray = [];
        for ($i = 0; $i < $maxSize; $i++) {
            $targetArray[$i] = isset($sourceList[$i]) ? ($sourceList[$i]->staff_name ?? '') : '';
        }

        return $targetArray;
    }

    /**
     * @param  array<int, object>  $sourceList
     * @return array<int, array{staff_type: string, staff_name: string}>
     */
    private function formatWorkerArray(array $sourceList): array
    {
        $targetArray = [];
        for ($i = 0; $i < self::PDF_MAX_WORKERS; $i++) {
            $staff = $sourceList[$i] ?? null;
            $staffType = $staff ? ($staff->staff_type ?? null) : null;
            $targetArray[$i] = [
                'staff_type' => $staffType ? (self::PDF_STAFF_HEADER[(int) $staffType] ?? '') : '',
                'staff_name' => $staff ? ($staff->staff_name ?? '') : '',
            ];
        }

        return $targetArray;
    }

    /**
     * @param  array<int, object>  $sourceList
     * @return array<int, array{vehicle_name: string}>
     */
    private function formatVehicleEquipmentArray(array $sourceList, string $nameKey): array
    {
        $targetArray = [];
        for ($i = 0; $i < self::PDF_MAX_VEHICLES_EQUIPMENTS; $i++) {
            $targetArray[$i] = [
                'vehicle_name' => isset($sourceList[$i]) ? ($sourceList[$i]->{$nameKey} ?? '') : '',
            ];
        }

        return $targetArray;
    }

    /**
     * @param  array<string, mixed>  $pdfDataEntry
     */
    private function fillEmptyWorkplaceData(array &$pdfDataEntry): void
    {
        $pdfDataEntry['technitian_list'] = [];
        for ($i = 0; $i < self::PDF_MAX_TECHNITIANS; $i++) {
            $pdfDataEntry['technitian_list'][$i] = '';
        }
        $pdfDataEntry['worker_list'] = [];
        for ($i = 0; $i < self::PDF_MAX_WORKERS; $i++) {
            $pdfDataEntry['worker_list'][$i]['staff_type'] = '';
            $pdfDataEntry['worker_list'][$i]['staff_name'] = '';
        }
        $pdfDataEntry['vehicle_list'] = [];
        $pdfDataEntry['equipment_list'] = [];
        for ($i = 0; $i < self::PDF_MAX_VEHICLES_EQUIPMENTS; $i++) {
            $pdfDataEntry['vehicle_list'][$i]['vehicle_name'] = '';
            $pdfDataEntry['equipment_list'][$i]['vehicle_name'] = '';
        }
    }

    /**
     * @return array<int, object>
     */
    private function getLocalStaffList(int $staffType, int $workplaceId, string $workDate, bool $assigned): array
    {
        $staffs = $this->readLocalJson('app/local_staff.json');
        $assignments = $this->readLocalAssignments();
        $result = [];

        foreach ($staffs as $row) {
            if ((int) ($row['staff_type'] ?? 0) !== $staffType) {
                continue;
            }
            $staffId = (int) ($row['id'] ?? 0);
            if ($staffId === 0) {
                continue;
            }

            $isAssignedThis = $this->existsLocalAssignment($assignments, $workDate, $workplaceId, $staffId, (string) config('assignments.master_type.staff'));
            $isAssignedOther = $this->existsLocalAssignmentOtherWorkplace($assignments, $workDate, $workplaceId, $staffId, (string) config('assignments.master_type.staff'));

            if ($isAssignedOther) {
                continue;
            }
            if ($assigned && ! $isAssignedThis) {
                continue;
            }

            $result[] = (object) [
                'staff_id' => $staffId,
                'staff_name' => (string) ($row['staff_name'] ?? ''),
                'staff_type' => $staffType,
                'assignment_flg' => $isAssignedThis ? 1 : 0,
                'sort_number' => (int) ($row['sort_number'] ?? 0),
            ];
        }

        usort($result, fn ($a, $b) => ((int) ($a->sort_number ?? 0)) <=> ((int) ($b->sort_number ?? 0)));
        return $result;
    }

    /**
     * @return array<int, object>
     */
    private function getLocalVehicleList(int $workplaceId, string $workDate, bool $assigned, int $vehicleType): array
    {
        $file = $vehicleType === 2 ? 'app/local_equipments.json' : 'app/local_vehicles.json';
        $vehicles = $this->readLocalJson($file);
        $assignments = $this->readLocalAssignments();
        $masterType = $vehicleType === 2
            ? (string) config('assignments.master_type.equipment')
            : (string) config('assignments.master_type.vehicle');

        $result = [];
        foreach ($vehicles as $row) {
            $vehicleId = (int) ($row['id'] ?? 0);
            if ($vehicleId === 0) {
                continue;
            }
            if (isset($row['vehicle_type']) && (int) $row['vehicle_type'] !== $vehicleType) {
                continue;
            }

            $isAssignedThis = $this->existsLocalAssignment($assignments, $workDate, $workplaceId, $vehicleId, $masterType);
            $isAssignedOther = $this->existsLocalAssignmentOtherWorkplace($assignments, $workDate, $workplaceId, $vehicleId, $masterType);

            if ($isAssignedOther) {
                continue;
            }
            if ($assigned && ! $isAssignedThis) {
                continue;
            }

            $result[] = (object) [
                'vehicle_id' => $vehicleId,
                'vehicle_name' => (string) ($row['vehicle_name'] ?? ''),
                'assignment_flg' => $isAssignedThis ? 1 : 0,
                'sort_number' => (int) ($row['sort_number'] ?? 0),
            ];
        }

        usort($result, fn ($a, $b) => ((int) ($a->sort_number ?? 0)) <=> ((int) ($b->sort_number ?? 0)));
        return $result;
    }

    /**
     * @param  array<int, mixed>  $staffList
     * @param  array<int, mixed>  $vehicleList
     * @param  array<int, mixed>  $equipmentList
     */
    private function updateLocalAssignment(int $workplaceId, string $workDate, array $staffList, array $vehicleList, array $equipmentList): bool
    {
        $rows = $this->readLocalAssignments();
        $targetRows = array_values(array_filter(
            $rows,
            fn ($row) => (string) ($row['work_date'] ?? '') === $workDate
                && (int) ($row['workplace_id'] ?? 0) === $workplaceId
        ));

        $keptRows = array_values(array_filter(
            $rows,
            fn ($row) => ! ((string) ($row['work_date'] ?? '') === $workDate
                && (int) ($row['workplace_id'] ?? 0) === $workplaceId)
        ));

        $nextId = 1;
        foreach ($rows as $row) {
            $nextId = max($nextId, (int) ($row['id'] ?? 0) + 1);
        }

        $apply = function (array $inputList, string $masterType) use (&$targetRows, &$nextId, $workplaceId, $workDate): void {
            foreach ($inputList as $masterId => $assignFlg) {
                $masterId = (int) $masterId;
                if ($masterId === 0) {
                    continue;
                }
                $assign = (int) $assignFlg === 1;

                $currentIdx = null;
                foreach ($targetRows as $idx => $row) {
                    if ((int) ($row['master_id'] ?? 0) === $masterId
                        && (string) ($row['master_type'] ?? '') === $masterType) {
                        $currentIdx = $idx;
                        break;
                    }
                }

                if ($assign && $currentIdx === null) {
                    $targetRows[] = [
                        'id' => $nextId++,
                        'workplace_id' => $workplaceId,
                        'work_date' => $workDate,
                        'master_id' => $masterId,
                        'master_type' => $masterType,
                    ];
                } elseif (! $assign && $currentIdx !== null) {
                    unset($targetRows[$currentIdx]);
                    $targetRows = array_values($targetRows);
                }
            }
        };

        $apply($staffList, (string) config('assignments.master_type.staff'));
        $apply($vehicleList, (string) config('assignments.master_type.vehicle'));
        $apply($equipmentList, (string) config('assignments.master_type.equipment'));

        return $this->writeLocalAssignments(array_values(array_merge($keptRows, $targetRows)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readLocalAssignments(): array
    {
        return $this->readLocalJson(self::LOCAL_ASSIGNMENT_FILE);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function writeLocalAssignments(array $rows): bool
    {
        $path = storage_path(self::LOCAL_ASSIGNMENT_FILE);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return @file_put_contents($path, json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readLocalJson(string $relativePath): array
    {
        $path = storage_path($relativePath);
        if (!is_file($path)) {
            return [];
        }
        $json = @file_get_contents($path);
        if (!is_string($json) || $json === '') {
            return [];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function existsLocalAssignment(array $rows, string $workDate, int $workplaceId, int $masterId, string $masterType): bool
    {
        foreach ($rows as $row) {
            if ((string) ($row['work_date'] ?? '') === $workDate
                && (int) ($row['workplace_id'] ?? 0) === $workplaceId
                && (int) ($row['master_id'] ?? 0) === $masterId
                && (string) ($row['master_type'] ?? '') === $masterType) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function existsLocalAssignmentOtherWorkplace(array $rows, string $workDate, int $workplaceId, int $masterId, string $masterType): bool
    {
        foreach ($rows as $row) {
            if ((string) ($row['work_date'] ?? '') === $workDate
                && (int) ($row['workplace_id'] ?? 0) !== $workplaceId
                && (int) ($row['master_id'] ?? 0) === $masterId
                && (string) ($row['master_type'] ?? '') === $masterType) {
                return true;
            }
        }
        return false;
    }

    /**
     * ローカル: 指定日に配置がある現場一覧（PDF用・DBの v_assignment_staff 代替）
     *
     * @return \Illuminate\Support\Collection<int, object{workplace_id: int, workplace_name: string}>
     */
    private function getAssignedWorkplaceLocal(string $workDate): \Illuminate\Support\Collection
    {
        $assignments = $this->readLocalAssignments();
        $workplaceIds = [];
        foreach ($assignments as $row) {
            if ((string) ($row['work_date'] ?? '') !== $workDate) {
                continue;
            }
            $wid = (int) ($row['workplace_id'] ?? 0);
            if ($wid > 0) {
                $workplaceIds[$wid] = true;
            }
        }

        $workplaces = $this->readLocalJson('app/local_workplaces.json');
        $nameById = [];
        foreach ($workplaces as $w) {
            $id = (int) ($w['id'] ?? 0);
            if ($id > 0) {
                $nameById[$id] = (string) ($w['workplace_name'] ?? '');
            }
        }

        $sortedIds = array_keys($workplaceIds);
        sort($sortedIds, SORT_NUMERIC);

        $list = [];

        foreach ($sortedIds as $wid) {
            $list[] = (object) [
                'workplace_id' => $wid,
                'workplace_name' => $nameById[$wid] ?? ('現場ID '.$wid),
            ];
        }

        return collect($list);
    }
}

