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
                $query->where(function($q) use ($year) {
                    $q->whereYear('obligation_date', $year)
                    ->orWhere(function($sub) use ($year) {
                        $sub->whereNull('obligation_date')
                            ->whereYear('created_at', $year);
                    });
                });

                if ($month) {
                    $query->where(function($q) use ($month) {
                        $q->whereMonth('obligation_date', $month)
                        ->orWhere(function($sub) { $sub->whereNull('obligation_date'); });
                    });
                } elseif ($quarter && isset($quarterMonths[$quarter])) {
                    $query->where(function($q) use ($quarterMonths, $quarter) {
                        $q->whereIn(\DB::raw('MONTH(obligation_date)'), $quarterMonths[$quarter])
                        ->orWhere(function($sub) { $sub->whereNull('obligation_date'); });
                    });
                }
            }])->get();

        $reportData = $sources->map(function ($source) use ($year) {
            $originalSourceTotal = (float) $source->total_amount;
            $totalPooledAmount = (float) $source->activities->sum('pooled_amount');
            
            $lineItems = $source->activities->map(function ($activity) use ($source, $year) {
                $activityFunds = $source->funds->where('transaction_type_id', $activity->id);
                
                $obligated = (float) $activityFunds->sum('obligation_amount');
                $disbursed = (float) $activityFunds->sum('disbursement_amount');

                // PENDING: In-flight transactions (No obligation yet)
                $pending = (float) $activityFunds->filter(function($f) use ($year) {
                    return (empty($f->obligation_date) || $f->obligation_amount <= 0) && 
                        ($f->disbursement_amount <= 0) &&
                        ($f->created_at->year == $year) &&
                        (!in_array($f->status, ['Cancelled', 'Rejected']));
                })->sum('amount');

                // SAVINGS: Obligated but not fully spent (only counted if disbursement exists)
                $savings = ($obligated > $disbursed && $disbursed > 0) ? ($obligated - $disbursed) : 0;
                
                $activityBudgetGross = (float) ($activity->budget_adjusted ?? $activity->budget);
                $pooled = (float) ($activity->pooled_amount ?? 0);
                $activityBudgetNet = $activityBudgetGross - $pooled;

                $untouched = $activityBudgetNet - ($obligated + $pending);
                $untouched = $untouched > 0 ? $untouched : 0;


                return [
                    'name'              => $activity->name,
                    'activity_budget'   => $activityBudgetGross, 
                    'net_budget'        => $activityBudgetNet,
                    'pooled_amount'     => $pooled,
                    'pending_amount'    => $pending,
                    'savings'           => (float) $savings, // ADDED THIS KEY
                    'obligated_amount'  => $obligated,
                    'disbursed_amount'  => $disbursed,
                    'obligation_rate'   => $activityBudgetNet > 0 ? ($obligated / $activityBudgetNet) * 100 : 0,
                    'disbursement_rate' => $obligated > 0 ? ($disbursed / $obligated) * 100 : 0,
                    'untouched_amount' => $untouched,
                ];
            });

            $totalActivityBudget = $lineItems->sum('net_budget');
            $unassignedBalance = $originalSourceTotal - $source->activities->sum('budget_adjusted');

            return [
                'source_name'           => $source->name,
                'source_total'          => $originalSourceTotal,
                'unassigned_balance'    => $unassignedBalance,
                'total_pooled'          => $totalPooledAmount,
                'line_items'            => $lineItems,
                'total_pending'         => $lineItems->sum('pending_amount'),
                'total_savings'         => $lineItems->sum('savings'),
                'total_untouched'       => $lineItems->sum('untouched_amount'),
                'total_activity_budget' => $totalActivityBudget,
                'total_obligated'       => $lineItems->sum('obligated_amount'),
                'total_disbursed'       => $lineItems->sum('disbursed_amount'),
                'overall_disb_rate'     => $lineItems->sum('obligated_amount') > 0 ? ($lineItems->sum('disbursed_amount') / $lineItems->sum('obligated_amount')) * 100 : 0,
                'overall_oblig_rate'    => $totalActivityBudget > 0 ? ($lineItems->sum('obligated_amount') / $totalActivityBudget) * 100 : 0,
            ];
        });

        return view('admin.reports.by_line_item', compact('reportData', 'year'));
    }

    public function byTransactions(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month');
        $quarter = $request->get('quarter');

        $quarterMonths = [
            1 => [1, 2, 3], 2 => [4, 5, 6],
            3 => [7, 8, 9], 4 => [10, 11, 12],
        ];

        // 1. Fetch Sources and eager load filtered funds
        $sources = \App\Models\SourceOfFund::where('fiscal_year', $year)
            ->with(['activities', 'funds' => function ($query) use ($year, $month, $quarter, $quarterMonths) {
                $query->where(function($q) use ($year) {
                    $q->whereYear('obligation_date', $year)
                    ->orWhere(function($sub) use ($year) {
                        $sub->whereNull('obligation_date')->whereYear('created_at', $year);
                    });
                })->whereNotIn('status', ['Cancelled', 'Rejected']);

                if ($month) {
                    $query->whereMonth('obligation_date', $month);
                } elseif ($quarter && isset($quarterMonths[$quarter])) {
                    $query->whereIn(\DB::raw('MONTH(obligation_date)'), $quarterMonths[$quarter]);
                }
            }])->get();

        // 2. Map data to match Line Item Report logic but keep transactions granular
        $groupedData = $sources->map(function ($source) use ($year) {
            $activities = $source->activities->map(function ($activity) use ($source, $year) {
                
                // STRICT FILTER: Match transaction to THIS activity within THIS source
                $activityFunds = $source->funds->where('transaction_type_id', $activity->id);
                
                $obligated = (float) $activityFunds->sum('obligation_amount');
                $disbursed = (float) $activityFunds->sum('disbursement_amount');

                // PENDING logic from your line item report
                $pending = (float) $activityFunds->filter(function($f) use ($year) {
                    return (empty($f->obligation_date) || $f->obligation_amount <= 0) && 
                        ($f->disbursement_amount <= 0);
                })->sum('amount');

                $activityBudgetGross = (float) ($activity->budget_adjusted ?? $activity->budget);
                $pooled = (float) ($activity->pooled_amount ?? 0);
                $activityBudgetNet = $activityBudgetGross - $pooled;

                $untouched = $activityBudgetNet - ($obligated + $pending);

                return [
                    'details'      => $activity,
                    'net_budget'   => $activityBudgetNet,
                    'obligated'    => $obligated,
                    'disbursed'    => $disbursed,
                    'pending'      => $pending,
                    'untouched'    => $untouched > 0 ? $untouched : 0,
                    'transactions' => $activityFunds->values() // Granular transactions for the ledger
                ];
            });

            return [
                'source_name' => $source->name,
                'activities'  => $activities
            ];
        });

        return view('admin.reports.by_transactions', compact('groupedData', 'year'));
    }

}