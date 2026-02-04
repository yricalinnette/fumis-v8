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

    public function budgetBySource() {
        $sources = \App\Models\SourceOfFund::with('funds')->get();

        $reportData = $sources->map(function ($source) {
            $allotted = $source->total_amount; // Now using the main table amount
            $obligated = $source->funds->sum('obligation_amount');
            $disbursed = $source->funds->sum('disbursement_amount');

            return [
                'name'      => $source->name,
                'allotted'  => $allotted,
                'obligated' => $obligated,
                'disbursed' => $disbursed,
                'balance'   => $allotted - $obligated,
                // Rate Formulas
                'obligation_rate'  => $allotted > 0 ? ($obligated / $allotted) * 100 : 0,
                'disbursement_rate' => $obligated > 0 ? ($disbursed / $obligated) * 100 : 0,
            ];
        });

        return view('admin.reports.by_source', compact('reportData'));
    }

    public function budgetByLineItem()
    {
        $sources = \App\Models\SourceOfFund::with(['activities', 'funds'])->get();

        $reportData = $sources->map(function ($source) {
            $sourceTotal = $source->total_amount; 

            $lineItems = $source->activities->map(function ($activity) use ($source, $sourceTotal) {
                $activityFunds = $source->funds->where('transaction_type', $activity->name);
                
                $obligated = $activityFunds->sum('obligation_amount');
                $disbursed = $activityFunds->sum('disbursement_amount');

                return [
                    'name'              => $activity->name,
                    'activity_budget'   => $activity->budget,
                    'obligated_amount'  => $obligated,
                    'disbursed_amount'  => $disbursed,
                    'obligation_rate'   => $sourceTotal > 0 ? ($obligated / $sourceTotal) * 100 : 0,
                    'disbursement_rate' => $obligated > 0 ? ($disbursed / $obligated) * 100 : 0,
                ];
            });

            // Calculate source-wide totals for the Summary Row
            $totalActivityBudget = $lineItems->sum('activity_budget'); // New: Sum of all activity budgets
            $totalObligated      = $lineItems->sum('obligated_amount');
            $totalDisbursed      = $lineItems->sum('disbursed_amount');

            return [
                'source_name'           => $source->name,
                'source_total'          => $sourceTotal,
                'line_items'            => $lineItems,
                'total_activity_budget' => $totalActivityBudget,
                'total_obligated'       => $totalObligated,
                'total_disbursed'       => $totalDisbursed,
                'overall_oblig_rate'    => $sourceTotal > 0 ? ($totalObligated / $sourceTotal) * 100 : 0,
                'overall_disb_rate'     => $totalObligated > 0 ? ($totalDisbursed / $totalObligated) * 100 : 0,
            ];
        });

        return view('admin.reports.by_line_item', compact('reportData'));
    }

}