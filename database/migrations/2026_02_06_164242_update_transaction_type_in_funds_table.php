<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('funds', function (Blueprint $table) {
            // Add the new ID column after the old transaction_type
            // We use transaction_type_id to link to the activities table
            $table->unsignedBigInteger('transaction_type_id')->nullable()->after('transaction_type');
            
            // Add the foreign key constraint
            $table->foreign('transaction_type_id')
                  ->references('id')
                  ->on('activities')
                  ->onDelete('restrict');
        });

        // DATA MIGRATION: Map existing activity names to IDs
        $activities = DB::table('activities')->get();
        foreach ($activities as $activity) {
            DB::table('funds')
                ->where('transaction_type', $activity->name)
                ->update(['transaction_type_id' => $activity->id]);
        }

        Schema::table('funds', function (Blueprint $table) {
            // Drop the old string column
            $table->dropColumn('transaction_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('funds', function (Blueprint $table) {
            // Add back the string column
            $table->string('transaction_type')->nullable()->after('id');
        });

        // Reverse data migration: put the name back into the string column
        $activities = DB::table('activities')->get();
        foreach ($activities as $activity) {
            DB::table('funds')
                ->where('transaction_type_id', $activity->id)
                ->update(['transaction_type' => $activity->name]);
        }

        Schema::table('funds', function (Blueprint $table) {
            // Remove the foreign key and the ID column
            $table->dropForeign(['transaction_type_id']);
            $table->dropColumn('transaction_type_id');
        });
    }
};