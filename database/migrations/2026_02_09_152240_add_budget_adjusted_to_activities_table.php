<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('activities', function (Blueprint $table) {
            // We default it to the current budget so no logic breaks
            $table->decimal('budget_adjusted', 15, 2)->after('budget')->nullable();
        });

        // Sync existing data: Set adjusted budget to equal original budget initially
        DB::statement('UPDATE activities SET budget_adjusted = budget WHERE budget_adjusted IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            //
        });
    }
};
