<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\CompanySetting;
use App\Services\TenantManager;

/**
 * Whether a cost centre / a project must be chosen on a line the user distributes
 * (a journal line, an expense or revenue voucher). Two company settings, one per
 * dimension:  optional  (never required),  required  (always), or  pnl  (only when
 * the account is a revenue / expense account). Settlement lines made by the system
 * (bank, cash, VAT, parties), opening balances and system documents are exempt, and
 * entries already posted are never touched by a policy change.
 */
class DimensionPolicy
{
    public const MODES = ['optional', 'required', 'pnl'];

    public const DIMENSIONS = ['cost_center' => 'مركز التكلفة', 'project' => 'المشروع'];

    public function __construct(protected TenantManager $tenants) {}

    public function mode(string $dimension, ?int $companyId = null): string
    {
        $companyId ??= $this->tenants->getTenantId();
        $value = CompanySetting::withoutGlobalScopes()
            ->where('company_id', $companyId)->where('key', "{$dimension}_policy")->value('value');

        return in_array($value, self::MODES, true) ? $value : 'optional';
    }

    /** @return array{cost_center: string, project: string} */
    public function modes(?int $companyId = null): array
    {
        return ['cost_center' => $this->mode('cost_center', $companyId), 'project' => $this->mode('project', $companyId)];
    }

    public function required(Account $account, string $dimension, ?int $companyId = null): bool
    {
        if ($dimension === 'cost_center' && $account->requires_cost_center) {
            return true;
        }

        return match ($this->mode($dimension, $companyId ?? $account->company_id)) {
            'required' => true,
            'pnl' => in_array($account->type, ['revenue', 'expense'], true),
            default => false,
        };
    }

    public function set(string $dimension, string $mode, int $companyId): void
    {
        CompanySetting::updateOrCreate(
            ['company_id' => $companyId, 'key' => "{$dimension}_policy"],
            ['group' => 'accounting', 'value' => $mode, 'type' => 'string'],
        );
    }
}
