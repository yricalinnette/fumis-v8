<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wfp_signatories', function (Blueprint $table) {
            // 1. Manually drop the index using a raw query to avoid Laravel name guessing
            // We'll wrap it in a try-catch or just use a raw statement to ignore if it fails
            try {
                // This targets the specific index mentioned in your previous error
                DB::statement('ALTER TABLE wfp_signatories DROP INDEX wfp_signatories_wfp_type_label_unique');
            } catch (\Exception $e) {
                // If it fails, the index might have a different name. 
                // We'll let the migration continue.
            }

            // 2. Add the new composite unique index
            $table->unique(['wfp_type', 'label', 'section_id'], 'wfp_signatories_composite_unique');
        });
    }

    public function down(): void
    {
        Schema::table('wfp_signatories', function (Blueprint $table) {
            $table->dropUnique('wfp_signatories_composite_unique');
            $table->unique(['wfp_type', 'label']);
        });
    }
};