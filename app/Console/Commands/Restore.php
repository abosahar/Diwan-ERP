<?php

namespace App\Console\Commands;

use App\Services\Platform\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Restores an erp:backup archive over the current database and uploaded
 * files. The site is put in maintenance mode while it runs.
 */
class Restore extends Command
{
    protected $signature = 'erp:restore {file : Archive name on the backup disk (see storage/app/backups)} {--disk= : Disk to read from (default: BACKUP_DISK; e.g. offsite)} {--force : Do not ask for confirmation}';

    protected $description = 'Restore the database and uploaded files from an encrypted backup';

    public function handle(BackupService $backups): int
    {
        $this->warn('Everything in the database and the uploaded files will be replaced by the backup: '.$this->argument('file'));
        if (! $this->option('force') && ! $this->confirm('Are you sure? Back up the current state first (erp:backup --tag=prerestore).')) {
            return self::FAILURE;
        }

        Artisan::call('down', ['--retry' => 60]);
        try {
            $result = $backups->restore($this->argument('file'), $this->option('disk'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->warn('The site is still in maintenance mode. Fix the cause and try again, or run php artisan up.');

            return self::FAILURE;
        }

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('optimize:clear');
        Artisan::call('up');

        $this->info("Restored the backup of {$result['created_at']} ({$result['tables']} tables).");
        if (! $result['key_matches']) {
            $this->error('The backup\'s application key differs from the current APP_KEY. Put this value in .env, then run php artisan optimize:clear; otherwise the ZATCA keys and encrypted data cannot be read:');
            $this->line('APP_KEY='.$result['app_key']);
        }

        return self::SUCCESS;
    }
}
