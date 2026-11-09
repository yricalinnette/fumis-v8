<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. Tag as 'Conap' if the name contains 'CONAP' (case-insensitive) and funding_source is null
        DB::table('source_of_funds')
            ->whereNull('funding_source')
            ->where('name', 'LIKE', '%CONAP%')
            ->update(['funding_source' => 'Conap']);

        // 2. Tag everything else remaining as 'Current' (excluding NEP)
        DB::table('source_of_funds')
            ->whereNull('funding_source')
            ->where('name', 'NOT LIKE', '%NEP%')
            ->update(['funding_source' => 'Current']);
    }

    public function down()
    {
        // Optional rollback: reset them back to null if needed
        DB::table('source_of_funds')
            ->whereIn('funding_source', ['Conap', 'Current'])
            ->update(['funding_source' => null]);
    }
};
