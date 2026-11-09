<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('source_of_funds', function (Blueprint $table) {
            // Options: 'NEP', 'Current', 'Conap'
            $table->string('funding_source')->nullable()->after('source_type');
        });
    }

    public function down()
    {
        Schema::table('source_of_funds', function (Blueprint $table) {
            $table->dropColumn('funding_source');
        });
    }
};
