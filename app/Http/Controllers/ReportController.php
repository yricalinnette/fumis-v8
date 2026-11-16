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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $year          = $request->get('year', date('Y'));
        $month         = $request->get('month');
        $sourceType    = $request->get('source_type');
        $sectionFilter = $request->get('section_id');

        $isAdminOrBudget = \Illuminate\Support\Facades\Gate::allows('budget-section');
        $isDivision      = \Illuminate\Support\Facades\Gate::allows('division-access');
        $user            = auth()->user();

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
        $selectedMonths = array_values(array_unique($selectedMonths));

        $allSections = \DB::connection('db_common')
            ->table('tbl_section')
            ->orderBy('secname')
            ->pluck('secname', 'secid');

        $query = SourceOfFund::where('fiscal_year', $year);

        if (!empty($sourceType)) {
            $query->where('source_type', $sourceType);
        }

        // --- SECTION / UNIT SCOPING & DROPDOWN FILTER ---
        if ($isAdminOrBudget) {
            if (!empty($sectionFilter)) {
                $query->where('section_id', $sectionFilter);
            }
        } elseif ($isDivision) {
            $divisionSecIds = $user->getDivisionSectionIds();
            $query->whereIn('section_id', $divisionSecIds);
            if (!empty($sectionFilter) && in_array($sectionFilter, $divisionSecIds)) {
                $query->where('section_id', $sectionFilter);
            }
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

        $sources = $query->with([
            'activities',
            'cancellations',
            'funds' => function ($query) use ($year, $month, $selectedMonths) {
                $query->where(function ($q) use ($year, $month, $selectedMonths) {
                    $q->where(function ($obligQ) use ($year, $month, $selectedMonths) {
                        $obligQ->whereYear('obligation_date', $year);
                        if ($month) {
                            $obligQ->whereMonth('obligation_date', $month);
                        } elseif (!empty($selectedMonths)) {
                            $obligQ->whereIn(\DB::raw('MONTH(obligation_date)'), $selectedMonths);
                        }
                    })
                    ->orWhere(function ($disbQ) use ($year, $month, $selectedMonths) {
                        $disbQ->whereYear('disbursement_date', $year);
                        if ($month) {
                            $disbQ->whereMonth('disbursement_date', $month);
                        } elseif (!empty($selectedMonths)) {
                            $disbQ->whereIn(\DB::raw('MONTH(disbursement_date)'), $selectedMonths);
                        }
                    })
                    ->orWhereHas('cosSalaryDisbursements', function ($cosQ) use ($year, $month, $selectedMonths) {
                        $cosQ->whereYear('disbursement_date', $year);
                        if ($month) {
                            $cosQ->whereMonth('disbursement_date', $month);
                        } elseif (!empty($selectedMonths)) {
                            $cosQ->whereIn(\DB::raw('MONTH(disbursement_date)'), $selectedMonths);
                        }
                    })
                    ->orWhere(function ($cancelQ) use ($year, $month, $selectedMonths) {
                        $cancelQ->where('status', 'Cancelled')
                                ->whereYear('status_date', $year);
                        if ($month) {
                            $cancelQ->whereMonth('status_date', $month);
                        } elseif (!empty($selectedMonths)) {
                            $cancelQ->whereIn(\DB::raw('MONTH(status_date)'), $selectedMonths);
                        }
                    })
                    ->orWhere(function ($subYear) use ($year, $month, $selectedMonths) {
                        $subYear->whereNull('obligation_date')
                                ->whereNull('disbursement_date')
                                ->whereYear('created_at', $year);

                        if ($month) {
                            $subYear->whereMonth('created_at', $month);
                        } elseif (!empty($selectedMonths)) {
                            $subYear->whereIn(\DB::raw('MONTH(created_at)'), $selectedMonths);
                        }
                    });
                })
                // Include Cancelled so prior obligations & cancellations reflect accurately, only exclude Rejected
                ->where('status', '!=', 'Rejected');
            },
            'funds.cosContract',
            'funds.cosSalaryDisbursements',
            'funds.norsaAdjustments',
            'funds.cancellations',
        ])->get();

        // Date filter helper for adjustment/cancellation records
        $matchesPeriod = function ($dateVal) use ($year, $month, $selectedMonths) {
            if (empty($dateVal)) return true;
            $d = \Carbon\Carbon::parse($dateVal);
            if ($d->year != $year) return false;
            if ($month && $d->month != $month) return false;
            if (!empty($selectedMonths) && !in_array($d->month, $selectedMonths)) return false;
            return true;
        };

        $reportData = $sources->map(function ($source) use ($allSections, $matchesPeriod) {
            
            $pooledActivities = $source->activities->filter(fn($act) => ($act->pooled_amount ?? 0) > 0 || !empty($act->is_pooled));
            $totalPooled = (float) $pooledActivities->sum('pooled_amount');
            
            $pooledReasonsFormatted = $pooledActivities->map(function ($act) {
                $reason = $act->pooled_remarks ?? $act->pooled_reason ?? $act->remarks ?? 'Pooled by Management';
                return '• ' . $act->name . ': ₱' . number_format($act->pooled_amount, 2) . ' (' . $reason . ')';
            })->implode("\n");

            $grossAllotted     = (float) $source->total_amount;
            $adjustedAllotment = $grossAllotted - $totalPooled;

            $norsaAmount         = 0.00;
            $cancelledFromFunds  = 0.00;
            $grossObligated      = 0.00;
            $overlapNorsaCancel  = 0.00;
            $cancelledFundIds    = [];

            foreach ($source->funds as $f) {
                // 1. Per-fund NORSA in selected period
                $fundNorsa = 0.00;
                if ($f->norsaAdjustments && $f->norsaAdjustments->count() > 0) {
                    $fundNorsa = (float) $f->norsaAdjustments->filter(fn($adj) => $matchesPeriod($adj->entry_date))
                        ->sum(fn($adj) => (float) ($adj->norsa_amount ?? $adj->amount ?? 0));
                }
                $norsaAmount += $fundNorsa;

                // 2. Per-fund Explicit Cancellation Records in selected period
                $fundRecordCancel = 0.00;
                if ($f->cancellations && $f->cancellations->count() > 0) {
                    $fundRecordCancel = (float) $f->cancellations->filter(fn($c) => $matchesPeriod($c->cancellation_date))
                        ->sum('cancelled_amount');
                }

                // 3. Determine Gross Obligation & whether 100% of obligation was reversed via NORSA
                $storedOb    = (float) $f->obligation_amount;
                $baseGrossOb = !empty($f->is_multi_activity) ? ($storedOb + $fundNorsa) : $storedOb;

                $isExplicitCancelled  = strtolower((string) $f->status) === 'cancelled';
                $isFullNorsaCancelled = ($baseGrossOb > 0 && abs($baseGrossOb - $fundNorsa) < 0.01);
                $isCancelledFund      = $isExplicitCancelled || $isFullNorsaCancelled;

                if ($isCancelledFund) {
                    $cancelledFundIds[] = $f->id;
                }

                // 4. Per-fund Cancelled Amount (from records, full NORSA reversal, or Cancelled status fallback)
                $fundCancelled = 0.00;
                if ($fundRecordCancel > 0) {
                    $fundCancelled = $fundRecordCancel;
                } elseif ($isFullNorsaCancelled) {
                    $fundCancelled = $baseGrossOb;
                } elseif ($isExplicitCancelled) {
                    $refDate = $f->status_date ?? $f->obligation_date ?? $f->created_at;
                    if ($matchesPeriod($refDate)) {
                        $fundCancelled = $storedOb > 0 ? $storedOb : (float) ($f->amount ?? 0);
                    }
                }
                $cancelledFromFunds += $fundCancelled;

                // Track overlap when a cancelled fund is reflected in BOTH NORSA and Cancelled
                if ($isCancelledFund && $fundNorsa > 0 && $fundCancelled > 0) {
                    $overlapNorsaCancel += min($fundNorsa, $fundCancelled);
                }

                // 5. Accumulate Gross Obligation if obligated in selected period
                if (!empty($f->obligation_date) && $matchesPeriod($f->obligation_date)) {
                    if ($isCancelledFund) {
                        $priorGross = $baseGrossOb > 0
                            ? $baseGrossOb
                            : ($fundCancelled > 0 ? $fundCancelled : ($fundNorsa > 0 ? $fundNorsa : (float) ($f->amount ?? 0)));
                        $grossObligated += $priorGross;
                    } else {
                        $grossObligated += $baseGrossOb;
                    }
                }
            }

            // Include any source-level cancellation records whose fund wasn't loaded in $source->funds
            $orphanSourceCancellations = (float) $source->cancellations->filter(function ($cancel) use ($matchesPeriod, $cancelledFundIds) {
                if (!empty($cancel->fund_id) && in_array($cancel->fund_id, $cancelledFundIds)) {
                    return false;
                }
                return $matchesPeriod($cancel->cancellation_date);
            })->sum('cancelled_amount');

            $cancelledAmount = $cancelledFromFunds + $orphanSourceCancellations;

            // Net Obligation = Gross Obligated - NORSA - Cancelled + Overlap (prevents double-deducting full NORSA cancellations)
            $netObligated = max(0.00, $grossObligated - $norsaAmount - $cancelledAmount + $overlapNorsaCancel);

            // 6. CALCULATE DISBURSEMENTS (Exclude Cancelled & Full-NORSA-Cancelled funds)
            $disbursed = (float) $source->funds->filter(fn($f) => !in_array($f->id, $cancelledFundIds))
                ->sum(function ($f) use ($matchesPeriod) {
                    $isCosSalary = ($f->remarks_salary === 'Imported HR COS Salary/Wages' || $f->cos_contract_id !== null);

                    if ($isCosSalary && $f->cosSalaryDisbursements && $f->cosSalaryDisbursements->count() > 0) {
                        return (float) $f->cosSalaryDisbursements->filter(function ($cosDisb) use ($matchesPeriod) {
                            if (empty($cosDisb->disbursement_date)) return false;
                            return $matchesPeriod($cosDisb->disbursement_date);
                        })->sum('amount');
                    }

                    if (empty($f->disbursement_date) || !$matchesPeriod($f->disbursement_date)) return 0.00;

                    return (float) $f->disbursement_amount;
                });

            $procurableBudget = $source->activities->where('is_for_procurement', 1)
                ->sum(fn($activity) => ($activity->budget_adjusted ?? $activity->budget) - $activity->pooled_amount);
            $nonProcurableBudget = $source->activities->where('is_for_procurement', 0)
                ->sum(fn($activity) => ($activity->budget_adjusted ?? $activity->budget) - $activity->pooled_amount);

            // Exclude Cancelled funds from Pending (their allotment returns to Unobligated Balance)
            $pending = (float) $source->funds->filter(fn($f) => 
                !in_array($f->id, $cancelledFundIds) &&
                (empty($f->obligation_date) || $f->obligation_amount <= 0) &&
                ($f->disbursement_amount <= 0)
            )->sum('amount');

            // 7. COS SALARY SAVINGS (Exclude Cancelled)
            $cosSavings = (float) $source->funds->filter(function ($fund) use ($cancelledFundIds) {
                if (in_array($fund->id, $cancelledFundIds)) return false;
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

                    $diff = (float) $fund->obligation_amount - $totalDisbursedAllTime;
                    return ($totalDisbursedAllTime > 0 && $diff > 0) ? $diff : 0.00;
                }

                return 0.00;
            });

            // 8. PROCUREMENT SAVINGS (Exclude Cancelled Procurement Transactions)
            $procurementSavings = 0.00;
            $procTransactions = \App\Models\ProcurementTransaction::with('items')
                ->where('source_of_fund_id', $source->id)
                ->whereNotIn('caf_status', ['Cancelled', 'Rejected'])
                ->get();

            foreach ($procTransactions as $procTrx) {
                if ($procTrx->items && $procTrx->items->count() > 0) {
                    foreach ($procTrx->items as $pItem) {
                        if (strtolower((string) ($pItem->status ?? '')) === 'cancelled') continue;
                        if (!is_null($pItem->po_amount)) {
                            $procurementSavings += max(0, (float) $pItem->item_amount - (float) $pItem->po_amount);
                        }
                    }
                } else {
                    if (!is_null($procTrx->po_amount)) {
                        $procurementSavings += max(0, (float) $procTrx->abc_allotment - (float) $procTrx->po_amount);
                    }
                }
            }

            $totalSavings      = $cosSavings + $procurementSavings;
            $unpaidObligations = max(0.00, $netObligated - $disbursed);

            // Unobligated Allotment Balance (Total Fund Source - Net Obligated)
            $unobligatedAllotmentBalance = max(0.00, $grossAllotted - $netObligated);

            // Unobligated (Minus Pending Transactions) = Adjusted Allotment - (Net Obligated + Pending)
            $unobligatedMinusPending = max(0.00, $adjustedAllotment - ($netObligated + $pending));

            return [
                'section_id'                    => $source->section_id,
                'section_name'                  => $allSections[$source->section_id] ?? 'General/Unassigned',
                'source_name'                   => $source->name,
                'source_type'                   => $source->source_type,
                'source_total'                  => $grossAllotted,
                'total_pooled'                  => $totalPooled,
                'pooled_reasons'                => $pooledReasonsFormatted,
                'has_pooled'                    => $totalPooled > 0,
                'adjusted_allotment'            => $adjustedAllotment,
                'gross_obligated'               => $grossObligated,
                'total_norsa'                   => $norsaAmount,
                'total_cancelled'               => $cancelledAmount,
                'total_obligated'               => $netObligated,
                'total_disbursed'               => $disbursed,
                'total_unpaid'                  => $unpaidObligations,
                'total_pending'                 => $pending, 
                'total_savings'                 => $totalSavings,
                'procurable_budget_total'       => $procurableBudget,
                'non_procurable_budget_total'   => $nonProcurableBudget,
                'unobligated_allotment_balance' => $unobligatedAllotmentBalance,
                'total_unobligated'             => $unobligatedMinusPending,
                'overall_oblig_rate'            => $adjustedAllotment > 0 ? ($netObligated / $adjustedAllotment) * 100 : 0,
                'overall_disb_rate'             => $netObligated > 0 ? ($disbursed / $netObligated) * 100 : 0,
            ];
        });

        return $reportData->groupBy('section_name');
    }

    /**
     * View Method
     */
    public function budgetBySource(Request $request)
    {
        $groupedReport = $this->getBudgetBySourceData($request);

        $isAdminOrBudget  = \Illuminate\Support\Facades\Gate::allows('budget-section');
        $isDivision       = \Illuminate\Support\Facades\Gate::allows('division-access');
        $canFilterSection = $isAdminOrBudget || $isDivision;

        $sectionQuery = \DB::connection('db_common')->table('tbl_section')->orderBy('secname');
        if ($isDivision && !$isAdminOrBudget) {
            $sectionQuery->whereIn('secid', auth()->user()->getDivisionSectionIds());
        }
        $sectionOptions = $canFilterSection ? $sectionQuery->pluck('secname', 'secid') : collect();

        return view('admin.reports.by_source', compact(
            'groupedReport',
            'sectionOptions',
            'canFilterSection'
        ));
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
        $year  = $request->get('year', date('Y'));
        $month = $request->get('month');
        
        $quarters = (array) $request->get('quarters', []);
        if (empty($quarters) && $request->has('quarter') && $request->get('quarter') !== '') {
            $quarters = [$request->get('quarter')];
        }

        $isAdminOrBudget = \Illuminate\Support\Facades\Gate::allows('budget-section');
        $isDivision      = \Illuminate\Support\Facades\Gate::allows('division-access');
        $user            = auth()->user();

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

        $query = \App\Models\SourceOfFund::where('fiscal_year', $year)
            ->with([
                'activities', 
                'funds' => function ($q) use ($year, $month, $selectedMonths) {
                    $q->where(function ($sub) use ($year, $month, $selectedMonths) {
                        $sub->where(function ($obligQ) use ($year, $month, $selectedMonths) {
                            $obligQ->whereYear('obligation_date', $year);
                            if ($month) {
                                $obligQ->whereMonth('obligation_date', $month);
                            } elseif (!empty($selectedMonths)) {
                                $obligQ->whereIn(\DB::raw('MONTH(obligation_date)'), $selectedMonths);
                            }
                        })
                        ->orWhere(function ($disbQ) use ($year, $month, $selectedMonths) {
                            $disbQ->whereYear('disbursement_date', $year);
                            if ($month) {
                                $disbQ->whereMonth('disbursement_date', $month);
                            } elseif (!empty($selectedMonths)) {
                                $disbQ->whereIn(\DB::raw('MONTH(disbursement_date)'), $selectedMonths);
                            }
                        })
                        ->orWhereHas('cosSalaryDisbursements', function ($cosQ) use ($year, $month, $selectedMonths) {
                            $cosQ->whereYear('disbursement_date', $year);
                            if ($month) {
                                $cosQ->whereMonth('disbursement_date', $month);
                            } elseif (!empty($selectedMonths)) {
                                $cosQ->whereIn(\DB::raw('MONTH(disbursement_date)'), $selectedMonths);
                            }
                        })
                        ->orWhere(function ($cancelQ) use ($year, $month, $selectedMonths) {
                            $cancelQ->where('status', 'Cancelled')
                                    ->whereYear('status_date', $year);
                            if ($month) {
                                $cancelQ->whereMonth('status_date', $month);
                            } elseif (!empty($selectedMonths)) {
                                $cancelQ->whereIn(\DB::raw('MONTH(status_date)'), $selectedMonths);
                            }
                        })
                        ->orWhere(function ($subYear) use ($year, $month, $selectedMonths) {
                            $subYear->whereNull('obligation_date')
                                    ->whereNull('disbursement_date')
                                    ->whereYear('created_at', $year);

                            if ($month) {
                                $subYear->whereMonth('created_at', $month);
                            } elseif (!empty($selectedMonths)) {
                                $subYear->whereIn(\DB::raw('MONTH(created_at)'), $selectedMonths);
                            }
                        });
                    })
                    // Include Cancelled so prior obligations & cancellations reflect accurately, only exclude Rejected
                    ->where('status', '!=', 'Rejected');
                }, 
                'funds.cosContract', 
                'funds.cosSalaryDisbursements', 
                'funds.norsaAdjustments',
                'funds.cancellations',
                'funds.multiActivities',
                'funds.procurementTransactions.items'
            ]);

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

        // Period matcher helper
        $matchesPeriod = function ($dateVal) use ($year, $month, $selectedMonths) {
            if (empty($dateVal)) return true;
            $d = \Carbon\Carbon::parse($dateVal);
            if ($d->year != $year) return false;
            if ($month && $d->month != $month) return false;
            if (!empty($selectedMonths) && !in_array($d->month, $selectedMonths)) return false;
            return true;
        };

        return $sources->map(function ($source) use ($year, $month, $selectedMonths, $sectionNames, $matchesPeriod) {
            $originalSourceTotal = (float) $source->total_amount;
            
            $lineItems = $source->activities->map(function ($activity) use ($source, $matchesPeriod) {

                // 1. Single activity funds assigned directly to this specific transaction_type_id
                $directFunds = $source->funds
                    ->where('transaction_type_id', $activity->id)
                    ->where('is_multi_activity', 0);

                // 2. Multi-activity parent funds containing items matching this exact activity ID
                $multiFunds = $source->funds->filter(function ($f) use ($activity) {
                    if (empty($f->is_multi_activity) || !$f->multiActivities) return false;
                    return $f->multiActivities->contains('transaction_type_id', $activity->id);
                });

                $grossObligated       = 0.00;
                $norsaAmount          = 0.00;
                $cancelledAmount      = 0.00;
                $overlapNorsaCancel   = 0.00;
                $disbursed            = 0.00;
                $cancelledDirectIds   = [];
                $cancelledMultiIds    = [];

                // Direct Funds: Gross Obligation, NORSA, & Cancellations (including Full-NORSA Cancellations)
                foreach ($directFunds as $f) {
                    $fundNorsa = 0.00;
                    if ($f->norsaAdjustments && $f->norsaAdjustments->count() > 0) {
                        $fundNorsa = (float) $f->norsaAdjustments->filter(function ($adj) use ($activity, $matchesPeriod) {
                            if ($adj->transaction_type_id && $adj->transaction_type_id !== $activity->id) {
                                return false;
                            }
                            return $matchesPeriod($adj->entry_date);
                        })->sum(fn($adj) => (float) ($adj->norsa_amount ?? $adj->amount ?? 0));
                    }
                    $norsaAmount += $fundNorsa;

                    $storedOb            = (float) $f->obligation_amount;
                    $isExplicitCancelled = strtolower((string) $f->status) === 'cancelled';
                    $isFullNorsaCancel   = ($storedOb > 0 && abs($storedOb - $fundNorsa) < 0.01);
                    $isCancelledFund     = $isExplicitCancelled || $isFullNorsaCancel;

                    if ($isCancelledFund) {
                        $cancelledDirectIds[] = $f->id;
                    }

                    $fundCancelled = 0.00;
                    if ($f->cancellations && $f->cancellations->count() > 0) {
                        $fundCancelled = (float) $f->cancellations->filter(function ($c) use ($matchesPeriod) {
                            return $matchesPeriod($c->cancellation_date);
                        })->sum('cancelled_amount');
                    } elseif ($isFullNorsaCancel) {
                        $fundCancelled = $storedOb;
                    } elseif ($isExplicitCancelled) {
                        $refDate = $f->status_date ?? $f->obligation_date ?? $f->created_at;
                        if ($matchesPeriod($refDate)) {
                            $fundCancelled = $storedOb > 0 ? $storedOb : (float) ($f->amount ?? 0);
                        }
                    }
                    $cancelledAmount += $fundCancelled;

                    if ($isCancelledFund && $fundNorsa > 0 && $fundCancelled > 0) {
                        $overlapNorsaCancel += min($fundNorsa, $fundCancelled);
                    }

                    if (!empty($f->obligation_date) && $matchesPeriod($f->obligation_date)) {
                        if ($isCancelledFund) {
                            $priorGross = $storedOb > 0
                                ? $storedOb
                                : ($fundCancelled > 0 ? $fundCancelled : ($fundNorsa > 0 ? $fundNorsa : (float) ($f->amount ?? 0)));
                            $grossObligated += $priorGross;
                        } else {
                            $grossObligated += $storedOb;
                        }
                    }
                }

                // Multi-Activity Line Items: Gross Obligation, NORSA, & Cancellations (including Full-NORSA Cancellations)
                foreach ($multiFunds as $f) {
                    $matchedItems      = $f->multiActivities->where('transaction_type_id', $activity->id);
                    $totalRoutedAll    = (float) $f->multiActivities->sum('routed_amount');
                    $matchedRouted     = (float) $matchedItems->sum('routed_amount');
                    $shareRatio        = $totalRoutedAll > 0 ? ($matchedRouted / $totalRoutedAll) : 0;

                    $activityNorsaForFund = 0.00;
                    if ($f->norsaAdjustments && $f->norsaAdjustments->count() > 0) {
                        $activityNorsaForFund = (float) $f->norsaAdjustments->filter(function ($adj) use ($activity, $matchesPeriod) {
                            if ($adj->transaction_type_id && $adj->transaction_type_id !== $activity->id) {
                                return false;
                            }
                            return $matchesPeriod($adj->entry_date);
                        })->sum(fn($adj) => (float) ($adj->norsa_amount ?? $adj->amount ?? 0));
                    }
                    $norsaAmount += $activityNorsaForFund;

                    $lineItemsNetOblig    = (float) $matchedItems->sum('obligation_amount');
                    $lineItemsGrossOblig  = $lineItemsNetOblig + $activityNorsaForFund;
                    $isCancelledParent    = strtolower((string) $f->status) === 'cancelled';
                    $isFullNorsaMulti     = ($lineItemsGrossOblig > 0 && abs($lineItemsGrossOblig - $activityNorsaForFund) < 0.01);
                    $isCancelledMultiItem = $isCancelledParent || $isFullNorsaMulti;

                    if ($isCancelledMultiItem) {
                        $cancelledMultiIds[] = $f->id;
                    }

                    $activityCancelledForFund = 0.00;
                    if ($f->cancellations && $f->cancellations->count() > 0) {
                        $parentCancelTotal = (float) $f->cancellations->filter(fn($c) => $matchesPeriod($c->cancellation_date))
                            ->sum('cancelled_amount');
                        $activityCancelledForFund = round($parentCancelTotal * $shareRatio, 2);
                    } elseif ($isFullNorsaMulti) {
                        $activityCancelledForFund = $lineItemsGrossOblig;
                    } else {
                        $cancelledLineItems = $matchedItems->filter(fn($item) => $isCancelledParent || strtolower((string) ($item->status ?? '')) === 'cancelled');
                        if ($cancelledLineItems->count() > 0) {
                            $refDate = $f->status_date ?? $f->obligation_date ?? $f->created_at;
                            if ($matchesPeriod($refDate)) {
                                $activityCancelledForFund = (float) $cancelledLineItems->sum(function ($item) {
                                    return (float) ($item->obligation_amount > 0 ? $item->obligation_amount : ($item->routed_amount ?? 0));
                                });
                            }
                        }
                    }
                    $cancelledAmount += $activityCancelledForFund;

                    if ($isCancelledMultiItem && $activityNorsaForFund > 0 && $activityCancelledForFund > 0) {
                        $overlapNorsaCancel += min($activityNorsaForFund, $activityCancelledForFund);
                    }

                    if (!empty($f->obligation_date) && $matchesPeriod($f->obligation_date)) {
                        if ($isCancelledParent && $lineItemsNetOblig <= 0) {
                            $grossObligated += ($activityCancelledForFund > 0 ? $activityCancelledForFund : $matchedRouted);
                        } else {
                            // Multi-activity obligation_amount is stored NET of NORSA; add back NORSA once for Gross
                            $grossObligated += $lineItemsGrossOblig;
                        }
                    }
                }

                // Net Obligation (Gross minus NORSA and Cancellations, without double-counting overlap)
                $netObligated = max(0.00, $grossObligated - $norsaAmount - $cancelledAmount + $overlapNorsaCancel);

                // --- CALCULATE DISBURSEMENTS (Exclude Cancelled & Full-NORSA-Cancelled funds) ---
                foreach ($directFunds as $f) {
                    if (in_array($f->id, $cancelledDirectIds)) continue;

                    $isCosSalary = ($f->remarks_salary === 'Imported HR COS Salary/Wages' || $f->cos_contract_id !== null);

                    if ($isCosSalary && $f->cosSalaryDisbursements && $f->cosSalaryDisbursements->count() > 0) {
                        $disbursed += (float) $f->cosSalaryDisbursements->filter(function ($cosDisb) use ($matchesPeriod) {
                            if (empty($cosDisb->disbursement_date)) return false;
                            return $matchesPeriod($cosDisb->disbursement_date);
                        })->sum('amount');
                        continue;
                    }

                    if (empty($f->disbursement_date) || !$matchesPeriod($f->disbursement_date)) continue;

                    $disbursed += (float) $f->disbursement_amount;
                }

                foreach ($multiFunds as $f) {
                    if (in_array($f->id, $cancelledMultiIds)) continue;
                    if (empty($f->disbursement_date) || !$matchesPeriod($f->disbursement_date)) continue;

                    $matchedItems = $f->multiActivities
                        ->where('transaction_type_id', $activity->id)
                        ->filter(fn($item) => strtolower((string) ($item->status ?? '')) !== 'cancelled');
                    $disbursed += (float) $matchedItems->sum('disbursement_amount');
                }

                // Disbursement Rate against Net Obligation (capped at 100%)
                $disbursementRate = $netObligated > 0 
                    ? min(100.0, ($disbursed / $netObligated) * 100) 
                    : 0;

                $unpaid = max(0.00, $netObligated - $disbursed);

                $activityBudgetGross = (float) ($activity->budget_adjusted ?? $activity->budget);
                $pooled              = (float) ($activity->pooled_amount ?? 0);
                $activityBudgetNet   = $activityBudgetGross - $pooled;

                // Pending transactions (both direct and multi-activity un-obligated items, excluding Cancelled)
                $directPending = (float) $directFunds->filter(fn($f) => 
                    !in_array($f->id, $cancelledDirectIds) &&
                    (empty($f->obligation_date) || $f->obligation_amount <= 0) &&
                    ($f->disbursement_amount <= 0)
                )->sum('amount');

                $multiPending = 0.00;
                foreach ($multiFunds as $f) {
                    if (!in_array($f->id, $cancelledMultiIds) && (empty($f->obligation_date) || $f->obligation_amount <= 0) && ($f->disbursement_amount <= 0)) {
                        $multiPending += (float) $f->multiActivities
                            ->where('transaction_type_id', $activity->id)
                            ->filter(fn($item) => strtolower((string) ($item->status ?? '')) !== 'cancelled')
                            ->sum('routed_amount');
                    }
                }
                $pending = $directPending + $multiPending;

                $untouched = $activityBudgetNet - ($netObligated + $pending);

                // Procurement Savings (Exclude Cancelled)
                $procurementSavings = 0.00;
                $procTransactions = \App\Models\ProcurementTransaction::with('items')
                    ->where('source_of_fund_id', $source->id)
                    ->where('activity_id', $activity->id)
                    ->whereNotIn('caf_status', ['Cancelled', 'Rejected'])
                    ->get();

                foreach ($procTransactions as $procTrx) {
                    if ($procTrx->items && $procTrx->items->count() > 0) {
                        foreach ($procTrx->items as $pItem) {
                            if (strtolower((string) ($pItem->status ?? '')) === 'cancelled') continue;
                            if (!is_null($pItem->po_amount)) {
                                $procurementSavings += max(0, (float) $pItem->item_amount - (float) $pItem->po_amount);
                            }
                        }
                    } else {
                        if (!is_null($procTrx->po_amount)) {
                            $procurementSavings += max(0, (float) $procTrx->abc_allotment - (float) $procTrx->po_amount);
                        }
                    }
                }

                // COS Contract Completed Savings (Exclude Cancelled)
                $cosSalaryFunds = $directFunds->filter(function ($fund) use ($cancelledDirectIds) {
                    if (in_array($fund->id, $cancelledDirectIds)) return false;
                    return $fund->remarks_salary === 'Imported HR COS Salary/Wages' || 
                        $fund->status === 'Disbursed (with savings)';
                });

                $hasCosSalaryFunds = $cosSalaryFunds->count() > 0;

                $cosSavings = 0.00;
                if ($hasCosSalaryFunds) {
                    $cosSavings = (float) $cosSalaryFunds->sum(function ($fund) {
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

                            $diff = (float) $fund->obligation_amount - $totalDisbursedAllTime;
                            return ($totalDisbursedAllTime > 0 && $diff > 0) ? $diff : 0.00;
                        }

                        return 0.00;
                    });
                }

                $totalActivitySavings = $procurementSavings + $cosSavings;

                return [
                    'name'              => $activity->name,
                    'has_cos_salary'    => $hasCosSalaryFunds || $procurementSavings > 0,
                    'net_budget'        => $activityBudgetNet,
                    'pooled_amount'     => $pooled,
                    'gross_obligated'   => $grossObligated,
                    'norsa_amount'      => $norsaAmount,
                    'cancelled_amount'  => $cancelledAmount,
                    'obligated_amount'  => $netObligated,
                    'disbursed_amount'  => $disbursed,
                    'unpaid_amount'     => $unpaid,
                    'savings_amount'    => $totalActivitySavings,
                    'pending_amount'    => $pending,
                    'untouched_amount'  => $untouched > 0 ? $untouched : 0,
                    'obligation_rate'   => $activityBudgetNet > 0 ? ($netObligated / $activityBudgetNet) * 100 : 0,
                    'disbursement_rate' => $disbursementRate,
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
                'total_norsa'           => $lineItems->sum('norsa_amount'),
                'total_cancelled'       => $lineItems->sum('cancelled_amount'),
                'total_untouched'       => $lineItems->sum('untouched_amount'),
                'total_activity_budget' => $lineItems->sum('net_budget'),
                'total_gross_obligated' => $lineItems->sum('gross_obligated'),
                'total_obligated'       => $lineItems->sum('obligated_amount'),
                'total_disbursed'       => $lineItems->sum('disbursed_amount'),
                'overall_disb_rate'     => $lineItems->sum('obligated_amount') > 0 
                    ? min(100.0, ($lineItems->sum('disbursed_amount') / $lineItems->sum('obligated_amount')) * 100) 
                    : 0,
                'overall_oblig_rate'    => $lineItems->sum('net_budget') > 0 ? ($lineItems->sum('obligated_amount') / $lineItems->sum('net_budget')) * 100 : 0,
            ];
        })->groupBy('section_name');
    }

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
    public function byTransactions(Request $request)
    {
        set_time_limit(120);

        $year          = $request->get('year', date('Y'));
        $month         = $request->get('month');
        $sectionFilter = $request->get('section_id'); 
        $search        = trim($request->get('search'));      

        // 1. CUMULATIVE MULTI-QUARTER CHECKBOX HANDLING
        $quartersInput = $request->get('quarters', $request->get('quarter') ? [$request->get('quarter')] : []);
        $selectedQuarters = is_array($quartersInput) ? array_map('intval', $quartersInput) : [(int) $quartersInput];
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
        $isDivision      = \Illuminate\Support\Facades\Gate::allows('division-access');
        $user            = auth()->user();

        // 3. Fetch Section Names from db_common
        $sectionNames = \DB::connection('db_common')
            ->table('tbl_section')
            ->pluck('secname', 'secid');

        // 4. Build Source of Fund Query (Eager load multiActivities & cancellations)
        $query = \App\Models\SourceOfFund::where('fiscal_year', $year)
            ->with([
                'activities',
                'funds' => function ($query) use ($year, $month, $selectedMonths, $search) {
                    $query->where(function ($sub) use ($year, $month, $selectedMonths) {
                        // A. Matched by Obligation Date
                        $sub->where(function ($obligQ) use ($year, $month, $selectedMonths) {
                            $obligQ->whereYear('obligation_date', $year);
                            if ($month) {
                                $obligQ->whereMonth('obligation_date', $month);
                            } elseif (!empty($selectedMonths)) {
                                $obligQ->whereIn(\DB::raw('MONTH(obligation_date)'), $selectedMonths);
                            }
                        })
                        // B. OR Matched by Main Disbursement Date
                        ->orWhere(function ($disbQ) use ($year, $month, $selectedMonths) {
                            $disbQ->whereYear('disbursement_date', $year);
                            if ($month) {
                                $disbQ->whereMonth('disbursement_date', $month);
                            } elseif (!empty($selectedMonths)) {
                                $disbQ->whereIn(\DB::raw('MONTH(disbursement_date)'), $selectedMonths);
                            }
                        })
                        // C. OR Matched by Monthly COS Salary Disbursement Entries
                        ->orWhereHas('cosSalaryDisbursements', function ($cosQ) use ($year, $month, $selectedMonths) {
                            $cosQ->whereYear('disbursement_date', $year);
                            if ($month) {
                                $cosQ->whereMonth('disbursement_date', $month);
                            } elseif (!empty($selectedMonths)) {
                                $cosQ->whereIn(\DB::raw('MONTH(disbursement_date)'), $selectedMonths);
                            }
                        })
                        // D. OR Matched by Cancellation Status Date
                        ->orWhere(function ($cancelQ) use ($year, $month, $selectedMonths) {
                            $cancelQ->where('status', 'Cancelled')
                                    ->whereYear('status_date', $year);
                            if ($month) {
                                $cancelQ->whereMonth('status_date', $month);
                            } elseif (!empty($selectedMonths)) {
                                $cancelQ->whereIn(\DB::raw('MONTH(status_date)'), $selectedMonths);
                            }
                        })
                        // E. Fallback for un-obligated items
                        ->orWhere(function ($subYear) use ($year, $month, $selectedMonths) {
                            $subYear->whereNull('obligation_date')
                                    ->whereNull('disbursement_date')
                                    ->whereYear('created_at', $year);

                            if ($month) {
                                $subYear->whereMonth('created_at', $month);
                            } elseif (!empty($selectedMonths)) {
                                $subYear->whereIn(\DB::raw('MONTH(created_at)'), $selectedMonths);
                            }
                        });
                    })
                    // Include Cancelled transactions so they appear in the transaction list & summary; only exclude Rejected
                    ->where('status', '!=', 'Rejected');

                    // Apply Text Search inside Funds
                    if (!empty($search)) {
                        $query->where(function ($q) use ($search) {
                            $q->where('dtrack_no', 'LIKE', "%{$search}%")
                            ->orWhere('dtrack_no_new', 'LIKE', "%{$search}%")
                            ->orWhere('obligation_serial', 'LIKE', "%{$search}%")
                            ->orWhere('particulars', 'LIKE', "%{$search}%")
                            ->orWhere('creditor', 'LIKE', "%{$search}%")
                            ->orWhere('remarks', 'LIKE', "%{$search}%")
                            ->orWhere('manual_remarks', 'LIKE', "%{$search}%");
                        });
                    }
                },
                'funds.cosContract',
                'funds.creditors',
                'funds.cosSalaryDisbursements',
                'funds.norsaAdjustments',
                'funds.cancellations',
                'funds.multiActivities',
            ]);

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

        // --- RESOLVE EMPLOYEE CREDITORS FROM employee_fund + db_common ---
        $allFundIds = $sources->pluck('funds')->flatten()->pluck('id')->unique()->toArray();
        $fundEmployeeMap = [];

        if (!empty($allFundIds)) {
            $empFundLinks = \DB::table('employee_fund')
                ->whereIn('fund_id', $allFundIds)
                ->get()
                ->groupBy('fund_id');

            $allDbedids = $empFundLinks->flatten()->pluck('user_id')->unique()->filter()->toArray();

            if (!empty($allDbedids)) {
                $key = config('app.db_common_key') ?? env('DB_COMMON_ENCRYPTION_KEY');

                $employees = \DB::connection('db_common')->table('tbl_employee')
                    ->join('tbl_emp_details', 'tbl_employee.empid', '=', 'tbl_emp_details.empid')
                    ->whereIn('tbl_emp_details.dbedid', $allDbedids)
                    ->select(
                        'tbl_emp_details.dbedid',
                        \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.fname, '{$key}') AS CHAR)) as fname"),
                        \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.mname, '{$key}') AS CHAR)) as mname"),
                        \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.lname, '{$key}') AS CHAR)) as lname"),
                        \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.suffix, '{$key}') AS CHAR)) as suffix")
                    )
                    ->get()
                    ->mapWithKeys(function ($emp) {
                        $clean  = fn($str) => $str ? mb_convert_encoding(trim($str), 'UTF-8', 'UTF-8') : '';
                        $fname  = $clean($emp->fname);
                        $mname  = $clean($emp->mname);
                        $lname  = $clean($emp->lname);
                        $suffix = $clean($emp->suffix);

                        $middleInitial = $mname ? ' ' . substr($mname, 0, 1) . '.' : '';
                        $suffixStr     = $suffix ? ' ' . $suffix : '';

                        return [$emp->dbedid => "{$lname}, {$fname}{$middleInitial}{$suffixStr}"];
                    });

                foreach ($empFundLinks as $fId => $links) {
                    $names = $links->pluck('user_id')
                        ->map(fn($dbedid) => $employees->get($dbedid))
                        ->filter()
                        ->unique()
                        ->values()
                        ->toArray();

                    if (!empty($names)) {
                        $fundEmployeeMap[$fId] = implode('; ', $names);
                    }
                }
            }
        }

        // Period matcher helper
        $matchesPeriod = function ($dateVal) use ($year, $month, $selectedMonths) {
            if (empty($dateVal)) return true;
            $d = \Carbon\Carbon::parse($dateVal);
            if ($d->year != $year) return false;
            if ($month && $d->month != $month) return false;
            if (!empty($selectedMonths) && !in_array($d->month, $selectedMonths)) return false;
            return true;
        };

        // 6. Group and Map Data: Section -> Source -> Activity -> Transactions
        $groupedReport = $sources->groupBy('section_id')->map(function ($sectionSources, $sectionId) use ($sectionNames, $search, $fundEmployeeMap, $matchesPeriod) {
            
            $sourcesData = $sectionSources->map(function ($source) use ($search, $fundEmployeeMap, $matchesPeriod) {
                
                // --- BUILD VIRTUAL TRANSACTIONS COLLECTION ---
                $allTransactions = collect();

                foreach ($source->funds as $fund) {
                    // Resolve Creditor: prioritize funds.creditor, fallback to decrypted employee_fund names
                    $resolvedCreditor = !empty(trim((string) $fund->creditor))
                        ? $fund->creditor
                        : ($fundEmployeeMap[$fund->id] ?? null);

                    $isParentCancelled = strtolower((string) $fund->status) === 'cancelled';

                    if ($fund->is_multi_activity && $fund->multiActivities && $fund->multiActivities->count() > 0) {
                        $totalRoutedAll = (float) $fund->multiActivities->sum('routed_amount');

                        // Split parent fund into its multi-activity child items
                        foreach ($fund->multiActivities as $multi) {
                            $item = clone $fund;
                            $item->creditor            = $resolvedCreditor;
                            $item->transaction_type_id = $multi->transaction_type_id;
                            $item->obligation_amount   = (float) $multi->obligation_amount;
                            $item->disbursement_amount = (float) $multi->disbursement_amount;
                            $item->amount              = (float) ($multi->routed_amount > 0 ? $multi->routed_amount : $fund->amount);
                            $item->is_multi_activity   = true;
                            $item->share_ratio         = $totalRoutedAll > 0 ? ((float) $multi->routed_amount / $totalRoutedAll) : 0;

                            if ($isParentCancelled || strtolower((string) ($multi->status ?? '')) === 'cancelled') {
                                $item->status = 'Cancelled';
                            }

                            if (!empty($multi->particulars)) {
                                $item->particulars = $multi->particulars;
                            }
                            $allTransactions->push($item);
                        }
                    } else {
                        // Standard single-activity fund
                        $item = clone $fund;
                        $item->creditor          = $resolvedCreditor;
                        $item->amount            = (float) $fund->amount;
                        $item->is_multi_activity = false;
                        $item->share_ratio       = 1.0;
                        $allTransactions->push($item);
                    }
                }

                // Group virtual transactions by target activity ID
                $fundsByActivity = $allTransactions->groupBy('transaction_type_id');

                $activities = $source->activities->map(function ($activity) use ($fundsByActivity, $matchesPeriod) {
                    $activityFunds = $fundsByActivity->get($activity->id, collect());

                    // Map individual transaction attributes (Cancellations folded into NORSA)
                    $mappedTransactions = $activityFunds->map(function ($f) use ($activity, $matchesPeriod) {
                        $txNorsa = 0.00;
                        if ($f->norsaAdjustments && $f->norsaAdjustments->count() > 0) {
                            $txNorsa = (float) $f->norsaAdjustments->filter(function ($adj) use ($activity, $matchesPeriod) {
                                if ($adj->transaction_type_id && $adj->transaction_type_id !== $activity->id) {
                                    return false;
                                }
                                return $matchesPeriod($adj->entry_date);
                            })->sum(fn($adj) => (float) ($adj->norsa_amount ?? $adj->amount ?? 0));
                        }

                        $storedOb    = (float) $f->obligation_amount;
                        $baseGrossOb = !empty($f->is_multi_activity) ? ($storedOb + $txNorsa) : $storedOb;

                        // Explicit Cancelled status OR 100% NORSA reversal (Gross Obligated == NORSA)
                        $isFullNorsaCancel = ($baseGrossOb > 0 && abs($baseGrossOb - $txNorsa) < 0.01);
                        $isCancelled       = strtolower((string) $f->status) === 'cancelled' || $isFullNorsaCancel;

                        // Resolve COS Salary consolidation & completion status
                        $cosMetrics = $this->resolveCosSalaryMetrics($f);

                        if ($isCancelled) {
                            $f->status = 'Cancelled';
                        } elseif ($cosMetrics['is_cos_salary']) {
                            $f->status = $cosMetrics['effective_status'];
                        }

                        // Fold any explicit cancellation record into NORSA without double-counting
                        $txCancelled = 0.00;
                        if ($f->cancellations && $f->cancellations->count() > 0) {
                            $recordCancelTotal = (float) $f->cancellations->filter(fn($c) => $matchesPeriod($c->cancellation_date))
                                ->sum('cancelled_amount');
                            $txCancelled = !empty($f->is_multi_activity)
                                ? round($recordCancelTotal * ($f->share_ratio ?? 0), 2)
                                : $recordCancelTotal;
                        } elseif ($isFullNorsaCancel) {
                            $txCancelled = $baseGrossOb;
                        } elseif ($isCancelled) {
                            $refDate = $f->status_date ?? $f->obligation_date ?? $f->created_at;
                            if ($matchesPeriod($refDate)) {
                                $txCancelled = $storedOb > 0 ? $storedOb : (float) ($f->amount ?? 0);
                            }
                        }

                        $effectiveNorsa = $txNorsa + max(0.00, $txCancelled - $txNorsa);

                        $priorGrossOb = $isCancelled
                            ? ($baseGrossOb > 0 ? $baseGrossOb : ($effectiveNorsa > 0 ? $effectiveNorsa : (float) ($f->amount ?? 0)))
                            : $baseGrossOb;

                        $f->is_cancelled          = $isCancelled;
                        $f->is_norsa_cancelled    = $isFullNorsaCancel;
                        $f->is_cos_salary         = $cosMetrics['is_cos_salary'];
                        $f->is_cos_consolidated   = $cosMetrics['is_consolidated'];
                        $f->cos_savings_amount    = $isCancelled ? 0.00 : $cosMetrics['savings'];
                        $f->norsa_amount          = $effectiveNorsa;
                        $f->prior_gross_obligated = $priorGrossOb;
                        $f->cancellation_remarks  = ($f->cancellations && $f->cancellations->count() > 0)
                            ? $f->cancellations->pluck('reason')->filter()->implode('; ')
                            : ($isFullNorsaCancel
                                ? ('Cancelled via Full NORSA' . (!empty($f->remarks) ? ' — ' . $f->remarks : ''))
                                : ($f->remarks ?? $f->manual_remarks ?? null));

                        return $f;
                    });

                    // Obligations
                    $obligatedFunds = $mappedTransactions->filter(function ($f) use ($matchesPeriod) {
                        if (empty($f->obligation_date)) return false;
                        return $matchesPeriod($f->obligation_date);
                    });

                    $norsaAmount = (float) $mappedTransactions->sum('norsa_amount');

                    $grossObligated = 0.00;
                    foreach ($obligatedFunds as $f) {
                        if ($f->is_cancelled) {
                            $grossObligated += (float) $f->prior_gross_obligated;
                        } elseif (!empty($f->is_multi_activity)) {
                            // Multi-activity obligation_amount is stored NET of NORSA; add back NORSA once for Gross
                            $grossObligated += ((float) $f->obligation_amount + (float) $f->norsa_amount);
                        } else {
                            $grossObligated += (float) $f->obligation_amount;
                        }
                    }

                    $netObligated = max(0.00, $grossObligated - $norsaAmount);

                    // Disbursements (Exclude Cancelled transactions)
                    $disbursed = (float) $mappedTransactions->filter(fn($f) => !$f->is_cancelled)
                        ->sum(function ($f) use ($matchesPeriod) {
                            if (!empty($f->is_cos_salary) && $f->cosSalaryDisbursements && $f->cosSalaryDisbursements->count() > 0) {
                                return (float) $f->cosSalaryDisbursements->filter(function ($cosDisb) use ($matchesPeriod) {
                                    if (empty($cosDisb->disbursement_date)) return false;
                                    return $matchesPeriod($cosDisb->disbursement_date);
                                })->sum('amount');
                            }

                            if (empty($f->disbursement_date) || !$matchesPeriod($f->disbursement_date)) return 0.00;

                            return (float) $f->disbursement_amount;
                        });

                    $pending = (float) $mappedTransactions->filter(fn($f) => 
                        empty($f->obligation_date) && empty($f->disbursement_date) && !$f->is_cancelled
                    )->sum('amount');

                    // COS Savings (Exclude Cancelled transactions; supports consolidated COS contracts)
                    $cosSalaryFunds    = $mappedTransactions->filter(fn($f) => !$f->is_cancelled && !empty($f->is_cos_salary));
                    $hasCosSalaryFunds = $cosSalaryFunds->count() > 0;
                    $savings           = $hasCosSalaryFunds ? (float) $cosSalaryFunds->sum('cos_savings_amount') : 0.00;

                    $activityBudgetGross = (float) ($activity->budget_adjusted ?? $activity->budget);
                    $pooled              = (float) ($activity->pooled_amount ?? 0);
                    $activityBudgetNet   = $activityBudgetGross - $pooled;
                    $untouched           = $activityBudgetNet - ($netObligated + $pending);

                    return [
                        'details'         => $activity,
                        'net_budget'      => $activityBudgetNet,
                        'gross_budget'    => $activityBudgetGross,
                        'pooled_amount'   => $pooled,
                        'is_pooled'       => $pooled > 0 || !empty($activity->is_pooled),
                        'pooled_remarks'  => $activity->pooled_remarks ?? $activity->pooled_reason ?? $activity->remarks ?? 'Budget pooled by management',
                        'gross_obligated' => $grossObligated,
                        'norsa_amount'    => $norsaAmount,
                        'obligated'       => $netObligated,
                        'disbursed'       => $disbursed,
                        'pending'         => $pending,
                        'savings'         => $savings,
                        'has_cos_salary'  => $hasCosSalaryFunds,
                        'untouched'       => $untouched > 0 ? $untouched : 0,
                        'transactions'    => $mappedTransactions->values(),
                    ];
                });

                if (!empty($search)) {
                    $activities = $activities->filter(function ($act) use ($search) {
                        return $act['transactions']->isNotEmpty() || 
                            \Illuminate\Support\Str::contains(strtolower($act['details']->name), strtolower($search));
                    });
                }

                return [
                    'source_name'  => $source->name,
                    'source_total' => $source->activities->sum(fn($a) => ($a->budget_adjusted ?? $a->budget) - ($a->pooled_amount ?? 0)),
                    'activities'   => $activities,
                ];
            })->filter(fn($source) => $source['activities']->isNotEmpty());

            return [
                'section_name' => $sectionNames[$sectionId] ?? 'Unknown / Unassigned Section',
                'sources'      => $sourcesData,
            ];
        })->filter(fn($section) => $section['sources']->isNotEmpty());

        if ($request->has('export') && $request->export === 'excel') {
            $fileName = 'WFP_ByTransaction_Report_' . $year . '_' . now()->format('Ymd_His') . '.xlsx';
            return Excel::download(new ActivityTransactionReportExport($groupedReport), $fileName);
        }

        return view('admin.reports.by_transactions', [
            'groupedReport'    => $groupedReport,
            'year'             => $year,
            'month'            => $month,
            'selectedQuarters' => $selectedQuarters,
            'selectedSection'  => $sectionFilter,
            'search'           => $search,
            'sectionNames'     => $sectionNames,
            'canFilter'        => $isAdminOrBudget || $isDivision,
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

    /**
     * Determine if a transaction/item is effectively cancelled, either explicitly by status
     * or because 100% of its gross obligation was reversed via NORSA.
     */
    private function isTransactionCancelled($status, float $grossObligated, float $norsaAmount): bool
    {
        if (strtolower((string) $status) === 'cancelled') {
            return true;
        }

        return $grossObligated > 0 && abs($grossObligated - $norsaAmount) < 0.01;
    }

    /**
     * Determine if a COS Salary fund has completed all contract months (including consolidated entries)
     * and return its effective status, savings, and consolidation metadata.
     */
    private function resolveCosSalaryMetrics($fund): array
    {
        $isCosSalary = ($fund->remarks_salary === 'Imported HR COS Salary/Wages'
            || $fund->cos_contract_id !== null
            || in_array($fund->status, ['Disbursed (with savings)']));

        if (!$isCosSalary) {
            return [
                'is_cos_salary'      => false,
                'is_fully_disbursed' => false,
                'is_consolidated'    => false,
                'effective_status'   => $fund->status,
                'savings'            => 0.00,
            ];
        }

        $disbursements       = $fund->cosSalaryDisbursements ?? collect();
        $totalContractMonths = (int) ($fund->cosContract->total_months ?? 0);

        $totalDisbursedAllTime = $disbursements->count() > 0
            ? (float) $disbursements->sum('amount')
            : (float) $fund->disbursement_amount;

        $storedOb = (float) $fund->obligation_amount;
        $norsaAmt = (float) ($fund->norsaAdjustments ? $fund->norsaAdjustments->sum(fn($a) => (float) ($a->amount ?? 0)) : 0);
        $diff     = round($storedOb - $totalDisbursedAllTime, 2);

        $expectedMonthlyRate = ($totalContractMonths > 0 && $storedOb > 0) ? ($storedOb / $totalContractMonths) : 0;
        $hasLargeConsolidatedPayout = $expectedMonthlyRate > 0 && $disbursements->contains(fn($d) => (float) $d->amount >= ($expectedMonthlyRate * 1.4));

        $isFullyDisbursed = in_array($fund->status, ['Completed', 'Disbursed (with savings)'])
            || ($fund->cosContract && $fund->cosContract->status === 'Completed')
            || ($totalContractMonths > 0 && (int) ($fund->disbursed_months ?? 0) >= $totalContractMonths)
            || ($totalDisbursedAllTime > 0 && $norsaAmt > 0 && abs($diff - $norsaAmt) < 0.01)
            || ($totalContractMonths > 0 && $hasLargeConsolidatedPayout && ($disbursements->count() + 1) >= $totalContractMonths);

        $isConsolidated = $hasLargeConsolidatedPayout
            || ($isFullyDisbursed && $totalContractMonths > 0 && $disbursements->count() > 0 && $disbursements->count() < $totalContractMonths);

        $savings = ($isFullyDisbursed && $totalDisbursedAllTime > 0 && $diff > 0) ? $diff : 0.00;

        $effectiveStatus = $fund->status;
        if ($isFullyDisbursed) {
            $effectiveStatus = $savings > 0 ? 'Disbursed (with savings)' : 'Completed';
        }

        return [
            'is_cos_salary'      => true,
            'is_fully_disbursed' => $isFullyDisbursed,
            'is_consolidated'    => $isConsolidated,
            'effective_status'   => $effectiveStatus,
            'savings'            => $savings,
        ];
    }

    public function uacsReport(Request $request)
    {
        set_time_limit(120);

        $year           = $request->get('year', date('Y'));
        $month          = $request->get('month');
        $allotmentClass = $request->get('allotment_class');
        $selectedSec    = $request->get('section');
        $selectedSource = $request->get('fund_source');
        $search         = trim((string) $request->get('search', ''));

        // 1. Cumulative Multi-Quarter Handling
        $quartersInput    = $request->get('quarters', $request->get('quarter') ? [$request->get('quarter')] : []);
        $selectedQuarters = is_array($quartersInput) ? array_map('intval', $quartersInput) : [(int) $quartersInput];
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

        // 2. Check Gates & Section Names from db_common
        $isAdminOrBudget = \Illuminate\Support\Facades\Gate::allows('budget-section');
        $isDivision      = \Illuminate\Support\Facades\Gate::allows('division-access');
        $user            = auth()->user();

        $sectionNames = \DB::connection('db_common')
            ->table('tbl_section')
            ->pluck('secname', 'secid');

        $uacsCodes = \DB::table('uacs_codes')
            ->where('is_active', 1)
            ->get()
            ->keyBy('id');

        // 3. Query SourceOfFund with Exact Same Relationships & Date Filters as byTransactions()
        $query = \App\Models\SourceOfFund::where('fiscal_year', $year)
            ->with([
                'activities',
                'funds' => function ($q) use ($year, $month, $selectedMonths) {
                    $q->where(function ($sub) use ($year, $month, $selectedMonths) {
                        $sub->where(function ($obligQ) use ($year, $month, $selectedMonths) {
                            $obligQ->whereYear('obligation_date', $year);
                            if ($month) {
                                $obligQ->whereMonth('obligation_date', $month);
                            } elseif (!empty($selectedMonths)) {
                                $obligQ->whereIn(\DB::raw('MONTH(obligation_date)'), $selectedMonths);
                            }
                        })
                        ->orWhere(function ($disbQ) use ($year, $month, $selectedMonths) {
                            $disbQ->whereYear('disbursement_date', $year);
                            if ($month) {
                                $disbQ->whereMonth('disbursement_date', $month);
                            } elseif (!empty($selectedMonths)) {
                                $disbQ->whereIn(\DB::raw('MONTH(disbursement_date)'), $selectedMonths);
                            }
                        })
                        ->orWhereHas('cosSalaryDisbursements', function ($cosQ) use ($year, $month, $selectedMonths) {
                            $cosQ->whereYear('disbursement_date', $year);
                            if ($month) {
                                $cosQ->whereMonth('disbursement_date', $month);
                            } elseif (!empty($selectedMonths)) {
                                $cosQ->whereIn(\DB::raw('MONTH(disbursement_date)'), $selectedMonths);
                            }
                        })
                        ->orWhere(function ($cancelQ) use ($year, $month, $selectedMonths) {
                            $cancelQ->where('status', 'Cancelled')
                                    ->whereYear('status_date', $year);
                            if ($month) {
                                $cancelQ->whereMonth('status_date', $month);
                            } elseif (!empty($selectedMonths)) {
                                $cancelQ->whereIn(\DB::raw('MONTH(status_date)'), $selectedMonths);
                            }
                        })
                        ->orWhere(function ($subYear) use ($year, $month, $selectedMonths) {
                            $subYear->whereNull('obligation_date')
                                    ->whereNull('disbursement_date')
                                    ->whereYear('created_at', $year);

                            if ($month) {
                                $subYear->whereMonth('created_at', $month);
                            } elseif (!empty($selectedMonths)) {
                                $subYear->whereIn(\DB::raw('MONTH(created_at)'), $selectedMonths);
                            }
                        });
                    })
                    ->where('status', '!=', 'Rejected');
                },
                'funds.cosContract',
                'funds.creditors',
                'funds.cosSalaryDisbursements',
                'funds.norsaAdjustments',
                'funds.cancellations',
                'funds.multiActivities',
            ]);

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

        // 4. Resolve Decrypted Employee Creditors from employee_fund + db_common
        $allFundIds      = $sources->pluck('funds')->flatten()->pluck('id')->unique()->toArray();
        $fundEmployeeMap = [];

        if (!empty($allFundIds)) {
            $empFundLinks = \DB::table('employee_fund')
                ->whereIn('fund_id', $allFundIds)
                ->get()
                ->groupBy('fund_id');

            $allDbedids = $empFundLinks->flatten()->pluck('user_id')->unique()->filter()->toArray();

            if (!empty($allDbedids)) {
                $key = config('app.db_common_key') ?? env('DB_COMMON_ENCRYPTION_KEY');

                $employees = \DB::connection('db_common')->table('tbl_employee')
                    ->join('tbl_emp_details', 'tbl_employee.empid', '=', 'tbl_emp_details.empid')
                    ->whereIn('tbl_emp_details.dbedid', $allDbedids)
                    ->select(
                        'tbl_emp_details.dbedid',
                        \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.fname, '{$key}') AS CHAR)) as fname"),
                        \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.mname, '{$key}') AS CHAR)) as mname"),
                        \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.lname, '{$key}') AS CHAR)) as lname"),
                        \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.suffix, '{$key}') AS CHAR)) as suffix")
                    )
                    ->get()
                    ->mapWithKeys(function ($emp) {
                        $clean  = fn($str) => $str ? mb_convert_encoding(trim($str), 'UTF-8', 'UTF-8') : '';
                        $fname  = $clean($emp->fname);
                        $mname  = $clean($emp->mname);
                        $lname  = $clean($emp->lname);
                        $suffix = $clean($emp->suffix);

                        $middleInitial = $mname ? ' ' . substr($mname, 0, 1) . '.' : '';
                        $suffixStr     = $suffix ? ' ' . $suffix : '';

                        return [$emp->dbedid => "{$lname}, {$fname}{$middleInitial}{$suffixStr}"];
                    });

                foreach ($empFundLinks as $fId => $links) {
                    $names = $links->pluck('user_id')
                        ->map(fn($dbedid) => $employees->get($dbedid))
                        ->filter()
                        ->unique()
                        ->values()
                        ->toArray();

                    if (!empty($names)) {
                        $fundEmployeeMap[$fId] = implode('; ', $names);
                    }
                }
            }
        }

        // Period matcher helper
        $matchesPeriod = function ($dateVal) use ($year, $month, $selectedMonths) {
            if (empty($dateVal)) return true;
            $d = \Carbon\Carbon::parse($dateVal);
            if ($d->year != $year) return false;
            if ($month && $d->month != $month) return false;
            if (!empty($selectedMonths) && !in_array($d->month, $selectedMonths)) return false;
            return true;
        };

        // 5. Build Nested Hierarchy: Section -> Fund Source -> UACS Code -> Activity -> Transactions
        $tree = [];

        foreach ($sources as $sof) {
            $secId   = $sof->section_id;
            $secName = $sectionNames[$secId] ?? 'Unknown / Unassigned Section';
            $srcName = $sof->name ?? ('Fund Source #' . $sof->id);

            if (!isset($tree[$secName])) {
                $tree[$secName] = [
                    'section_id'   => $secId,
                    'section_name' => $secName,
                    'sources'      => [],
                ];
            }

            if (!isset($tree[$secName]['sources'][$sof->id])) {
                $tree[$secName]['sources'][$sof->id] = [
                    'source_id'    => $sof->id,
                    'source_name'  => $srcName,
                    'source_total' => 0,
                    'uacs_items'   => [],
                ];
            }

            // --- A. Build Virtual Transactions Collection (Identical to byTransactions) ---
            $allTransactions = collect();

            foreach ($sof->funds as $fund) {
                $resolvedCreditor = !empty(trim((string) $fund->creditor))
                    ? $fund->creditor
                    : ($fundEmployeeMap[$fund->id] ?? null);

                $isParentCancelled = strtolower((string) $fund->status) === 'cancelled';

                if ($fund->is_multi_activity && $fund->multiActivities && $fund->multiActivities->count() > 0) {
                    $totalRoutedAll = (float) $fund->multiActivities->sum('routed_amount');

                    foreach ($fund->multiActivities as $multi) {
                        $item                      = clone $fund;
                        $item->creditor            = $resolvedCreditor;
                        $item->transaction_type_id = $multi->transaction_type_id;
                        $item->obligation_amount   = (float) $multi->obligation_amount;
                        $item->disbursement_amount = (float) $multi->disbursement_amount;
                        $item->amount              = (float) ($multi->routed_amount > 0 ? $multi->routed_amount : $fund->amount);
                        $item->routed_amount       = $item->amount;
                        $item->is_multi_activity   = true;
                        $item->share_ratio         = $totalRoutedAll > 0 ? ((float) $multi->routed_amount / $totalRoutedAll) : 0;

                        if ($isParentCancelled || strtolower((string) ($multi->status ?? '')) === 'cancelled') {
                            $item->status = 'Cancelled';
                        }

                        if (!empty($multi->particulars)) {
                            $item->particulars = $multi->particulars;
                        }
                        $allTransactions->push($item);
                    }
                } else {
                    $item                    = clone $fund;
                    $item->creditor          = $resolvedCreditor;
                    $item->amount            = (float) $fund->amount;
                    $item->routed_amount     = (float) $fund->amount;
                    $item->is_multi_activity = false;
                    $item->share_ratio       = 1.0;
                    $allTransactions->push($item);
                }
            }

            $fundsByActivity = $allTransactions->groupBy('transaction_type_id');

            // --- B. Process Each Activity on SourceOfFund & Group into its UACS Code ---
            foreach ($sof->activities as $activity) {
                $uacs = $uacsCodes[$activity->uacs_code_id] ?? null;
                if (!$uacs) {
                    continue;
                }
                if ($allotmentClass && $uacs->allotment_class !== $allotmentClass) {
                    continue;
                }

                $activityFunds = $fundsByActivity->get($activity->id, collect());

                $mappedTransactions = $activityFunds->map(function ($f) use ($activity, $matchesPeriod) {
                    $txNorsa = 0.00;
                    if ($f->norsaAdjustments && $f->norsaAdjustments->count() > 0) {
                        $txNorsa = (float) $f->norsaAdjustments->filter(function ($adj) use ($activity, $matchesPeriod) {
                            if ($adj->transaction_type_id && $adj->transaction_type_id !== $activity->id) {
                                return false;
                            }
                            return $matchesPeriod($adj->entry_date);
                        })->sum(fn($adj) => (float) ($adj->norsa_amount ?? $adj->amount ?? 0));
                    }

                    $storedOb          = (float) $f->obligation_amount;
                    $baseGrossOb       = !empty($f->is_multi_activity) ? ($storedOb + $txNorsa) : $storedOb;
                    $isFullNorsaCancel = ($baseGrossOb > 0 && abs($baseGrossOb - $txNorsa) < 0.01);
                    $isCancelled       = strtolower((string) $f->status) === 'cancelled' || $isFullNorsaCancel;

                    $cosMetrics = $this->resolveCosSalaryMetrics($f);

                    if ($isCancelled) {
                        $f->status = 'Cancelled';
                    } elseif ($cosMetrics['is_cos_salary']) {
                        $f->status = $cosMetrics['effective_status'];
                    }

                    $txCancelled = 0.00;
                    if ($f->cancellations && $f->cancellations->count() > 0) {
                        $recordCancelTotal = (float) $f->cancellations->filter(fn($c) => $matchesPeriod($c->cancellation_date))
                            ->sum('cancelled_amount');
                        $txCancelled = !empty($f->is_multi_activity)
                            ? round($recordCancelTotal * ($f->share_ratio ?? 0), 2)
                            : $recordCancelTotal;
                    } elseif ($isFullNorsaCancel) {
                        $txCancelled = $baseGrossOb;
                    } elseif ($isCancelled) {
                        $refDate = $f->status_date ?? $f->obligation_date ?? $f->created_at;
                        if ($matchesPeriod($refDate)) {
                            $txCancelled = $storedOb > 0 ? $storedOb : (float) ($f->amount ?? 0);
                        }
                    }

                    $effectiveNorsa = $txNorsa + max(0.00, $txCancelled - $txNorsa);
                    $priorGrossOb   = $isCancelled
                        ? ($baseGrossOb > 0 ? $baseGrossOb : ($effectiveNorsa > 0 ? $effectiveNorsa : (float) ($f->amount ?? 0)))
                        : $baseGrossOb;

                    $f->activity_name         = $activity->name;
                    $f->is_cancelled          = $isCancelled;
                    $f->is_norsa_cancelled    = $isFullNorsaCancel;
                    $f->is_cos_salary         = $cosMetrics['is_cos_salary'];
                    $f->is_cos_consolidated   = $cosMetrics['is_consolidated'];
                    $f->cos_savings_amount    = $isCancelled ? 0.00 : $cosMetrics['savings'];
                    $f->norsa_amount          = $effectiveNorsa;
                    $f->prior_gross_obligated = $priorGrossOb;
                    $f->cancellation_remarks  = ($f->cancellations && $f->cancellations->count() > 0)
                        ? $f->cancellations->pluck('reason')->filter()->implode('; ')
                        : ($isFullNorsaCancel
                            ? ('Cancelled via Full NORSA' . (!empty($f->remarks) ? ' — ' . $f->remarks : ''))
                            : ($f->remarks ?? $f->manual_remarks ?? null));

                    return $f;
                });

                // Calculate Activity Totals
                $obligatedFunds = $mappedTransactions->filter(fn($f) => !empty($f->obligation_date) && $matchesPeriod($f->obligation_date));
                $norsaAmount    = (float) $mappedTransactions->sum('norsa_amount');

                $grossObligated = 0.00;
                foreach ($obligatedFunds as $f) {
                    if ($f->is_cancelled) {
                        $grossObligated += (float) $f->prior_gross_obligated;
                    } elseif (!empty($f->is_multi_activity)) {
                        $grossObligated += ((float) $f->obligation_amount + (float) $f->norsa_amount);
                    } else {
                        $grossObligated += (float) $f->obligation_amount;
                    }
                }

                $netObligated = max(0.00, $grossObligated - $norsaAmount);

                $disbursed = (float) $mappedTransactions->filter(fn($f) => !$f->is_cancelled)
                    ->sum(function ($f) use ($matchesPeriod) {
                        if (!empty($f->is_cos_salary) && $f->cosSalaryDisbursements && $f->cosSalaryDisbursements->count() > 0) {
                            return (float) $f->cosSalaryDisbursements->filter(
                                fn($cosDisb) => !empty($cosDisb->disbursement_date) && $matchesPeriod($cosDisb->disbursement_date)
                            )->sum('amount');
                        }
                        if (empty($f->disbursement_date) || !$matchesPeriod($f->disbursement_date)) return 0.00;
                        return (float) $f->disbursement_amount;
                    });

                $pending = (float) $mappedTransactions->filter(
                    fn($f) => empty($f->obligation_date) && empty($f->disbursement_date) && !$f->is_cancelled
                )->sum('amount');

                $activityBudgetGross = (float) ($activity->budget_adjusted ?? $activity->budget);
                $pooled              = (float) ($activity->pooled_amount ?? 0);
                $activityBudgetNet   = $activityBudgetGross - $pooled;
                $unobligated         = max(0.00, round($activityBudgetNet - ($netObligated + $pending), 2));

                // Add into UACS Bucket
                $uKey = $uacs->uacs_code;
                if (!isset($tree[$secName]['sources'][$sof->id]['uacs_items'][$uKey])) {
                    $tree[$secName]['sources'][$sof->id]['uacs_items'][$uKey] = [
                        'uacs_id'         => $uacs->id,
                        'uacs_code'       => $uacs->uacs_code,
                        'account_title'   => $uacs->account_title,
                        'allotment_class' => $uacs->allotment_class,
                        'allotment'       => 0,
                        'pending'         => 0,
                        'gross_obligated' => 0,
                        'norsa_amount'    => 0,
                        'net_obligated'   => 0,
                        'disbursed'       => 0,
                        'unobligated'     => 0,
                        'unpaid'          => 0,
                        'activities'      => collect(),
                        'transactions'    => collect(),
                    ];
                }

                $bucket = &$tree[$secName]['sources'][$sof->id]['uacs_items'][$uKey];

                $bucket['allotment']       += $activityBudgetNet;
                $bucket['pending']         += $pending;
                $bucket['gross_obligated'] += $grossObligated;
                $bucket['norsa_amount']    += $norsaAmount;
                $bucket['net_obligated']   += $netObligated;
                $bucket['disbursed']       += $disbursed;
                $bucket['unobligated']     += $unobligated;

                $bucket['activities']->push([
                    'activity_id'     => $activity->id,
                    'activity_name'   => $activity->name,
                    'allotment'       => $activityBudgetNet,
                    'pending'         => $pending,
                    'gross_obligated' => $grossObligated,
                    'norsa_amount'    => $norsaAmount,
                    'net_obligated'   => $netObligated,
                    'disbursed'       => $disbursed,
                    'unobligated'     => $unobligated,
                    'transactions'    => $mappedTransactions->values(),
                ]);

                foreach ($mappedTransactions as $mTx) {
                    $bucket['transactions']->push($mTx);
                }

                unset($bucket);
            }
        }

        // 6. Finalize Subtotals, Apply Filters & Sort
        $groupedReport = collect($tree)->map(function ($sec) use ($selectedSource, $search) {
            $sources = collect($sec['sources'])->map(function ($src) use ($search) {
                $uacsCollection = collect($src['uacs_items'])->map(function ($u) use ($search) {
                    $u['unpaid'] = max(0, round($u['net_obligated'] - $u['disbursed'], 2));

                    if ($search !== '') {
                        $uacsMatches = stripos($u['uacs_code'], $search) !== false
                            || stripos($u['account_title'], $search) !== false;

                        if (!$uacsMatches) {
                            $u['activities'] = $u['activities']->filter(function ($actItem) use ($search) {
                                return stripos($actItem['activity_name'], $search) !== false
                                    || $actItem['transactions']->contains(function ($tx) use ($search) {
                                        return stripos((string) $tx->dtrack_no, $search) !== false
                                            || stripos((string) $tx->obligation_serial, $search) !== false
                                            || stripos((string) $tx->creditor, $search) !== false
                                            || stripos((string) $tx->particulars, $search) !== false;
                                    });
                            })->values();
                        }
                    }

                    $u['activities'] = $u['activities']->sortBy('activity_name')->values();
                    return $u;
                })->filter(function ($u) use ($search) {
                    if ($search !== '' && $u['activities']->isEmpty()) {
                        return false;
                    }
                    return $u['allotment'] > 0
                        || $u['gross_obligated'] > 0
                        || $u['disbursed'] > 0
                        || $u['pending'] > 0
                        || $u['activities']->isNotEmpty();
                })->sortBy('uacs_code')->values();

                $src['uacs_items']        = $uacsCollection;
                $src['source_total']      = $uacsCollection->sum('allotment');
                $src['total_pending']     = $uacsCollection->sum('pending');
                $src['total_obligated']   = $uacsCollection->sum('gross_obligated');
                $src['total_norsa']       = $uacsCollection->sum('norsa_amount');
                $src['total_net_oblig']   = $uacsCollection->sum('net_obligated');
                $src['total_disbursed']   = $uacsCollection->sum('disbursed');
                $src['total_unobligated'] = $uacsCollection->sum('unobligated');
                return $src;
            })->filter(function ($src) use ($selectedSource) {
                if ($src['uacs_items']->isEmpty()) {
                    return false;
                }
                if ($selectedSource && $src['source_name'] !== $selectedSource) {
                    return false;
                }
                return true;
            })->sortBy('source_name')->values();

            $sec['sources'] = $sources;
            return $sec;
        })->filter(fn ($sec) => $sec['sources']->isNotEmpty())
        ->sortBy('section_name')
        ->values();

        $allotmentClasses = \DB::table('uacs_codes')
            ->whereNotNull('allotment_class')
            ->distinct()
            ->pluck('allotment_class');

        $allFundSources = $groupedReport->flatMap(fn ($s) => $s['sources']->pluck('source_name'))->unique()->sort()->values();

        return view('admin.reports.uacs', compact(
            'groupedReport',
            'year',
            'selectedQuarters',
            'allotmentClass',
            'allotmentClasses',
            'allFundSources',
            'search'
        ));
    }

}