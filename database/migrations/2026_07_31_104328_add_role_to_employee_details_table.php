<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employee_details', 'role')) {
            Schema::table('employee_details', function (Blueprint $table) {
                $table->string('role')->default('staff')->after('dbedid');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_details', 'role')) {
            Schema::table('employee_details', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};