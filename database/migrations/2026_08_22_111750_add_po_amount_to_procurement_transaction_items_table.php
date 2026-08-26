<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_transaction_items', function (Blueprint $table) {
            $table->decimal('po_amount', 15, 2)->nullable()->after('po_dtrack_no');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_transaction_items', function (Blueprint $table) {
            $table->dropColumn('po_amount');
        });
    }
};