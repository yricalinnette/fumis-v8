<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('funds', 'remarks_salary')) {
            Schema::table('funds', function (Blueprint $table) {
                $table->string('remarks_salary')->nullable()->after('remarks');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('funds', 'remarks_salary')) {
            Schema::table('funds', function (Blueprint $table) {
                $table->dropColumn('remarks_salary');
            });
        }
    }
};