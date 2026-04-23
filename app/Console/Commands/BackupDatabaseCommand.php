<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database
                            {--path= : 保存ディレクトリ（既定: config/backup.php）}
                            {--keep-days= : 保持日数（既定: config/backup.php）}';

    protected $description = 'MySQL/MariaDB/SQLite を gzip で storage に保存し、古いファイルを削除。BACKUP_RSYNC_DEST があれば rsync します。';

    public function handle(): int
    {
        $dir = $this->option('path') ?: config('backup.database.directory');
        $keepDays = (int) ($this->option('keep-days') ?: config('backup.database.keep_days', 365));

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error('バックアップ先ディレクトリを作成できません: '.$dir);

            return self::FAILURE;
        }

        $connectionName = (string) config('database.default');
        $config = config("database.connections.{$connectionName}");
        if (! is_array($config)) {
            $this->error('database 接続設定が見つかりません。');

            return self::FAILURE;
        }

        $driver = $config['driver'] ?? '';
        $stamp = now()->format('Y-m-d_His');
        $basename = 'nakatsuka-backup-'.$stamp;
        $gzPath = $dir.DIRECTORY_SEPARATOR.$basename.'.sql.gz';

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $ok = $this->dumpMysqlFamily($config, $gzPath);
        } elseif ($driver === 'sqlite') {
            $ok = $this->dumpSqlite($config, $gzPath);
        } else {
            $this->warn('未対応の driver ('.$driver.') のためスキップしました。mysql / mariadb / sqlite のみ対応です。');

            return self::FAILURE;
        }

        if (! $ok) {
            return self::FAILURE;
        }

        $this->info('作成: '.$gzPath);

        $this->pruneOldBackups($dir, $keepDays);

        $rsync = config('backup.database.rsync_dest');
        if (is_string($rsync) && $rsync !== '') {
            $this->mirrorWithRsync($gzPath, $rsync);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function dumpMysqlFamily(array $config, string $gzPath): bool
    {
        $binary = config('backup.database.mysqldump_binary', 'mysqldump');
        if (! is_executable((string) $binary) && $binary === 'mysqldump') {
            $found = (new ExecutableFinder)->find('mysqldump');
            if ($found !== null) {
                $binary = $found;
            }
        }

        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');
        $user = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $database = (string) ($config['database'] ?? '');
        $socket = (string) ($config['unix_socket'] ?? '');

        if ($database === '' || $user === '') {
            $this->error('DB_DATABASE / DB_USERNAME が空です。');

            return false;
        }

        $args = array_merge(
            [$binary, '--single-transaction', '--skip-lock-tables', '--default-character-set=utf8mb4', '-u', $user],
            $socket !== '' ? ['-S', $socket] : ['-h', $host, '-P', $port],
            [$database]
        );

        $dump = Process::timeout(3600)
            ->env(['MYSQL_PWD' => $password])
            ->run($args);

        if (! $dump->successful()) {
            $this->error('mysqldump に失敗しました: '.$dump->errorOutput());

            return false;
        }

        $gz = gzencode($dump->output(), 9);
        if ($gz === false) {
            $this->error('gzip 圧縮に失敗しました。');

            return false;
        }

        if (@file_put_contents($gzPath, $gz) === false) {
            $this->error('ファイル書き込みに失敗しました: '.$gzPath);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function dumpSqlite(array $config, string $gzPath): bool
    {
        $database = $config['database'] ?? '';
        if (! is_string($database) || $database === '' || ! is_file($database)) {
            $this->error('SQLite の database パスが無効です。');

            return false;
        }

        $sqlite3 = config('backup.database.sqlite_binary', 'sqlite3');
        if (! is_executable((string) $sqlite3) && $sqlite3 === 'sqlite3') {
            $found = (new ExecutableFinder)->find('sqlite3');
            if ($found !== null) {
                $sqlite3 = $found;
            }
        }

        if (is_executable((string) $sqlite3) || $sqlite3 !== 'sqlite3') {
            $dump = Process::timeout(3600)->run([$sqlite3, $database, '.dump']);
            if (! $dump->successful()) {
                $this->error('sqlite3 .dump に失敗しました: '.$dump->errorOutput());

                return false;
            }
            $payload = $dump->output();
        } else {
            $payload = @file_get_contents($database);
            if (! is_string($payload)) {
                $this->error('SQLite ファイルの読み込みに失敗しました。');

                return false;
            }
        }

        $gz = gzencode($payload, 9);
        if ($gz === false) {
            $this->error('gzip 圧縮に失敗しました。');

            return false;
        }

        if (@file_put_contents($gzPath, $gz) === false) {
            $this->error('ファイル書き込みに失敗しました: '.$gzPath);

            return false;
        }

        return true;
    }

    private function pruneOldBackups(string $dir, int $keepDays): void
    {
        if ($keepDays < 1) {
            return;
        }
        $threshold = now()->subDays($keepDays)->getTimestamp();
        foreach (File::files($dir) as $file) {
            $name = $file->getFilename();
            if (! str_starts_with($name, 'nakatsuka-backup-')) {
                continue;
            }
            if ($file->getMTime() < $threshold) {
                @unlink($file->getPathname());
                $this->line('削除（保持期限超過）: '.$name);
            }
        }
    }

    private function mirrorWithRsync(string $localFile, string $dest): void
    {
        $result = Process::timeout(3600)->run([
            'rsync',
            '-az',
            $localFile,
            rtrim($dest, '/').'/',
        ]);
        if ($result->successful()) {
            $this->info('rsync 完了: '.$dest);
        } else {
            $this->warn('rsync に失敗しました（バックアップファイルはローカルに残っています）: '.$result->errorOutput());
        }
    }
}
