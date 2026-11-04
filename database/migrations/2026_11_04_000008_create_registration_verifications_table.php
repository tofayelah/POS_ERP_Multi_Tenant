<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email', 255)->index();
            $table->string('phone', 50)->index();
            $table->string('email_token', 100)->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('phone_otp_hash', 255);
            $table->string('raw_otp', 10)->nullable();
            $table->timestampTz('phone_verified_at')->nullable();
            $table->jsonb('payload');
            $table->timestampTz('expires_at');
            $table->integer('attempts')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_verifications');
    }
};
