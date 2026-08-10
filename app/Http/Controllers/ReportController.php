<?php

namespace App\Http\Controllers;

use App\Models\Fund;
use Illuminate\Http\Request;
use App\Models\SourceOfFund;
use App\Models\Employee;
use App\Models\Activity;
use App\Exports\BudgetBySourceExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BudgetByLineItemExport;
use App\Exports\ActivityTransactionReportExport;

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

    private function getBudgetBySourceData(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month');
        $sourceType = $request->get('source_type');

        // INITIALIZE USER SCOPING VARIABLES
        $isAdminOrBudget = \Illuminate\Support\Facades\Gate::allows('budget-section');
        $isDivision = \Illuminate\Support\Facades\Gate::allows('division-access');
        $user = auth()->user();

        // Support both single 'quarter' and multiple 'quarters[]'
        $quarters = (array) $request->get('quarters', []);
        if (empty($quarters) && $request->has('quarter') && $request->get('quarter') !== '') {
            $quarters = [$request->get('quarter')];
        }

        $quarterMonthsMap = [
            1 => [1, 2, 3], 
            2 => [4, 5, 6], 
            3 => [7, 8, 9], 
            4 => [10, 11, 12]
        ];

        $selectedMonths = [];
        foreach ($quarters as $q) {
            if (isset($quarterMonthsMap[$q])) {
                $selectedMonths = array_merge($selectedMonths, $quarterMonthsMap[$q]);
            }
        }
        $selectedMonths = array_unique($selectedMonths);

        $sectionNames = \DB::connection('db_common')->table('tbl_section')->pluck('secname', 'secid');
        $query = SourceOfFund::where('fiscal_year', $year);

        if (!empty($sourceType)) {
            $query->where('source_type', $sourceType);
        }

        // --- REPORT SCOPING LOGIC ---
        if (!$isAdminOrBudget) {
            if ($isDivision) {
                $divisionSecIds = $user->getDivisionSectionIds();
                $query->whereIn('section_id', $divisionSecIds);
            } else {
                $localDetail = \DB::table('employee_details')->where('user_id', $user->id)->first();
                if ($localDetail) {
                    $userSecId = \DB::connection('db_common')->table('tbl_emp_details')
                        ->where('dbedid', $localDetail->dbedid)->value('secid');
                    if ($userSecId) {
                        $query->where('section_id', $userSecId);
                    }
                }
            }
        }

        // Eager load funds along with cosContract & cosSalaryDisbursements
        $sources = $query->with(['activities', 'funds' => function ($query) use ($year, $month, $selectedMonths) {
            $query->where(function($q) use ($year, $month, $selectedMonths) {
                
                // 1. Matched by Obligation Date
                $q->where(function($obligQ) use ($year, $month, $selectedMonths) {
                    $obligQ->whereYear('obligation_date', $year);
                    if ($month) {
                        $obligQ->whereMonth('obligation_date', $month);
                    } elseif (!empty($selectedMonths)) {
                        $obligQ->whereIn(\DB::raw('MONTH(obligation_date)'), $selectedMonths);
                    }
                })

                // 2. OR Matched by Main Disbursement Date
                ->orWhere(function($disbQ) use ($year, $month, $selectedMonths) {
                    $disbQ->whereYear('disbursement_date', $year);
                    if ($month) {
                        $disbQ->whereMonth('disbursement_date', $month);
                    } elseif (!empty($selectedMonths)) {
                        $disbQ->whereIn(\DB::raw('MONTH(disbursement_date)'), $selectedMonths);
                    }
                })

                // 3. OR Matched by Monthly COS Salary Disbursement Entries
                ->orWhereHas('cosSalaryDisbursements', function($cosQ) use ($year, $month, $selectedMonths) {
                    $cosQ->whereYear('disbursement_date', $year);
                    if ($month) {
                        $cosQ->whereMonth('disbursement_date', $month);
                    } elseif (!empty($selectedMonths)) {
                        $cosQ->whereIn(\DB::raw('MONTH(disbursement_date)'), $selectedMonths);
                    }
                })

                // 4. Fallback for un-obligated items
                ->orWhere(function($subYear) use ($year, $month, $selectedMonths) {
                    $subYear->whereNull('obligation_date')
                            ->whereNull('disbursement_date')
                            ->whereYear('created_at', $year);

                    if ($month) {
                        $subYear->whereMonth('created_at', $month);
                    } elseif (!empty($selectedMonths)) {
                        $subYear->whereIn(\DB::raw('MONTH(created_at)'), $selectedMonths);
                    }
                });

            })->whereNotIn('status', ['Cancelled', 'Rejected']);

        }, 'funds.cosContract', 'funds.cosSalaryDisbursements'])->get();

        $reportData = $sources->map(function ($source) use ($sectionNames, $year, $month, $selectedMonths) {
            
            // Calculate Pooled Amount and format Reasons for Hover Tooltip
            $pooledActivities = $source->activities->filter(fn($act) => ($act->pooled_amount ?? 0) > 0 || !empty($act->is_pooled));
            $totalPooled = (float) $pooledActivities->sum('pooled_amount');
            
            $pooledReasonsFormatted = $pooledActivities->map(function($act) {
                $reason = $act->pooled_remarks ?? $act->pooled_reason ?? $act->remarks ?? 'Pooled by Management';
                return '• ' . $act->name . ': ₱' . number_format($act->pooled_amount, 2) . ' (' . $reason . ')';
            })->implode("\n");

            $grossAllotted = (float) $source->total_amount;
            $adjustedAllotment = $grossAllotted - $totalPooled; // Adjusted Allotment = Gross - Pooled
            
            $obligated = (float) $source->funds->sum('obligation_amount');

            // CALCULATE DISBURSEMENTS
            $disbursed = (float) $source->funds->sum(function($f) use ($year, $month, $selectedMonths) {
                $isCosSalary = ($f->remarks_salary === 'Imported HR COS Salary/Wages' || $f->cos_contract_id !== null);

                if ($isCosSalary && $f->cosSalaryDisbursements && $f->cosSalaryDisbursements->count() > 0) {
                    return (float) $f->cosSalaryDisbursements->filter(function($cosDisb) use ($year, $month, $selectedMonths) {
                        if (empty($cosDisb->disbursement_date)) return false;
                        $dDate = \Carbon\Carbon::parse($cosDisb->disbursement_date);
                        if ($dDate->year != $year) return false;
                        if ($month && $dDate->month != $month) return false;
                        if (!empty($selectedMonths) && !in_array($dDate->month, $selectedMonths)) return false;
                        return true;
                    })->sum('amount');
                }

                if (empty($f->disbursement_date)) return 0.00;
                $dDate = \Carbon\Carbon::parse($f->disbursement_date);
                if ($dDate->year != $year) return 0.00;
                if ($month && $dDate->month != $month) return 0.00;
                if (!empty($selectedMonths) && !in_array($dDate->month, $selectedMonths)) return 0.00;

                return (float) $f->disbursement_amount;
            });

            $procurableBudget = $source->activities->where('is_for_procurement', 1)
                ->sum(fn($activity) => ($activity->budget_adjusted ?? $activity->budget) - $activity->pooled_amount);
            $nonProcurableBudget = $source->activities->where('is_for_procurement', 0)
                ->sum(fn($activity) => ($activity->budget_adjusted ?? $activity->budget) - $activity->pooled_amount);

            $pending = (float) $source->funds->filter(fn($f) => 
                (empty($f->obligation_date) || $f->obligation_amount <= 0) && ($f->disbursement_amount <= 0)
            )->sum('amount');

            // STRICT COS SALARY SAVINGS
            $savings = (float) $source->funds->filter(function ($fund) {
                return $fund->remarks_salary === 'Imported HR COS Salary/Wages' || 
                    $fund->status === 'Disbursed (with savings)';
            })->sum(function ($fund) {
                $isFullyDisbursed = false;

                if ($fund->cosContract && $fund->cosContract->total_months > 0) {
                    $isFullyDisbursed = ($fund->disbursed_months >= $fund->cosContract->total_months) || 
                                        ($fund->cosContract->status === 'Completed');
                } else {
                    $isFullyDisbursed = in_array($fund->status, ['Completed', 'Disbursed (with savings)']);
                }

                if ($isFullyDisbursed) {
                    $totalDisbursedAllTime = $fund->cosSalaryDisbursements && $fund->cosSalaryDisbursements->count() > 0
                        ? (float) $fund->cosSalaryDisbursements->sum('amount')
                        : (float) $fund->disbursement_amount;

                    $diff = (float)$fund->obligation_amount - $totalDisbursedAllTime;
                    return ($totalDisbursedAllTime > 0 && $diff > 0) ? $diff : 0.00;
                }

                return 0.00;
            });

            $unobligated = $adjustedAllotment - ($obligated + $pending);

            return [
                'section_id'                 => $source->section_id,
                'section_name'               => $sectionNames[$source->section_id] ?? 'General/Unassigned',
                'source_name'                => $source->name,
                'source_total'               => $grossAllotted, // Total Gross Fund Source
                'total_pooled'               => $totalPooled,
                'pooled_reasons'             => $pooledReasonsFormatted,
                'has_pooled'                 => $totalPooled > 0,
                'adjusted_allotment'         => $adjustedAllotment, // Net Allotment (Gross - Pooled)
                'total_obligated'            => $obligated, 
                'total_disbursed'            => $disbursed,
                'total_pending'              => $pending, 
                'total_savings'              => $savings,
                'procurable_budget_total'    => $procurableBudget,
                'non_procurable_budget_total'=> $nonProcurableBudget,
                'total_unobligated'          => $unobligated > 0 ? $unobligated : 0,
                'overall_oblig_rate'         => $adjustedAllotment > 0 ? ($obligated / $adjustedAllotment) * 100 : 0,
                'overall_disb_rate'          => $obligated > 0 ? ($disbursed / $obligated) * 100 : 0,
            ];
        });

        return $reportData->groupBy('section_name');
    }

    /**
     * View Method
     */
    public function budgetBySource(Request $request) 
    {
        $year = $request->get('year', date('Y'));
        $groupedReport = $this->getBudgetBySourceData($request);

        $sourceTypes = SourceOfFund::where('fiscal_year', $year)
            ->whereNotNull('source_type')->where('source_type', '!=', '')
            ->distinct()->pluck('source_type')->toArray();

        if (empty($sourceTypes)) {
            $sourceTypes = ['saa', 'regular', 'continuing'];
        }

        return view('admin.reports.by_source', compact('groupedReport', 'year', 'sourceTypes'));
    }

    /**
     * NEW METHOD: Handles the Excel download execution
     */
    public function exportBudgetBySource(Request $request)
    {
        $groupedReport = $this->getBudgetBySourceData($request);
        $year = $request->get('year', date('Y'));
        
        $filename = "Budget_By_Source_Report_{$year}.xlsx";

        return Excel::download(new BudgetBySourceExport($groupedReport), $filename);
    }

    /**
     * Helper Method: Extract Line Item Budget Data
     */
    public function getBudgetByLineItemData(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month');
        
        $quarters = (array) $request->get('quarters', []);
        if (empty($quarters) && $request->has('quarter') && $request->get('quarter') !== '') {
            $quarters = [$request->get('quarter')];
        }

        $isAdminOrBudget = \Illuminate\Support\Facades\Gate::allows('budget-section');
        $isDivision = \Illuminate\Support\Facades\Gate::allows('division-access');
        $user = auth()->user();

        $quarterMonthsMap = [
            1 => [1, 2, 3], 
            2 => [4, 5, 6],
            3 => [7, 8, 9], 
            4 => [10, 11, 12],
        ];

        $selectedMonths = [];
        foreach ($quarters as $q) {
            if (isset($quarterMonthsMap[$q])) {
                $selectedMonths = array_merge($selectedMonths, $quarterMonthsMap[$q]);
            }
        }
        $selectedMonths = array_unique($selectedMonths);

        $sectionNames = \DB::connection('db_common')->table('tbl_section')->pluck('secname', 'secid');

        // Eager load funds along with its COS salary disbursements & contract
        $query = \App\Models\SourceOfFund::where('fiscal_year', $year)
            ->with(['activities', 'funds' => function ($q) use ($year, $month, $selectedMonths) {
                
                $q->where(function($sub) use ($year, $month, $selectedMonths) {
                    
                    // 1. Matched by Obligation Date
                    $sub->where(function($obligQ) use ($year, $month, $selectedMonths) {
                        $obligQ->whereYear('obligation_date', $year);
                        if ($month) {
                            $obligQ->whereMonth('obligation_date', $month);
                        } elseif (!empty($selectedMonths)) {
                            $obligQ->whereIn(\DB::raw('MONTH(obligation_date)'), $selectedMonths);
                        }
                    })
                    
                    // 2. OR Matched by Main Disbursement Date
                    ->orWhere(function($disbQ) use ($year, $month, $selectedMonths) {
                        $disbQ->whereYear('disbursement_date', $year);
                        if ($month) {
                            $disbQ->whereMonth('disbursement_date', $month);
                        } elseif (!empty($selectedMonths)) {
                            $disbQ->whereIn(\DB::raw('MONTH(disbursement_date)'), $selectedMonths);
                        }
                    })

                    // 3. OR Matched by Monthly COS Salary Disbursement Entries
                    ->orWhereHas('cosSalaryDisbursements', function($cosQ) use ($year, $month, $selectedMonths) {
                        $cosQ->whereYear('disbursement_date', $year);
                        if ($month) {
                            $cosQ->whereMonth('disbursement_date', $month);
                        } elseif (!empty($selectedMonths)) {
                            $cosQ->whereIn(\DB::raw('MONTH(disbursement_date)'), $selectedMonths);
                        }
                    })

                    // 4. Fallback for un-obligated items
                    ->orWhere(function($subYear) use ($year, $month, $selectedMonths) {
                        $subYear->whereNull('obligation_date')
                                ->whereNull('disbursement_date')
                                ->whereYear('created_at', $year);

                        if ($month) {
                            $subYear->whereMonth('created_at', $month);
                        } elseif (!empty($selectedMonths)) {
                            $subYear->whereIn(\DB::raw('MONTH(created_at)'), $selectedMonths);
                        }
                    });

                })->whereNotIn('status', ['Cancelled', 'Rejected']);

            }, 'funds.cosContract', 'funds.cosSalaryDisbursements']);

        if (!$isAdminOrBudget) {
            if ($isDivision) {
                $divisionSecIds = $user->getDivisionSectionIds();
                $query->whereIn('section_id', $divisionSecIds);
            } else {
                $localDetail = \DB::table('employee_details')->where('user_id', $user->id)->first();
                if ($localDetail) {
                    $userSecId = \DB::connection('db_common')->table('tbl_emp_details')
                        ->where('dbedid', $localDetail->dbedid)
                        ->value('secid');
                    if ($userSecId) {
                        $query->where('section_id', $userSecId);
                    }
                }
            }
        }

        $sources = $query->get();

        return $sources->map(function ($source) use ($year, $month, $selectedMonths, $sectionNames) {
            $originalSourceTotal = (float) $source->total_amount;
            
            $lineItems = $source->activities->map(function ($activity) use ($source, $year, $month, $selectedMonths) {
                $activityFunds = $source->funds->where('transaction_type_id', $activity->id);
                
                // Filter Obligations by selected date
                $obligatedFunds = $activityFunds->filter(function($f) use ($year, $month, $selectedMonths) {
                    if (empty($f->obligation_date)) return false;
                    $oDate = \Carbon\Carbon::parse($f->obligation_date);
                    if ($oDate->year != $year) return false;
                    if ($month && $oDate->month != $month) return false;
                    if (!empty($selectedMonths) && !in_array($oDate->month, $selectedMonths)) return false;
                    return true;
                });
                $obligated = (float) $obligatedFunds->sum('obligation_amount');

                // CALCULATE DISBURSEMENTS (COS Salary Breakdown vs. Regular Funds)
                $disbursed = (float) $activityFunds->sum(function($f) use ($year, $month, $selectedMonths) {
                    $isCosSalary = ($f->remarks_salary === 'Imported HR COS Salary/Wages' || $f->cos_contract_id !== null);

                    // Scenario A: COS Salary — Sum discrete items from cos_salary_disbursements table
                    if ($isCosSalary && $f->cosSalaryDisbursements && $f->cosSalaryDisbursements->count() > 0) {
                        return (float) $f->cosSalaryDisbursements->filter(function($cosDisb) use ($year, $month, $selectedMonths) {
                            if (empty($cosDisb->disbursement_date)) return false;
                            $dDate = \Carbon\Carbon::parse($cosDisb->disbursement_date);
                            if ($dDate->year != $year) return false;
                            if ($month && $dDate->month != $month) return false;
                            if (!empty($selectedMonths) && !in_array($dDate->month, $selectedMonths)) return false;
                            return true;
                        })->sum('amount');
                    }

                    // Scenario B: Non-COS Fund — Check parent fund disbursement date
                    if (empty($f->disbursement_date)) return 0.00;
                    $dDate = \Carbon\Carbon::parse($f->disbursement_date);
                    if ($dDate->year != $year) return 0.00;
                    if ($month && $dDate->month != $month) return 0.00;
                    if (!empty($selectedMonths) && !in_array($dDate->month, $selectedMonths)) return 0.00;

                    return (float) $f->disbursement_amount;
                });

                $unpaid = $obligated - $disbursed;

                $activityBudgetGross = (float) ($activity->budget_adjusted ?? $activity->budget);
                $pooled = (float) ($activity->pooled_amount ?? 0);
                $activityBudgetNet = $activityBudgetGross - $pooled;

                $pending = (float) $activityFunds->filter(fn($f) => empty($f->obligation_date) && empty($f->disbursement_date) && $f->status !== 'Cancelled')->sum('amount');

                $untouched = $activityBudgetNet - ($obligated + $pending);

                // --- STRICT COS SALARY SAVINGS ---
                $cosSalaryFunds = $activityFunds->filter(function ($fund) {
                    return $fund->remarks_salary === 'Imported HR COS Salary/Wages' || 
                        $fund->status === 'Disbursed (with savings)';
                });

                $hasCosSalaryFunds = $cosSalaryFunds->count() > 0;

                $savings = 0.00;
                if ($hasCosSalaryFunds) {
                    $savings = (float) $cosSalaryFunds->sum(function ($fund) {
                        $isFullyDisbursed = false;

                        if ($fund->cosContract && $fund->cosContract->total_months > 0) {
                            $isFullyDisbursed = ($fund->disbursed_months >= $fund->cosContract->total_months) || 
                                                ($fund->cosContract->status === 'Completed');
                        } else {
                            $isFullyDisbursed = in_array($fund->status, ['Completed', 'Disbursed (with savings)']);
                        }

                        if ($isFullyDisbursed) {
                            $totalDisbursedAllTime = $fund->cosSalaryDisbursements && $fund->cosSalaryDisbursements->count() > 0
                                ? (float) $fund->cosSalaryDisbursements->sum('amount')
                                : (float) $fund->disbursement_amount;

                            $diff = (float)$fund->obligation_amount - $totalDisbursedAllTime;
                            return ($totalDisbursedAllTime > 0 && $diff > 0) ? $diff : 0.00;
                        }

                        return 0.00;
                    });
                }

                return [
                    'name'              => $activity->name,
                    'has_cos_salary'    => $hasCosSalaryFunds,
                    'net_budget'        => $activityBudgetNet,
                    'pooled_amount'     => $pooled,
                    'obligated_amount'  => $obligated,
                    'disbursed_amount'  => $disbursed,
                    'unpaid_amount'     => $unpaid,
                    'savings_amount'    => $savings,
                    'pending_amount'    => $pending,
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
                'total_savings'         => $lineItems->sum('savings_amount'),
                'total_untouched'       => $lineItems->sum('untouched_amount'),
                'total_activity_budget' => $lineItems->sum('net_budget'),
                'total_obligated'       => $lineItems->sum('obligated_amount'),
                'total_disbursed'       => $lineItems->sum('disbursed_amount'),
                'overall_disb_rate'     => $lineItems->sum('obligated_amount') > 0 ? ($lineItems->sum('disbursed_amount') / $lineItems->sum('obligated_amount')) * 100 : 0,
                'overall_oblig_rate'    => $lineItems->sum('net_budget') > 0 ? ($lineItems->sum('obligated_amount') / $lineItems->sum('net_budget')) * 100 : 0,
            ];
        })->groupBy('section_name');
    }

    /**
     * Web View Route Handler
     */
    public function budgetByLineItem(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $month = $request->get('month');
        
        $quarters = (array) $request->get('quarters', []);
        if (empty($quarters) && $request->has('quarter') && $request->get('quarter') !== '') {
            $quarters = [$request->get('quarter')];
        }

        $reportData = $this->getBudgetByLineItemData($request);

        return view('admin.reports.by_line_item', compact('reportData', 'year', 'month', 'quarters'));
    }

    /**
     * Excel Export Route Handler
     */
    public function exportBudgetByLineItem(Request $request)
    {
        // Fetch raw report data array/collection
        $reportData = $this->getBudgetByLineItemData($request); 

        $year = $request->get('year', date('Y'));
        $month = $request->get('month');
        
        $quarters = (array) $request->get('quarters', []);
        if (empty($quarters) && $request->has('quarter') && $request->get('quarter') !== '') {
            $quarters = [$request->get('quarter')];
        }

        $periodString = $month 
            ? date('F', mktime(0, 0, 0, (int) $month, 1)) 
            : (!empty($quarters) ? 'Q' . implode('_Q', $quarters) : 'FullYear');

        $fileName = "Line_Item_Budget_Report_{$year}_{$periodString}.xlsx";

        return Excel::download(new BudgetByLineItemExport($reportData), $fileName);
    }

    public function byTransactions(Request $request)
    {
        set_time_limit(120);

        $year          = $request->get('year', date('Y'));
        $month         = $request->get('month');
        $sectionFilter = $request->get('section_id'); 
        $search        = trim($request->get('search'));      

        // 1. CUMULATIVE MULTI-QUARTER CHECKBOX HANDLING
        $quartersInput = $request->get('quarters', $request->get('quarter') ? [$request->get('quarter')] : []);
        $selectedQuarters = is_array($quartersInput) ? array_map('intval', $quartersInput) : [(int)$quartersInput];
        $selectedQuarters = array_values(array_filter($selectedQuarters));

        $quarterMonthsMap = [
            1 => [1, 2, 3],
            2 => [4, 5, 6],
            3 => [7, 8, 9],
            4 => [10, 11, 12],
        ];

        $selectedMonths = [];
        foreach ($selectedQuarters as $q) {
            if (isset($quarterMonthsMap[$q])) {
                $selectedMonths = array_merge($selectedMonths, $quarterMonthsMap[$q]);
            }
        }
        $selectedMonths = array_values(array_unique($selectedMonths));

        // 2. Check Gates
        $isAdminOrBudget = \Illuminate\Support\Facades\Gate::allows('budget-section');
        $isDivision       = \Illuminate\Support\Facades\Gate::allows('division-access');
        $user             = auth()->user();

        // 3. Fetch Section Names from db_common
        $sectionNames = \DB::connection('db_common')
            ->table('tbl_section')
            ->pluck('secname', 'secid');

        // 4. Build Source of Fund Query (Exact same filtering as getBudgetByLineItemData)
        $query = \App\Models\SourceOfFund::where('fiscal_year', $year)
            ->with(['activities', 'funds' => function ($query) use ($year, $month, $selectedMonths, $search) {
                
                $query->where(function($sub) use ($year, $month, $selectedMonths) {
                    
                    // A. Matched by Obligation Date
                    $sub->where(function($obligQ) use ($year, $month, $selectedMonths) {
                        $obligQ->whereYear('obligation_date', $year);
                        if ($month) {
                            $obligQ->whereMonth('obligation_date', $month);
                        } elseif (!empty($selectedMonths)) {
                            $obligQ->whereIn(\DB::raw('MONTH(obligation_date)'), $selectedMonths);
                        }
                    })
                    
                    // B. OR Matched by Main Disbursement Date
                    ->orWhere(function($disbQ) use ($year, $month, $selectedMonths) {
                        $disbQ->whereYear('disbursement_date', $year);
                        if ($month) {
                            $disbQ->whereMonth('disbursement_date', $month);
                        } elseif (!empty($selectedMonths)) {
                            $disbQ->whereIn(\DB::raw('MONTH(disbursement_date)'), $selectedMonths);
                        }
                    })

                    // C. OR Matched by Monthly COS Salary Disbursement Entries
                    ->orWhereHas('cosSalaryDisbursements', function($cosQ) use ($year, $month, $selectedMonths) {
                        $cosQ->whereYear('disbursement_date', $year);
                        if ($month) {
                            $cosQ->whereMonth('disbursement_date', $month);
                        } elseif (!empty($selectedMonths)) {
                            $cosQ->whereIn(\DB::raw('MONTH(disbursement_date)'), $selectedMonths);
                        }
                    })

                    // D. Fallback for un-obligated items
                    ->orWhere(function($subYear) use ($year, $month, $selectedMonths) {
                        $subYear->whereNull('obligation_date')
                                ->whereNull('disbursement_date')
                                ->whereYear('created_at', $year);

                        if ($month) {
                            $subYear->whereMonth('created_at', $month);
                        } elseif (!empty($selectedMonths)) {
                            $subYear->whereIn(\DB::raw('MONTH(created_at)'), $selectedMonths);
                        }
                    });

                })->whereNotIn('status', ['Cancelled', 'Rejected']);

                // Apply Text Search inside Funds
                if (!empty($search)) {
                    $query->where(function($q) use ($search) {
                        $q->where('dtrack_no', 'LIKE', "%{$search}%")
                        ->orWhere('obligation_serial', 'LIKE', "%{$search}%")
                        ->orWhere('particulars', 'LIKE', "%{$search}%")
                        ->orWhere('creditor', 'LIKE', "%{$search}%")
                        ->orWhere('remarks', 'LIKE', "%{$search}%")
                        ->orWhere('manual_remarks', 'LIKE', "%{$search}%");
                    });
                }
            }, 'funds.cosContract', 'funds.creditors', 'funds.cosSalaryDisbursements']);

        // 5. TRANSACTION SCOPING & SECTION FILTER LOGIC
        if ($isAdminOrBudget || $isDivision) {
            if (!empty($sectionFilter)) {
                $query->where('section_id', $sectionFilter);
            } elseif ($isDivision && !$isAdminOrBudget) {
                $divisionSecIds = $user->getDivisionSectionIds();
                $query->whereIn('section_id', $divisionSecIds);
            }
        } else {
            $localDetail = \DB::table('employee_details')->where('user_id', $user->id)->first();
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

        // 6. Group and Map Data: Section -> Source -> Activity -> Transactions
        $groupedReport = $sources->groupBy('section_id')->map(function ($sectionSources, $sectionId) use ($sectionNames, $search, $year, $month, $selectedMonths) {
            
            $sourcesData = $sectionSources->map(function ($source) use ($search, $year, $month, $selectedMonths) {
                
                $fundsByActivity = $source->funds->groupBy('transaction_type_id');

                $activities = $source->activities->map(function ($activity) use ($fundsByActivity, $year, $month, $selectedMonths) {
                    $activityFunds = $fundsByActivity->get($activity->id, collect());

                    // Filter Obligations strictly matching date scope
                    $obligatedFunds = $activityFunds->filter(function($f) use ($year, $month, $selectedMonths) {
                        if (empty($f->obligation_date)) return false;
                        $oDate = \Carbon\Carbon::parse($f->obligation_date);
                        if ($oDate->year != $year) return false;
                        if ($month && $oDate->month != $month) return false;
                        if (!empty($selectedMonths) && !in_array($oDate->month, $selectedMonths)) return false;
                        return true;
                    });
                    $obligated = (float) $obligatedFunds->sum('obligation_amount');

                    // CALCULATE DISBURSEMENTS (COS Salary Breakdown vs. Regular Funds)
                    $disbursed = (float) $activityFunds->sum(function($f) use ($year, $month, $selectedMonths) {
                        $isCosSalary = ($f->remarks_salary === 'Imported HR COS Salary/Wages' || $f->cos_contract_id !== null);

                        if ($isCosSalary && $f->cosSalaryDisbursements && $f->cosSalaryDisbursements->count() > 0) {
                            return (float) $f->cosSalaryDisbursements->filter(function($cosDisb) use ($year, $month, $selectedMonths) {
                                if (empty($cosDisb->disbursement_date)) return false;
                                $dDate = \Carbon\Carbon::parse($cosDisb->disbursement_date);
                                if ($dDate->year != $year) return false;
                                if ($month && $dDate->month != $month) return false;
                                if (!empty($selectedMonths) && !in_array($dDate->month, $selectedMonths)) return false;
                                return true;
                            })->sum('amount');
                        }

                        if (empty($f->disbursement_date)) return 0.00;
                        $dDate = \Carbon\Carbon::parse($f->disbursement_date);
                        if ($dDate->year != $year) return 0.00;
                        if ($month && $dDate->month != $month) return 0.00;
                        if (!empty($selectedMonths) && !in_array($dDate->month, $selectedMonths)) return 0.00;

                        return (float) $f->disbursement_amount;
                    });

                    $pending = (float) $activityFunds->filter(fn($f) => empty($f->obligation_date) && empty($f->disbursement_date) && $f->status !== 'Cancelled')->sum('amount');

                    // --- STRICT COS SALARY SAVINGS ---
                    $cosSalaryFunds = $activityFunds->filter(function ($fund) {
                        return $fund->remarks_salary === 'Imported HR COS Salary/Wages' || 
                            $fund->status === 'Disbursed (with savings)';
                    });

                    $hasCosSalaryFunds = $cosSalaryFunds->count() > 0;
                    $savings = 0.00;

                    if ($hasCosSalaryFunds) {
                        $savings = (float) $cosSalaryFunds->sum(function ($fund) {
                            $isFullyDisbursed = false;

                            if ($fund->cosContract && $fund->cosContract->total_months > 0) {
                                $isFullyDisbursed = ($fund->disbursed_months >= $fund->cosContract->total_months) || 
                                                    ($fund->cosContract->status === 'Completed');
                            } else {
                                $isFullyDisbursed = in_array($fund->status, ['Completed', 'Disbursed (with savings)']);
                            }

                            if ($isFullyDisbursed) {
                                $totalDisbursedAllTime = $fund->cosSalaryDisbursements && $fund->cosSalaryDisbursements->count() > 0
                                    ? (float) $fund->cosSalaryDisbursements->sum('amount')
                                    : (float) $fund->disbursement_amount;

                                $diff = (float)$fund->obligation_amount - $totalDisbursedAllTime;
                                return ($totalDisbursedAllTime > 0 && $diff > 0) ? $diff : 0.00;
                            }

                            return 0.00;
                        });
                    }

                    $activityBudgetGross = (float) ($activity->budget_adjusted ?? $activity->budget);
                    $pooled = (float) ($activity->pooled_amount ?? 0);
                    $activityBudgetNet = $activityBudgetGross - $pooled;
                    $untouched = $activityBudgetNet - ($obligated + $pending);

                    return [
                        'details'          => $activity,
                        'net_budget'       => $activityBudgetNet,
                        'gross_budget'     => $activityBudgetGross,
                        'pooled_amount'    => $pooled,
                        'is_pooled'        => $pooled > 0 || !empty($activity->is_pooled),
                        'pooled_remarks'   => $activity->pooled_remarks ?? $activity->pooled_reason ?? $activity->remarks ?? 'Budget pooled by management',
                        'obligated'        => $obligated,
                        'disbursed'        => $disbursed,
                        'pending'          => $pending,
                        'savings'          => $savings,
                        'has_cos_salary'   => $hasCosSalaryFunds,
                        'untouched'        => $untouched > 0 ? $untouched : 0,
                        'transactions'     => $activityFunds->values() 
                    ];
                });

                // Filter out activities if search is active and no transactions matched
                if (!empty($search)) {
                    $activities = $activities->filter(function($act) use ($search) {
                        return $act['transactions']->isNotEmpty() || 
                            \Illuminate\Support\Str::contains(strtolower($act['details']->name), strtolower($search));
                    });
                }

                return [
                    'source_name'  => $source->name,
                    'source_total' => $source->activities->sum(fn($a) => ($a->budget_adjusted ?? $a->budget) - ($a->pooled_amount ?? 0)),
                    'activities'   => $activities
                ];
            })->filter(fn($source) => $source['activities']->isNotEmpty());

            return [
                'section_name' => $sectionNames[$sectionId] ?? 'Unknown / Unassigned Section',
                'sources'      => $sourcesData
            ];
        })->filter(fn($section) => $section['sources']->isNotEmpty());

        // 7. Handle Excel Export Request Trigger
        if ($request->has('export') && $request->export === 'excel') {
            $fileName = 'WFP_ByTransaction_Report_' . $year . '_' . now()->format('Ymd_His') . '.xlsx';
            return Excel::download(new ActivityTransactionReportExport($groupedReport), $fileName);
        }

        return view('admin.reports.by_transactions', [
            'groupedReport'   => $groupedReport,
            'year'            => $year,
            'month'           => $month,
            'selectedQuarters'=> $selectedQuarters,
            'selectedSection' => $sectionFilter,
            'search'          => $search,
            'sectionNames'    => $sectionNames,
            'canFilter'       => $isAdminOrBudget || $isDivision
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