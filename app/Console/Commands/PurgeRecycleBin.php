<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\RecycleBin;
use App\Services\TenantManager;
use Illuminate\Console\Command;

class PurgeRecycleBin extends Command
{
    protected $signature = 'erp:purge-recycle-bin';

    protected $description = 'Erase documents that stayed in the recycle bin longer than the retention period';

    public function handle(TenantManager $tenantManager, RecycleBin $bin): int
    {
        Company::query()->each(function (Company $company) use ($tenantManager, $bin) {
            $n = $tenantManager->runAs($company, fn () => $bin->purgeExpired());
            if ($n) {
                $this->line("{$company->name}: {$n}");
            }
        });

        return self::SUCCESS;
    }
}
