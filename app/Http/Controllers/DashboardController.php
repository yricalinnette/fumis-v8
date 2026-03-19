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

        // Load sources with all funds related to the selected year (either by creation or obligation)
        $sources = \App\Models\SourceOfFund::where('fiscal_year', $selectedYear)
            ->with(['activities', 'funds' => function($query) use ($selectedYear) {
                $query->where(function($q) use ($selectedYear) {
                    $q->whereYear('created_at', $selectedYear)
                    ->orWhereYear('obligation_date', $selectedYear);
                })->where('status', '!=', 'Cancelled'); // Common practice to exclude cancelled
            }])->get();

        $chartData = $sources->map(function ($source) use ($selectedYear) {
            $originalAllotted = (float) $source->total_amount;
            $totalPooled = (float) $source->activities->sum('pooled_amount');
            $adjustedAllotted = $originalAllotted - $totalPooled;

            // 1. PROCESSED TOTAL (Based on created_at)
            // Only sum funds created in the selected year
            $processedTotal = $source->funds
                ->whereStrict('created_at.year', (int)$selectedYear) // Ensure year matches
                ->sum(function ($fund) {
                    return (is_null($fund->obligation_amount) || $fund->obligation_amount == 0)
                        ? (float) $fund->amount 
                        : (float) $fund->obligation_amount;
                });

            // 2. OBLIGATED TOTAL (Based on obligation_date)
            // Only sum funds that have an obligation date in the selected year
            $strictlyObligated = $source->funds
                ->filter(function($fund) use ($selectedYear) {
                    return $fund->obligation_date && \Carbon\Carbon::parse($fund->obligation_date)->year == $selectedYear;
                })
                ->sum('obligation_amount');

            // 3. DISBURSED TOTAL
            $disbursed = $source->funds
                ->filter(function($fund) use ($selectedYear) {
                    return $fund->obligation_date && \Carbon\Carbon::parse($fund->obligation_date)->year == $selectedYear;
                })
                ->sum('disbursement_amount');
            
            return [
                'name'              => $source->name,
                'original_allotted' => $originalAllotted,
                'total_allotted'    => $adjustedAllotted,
                'total_pooled'      => $totalPooled,
                'processed_total'   => $processedTotal, 
                'remaining_budget'  => $adjustedAllotted - $processedTotal,
                'obligated_total'   => $strictlyObligated,
                'disbursed_total'   => $disbursed,
                
                'percent'           => $adjustedAllotted > 0 ? round(($processedTotal / $adjustedAllotted) * 100, 1) : 0,
                'ob_rate'           => $adjustedAllotted > 0 ? round(($strictlyObligated / $adjustedAllotted) * 100, 1) : 0,
                'disb_rate'         => $strictlyObligated > 0 ? round(($disbursed / $strictlyObligated) * 100, 1) : 0,
                
                'last_updated'      => $source->funds->max('updated_at') 
                                        ? $source->funds->max('updated_at')->diffForHumans() 
                                        : 'No activity',
            ];
        });

        return view('dashboard', compact('chartData', 'selectedYear'));
    }
}