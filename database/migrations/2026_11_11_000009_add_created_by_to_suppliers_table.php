<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds created_by to suppliers for Segregation of Duties Rule SoD-02 (§3.3).
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->uuid('created_by')->nullable()->after('is_active');
            $table->foreign('created_by', 'fk_suppliers_creator')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign('fk_suppliers_creator');
            $table->dropColumn('created_by');
        });
    }
};
