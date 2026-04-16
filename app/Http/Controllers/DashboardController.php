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
        $selectedYear = $request->get('year', date('Y'));

        // 1. Fetch Sources for the Fiscal Year
        $sources = \App\Models\SourceOfFund::where('fiscal_year', $selectedYear)
            ->with(['activities', 'funds' => function($query) use ($selectedYear) {
                $query->where(function($q) use ($selectedYear) {
                    // Filter: Either it was obligated this year, OR it's a pending item created this year
                    $q->whereYear('obligation_date', $selectedYear)
                    ->orWhere(function($sub) use ($selectedYear) {
                        $sub->whereNull('obligation_date')
                            ->whereYear('created_at', $selectedYear);
                    });
                })->whereNotIn('status', ['Cancelled', 'Rejected']);
            }])->get();

        $chartData = $sources->map(function ($source) use ($selectedYear) {
            // --- BUDGET CALCULATIONS ---
            $originalAllotted = (float) $source->total_amount;
            $totalPooled = (float) $source->activities->sum('pooled_amount');
            $adjustedAllotted = $originalAllotted - $totalPooled;

            // --- 1. OBLIGATED TOTAL ---
            // Sum only if obligation_date is within the selected year
            $obligatedTotal = $source->funds->filter(function($f) use ($selectedYear) {
                return $f->obligation_date && \Carbon\Carbon::parse($f->obligation_date)->year == $selectedYear;
            })->sum('obligation_amount');

            // --- 2. DISBURSED TOTAL ---
            $disbursedTotal = $source->funds->filter(function($f) use ($selectedYear) {
                return $f->obligation_date && \Carbon\Carbon::parse($f->obligation_date)->year == $selectedYear;
            })->sum('disbursement_amount');

            // --- 3. PENDING TOTAL (Matches your Report Logic) ---
            // Items created this year but not yet obligated
            $pendingTotal = $source->funds->filter(function($f) use ($selectedYear) {
                return (empty($f->obligation_date) || $f->obligation_amount <= 0) && 
                    ($f->disbursement_amount <= 0) &&
                    $f->created_at->year == $selectedYear;
            })->sum('amount');

            // --- 4. PROCESSED TOTAL (Obligated + Pending) ---
            $processedTotal = $obligatedTotal + $pendingTotal;
            
            // --- 5. UNTOUCHED (Remaining balance including un-processed funds) ---
            $untouchedBudget = $adjustedAllotted - $processedTotal;

            return [
                'name'              => $source->name,
                'original_allotted' => $originalAllotted,
                'total_allotted'    => $adjustedAllotted,
                'total_pooled'      => $totalPooled,
                'processed_total'   => $processedTotal, 
                'pending_total'     => $pendingTotal,
                'remaining_budget'  => $untouchedBudget > 0 ? $untouchedBudget : 0,
                'obligated_total'   => $obligatedTotal,
                'disbursed_total'   => $disbursedTotal,
                
                // Rates
                'percent'           => $adjustedAllotted > 0 ? round(($processedTotal / $adjustedAllotted) * 100, 1) : 0,
                'ob_rate'           => $adjustedAllotted > 0 ? round(($obligatedTotal / $adjustedAllotted) * 100, 1) : 0,
                'disb_rate'         => $obligatedTotal > 0 ? round(($disbursedTotal / $obligatedTotal) * 100, 1) : 0,
                
                'last_updated'      => $source->funds->max('updated_at') 
                                        ? $source->funds->max('updated_at')->diffForHumans() 
                                        : 'No activity',
            ];
        });

        return view('dashboard', compact('chartData', 'selectedYear'));
    }
}