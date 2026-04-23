<?php

return [

    /*
    |--------------------------------------------------------------------------
    | データベースバックアップ（backup:database）
    |--------------------------------------------------------------------------
    | 本番では cron で `php artisan schedule:run` を毎分実行し、
    | BACKUP_DATABASE_SCHEDULE_ENABLED=true のとき日次で backup:database が走ります。
    | 手動: php artisan backup:database
    */

    'database' => [
        'schedule_enabled' => env('BACKUP_DATABASE_SCHEDULE_ENABLED', false),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
        'directory' => storage_path('app/'.env('BACKUP_DATABASE_SUBDIR', 'database-backups')),
        'rsync_dest' => env('BACKUP_RSYNC_DEST'),
        'mysqldump_binary' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
        'sqlite_binary' => env('BACKUP_SQLITE_PATH', 'sqlite3'),
    ],

];
