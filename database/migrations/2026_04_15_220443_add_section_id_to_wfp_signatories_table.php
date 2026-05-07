<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if the column was already added by the previous migration
        if (!Schema::hasColumn('wfp_signatories', 'section_id')) {
            Schema::table('wfp_signatories', function (Blueprint $table) {
                $table->unsignedBigInteger('section_id')->nullable()->after('employee_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wfp_signatories', function (Blueprint $table) {
            // Only drop if it exists, to avoid errors during rollback
            if (Schema::hasColumn('wfp_signatories', 'section_id')) {
                $table->dropColumn('section_id');
            }
        });
    }
};