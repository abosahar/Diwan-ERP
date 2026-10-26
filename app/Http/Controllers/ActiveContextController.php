<?php

namespace App\Http\Controllers;

use App\Services\ActiveBranch;
use App\Services\ActiveFiscalYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The top bar selects: working branch and fiscal year.
 */
class ActiveContextController extends Controller
{
    public function branch(Request $request, ActiveBranch $active): RedirectResponse
    {
        $request->validate(['branch_id' => ['required']]);
        $value = $request->input('branch_id');

        if (! $active->switchTo($value === ActiveBranch::ALL ? ActiveBranch::ALL : (int) $value)) {
            return back()->with('error', 'لا تملك صلاحية العمل على هذا الفرع.');
        }

        return $this->backToSafePage($request);
    }

    public function fiscalYear(Request $request, ActiveFiscalYear $active): RedirectResponse
    {
        $request->validate(['fiscal_year_id' => ['required', 'integer']]);

        if (! $active->switchTo((int) $request->input('fiscal_year_id'))) {
            return back()->with('error', 'السنة المالية غير موجودة.');
        }

        return back();
    }

    /**
     * Back to the page the user was on. A record page (a number in its path)
     * may belong to the other branch, so those go to the dashboard.
     */
    protected function backToSafePage(Request $request): RedirectResponse
    {
        $previous = parse_url((string) url()->previous(), PHP_URL_PATH) ?: '/';
        if (preg_match('#/\d+(/|$)#', $previous)) {
            return redirect()->route('dashboard');
        }

        return back();
    }
}
