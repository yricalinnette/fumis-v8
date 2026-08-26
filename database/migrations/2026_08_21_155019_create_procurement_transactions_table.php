<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_of_fund_id'); // Foreign key to fund sources
            $table->unsignedBigInteger('activity_id');         // Foreign key to activities
            
            // PR Header Details
            $table->string('item_name')->nullable();
            $table->string('end_user')->nullable();
            $table->decimal('abc_allotment', 12, 2)->default(0.00);
            
            // Procurement Tracking
            $table->text('po_particulars')->nullable();
            $table->string('po_number')->nullable();
            $table->text('remarks')->nullable();
            $table->string('caf_status')->default('Pending');
            $table->timestamp('caf_date')->nullable();
            
            $table->timestamps();

            // Foreign Key Constraints
            $table->foreign('source_of_fund_id')->references('id')->on('source_of_funds')->onDelete('cascade');
            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_transactions');
    }
};