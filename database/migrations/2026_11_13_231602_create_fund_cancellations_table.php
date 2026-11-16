<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained('funds')->onDelete('cascade');
            $table->foreignId('fund_multi_activity_id')->nullable()->constrained('fund_multi_activities')->onDelete('cascade');
            $table->foreignId('source_of_fund_id')->constrained('source_of_funds');
            $table->string('dtrack_no', 30)->index();
            $table->string('original_obligation_serial', 50)->nullable();
            $table->string('cancellation_obligation_serial', 50)->index();
            $table->date('cancellation_date')->index();
            $table->decimal('cancelled_amount', 15, 2);
            $table->text('reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_cancellations');
    }
};