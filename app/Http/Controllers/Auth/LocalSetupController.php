<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\Platform\CompanyRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * First run of a local install: one form creates the company and its owner,
 * then the page closes for good (it only exists while there is no company).
 * The server answers the whole office network, so until then only the computer
 * it runs on may open the page: another device must not become the owner first.
 */
class LocalSetupController extends Controller
{
    private const ELSEWHERE = 'يُكمَل الإعداد الأول من الجهاز الذي ثُبّت عليه الديوان (اختصار «الديوان» على سطح مكتبه)، ثم تفتحه بقية الأجهزة.';

    public static function isOpen(): bool
    {
        return config('app.local_install') && ! Company::query()->exists();
    }

    /** The connection itself, not a forwarded header: a local install has no proxy in front. */
    protected static function fromThisComputer(Request $request): bool
    {
        return IpUtils::checkIp((string) $request->server('REMOTE_ADDR'), ['127.0.0.0/8', '::1', '::ffff:127.0.0.0/104']);
    }

    public function create(Request $request): Response
    {
        abort_unless(self::isOpen(), 404);
        abort_unless(self::fromThisComputer($request), 403, self::ELSEWHERE);

        return Inertia::render('Auth/LocalSetup', [
            'countries' => collect(config('countries'))->map(fn ($c, $code) => ['code' => $code, 'name' => $c['name'], 'currency' => $c['currency'],
                'tax' => $c['tax']['has'] ? $c['tax']['name'].' '.$c['tax']['standard'].'%' : 'بدون ضريبة قيمة مضافة'])->values(),
            'currencies' => RegisteredUserController::currencies(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(self::isOpen(), 404);
        abort_unless(self::fromThisComputer($request), 403, self::ELSEWHERE);

        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'country_code' => ['required', Rule::in(array_keys(config('countries')))],
            'currency' => ['nullable', 'string', Rule::in(array_column(RegisteredUserController::currencies(), 'code'))],
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'phone' => 'nullable|string|max:20',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // The company is reached by this computer's address, not a subdomain: any free label will do.
        $user = app(CompanyRegistration::class)->register($data + ['subdomain' => 'company-1']);

        Auth::login($user);

        return redirect()->route('dashboard')->with('success', 'تم إعداد منشأتك بنجاح. مرحباً بك في '.config('app.name').'.');
    }
}
