<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20)->default('feature');
            $table->string('area', 100)->nullable();
            $table->string('title', 200);
            $table->text('description');
            $table->string('status', 20)->default('new');
            $table->text('admin_note')->nullable();
            $table->string('released_in', 20)->nullable();
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('feature_request_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['feature_request_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_request_votes');
        Schema::dropIfExists('feature_requests');
    }
};
