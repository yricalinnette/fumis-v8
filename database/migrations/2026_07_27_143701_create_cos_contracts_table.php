<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cos_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('creditor_name')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('total_months')->default(0);
            $table->decimal('monthly_remuneration', 12, 2)->default(0.00);
            $table->decimal('premium_amount', 12, 2)->default(0.00);
            $table->decimal('total_contract_amount', 12, 2)->default(0.00);
            $table->string('status', 50)->default('Active'); // Active, Completed, Terminated
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cos_contracts');
    }
};