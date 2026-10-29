<?php

use App\Models\Company;
use App\Services\HR\NationalityCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nationalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 2);
            $table->string('name_ar', 100);
            $table->string('name_en', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Company::query()->each(fn (Company $company) => app(NationalityCatalog::class)->seed($company));
    }

    public function down(): void
    {
        Schema::dropIfExists('nationalities');
    }
};
