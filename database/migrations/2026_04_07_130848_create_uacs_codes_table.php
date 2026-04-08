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
        Schema::create('uacs_codes', function (Blueprint $table) {
            $table->id();
            $table->string('uacs_code')->unique(); // e.g., 5020301000
            $table->string('account_title');       // e.g., Office Supplies Expenses
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uacs_codes');
    }
};
