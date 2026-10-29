<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per branch closed inside a fiscal year (the year itself closes when every branch has).
        Schema::create('fiscal_year_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreignId('closing_entry_id')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable();
            $table->timestamps();

            $table->unique(['fiscal_year_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_year_closings');
    }
};
