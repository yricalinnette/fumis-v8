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

        // 1. Check permissions using your Gate logic
        $isAdminOrBudget = \Illuminate\Support\Facades\Gate::allows('budget-section');

        $quarterMonths = [
            1 => [1, 2, 3], 2 => [4, 5, 6],
            3 => [7, 8, 9], 4 => [10, 11, 12],
        ];

        // 2. Fetch Section Names for mapping
        $sectionNames = \DB::connection('db_common')->table('tbl_section')->pluck('secname', 'secid');

        // 3. Build Query
        $query = \App\Models\SourceOfFund::where('fiscal_year', $year);

        // Apply Section Filter ONLY for regular users
        if (!$isAdminOrBudget) {
            $localDetail = \DB::table('employee_details')->where('user_id', auth()->id())->first();
            if ($localDetail) {
                $userSecId = \DB::connection('db_common')->table('tbl_emp_details')
                    ->where('dbedid', $localDetail->dbedid)
                    ->value('secid');
                if ($userSecId) {
                    $query->where('section_id', $userSecId);
                }
            }
        }

        $sources = $query->with(['activities', 'funds' => function ($query) use ($year, $month, $quarter, $quarterMonths) {
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

        // 4. Process and Add Section Names
        $reportData = $sources->map(function ($source) use ($year, $sectionNames) {
            $totalPooled = (float) $source->activities->sum('pooled_amount');
            $originalAllotted = (float) $source->total_amount; 
            $netAllotted = $originalAllotted - $totalPooled;

            $obligated = (float) $source->funds->sum('obligation_amount');
            $disbursed = (float) $source->funds->sum('disbursement_amount');

            $procurableBudget = $source->activities->where('is_for_procurement', 1)
                ->sum(fn($activity) => $activity->budget_adjusted - $activity->pooled_amount);

            $nonProcurableBudget = $source->activities->where('is_for_procurement', 0)
                ->sum(fn($activity) => $activity->budget_adjusted - $activity->pooled_amount);

            $pending = (float) $source->funds->filter(fn($f) => 
                (empty($f->obligation_date) || $f->obligation_amount <= 0) && ($f->disbursement_amount <= 0)
            )->sum('amount');

            $unobligated = $netAllotted - ($obligated + $pending);

            return [
                'section_id'                  => $source->section_id,
                'section_name'                => $sectionNames[$source->section_id] ?? 'General/Unassigned',
                'source_name'                 => $source->name,
                'source_total'                => $netAllotted, 
                'total_obligated'             => $obligated, 
                'total_disbursed'             => $disbursed,
                'total_pending'               => $pending, 
                'procurable_budget_total'     => $procurableBudget,     // Ensure this matches
                'non_procurable_budget_total' => $nonProcurableBudget, // Ensure this matches
                'total_unobligated'           => $unobligated > 0 ? $unobligated : 0,
                'overall_oblig_rate'          => $netAllotted > 0 ? ($obligated / $netAllotted) * 100 : 0,
                'overall_disb_rate'           => $obligated > 0 ? ($disbursed / $obligated) * 100 : 0,
            ];
        });

        // 5. Group by Section for the View
        $groupedReport = $reportData->groupBy('section_name');

        return view('admin.reports.by_source', compact('groupedReport', 'year'));
    }

    public function budgetByLineItem(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month');
        $quarter = $request->get('quarter');

        // 1. Check permissions using your Gate logic
        $isAdminOrBudget = \Illuminate\Support\Facades\Gate::allows('budget-section');

        $quarterMonths = [
            1 => [1, 2, 3], 2 => [4, 5, 6],
            3 => [7, 8, 9], 4 => [10, 11, 12],
        ];

        // 2. Fetch Section Names for mapping from db_common
        $sectionNames = \DB::connection('db_common')->table('tbl_section')->pluck('secname', 'secid');

        // 3. Build Query
        $query = \App\Models\SourceOfFund::where('fiscal_year', $year)
            ->with(['activities', 'funds']);

        // Apply Section Filter ONLY for regular users
        if (!$isAdminOrBudget) {
            $localDetail = \DB::table('employee_details')->where('user_id', auth()->id())->first();
            if ($localDetail) {
                $userSecId = \DB::connection('db_common')->table('tbl_emp_details')
                    ->where('dbedid', $localDetail->dbedid)
                    ->value('secid');
                if ($userSecId) {
                    $query->where('section_id', $userSecId);
                }
            }
        }

        $sources = $query->get();

        $reportData = $sources->map(function ($source) use ($year, $sectionNames) {
            $originalSourceTotal = (float) $source->total_amount;
            
            $lineItems = $source->activities->map(function ($activity) use ($source, $year) {
                $activityFunds = $source->funds->where('transaction_type_id', $activity->id);
                
                $obligated = (float) $activityFunds->sum('obligation_amount');
                $disbursed = (float) $activityFunds->sum('disbursement_amount');

                // Logic: Unpaid Obligations = Obligation - Disbursement
                $unpaid = $obligated - $disbursed;

                $activityBudgetGross = (float) ($activity->budget_adjusted ?? $activity->budget);
                $pooled = (float) ($activity->pooled_amount ?? 0);
                $activityBudgetNet = $activityBudgetGross - $pooled;

                $untouched = $activityBudgetNet - ($obligated + ($activityFunds->whereNull('obligation_date')->sum('amount')));

                return [
                    'name'              => $activity->name,
                    'net_budget'        => $activityBudgetNet,
                    'pooled_amount'     => $pooled,
                    'obligated_amount'  => $obligated,
                    'disbursed_amount'  => $disbursed,
                    'unpaid_amount'     => $unpaid,
                    'pending_amount'    => (float) $activityFunds->filter(fn($f) => empty($f->obligation_date) && $f->status !== 'Cancelled')->sum('amount'),
                    'untouched_amount'  => $untouched > 0 ? $untouched : 0,
                    'obligation_rate'   => $activityBudgetNet > 0 ? ($obligated / $activityBudgetNet) * 100 : 0,
                    'disbursement_rate' => $obligated > 0 ? ($disbursed / $obligated) * 100 : 0,
                ];
            });

            return [
                'section_name'          => $sectionNames[$source->section_id] ?? 'Unassigned Section',
                'source_name'           => $source->name,
                'source_total'          => $originalSourceTotal,
                'unassigned_balance'    => $originalSourceTotal - $source->activities->sum('budget_adjusted'),
                'line_items'            => $lineItems,
                'total_pending'         => $lineItems->sum('pending_amount'),
                'total_unpaid'          => $lineItems->sum('unpaid_amount'), 
                'total_untouched'       => $lineItems->sum('untouched_amount'),
                'total_activity_budget' => $lineItems->sum('net_budget'),
                'total_obligated'       => $lineItems->sum('obligated_amount'),
                'total_disbursed'       => $lineItems->sum('disbursed_amount'),
                'overall_disb_rate'     => $lineItems->sum('obligated_amount') > 0 ? ($lineItems->sum('disbursed_amount') / $lineItems->sum('obligated_amount')) * 100 : 0,
                'overall_oblig_rate'    => $lineItems->sum('net_budget') > 0 ? ($lineItems->sum('obligated_amount') / $lineItems->sum('net_budget')) * 100 : 0,
            ];
        })->groupBy('section_name'); // Grouped by Section Name from db_common

        return view('admin.reports.by_line_item', compact('reportData', 'year'));
    }

    public function byTransactions(Request $request)
    {
        set_time_limit(60);

        $year = $request->get('year', date('Y'));
        $month = $request->get('month');
        $quarter = $request->get('quarter');

        $quarterMonths = [
            1 => [1, 2, 3], 2 => [4, 5, 6],
            3 => [7, 8, 9], 4 => [10, 11, 12],
        ];

        // 1. Check permissions
        $isAdminOrBudget = \Illuminate\Support\Facades\Gate::allows('budget-section');

        // 2. Fetch Section Names from db_common for mapping
        $sectionNames = \DB::connection('db_common')
            ->table('tbl_section')
            ->pluck('secname', 'secid');

        // 3. Build Source of Fund Query
        $query = \App\Models\SourceOfFund::where('fiscal_year', $year)
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
            }]);

        // 4. Apply Section Filter ONLY for regular users
        if (!$isAdminOrBudget) {
            $localDetail = \DB::table('employee_details')->where('user_id', auth()->id())->first();
            if ($localDetail) {
                $userSecId = \DB::connection('db_common')->table('tbl_emp_details')
                    ->where('dbedid', $localDetail->dbedid)
                    ->value('secid');
                if ($userSecId) {
                    $query->where('section_id', $userSecId);
                }
            }
        }

        $sources = $query->get();

        // 5. Group and Map Data: Section -> Source -> Activity -> Transactions
        $groupedReport = $sources->groupBy('section_id')->map(function ($sectionSources, $sectionId) use ($sectionNames) {
            
            $sourcesData = $sectionSources->map(function ($source) {
                
                $fundsByActivity = $source->funds->groupBy('transaction_type_id');

                $activities = $source->activities->map(function ($activity) use ($fundsByActivity) {
                    $activityFunds = $fundsByActivity->get($activity->id, collect());

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
                    'source_name'  => $source->name,
                    'source_total' => $source->activities->sum(fn($a) => ($a->budget_adjusted ?? $a->budget) - ($a->pooled_amount ?? 0)),
                    'activities'   => $activities
                ];
            });

            return [
                'section_name' => $sectionNames[$sectionId] ?? 'Unknown Section',
                'sources'      => $sourcesData
            ];
        });

        return view('admin.reports.by_transactions', [
            'groupedReport' => $groupedReport,
            'year'          => $year,
            'isAdmin'       => $isAdminOrBudget
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