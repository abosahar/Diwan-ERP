<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 10)->default('flat'); // flat | tiered
            $table->decimal('rate', 7, 4)->default(0); // % of net sales (flat)
            $table->json('tiers')->nullable(); // [{from, rate}] — each rate applies to the sales above `from` (marginal)
            $table->decimal('target_amount', 15, 2)->nullable();
            $table->decimal('bonus_amount', 15, 2)->nullable(); // paid when the month's net sales reach the target
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('commission_plan_id')->nullable()->constrained('commission_plans')->nullOnDelete();
        });

        // A month's commission is sent to payroll once per salesperson.
        Schema::create('commission_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 15, 2);
            $table->foreignId('employee_adjustment_id')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_postings');
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('commission_plan_id'));
        Schema::dropIfExists('commission_plans');
    }
};
