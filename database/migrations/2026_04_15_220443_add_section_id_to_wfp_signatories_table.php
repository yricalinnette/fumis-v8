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
        Schema::table('wfp_signatories', function (Blueprint $table) {
            // Adding section_id as an unsigned big integer (adjust type if your PK is different)
            $table->unsignedBigInteger('section_id')->nullable()->after('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wfp_signatories', function (Blueprint $table) {
            //
        });
    }
};
