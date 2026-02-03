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
        // 1. Create the new Pivot Table
        Schema::create('employee_fund', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // 2. Safely remove the old creditor_id from 'funds' table
        if (Schema::hasColumn('funds', 'creditor_id')) {
            Schema::table('funds', function (Blueprint $table) {
                // Drop the foreign key constraint first
                $table->dropForeign(['creditor_id']); 
                
                // Now drop the column
                $table->dropColumn('creditor_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_fund');
        
        // Reverse logic: put it back if needed
        Schema::table('funds', function (Blueprint $table) {
            $table->unsignedBigInteger('creditor_id')->nullable();
            $table->foreign('creditor_id')->references('id')->on('employees');
        });
    }
};
