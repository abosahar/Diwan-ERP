<?php

namespace App\Http\Middleware;

use App\Services\Tax\CountryFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses (404) the pages and actions of a country-specific feature (ZATCA,
 * zakat, GOSI...) while the branch being worked in is in another country.
 */
class EnsureCountryFeature
{
    public function handle(Request $request, Closure $next): Response
    {
        $feature = CountryFeatures::forRoute($request->route()?->getName());
        if ($feature && $request->user() && ! CountryFeatures::has($feature)) {
            abort(404);
        }

        return $next($request);
    }
}
