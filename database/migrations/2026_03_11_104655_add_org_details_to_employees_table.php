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
        Schema::table('employees', function (Blueprint $table) {
            // Adding the new columns after the 'position' column
            $table->string('section_name')->nullable()->after('position');
            $table->string('division_name')->nullable()->after('section_name');
        });
    }

    public function down()
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['section_name', 'division_name']);
        });
    }
};
