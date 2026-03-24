<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairFundSections extends Command
{
    protected $signature = 'fums:repair-sections';
    protected $description = 'Populates secid in funds table based on the creator\'s section at the time of repair';

    public function handle()
    {
        $this->info('Starting Data Repair...');

        // 1. Get unique user IDs who have created funds
        $creators = \DB::table('funds')->distinct()->pluck('user_id');

        foreach ($creators as $userId) {
            $secid = null;

            // --- STEP A: Handle Admin (User ID 1) ---
            if ($userId == 1) {
                $secid = 0;
                $this->info("User ID: 1 detected as ADMIN. Setting SecID to 0.");
            } else {
                // --- STEP B: Handle Regular Users ---
                
                // Get dbedid from your LOCAL employee_details table
                $localDetail = \DB::table('employee_details')
                    ->where('user_id', $userId)
                    ->first();

                if ($localDetail && $localDetail->dbedid) {
                    // Get the secid from db_common.tbl_emp_details via dbedid
                    $section = \DB::connection('db_common')->table('tbl_emp_details')
                        ->where('dbedid', $localDetail->dbedid)
                        ->select('secid')
                        ->first();
                    
                    if ($section) {
                        $secid = $section->secid;
                    }
                }
            }

            // --- STEP C: Update the Funds table ---
            if (!is_null($secid)) {
                $count = \DB::table('funds')
                    ->where('user_id', $userId)
                    ->update(['secid' => $secid]);

                $this->info("Updated {$count} records for User ID: {$userId} (Assigned SecID: {$secid})");
            } else {
                $this->error("Warning: Could not find SecID for User ID: {$userId}. Check employee_details mapping.");
            }
        }

        $this->info('Repair Complete!');
    }
}