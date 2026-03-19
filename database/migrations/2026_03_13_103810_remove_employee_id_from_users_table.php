<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Add this

class RemoveEmployeeIdFromUsersTable extends Migration
{
    public function up()
    {
        // 1. Disable foreign key checks temporarily
        Schema::disableForeignKeyConstraints();

        Schema::table('users', function (Blueprint $table) {
            // 2. Drop the column directly
            // We skip dropForeign() since the constraint name is missing
            $table->dropColumn('employee_id');
        });

        // 3. Re-enable checks
        Schema::enableForeignKeyConstraints();
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->nullable()->after('id');
        });
    }
}