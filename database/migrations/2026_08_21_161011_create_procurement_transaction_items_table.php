<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('procurement_transaction_id');
            $table->string('pr_item_name');
            $table->decimal('item_amount', 12, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('procurement_transaction_id')
                ->references('id')
                ->on('procurement_transactions')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_transaction_items');
    }
};
