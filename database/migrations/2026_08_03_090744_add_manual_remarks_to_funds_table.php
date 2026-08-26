<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('funds', 'manual_remarks')) {
            Schema::table('funds', function (Blueprint $table) {
                $table->text('manual_remarks')->nullable()->after('remarks');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('funds', 'manual_remarks')) {
            Schema::table('funds', function (Blueprint $table) {
                $table->dropColumn('manual_remarks');
            });
        }
    }
};