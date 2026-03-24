<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('funds', function (Blueprint $table) {
            // We use unsignedBigInteger to match the ID type in db_common
            $table->unsignedBigInteger('secid')->nullable()->after('user_id')->index();
        });
    }

    public function down()
    {
        Schema::table('funds', function (Blueprint $table) {
            $table->dropColumn('secid');
        });
    }
};
