<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_dtrack_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained('funds')->onDelete('cascade');
            $table->string('dtrack_no');
            $table->string('obligation_serial')->nullable();
            $table->decimal('obligation_amount', 15, 2)->default(0.00);
            $table->date('obligation_date')->nullable();
            $table->decimal('disbursement_amount', 15, 2)->default(0.00);
            $table->date('disbursement_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_dtrack_logs');
    }
};