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
            // Option A: Just make it nullable so the error stops
            $table->string('creditor')->nullable()->change();
            
            // Option B: If you are sure you don't need the old single-column data:
            // $table->dropColumn('creditor'); 
        });
    }

    public function down()
    {
        Schema::table('funds', function (Blueprint $table) {
            $table->string('creditor')->nullable(false)->change();
        });
    }
};
