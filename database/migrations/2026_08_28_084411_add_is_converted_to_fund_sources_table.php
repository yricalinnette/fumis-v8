<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('source_of_funds', function (Blueprint $table) {
            $table->boolean('is_converted')->default(false)->after('is_active');
        });
    }

    public function down()
    {
        Schema::table('source_of_funds', function (Blueprint $table) {
            $table->dropColumn('is_converted');
        });
    }
};
