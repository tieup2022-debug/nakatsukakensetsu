<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class UtilizationRateService
{
    public function GetUtilizationRateList($year, $type, $sort, $direction)
    {
        try {
            $data = [];

            $vehicleList = DB::table('m_vehicle')
                ->whereNull('deleted_at')
                ->orderBy('vehicle_type', 'asc')
                ->orderBy('sort_number', 'asc')
                ->get();

            foreach ($vehicleList as $vehicle) {
                $data[$vehicle->id]['name'] = $vehicle->vehicle_name;
                $data[$vehicle->id]['type'] = $vehicle->vehicle_type == '1' ? '車両' : '重機';
            }

            $yearTotalDays = 0;
            $yearTotalCount = [];

            for ($month = 1; $month <= 12; $month++) {
                $startDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
                $days = (int)date('t', strtotime($startDate));
                $endDate = date('Y-m-t', strtotime($startDate));

                $yearTotalDays += $days;

                foreach ($vehicleList as $vehicle) {
                    if (!isset($yearTotalCount[$vehicle->id])) {
                        $yearTotalCount[$vehicle->id] = 0;
                    }

                    $count = DB::table('t_assignment')
                        ->where('master_id', $vehicle->id)
                        ->whereBetween('work_date', [$startDate, $endDate])
                        ->whereIn('master_type', [
                            config('assignments.master_type.vehicle'),
                            config('assignments.master_type.equipment'),
                        ])
                        ->whereNull('deleted_at')
                        ->count();

                    $yearTotalCount[$vehicle->id] += $count;
                    $rate = ($days > 0) ? round($count / $days * 100, 2) : 0;
                    $data[$vehicle->id][$month] = $rate;
                }
            }

            foreach ($vehicleList as $vehicle) {
                $totalCount = $yearTotalCount[$vehicle->id] ?? 0;
                $data[$vehicle->id]['total'] =
                    $yearTotalDays > 0 ? round($totalCount / $yearTotalDays * 100, 2) : 0;
            }

            return $data;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            return false;
        }
    }

    public function GetSelectYearList()
    {
        try {
            return DB::table('t_assignment')
                ->selectRaw('YEAR(work_date) AS year')
                ->whereIn('master_type', [
                    config('assignments.master_type.vehicle'),
                    config('assignments.master_type.equipment'),
                ])
                ->whereNull('deleted_at')
                ->groupBy(DB::raw('YEAR(work_date)'))
                ->pluck('year')
                ->toArray();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            return false;
        }
    }
}

