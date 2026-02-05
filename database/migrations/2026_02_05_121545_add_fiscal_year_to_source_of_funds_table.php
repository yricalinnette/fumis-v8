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
            $table->integer('fiscal_year')->after('name')->default(2026);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('source_of_funds', function (Blueprint $table) {
            //
        });
    }
};
