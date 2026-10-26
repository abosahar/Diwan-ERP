<?php

namespace App\Services\Platform;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Permissions\PermissionRegistry;
use App\Services\TenantManager;
use App\Support\CurrencyNames;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a company with everything it needs to start: main branch, base
 * currency, tax settings, chart of accounts, roles and its owner.
 *
 * On the platform a new company starts a trial of the popular plan. On a
 * local install (one office, its own computer) it is active with no plan
 * limits, and the owner's email counts as verified: there is no mail there.
 */
class CompanyRegistration
{
    /**
     * @param  array{company_name: string, subdomain: string, name: string, email: string, phone?: ?string, country_code?: ?string, currency?: ?string, password: string}  $data
     */
    public function register(array $data): User
    {
        $local = (bool) config('app.local_install');

        return DB::transaction(function () use ($data, $local) {
            // New companies try the most popular plan; they choose a plan when subscribing.
            $basicPlan = $local ? null : (SubscriptionPlan::where('is_active', true)->where('is_popular', true)->first()
                ?? SubscriptionPlan::where('slug', 'basic')->first());

            $countryCode = ($data['country_code'] ?? null) ?: 'SA';
            $country = config("countries.{$countryCode}");
            $selectedCurrencyCode = ($data['currency'] ?? null) ?: $country['currency'];
            [$currencyName, , $currencySymbol] = CurrencyNames::for($selectedCurrencyCode);

            $company = Company::create([
                'name' => $data['company_name'],
                'subdomain' => strtolower($data['subdomain']),
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'status' => $local ? 'active' : 'trial',
                'trial_ends_at' => $local ? null : now()->addDays($basicPlan?->trial_days ?: 14),
                'subscription_plan_id' => $basicPlan?->id,
                'currency' => $selectedCurrencyCode,
                'country_code' => $countryCode,
                'timezone' => $country['timezone'],
                'language' => 'ar',
                'is_verified' => $local,
            ]);

            // Activate the new company as the current tenant immediately so that
            // every tenant-scoped record created below (branch, currency, user)
            // is unambiguously attached to it, regardless of any fallback tenant
            // resolution middleware may have applied to this (pre-login) request.
            app(TenantManager::class)->setTenant($company);

            $branch = Branch::create([
                'company_id' => $company->id,
                'name_ar' => 'الفرع الرئيسي',
                'name_en' => 'Main Branch',
                'code' => 'BR-01',
                'phone' => $data['phone'] ?? null,
                'is_main' => true,
                'is_active' => true,
            ]);

            Currency::create([
                'company_id' => $company->id,
                'name_ar' => $currencyName,
                'name_en' => $selectedCurrencyCode,
                'code' => $selectedCurrencyCode,
                'symbol_ar' => $currencySymbol,
                'symbol_en' => $selectedCurrencyCode,
                'exchange_rate' => 1.000000,
                'is_default' => true,
            ]);

            $company->setSetting('tax_rate', (string) $country['tax']['standard'], 'finance', 'string');
            $company->setSetting('tax_enabled', $country['tax']['has'] ? '1' : '0', 'finance', 'boolean');
            $company->setSetting('invoice_prefix', 'INV-', 'sales', 'string');

            $user = (new User)->forceFill([
                'company_id' => $company->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'branch_id' => $branch->id,
                'password' => Hash::make($data['password']),
                'status' => 'active',
                'terms_accepted_at' => now(), // PDPL: consent to terms and privacy policy
                'email_verified_at' => $local ? now() : null,
            ]);
            $user->save();

            // Chart of accounts, account mappings and fiscal year.
            app(AccountingSetupService::class)->provision($company);

            // Per-company roles (owner + defaults) and owner assignment.
            $registry = app(PermissionRegistry::class);
            $registry->seedCompanyRoles($company);
            $user->assignRole($registry->ownerRole());

            return $user;
        });
    }
}
