<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class PasswordService
{
    public function update($userId, $password1, $password2)
    {
        try {
            if (!isset($userId) || !isset($password1) || !isset($password2)) {
                return false;
            }

            if ($password1 != $password2) {
                return false;
            }

            // 最低8文字。既存ユーザーのログインには影響せず、新しく設定する時のみ適用。
            if (strlen((string) $password1) < 8) {
                return false;
            }

            DB::beginTransaction();

            DB::table('m_user')
                ->where('id', '=', $userId)
                ->update([
                    'password' => Hash::make($password1),
                    'access_token_web' => null,
                    'access_token_app' => null,
                    'updated_at' => now(),
                ]);

            if (Schema::hasTable('t_web_remember_tokens')) {
                DB::table('t_web_remember_tokens')->where('user_id', $userId)->delete();
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            return false;
        }
    }

    /**
     * 管理者が他ユーザーのログインパスワードを再設定する（Remember / アプリ用トークンも無効化）
     */
    public function adminResetPassword(int $targetUserId, string $password1, string $password2): bool
    {
        if ($password1 !== $password2) {
            return false;
        }

        if (strlen($password1) < 8) {
            return false;
        }

        try {
            DB::beginTransaction();

            $updated = DB::table('m_user')
                ->where('id', '=', $targetUserId)
                ->whereNull('deleted_at')
                ->update([
                    'password' => Hash::make($password1),
                    'access_token_web' => null,
                    'access_token_app' => null,
                    'updated_at' => now(),
                ]);

            if (Schema::hasTable('t_web_remember_tokens')) {
                DB::table('t_web_remember_tokens')->where('user_id', $targetUserId)->delete();
            }

            DB::commit();

            return $updated > 0;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);

            return false;
        }
    }
}
