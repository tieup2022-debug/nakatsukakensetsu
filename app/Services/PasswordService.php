<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

            DB::beginTransaction();

            DB::table('m_user')
                ->where('id', '=', $userId)
                ->update([
                    'password' => Hash::make($password1),
                    'updated_at' => now(),
                ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            return false;
        }
    }
}

