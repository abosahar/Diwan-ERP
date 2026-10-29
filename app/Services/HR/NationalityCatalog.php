<?php

namespace App\Services\HR;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Nationality;

/** Gives a company its starting list of nationalities (idempotent). */
class NationalityCatalog
{
    public function seed(Company $company): void
    {
        $sort = 1;
        foreach (config('nationalities') as $code => [$ar, $en]) {
            Nationality::withoutGlobalScopes()->firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name_ar' => $ar, 'name_en' => $en, 'is_active' => true, 'sort' => $sort],
            );
            $sort++;
        }

        // Codes already used by employees stay selectable even when not in the catalogue.
        Employee::withoutGlobalScopes()->where('company_id', $company->id)->whereNotNull('nationality')->distinct()->pluck('nationality')
            ->map(fn ($c) => strtoupper((string) $c))->filter()->unique()->each(function ($code) use ($company, &$sort) {
                Nationality::withoutGlobalScopes()->firstOrCreate(
                    ['company_id' => $company->id, 'code' => $code],
                    ['name_ar' => $code, 'name_en' => $code, 'is_active' => true, 'sort' => $sort++],
                );
            });
    }
}
