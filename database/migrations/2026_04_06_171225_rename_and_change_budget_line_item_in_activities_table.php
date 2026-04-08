<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // We remove the renameColumn line because it already happened!
        Schema::table('activities', function (Blueprint $table) {
            // We only change the type to the correct integer format
            // Adding ->nullable() helps avoid "Data Truncated" errors if the column is empty
            $table->unsignedBigInteger('budget_line_item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('budget_line_item_id')->change();
        });
    }

    
};