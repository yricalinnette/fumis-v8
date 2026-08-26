<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('procurement_transaction_items', function (Blueprint $table) {
            $table->date('po_date')->nullable()->after('po_amount');
        });

        Schema::table('procurement_transactions', function (Blueprint $table) {
            $table->date('po_date')->nullable()->after('po_amount');
        });
    }

    public function down()
    {
        Schema::table('procurement_transaction_items', function (Blueprint $table) {
            $table->dropColumn('po_date');
        });

        Schema::table('procurement_transactions', function (Blueprint $table) {
            $table->dropColumn('po_date');
        });
    }
};
