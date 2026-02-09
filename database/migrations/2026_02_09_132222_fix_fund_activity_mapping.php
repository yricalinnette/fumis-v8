<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixFundActivityMapping extends Migration
{
    public function up()
    {
        // 1. Get all activities and their source parents
        $activities = DB::table('activities')->get();

        foreach ($activities as $activity) {
            // 2. Find funds that belong to the SAME source as this activity
            // and currently share the SAME name (if you still had the name)
            // Since the name column is gone, we rely on the fact that 
            // 'transaction_type_id' currently holds *an* ID, but we need the *correct* ID.
            
            // NOTE: If you have already lost the original 'transaction_type' string,
            // we have to assume the 'transaction_type_id' currently points to 
            // an activity with the correct name, just potentially the wrong source.
            
            $wrongActivityIds = DB::table('activities')
                ->where('name', $activity->name)
                ->pluck('id');

            DB::table('funds')
                ->where('source_of_fund_id', $activity->source_of_fund_id)
                ->whereIn('transaction_type_id', $wrongActivityIds)
                ->update(['transaction_type_id' => $activity->id]);
        }
    }

    public function down()
    {
        // This is a data-only fix, so 'down' can remain empty 
        // or you can log a warning.
    }
}
