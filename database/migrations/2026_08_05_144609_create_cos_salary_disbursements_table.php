<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cos_salary_disbursements', function (Blueprint $table) {
            $table->id();
            // Constrained to the 'funds' table
            $table->foreignId('fund_id')->constrained('funds')->onDelete('cascade');
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->date('disbursement_date')->nullable();
            $table->integer('column_index')->nullable(); // Column index origin (e.g., Index 17, 24, etc.)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cos_salary_disbursements');
    }
};