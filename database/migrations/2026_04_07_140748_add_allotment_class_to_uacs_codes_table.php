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
        Schema::table('uacs_codes', function (Blueprint $table) {
            // PS, MOOE, CO, FinEx
            $table->string('allotment_class', 10)->after('account_title')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uacs_codes', function (Blueprint $table) {
            //
        });
    }
};
