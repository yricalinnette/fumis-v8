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
            ->with(['activities', 'funds' => function ($query) use ($year, $month, $quarter, $quarterMonths) {
                // MATCHING LOGIC: Same date filtering as byTransactions
                $query->where(function($q) use ($year) {
                    $q->whereYear('obligation_date', $year)
                    ->orWhere(function($sub) use ($year) {
                        $sub->whereNull('obligation_date')->whereYear('created_at', $year);
                    });
                })
                // MATCHING LOGIC: Exclude Invalid statuses
                ->whereNotIn('status', ['Cancelled', 'Rejected']);

                if ($month) {
                    $query->whereMonth('obligation_date', $month);
                } elseif ($quarter && isset($quarterMonths[$quarter])) {
                    $query->whereIn(\DB::raw('MONTH(obligation_date)'), $quarterMonths[$quarter]);
                }
            }])->get();
        

        $reportData = $sources->map(function ($source) use ($year) {
            // 1. Calculate pooling
            $totalPooled = (float) $source->activities->sum('pooled_amount');
            
            $originalAllotted = (float) $source->total_amount; 
            $netAllotted = $originalAllotted - $totalPooled;

            // 2. Aggregate Obligations and Disbursements
            $obligated = (float) $source->funds->sum('obligation_amount');
            $disbursed = (float) $source->funds->sum('disbursement_amount');

            /**
             * UPDATED: Procurable/Non-Procurable Logic
             * We calculate the budget adjusted minus any amount that was pooled.
             * This ensures the "Grand Total" percentage matches the "Net Allotted" amount.
             **/
            $procurableBudget = $source->activities
                ->where('is_for_procurement', 1)
                ->sum(function($activity) {
                    // Subtract pooled amount from the budget to get the "active" procurable budget
                    return $activity->budget_adjusted - $activity->pooled_amount;
                });

            $nonProcurableBudget = $source->activities
                ->where('is_for_procurement', 0)
                ->sum(function($activity) {
                    return $activity->budget_adjusted - $activity->pooled_amount;
                });

            // 3. STRICT PENDING LOGIC
            $pending = (float) $source->funds->filter(function($f) {
                return (empty($f->obligation_date) || $f->obligation_amount <= 0) && 
                    ($f->disbursement_amount <= 0);
            })->sum('amount');

            // 4. UNOBLIGATED BALANCE
            $unobligated = $netAllotted - ($obligated + $pending);

            return [
                'source_name'               => $source->name,
                'original_source_total'     => $originalAllotted, 
                'total_pooled'              => $totalPooled,      
                'source_total'              => $netAllotted,      
                'total_obligated'           => $obligated,       
                'total_disbursed'           => $disbursed,
                'total_pending'             => $pending, 
                'procurable_budget_total'   => $procurableBudget,
                'non_procurable_budget_total' => $nonProcurableBudget,
                'total_unobligated'         => $unobligated > 0 ? $unobligated : 0, 
                'overall_oblig_rate'        => $netAllotted > 0 ? ($obligated / $netAllotted) * 100 : 0,
                'overall_disb_rate'         => $obligated > 0 ? ($disbursed / $obligated) * 100 : 0,
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
        // 1. Increase time limit to 5 minutes to allow for multiple sequential API calls
        set_time_limit(300);

        $year = $request->get('year', date('Y'));
        $month = $request->get('month');
        $quarter = $request->get('quarter');

        $quarterMonths = [
            1 => [1, 2, 3], 2 => [4, 5, 6],
            3 => [7, 8, 9], 4 => [10, 11, 12],
        ];

        $dtrackService = new \App\Services\DTrackService();
        $isDTrackReachable = true; // Circuit breaker flag

        // 2. Fetch Sources and Funds
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

        // 3. Map Data and Sync DTrack (only if reachable)
        $groupedData = $sources->map(function ($source) use ($year, $dtrackService, &$isDTrackReachable) {
            $activities = $source->activities->map(function ($activity) use ($source, $year, $dtrackService, &$isDTrackReachable) {
                
                $activityFunds = $source->funds->where('transaction_type_id', $activity->id);
                
                // DTRACK SYNC LOGIC
                foreach ($activityFunds as $fund) {
                    if (!$isDTrackReachable) continue; 

                    // LOGIC: Only sync if it has a DTrack number, isn't disbursed, 
                    // and hasn't been updated in the last 120 minutes.
                    $isStale = !$fund->updated_at || $fund->updated_at->diffInMinutes(now()) > 120;

                    if ($fund->dtrack_no && $fund->status !== 'Disbursed' && $isStale) {
                        try {
                            // Use a very short 1.5s timeout for report loops
                            $externalData = $dtrackService->getDTrackStatus($fund->dtrack_no, 1.5);
                            
                            $logs = $externalData['doc_register_destination'] ?? [];
                            $latestLog = !empty($logs) ? end($logs) : null;

                            if ($latestLog) {
                                $dtrackStatus = $latestLog['actreq_desc'] ?? '';
                                $office = $latestLog['dest_office'] ?? 'Unknown Office';
                                
                                if ($fund->status === 'Obligated') {
                                    $fund->remarks = "Currently at: {$office} ({$dtrackStatus})";
                                } else {
                                    $fund->status = $this->mapStatus($dtrackStatus); 
                                    $fund->remarks = "Currently at: {$office}";
                                }

                                $fund->status_date = now();
                                $fund->save(); // This resets the 'updated_at' timer
                            }
                        } catch (\Exception $e) {
                            // If it fails or times out, trigger the circuit breaker for this request
                            $isDTrackReachable = false;
                        }
                    }
                }

                // Calculations
                $obligated = (float) $activityFunds->sum('obligation_amount');
                $disbursed = (float) $activityFunds->sum('disbursement_amount');

                $pending = (float) $activityFunds->filter(function($f) {
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
                    'transactions' => $activityFunds->values()
                ];
            });

            return [
                'source_name' => $source->name,
                'activities'  => $activities
            ];
        });

        return view('admin.reports.by_transactions', [
            'groupedData' => $groupedData,
            'year' => $year,
            'dtrackOffline' => !$isDTrackReachable // Pass status to view
        ]);
    }

    /**
     * Helper to map DTrack status codes to local status names
     */
    private function mapStatus($dtrackStatus)
    {
        // Add your mapping logic here
        $status = trim(strtolower($dtrackStatus));
        if (str_contains($status, 'approved') || str_contains($status, 'signed')) return 'Processing';
        if (str_contains($status, 'received')) return 'Pending';
        return 'In Transit';
    }

}