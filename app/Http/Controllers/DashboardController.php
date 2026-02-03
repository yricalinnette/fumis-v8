<?php

namespace App\Http\Controllers;

use App\Models\SourceOfFund;
use App\Models\Fund; // Added this
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $sources = SourceOfFund::all();

        $chartData = $sources->map(function($source) {
            // 1. Fetch the latest update date for this specific source
            $latestUpdate = DB::table('funds')
                ->where('source_of_fund_id', $source->id)
                ->latest('updated_at')
                ->value('updated_at');

            // Totals from 'funds' table
            $totalProcessed = DB::table('funds')->where('source_of_fund_id', $source->id)->where('status', '!=', 'Cancelled')->sum('amount');
            $totalObligated = DB::table('funds')->where('source_of_fund_id', $source->id)->where('status', '!=', 'Cancelled')->sum('obligation_amount');
            $totalDisbursed = DB::table('funds')->where('source_of_fund_id', $source->id)->where('status', '!=', 'Cancelled')->where('status', 'Disbursed')->sum('disbursement_amount');

            $remaining = $source->total_amount - $totalProcessed;

            // Efficiency Calculations
            $obRate = ($source->total_amount > 0) ? ($totalObligated / $source->total_amount) * 100 : 0;
            $disbRate = ($totalObligated > 0) ? ($totalDisbursed / $totalObligated) * 100 : 0;

            // Utilization Rate calculation
            $utilizationRate = ($source->total_amount > 0) 
                ? ($totalProcessed / $source->total_amount) * 100 
                : 0;

            return [
                'name'              => $source->name,
                'total_allotted'    => (float) $source->total_amount,
                'processed_total'   => (float) $totalProcessed,
                'remaining_budget'  => (float) ($remaining > 0 ? $remaining : 0),
                'obligated_total'   => (float) $totalObligated,
                'disbursed_total'   => (float) $totalDisbursed,
                'percent'           => round($utilizationRate, 1),
                'ob_rate'           => round($obRate, 1),
                'disb_rate'         => round($disbRate, 1),
                'last_updated'      => $latestUpdate ? \Carbon\Carbon::parse($latestUpdate)->format('M d, Y h:i A') : 'No Data',
            ];
        });

        return view('dashboard', compact('chartData'));
    }
}