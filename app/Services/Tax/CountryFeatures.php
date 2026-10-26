<?php

namespace App\Services\Tax;

/**
 * Which country-specific features (config/country_features.php) the branch
 * being worked in has. Used by the menus, the pages and the server.
 */
class CountryFeatures
{
    public static function enabled(?TaxProfile $profile = null): array
    {
        $profile ??= TaxProfile::current();

        return array_keys(array_filter(config('country_features'), fn (array $rule) => self::matches($rule, $profile)));
    }

    public static function has(string $feature, ?TaxProfile $profile = null): bool
    {
        return in_array($feature, self::enabled($profile), true);
    }

    /** The feature a route belongs to, when it belongs to one. */
    public static function forRoute(?string $route): ?string
    {
        if (! $route) {
            return null;
        }
        foreach (config('country_features') as $feature => $rule) {
            foreach ($rule['routes'] as $name) {
                if ($route === $name || (str_ends_with($name, '.') && str_starts_with($route, $name))) {
                    return $feature;
                }
            }
        }

        return null;
    }

    protected static function matches(array $rule, TaxProfile $profile): bool
    {
        if (isset($rule['countries']) && ! in_array($profile->countryCode, $rule['countries'], true)) {
            return false;
        }
        if (isset($rule['einvoicing']) && ! in_array($profile->einvoicing(), $rule['einvoicing'], true)) {
            return false;
        }
        if (isset($rule['has_tax']) && $profile->hasTax() !== $rule['has_tax']) {
            return false;
        }
        if (isset($rule['national_address']) && $profile->requiresNationalAddress() !== $rule['national_address']) {
            return false;
        }

        return true;
    }
}
