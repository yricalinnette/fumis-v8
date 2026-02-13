<?php

namespace App\Http\Controllers;

use App\Models\Fund;
use Illuminate\Http\Request;
use App\Models\SourceOfFund;
use App\Models\Employee;
use App\Models\Activity;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $query = Fund::query();

        // 1. Year Filter (Required for all reports)
        $year = $request->get('year', date('Y'));
        $query->whereYear('transaction_date', $year);

        // 2. Monthly Filter
        if ($request->filled('month')) {
            $query->whereMonth('transaction_date', $request->month);
        }

        // 3. Quarterly Filter
        if ($request->filled('quarter')) {
            $quarter = $request->quarter;
            // Q1: Jan-Mar, Q2: Apr-Jun, etc.
            $months = match($quarter) {
                '1' => [1, 2, 3],
                '2' => [4, 5, 6],
                '3' => [7, 8, 9],
                '4' => [10, 11, 12],
                default => []
            };
            $query->whereIn(\DB::raw('MONTH(transaction_date)'), $months);
        }

        $funds = $query->orderBy('transaction_date', 'desc')->get();
        $totalAmount = $funds->sum('amount');

        return view('admin.reports', compact('funds', 'totalAmount', 'year'));
    }

    public function budgetBySource(Request $request) 
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month');
        $quarter = $request->get('quarter');

        $quarterMonths = [
            1 => [1, 2, 3], 2 => [4, 5, 6],
            3 => [7, 8, 9], 4 => [10, 11, 12],
        ];

        $sources = \App\Models\SourceOfFund::where('fiscal_year', $year)
            // We now include 'activities' to access pooled_amount
            ->with(['activities', 'funds' => function ($query) use ($year, $month, $quarter, $quarterMonths) {
                $query->whereYear('obligation_date', $year);

                if ($month) {
                    $query->whereMonth('obligation_date', $month);
                } 
                elseif ($quarter && isset($quarterMonths[$quarter])) {
                    $query->whereIn(\DB::raw('MONTH(obligation_date)'), $quarterMonths[$quarter]);
                }
            }])->get();

        $reportData = $sources->map(function ($source) {
            // 1. Calculate pooling from all activities under this source
            $totalPooled = (float) $source->activities->sum('pooled_amount');
            
            // 2. The working allotment is the original total minus pooled funds
            $originalAllotted = (float) $source->total_amount; 
            $netAllotted = $originalAllotted - $totalPooled;

            $obligated = (float) $source->funds->sum('obligation_amount');
            $disbursed = (float) $source->funds->sum('disbursement_amount');

            return [
                'source_name'           => $source->name,
                'original_source_total' => $originalAllotted, 
                'total_pooled'          => $totalPooled,      
                'source_total'          => $netAllotted,      // Changed from 'allotted' to 'source_total'
                'total_obligated'       => $obligated,       // Changed from 'obligated' to 'total_obligated'
                'total_disbursed'       => $disbursed,       // Changed from 'disbursed' to 'total_disbursed'
                'total_unobligated'     => $netAllotted - $obligated, // Changed from 'balance'
                'overall_oblig_rate'    => $netAllotted > 0 ? ($obligated / $netAllotted) * 100 : 0,
                'overall_disb_rate'     => $obligated > 0 ? ($disbursed / $obligated) * 100 : 0,
            ];
        });

        return view('admin.reports.by_source', compact('reportData', 'year'));
    }

    public function budgetByLineItem(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month');
        $quarter = $request->get('quarter');

        $quarterMonths = [
            1 => [1, 2, 3], 2 => [4, 5, 6],
            3 => [7, 8, 9], 4 => [10, 11, 12],
        ];

        $sources = \App\Models\SourceOfFund::where('fiscal_year', $year)
            ->with(['activities', 'funds' => function ($query) use ($year, $month, $quarter, $quarterMonths) {
                $query->whereYear('obligation_date', $year);
                if ($month) {
                    $query->whereMonth('obligation_date', $month);
                } 
                elseif ($quarter && isset($quarterMonths[$quarter])) {
                    $query->whereIn(\DB::raw('MONTH(obligation_date)'), $quarterMonths[$quarter]);
                }
            }])->get();

        $reportData = $sources->map(function ($source) {
            // 1. Get the total pooled amount for all activities under this source
            $totalPooledAmount = (float) $source->activities->sum('pooled_amount');

            // 2. The Effective Source Total is the original amount minus what was returned to the pool
            $originalSourceTotal = (float) $source->total_amount; 
            $effectiveSourceTotal = $originalSourceTotal - $totalPooledAmount;

            $lineItems = $source->activities->map(function ($activity) use ($source) {
                $activityFunds = $source->funds->where('transaction_type_id', $activity->id);
                
                $obligated = (float) $activityFunds->sum('obligation_amount');
                $disbursed = (float) $activityFunds->sum('disbursement_amount');
                
                // The budget available for this activity specifically
                $activityBudgetGross = (float) ($activity->budget_adjusted ?? $activity->budget);
                $pooled = (float) ($activity->pooled_amount ?? 0);
                $activityBudgetNet = $activityBudgetGross - $pooled;

                return [
                    'name'              => $activity->name,
                    'original_budget'   => (float) $activity->budget, 
                    'activity_budget'   => $activityBudgetGross, // Gross Adjusted
                    'net_budget'        => $activityBudgetNet,   // Adjusted - Pooled
                    'pooled_amount'     => $pooled,
                    'pooled_remarks'    => $activity->pooled_remarks,
                    'obligated_amount'  => $obligated,
                    'disbursed_amount'  => $disbursed,
                    'unobligated'       => $activityBudgetNet - $obligated,
                    // Rate should be against the Net Budget (what they actually have left to spend)
                    'obligation_rate'   => $activityBudgetNet > 0 ? ($obligated / $activityBudgetNet) * 100 : 0,
                    'disbursement_rate' => $obligated > 0 ? ($disbursed / $obligated) * 100 : 0,
                ];
            });

            $totalObligated = $lineItems->sum('obligated_amount');
            $totalDisbursed = $lineItems->sum('disbursed_amount');
            
            // Overall Source Unobligated is now based on the EFFECTIVE total (Source - Pools)
            $overallUnobligated = $effectiveSourceTotal - $totalObligated;

            return [
                'source_name'           => $source->name,
                'source_total'          => $effectiveSourceTotal, // This is the Adjusted Total (Source - Pooled)
                'original_source_total' => $originalSourceTotal,  // Kept for reference if needed
                'total_pooled'          => $totalPooledAmount,
                'line_items'            => $lineItems,
                'total_activity_budget' => $lineItems->sum('net_budget'),
                'total_obligated'       => $totalObligated,
                'total_disbursed'       => $totalDisbursed,
                'total_unobligated'     => $overallUnobligated,
                // Overall rate is now accurate to the "New" allotment
                'overall_oblig_rate'    => $effectiveSourceTotal > 0 ? ($totalObligated / $effectiveSourceTotal) * 100 : 0,
                'overall_disb_rate'     => $totalObligated > 0 ? ($totalDisbursed / $totalObligated) * 100 : 0,
            ];
        });

        return view('admin.reports.by_line_item', compact('reportData', 'year'));
    }

}