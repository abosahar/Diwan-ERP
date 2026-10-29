<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * «حفظ وإضافة جديد»: a create form posted with the X-Save-And-New header goes back to the
 * resource's own create page after a successful save (route `x.store` → `x.create`) instead of
 * the page the controller chose. Validation errors and routes without a plain create page are untouched.
 */
class SaveAndNew
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! $request->isMethod('POST') || ! $request->header('X-Save-And-New') || ! $response instanceof RedirectResponse) {
            return $response;
        }
        $name = $request->route()?->getName();
        if (! $name || ! Str::endsWith($name, '.store') || $request->session()->get('errors')?->any()) {
            return $response;
        }

        $create = Str::beforeLast($name, '.store').'.create';
        $target = Route::getRoutes()->getByName($create);
        if (! $target || $target->parameterNames() !== []) {
            return $response;
        }

        return redirect()->route($create);
    }
}
