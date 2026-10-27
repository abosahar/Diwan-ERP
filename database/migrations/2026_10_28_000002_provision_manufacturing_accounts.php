<?php

use App\Models\Company;
use App\Services\Accounting\AccountingSetupService;
use Illuminate\Database\Migrations\Migration;

/** Existing companies get the work-in-progress and manufacturing absorption accounts. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Company::cursor() as $company) {
            app(AccountingSetupService::class)->provision($company);
        }
    }

    public function down(): void {}
};
