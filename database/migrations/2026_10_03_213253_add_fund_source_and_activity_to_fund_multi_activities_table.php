<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_multi_activities', function (Blueprint $table) {
            if (!Schema::hasColumn('fund_multi_activities', 'source_of_fund_id')) {
                $table->foreignId('source_of_fund_id')
                      ->nullable()
                      ->after('fund_id')
                      ->constrained('source_of_funds')
                      ->nullOnDelete();
            }

            if (!Schema::hasColumn('fund_multi_activities', 'transaction_type_id')) {
                $table->foreignId('transaction_type_id')
                      ->nullable()
                      ->after('source_of_fund_id')
                      ->constrained('transaction_types')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fund_multi_activities', function (Blueprint $table) {
            if (Schema::hasColumn('fund_multi_activities', 'source_of_fund_id')) {
                $table->dropForeign(['source_of_fund_id']);
                $table->dropColumn('source_of_fund_id');
            }

            if (Schema::hasColumn('fund_multi_activities', 'transaction_type_id')) {
                $table->dropForeign(['transaction_type_id']);
                $table->dropColumn('transaction_type_id');
            }
        });
    }
};