<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The customer's data as it was when the document was created (older documents have none and read the live customer). */
    public function up(): void
    {
        foreach (['invoices', 'credit_notes', 'debit_notes'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->json('customer_snapshot')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'credit_notes', 'debit_notes'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('customer_snapshot'));
        }
    }
};
