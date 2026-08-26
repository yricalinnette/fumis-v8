<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funds', function (Blueprint $table) {
            if (!Schema::hasColumn('funds', 'cos_contract_id')) {
                $table->unsignedBigInteger('cos_contract_id')->nullable()->after('remarks_salary');
            }
            if (!Schema::hasColumn('funds', 'disbursed_months')) {
                $table->integer('disbursed_months')->default(0)->after('cos_contract_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('funds', function (Blueprint $table) {
            if (Schema::hasColumn('funds', 'disbursed_months')) {
                $table->dropColumn('disbursed_months');
            }
            if (Schema::hasColumn('funds', 'cos_contract_id')) {
                $table->dropColumn('cos_contract_id');
            }
        });
    }
};