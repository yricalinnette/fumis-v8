<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateEmployeeFundTableToUsers extends Migration
{
    public function up()
    {
        Schema::table('employee_fund', function (Blueprint $table) {
            // 1. Only drop if the column exists (prevents the error you just got)
            if (Schema::hasColumn('employee_fund', 'employee_id')) {
                // Try to drop the foreign key first (wrapped in try-catch to be safe)
                try {
                    $table->dropForeign('employee_fund_employee_id_foreign');
                } catch (\Exception $e) {
                    // Foreign key might already be gone
                }
                $table->dropColumn('employee_id');
            }

            // 2. Only add user_id if it doesn't exist yet
            if (!Schema::hasColumn('employee_fund', 'user_id')) {
                $table->foreignId('user_id')->after('fund_id')->nullable()->constrained()->onDelete('cascade');
            }
        });
    }

    public function down()
    {
        Schema::table('employee_fund', function (Blueprint $table) {
            if (Schema::hasColumn('employee_fund', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            $table->unsignedBigInteger('employee_id')->nullable();
        });
    }
}
