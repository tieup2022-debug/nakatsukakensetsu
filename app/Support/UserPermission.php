<?php

namespace App\Support;

/**
 * m_user.permission の判定（1=マスター, 2=担当者, 3=利用者）
 */
class UserPermission
{
    public static function normalize(mixed $permission): int
    {
        if ($permission === null || $permission === '') {
            return 0;
        }

        if (is_numeric($permission)) {
            return (int) $permission;
        }

        return 0;
    }

    public static function isMaster(mixed $permission): bool
    {
        return self::normalize($permission) === 1;
    }

    public static function isManager(mixed $permission): bool
    {
        $value = self::normalize($permission);

        return $value === 1 || $value === 2;
    }

    public static function label(mixed $permission): string
    {
        return match (self::normalize($permission)) {
            1 => '1（マスター）',
            2 => '2（担当者）',
            3 => '3（利用者）',
            0 => '未設定',
            default => (string) $permission,
        };
    }
}
