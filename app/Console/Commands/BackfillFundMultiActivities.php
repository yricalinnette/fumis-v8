<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FundMultiActivity;
use App\Models\Activity as FundsTransactionType;
use Illuminate\Support\Facades\DB;

class BackfillFundMultiActivities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fund:backfill-multi-activities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill source_of_fund_id and transaction_type_id in fund_multi_activities based on parent funds and creditor activity strings.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting backfill process for fund_multi_activities...');

        // 1. Load all activities once into memory
        $activities = FundsTransactionType::all();
        
        if ($activities->isEmpty()) {
            $this->error('No activities found in the transaction types table!');
            return 1;
        }

        // 2. Retrieve records needing updates
        $items = FundMultiActivity::with('fund')->get();
        $this->info("Processing {$items->count()} multi-activity records...");

        $sourceUpdatedCount = 0;
        $typeUpdatedCount   = 0;
        $unmatchedCount     = 0;

        DB::beginTransaction();

        try {
            foreach ($items as $item) {
                $isUpdated = false;

                // --- Task A: Populate source_of_fund_id ---
                if (empty($item->source_of_fund_id) && $item->fund) {
                    $item->source_of_fund_id = $item->fund->source_of_fund_id;
                    $sourceUpdatedCount++;
                    $isUpdated = true;
                }

                // --- Task B: Match transaction_type_id from creditor string ---
                if (empty($item->transaction_type_id) && !empty($item->creditor)) {
                    $matchedActivity = $activities->first(function ($act) use ($item) {
                        // Extract "Activity X.XX" or similar prefix if present
                        preg_match('/^(Activity\s+[\d\.]+)/i', trim($act->name), $matches);
                        $searchKey = $matches[1] ?? trim($act->name);

                        return stripos($item->creditor, $searchKey) !== false;
                    });

                    if ($matchedActivity) {
                        $item->transaction_type_id = $matchedActivity->id;
                        $typeUpdatedCount++;
                        $isUpdated = true;
                    } else {
                        $unmatchedCount++;
                        $this->warn("ID #{$item->id}: Could not match creditor '{$item->creditor}' to any activity.");
                    }
                }

                if ($isUpdated) {
                    $item->save();
                }
            }

            DB::commit();

            $this->info('----------------------------------------');
            $this->info("Successfully updated source_of_fund_id on {$sourceUpdatedCount} records.");
            $this->info("Successfully matched transaction_type_id on {$typeUpdatedCount} records.");
            if ($unmatchedCount > 0) {
                $this->warn("{$unmatchedCount} records could not be matched automatically. You may need to review them manually.");
            }
            $this->info('Backfill completed successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('An error occurred during execution: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
