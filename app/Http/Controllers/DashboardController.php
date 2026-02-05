<?php

namespace App\Http\Controllers;

use App\Models\SourceOfFund;
use App\Models\Fund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // 1. Get the year from request, default to current year (2026)
        $selectedYear = $request->get('year', date('Y'));

        $sources = SourceOfFund::all();

        $chartData = $sources->map(function($source) use ($selectedYear) {
            // Shared query base
            $fundQuery = DB::table('funds')
                ->where('source_of_fund_id', $source->id)
                ->where('status', '!=', 'Cancelled')
                ->whereYear('transaction_date', $selectedYear);

            // Fetch totals
            $totalProcessed = (clone $fundQuery)->sum('amount');
            
            // IF NO DATA EXISTS FOR THIS YEAR, we return null to filter it out later
            if ($totalProcessed <= 0) {
                return null;
            }

            $latestUpdate = (clone $fundQuery)->latest('updated_at')->value('updated_at');
            $totalObligated = (clone $fundQuery)->sum('obligation_amount');
            $totalDisbursed = (clone $fundQuery)->where('status', 'Disbursed')->sum('disbursement_amount');

            $remaining = $source->total_amount - $totalProcessed;

            // Efficiency Calculations
            $obRate = ($source->total_amount > 0) ? ($totalObligated / $source->total_amount) * 100 : 0;
            $disbRate = ($totalObligated > 0) ? ($totalDisbursed / $totalObligated) * 100 : 0;
            $utilizationRate = ($source->total_amount > 0) ? ($totalProcessed / $source->total_amount) * 100 : 0;

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
                'last_updated'      => $latestUpdate ? Carbon::parse($latestUpdate)->format('M d, Y h:i A') : 'N/A',
            ];
        })
        ->filter() // Removes all the 'null' entries (sources with no data for the year)
        ->values(); // Resets array keys (0, 1, 2...) so JavaScript loops don't break

        return view('dashboard', compact('chartData', 'selectedYear'));
    }
}