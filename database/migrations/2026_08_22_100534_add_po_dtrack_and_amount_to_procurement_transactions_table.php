<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_transactions', function (Blueprint $table) {
            $table->string('po_dtrack_no')->nullable()->after('po_number');
            $table->decimal('po_amount', 15, 2)->nullable()->after('po_dtrack_no');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_transactions', function (Blueprint $table) {
            $table->dropColumn(['po_dtrack_no', 'po_amount']);
        });
    }
};