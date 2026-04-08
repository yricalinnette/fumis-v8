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
        Schema::table('activities', function (Blueprint $table) {
            // We make it nullable in case old records don't have a match yet
            $table->foreignId('uacs_code_id')->nullable()->constrained('uacs_codes')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['uacs_code_id']);
            $table->dropColumn('uacs_code_id');
        });
    }
};
