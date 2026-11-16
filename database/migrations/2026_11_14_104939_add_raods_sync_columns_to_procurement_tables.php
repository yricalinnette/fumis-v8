<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('procurement_transactions', 'obligation_serial')) {
                $table->string('obligation_serial', 50)->nullable()->index()->after('po_date');
            }
            if (!Schema::hasColumn('procurement_transactions', 'obligation_amount')) {
                $table->decimal('obligation_amount', 15, 2)->default(0)->after('obligation_serial');
            }
            if (!Schema::hasColumn('procurement_transactions', 'obligation_date')) {
                $table->date('obligation_date')->nullable()->after('obligation_amount');
            }
            if (!Schema::hasColumn('procurement_transactions', 'disbursement_amount')) {
                $table->decimal('disbursement_amount', 15, 2)->default(0)->after('obligation_date');
            }
            if (!Schema::hasColumn('procurement_transactions', 'disbursement_date')) {
                $table->date('disbursement_date')->nullable()->after('disbursement_amount');
            }
            if (!Schema::hasColumn('procurement_transactions', 'status')) {
                $table->string('status', 50)->default('Routed')->after('disbursement_date');
            }
        });

        Schema::table('procurement_transaction_items', function (Blueprint $table) {
            if (!Schema::hasColumn('procurement_transaction_items', 'obligation_serial')) {
                $table->string('obligation_serial', 50)->nullable()->index()->after('po_date');
            }
            if (!Schema::hasColumn('procurement_transaction_items', 'obligation_amount')) {
                $table->decimal('obligation_amount', 15, 2)->default(0)->after('obligation_serial');
            }
            if (!Schema::hasColumn('procurement_transaction_items', 'obligation_date')) {
                $table->date('obligation_date')->nullable()->after('obligation_amount');
            }
            if (!Schema::hasColumn('procurement_transaction_items', 'disbursement_amount')) {
                $table->decimal('disbursement_amount', 15, 2)->default(0)->after('obligation_date');
            }
            if (!Schema::hasColumn('procurement_transaction_items', 'disbursement_date')) {
                $table->date('disbursement_date')->nullable()->after('disbursement_amount');
            }
            if (!Schema::hasColumn('procurement_transaction_items', 'status')) {
                $table->string('status', 50)->default('Routed')->after('disbursement_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'obligation_serial',
                'obligation_amount',
                'obligation_date',
                'disbursement_amount',
                'disbursement_date',
                'status',
            ]);
        });

        Schema::table('procurement_transaction_items', function (Blueprint $table) {
            $table->dropColumn([
                'obligation_serial',
                'obligation_amount',
                'obligation_date',
                'disbursement_amount',
                'disbursement_date',
                'status',
            ]);
        });
    }
};