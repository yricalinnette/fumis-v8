<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('fund_transaction_id')->nullable()->after('id');
            // Optional: add foreign key constraint if your funds table uses unsignedBigInteger for id
            // $table->foreign('fund_transaction_id')->references('id')->on('funds')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_transactions', function (Blueprint $table) {
            $table->dropColumn('fund_transaction_id');
        });
    }
};