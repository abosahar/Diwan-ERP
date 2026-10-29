<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerGroup;
use App\Models\JournalEntryLine;
use App\Services\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Customer groups; a group may post its customers' receivables to its own ledger account. */
class CustomerGroupController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'status' => 'nullable|in:active,inactive,all']);
        $status = $filters['status'] ?? 'active';

        $groups = CustomerGroup::query()->with('account:id,code,name_ar')
            ->withCount(['customers', 'customers as active_customers_count' => fn ($q) => $q->where('status', 'active')])
            ->when($status !== 'all', fn ($q) => $q->where('is_active', $status === 'active'))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")))
            ->orderBy('code')->get();

        return Inertia::render('Sales/CustomerGroups/Index', [
            'groups' => $groups,
            'total' => CustomerGroup::count(),
            'active' => CustomerGroup::where('is_active', true)->count(),
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $status],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Sales/CustomerGroups/Form', ['group' => null] + $this->lookups());
    }

    public function edit(CustomerGroup $group): Response
    {
        return Inertia::render('Sales/CustomerGroups/Form', ['group' => $group] + $this->lookups());
    }

    public function store(Request $request)
    {
        $group = CustomerGroup::create($this->validated($request));

        if ($json = $this->createdResponse($request, $group, $group->name)) {
            return $json;
        }

        return redirect()->route('sales.customer-groups.index')->with('success', 'تمت إضافة مجموعة العملاء');
    }

    public function update(Request $request, CustomerGroup $group)
    {
        $data = $this->validated($request, $group);

        // Customers already posted to the old account: moving the group would split their balances.
        if (($data['account_id'] ?? null) != $group->account_id && $group->account_id
            && JournalEntryLine::where('account_id', $group->account_id)->where('party_type', 'customer')->whereIn('party_id', $group->customers()->pluck('id'))->exists()) {
            throw ValidationException::withMessages(['account_id' => 'لا يمكن تغيير حساب المجموعة بعد وجود حركات لعملائها عليه.']);
        }

        $group->update($data);

        return redirect()->route('sales.customer-groups.index')->with('success', 'تم تحديث مجموعة العملاء');
    }

    protected function lookups(): array
    {
        return ['accounts' => Account::where('type', 'asset')->where('is_parent', false)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name_ar'])];
    }

    protected function validated(Request $request, ?CustomerGroup $group = null): array
    {
        $companyId = app(TenantManager::class)->getTenantId();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('customer_groups', 'code')->where('company_id', $companyId)->ignore($group?->id)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'account_id' => ['nullable', 'tenant_exists:accounts,id', function ($attribute, $value, $fail) {
                $account = $value ? Account::find($value) : null;
                if ($account && ($account->type !== 'asset' || $account->is_parent)) {
                    $fail('اختر حسابًا فرعيًا من الأصول (ذمم العملاء).');
                }
            }],
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
