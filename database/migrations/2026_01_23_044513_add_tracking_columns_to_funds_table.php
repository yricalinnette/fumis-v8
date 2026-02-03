<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('funds', function (Blueprint $table) {
            $table->decimal('obligation_amount', 15, 2)->nullable();
            $table->date('obligation_date')->nullable();
            $table->decimal('disbursement_amount', 15, 2)->nullable();
            $table->date('disbursement_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funds', function (Blueprint $table) {
            //
        });
    }
};
