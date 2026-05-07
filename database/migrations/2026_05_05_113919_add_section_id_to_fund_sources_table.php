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
        // Safety Check: Only run if the column does not exist yet
        if (!Schema::hasColumn('source_of_funds', 'section_id')) {
            Schema::table('source_of_funds', function (Blueprint $table) {
                // We use unsignedBigInteger to match common ID formats.
                // It's nullable so existing records don't break.
                $table->unsignedBigInteger('section_id')->nullable()->after('id');
                
                // Add an index for faster searching/filtering by section
                $table->index('section_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // FIX: Changed table name to match 'source_of_funds'
        if (Schema::hasColumn('source_of_funds', 'section_id')) {
            Schema::table('source_of_funds', function (Blueprint $table) {
                $table->dropIndex(['section_id']);
                $table->dropColumn('section_id');
            });
        }
    }
};