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
        Schema::table('source_of_funds', function (Blueprint $table) {
            // Add the foreign key column
            $table->foreignId('budget_line_item_id')
                ->nullable() // Nullable in case some funds don't have a line item yet
                ->after('id') // Position it after the ID column
                ->constrained('budget_line_items')
                ->onDelete('set null'); 
        });
    }

    public function down(): void
    {
        Schema::table('source_of_funds', function (Blueprint $table) {
            $table->dropForeign(['budget_line_item_id']);
            $table->dropColumn('budget_line_item_id');
        });
    }
};
