<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up() {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            // Link to the Fund Sources table
            $table->foreignId('source_of_fund_id')->constrained('source_of_funds')->onDelete('cascade');
            $table->string('name');
            $table->decimal('budget', 15, 2); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
