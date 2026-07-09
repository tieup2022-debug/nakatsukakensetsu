<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class BackupDoctorCommand extends Command
{
    protected $signature = 'backup:doctor';

    protected $description = 'DBバックアップの設定・mysqldump・保存先・直近ファイル・定期実行の状態を確認します';

    public function handle(): int
    {
        $ok = true;

        $this->line('--- 設定 ---');
        $scheduleEnabled = (bool) config('backup.database.schedule_enabled');
        $this->line('BACKUP_DATABASE_SCHEDULE_ENABLED: '.($scheduleEnabled ? 'true（日次 03:15）' : 'false（定期バックアップ無効）'));
        if (! $scheduleEnabled) {
            $this->warn('  → 本番 .env に BACKUP_DATABASE_SCHEDULE_ENABLED=true を設定してください。');
            $ok = false;
        }

        $keepDays = (int) config('backup.database.keep_days', 365);
        $dir = (string) config('backup.database.directory');
        $this->line('保持日数 (BACKUP_KEEP_DAYS): '.$keepDays);
        $this->line('保存先: '.$dir);

        $rsync = config('backup.database.rsync_dest');
        if (is_string($rsync) && $rsync !== '') {
            $this->line('外部 rsync 先: '.$rsync);
        }

        $this->newLine();
        $this->line('--- mysqldump ---');
        $driver = config('database.connections.'.config('database.default').'.driver');
        if ($driver === 'sqlite') {
            $this->line('driver: sqlite（sqlite3 でバックアップ）');
        } else {
            $binary = $this->resolveMysqldumpPath();
            if ($binary === null) {
                $this->error('mysqldump が見つかりません。.env の BACKUP_MYSQLDUMP_PATH を設定してください。');
                $ok = false;
            } else {
                $this->info('mysqldump: '.$binary);
            }
        }

        $this->newLine();
        $this->line('--- 保存ディレクトリ ---');
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error('ディレクトリを作成できません: '.$dir);
            $ok = false;
        } elseif (! is_writable($dir)) {
            $this->error('ディレクトリに書き込めません: '.$dir);
            $ok = false;
        } else {
            $this->info('書き込み OK');
        }

        $this->newLine();
        $this->line('--- 直近のバックアップ ---');
        $files = collect(File::exists($dir) ? File::files($dir) : [])
            ->filter(fn ($f) => str_starts_with($f->getFilename(), 'nakatsuka-backup-'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->take(5);

        if ($files->isEmpty()) {
            $this->warn('バックアップファイルがありません。php artisan backup:database を実行してください。');
            $ok = false;
        } else {
            foreach ($files as $file) {
                $sizeKb = round($file->getSize() / 1024);
                $this->line(sprintf(
                    '  %s  (%s KB)  %s',
                    $file->getFilename(),
                    number_format($sizeKb),
                    date('Y-m-d H:i:s', $file->getMTime())
                ));
            }
        }

        $this->newLine();
        $this->line('--- cron (schedule:run) ---');
        $cronLine = $this->suggestedCronLine();
        if ($this->cronHasScheduleRun()) {
            $this->info('crontab に schedule:run が登録されています。');
        } else {
            $this->warn('crontab に schedule:run がありません（定期バックアップは動きません）。');
            $this->line('Xserver の cron 設定、または SSH で crontab -e に以下を追加:');
            $this->line('  '.$cronLine);
            $ok = false;
        }

        $this->newLine();
        if ($ok) {
            $this->info('バックアップ設定は問題なさそうです。');

            return self::SUCCESS;
        }

        $this->warn('要対応項目があります。上記を確認してください。');

        return self::FAILURE;
    }

    private function resolveMysqldumpPath(): ?string
    {
        $configured = config('backup.database.mysqldump_binary', 'mysqldump');
        if (is_string($configured) && $configured !== '' && is_executable($configured)) {
            return $configured;
        }

        $candidates = [
            'mysqldump',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/opt/mysql/bin/mysqldump',
        ];

        $finder = new ExecutableFinder;
        foreach ($candidates as $candidate) {
            if ($candidate === 'mysqldump') {
                $found = $finder->find('mysqldump');
                if ($found !== null) {
                    return $found;
                }

                continue;
            }
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function cronHasScheduleRun(): bool
    {
        $process = Process::fromShellCommandline('crontab -l 2>/dev/null');
        $process->run();

        if (! $process->isSuccessful()) {
            return false;
        }

        return str_contains($process->getOutput(), 'schedule:run');
    }

    private function suggestedCronLine(): string
    {
        $php = PHP_BINARY;
        $root = base_path();

        return "* * * * * cd {$root} && {$php} artisan schedule:run >> {$root}/storage/logs/schedule-run.log 2>&1";
    }
}
