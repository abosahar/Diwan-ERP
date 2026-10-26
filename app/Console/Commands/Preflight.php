<?php

namespace App\Console\Commands;

use App\Services\Platform\HealthCheck;
use Illuminate\Console\Command;

/**
 * Go-live checklist for a production server. Run it after every deploy:
 * it fails on settings that are unsafe or that silently stop automation.
 */
class Preflight extends Command
{
    protected $signature = 'erp:preflight';

    protected $description = 'Check that the production configuration is safe and complete';

    public function handle(HealthCheck $health): int
    {
        $errors = [];
        $warnings = [];
        $production = app()->environment('production');

        $check = function (bool $ok, string $message, bool $blocking = true) use (&$errors, &$warnings) {
            if ($ok) {
                return;
            }
            if ($blocking) {
                $errors[] = $message;
            } else {
                $warnings[] = $message;
            }
        };

        $check($production, 'APP_ENV is not production.');
        $check(! config('app.debug'), 'APP_DEBUG is on: error pages reveal secrets.');
        $check((bool) config('app.key'), 'APP_KEY is not set (php artisan key:generate).');
        $check(config('security.force_https') || str_starts_with((string) config('app.url'), 'https://'), 'HTTPS is not enforced (FORCE_HTTPS).');
        $check((bool) config('session.secure'), 'SESSION_SECURE_COOKIE is off.', false);
        $check((bool) config('backup.password'), 'BACKUP_PASSWORD is not set: backups will not run in production.');
        $check((bool) config('backup.offsite_disk'), 'No off-site backup (BACKUP_OFFSITE_DISK).', false);
        $check(config('queue.default') !== 'sync', 'QUEUE_CONNECTION=sync: tax-authority submissions and mail slow every request.');
        $check(! config('tenancy.allow_dev_override'), 'TENANCY_ALLOW_DEV_OVERRIDE is on.', false);
        $check((bool) config('tenancy.reject_unknown_hosts'), 'TENANCY_REJECT_UNKNOWN_HOSTS is off: password reset links can be forged.');
        $check((bool) config('tenancy.enforce_subdomain'), 'TENANCY_ENFORCE_SUBDOMAIN is off: all companies share one domain.', false);
        $check(config('tenancy.central_domain') !== 'localhost', 'APP_DOMAIN is not set.');
        $check(! in_array(config('mail.default'), ['log', 'array'], true), 'MAIL_MAILER does not send real mail (log/array): verification and password reset emails will not arrive.');
        $check(config('app.timezone') === 'Asia/Riyadh', 'APP_TIMEZONE is not Asia/Riyadh.', false);
        $check(config('logging.default') === 'daily' || in_array('daily', (array) config('logging.channels.stack.channels'), true), 'Logs do not rotate daily (LOG_STACK=daily).', false);
        $check(is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')), 'storage or bootstrap/cache is not writable.');
        $check(app()->configurationIsCached(), 'Configuration is not cached (php artisan optimize).', false);

        foreach ($health->run()['checks'] as $name => $result) {
            $check($result['ok'], "Check {$name}: {$result['detail']}", $name === 'database');
        }

        foreach ($warnings as $w) {
            $this->warn('Warning: '.$w);
        }
        foreach ($errors as $e) {
            $this->error('Error: '.$e);
        }

        if ($errors) {
            $this->error(count($errors).' problem(s) block production use.');

            return self::FAILURE;
        }

        $this->info('Ready for production'.($warnings ? ' (with '.count($warnings).' warning(s)).' : '.'));

        return self::SUCCESS;
    }
}
