<?php

use App\Models\Company;
use App\Services\Accounting\AccountingSetupService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('tax_type', 20)->default('vat');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('output_amount', 15, 2)->default(0);
            $table->decimal('input_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'tax_type', 'start_date', 'end_date']);
        });

        // Existing charts get the «VAT payable» settlement account.
        foreach (Company::cursor() as $company) {
            app(AccountingSetupService::class)->provision($company);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_settlements');
    }
};
