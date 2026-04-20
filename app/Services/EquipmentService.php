<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class EquipmentService
{
    private const LOCAL_EQUIPMENT_FILE = 'app/local_equipments.json';
    public function GetEquipment($vehicleId)
    {
        try {
            return DB::table('m_vehicle')
                ->where('id', '=', $vehicleId)
                ->where('vehicle_type', '=', 2)
                ->whereNull('deleted_at')
                ->first();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                foreach ($this->readLocalEquipments() as $row) {
                    if ((int)($row['id'] ?? 0) === (int)$vehicleId) {
                        return (object)$row;
                    }
                }
            }
            return false;
        }
    }

    public function GetEquipmentList()
    {
        try {
            return DB::table('m_vehicle')
                ->where('vehicle_type', '=', 2)
                ->whereNull('deleted_at')
                ->orderBy('sort_number', 'asc')
                ->get();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                return collect($this->readLocalEquipments())->map(fn ($r) => (object)$r);
            }
            return false;
        }
    }

    public function create($vehicleName)
    {
        try {
            if (!isset($vehicleName)) {
                return false;
            }

            DB::beginTransaction();

            DB::table('m_vehicle')->insert([
                'vehicle_type' => 2,
                'vehicle_name' => $vehicleName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $rows = $this->readLocalEquipments();
                $maxId = 0;
                foreach ($rows as $r) $maxId = max($maxId, (int)($r['id'] ?? 0));
                $rows[] = ['id' => $maxId + 1, 'vehicle_type' => 2, 'vehicle_name' => (string)$vehicleName, 'sort_number' => count($rows) + 1];
                return $this->writeLocalEquipments($rows);
            }
            return false;
        }
    }

    public function update($vehicleId, $vehicleName)
    {
        try {
            if (!isset($vehicleId) || !isset($vehicleName)) {
                return false;
            }

            DB::beginTransaction();

            DB::table('m_vehicle')
                ->where('id', '=', $vehicleId)
                ->where('vehicle_type', '=', 2)
                ->update([
                    'vehicle_name' => $vehicleName,
                    'updated_at' => now(),
                ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $rows = $this->readLocalEquipments();
                foreach ($rows as &$r) {
                    if ((int)($r['id'] ?? 0) === (int)$vehicleId) {
                        $r['vehicle_name'] = (string)$vehicleName;
                        unset($r);
                        return $this->writeLocalEquipments($rows);
                    }
                }
                unset($r);
            }
            return false;
        }
    }

    public function delete($vehicleId)
    {
        try {
            if (!isset($vehicleId)) {
                return false;
            }

            DB::beginTransaction();

            DB::table('m_vehicle')
                ->where('id', '=', $vehicleId)
                ->where('vehicle_type', '=', 2)
                ->whereNull('deleted_at')
                ->delete();

            DB::table('t_assignment')
                ->where('master_id', '=', $vehicleId)
                ->where('master_type', '=', config('assignments.master_type.equipment'))
                ->whereNull('deleted_at')
                ->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $rows = array_values(array_filter($this->readLocalEquipments(), fn ($r) => (int)($r['id'] ?? 0) !== (int)$vehicleId));
                return $this->writeLocalEquipments($rows);
            }
            return false;
        }
    }

    public function Sort($sortNumberList)
    {
        try {
            if (!isset($sortNumberList)) {
                return false;
            }

            DB::beginTransaction();
            foreach ($sortNumberList as $vehicleId => $sortNumber) {
                DB::table('m_vehicle')
                    ->where('id', '=', $vehicleId)
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
                $rows = $this->readLocalEquipments();
                foreach ($rows as &$r) {
                    $id = (int)($r['id'] ?? 0);
                    if (isset($sortNumberList[$id])) $r['sort_number'] = (int)$sortNumberList[$id];
                }
                unset($r);
                return $this->writeLocalEquipments($rows);
            }
            return false;
        }
    }

    private function readLocalEquipments(): array
    {
        $path = storage_path(self::LOCAL_EQUIPMENT_FILE);
        if (!is_file($path)) return [];
        $json = @file_get_contents($path);
        $arr = is_string($json) ? json_decode($json, true) : [];
        return is_array($arr) ? $arr : [];
    }

    private function writeLocalEquipments(array $rows): bool
    {
        usort($rows, fn ($a, $b) => ((int)($a['sort_number'] ?? 0)) <=> ((int)($b['sort_number'] ?? 0)));
        $path = storage_path(self::LOCAL_EQUIPMENT_FILE);
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return @file_put_contents($path, json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
    }
}

