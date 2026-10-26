<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every financial record belongs to a branch (the branch chosen in the top bar
 * when it was entered). Existing rows take the branch of the document they
 * belong to (payments and notes: their invoice; sessions: their register...),
 * else the company's main branch. Bank accounts stay shared (null) until a
 * branch claims them, so no branch loses its cash accounts.
 */
return new class extends Migration
{
    /** table => [parent table, foreign key in table] used to inherit the branch before the main-branch fallback */
    private array $tables = [
        'customer_payments' => ['invoices', 'invoice_id'],
        'supplier_payments' => ['purchase_invoices', 'purchase_invoice_id'],
        'credit_notes' => ['invoices', 'invoice_id'],
        'debit_notes' => ['invoices', 'invoice_id'],
        'purchase_returns' => ['purchase_invoices', 'purchase_invoice_id'],
        'landed_costs' => ['purchase_invoices', 'purchase_invoice_id'],
        'pos_sessions' => ['pos_registers', 'pos_register_id'],
        'pos_orders' => ['pos_sessions', 'pos_session_id'],
        'expenses' => null,
        'revenues' => null,
        'payrolls' => null,
        'cheques' => null,
        'recurring_journals' => null,
        'bank_accounts' => null,
    ];

    /** journal entry source class => [table, ...] whose branch the entry follows */
    private array $entrySources = [
        'App\\Models\\Invoice' => 'invoices',
        'App\\Models\\PurchaseInvoice' => 'purchase_invoices',
        'App\\Models\\DeliveryNote' => 'delivery_notes',
        'App\\Models\\GoodsReceipt' => 'goods_receipts',
        'App\\Models\\FixedAsset' => 'fixed_assets',
        'App\\Models\\Employee' => 'employees',
    ];

    private function mainBranch(string $table): string
    {
        return "(SELECT b.id FROM branches b WHERE b.company_id = {$table}.company_id AND b.deleted_at IS NULL ORDER BY b.is_main DESC, b.id ASC LIMIT 1)";
    }

    public function up(): void
    {
        foreach ($this->tables as $table => $parent) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'branch_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            });
        }

        // Inherit from the parent document first (parents are filled before their children).
        foreach (['pos_sessions', 'pos_orders', 'customer_payments', 'supplier_payments', 'credit_notes', 'debit_notes', 'purchase_returns', 'landed_costs'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }
            [$parent, $key] = $this->tables[$table];
            if (Schema::hasTable($parent) && Schema::hasColumn($parent, 'branch_id') && Schema::hasColumn($table, $key)) {
                DB::statement("UPDATE {$table} SET branch_id = (SELECT p.branch_id FROM {$parent} p WHERE p.id = {$table}.{$key}) WHERE branch_id IS NULL");
            }
            DB::statement("UPDATE {$table} SET branch_id = ".$this->mainBranch($table).' WHERE branch_id IS NULL');
        }
        foreach (['expenses', 'revenues', 'payrolls', 'cheques', 'recurring_journals'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                DB::statement("UPDATE {$table} SET branch_id = ".$this->mainBranch($table).' WHERE branch_id IS NULL');
            }
        }

        // Journal entries follow their source document, else the main branch.
        if (Schema::hasColumn('journal_entries', 'branch_id')) {
            foreach ($this->entrySources as $class => $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                    $sourceClass = str_replace('\\', '\\\\', $class);
                    DB::statement("UPDATE journal_entries SET branch_id = (SELECT s.branch_id FROM {$table} s WHERE s.id = journal_entries.source_id) WHERE branch_id IS NULL AND source_type = '{$sourceClass}'");
                }
            }
            DB::statement('UPDATE journal_entries SET branch_id = '.$this->mainBranch('journal_entries').' WHERE branch_id IS NULL');
            Schema::table('journal_entries', function (Blueprint $t) {
                $t->index(['company_id', 'branch_id', 'date'], 'journal_entries_branch_date_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('journal_entries', 'branch_id')) {
            Schema::table('journal_entries', fn (Blueprint $t) => $t->dropIndex('journal_entries_branch_date_index'));
        }
        foreach (array_keys($this->tables) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropConstrainedForeignId('branch_id');
                });
            }
        }
    }
};
