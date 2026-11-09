<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_multi_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained('funds')->onDelete('cascade');
            $table->string('dtrack_no')->nullable()->index();
            $table->string('obligation_serial')->nullable()->index();
            $table->string('creditor')->nullable();
            $table->text('particulars')->nullable();
            $table->decimal('obligation_amount', 15, 2)->default(0.00);
            $table->decimal('disbursement_amount', 15, 2)->default(0.00);
            $table->string('status')->nullable();
            $table->foreignId('cos_contract_id')->nullable()->constrained('cos_contracts')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_multi_activities');
    }
};