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
        Schema::table('source_of_funds', function (Blueprint $table) {
            $table->string('spreadsheet_id')->nullable()->after('name');
            $table->string('sheet_name')->nullable()->after('spreadsheet_id');
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
