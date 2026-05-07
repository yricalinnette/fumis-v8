<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add the column if it doesn't exist
        if (!Schema::hasColumn('wfp_signatories', 'section_id')) {
            Schema::table('wfp_signatories', function (Blueprint $table) {
                // Adding the column first. Adjust 'after' as needed.
                $table->unsignedBigInteger('section_id')->nullable()->after('label');
            });
        }

        // Step 2: Handle the Index update
        Schema::table('wfp_signatories', function (Blueprint $table) {
            try {
                // Drop the old index if it exists
                DB::statement('ALTER TABLE wfp_signatories DROP INDEX wfp_signatories_wfp_type_label_unique');
            } catch (\Exception $e) {
                // Index might not exist or has a different name; continue safely.
            }

            // Step 3: Now that the column exists, create the composite unique index
            $table->unique(['wfp_type', 'label', 'section_id'], 'wfp_signatories_composite_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wfp_signatories', function (Blueprint $table) {
            // Remove the composite index
            $table->dropUnique('wfp_signatories_composite_unique');
            
            // Restore the old unique constraint
            $table->unique(['wfp_type', 'label'], 'wfp_signatories_wfp_type_label_unique');
            
            // Remove the column
            $table->dropColumn('section_id');
        });
    }
};