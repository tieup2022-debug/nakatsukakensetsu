<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class WorkplaceService
{
    private const LOCAL_WORKPLACE_FILE = 'app/local_workplaces.json';

    /**
     * 現場 取得
     */
    public function getWorkplace($workplaceId)
    {
        try {
            if (isset($workplaceId)) {
                return DB::table('m_workplace')
                    ->where('id', '=', $workplaceId)
                    ->whereNull('deleted_at')
                    ->first();
            }

            return false;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                foreach ($this->readLocalWorkplaces() as $row) {
                    if ((int) ($row['id'] ?? 0) === (int) $workplaceId && empty($row['deleted_at'])) {
                        return (object) $row;
                    }
                }
            }

            return false;
        }
    }

    /**
     * 現場 一覧取得
     */
    public function getWorkplaceList($activeFlg)
    {
        try {
            return DB::table('m_workplace')
                ->when($activeFlg, function ($query) {
                    return $query->where('active_flg', '=', true);
                })
                ->when(! $activeFlg, function ($query) {
                    return $query->where('active_flg', '=', false);
                })
                ->whereNull('deleted_at')
                ->get();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $rows = array_values(array_filter(
                    $this->readLocalWorkplaces(),
                    fn ($r) => (bool) ($r['active_flg'] ?? false) === (bool) $activeFlg && empty($r['deleted_at'])
                ));

                return collect($rows)->map(fn ($r) => (object) $r);
            }

            return false;
        }
    }

    /**
     * 現場 新規登録
     */
    public function create($workplaceName)
    {
        try {
            if (isset($workplaceName)) {
                DB::beginTransaction();

                DB::table('m_workplace')
                    ->insert([
                        'workplace_name' => $workplaceName,
                        'active_flg' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::commit();

                return true;
            }

            return false;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $rows = $this->readLocalWorkplaces();
                $maxId = 0;
                foreach ($rows as $r) {
                    $maxId = max($maxId, (int) ($r['id'] ?? 0));
                }
                $rows[] = [
                    'id' => $maxId + 1,
                    'workplace_name' => (string) $workplaceName,
                    'active_flg' => true,
                    'deleted_at' => null,
                ];

                return $this->writeLocalWorkplaces($rows);
            }

            return false;
        }
    }

    /**
     * 現場削除時の件数（確認ダイアログ用）
     *
     * @param  array<int|string>  $workplaceIds
     * @return array<string, array{attendance: int, assignment: int}>
     */
    public function getDeletionImpactCounts(array $workplaceIds): array
    {
        $workplaceIds = array_values(array_unique(array_filter($workplaceIds, fn ($id) => $id !== null && $id !== '')));
        if ($workplaceIds === []) {
            return [];
        }

        try {
            $attendance = DB::table('t_attendance')
                ->selectRaw('workplace_id, COUNT(*) as c')
                ->whereIn('workplace_id', $workplaceIds)
                ->whereNull('deleted_at')
                ->groupBy('workplace_id')
                ->pluck('c', 'workplace_id');

            $assignment = DB::table('t_assignment')
                ->selectRaw('workplace_id, COUNT(*) as c')
                ->whereIn('workplace_id', $workplaceIds)
                ->whereNull('deleted_at')
                ->groupBy('workplace_id')
                ->pluck('c', 'workplace_id');

            $out = [];
            foreach ($workplaceIds as $id) {
                $key = (string) $id;
                $out[$key] = [
                    'attendance' => (int) ($attendance[$id] ?? $attendance[(string) $id] ?? 0),
                    'assignment' => (int) ($assignment[$id] ?? $assignment[(string) $id] ?? 0),
                ];
            }

            return $out;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $out = [];
                foreach ($workplaceIds as $id) {
                    $out[(string) $id] = ['attendance' => 0, 'assignment' => 0];
                }

                return $out;
            }

            return [];
        }
    }

    /**
     * 現場 編集
     */
    public function update($workplaceId, $workplaceName, $activeFlg)
    {
        try {
            if (isset($workplaceId) && isset($workplaceName)) {
                DB::beginTransaction();

                DB::table('m_workplace')
                    ->where('id', '=', $workplaceId)
                    ->whereNull('deleted_at')
                    ->update([
                        'workplace_name' => $workplaceName,
                        'active_flg' => $activeFlg,
                        'updated_at' => now(),
                    ]);

                DB::commit();

                return true;
            }

            return false;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $rows = $this->readLocalWorkplaces();
                foreach ($rows as &$r) {
                    if ((int) ($r['id'] ?? 0) === (int) $workplaceId && empty($r['deleted_at'])) {
                        $r['workplace_name'] = (string) $workplaceName;
                        $r['active_flg'] = (bool) $activeFlg;
                        $ok = $this->writeLocalWorkplaces($rows);
                        unset($r);

                        return $ok;
                    }
                }
                unset($r);
            }

            return false;
        }
    }

    /**
     * 現場 削除（論理削除：勤怠・配置もまとめて非表示）
     */
    public function delete($workplaceId)
    {
        try {
            if (isset($workplaceId)) {
                DB::beginTransaction();

                $now = now();

                $workplaceRows = DB::table('m_workplace')
                    ->where('id', '=', $workplaceId)
                    ->whereNull('deleted_at')
                    ->update([
                        'deleted_at' => $now,
                        'updated_at' => $now,
                    ]);

                if ($workplaceRows === 0) {
                    DB::rollBack();

                    return false;
                }

                DB::table('t_assignment')
                    ->where('workplace_id', '=', $workplaceId)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => $now, 'updated_at' => $now]);

                DB::table('t_attendance')
                    ->where('workplace_id', '=', $workplaceId)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => $now, 'updated_at' => $now]);

                DB::commit();

                return true;
            }

            return false;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $rows = $this->readLocalWorkplaces();
                foreach ($rows as &$r) {
                    if ((int) ($r['id'] ?? 0) === (int) $workplaceId) {
                        $r['deleted_at'] = now()->toIso8601String();
                        $ok = $this->writeLocalWorkplaces($rows);
                        unset($r);

                        return $ok;
                    }
                }
                unset($r);

                return false;
            }

            return false;
        }
    }

    private function readLocalWorkplaces(): array
    {
        $path = storage_path(self::LOCAL_WORKPLACE_FILE);
        if (! is_file($path)) {
            return [];
        }
        $json = @file_get_contents($path);
        $arr = is_string($json) ? json_decode($json, true) : [];

        return is_array($arr) ? $arr : [];
    }

    private function writeLocalWorkplaces(array $rows): bool
    {
        $path = storage_path(self::LOCAL_WORKPLACE_FILE);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return @file_put_contents($path, json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
    }
}
