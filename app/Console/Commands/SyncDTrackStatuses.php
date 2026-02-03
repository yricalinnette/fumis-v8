<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Fund;
use App\Services\DTrackService;
use Carbon\Carbon;

class SyncDTrackStatuses extends Command
{
    protected $signature = 'sync:dtrack';
    protected $description = 'Automatically update fund statuses and document update dates from DTrack';

    public function handle()
    {
        \Log::info("DTrack Sync Started at: " . now());

        $updatedCount = 0;
        $dtrackService = new DTrackService();

        // 1. Fetch transactions excluding 'Disbursed'
        $syncableFunds = Fund::where('status', '!=', 'Disbursed')->get();

        foreach ($syncableFunds as $fund) {
            
            // --- RULE: If status is 'Disbursed', do absolutely nothing ---
            if ($fund->status === 'Disbursed') {
                continue;
            }

            $externalData = $dtrackService->getDTrackStatus($fund->dtrack_no);

            if ($externalData && 
                isset($externalData['doc_register_destination']) && 
                is_array($externalData['doc_register_destination']) && 
                count($externalData['doc_register_destination']) > 0) {

                $latestLog = $externalData['doc_register_destination'][0];
                $dtrackStatus = $latestLog['actreq_desc'] ?? '';
                $office = $latestLog['dest_office'] ?? 'Unknown Office';
                $actionRemarks = $latestLog['acttaken_desc'] ?? '';
                
                // NEW: Get the DTrack update date from system
                $dtrackUpdateDate = $latestLog['docdet_rlsd_dateupdated'] ?? null;

                // --- SHARED RULE: Update doc_update_date for anything not Disbursed ---
                if ($dtrackUpdateDate) {
                    try {
                        $fund->doc_update_date = Carbon::parse($dtrackUpdateDate);
                    } catch (\Exception $e) {
                        \Log::warning("Date parse failed for {$fund->dtrack_no}: " . $dtrackUpdateDate);
                    }
                }

                // --- RULE: If status is 'Obligated', only update remarks (and date above) ---
                if ($fund->status === 'Obligated') {
                    $fund->remarks = "Currently at: " . $office . " (" . $dtrackStatus . ")";
                } else {
                    // --- RULE: For all other statuses, update Status, Remarks, and Date ---
                    $fund->status = $this->mapStatus($dtrackStatus);
                    $fund->remarks = "Currently at: " . $office . ($actionRemarks ? " (" . $actionRemarks . ")" : "");
                }

                $fund->status_date = now();
                $fund->save();
                $updatedCount++;
            }
        }

        $this->info("DTrack sync completed. Updated $updatedCount records.");
        \Log::info("DTrack Sync Finished. Updated $updatedCount records.");
    }

    /**
     * Helper to map DTrack internal descriptions to System Statuses
     */
    private function mapStatus($dtrackStatus) {
        return match ($dtrackStatus) {
            'For Signature' => 'For Signature',
            'For CAF/Obligation (Budget)' => 'Obligated',
            'For Processing', 'For Processing (Accounting)' => 'Processing',
            default => $dtrackStatus,
        };
    }
}