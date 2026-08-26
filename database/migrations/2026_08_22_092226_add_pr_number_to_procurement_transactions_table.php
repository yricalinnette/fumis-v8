<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_transactions', function (Blueprint $table) {
            $table->string('pr_number')->nullable()->after('abc_allotment');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_transactions', function (Blueprint $table) {
            $table->dropColumn('pr_number');
        });
    }
};