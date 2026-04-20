<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LinkUserService
{
    public function GetLinkUser($userId, $staffId)
    {
        try {
            DB::beginTransaction();

            $user = DB::table('m_user')
                ->where('id', '=', $userId)
                ->whereNull('deleted_at')
                ->first();

            if ($staffId === 'unset') {
                $newStaffId = null;
            } else {
                $staff = DB::table('m_staff')
                    ->where('id', '=', $staffId)
                    ->whereNull('deleted_at')
                    ->first();
                $newStaffId = $staff ? $staff->id : null;
            }

            if ($user) {
                DB::table('m_user')
                    ->where('id', '=', $userId)
                    ->update([
                        'staff_id' => $newStaffId,
                        'updated_at' => now(),
                    ]);

                DB::commit();
                return true;
            }

            DB::rollback();
            return false;
        } catch (\Exception $e) {
            DB::rollback();
            error($e, __FILE__, __METHOD__, __LINE__);
            return false;
        }
    }

    public function GetUnlinkedStaff()
    {
        try {
            return DB::table('m_staff')
                ->whereNull('deleted_at')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('m_user')
                        ->whereColumn('m_user.staff_id', 'm_staff.id');
                })
                ->get();
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            return false;
        }
    }

    public function GetLinkedStaff($userId)
    {
        try {
            $user = DB::table('m_user')
                ->where('id', '=', $userId)
                ->whereNull('deleted_at')
                ->first();

            if (!$user || empty($user->staff_id)) {
                return false;
            }

            $staff = DB::table('m_staff')
                ->where('id', '=', $user->staff_id)
                ->whereNull('deleted_at')
                ->first();

            if ($staff) {
                return $staff;
            }

            return false;
        } catch (\Exception $e) {
            error($e, __FILE__, __METHOD__, __LINE__);
            return false;
        }
    }
}

