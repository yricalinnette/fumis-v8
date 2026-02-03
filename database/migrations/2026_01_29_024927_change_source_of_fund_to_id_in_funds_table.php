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
            // Add the new ID column after transaction_date
            $table->unsignedBigInteger('source_of_fund_id')->nullable()->after('transaction_date');
            
            // Add the foreign key constraint
            $table->foreign('source_of_fund_id')->references('id')->on('source_of_funds')->onDelete('restrict');
        });

        // DATA MIGRATION: Map existing names to IDs
        $sources = DB::table('source_of_funds')->get();
        foreach ($sources as $source) {
            DB::table('funds')
                ->where('source_of_fund', $source->name)
                ->update(['source_of_fund_id' => $source->id]);
        }

        Schema::table('funds', function (Blueprint $table) {
            // Drop the old string column
            $table->dropColumn('source_of_fund');
        });
    }

    public function down()
    {
        Schema::table('funds', function (Blueprint $table) {
            $table->string('source_of_fund')->nullable()->after('transaction_date');
        });

        // Reverse data migration
        $sources = DB::table('source_of_funds')->get();
        foreach ($sources as $source) {
            DB::table('funds')
                ->where('source_of_fund_id', $source->id)
                ->update(['source_of_fund' => $source->name]);
        }

        Schema::table('funds', function (Blueprint $table) {
            $table->dropForeign(['source_of_fund_id']);
            $table->dropColumn('source_of_fund_id');
        });
    }
};
