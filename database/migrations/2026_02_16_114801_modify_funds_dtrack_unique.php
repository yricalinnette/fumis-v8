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
            // 1. Drop the unique constraint
            // Note: The index name is usually 'funds_dtrack_no_unique'
            $table->dropUnique(['dtrack_no']); 
            
            // 2. Add a regular index so searching by DTrack remains fast
            $table->index('dtrack_no'); 
        });
    }

    public function down()
    {
        Schema::table('funds', function (Blueprint $table) {
            $table->unique('dtrack_no');
            $table->dropIndex(['dtrack_no']);
        });
    }
};
