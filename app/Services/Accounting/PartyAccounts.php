<?php

namespace App\Services\Accounting;

use App\Models\Customer;
use App\Models\CustomerGroup;

/**
 * Which receivable account a customer posts to: the account of the customer's
 * group when it has one, else the general "receivables" account. Used by
 * PostingService for every line {role: receivables, party_type: customer}, and by
 * the reports that must add up all receivable accounts.
 */
class PartyAccounts
{
    public function __construct(protected AccountMap $map) {}

    public function receivableFor(int $customerId, int $companyId): ?int
    {
        $groupId = Customer::withoutGlobalScopes()->where('company_id', $companyId)->whereKey($customerId)->value('customer_group_id');
        if (! $groupId) {
            return null;
        }

        return CustomerGroup::withoutGlobalScopes()->where('company_id', $companyId)->whereKey($groupId)->value('account_id');
    }

    /** The general receivables account plus every group account (the whole customer ledger). @return array<int, int> */
    public function receivableAccountIds(?int $companyId = null): array
    {
        $ids = CustomerGroup::query()->whereNotNull('account_id')->pluck('account_id')->all();
        if ($general = $this->map->find('receivables', $companyId)) {
            $ids[] = $general;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
