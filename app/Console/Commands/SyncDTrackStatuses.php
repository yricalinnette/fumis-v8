<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Fund;
use App\Services\DTrackService;

class SyncDTrackStatuses extends Command
{
    protected $signature = 'sync:dtrack';
    protected $description = 'Automatically update fund statuses from DTrack';

    public function handle()
    {
        \Log::info("DTrack Sync Started at: " . now());

        $updatedCount = 0;

        // 1. Fetch transactions excluding 'Disbursed'
        $syncableFunds = Fund::where('status', '!=', 'Disbursed')->get();
        $dtrackService = new DTrackService();

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

                // --- RULE: If status is 'Obligated', only update remarks ---
                if ($fund->status === 'Obligated') {
                    // Use dest_office and actreq_desc for remarks as requested
                    $fund->remarks = "Currently at: " . $office . " (" . $dtrackStatus . ")";
                } else {
                    // --- RULE: For all other statuses, update both Status and Remarks ---
                    
                    // Update Status based on actreq_desc
                    switch ($dtrackStatus) {
                        case 'For Signature':
                            $fund->status = 'For Signature';
                            break;
                        case 'For CAF/Obligation (Budget)':
                            $fund->status = 'Obligated';
                            break;
                        case 'For Processing':
                        case 'For Processing (Accounting)':
                            $fund->status = 'Processing';
                            break;
                        default:
                            $fund->status = $dtrackStatus; 
                            break;
                    }

                    // Update Remarks with dest_office and taken_remarks
                    $fund->remarks = "Currently at: " . $office . ($actionRemarks ? " (" . $actionRemarks . ")" : "");
                }

                $fund->status_date = now();
                $fund->save();
                $updatedCount++;
            }
        }
        $this->info('DTrack sync completed successfully.');

        \Log::info("DTrack Sync Finished. Updated $updatedCount records.");
    }

    private function mapStatus($dtrackStatus) {
        return match ($dtrackStatus) {
            'For Signature' => 'For Signature',
            'For CAF/Obligation (Budget)' => 'Obligated',
            'For Processing', 'For Processing (Accounting)' => 'For Processing',
            default => $dtrackStatus,
        };
    }
}