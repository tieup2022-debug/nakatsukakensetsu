<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StaffService
{
    private const LOCAL_STAFF_FILE = 'app/local_staff.json';
    /**
     * 社員 取得
     */
    public function GetStaff($staffId)
    {
        try {
            return DB::table('m_staff')
                ->where('id', '=', $staffId)
                ->whereNull('deleted_at')
                ->first();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                foreach ($this->readLocalStaffs() as $staff) {
                    if ((int)($staff['id'] ?? 0) === (int)$staffId) {
                        return (object) $staff;
                    }
                }
            }
            return false;
        }
    }

    /**
     * 社員 一覧取得
     */
    public function GetStaffList()
    {
        try {
            return DB::table('m_staff')
                ->whereNull('deleted_at')
                ->orderBy('sort_number', 'asc')
                ->get();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                return collect($this->readLocalStaffs())->map(fn ($s) => (object) $s);
            }
            return false;
        }
    }

    /**
     * 社員 新規登録
     */
    public function create($staffName, $staffType)
    {
        try {
            if (!isset($staffName) || !isset($staffType)) {
                return false;
            }

            DB::beginTransaction();

            DB::table('m_staff')->insert([
                'staff_name' => $staffName,
                'staff_type' => $staffType,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $list = $this->readLocalStaffs();
                $maxId = 0;
                foreach ($list as $row) {
                    $maxId = max($maxId, (int) ($row['id'] ?? 0));
                }
                $list[] = [
                    'id' => $maxId + 1,
                    'staff_name' => (string) $staffName,
                    'staff_type' => (int) $staffType,
                    'sort_number' => count($list) + 1,
                ];
                return $this->writeLocalStaffs($list);
            }
            return false;
        }
    }

    /**
     * 社員 編集
     */
    public function update($staffId, $staffName, $staffType)
    {
        try {
            if (!isset($staffId) || !isset($staffName) || !isset($staffType)) {
                return false;
            }

            DB::beginTransaction();

            DB::table('m_staff')
                ->where('id', '=', $staffId)
                ->update([
                    'staff_name' => $staffName,
                    'staff_type' => $staffType,
                    'updated_at' => now(),
                ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $list = $this->readLocalStaffs();
                foreach ($list as &$row) {
                    if ((int)($row['id'] ?? 0) === (int)$staffId) {
                        $row['staff_name'] = (string) $staffName;
                        $row['staff_type'] = (int) $staffType;
                        unset($row);
                        return $this->writeLocalStaffs($list);
                    }
                }
                unset($row);
            }
            return false;
        }
    }

    /**
     * 社員 削除
     */
    public function delete($staffId)
    {
        try {
            if (!isset($staffId)) {
                return false;
            }

            DB::beginTransaction();

            DB::table('m_staff')
                ->where('id', '=', $staffId)
                ->whereNull('deleted_at')
                ->delete();

            DB::table('t_assignment')
                ->where('master_id', '=', $staffId)
                ->where('master_type', '=', config('assignments.master_type.staff'))
                ->whereNull('deleted_at')
                ->delete();

            DB::table('t_attendance')
                ->where('staff_id', '=', $staffId)
                ->whereNull('deleted_at')
                ->delete();

            DB::table('m_user')
                ->where('staff_id', '=', $staffId)
                ->whereNull('deleted_at')
                ->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $list = array_values(array_filter(
                    $this->readLocalStaffs(),
                    fn ($row) => (int)($row['id'] ?? 0) !== (int)$staffId
                ));
                return $this->writeLocalStaffs($list);
            }
            return false;
        }
    }

    /**
     * ソート順変更
     */
    public function Sort($sortNumberList)
    {
        try {
            if (!isset($sortNumberList)) {
                return false;
            }

            DB::beginTransaction();
            foreach ($sortNumberList as $staffId => $sortNumber) {
                DB::table('m_staff')
                    ->where('id', '=', $staffId)
                    ->update([
                        'sort_number' => $sortNumber,
                        'updated_at' => now(),
                    ]);
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $list = $this->readLocalStaffs();
                foreach ($list as &$row) {
                    $id = (int)($row['id'] ?? 0);
                    if (isset($sortNumberList[$id])) {
                        $row['sort_number'] = (int)$sortNumberList[$id];
                    }
                }
                unset($row);
                return $this->writeLocalStaffs($list);
            }
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readLocalStaffs(): array
    {
        $path = storage_path(self::LOCAL_STAFF_FILE);
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
     * @param array<int, array<string, mixed>> $rows
     */
    private function writeLocalStaffs(array $rows): bool
    {
        usort($rows, fn ($a, $b) => ((int)($a['sort_number'] ?? 0)) <=> ((int)($b['sort_number'] ?? 0)));
        $path = storage_path(self::LOCAL_STAFF_FILE);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return @file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
    }
}

