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
        Schema::create('wfp_signatories', function (Blueprint $table) {
            $table->id();
            // 'program', 'section', 'consolidated', or 'saa'
            $table->string('wfp_type'); 
            $table->string('label'); // e.g., 'Prepared by:', 'Approved by:'
            $table->integer('employee_id'); // ID from db_common.tbl_emp_details
            $table->integer('rank'); // 1, 2, or 3 for horizontal ordering
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wfp_signatories');
    }
};
