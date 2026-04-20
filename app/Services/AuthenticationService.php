<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthenticationService
{
    /**
     * ログイン処理
     */
    public function Login($loginId, $password)
    {
        try {
            if (isset($loginId) && isset($password)) {
                $user = DB::table('m_user')
                    ->where('login_id', '=', $loginId)
                    ->whereNull('deleted_at')
                    ->first();

                if ($user && Hash::check($password, $user->password)) {
                    return $user;
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
     * トークン生成
     */
    public function GenerateToken($userId, $appFlag)
    {
        try {
            if (isset($userId)) {
                DB::beginTransaction();

                $column = $appFlag ? 'access_token_app' : 'access_token_web';

                $token = Str::random(64);

                DB::table('m_user')
                    ->where('id', '=', $userId)
                    ->whereNull('deleted_at')
                    ->update([
                        $column => $token,
                        'updated_at' => now(),
                    ]);

                DB::commit();

                return $token;
            }

            return false;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            return false;
        }
    }

    /**
     * ユーザーIDからユーザーデータを取得
     */
    public function GetToken($userId)
    {
        try {
            if (isset($userId)) {
                $user = DB::table('m_user')
                    ->where('id', '=', $userId)
                    ->whereNull('deleted_at')
                    ->first();

                if ($user) {
                    return $user;
                }

                return false;
            }

            return false;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            return false;
        }
    }
}

