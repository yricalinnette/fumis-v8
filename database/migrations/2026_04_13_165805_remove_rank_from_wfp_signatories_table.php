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
        Schema::table('wfp_signatories', function (Blueprint $table) {
            // 1. Remove the rank/order column
            if (Schema::hasColumn('wfp_signatories', 'rank')) {
                $table->dropColumn('rank');
            }

            // 2. Ensure we only have one person per role per WFP type
            // This prevents logic errors later
            $table->unique(['wfp_type', 'label']);
        });
    }

    public function down()
    {
        Schema::table('wfp_signatories', function (Blueprint $table) {
            $table->integer('rank')->nullable();
            $table->dropUnique(['wfp_type', 'label']);
        });
    }
};
