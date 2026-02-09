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
            ->with(['funds' => function ($query) use ($year, $month, $quarter, $quarterMonths) {
                
                // STRICT YEAR FILTER: Only transactions obligated in the selected Fiscal Year
                $query->whereYear('obligation_date', $year);

                // STRICT MONTH/QUARTER FILTER: Only based on obligation_date
                if ($month) {
                    $query->whereMonth('obligation_date', $month);
                } 
                elseif ($quarter && isset($quarterMonths[$quarter])) {
                    $query->whereIn(\DB::raw('MONTH(obligation_date)'), $quarterMonths[$quarter]);
                }
            }])->get();

        $reportData = $sources->map(function ($source) {
            $allotted = (float) $source->total_amount; 
            $obligated = (float) $source->funds->sum('obligation_amount');
            $disbursed = (float) $source->funds->sum('disbursement_amount');

            return [
                'name'              => $source->name,
                'allotted'          => $allotted,
                'obligated'         => $obligated,
                'disbursed'         => $disbursed,
                'balance'           => $allotted - $obligated,
                'obligation_rate'   => $allotted > 0 ? ($obligated / $allotted) * 100 : 0,
                'disbursement_rate' => $obligated > 0 ? ($disbursed / $obligated) * 100 : 0,
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
            $sourceTotal = (float) $source->total_amount; 

            $lineItems = $source->activities->map(function ($activity) use ($source) {
                $activityFunds = $source->funds->where('transaction_type_id', $activity->id);
                
                $obligated = (float) $activityFunds->sum('obligation_amount');
                $disbursed = (float) $activityFunds->sum('disbursement_amount');
                
                // Use budget_adjusted as the primary working budget
                // Use budget as the original reference
                $activityBudget = (float) ($activity->budget_adjusted ?? $activity->budget);
                $originalBudget = (float) $activity->budget;

                return [
                    'name'              => $activity->name,
                    'original_budget'   => $originalBudget, // Add this line to fix the error
                    'activity_budget'   => $activityBudget,
                    'obligated_amount'  => $obligated,
                    'disbursed_amount'  => $disbursed,
                    'unobligated'       => $activityBudget - $obligated,
                    'obligation_rate'   => $activityBudget > 0 ? ($obligated / $activityBudget) * 100 : 0,
                    'disbursement_rate' => $obligated > 0 ? ($disbursed / $obligated) * 100 : 0,
                ];
            });

            $totalObligated = $lineItems->sum('obligated_amount');
            $totalDisbursed = $lineItems->sum('disbursed_amount');
            
            // Overall Source Unobligated/Savings
            $overallUnobligated = $sourceTotal - $totalObligated;

            return [
                'source_name'           => $source->name,
                'source_total'          => $sourceTotal,
                'line_items'            => $lineItems,
                'total_activity_budget' => $lineItems->sum('activity_budget'),
                'total_obligated'       => $totalObligated,
                'total_disbursed'       => $totalDisbursed,
                'total_unobligated'     => $overallUnobligated,
                'total_savings'         => $overallUnobligated,
                'overall_oblig_rate'    => $sourceTotal > 0 ? ($totalObligated / $sourceTotal) * 100 : 0,
                'overall_disb_rate'     => $totalObligated > 0 ? ($totalDisbursed / $totalObligated) * 100 : 0,
            ];
        });

        return view('admin.reports.by_line_item', compact('reportData', 'year'));
    }

}