<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    private const LOCAL_USER_FILE = 'app/local_users.json';

    public function GetUser($userId)
    {
        try {
            return DB::table('m_user')
                ->where('id', '=', $userId)
                ->whereNull('deleted_at')
                ->first();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                foreach ($this->readLocalUsers() as $row) {
                    if ((int)($row['id'] ?? 0) === (int)$userId) {
                        return (object)$row;
                    }
                }
            }
            return false;
        }
    }

    public function GetUserList()
    {
        try {
            return DB::table('m_user')
                ->where('hidden_flg', '=', 0)
                ->whereNull('deleted_at')
                ->orderBy('permission', 'ASC')
                ->get();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                return collect($this->readLocalUsers())->map(fn ($r) => (object)$r);
            }
            return false;
        }
    }

    public function create($userName, $loginId, $permission)
    {
        try {
            if (!isset($userName) || !isset($loginId) || !isset($permission)) {
                return false;
            }

            $loginIdCheck = DB::table('m_user')
                ->where('login_id', '=', $loginId)
                ->whereNull('deleted_at')
                ->exists();

            if ($loginIdCheck) {
                return false;
            }

            DB::beginTransaction();

            DB::table('m_user')->insert([
                'user_name' => $userName,
                'login_id' => $loginId,
                'password' => Hash::make('0000'),
                'permission' => $permission,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $rows = $this->readLocalUsers();
                foreach ($rows as $r) {
                    if (($r['login_id'] ?? null) === (string)$loginId) {
                        return false;
                    }
                }
                $maxId = 0;
                foreach ($rows as $r) {
                    $maxId = max($maxId, (int)($r['id'] ?? 0));
                }
                $rows[] = [
                    'id' => $maxId + 1,
                    'user_name' => (string)$userName,
                    'login_id' => (string)$loginId,
                    'permission' => (int)$permission,
                    'hidden_flg' => 0,
                ];
                return $this->writeLocalUsers($rows);
            }
            return false;
        }
    }

    public function update($userId, $userName, $loginId, $permission)
    {
        try {
            if (!isset($userId) || !isset($userName) || !isset($loginId) || !isset($permission)) {
                return false;
            }

            $loginIdCheck = DB::table('m_user')
                ->where('id', '<>', $userId)
                ->where('login_id', '=', $loginId)
                ->whereNull('deleted_at')
                ->exists();

            if ($loginIdCheck) {
                return false;
            }

            DB::beginTransaction();

            DB::table('m_user')
                ->where('id', '=', $userId)
                ->update([
                    'user_name' => $userName,
                    'login_id' => $loginId,
                    'permission' => $permission,
                    'updated_at' => now(),
                ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $rows = $this->readLocalUsers();
                foreach ($rows as $r) {
                    if ((int)($r['id'] ?? 0) !== (int)$userId && ($r['login_id'] ?? null) === (string)$loginId) {
                        return false;
                    }
                }
                foreach ($rows as &$r) {
                    if ((int)($r['id'] ?? 0) === (int)$userId) {
                        $r['user_name'] = (string)$userName;
                        $r['login_id'] = (string)$loginId;
                        $r['permission'] = (int)$permission;
                        unset($r);
                        return $this->writeLocalUsers($rows);
                    }
                }
                unset($r);
            }
            return false;
        }
    }

    public function delete($userId)
    {
        try {
            if (!isset($userId)) {
                return false;
            }

            DB::beginTransaction();

            DB::table('m_user')
                ->where('id', '=', $userId)
                ->whereNull('deleted_at')
                ->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            if (app()->environment('local')) {
                $rows = array_values(array_filter(
                    $this->readLocalUsers(),
                    fn ($r) => (int)($r['id'] ?? 0) !== (int)$userId
                ));
                return $this->writeLocalUsers($rows);
            }
            return false;
        }
    }

    private function readLocalUsers(): array
    {
        $path = storage_path(self::LOCAL_USER_FILE);
        if (!is_file($path)) {
            return [];
        }
        $json = @file_get_contents($path);
        $arr = is_string($json) ? json_decode($json, true) : [];
        return is_array($arr) ? $arr : [];
    }

    private function writeLocalUsers(array $rows): bool
    {
        usort($rows, fn ($a, $b) => ((int)($a['permission'] ?? 0)) <=> ((int)($b['permission'] ?? 0)));
        $path = storage_path(self::LOCAL_USER_FILE);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return @file_put_contents($path, json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
    }
}

