<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_of_funds', function (Blueprint $table) {
            // 1. Drop the old unique constraint that only looks at the Name
            // If this fails, check your table structure for the exact index name
            $table->dropUnique('source_of_funds_name_unique');

            // 2. Add the new composite unique constraint
            $table->unique(['name', 'fiscal_year'], 'uidx_name_fiscal_year');
        });
    }

    public function down(): void
    {
        Schema::table('source_of_funds', function (Blueprint $table) {
            $table->dropUnique('uidx_name_fiscal_year');
            $table->unique('name'); // Restore the old one if rolled back
        });
    }
};