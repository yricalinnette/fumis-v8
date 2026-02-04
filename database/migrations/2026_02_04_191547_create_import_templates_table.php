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
        Schema::create('import_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_name')->nullable(); // Add ->nullable()
            $table->integer('header_row')->default(15); // The row where headers start
            // The values here will be the exact header names in Excel
            $table->string('budget_line_col')->default('BUDGET LINE ITEM');
            $table->string('objective_col')->default('OBJECTIVE');
            $table->string('activity_col')->default('ACTIVITIES TO ATTAIN THE SUCESS INDICATORS');
            $table->string('budget_col')->default('COST');
            $table->string('source_col')->default('SOURCE OF FUND');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_templates');
    }
};
