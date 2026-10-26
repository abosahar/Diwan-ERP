<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Platform\CompanyRegistration;
use App\Support\CurrencyNames;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            // The country sets the base currency, the tax and the time zone.
            'countries' => collect(config('countries'))->map(fn ($c, $code) => ['code' => $code, 'name' => $c['name'], 'currency' => $c['currency'],
                'currency_name' => $c['currency_name'], 'symbol' => $c['symbol'], 'tax' => $c['tax']['has'] ? $c['tax']['name'].' '.$c['tax']['standard'].'%' : 'بدون ضريبة قيمة مضافة'])->values(),
            'currencies' => self::currencies(),
        ]);
    }

    /** Currencies a company can keep its books in: the Arab currencies plus USD / EUR. */
    public static function currencies(): array
    {
        return collect(config('countries'))->pluck('currency')->merge(['USD', 'EUR'])->unique()
            ->map(fn ($code) => ['code' => $code, 'name' => CurrencyNames::for($code)[0], 'symbol' => CurrencyNames::for($code)[2]])->values()->all();
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $reservedSubdomains = config('tenancy.reserved_subdomains', []);

        $request->validate([
            'company_name' => 'required|string|max:255',
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/',
                Rule::notIn($reservedSubdomains),
                'unique:companies,subdomain',
            ],
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'phone' => 'required|string|max:20',
            'country_code' => ['nullable', Rule::in(array_keys(config('countries')))],
            'currency' => ['nullable', 'string', Rule::in(array_column(self::currencies(), 'code'))],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => 'accepted',
        ], [
            'terms.accepted' => 'يجب الموافقة على الشروط والأحكام وسياسة الخصوصية.',
            'subdomain.regex' => 'النطاق الفرعي يجب أن يحتوي على أحرف إنجليزية وأرقام وعلامة (-) فقط ولا يبدأ أو ينتهي بشرطة.',
            'subdomain.not_in' => 'هذا النطاق الفرعي محجوز من قبل إدارة النظام.',
            'subdomain.unique' => 'هذا النطاق الفرعي مسجل بالفعل، يرجى اختيار نطاق آخر.',
        ]);

        $user = app(CompanyRegistration::class)->register($request->only(['company_name', 'subdomain', 'name', 'email', 'phone', 'country_code', 'currency', 'password']));

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false))->with('success', 'تم إنشاء منشأتك بنجاح! مرحباً بك في '.config('app.name').'.');
    }
}
