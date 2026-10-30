<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->decimal('price_bdt', 18, 4)->default(0.0000);
            $table->string('billing_interval', 20)->default('monthly');
            $table->jsonb('included_modules')->default('[]');
            $table->integer('max_branches')->default(1);
            $table->integer('max_terminals')->default(1);
            $table->integer('max_users')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
