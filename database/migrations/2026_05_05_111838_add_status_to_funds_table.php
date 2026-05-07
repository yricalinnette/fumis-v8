<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funds', function (Blueprint $table) {
            // Adding 'status' before 'status_date' for better logical grouping
            // We use 'Pending' as a safe default value
            $table->string('status')->default('Pending')->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('funds', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};