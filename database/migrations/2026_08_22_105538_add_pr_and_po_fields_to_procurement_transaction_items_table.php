<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_transaction_items', function (Blueprint $table) {
            $table->string('pr_number')->nullable()->after('pr_item_name');
            $table->string('po_number')->nullable()->after('item_amount');
            $table->string('po_dtrack_no')->nullable()->after('po_number');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_transaction_items', function (Blueprint $table) {
            $table->dropColumn(['pr_number', 'po_number', 'po_dtrack_no']);
        });
    }
};