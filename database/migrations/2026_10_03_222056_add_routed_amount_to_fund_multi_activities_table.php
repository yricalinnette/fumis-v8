<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_multi_activities', function (Blueprint $table) {
            $table->decimal('routed_amount', 15, 2)->default(0.00)->after('dtrack_no');
        });
    }

    public function down(): void
    {
        Schema::table('fund_multi_activities', function (Blueprint $table) {
            $table->dropColumn('routed_amount');
        });
    }
};
