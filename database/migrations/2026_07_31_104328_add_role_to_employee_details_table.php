<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employee_details', function (Blueprint $table) {
            // Adds 'role' column defaulting to 'staff'
            $table->string('role')->default('staff')->after('dbedid');
        });
    }

    public function down(): void
    {
        Schema::table('employee_details', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};