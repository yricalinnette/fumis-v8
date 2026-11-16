<?php

namespace App\Http\Controllers;

use App\Models\SourceOfFund;
use App\Models\Fund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $selectedYear    = (int) $request->get('year', date('Y'));
        $selectedSection = $request->get('section');
        $currentUser     = auth()->user();

        // 1. Check Permissions via Gates
        $isAdminOrBudget = Gate::allows('budget-section');
        $isDivision      = Gate::allows('division-access');

        // 2. Build Base Query for Sources of Fund
        $query = SourceOfFund::where('fiscal_year', $selectedYear);

        // --- DASHBOARD SCOPING LOGIC ---
        if (!$isAdminOrBudget) {
            if ($isDivision) {
                $divisionSecIds = $currentUser->getDivisionSectionIds();
                $query->whereIn('section_id', $divisionSecIds);
            } else {
                $localDetail = DB::table('employee_details')
                    ->where('user_id', $currentUser->id)
                    ->first();

                if ($localDetail) {
                    $userSecId = DB::connection('db_common')
                        ->table('tbl_emp_details')
                        ->where('dbedid', $localDetail->dbedid)
                        ->value('secid');

                    if ($userSecId) {
                        $query->where('section_id', $userSecId);
                    }
                }
            }
        }

        // 3. Get results with Exact Same Eager Loaded Relations as getBudgetBySourceData()
        $sources = $query->with([
            'activities',
            'cancellations',
            'funds' => function ($q) use ($selectedYear) {
                $q->where(function ($sub) use ($selectedYear) {
                    $sub->whereYear('obligation_date', $selectedYear)
                        ->orWhereYear('disbursement_date', $selectedYear)
                        ->orWhereHas('cosSalaryDisbursements', function ($cosQ) use ($selectedYear) {
                            $cosQ->whereYear('disbursement_date', $selectedYear);
                        })
                        ->orWhere(function ($cancelQ) use ($selectedYear) {
                            $cancelQ->where('status', 'Cancelled')
                                    ->whereYear('status_date', $selectedYear);
                        })
                        ->orWhere(function ($subYear) use ($selectedYear) {
                            $subYear->whereNull('obligation_date')
                                    ->whereNull('disbursement_date')
                                    ->whereYear('created_at', $selectedYear);
                        });
                })->where('status', '!=', 'Rejected');
            },
            'funds.cosContract',
            'funds.cosSalaryDisbursements',
            'funds.norsaAdjustments',
            'funds.cancellations',
        ])->get();

        // 4. Fetch Section Names from db_common
        $sectionNames = DB::connection('db_common')
            ->table('tbl_section')
            ->pluck('secname', 'secid');

        $matchesPeriod = function ($dateVal) use ($selectedYear) {
            if (empty($dateVal)) return true;
            return Carbon::parse($dateVal)->year == $selectedYear;
        };

        // 5. Map and Process Data (100% Identical to getBudgetBySourceData)
        $chartData = $sources->map(function ($source) use ($sectionNames, $matchesPeriod) {
            $grossAllotted     = (float) $source->total_amount;
            $totalPooled       = (float) $source->activities->filter(fn($act) => ($act->pooled_amount ?? 0) > 0 || !empty($act->is_pooled))->sum('pooled_amount');
            $adjustedAllotment = max(0.00, round($grossAllotted - $totalPooled, 2));

            $norsaAmount        = 0.00;
            $cancelledFromFunds = 0.00;
            $grossObligated     = 0.00;
            $overlapNorsaCancel = 0.00;
            $cancelledFundIds   = [];

            foreach ($source->funds as $f) {
                // 1. Per-fund NORSA in selected year
                $fundNorsa = 0.00;
                if ($f->norsaAdjustments && $f->norsaAdjustments->count() > 0) {
                    $fundNorsa = (float) $f->norsaAdjustments->filter(fn($adj) => $matchesPeriod($adj->entry_date))
                        ->sum(fn($adj) => (float) ($adj->norsa_amount ?? $adj->amount ?? 0));
                }
                $norsaAmount += $fundNorsa;

                // 2. Per-fund Explicit Cancellation Records in selected year
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

                // 4. Per-fund Cancelled Amount
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

                // 5. Accumulate Gross Obligation if obligated in selected year
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
            $totalDeductions = max(0.00, $norsaAmount + $cancelledAmount - $overlapNorsaCancel);
            $netObligated    = max(0.00, round($grossObligated - $totalDeductions, 2));

            // 6. Disbursements (Exclude Cancelled & Full-NORSA-Cancelled funds)
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

            // 7. Pending (Exclude Cancelled funds)
            $pending = (float) $source->funds->filter(fn($f) =>
                !in_array($f->id, $cancelledFundIds) &&
                (empty($f->obligation_date) || $f->obligation_amount <= 0) &&
                ($f->disbursement_amount <= 0)
            )->sum('amount');

            $processedTotal          = round($netObligated + $pending, 2);
            $unobligatedMinusPending = max(0.00, round($adjustedAllotment - ($netObligated + $pending), 2));

            return [
                'section_id'       => $source->section_id,
                'section_name'     => $sectionNames[$source->section_id] ?? 'General / Unassigned',
                'name'             => $source->name,
                'gross_allotted'   => round($grossAllotted, 2),
                'total_pooled'     => round($totalPooled, 2),
                'total_allotted'   => $adjustedAllotment,
                'gross_obligated'  => round($grossObligated, 2),
                'norsa_total'      => round($totalDeductions, 2),
                'obligated_total'  => $netObligated,
                'pending_total'    => round($pending, 2),
                'disbursed_total'  => round($disbursed, 2),
                'processed_total'  => $processedTotal,
                'remaining_budget' => $unobligatedMinusPending,
                'ob_rate'          => $adjustedAllotment > 0 ? round(($netObligated / $adjustedAllotment) * 100, 1) : 0,
                'disb_rate'        => $netObligated > 0 ? round(($disbursed / $netObligated) * 100, 1) : 0,
                'percent'          => $adjustedAllotment > 0 ? round(($processedTotal / $adjustedAllotment) * 100, 1) : 0,
                'last_updated'     => $source->funds->max('updated_at') ? $source->funds->max('updated_at')->diffForHumans() : 'No activity',
            ];
        });

        // Available sections for filter dropdown
        $availableSections = $chartData
            ->pluck('section_name', 'section_id')
            ->unique()
            ->sort();

        // Filter by selected section if requested
        $filteredChartData = $selectedSection
            ? $chartData->where('section_id', $selectedSection)->values()
            : $chartData;

        // Overall Executive KPI Totals (reflecting active filter)
        $summaryTotals = [
            'gross_allotted'  => $filteredChartData->sum('gross_allotted'),
            'pooled'          => $filteredChartData->sum('total_pooled'),
            'allotted'        => $filteredChartData->sum('total_allotted'),
            'gross_obligated' => $filteredChartData->sum('gross_obligated'),
            'norsa'           => $filteredChartData->sum('norsa_total'),
            'net_obligated'   => $filteredChartData->sum('obligated_total'),
            'pending'         => $filteredChartData->sum('pending_total'),
            'disbursed'       => $filteredChartData->sum('disbursed_total'),
            'remaining'       => $filteredChartData->sum('remaining_budget'),
        ];
        $summaryTotals['ob_rate']   = $summaryTotals['allotted'] > 0
            ? round(($summaryTotals['net_obligated'] / $summaryTotals['allotted']) * 100, 1) : 0;
        $summaryTotals['disb_rate'] = $summaryTotals['net_obligated'] > 0
            ? round(($summaryTotals['disbursed'] / $summaryTotals['net_obligated']) * 100, 1) : 0;

        // 6. Group Data by Section
        $groupedData = $filteredChartData->groupBy('section_id');

        return view('dashboard', compact(
            'groupedData',
            'selectedYear',
            'selectedSection',
            'availableSections',
            'summaryTotals',
            'isAdminOrBudget',
            'isDivision',
            'chartData'
        ));
    }
}