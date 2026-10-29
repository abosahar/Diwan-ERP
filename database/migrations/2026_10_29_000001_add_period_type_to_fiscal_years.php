<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // monthly | quarterly | semiannual | semimonthly: how the year is divided into periods.
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->string('period_type', 20)->default('monthly')->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropColumn('period_type');
        });
    }
};
