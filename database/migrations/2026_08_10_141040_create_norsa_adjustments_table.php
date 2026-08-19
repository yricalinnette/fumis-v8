<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('norsa_adjustments', function (Blueprint $table) {
            $table->id();
            
            // Foreign Key to the parent Fund record
            $table->foreignId('fund_id')->constrained('funds')->onDelete('cascade');
            
            // Tracking & Audit fields
            $table->string('dtrack_no')->nullable();
            $table->string('obligation_serial')->nullable(); // Matches ORS number (e.g., 02-101101-2026-05-00004)
            $table->string('creditor')->nullable();
            $table->text('particulars')->nullable();
            $table->date('entry_date')->nullable(); // Transaction/Entry date in RAODS
            
            // Financial Amount (Stores positive savings magnitude)
            $table->decimal('amount', 15, 2); 
            
            // Foreign Key to Fund Source Configuration
            $table->foreignId('source_of_fund_id')->nullable()->constrained('source_of_funds')->onDelete('set null');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('norsa_adjustments');
    }
};