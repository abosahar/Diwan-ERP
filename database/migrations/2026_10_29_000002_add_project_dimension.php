<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The project is an analytical dimension of a ledger line, next to the cost centre. */
    public function up(): void
    {
        foreach (['journal_entry_lines', 'expenses', 'revenues'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['journal_entry_lines', 'expenses', 'revenues'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('project_id');
            });
        }
    }
};
