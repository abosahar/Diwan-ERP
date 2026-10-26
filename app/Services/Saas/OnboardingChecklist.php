<?php

namespace App\Services\Saas;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\ZatcaDevice;
use App\Services\Tax\CountryFeatures;
use App\Services\Tax\TaxProfile;
use App\Services\Zatca\DocumentMapper;

/**
 * First-run setup steps a new company needs before issuing invoices,
 * shown to its owner on the dashboard until all are done.
 */
class OnboardingChecklist
{
    public function steps(Company $company): array
    {
        $saudi = CountryFeatures::has('zatca');
        $steps = [
            ['key' => 'profile', 'label' => $saudi ? 'بيانات المنشأة والعنوان الوطني والرقم الضريبي' : 'بيانات المنشأة والرقم الضريبي', 'done' => $saudi ? app(DocumentMapper::class)->missingSellerFields($company) === [] : (filled($company->tax_number) && TaxProfile::current()->validTaxNumber($company->tax_number)), 'route' => 'settings.index'],
            ['key' => 'logo', 'label' => 'شعار المنشأة على الفواتير', 'done' => (bool) $company->logoPath(), 'route' => 'settings.index'],
            ['key' => 'zatca', 'label' => 'الربط مع منصة فاتورة (المرحلة الثانية)', 'done' => ZatcaDevice::where('status', 'active')->exists(), 'route' => 'zatca.index'],
            ['key' => 'opening', 'label' => 'الأرصدة الافتتاحية (حسابات، عملاء، موردون، مخزون)', 'done' => JournalEntry::where('entry_type', 'opening')->exists(), 'route' => 'accounting.journals.index'],
            ['key' => 'users', 'label' => 'إضافة المستخدمين وصلاحياتهم', 'done' => User::where('company_id', $company->id)->count() > 1, 'route' => 'users.index'],
            ['key' => 'invoice', 'label' => 'إصدار أول فاتورة', 'done' => Invoice::withoutGlobalScope('branch')->exists(), 'route' => 'sales.invoices.create'],
        ];

        if (! $saudi) {
            $steps = array_values(array_filter($steps, fn ($s) => $s['key'] !== 'zatca'));
        }

        return array_map(fn ($s) => $s + ['url' => route($s['route'], [], false)], $steps);
    }

    /** null once every step is done (nothing to show). */
    public function forDashboard(Company $company): ?array
    {
        if ($company->setting('onboarding_dismissed')) {
            return null;
        }
        $steps = $this->steps($company);
        $done = count(array_filter($steps, fn ($s) => $s['done']));

        return $done === count($steps) ? null : ['steps' => $steps, 'done' => $done, 'total' => count($steps)];
    }
}
