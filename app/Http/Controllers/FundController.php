<?php

namespace App\Http\Controllers;

use App\Models\Fund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\SourceOfFund;
use App\Models\NorsaAdjustment;
use App\Models\Activity;
use Google\Client;            
use Google\Service\Sheets; 
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\DTrackService;

class MyReadFilter implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter
{
    private $startRow = 1;
    private $endRow = 20000;
    private $columns = [];

    public function __construct($startRow, $endRow, $columns) {
        $this->startRow = $startRow;
        $this->endRow = $endRow;
        $this->columns = (array)$columns; // Force to array to prevent the error
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool {
        // Only read if row is in range AND column is in our allowed list
        if ($row >= $this->startRow && $row <= $this->endRow) {
            return in_array($columnAddress, $this->columns);
        }
        return false;
    }
}

class FundController extends Controller
{

    public function index()
    {
        $currentUser = auth()->user();
        $isAdmin = ($currentUser->is_admin || $currentUser->id == 1 || $currentUser->username === 'admin');

        // --- 1. FETCH BASE DATA ---
        if ($isAdmin) {
            $baseQuery = \App\Models\Fund::query();
        } else {
            $localDetail = \DB::table('employee_details')
                ->where('user_id', $currentUser->id)
                ->first();

            $userSecId = null;
            if ($localDetail) {
                $userSecId = \DB::connection('db_common')->table('tbl_emp_details')
                    ->where('dbedid', $localDetail->dbedid)
                    ->value('secid');
            }

            $baseQuery = \App\Models\Fund::where(function($q) use ($currentUser, $userSecId) {
                $q->where('user_id', $currentUser->id);
                if ($userSecId !== null) {
                    $q->orWhere('secid', $userSecId);
                }
            });
        }

        // Eager-load lineItems along with other relations
        $fundsCollection = $baseQuery->with([
            'fundSource', 
            'activity',
            'cosContract', 
            'creditors.employeeDetail',
            'norsaAdjustments',
            'cosSalaryDisbursements',
            'lineItems',
            'procurementTransaction'
        ])
        ->whereNotNull('dtrack_no')
        ->where('dtrack_no', '!=', '')
        ->orderBy('created_at', 'desc')
        ->get();

        // --- 2. EMPLOYEE LOGIC ---
        $key = config('app.db_common_key') ?? env('DB_COMMON_ENCRYPTION_KEY');

        $involvedIds = \DB::table('employee_fund')
            ->whereIn('fund_id', $fundsCollection->pluck('id'))
            ->pluck('user_id')
            ->unique()
            ->toArray();

        $myDetails = DB::connection('db_common')->table('tbl_emp_details')
            ->leftJoin('tbl_section', 'tbl_emp_details.secid', '=', 'tbl_section.secid')
            ->where('tbl_emp_details.empid', $currentUser->empid)
            ->select('tbl_section.secname', 'tbl_emp_details.secid')
            ->orderBy('tbl_emp_details.dbedid', 'desc')
            ->first();

        $latestDetails = DB::connection('db_common')->table('tbl_emp_details')
            ->select('empid', DB::raw('MAX(dbedid) as latest_id'))
            ->groupBy('empid');

        $query = DB::connection('db_common')->table('tbl_employee')
            ->joinSub($latestDetails, 'latest_status', function ($join) {
                $join->on('tbl_employee.empid', '=', 'latest_status.empid');
            })
            ->join('tbl_emp_details', 'latest_status.latest_id', '=', 'tbl_emp_details.dbedid')
            ->leftJoin('tbl_section', 'tbl_emp_details.secid', '=', 'tbl_section.secid')
            ->where('tbl_employee.isactive', 1)
            ->select(
                'tbl_emp_details.dbedid',
                'tbl_section.secname', 
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.fname, '{$key}') AS CHAR)) as fname"),
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.mname, '{$key}') AS CHAR)) as mname"),
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.lname, '{$key}') AS CHAR)) as lname"),
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.suffix, '{$key}') AS CHAR)) as suffix")
            )
            ->orderBy(DB::raw("CAST(AES_DECRYPT(tbl_employee.lname, '{$key}') AS CHAR)"), 'asc')
            ->orderBy(DB::raw("CAST(AES_DECRYPT(tbl_employee.fname, '{$key}') AS CHAR)"), 'asc');

        if (!$currentUser->is_admin) {
            $mySectionId = $currentUser->live_info->secid ?? ($myDetails->secid ?? null);
            
            $query->where(function($q) use ($mySectionId, $involvedIds) {
                $q->where('tbl_emp_details.secid', '=', $mySectionId);
                if (!empty($involvedIds)) {
                    $q->orWhereIn('tbl_emp_details.dbedid', $involvedIds);
                }
            });
        }

        $employees = $query->get()->map(function($emp) {
            $clean = function($str) {
                return $str ? mb_convert_encoding($str, 'UTF-8', 'UTF-8') : '';
            };

            $fname = $clean($emp->fname);
            $mname = $clean($emp->mname);
            $lname = $clean($emp->lname);
            $suffix = $clean($emp->suffix);

            $middleInitial = $mname ? ' ' . substr($mname, 0, 1) . '.' : '';
            $suffixStr = $suffix ? ' ' . $suffix : '';
            
            $emp->fullname = "{$lname}, {$fname}{$middleInitial}{$suffixStr}";
            $emp->fname = $fname;
            $emp->mname = $mname;
            $emp->lname = $lname;
            $emp->suffix = $suffix;

            return $emp;
        })->keyBy('dbedid');

        // --- HELPER: BUILD STAGE-BY-STAGE PROCUREMENT LEAD TIME TRACKER ---
        $buildLeadTimeTracker = function ($routedDate, $cafDate, $poDate, $obligDate, $disbDate, $currentStatus = 'Routed', $forceInProgress = false) {
            $parseDate = function ($val) {
                if (empty($val)) return null;
                try {
                    return \Carbon\Carbon::parse($val)->startOfDay();
                } catch (\Exception $e) {
                    return null;
                }
            };

            $dRouted = $parseDate($routedDate);
            $dCaf    = $parseDate($cafDate);
            $dPo     = $parseDate($poDate);
            $dOblig  = $parseDate($obligDate);
            $dDisb   = $forceInProgress ? null : $parseDate($disbDate);
            $today   = \Carbon\Carbon::today();

            $milestones = [
                ['key' => 'routed', 'label' => 'PR / Routed',   'short' => 'PR/Routed', 'date' => $dRouted, 'icon' => 'fa-paper-plane',   'color' => 'secondary'],
                ['key' => 'caf',    'label' => 'Tagged as CAF', 'short' => 'CAF',       'date' => $dCaf,    'icon' => 'fa-tag',           'color' => 'warning'],
                ['key' => 'po',     'label' => 'Tagged as PO',  'short' => 'PO',        'date' => $dPo,     'icon' => 'fa-file-contract', 'color' => 'info'],
                ['key' => 'oblig',  'label' => 'Obligated',     'short' => 'Obligated', 'date' => $dOblig,  'icon' => 'fa-file-signature','color' => 'primary'],
                ['key' => 'disb',   'label' => 'Disbursed',     'short' => 'Disbursed', 'date' => $dDisb,   'icon' => 'fa-check-circle',  'color' => 'success'],
            ];

            $stages       = [];
            $prevStage    = null;
            $lastReached  = null;
            $firstReached = null;

            foreach ($milestones as $m) {
                $daysFromPrev = null;
                if ($m['date'] !== null) {
                    if ($firstReached === null) {
                        $firstReached = $m;
                    }
                    if ($prevStage !== null && $prevStage['date'] !== null) {
                        $daysFromPrev = max(0, $prevStage['date']->diffInDays($m['date'], false));
                    }
                    $prevStage   = $m;
                    $lastReached = $m;
                }

                $stages[] = [
                    'key'            => $m['key'],
                    'label'          => $m['label'],
                    'short'          => $m['short'],
                    'date'           => $m['date'] ? $m['date']->format('M d, Y') : null,
                    'date_raw'       => $m['date'] ? $m['date']->format('Y-m-d') : null,
                    'days_from_prev' => $daysFromPrev,
                    'completed'      => $m['date'] !== null,
                    'icon'           => $m['icon'],
                    'color'          => $m['color'],
                ];
            }

            $isCancelled = (strcasecmp(trim((string) $currentStatus), 'Cancelled') === 0);
            $isCompleted = !$forceInProgress && (
                ($dDisb !== null) ||
                in_array(strtolower(trim((string) $currentStatus)), ['disbursed', 'disbursed (with savings)', 'completed', 'cancelled'])
            );

            $totalDays   = null;
            $ongoingDays = null;
            $activeLabel = null;

            if ($firstReached !== null && $lastReached !== null) {
                if ($dDisb !== null && !$forceInProgress) {
                    $totalDays = max(0, $firstReached['date']->diffInDays($dDisb, false));
                } elseif (!$isCompleted && !$isCancelled) {
                    $ongoingDays = max(0, $lastReached['date']->diffInDays($today, false));
                    $totalDays   = max(0, $firstReached['date']->diffInDays($today, false));
                    $activeLabel = $lastReached['label'];
                } else {
                    $totalDays = max(0, $firstReached['date']->diffInDays($lastReached['date'], false));
                }
            }

            return [
                'stages'        => $stages,
                'total_days'    => $totalDays,
                'ongoing_days'  => $ongoingDays,
                'active_stage'  => $activeLabel,
                'is_completed'  => ($dDisb !== null && !$forceInProgress) || $isCancelled,
                'has_any_date'  => $firstReached !== null,
            ];
        };

        // --- 3. GROUP AND MAP (WITH MULTI-ACTIVITY & NORSA INTEGRATION) ---
        $funds = $fundsCollection->groupBy(function ($item) {
            if ($item->remarks_salary === 'Imported HR COS Salary/Wages') {
                return 'COS_SALARY_' . $item->id;
            }
            return $item->dtrack_no;
        })->map(function ($group) use ($employees, $buildLeadTimeTracker) {
            $first = $group->first();

            // Creditor resolution
            $tableCreditors = $group->pluck('creditor')->filter()->unique();

            if ($tableCreditors->isNotEmpty()) {
                $mappedCreditors = $tableCreditors->map(function($creditorText) {
                    return (object) ['full_name' => $creditorText];
                });
            } else {
                $dbedids = \DB::table('employee_fund')
                    ->whereIn('fund_id', $group->pluck('id'))
                    ->pluck('user_id')
                    ->unique();

                $mappedCreditors = $dbedids->map(function($id) use ($employees) {
                    $emp = $employees->get($id);
                    return (object) [
                        'full_name' => $emp ? $emp->fullname : "ID: $id (Not found in section)"
                    ];
                });
            }

            // Calculate total amount considering NORSA deductions
            $calculatedTotal = $group->sum(function($item) {
                $isDisbursedType = in_array($item->status, [
                    'Disbursed', 
                    'Disbursed (with savings)', 
                    'Disbursed (Partially)', 
                    'Completed'
                ]);

                if ($isDisbursedType && $item->disbursement_amount > 0) {
                    return $item->disbursement_amount;
                }

                $norsaAmount = $item->norsaAdjustments ? (float)$item->norsaAdjustments->sum('amount') : 0.00;
                $netObligation = max(0.00, (float)$item->obligation_amount - $norsaAmount);

                return ($netObligation > 0) ? $netObligation : $item->amount;
            });

            $disbursedItem = $group->first(function($item) {
                return in_array($item->status, ['Disbursed', 'Disbursed (with savings)', 'Disbursed (Partially)', 'Completed']);
            });

            $hasObligated = $group->contains('status', 'Obligated');

            if ($disbursedItem) {
                $priorityStatus = $disbursedItem->status;
            } elseif ($hasObligated) {
                $priorityStatus = 'Obligated';
            } else {
                $priorityStatus = $first->status;
            }

            $uniqueTypeIds = $group->pluck('transaction_type_id')->filter()->unique();
            $groupTransactionTypeId = ($uniqueTypeIds->count() === 1) ? $uniqueTypeIds->first() : null;

            // Group NORSA aggregations
            $groupTotalNorsa = $group->sum(function($item) {
                return $item->norsaAdjustments ? (float)$item->norsaAdjustments->sum('amount') : 0.00;
            });

            // Collect child line items or distinct activity names across the group
            $groupedActivities = collect();
            
            foreach ($group as $gItem) {
                if ($gItem->lineItems && $gItem->lineItems->isNotEmpty()) {
                    foreach ($gItem->lineItems as $line) {
                        $groupedActivities->push([
                            'name' => $line->creditor ?? $line->particulars ?? 'N/A',
                            'amount' => $line->obligation_amount ?? 0,
                        ]);
                    }
                }
            }

            if ($groupedActivities->isEmpty()) {
                foreach ($group as $gItem) {
                    if ($gItem->activity) {
                        $groupedActivities->push([
                            'name' => $gItem->activity->name,
                            'amount' => $gItem->amount ?? 0,
                        ]);
                    }
                }
            }

            return (object) [
                'id'                  => $first->id,
                'dtrack_no'           => $first->dtrack_no,
                'transaction_date'    => $first->transaction_date,
                'particulars'         => $first->particulars,
                'secid'               => $first->secid,
                'created_at'          => $first->created_at,
                'creditors'           => $mappedCreditors, 
                'total_amount'        => $calculatedTotal,
                'group_status'        => $priorityStatus,
                'transaction_type_id' => $groupTransactionTypeId,
                'remarks_salary'      => $first->remarks_salary,
                'disbursed_months'    => $first->disbursed_months,
                'contract'            => $first->cosContract,
                'cos_disbursements'   => $first->cosSalaryDisbursements,
                'source_names'        => $group->pluck('fundSource.name')->filter()->unique()->implode('<br>'),
                'activity_names'      => $group->pluck('activity.name')->filter()->unique()->implode('<br>'),
                'grouped_activities'  => $groupedActivities->unique('name')->values()->all(),
                'total_norsa'         => $groupTotalNorsa,
                'has_norsa'           => $groupTotalNorsa > 0,
                'is_fully_synced'     => !$group->contains(function ($item) {
                    $isObligatedMissing = ($item->status === 'Obligated' && (empty($item->obligation_amount) || $item->obligation_amount <= 0));
                    $isDisbursedType = in_array($item->status, ['Disbursed', 'Disbursed (with savings)', 'Disbursed (Partially)', 'Completed']);
                    $isDisbursedMissing = ($isDisbursedType && (empty($item->disbursement_amount) || $item->disbursement_amount <= 0));
                    return $isObligatedMissing || $isDisbursedMissing;
                }),
                'breakdown' => $group->map(function($item) use ($buildLeadTimeTracker) {
                    $norsaAmount = $item->norsaAdjustments ? (float)$item->norsaAdjustments->sum('amount') : 0.00;
                    $netObligationAmount = max(0.00, (float)$item->obligation_amount - $norsaAmount);

                    // Fetch all procurement transaction records linked to this fund item
                    $procurements = \DB::table('procurement_transactions')
                        ->where('fund_transaction_id', $item->id)
                        ->get();

                    $routedDate = $item->transaction_date ?? $item->created_at;

                    // Attach lead time tracker to each procurement transaction and its child items
                    $procurements = $procurements->map(function ($pt) use ($item, $routedDate, $buildLeadTimeTracker) {
                        $rawItems = \DB::table('procurement_transaction_items')
                            ->where('procurement_transaction_id', $pt->id)
                            ->get();

                        $ptItems = $rawItems->map(function ($pItem) use ($pt, $routedDate, $buildLeadTimeTracker) {
                            $rawStatus = trim((string) ($pItem->status ?? ''));
                            $pDisbAmt  = (float) ($pItem->disbursement_amount ?? 0);
                            $pObAmt    = (float) ($pItem->obligation_amount ?? 0);
                            $pPoAmt    = (float) ($pItem->po_amount ?? 0);

                            $hasPo   = !empty($pItem->po_number) || $pPoAmt > 0 || !empty($pItem->po_date) || strcasecmp($rawStatus, 'Tagged as PO') === 0;
                            $hasOblig = $pObAmt > 0 || !empty($pItem->obligation_date) || strcasecmp($rawStatus, 'Obligated') === 0;
                            $hasDisb = $pDisbAmt > 0 || !empty($pItem->disbursement_date) || in_array(strtolower($rawStatus), ['disbursed', 'disbursed (with savings)', 'completed']);

                            // Resolve exact item status (never falsely inherit parent Disbursed/Obligated)
                            if (strcasecmp($rawStatus, 'Cancelled') === 0) {
                                $resolvedStatus = 'Cancelled';
                            } elseif ($hasDisb) {
                                $resolvedStatus = ($pObAmt > 0 && $pDisbAmt > 0 && $pDisbAmt < $pObAmt)
                                    ? 'Disbursed (with savings)'
                                    : 'Disbursed';
                            } elseif ($hasOblig) {
                                $resolvedStatus = 'Obligated';
                            } elseif ($hasPo) {
                                $resolvedStatus = 'Tagged as PO';
                            } elseif (!empty($pt->caf_date) || in_array($pt->caf_status ?? '', ['Tagged as CAF', 'Tagged as PO']) || strcasecmp($rawStatus, 'Tagged as CAF') === 0) {
                                $resolvedStatus = 'Tagged as CAF';
                            } else {
                                $resolvedStatus = $rawStatus !== '' ? $rawStatus : 'Routed';
                            }

                            $pItem->resolved_status = $resolvedStatus;

                            // Use ONLY item-specific dates for PO, Obligation, and Disbursement (no parent fund fallback!)
                            $pItem->lead_time = $buildLeadTimeTracker(
                                $routedDate,
                                $pt->caf_date ?? null,
                                $pItem->po_date ?? null,
                                $pItem->obligation_date ?? null,
                                $pItem->disbursement_date ?? null,
                                $resolvedStatus,
                                !$hasDisb && $resolvedStatus !== 'Cancelled'
                            );

                            return $pItem;
                        });

                        $pt->items = $ptItems;

                        // Determine parent procurement transaction completion & summary status across its items
                        $totalChildItems     = $ptItems->count();
                        $disbursedChildCount = $ptItems->filter(fn($i) => in_array($i->resolved_status, ['Disbursed', 'Disbursed (with savings)', 'Completed']))->count();
                        $obligatedChildCount = $ptItems->filter(fn($i) => in_array($i->resolved_status, ['Obligated', 'Disbursed', 'Disbursed (with savings)', 'Completed']))->count();
                        $poChildCount        = $ptItems->filter(fn($i) => in_array($i->resolved_status, ['Tagged as PO', 'Obligated', 'Disbursed', 'Disbursed (with savings)', 'Completed']))->count();

                        if ($totalChildItems > 0) {
                            $allItemsDisbursed = ($disbursedChildCount === $totalChildItems);

                            if ($allItemsDisbursed) {
                                $pt->resolved_status = 'Disbursed';
                            } elseif ($disbursedChildCount > 0) {
                                $pt->resolved_status = "Partially Disbursed ({$disbursedChildCount}/{$totalChildItems} items)";
                            } elseif ($obligatedChildCount === $totalChildItems) {
                                $pt->resolved_status = 'Obligated';
                            } elseif ($obligatedChildCount > 0) {
                                $pt->resolved_status = "Partially Obligated ({$obligatedChildCount}/{$totalChildItems} items)";
                            } elseif ($poChildCount === $totalChildItems) {
                                $pt->resolved_status = 'Tagged as PO';
                            } elseif ($poChildCount > 0) {
                                $pt->resolved_status = "Partially Tagged as PO ({$poChildCount}/{$totalChildItems} items)";
                            } else {
                                $pt->resolved_status = !empty($pt->caf_date) ? 'Tagged as CAF' : ($pt->status ?? 'Routed');
                            }

                            $latestPoDate    = $ptItems->pluck('po_date')->filter()->max() ?? $pt->po_date ?? null;
                            $latestObligDate = $ptItems->pluck('obligation_date')->filter()->max() ?? $pt->obligation_date ?? null;
                            $latestDisbDate  = $allItemsDisbursed
                                ? ($ptItems->pluck('disbursement_date')->filter()->max() ?? $pt->disbursement_date ?? $item->disbursement_date ?? null)
                                : null;

                            $pt->lead_time = $buildLeadTimeTracker(
                                $routedDate,
                                $pt->caf_date ?? null,
                                $latestPoDate,
                                $latestObligDate,
                                $latestDisbDate,
                                $pt->resolved_status,
                                !$allItemsDisbursed
                            );
                        } else {
                            // Single-item procurement transaction without child rows
                            $pt->resolved_status = $pt->status ?? $item->status;
                            $pt->lead_time = $buildLeadTimeTracker(
                                $routedDate,
                                $pt->caf_date ?? null,
                                $pt->po_date ?? null,
                                $pt->obligation_date ?? $item->obligation_date ?? null,
                                $pt->disbursement_date ?? $item->disbursement_date ?? null,
                                $pt->resolved_status
                            );
                        }

                        return $pt;
                    });

                    // Overall item-level procurement lead time summary
                    $firstProc = $procurements->first();
                    $overallLeadTime = $firstProc
                        ? $firstProc->lead_time
                        : $buildLeadTimeTracker(
                            $routedDate,
                            null,
                            null,
                            $item->obligation_date ?? null,
                            $item->disbursement_date ?? null,
                            $item->status
                        );

                    $totalProcurements = $procurements->count();
                    $poCount = $procurements->where('caf_status', 'Tagged as PO')->count();

                    $procurementStatus = null;
                    if ($totalProcurements > 0) {
                        if ($poCount === $totalProcurements) {
                            $procurementStatus = 'Tagged as PO';
                        } elseif ($poCount > 0 && $poCount < $totalProcurements) {
                            $procurementStatus = 'Partially Tagged as PO';
                        } else {
                            $procurementStatus = 'Tagged as CAF';
                        }
                    }

                    return (object) [
                        'id'                    => $item->id,
                        'source_name'           => $item->fundSource->name ?? 'N/A', 
                        'activity_name'         => $item->activity->name ?? 'N/A',
                        'transaction_date'      => $item->transaction_date,
                        'amount'                => $item->amount,
                        'status'                => $item->status,
                        'remarks'               => $item->remarks,
                        'manual_remarks'        => $item->manual_remarks,
                        'remarks_salary'        => $item->remarks_salary,
                        'disbursed_months'      => $item->disbursed_months,
                        'contract'              => $item->cosContract,
                        'cos_disbursements'     => $item->cosSalaryDisbursements,
                        'creditor'              => $item->creditor,
                        'transaction_type_id'   => $item->transaction_type_id,
                        'obligation_serial'     => $item->obligation_serial,
                        'obligation_amount'     => $item->obligation_amount,
                        'norsa_amount'          => $norsaAmount,
                        'has_norsa'             => $norsaAmount > 0 || ($item->norsaAdjustments && $item->norsaAdjustments->isNotEmpty()),
                        'net_obligation_amount' => $netObligationAmount,
                        'obligation_date'       => $item->obligation_date,
                        'disbursement_amount'   => $item->disbursement_amount,
                        'disbursement_date'     => $item->disbursement_date,
                        'status_date'           => $item->status_date,
                        'line_items'            => $item->lineItems,
                        // Procurement tracking & lead time additions:
                        'procurements'          => $procurements,
                        'procurement_status'    => $procurementStatus,
                        'po_numbers'            => $procurements->pluck('po_number')->filter()->unique()->implode(', '),
                        'po_dtrack_nos'         => $procurements->pluck('po_dtrack_no')->filter()->unique()->implode(', '),
                        'lead_time_tracker'     => $overallLeadTime,
                    ];
                }),
            ];
        })->sortByDesc('transaction_date')->values();

        $userSectionName = ($currentUser->is_admin) ? 'All Personnel' : ($myDetails->secname ?? 'Assigned Section');

        $userSecId = null;
        $localDetail = \DB::table('employee_details')
            ->where('user_id', $currentUser->id)
            ->first();

        if ($localDetail && $localDetail->dbedid) {
            $userSecId = \DB::connection('db_common')
                ->table('tbl_emp_details')
                ->where('dbedid', $localDetail->dbedid)
                ->value('secid');
        }

        // Modal Transaction Sources
        $sourcesQuery = \App\Models\SourceOfFund::where('fiscal_year', date('Y'))
            ->with(['activities', 'budgetLineItem'])
            ->orderBy('name');

        if (!$isAdmin) {
            if ($userSecId) {
                $sourcesQuery->where('section_id', $userSecId);
            } else {
                $sourcesQuery->whereRaw('1 = 0');
            }
        }

        $sources = $sourcesQuery->get();

        // Activities
        $activitiesQuery = \App\Models\Activity::with('source')
            ->orderBy('name', 'asc');

        if (!$isAdmin) {
            if ($userSecId) {
                $activitiesQuery->where(function ($q) use ($userSecId) {
                    $q->where('section_id', $userSecId)
                    ->orWhereHas('source', function ($sq) use ($userSecId) {
                        $sq->where('section_id', $userSecId);
                    });
                });
            } else {
                $activitiesQuery->whereRaw('1 = 0');
            }
        }

        $allActivitiesList = $activitiesQuery->get();

        $user = auth()->user();
        $isSectionAdmin = ($user->id == 1 || $user->username === 'admin');

        $syncQuery = \App\Models\Fund::whereNotIn('status', [
            'Disbursed', 
            'Disbursed (with savings)', 
            'Disbursed (Partially)', 
            'Cancelled'
        ]);

        $obrnQuery = \App\Models\Fund::where('status', 'For CAF/Obligation')
                        ->whereNull('obligation_serial');

        if (!$isSectionAdmin) {
            $localDetail = \DB::table('employee_details')
                ->where('user_id', $user->id)
                ->select('dbedid')
                ->first();

            if ($localDetail && $localDetail->dbedid) {
                $userSecId = \DB::connection('db_common')->table('tbl_emp_details')
                    ->where('dbedid', $localDetail->dbedid)
                    ->value('secid');
                
                $syncQuery->where('secid', $userSecId);
                $obrnQuery->where('secid', $userSecId);
            } else {
                $syncQuery->whereRaw('1 = 0'); 
                $obrnQuery->whereRaw('1 = 0');
            }
        }

        $awaitingSyncCount = $syncQuery->count();
        $awaitingOBRN      = $obrnQuery->count();

        $allSections = $isAdmin 
            ? \DB::connection('db_common')
                ->table('tbl_section')
                ->where('isactive', 1)
                ->orderBy('secname', 'asc')
                ->pluck('secname', 'secid')
                ->toArray() 
            : [];

        return view('funds.index', compact(
            'funds', 
            'sources', 
            'allActivitiesList', 
            'employees', 
            'userSectionName', 
            'awaitingSyncCount', 
            'awaitingOBRN', 
            'allSections', 
            'isAdmin'
        ));
    }
        
    public function store(Request $request)
    {
        $yearPrefix = date('Y') . '-';
        
        // Auto-fix dtrack_no format
        if (!str_starts_with($request->dtrack_no, $yearPrefix)) {
            $cleanNumber = preg_replace('/[^0-9]/', '', $request->dtrack_no);
            $suffix = str_replace(date('Y'), '', $cleanNumber);
            $request->merge(['dtrack_no' => $yearPrefix . $suffix]);
        }

        // VALIDATION (creditor_ids validates against db_common.tbl_emp_details.dbedid)
        $validated = $request->validate([
            'dtrack_no'                 => ['required', 'regex:/^\d{4}-\d{6}$/'],
            'transaction_date'          => 'required|date',
            'particulars'               => 'required|string',
            'creditor_ids'              => 'nullable|array',
            'creditor_ids.*'            => 'exists:db_common.tbl_emp_details,dbedid',
            'allocations'               => 'required|array|min:1',
            'allocations.*.source_id'   => 'required|exists:source_of_funds,id',
            'allocations.*.activity_id' => 'required|exists:activities,id',
            'allocations.*.amount'      => 'required|numeric|min:0.01',
        ]);

        // Ensure this DTrack isn't already in the DB
        $exists = \App\Models\Fund::where('dtrack_no', $request->dtrack_no)->exists();
        if ($exists) {
            return response()->json(['message' => 'This DTrack number has already been used.'], 422);
        }

        // --- FETCH SECID LOGIC ---
        $userId = auth()->id();
        $secid = null;

        if (auth()->user()->username === 'admin') {
            $secid = 0; 
        } else {
            $localDetail = \DB::table('employee_details')
                ->where('user_id', $userId)
                ->select('dbedid')
                ->first();

            if ($localDetail && $localDetail->dbedid) {
                $secid = \DB::connection('db_common')->table('tbl_emp_details')
                    ->where('dbedid', $localDetail->dbedid)
                    ->value('secid');
            }
        }

        // Protection for non-admins missing secid
        if (is_null($secid) && auth()->user()->username !== 'admin') {
            return response()->json([
                'success' => false, 
                'message' => 'System Error: Your account is not correctly linked to a DOH Section. Please contact ICT to update your Employee Profile.'
            ], 422);
        }

        try {
            return \DB::transaction(function () use ($request, $validated, $secid) {
                $allocations = collect($request->allocations);
                $totalConsolidatedAmount = round((float)$allocations->sum('amount'), 2);

                // Pre-check budgets across all submitted allocation activities
                foreach ($request->allocations as $index => $allocation) {
                    $activity = \App\Models\Activity::findOrFail($allocation['activity_id']);

                    // Calculate total spent based on line item details
                    $totalSpent = \App\Models\FundMultiActivity::whereHas('parentFund', function ($q) use ($activity) {
                        $q->where('transaction_type_id', $activity->id);
                    })->sum('obligation_amount');

                    // If activity budget calculation includes funds table totals:
                    $totalSpentFromFunds = \App\Models\Fund::where('transaction_type_id', $activity->id)->sum('amount');
                    $actualSpent = max($totalSpent, $totalSpentFromFunds);

                    $remaining = (float)($activity->budget ?? 0) + (float)($activity->pooled_amount ?? 0) - $actualSpent;

                    if ($allocation['amount'] > $remaining) {
                        throw new \Exception("Row " . ($index + 1) . " exceeds budget for {$activity->name}. Available: ₱" . number_format($remaining, 2));
                    }
                }

                // Resolve creditor names if selected
                $creditorIds = array_values(array_unique(array_filter($request->creditor_ids ?? [])));
                $creditorNamesStr = null;

                if (!empty($creditorIds)) {
                    $key = config('app.db_common_key') ?? env('DB_COMMON_ENCRYPTION_KEY');
                    $empNames = \DB::connection('db_common')->table('tbl_employee')
                        ->join('tbl_emp_details', 'tbl_employee.empid', '=', 'tbl_emp_details.empid')
                        ->whereIn('tbl_emp_details.dbedid', $creditorIds)
                        ->select(
                            'tbl_emp_details.dbedid',
                            \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.fname, '{$key}') AS CHAR)) as fname"),
                            \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.mname, '{$key}') AS CHAR)) as mname"),
                            \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.lname, '{$key}') AS CHAR)) as lname"),
                            \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.suffix, '{$key}') AS CHAR)) as suffix")
                        )
                        ->get()
                        ->map(function ($emp) {
                            $fname  = trim((string) $emp->fname);
                            $mname  = trim((string) $emp->mname);
                            $lname  = trim((string) $emp->lname);
                            $suffix = trim((string) $emp->suffix);
                            $mi     = $mname ? ' ' . substr($mname, 0, 1) . '.' : '';
                            $suf    = $suffix ? ' ' . $suffix : '';
                            return "{$lname}, {$fname}{$mi}{$suf}";
                        })
                        ->filter()
                        ->toArray();

                    if (!empty($empNames)) {
                        $creditorNamesStr = implode('; ', $empNames);
                    }
                }

                // Primary source ID and activity ID from the first allocation item
                $primarySourceId   = $request->allocations[0]['source_id'];
                $primaryActivityId = $request->allocations[0]['activity_id'];
                $isMultiActivity   = count($request->allocations) > 1;

                // --- 1. CREATE SINGLE PARENT SUMMARY RECORD IN FUNDS ---
                $fundHeader = new \App\Models\Fund();
                $fundHeader->dtrack_no           = $validated['dtrack_no'];
                $fundHeader->transaction_date    = $validated['transaction_date'];
                $fundHeader->particulars         = $validated['particulars'];
                $fundHeader->creditor            = $creditorNamesStr;
                $fundHeader->amount              = $totalConsolidatedAmount;
                $fundHeader->obligation_amount   = 0.00;
                $fundHeader->source_of_fund_id   = $primarySourceId;
                $fundHeader->transaction_type_id = $primaryActivityId; 
                $fundHeader->user_id             = auth()->id();
                $fundHeader->secid               = $secid; 
                $fundHeader->status              = 'Routed';
                $fundHeader->is_multi_activity   = $isMultiActivity;
                $fundHeader->save();

                // Sync creditors into employee_fund pivot table
                if (!empty($creditorIds)) {
                    $pivotRows = array_map(fn($dbedid) => [
                        'fund_id'    => $fundHeader->id,
                        'user_id'    => $dbedid,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], $creditorIds);

                    \DB::table('employee_fund')->insert($pivotRows);
                }

                // --- 2. RECORD EACH ALLOCATION INTO FUND_MULTI_ACTIVITIES ---
                foreach ($request->allocations as $allocation) {
                    $activityId = $allocation['activity_id'];
                    $activity = \App\Models\Activity::findOrFail($activityId);
                    $allocatedAmount = round((float)$allocation['amount'], 2);

                    \App\Models\FundMultiActivity::create([
                        'fund_id'             => $fundHeader->id,
                        'source_of_fund_id'   => $allocation['source_id'] ?? $primarySourceId,
                        'transaction_type_id' => $activityId,
                        'dtrack_no'           => $validated['dtrack_no'],
                        'routed_amount'       => $allocatedAmount,
                        'creditor'            => $creditorNamesStr ?? $activity->name,
                        'particulars'         => $validated['particulars'],
                        'obligation_amount'   => 0.00,
                        'status'              => 'Routed',
                    ]);
                }

                return response()->json([
                    'success' => true, 
                    'message' => 'Multi-activity transaction saved successfully as routed transaction!'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => $e->getMessage()
            ], 422);
        }
    }

    // public function create()
    // {
    //     // Fetch users who have an employee detail link
    //     // We 'eager load' employeeDetail to avoid N+1 query issues
    //     $users = \App\Models\User::has('employeeDetail')->with('employeeDetail')->get();

    //     return view('your-view-name', compact('users'));
    // }

    public function updateStatus(Request $request, $id)
    {
        // 1. Get the primary record to identify the DTRACK group
        $primaryFund = Fund::findOrFail($id);
        $dtrack = $primaryFund->dtrack_no;

        // 2. Auto-format dtrack_no_new if provided (e.g. "041234" -> "2026-041234")
        if ($request->filled('dtrack_no_new')) {
            $yearPrefix = date('Y') . '-';
            $rawNewDtrack = trim($request->dtrack_no_new);

            if (!str_starts_with($rawNewDtrack, $yearPrefix)) {
                $cleanNumber = preg_replace('/[^0-9]/', '', $rawNewDtrack);
                $suffix = str_replace(date('Y'), '', $cleanNumber);
                $request->merge(['dtrack_no_new' => $yearPrefix . $suffix]);
            } else {
                $request->merge(['dtrack_no_new' => $rawNewDtrack]);
            }
        }

        // 3. Validate Request
        $request->validate([
            'status_date'                          => 'required|date',
            'status'                               => 'required|in:Routed,For Signature,Obligated,Cancelled',
            'dtrack_no_new'                        => ['nullable', 'regex:/^\d{4}-\d{6}$/'],
            'remarks'                              => $request->status === 'Cancelled' ? 'required|string' : 'nullable|string',
            'serials'                              => 'nullable|array',
            'cancellations'                        => 'nullable|array',
            'cancellations.*.fund_id'              => 'required_with:cancellations|exists:funds,id',
            'cancellations.*.cancellation_serial'  => 'required_with:cancellations|string|max:50',
            'cancellations.*.cancelled_amount'     => 'required_with:cancellations|numeric|min:0.01',
        ]);

        // 4. Prevent assigning a PO DTrack that already belongs to another PR group
        if ($request->filled('dtrack_no_new')) {
            $duplicatePoDtrack = Fund::where('dtrack_no', '!=', $dtrack)
                ->where(function ($q) use ($request) {
                    $q->where('dtrack_no', $request->dtrack_no_new)
                    ->orWhere('dtrack_no_new', $request->dtrack_no_new);
                })
                ->exists();

            if ($duplicatePoDtrack) {
                return response()->json([
                    'success' => false,
                    'message' => "PO DTrack {$request->dtrack_no_new} is already linked to another transaction."
                ], 422);
            }
        }

        // 5. Fetch all allocations in this DTrack group
        $funds = Fund::where('dtrack_no', $dtrack)->get();

        DB::beginTransaction();
        try {
            $submittedSerials       = collect($request->input('serials', []))->keyBy('id');
            $submittedCancellations = collect($request->input('cancellations', []))->keyBy('fund_id');
            $newPoDtrack            = $request->filled('dtrack_no_new') ? $request->dtrack_no_new : null;

            foreach ($funds as $fund) {
                $wasObligated = (
                    $fund->status === 'Obligated' ||
                    (float) $fund->obligation_amount > 0 ||
                    !empty($fund->obligation_serial)
                );

                // CASE A: Cancelling / Deobligating a transaction
                if ($request->status === 'Cancelled') {
                    if ($wasObligated) {
                        $cancelRow = $submittedCancellations->get($fund->id);

                        if (!$cancelRow || empty(trim($cancelRow['cancellation_serial'] ?? ''))) {
                            throw new \Exception("New Cancellation ORS Serial Number is required for obligated fund #{$fund->id}.");
                        }

                        $cancelledAmt = round((float) $cancelRow['cancelled_amount'], 2);
                        $maxAllowed   = (float) ($fund->obligation_amount > 0 ? $fund->obligation_amount : $fund->amount);

                        if ($cancelledAmt > ($maxAllowed + 0.01)) {
                            throw new \Exception("Cancelled amount (₱" . number_format($cancelledAmt, 2) . ") cannot exceed obligated amount (₱" . number_format($maxAllowed, 2) . ").");
                        }

                        // Record deobligation into fund_cancellations (keeps original obligation_serial & obligation_date intact)
                        \App\Models\FundCancellation::updateOrCreate(
                            [
                                'fund_id'                        => $fund->id,
                                'cancellation_obligation_serial' => trim($cancelRow['cancellation_serial']),
                            ],
                            [
                                'source_of_fund_id'          => $fund->source_of_fund_id,
                                'dtrack_no'                  => $fund->dtrack_no,
                                'original_obligation_serial' => $fund->obligation_serial,
                                'cancellation_date'          => $request->status_date,
                                'cancelled_amount'           => $cancelledAmt,
                                'reason'                     => $request->remarks,
                                'cancelled_by'               => auth()->id(),
                            ]
                        );

                        $fund->update([
                            'status'        => 'Cancelled',
                            'status_date'   => $request->status_date,
                            'remarks'       => $request->remarks,
                            'dtrack_no_new' => $newPoDtrack ?? $fund->dtrack_no_new,
                        ]);
                    } else {
                        // Not yet obligated (Routed / For Signature) -> simply mark Cancelled
                        $fund->update([
                            'status'            => 'Cancelled',
                            'status_date'       => $request->status_date,
                            'remarks'           => $request->remarks,
                            'dtrack_no_new'     => $newPoDtrack,
                            'obligation_serial' => null,
                        ]);
                    }

                    // Sync Cancelled status to child multi-activity records
                    \App\Models\FundMultiActivity::where('fund_id', $fund->id)
                        ->update(['status' => 'Cancelled']);

                    continue;
                }

                // CASE B: Standard Status Updates (Routed / For Signature / Obligated)
                $updateData = [
                    'status_date'   => $request->status_date,
                    'remarks'       => $request->remarks,
                    'dtrack_no_new' => $newPoDtrack,
                ];

                // Protect already-synced/obligated rows unless explicitly updating serials
                if ($fund->status === 'Obligated' && $fund->obligation_amount > 0) {
                    $rowSerial = $submittedSerials->get($fund->id);
                    if ($rowSerial && !empty($rowSerial['serial_no'])) {
                        $updateData['obligation_serial'] = trim($rowSerial['serial_no']);
                    }
                } else {
                    $updateData['status'] = $request->status;

                    if ($request->status === 'Obligated') {
                        $rowSerial = $submittedSerials->get($fund->id);
                        $updateData['obligation_serial'] = !empty($rowSerial['serial_no'])
                            ? trim($rowSerial['serial_no'])
                            : null;
                    } else {
                        $updateData['obligation_serial'] = null;
                    }
                }

                $fund->update($updateData);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $request->status === 'Cancelled'
                    ? 'Transaction cancelled and deobligation recorded successfully!'
                    : 'Group status updated successfully!',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 422);
        }
    }

    public function getGroupByDtrack($dtrack)
    {
        $allocations = Fund::where('dtrack_no', $dtrack)
            ->with(['fundSource', 'cancellations'])
            ->get()
            ->map(function ($fund) {
                $latestCancel = $fund->cancellations->sortByDesc('id')->first();

                return [
                    'id'                    => $fund->id,
                    'dtrack_no_new'         => $fund->dtrack_no_new,
                    'source_name'           => $fund->fundSource->name ?? 'N/A',
                    'amount'                => number_format($fund->amount, 2),
                    'raw_amount'            => (float) $fund->amount,
                    'raw_obligation_amount' => (float) $fund->obligation_amount,
                    'obligation_serial'     => $fund->obligation_serial,
                    'cancellation_serial'   => $latestCancel?->cancellation_obligation_serial,
                    'status'                => $fund->status,
                    'remarks'               => $fund->remarks,
                    'status_date'           => $fund->status_date ? \Carbon\Carbon::parse($fund->status_date)->format('Y-m-d') : date('Y-m-d'),
                ];
            });

        return response()->json(['allocations' => $allocations]);
    }

    public function edit(\App\Models\Fund $fund)
    {
        // 1. Validation (Only 'Routed' status can be edited)
        if (strtolower($fund->status) !== 'routed') {
            return response()->json([
                'success' => false,
                'message' => 'Editing is only allowed for transactions in Routed status.'
            ], 403);
        }

        // 2. Fetch all activity line items from fund_multi_activities for this parent fund
        $multiItems = \App\Models\FundMultiActivity::where('fund_id', $fund->id)
            ->with(['sourceOfFund', 'activity'])
            ->orderBy('id')
            ->get();

        if ($multiItems->isNotEmpty()) {
            $allAllocations = $multiItems->map(function ($item) {
                return [
                    'id'                  => $item->id,
                    'source_of_fund_id'   => $item->source_of_fund_id,
                    'transaction_type_id' => $item->transaction_type_id,
                    'amount'              => (float) $item->routed_amount,
                    'routed_amount'       => (float) $item->routed_amount,
                    'fund_source'         => $item->sourceOfFund,
                    'activity'            => $item->activity,
                ];
            })->values();
        } else {
            // Fallback for legacy records created before fund_multi_activities was used
            $allAllocations = \App\Models\Fund::where('dtrack_no', $fund->dtrack_no)
                ->with(['fundSource', 'activity'])
                ->get()
                ->map(function ($row) {
                    return [
                        'id'                  => null, // Will be saved into fund_multi_activities on update
                        'source_of_fund_id'   => $row->source_of_fund_id,
                        'transaction_type_id' => $row->transaction_type_id,
                        'amount'              => (float) $row->amount,
                        'routed_amount'       => (float) $row->amount,
                        'fund_source'         => $row->fundSource,
                        'activity'            => $row->activity,
                    ];
                })->values();
        }

        // 3. Load Creditors from employee_fund pivot table
        $relatedFundIds = \App\Models\Fund::where('dtrack_no', $fund->dtrack_no)->pluck('id');
        $creditorIds = \DB::table('employee_fund')
            ->whereIn('fund_id', $relatedFundIds)
            ->pluck('user_id') // user_id column stores the dbedid
            ->unique()
            ->values()
            ->toArray();

        // 4. Return structured JSON object
        return response()->json([
            'success'      => true,
            'main'         => $fund,
            'allocations'  => $allAllocations,
            'creditor_ids' => $creditorIds,
        ]);
    }

    public function update(Request $request, $id, \App\Services\DTrackService $dtrackService)
    {
        // 1. Initial Fetch and Guard Clause
        $primaryFund = \App\Models\Fund::findOrFail($id);

        if (strtolower($primaryFund->status) !== 'routed') {
            return response()->json([
                'success' => false,
                'message' => "This transaction is currently '{$primaryFund->status}' and cannot be modified."
            ], 422);
        }

        $oldDtrack = $primaryFund->dtrack_no;
        $yearPrefix = date('Y') . '-';

        // 2. Auto-fix dtrack_no format (Mirrors store logic)
        if (!str_starts_with($request->dtrack_no, $yearPrefix)) {
            $cleanNumber = preg_replace('/[^0-9]/', '', $request->dtrack_no);
            $suffix = str_replace(date('Y'), '', $cleanNumber);
            $request->merge(['dtrack_no' => $yearPrefix . $suffix]);
        }

        // 3. VALIDATION (creditor_ids is optional for all transactions)
        $validated = $request->validate([
            'dtrack_no'                 => ['required', 'regex:/^\d{4}-\d{6}$/'],
            'transaction_date'          => 'required|date',
            'particulars'               => 'required|string',
            'creditor_ids'              => 'nullable|array',
            'allocations'               => 'required|array|min:1',
            'allocations.*.id'          => 'nullable|integer',
            'allocations.*.source_id'   => 'required|exists:source_of_funds,id',
            'allocations.*.activity_id' => 'required|exists:activities,id',
            'allocations.*.amount'      => 'required|numeric|min:0.01',
        ]);

        // Check DTrack uniqueness if changed
        if ($validated['dtrack_no'] !== $oldDtrack) {
            $duplicateDtrack = \App\Models\Fund::where('dtrack_no', $validated['dtrack_no'])
                ->where('id', '!=', $primaryFund->id)
                ->exists();

            if ($duplicateDtrack) {
                return response()->json([
                    'success' => false,
                    'message' => 'This DTrack number has already been used.'
                ], 422);
            }
        }

        // 4. SECID Logic
        $userId = auth()->id();
        $secid = (auth()->user()->username === 'admin') ? 0 : null;

        if (is_null($secid)) {
            $localDetail = \DB::table('employee_details')->where('user_id', $userId)->first();
            if ($localDetail && $localDetail->dbedid) {
                $secid = \DB::connection('db_common')->table('tbl_emp_details')
                    ->where('dbedid', $localDetail->dbedid)
                    ->value('secid');
            }
        }

        if (is_null($secid) && auth()->user()->username !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'System Error: Profile section link missing.'
            ], 422);
        }

        try {
            return \DB::transaction(function () use ($request, $validated, $secid, $primaryFund, $oldDtrack) {
                $allocations = collect($request->allocations);
                $totalConsolidatedAmount = round((float) $allocations->sum('amount'), 2);

                // Group submitted amounts by activity_id in case multiple rows share an activity
                $submittedByActivity = $allocations->groupBy('activity_id')
                    ->map(fn($rows) => round((float) $rows->sum('amount'), 2));

                // A. Budget Check per Activity (excluding this parent transaction's existing allocations)
                foreach ($request->allocations as $index => $item) {
                    $activity = \App\Models\Activity::findOrFail($item['activity_id']);

                    // Spent in fund_multi_activities excluding current parent fund
                    $multiSpentByOthers = (float) \App\Models\FundMultiActivity::where('transaction_type_id', $activity->id)
                        ->where('fund_id', '!=', $primaryFund->id)
                        ->whereHas('parentFund', function ($q) {
                            $q->whereNotIn('status', ['Cancelled', 'Rejected']);
                        })
                        ->sum('routed_amount');

                    // Spent in legacy single-activity funds (no fund_multi_activities rows) excluding current parent fund
                    $legacySingleSpentByOthers = (float) \App\Models\Fund::where('transaction_type_id', $activity->id)
                        ->where('id', '!=', $primaryFund->id)
                        ->where('dtrack_no', '!=', $oldDtrack)
                        ->whereNotIn('status', ['Cancelled', 'Rejected'])
                        ->whereDoesntHave('multiActivities')
                        ->sum('amount');

                    $totalSpentByOthers = $multiSpentByOthers + $legacySingleSpentByOthers;
                    $availableBudget = ((float) ($activity->budget_adjusted ?? $activity->budget ?? 0)) - ((float) ($activity->pooled_amount ?? 0));
                    $remaining = $availableBudget - $totalSpentByOthers;

                    if ($submittedByActivity[$activity->id] > ($remaining + 0.01)) {
                        throw new \Exception(
                            "Row " . ($index + 1) . " exceeds budget for {$activity->name}. Available: ₱" . number_format(max(0, $remaining), 2)
                        );
                    }
                }

                // B. Clean up any legacy duplicate Fund rows sharing oldDtrack (keep only $primaryFund)
                \App\Models\Fund::where('dtrack_no', $oldDtrack)
                    ->where('id', '!=', $primaryFund->id)
                    ->delete();

                // C. Update Single Parent Header Record in `funds`
                $primarySourceId   = $request->allocations[0]['source_id'];
                $primaryActivityId = $request->allocations[0]['activity_id'];
                $isMultiActivity   = count($request->allocations) > 1;

                $primaryFund->update([
                    'dtrack_no'           => $validated['dtrack_no'],
                    'transaction_date'    => $validated['transaction_date'],
                    'particulars'         => $validated['particulars'],
                    'amount'              => $totalConsolidatedAmount,
                    'source_of_fund_id'   => $primarySourceId,
                    'transaction_type_id' => $primaryActivityId,
                    'user_id'             => auth()->id(),
                    'secid'               => $secid,
                    'status'              => 'Routed',
                    'is_multi_activity'   => $isMultiActivity,
                ]);

                // Sync Creditors once on the parent summary record
                $primaryFund->creditors()->sync($request->creditor_ids ?? []);

                // D. Handle Deletions in `fund_multi_activities` (Rows removed in Edit Modal)
                $existingItemIds = \App\Models\FundMultiActivity::where('fund_id', $primaryFund->id)
                    ->pluck('id')
                    ->toArray();

                $submittedItemIds = collect($request->allocations)
                    ->pluck('id')
                    ->filter(fn($val) => !empty($val) && in_array((int) $val, $existingItemIds))
                    ->map(fn($val) => (int) $val)
                    ->toArray();

                $idsToDelete = array_diff($existingItemIds, $submittedItemIds);
                if (!empty($idsToDelete)) {
                    \App\Models\FundMultiActivity::whereIn('id', $idsToDelete)->delete();
                }

                // E. Update or Create Each Line Item in `fund_multi_activities`
                foreach ($request->allocations as $item) {
                    $activity = \App\Models\Activity::findOrFail($item['activity_id']);
                    $allocatedAmount = round((float) $item['amount'], 2);
                    $itemId = (!empty($item['id']) && in_array((int) $item['id'], $existingItemIds))
                        ? (int) $item['id']
                        : null;

                    \App\Models\FundMultiActivity::updateOrCreate(
                        ['id' => $itemId],
                        [
                            'fund_id'             => $primaryFund->id,
                            'source_of_fund_id'   => $item['source_id'],
                            'transaction_type_id' => $activity->id,
                            'dtrack_no'           => $validated['dtrack_no'],
                            'routed_amount'       => $allocatedAmount,
                            'creditor'            => $activity->name,
                            'particulars'         => $validated['particulars'],
                            'obligation_amount'   => 0.00,
                            'status'              => 'Routed',
                        ]
                    );
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction updated successfully!'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function checkBalance(Request $request)
    {
        try {
            $sourceId = $request->query('source_of_fund_id');
            $activityId = $request->query('activity_id');
            $inputAmount = (float)$request->query('amount', 0);
            $currentFundId = $request->query('current_fund_id');

            $activity = Activity::find($activityId);
            if (!$activity) {
                return response()->json(['error' => 'Activity not found'], 404);
            }

            // 1. Determine the Base Budget
            $baseBudget = !is_null($activity->budget_adjusted) 
                ? (float)$activity->budget_adjusted 
                : (float)$activity->budget;

            // 2. Apply Pooled Deduction
            $pooledAmount = (float)($activity->pooled_amount ?? 0);
            $adjustedLimit = $baseBudget - $pooledAmount;

            // 3. Calculate Total Spent (Same priority logic)
            $totalSpent = Fund::where('transaction_type_id', $activityId)
                ->where('source_of_fund_id', $sourceId)
                ->whereNotIn('status', ['Cancelled', 'cancelled']) 
                ->when($currentFundId, function ($query) use ($currentFundId) {
                    return $query->where('id', '!=', $currentFundId);
                })
                ->sum(\DB::raw('CASE 
                    WHEN obligation_amount IS NOT NULL AND obligation_amount > 0 THEN obligation_amount 
                    ELSE amount 
                END'));

            $remainingBalance = $adjustedLimit - (float)$totalSpent;
            $displayBalance = max(0, $remainingBalance);

            return response()->json([
                'status' => 'success',
                'remaining' => $remainingBalance,
                // Flag to tell JS to disable this activity in the dropdown
                'is_depleted' => round($remainingBalance, 2) <= 0, 
                'is_sufficient' => round($inputAmount, 2) <= round($remainingBalance, 2),
                'formatted_remaining' => number_format($displayBalance, 2),
                'debug' => [
                    'base_budget' => $baseBudget,
                    'pooled_deducted' => $pooledAmount,
                    'total_spent' => $totalSpent
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function show($id)
    {
        $fund = Fund::findOrFail($id);
        return view('funds.show', compact('fund'));
    }

    public function saveManualActivityObligation(Request $request)
    {
        $request->validate([
            'dtrack_no'            => 'required|string',
            'total_net_obligated'  => 'required|numeric|min:0',
            'allocations'          => 'required|array|min:1',
            'allocations.*.amount' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $allocations = collect($request->allocations);
            $userSum = round($allocations->sum('amount'), 2);
            $expectedTotal = round((float)$request->total_net_obligated, 2);

            if (abs($userSum - $expectedTotal) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => "Validation Error: The sum of allocated amounts (₱" . number_format($userSum, 2) . ") must exactly equal the consolidated obligated amount (₱" . number_format($expectedTotal, 2) . ")."
                ], 422);
            }

            $trackerData = $request->tracker_data ?? $request->alloc_tracker_data ?? [];
            if (is_string($trackerData)) {
                $trackerData = json_decode($trackerData, true) ?: [];
            }

            $totalDisbursedFromRaods = (float)($trackerData['netDisb'] ?? 0);
            $disbursements           = $trackerData['disbursements'] ?? [];
            $latestDisbDate          = $trackerData['latestDisbDate'] ?? null;
            $latestObDate            = $trackerData['latestObDate'] ?? null;

            $primaryFund = Fund::where('dtrack_no', $request->dtrack_no)->firstOrFail();

            // Standard parent-level fallback resolution
            $parentSourceOfFundId = $primaryFund->source_of_fund_id 
                ?? $request->source_of_fund_id 
                ?? $trackerData['source_of_fund_id'] 
                ?? null;

            $parentTransactionTypeId = $primaryFund->transaction_type_id 
                ?? $request->transaction_type_id 
                ?? $trackerData['transaction_type_id'] 
                ?? null;

            $status = ($totalDisbursedFromRaods > 0)
                ? (($expectedTotal > 0 && $totalDisbursedFromRaods < $expectedTotal) ? 'Disbursed (with savings)' : 'Disbursed')
                : 'Obligated';

            $primaryFund->update([
                'source_of_fund_id'   => $parentSourceOfFundId ?: $primaryFund->source_of_fund_id,
                'transaction_type_id' => $parentTransactionTypeId ?: $primaryFund->transaction_type_id,
                'obligation_amount'   => $expectedTotal,
                'disbursement_amount' => $totalDisbursedFromRaods,
                'obligation_serial'   => $request->obligation_serial ?: $primaryFund->obligation_serial,
                'obligation_date'     => $latestObDate ?: $primaryFund->obligation_date,
                'disbursement_date'   => $latestDisbDate ?: $primaryFund->disbursement_date,
                'status'              => $status,
                'status_date'         => now(),
                'is_multi_activity'   => count($request->allocations) > 1,
            ]);

            foreach ($request->allocations as $alloc) {
                $allocatedAmount = (float)$alloc['amount'];

                // Priority logic for individual row fields
                $sourceOfFundId = $alloc['source_of_fund_id'] ?? $parentSourceOfFundId;
                $transactionTypeId = $alloc['transaction_type_id'] ?? $parentTransactionTypeId;

                $routedAmount = (isset($alloc['routed_amount']) && (float)$alloc['routed_amount'] > 0)
                    ? (float)$alloc['routed_amount']
                    : $allocatedAmount;

                if (isset($alloc['disbursement_amount'])) {
                    $allocatedDisbursement = round((float)$alloc['disbursement_amount'], 2);
                } else {
                    $ratio = ($expectedTotal > 0) ? ($allocatedAmount / $expectedTotal) : (1 / count($request->allocations));
                    $allocatedDisbursement = round($totalDisbursedFromRaods * $ratio, 2);
                }

                if ($allocatedDisbursement > 0) {
                    $activityStatus = ($allocatedAmount > 0 && $allocatedDisbursement < $allocatedAmount)
                        ? 'Disbursed (with savings)'
                        : 'Disbursed';
                } else {
                    $activityStatus = ($allocatedAmount > 0) ? 'Obligated' : 'Routed';
                }

                $matchAttributes = !empty($alloc['id'])
                    ? ['id' => $alloc['id']]
                    : [
                        'fund_id'  => $primaryFund->id,
                        'creditor' => $alloc['activity_name'] ?? ('Activity #' . ($alloc['fund_id'] ?? 1)),
                    ];

                \App\Models\FundMultiActivity::updateOrCreate(
                    $matchAttributes,
                    [
                        'fund_id'             => $primaryFund->id,
                        'source_of_fund_id'   => $sourceOfFundId,
                        'transaction_type_id' => $transactionTypeId,
                        'dtrack_no'           => $request->dtrack_no,
                        'creditor'            => $alloc['activity_name'] ?? null,
                        'particulars'         => $alloc['particulars'] ?? $primaryFund->particulars,
                        'routed_amount'       => $routedAmount,
                        'obligation_serial'   => $request->obligation_serial ?: $primaryFund->obligation_serial,
                        'obligation_amount'   => $allocatedAmount,
                        'disbursement_amount' => $allocatedDisbursement,
                        'status'              => $activityStatus,
                    ]
                );
            }

            if (!empty($disbursements)) {
                $this->syncDisbursementRecords($primaryFund, $disbursements, false);
            }

            $norsaEntries = $request->tracker_norsa_entries;
            if (is_string($norsaEntries)) {
                $norsaEntries = json_decode($norsaEntries, true) ?: [];
            }

            if (!empty($norsaEntries) && (float)$request->total_norsa > 0) {
                $this->syncNorsaAdjustments($primaryFund, $norsaEntries, $parentSourceOfFundId);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Consolidated activity obligations and disbursements updated successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage() . " at line " . $e->getLine()], 500);
        }
    }

    /**
     * Build a Google Sheets service instance that works on both Ubuntu servers and Windows XAMPP,
     * with OAuth token caching and IPv4 enforcement to prevent cURL port 443 timeouts.
     */
    private function getGoogleSheetsService(): \Google_Service_Sheets
    {
        $client = new \Google\Client();

        // Retry middleware for transient cURL error 7 / 28 network drops on Ubuntu server
        $stack = \GuzzleHttp\HandlerStack::create();
        $stack->push(\GuzzleHttp\Middleware::retry(
            function ($retries, $request, $response = null, $exception = null) {
                if ($retries >= 3) {
                    return false;
                }
                // Retry on network/cURL connection errors or 5xx/429 responses
                if ($exception instanceof \GuzzleHttp\Exception\ConnectException) {
                    return true;
                }
                if ($response && ($response->getStatusCode() >= 500 || $response->getStatusCode() === 429)) {
                    return true;
                }
                return false;
            },
            function ($retries) {
                return 500 * ($retries + 1); // 500ms, 1000ms, 1500ms backoff
            }
        ));

        $guzzleOptions = [
            'handler'         => $stack,
            'timeout'         => 60,
            'connect_timeout' => 20,
            'curl'            => [
                CURLOPT_IPRESOLVE     => CURL_IPRESOLVE_V4,
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_FORBID_REUSE  => true,
            ],
        ];

        if (PHP_OS_FAMILY === 'Windows' && file_exists('C:\xampp\php\extras\ssl\cacert.pem')) {
            $guzzleOptions['verify'] = 'C:\xampp\php\extras\ssl\cacert.pem';
        }

        $client->setHttpClient(new \GuzzleHttp\Client($guzzleOptions));
        $client->setAuthConfig(storage_path('app/google-credentials.json'));
        $client->addScope(\Google_Service_Sheets::SPREADSHEETS_READONLY);

        $token = Cache::get('google_sheets_oauth_token');
        if (!$token) {
            $token = Cache::lock('lock_google_sheets_oauth_token', 30)->block(25, function () use ($client) {
                if (Cache::has('google_sheets_oauth_token')) {
                    return Cache::get('google_sheets_oauth_token');
                }
                $newToken = $client->fetchAccessTokenWithAssertion();
                Cache::put('google_sheets_oauth_token', $newToken, now()->addMinutes(50));
                return $newToken;
            });
        }

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $newToken = $client->fetchAccessTokenWithAssertion();
            Cache::put('google_sheets_oauth_token', $newToken, now()->addMinutes(50));
            $client->setAccessToken($newToken);
        }

        return new \Google_Service_Sheets($client);
    }

    public function syncWithGoogleSheet(Request $request, $id)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        try {
            $clickedFund  = Fund::findOrFail($id);
            $prDtrack     = trim((string) $clickedFund->dtrack_no);
            $poDtrack     = trim((string) ($clickedFund->dtrack_no_new ?? ''));
            $targetDtrack = !empty($poDtrack) ? $poDtrack : $prDtrack;

            // Fetch any PO DTracks encoded under procurement_transactions / procurement_transaction_items
            $procTx = \App\Models\ProcurementTransaction::where('fund_transaction_id', $clickedFund->id)->first();
            $procPoDtracks = [];

            if ($procTx) {
                if (!empty($procTx->po_dtrack_no)) {
                    $procPoDtracks[] = trim((string) $procTx->po_dtrack_no);
                }
                $itemDtracks = \App\Models\ProcurementTransactionItem::where('procurement_transaction_id', $procTx->id)
                    ->whereNotNull('po_dtrack_no')
                    ->pluck('po_dtrack_no')
                    ->map(fn($d) => trim((string) $d))
                    ->toArray();

                $procPoDtracks = array_values(array_unique(array_merge($procPoDtracks, $itemDtracks)));
            }

            // Match against PR DTrack, PO DTrack (dtrack_no_new), and any Procurement PO DTracks
            $candidateDtracks = array_values(array_filter(array_unique(array_merge([$prDtrack, $poDtrack], $procPoDtracks))));

            if (empty($candidateDtracks)) {
                return response()->json(['success' => false, 'message' => 'DTrack number is missing.']);
            }

            $sourceConfig = \App\Models\SourceOfFund::find($clickedFund->source_of_fund_id);
            if (!$sourceConfig || !$sourceConfig->spreadsheet_id || !$sourceConfig->sheet_name) {
                return response()->json(['success' => false, 'message' => 'Fund source configuration missing.']);
            }

            $syncedDetails = [];
            $userId = Auth::id() ?? 1;

            // Cache the sheet rows for 3 minutes; only initialize Google Sheets API on cache miss
            $sheetCacheKey = "raods_sheet_{$sourceConfig->spreadsheet_id}_" . md5($sourceConfig->sheet_name);
            $rows = Cache::get($sheetCacheKey);

            if (is_null($rows)) {
                $rows = Cache::lock("lock_{$sheetCacheKey}", 45)->block(40, function () use ($sheetCacheKey, $sourceConfig) {
                    if (Cache::has($sheetCacheKey)) {
                        return Cache::get($sheetCacheKey);
                    }
                    $service = $this->getGoogleSheetsService();
                    $range = "'{$sourceConfig->sheet_name}'!A:CQ";
                    $response = $service->spreadsheets_values->get($sourceConfig->spreadsheet_id, $range);
                    $fetchedRows = $response->getValues() ?? [];
                    Cache::put($sheetCacheKey, $fetchedRows, now()->addMinutes(3));
                    return $fetchedRows;
                });
            }

            // =====================================================================
            // BRANCH A: PROCURABLE TRANSACTION WITH PO DTRACK(S) IN PROCUREMENT TABLES
            // =====================================================================
            if (!empty($rows) && !empty($procPoDtracks)) {
                $allMatchedRows = [];

                foreach ($procPoDtracks as $singlePoDtrack) {
                    $poTracker = [
                        'netOb'          => 0.0,
                        'netDisb'        => 0.0,
                        'latestObDate'   => null,
                        'latestDisbDate' => null,
                        'found_serial'   => null,
                        'found'          => false,
                        'disbursements'  => [],
                        'norsa_entries'  => [],
                    ];
                    $poSerial   = null;
                    $poCreditor = null;

                    foreach ($rows as $rowData) {
                        $sheetDtrack    = isset($rowData[0])  ? trim((string) $rowData[0])  : '';
                        $sheetSerialRow = isset($rowData[3])  ? trim((string) $rowData[3])  : '';
                        $creditorRow    = isset($rowData[10]) ? trim((string) $rowData[10]) : null;
                        $particularsRow = isset($rowData[11]) ? trim((string) $rowData[11]) : '';

                        if ($sheetDtrack !== $singlePoDtrack) continue;

                        $poTracker['found'] = true;
                        $allMatchedRows[]   = $rowData;

                        if (!empty($sheetSerialRow)) $poSerial   = $sheetSerialRow;
                        if (!empty($creditorRow))    $poCreditor = $creditorRow;

                        $this->processApiRowData($rowData, $poTracker, $particularsRow, false);
                    }

                    if ($poTracker['found']) {
                        if ($poTracker['netDisb'] > 0) {
                            $poStatus = ($poTracker['netOb'] > 0 && $poTracker['netDisb'] < $poTracker['netOb'])
                                ? 'Disbursed (with savings)'
                                : 'Disbursed';
                            $displayAmt = $poTracker['netDisb'];
                        } else {
                            $poStatus = 'Obligated';
                            $displayAmt = $poTracker['netOb'];
                        }

                        $updatedParentFunds = $this->syncProcurementPoFromRaods($singlePoDtrack, $poTracker, $poSerial, $poCreditor, $poStatus);

                        // Ensure clickedFund is always included even if already updated in the array
                        $fundsToSync = collect($updatedParentFunds)->keyBy('id');
                        $fundsToSync->put($clickedFund->id, $clickedFund->fresh());

                        foreach ($fundsToSync as $targetParentFund) {
                            $this->syncDisbursementRecords($targetParentFund, $poTracker['disbursements'], false);
                            $this->syncNorsaAdjustments($targetParentFund, $poTracker['norsa_entries'] ?? [], $sourceConfig->id);
                        }

                        $syncedDetails[] = [
                            'name'   => $sourceConfig->name ?? 'Fund Source',
                            'dtrack' => $singlePoDtrack,
                            'serial' => $poSerial,
                            'amount' => number_format($displayAmt, 2),
                            'status' => $poStatus,
                        ];
                    }
                }

                if (!empty($allMatchedRows)) {
                    $this->syncFundDtrackLogs($clickedFund, $allMatchedRows, $rows);
                }

                if (empty($syncedDetails)) {
                    return response()->json([
                        'success'           => false,
                        'obligation_serial' => implode(', ', $procPoDtracks),
                        'message'           => 'PO DTrack(s) (' . implode(', ', $procPoDtracks) . ') not yet found in RAODS',
                    ]);
                }

                $freshFund = $clickedFund->fresh();
                return response()->json([
                    'success'           => true,
                    'obligation_serial' => $freshFund->obligation_serial ?: implode(', ', $procPoDtracks),
                    'details'           => [
                        'dtrack_no'    => implode(', ', $procPoDtracks),
                        'synced_items' => $syncedDetails,
                        'count'        => count($syncedDetails),
                    ],
                ]);
            }

            // =====================================================================
            // BRANCH B: STANDARD NON-PROCUREMENT / DIRECT TRANSACTION SYNC
            // =====================================================================
            if (!empty($rows)) {
                $tracker = [
                    'netOb'          => 0.0,
                    'netDisb'        => 0.0,
                    'latestObDate'   => null,
                    'latestDisbDate' => null,
                    'found_serial'   => null,
                    'found'          => false,
                    'disbursements'  => [],
                    'norsa_entries'  => [],
                ];

                $creditorName       = null;
                $particulars        = '';
                $sheetSerial        = null;
                $matchedSheetDtrack = null;
                $isCosSalary        = false;
                $matchedRows        = [];
                $cancellationRows   = [];

                // Check if multiple distinct creditors share this DTrack/Serial in RAODS (e.g. batch COS payroll)
                $matchingDtrackRows = array_filter($rows, function ($r) use ($candidateDtracks, $clickedFund) {
                    $d = isset($r[0]) ? trim((string) $r[0]) : '';
                    $s = isset($r[3]) ? trim((string) $r[3]) : '';
                    return in_array($d, $candidateDtracks, true)
                        || (!empty($s) && $s === $clickedFund->obligation_serial);
                });

                $distinctSheetCreditors = collect($matchingDtrackRows)
                    ->map(fn($r) => isset($r[10]) ? strtolower(trim((string) $r[10])) : '')
                    ->filter()
                    ->unique()
                    ->count();

                // Normalize names regardless of "LAST, FIRST" vs "FIRST LAST" or Ñ vs N
                $normalizePersonName = function (?string $name): array {
                    if (empty($name)) return [];
                    $ascii  = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
                    $clean  = strtolower(preg_replace('/[^a-z0-9\s]/i', ' ', $ascii));
                    $tokens = array_values(array_filter(explode(' ', $clean), fn($t) => strlen($t) > 1));
                    sort($tokens);
                    return $tokens;
                };

                foreach ($rows as $rowIndex => $rowData) {
                    $sheetDtrack    = isset($rowData[0])  ? trim((string) $rowData[0])  : '';
                    $sheetSerialRow = isset($rowData[3])  ? trim((string) $rowData[3])  : '';
                    $allotment      = isset($rowData[5])  ? trim((string) $rowData[5])  : '';
                    $creditorRow    = isset($rowData[10]) ? trim((string) $rowData[10]) : null;
                    $particularsRow = isset($rowData[11]) ? trim((string) $rowData[11]) : '';

                    if (empty($sheetDtrack) && empty($sheetSerialRow)) continue;

                    $isTargetMatch = in_array($sheetDtrack, $candidateDtracks, true) ||
                                    (!empty($sheetSerialRow) && $sheetSerialRow === $clickedFund->obligation_serial);

                    if (!$isTargetMatch) continue;

                    // Only filter by creditor when multiple distinct creditors share the same DTrack/Serial
                    if ($distinctSheetCreditors > 1 && $clickedFund->creditor && $creditorRow) {
                        $targetTokens = $normalizePersonName($clickedFund->creditor);
                        $sheetTokens  = $normalizePersonName($creditorRow);
                        if (!empty($targetTokens) && !empty($sheetTokens) && $targetTokens !== $sheetTokens) {
                            continue;
                        }
                    }

                    $tracker['found'] = true;
                    $matchedRows[] = $rowData;

                    if (!empty($sheetDtrack))    $matchedSheetDtrack = $sheetDtrack;
                    if (!empty($sheetSerialRow)) $sheetSerial        = $sheetSerialRow;
                    if (!empty($creditorRow))    $creditorName       = $creditorRow;
                    if (!empty($particularsRow)) $particulars        = $particularsRow;

                    if (stripos($particularsRow, 'cancelled ors') !== false || stripos($particularsRow, 'deobligation') !== false) {
                        $cancellationRows[] = $rowData;
                    }

                    $cleanAllotment   = strtolower(preg_replace('/\s+/', ' ', $allotment));
                    $cleanParticulars = strtolower(preg_replace('/\s+/', ' ', $particularsRow));

                    $isOtherProfServ = (stripos($cleanAllotment, 'other professional services') !== false);
                    $isWages         = (stripos($cleanParticulars, 'wages') !== false);
                    if ($isOtherProfServ && $isWages) {
                        $isCosSalary = true;
                    }

                    $this->processApiRowData($rowData, $tracker, $particularsRow, $isCosSalary);
                }

                if ($tracker['found']) {
                    $disbursedMonthCount = count($tracker['disbursements'] ?? []);
                    $contractId = null;
                    $contractDetails = [];

                    if ($isCosSalary) {
                        $contractDetails = $this->parseCosParticulars($particulars, $creditorName);
                        if (!empty($contractDetails['start_date']) && !empty($contractDetails['end_date'])) {
                            $contractStatus = ($disbursedMonthCount >= $contractDetails['total_months'] && $contractDetails['total_months'] > 0)
                                ? 'Completed'
                                : 'Active';

                            $contract = \App\Models\CosContract::firstOrCreate(
                                [
                                    'creditor_name' => $creditorName,
                                    'start_date'    => $contractDetails['start_date'],
                                    'end_date'      => $contractDetails['end_date'],
                                ],
                                [
                                    'total_months'          => $contractDetails['total_months'],
                                    'monthly_remuneration'  => $contractDetails['monthly_remuneration'],
                                    'premium_amount'        => $contractDetails['premium_amount'],
                                    'total_contract_amount' => $contractDetails['total_contract_amount'],
                                    'status'                => $contractStatus,
                                ]
                            );
                            $contractId = $contract->id;
                        }
                    }

                    // Calculate Total Sheet NORSA from negative obligation rows
                    $totalSheetNorsa = 0.0;
                    if (!empty($tracker['norsa_entries'])) {
                        foreach ($tracker['norsa_entries'] as $nEntry) {
                            $totalSheetNorsa += (float) ($nEntry['amount'] ?? 0);
                        }
                    }

                    // $tracker['netOb'] already equals gross obligation minus negative NORSA rows
                    $netConsolidatedObligated = ($tracker['netOb'] > 0 || $totalSheetNorsa > 0)
                        ? max(0.0, round((float) $tracker['netOb'], 2))
                        : round((float) $clickedFund->obligation_amount, 2);

                    $totalDisbursedFromRaods = round((float) $tracker['netDisb'], 2);

                    // Full deobligation (netOb == 0 after negative entry) or explicit cancellation -> Cancelled
                    $isFullyDeobligated = ($totalSheetNorsa > 0 && $netConsolidatedObligated <= 0.009);
                    $hasCancellation    = !empty($cancellationRows) || $isFullyDeobligated || strtolower($clickedFund->status) === 'cancelled';

                    if ($hasCancellation) {
                        $status = 'Cancelled';
                        $displayAmount = $netConsolidatedObligated > 0 ? $netConsolidatedObligated : (float) $clickedFund->obligation_amount;
                    } elseif ($totalDisbursedFromRaods > 0) {
                        if ($isCosSalary && !empty($contractDetails['total_months'])) {
                            if ($disbursedMonthCount < $contractDetails['total_months']) {
                                $status = 'Disbursed (Partially)';
                            } else {
                                $status = ($netConsolidatedObligated > 0 && $totalDisbursedFromRaods < $netConsolidatedObligated)
                                    ? 'Disbursed (with savings)'
                                    : 'Completed';
                            }
                        } else {
                            $status = ($netConsolidatedObligated > 0 && $totalDisbursedFromRaods < $netConsolidatedObligated)
                                ? 'Disbursed (with savings)'
                                : 'Disbursed';
                        }
                        $displayAmount = $totalDisbursedFromRaods;
                    } else {
                        $status = 'Obligated';
                        $displayAmount = $netConsolidatedObligated;
                    }

                    // --- CHECK TRUE MULTI-ACTIVITY STATUS VIA LINE ITEMS ---
                    $lineItems = \App\Models\FundMultiActivity::where('fund_id', $clickedFund->id)->get();
                    $lineItemsCount = $lineItems->count();
                    $isTrueMultiActivity = ($lineItemsCount > 1) && !$isCosSalary && !$hasCancellation;

                    $totalRoutedAmount = $lineItems->isNotEmpty()
                        ? (float) $lineItems->sum('routed_amount')
                        : ((float) ($clickedFund->amount ?? $netConsolidatedObligated));

                    $hasDiscrepancy = (abs($netConsolidatedObligated - $totalRoutedAmount) > 0.01) || ($totalSheetNorsa > 0);

                    // =====================================================================
                    // 1. INTERACTIVE MODAL ONLY FOR TRUE MULTI-ACTIVITY (> 1 ACTIVITY)
                    // =====================================================================
                    if ($isTrueMultiActivity && $hasDiscrepancy) {
                        $activitiesBreakdown = $lineItems->map(function ($item) {
                            return [
                                'id'                  => $item->id,
                                'fund_id'             => $item->fund_id,
                                'transaction_type_id' => $item->transaction_type_id,
                                'source_of_fund_id'   => $item->source_of_fund_id,
                                'activity_name'       => $item->creditor ?? ('Item #' . $item->id),
                                'routed_amount'       => (float) $item->routed_amount,
                                'obligation_amount'   => (float) $item->obligation_amount,
                            ];
                        })->values()->all();

                        return response()->json([
                            'success'                    => true,
                            'requires_manual_allocation' => true,
                            'dtrack_no'                  => $targetDtrack,
                            'obligation_serial'          => $sheetSerial ?: $clickedFund->obligation_serial,
                            'total_net_obligated'        => $netConsolidatedObligated,
                            'total_net_disbursed'        => $totalDisbursedFromRaods,
                            'total_norsa'                => $totalSheetNorsa,
                            'activities'                 => $activitiesBreakdown,
                            'tracker_data'               => $tracker,
                            'details'                    => [
                                'dtrack_no' => $targetDtrack,
                            ]
                        ]);
                    }

                    // =====================================================================
                    // 2. SINGLE-ACTIVITY OR TALLIED MULTI-ACTIVITY DIRECT SAVE
                    // =====================================================================
                    $updateData = [
                        'creditor'            => $creditorName ?: $clickedFund->creditor,
                        'obligation_serial'   => (!empty($sheetSerial)) ? $sheetSerial : $clickedFund->obligation_serial,
                        'obligation_amount'   => $netConsolidatedObligated,
                        'obligation_date'     => $tracker['latestObDate'] ?: $clickedFund->obligation_date,
                        'disbursement_amount' => $totalDisbursedFromRaods,
                        'disbursement_date'   => $tracker['latestDisbDate'] ?: $clickedFund->disbursement_date,
                        'status'              => $status,
                        'status_date'         => now(),
                    ];

                    if (!empty($matchedSheetDtrack) && $matchedSheetDtrack !== $prDtrack && empty($clickedFund->dtrack_no_new)) {
                        $updateData['dtrack_no_new'] = $matchedSheetDtrack;
                    }

                    if ($isCosSalary) {
                        if ($contractId) $updateData['cos_contract_id'] = $contractId;
                        $updateData['disbursed_months']  = $disbursedMonthCount;
                        $updateData['remarks_salary']    = 'Imported HR COS Salary/Wages';
                        $updateData['is_multi_activity'] = 0;
                    }

                    $clickedFund->update($updateData);

                    if (!empty($cancellationRows) || $isFullyDeobligated) {
                        $rowsToCancel = !empty($cancellationRows) ? $cancellationRows : $matchedRows;
                        $this->syncCancellationRecords($clickedFund, $rowsToCancel, $sourceConfig->id, $userId);
                    }

                    // Update line items if any exist (e.g. 1 child row or tallied multi-activity)
                    if ($lineItemsCount > 0 && !$isCosSalary) {
                        foreach ($lineItems as $actItem) {
                            $actRouted = (float) $actItem->routed_amount;

                            $actObligation = ($lineItemsCount === 1)
                                ? $netConsolidatedObligated
                                : (($totalRoutedAmount > 0) ? round($netConsolidatedObligated * ($actRouted / $totalRoutedAmount), 2) : 0);

                            $actDisbursement = ($lineItemsCount === 1)
                                ? $totalDisbursedFromRaods
                                : (($netConsolidatedObligated > 0) ? round($totalDisbursedFromRaods * ($actObligation / $netConsolidatedObligated), 2) : 0);

                            if ($hasCancellation) {
                                $actStatus = 'Cancelled';
                            } elseif ($actDisbursement > 0) {
                                $actStatus = ($actObligation > 0 && $actDisbursement < $actObligation)
                                    ? 'Disbursed (with savings)'
                                    : 'Disbursed';
                            } else {
                                $actStatus = 'Obligated';
                            }

                            $actItem->update([
                                'obligation_serial'   => !empty($sheetSerial) ? $sheetSerial : $actItem->obligation_serial,
                                'obligation_amount'   => $actObligation,
                                'disbursement_amount' => $actDisbursement,
                                'status'              => $actStatus,
                            ]);
                        }
                    }

                    $this->syncDisbursementRecords($clickedFund, $tracker['disbursements'], $isCosSalary);
                    $this->syncNorsaAdjustments($clickedFund, $tracker['norsa_entries'] ?? [], $sourceConfig->id);
                    $this->syncFundDtrackLogs($clickedFund, $matchedRows, $rows);

                    $syncedDetails[] = [
                        'name'   => $sourceConfig->name ?? 'Fund Source',
                        'dtrack' => $matchedSheetDtrack ?: $targetDtrack,
                        'serial' => $sheetSerial ?: $clickedFund->obligation_serial,
                        'amount' => number_format($displayAmount, 2),
                        'status' => $status
                    ];
                }
            }

            if (empty($syncedDetails)) {
                $serialOrDtrack = !empty($clickedFund->obligation_serial) ? $clickedFund->obligation_serial : $targetDtrack;
                return response()->json([
                    'success'           => false,
                    'obligation_serial' => $serialOrDtrack,
                    'message'           => 'No available data found in RAODS'
                ]);
            }

            $freshFund = $clickedFund->fresh();
            $dbSerial  = !empty($freshFund->obligation_serial) ? $freshFund->obligation_serial : ($freshFund->dtrack_no_new ?: $freshFund->dtrack_no);

            return response()->json([
                'success'           => true,
                'obligation_serial' => $dbSerial,
                'details'           => [
                    'dtrack_no'    => $targetDtrack,
                    'synced_items' => $syncedDetails,
                    'count'        => count($syncedDetails)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => "API Error: " . $e->getMessage() . " at line " . $e->getLine()], 500);
        }
    }
    
    public function bulkSync()
    {
        $userId = Auth::id() ?? 1;
        $cacheKey = "sync_progress_{$userId}";
        $cancelKey = "sync_cancel_{$userId}";

        Cache::forget($cancelKey);

        $user = Auth::user();
        $secid = null;

        if ($user && $user->username === 'admin') {
            $secid = 0;
        } elseif ($user) {
            $localDetail = \DB::table('employee_details')
                ->where('user_id', $user->id)
                ->select('dbedid')
                ->first();

            if ($localDetail && $localDetail->dbedid) {
                $secid = \DB::connection('db_common')->table('tbl_emp_details')
                    ->where('dbedid', $localDetail->dbedid)
                    ->value('secid');
            }
        }

        if (is_null($secid) && ($user->username ?? '') !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'System Error: Your account is not linked to a section.'
            ], 422);
        }

        $sourcesQuery = SourceOfFund::whereNotNull('spreadsheet_id')
            ->whereNotNull('sheet_name');

        if ($secid !== 0) {
            $sourcesQuery->where('section_id', $secid);
        }

        $sources = $sourcesQuery->get();
        $totalSources = $sources->count();

        if ($totalSources === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No configured fund sources found for your section.'
            ]);
        }

        $this->updateCache($cacheKey, 0, $totalSources);
        session_write_close();

        $service = $this->getGoogleSheetsService();

        $processedSources = 0;
        $sourceCounter = 0;
        $importedItems = [];

        $ignoredLabels = [
            'entity name', 'fund cluster', 'legal basis', 'fund source',
            'dtrack no', 'dtrack no.', 'serial number', 'obligation serial'
        ];

        foreach ($sources as $sourceConfig) {
            if (Cache::has($cancelKey)) {
                $this->finalizeProgress($cacheKey, $processedSources, $totalSources, 'cancelled');
                return response()->json(['success' => false, 'message' => 'Sync Cancelled']);
            }

            try {
                $sourceCounter++;
                if ($sourceCounter > 10) {
                    sleep(1);
                }

                $allotClass = strtoupper(trim((string) ($sourceConfig->allotment_class ?? '')));
                $sourceName = $sourceConfig->name ?? '';
                $sourceCode = $sourceConfig->code ?? '';

                $isCapitalOutlay = ($allotClass === 'CO') ||
                                (stripos($sourceName, 'Capital Outlay') !== false) ||
                                (stripos($sourceName, ' CO') !== false) ||
                                (stripos($sourceCode, 'CO') !== false);

                $range = "'{$sourceConfig->sheet_name}'!A:CQ";
                $response = $service->spreadsheets_values->get($sourceConfig->spreadsheet_id, $range);
                $rows = $response->getValues();

                if (!empty($rows)) {
                    $groupedRows = [];
                    foreach ($rows as $rowIndex => $rowData) {
                        $sheetDtrack = isset($rowData[0]) ? trim((string) $rowData[0]) : '';
                        $sheetSerial = isset($rowData[3]) ? trim((string) $rowData[3]) : '';

                        if (empty($sheetDtrack) && empty($sheetSerial)) continue;

                        $cleanSerialCheck = strtolower($sheetSerial);
                        $cleanDtrackCheck = strtolower($sheetDtrack);

                        $isHeaderOrMetadata = false;
                        foreach ($ignoredLabels as $label) {
                            if (str_contains($cleanSerialCheck, $label) || str_contains($cleanDtrackCheck, $label)) {
                                $isHeaderOrMetadata = true;
                                break;
                            }
                        }

                        if ($isHeaderOrMetadata || preg_match('/^(january|february|march|april|may|june|july|august|september|october|november|december)\s+\d{4}$/i', $sheetSerial) || preg_match('/^(january|february|march|april|may|june|july|august|september|october|november|december)\s+\d{4}$/i', $sheetDtrack)) {
                            continue;
                        }

                        $groupKey = !empty($sheetSerial) ? $sheetSerial : $sheetDtrack;
                        $groupedRows[$groupKey][] = $rowData;
                    }

                    foreach ($groupedRows as $groupKey => $groupRows) {
                        $tracker = [
                            'netOb'          => 0.0,
                            'netDisb'        => 0.0,
                            'latestObDate'   => null,
                            'latestDisbDate' => null,
                            'found_serial'   => null,
                            'found'          => true,
                            'disbursements'  => [],
                            'norsa_entries'  => [],
                        ];

                        $creditorName        = null;
                        $particulars         = '';
                        $sheetSerial         = null;
                        $sheetDtrack         = '';
                        $isCosSalary         = false;
                        $isCapitalOutlay     = false;
                        $isCancellationGroup = false;

                        foreach ($groupRows as $rowData) {
                            $sheetDtrack    = isset($rowData[0])  ? trim((string) $rowData[0])  : $sheetDtrack;
                            $sheetSerial    = isset($rowData[3])  ? trim((string) $rowData[3])  : $sheetSerial;
                            $allotment      = isset($rowData[5])  ? trim((string) $rowData[5])  : '';
                            $creditorRow    = isset($rowData[10]) ? trim((string) $rowData[10]) : null;
                            $particularsRow = isset($rowData[11]) ? trim((string) $rowData[11]) : '';

                            if (!empty($creditorRow))    $creditorName = $creditorRow;
                            if (!empty($particularsRow)) $particulars  = $particularsRow;

                            // Detect RAODS Cancellation / Deobligation rows (e.g., "CANCELLED ORS NO 02-101101-2026-09-00085...")
                            if (stripos($particularsRow, 'cancelled ors') !== false || stripos($particularsRow, 'deobligation') !== false) {
                                $isCancellationGroup = true;
                            }

                            $cleanAllotment   = strtolower(preg_replace('/\s+/', ' ', $allotment));
                            $cleanParticulars = strtolower(preg_replace('/\s+/', ' ', $particularsRow));

                            $isOtherProfServ = (stripos($cleanAllotment, 'other professional services') !== false);
                            $isWages         = (stripos($cleanParticulars, 'wages') !== false);
                            if ($isOtherProfServ && $isWages) {
                                $isCosSalary = true;
                            }

                            if ($isCosSalary) {
                                $allClass   = strtoupper(trim((string) ($sourceConfig->allotment_class ?? '')));
                                $sourceName = $sourceConfig->name ?? '';
                                $sourceCode = $sourceConfig->code ?? '';
                                $isCapitalOutlay = ($allClass === 'CO') ||
                                    (stripos($sourceName, 'Capital Outlay') !== false) ||
                                    (stripos($sourceName, ' CO') !== false) ||
                                    (stripos($sourceCode, 'CO') !== false);
                            }

                            if (!$isCancellationGroup) {
                                $this->processApiRowData($rowData, $tracker, $particularsRow, $isCosSalary);
                            }
                        }

                        if ($isCosSalary && $isCapitalOutlay) {
                            continue;
                        }

                        // Handle RAODS Cancellation / Deobligation group separately
                        if ($isCancellationGroup) {
                            $existingFund = null;

                            // Extract original ORS serial from "CANCELLED ORS NO 02-101101-2026-09-00085" if present
                            if (preg_match('/02-\d+-\d{4}-\d{2}-\d+/i', $particulars, $matches)) {
                                $origSerial = trim($matches[0]);
                                $existingFund = Fund::where('source_of_fund_id', $sourceConfig->id)
                                    ->where('obligation_serial', $origSerial)
                                    ->first();
                            }

                            if (!$existingFund && !empty($sheetDtrack)) {
                                $existingFund = Fund::where('source_of_fund_id', $sourceConfig->id)
                                    ->where(function ($q) use ($sheetDtrack) {
                                        $q->where('dtrack_no', $sheetDtrack)
                                        ->orWhere('dtrack_no_new', $sheetDtrack);
                                    })
                                    ->first();
                            }

                            if ($existingFund) {
                                $this->syncCancellationRecords($existingFund, $groupRows, $sourceConfig->id, $userId);
                                $existingFund->update([
                                    'status'      => 'Cancelled',
                                    'status_date' => now(),
                                ]);
                                \App\Models\FundMultiActivity::where('fund_id', $existingFund->id)
                                    ->update(['status' => 'Cancelled']);
                                $this->syncFundDtrackLogs($existingFund, $groupRows, $rows);
                            }

                            continue;
                        }

                        $disbursedMonthCount = count($tracker['disbursements'] ?? []);
                        $contractId = null;
                        $contractDetails = [];

                        if ($isCosSalary && !empty($particulars)) {
                            $contractDetails = $this->parseCosParticulars($particulars, $creditorName);
                            if (!empty($contractDetails['start_date']) && !empty($contractDetails['end_date'])) {
                                $totalMonths = $contractDetails['total_months'] ?? 0;
                                $contractStatus = ($disbursedMonthCount >= $totalMonths && $totalMonths > 0)
                                    ? 'Completed'
                                    : 'Active';

                                $contract = \App\Models\CosContract::firstOrCreate(
                                    [
                                        'creditor_name' => $creditorName,
                                        'start_date'    => $contractDetails['start_date'],
                                        'end_date'      => $contractDetails['end_date'],
                                    ],
                                    [
                                        'total_months'          => $totalMonths,
                                        'monthly_remuneration'  => $contractDetails['monthly_remuneration'],
                                        'premium_amount'        => $contractDetails['premium_amount'],
                                        'total_contract_amount' => $contractDetails['total_contract_amount'],
                                        'status'                => $contractStatus,
                                    ]
                                );
                                $contractId = $contract->id;
                            }
                        }

                        if ($tracker['netDisb'] > 0) {
                            if ($isCosSalary && !empty($contractDetails['total_months'])) {
                                if ($disbursedMonthCount < $contractDetails['total_months']) {
                                    $status = 'Disbursed (Partially)';
                                } else {
                                    $status = ($tracker['netOb'] > 0 && $tracker['netDisb'] < $tracker['netOb'])
                                        ? 'Disbursed (with savings)'
                                        : 'Completed';
                                }
                            } else {
                                $status = ($tracker['netOb'] > 0 && $tracker['netDisb'] < $tracker['netOb'])
                                    ? 'Disbursed (with savings)'
                                    : 'Disbursed';
                            }
                            $displayAmount = $tracker['netDisb'];
                        } else {
                            $status = 'Obligated';
                            $displayAmount = $tracker['netOb'];
                        }

                        $existingFund = null;
                        if (!empty($sheetSerial)) {
                            $existingFund = Fund::where('source_of_fund_id', $sourceConfig->id)
                                ->where('obligation_serial', $sheetSerial)
                                ->when($isCosSalary && $creditorName, function ($q) use ($creditorName) {
                                    $q->where('creditor', $creditorName);
                                })
                                ->first();
                        }

                        if (!$existingFund && !empty($sheetDtrack)) {
                            $existingFund = Fund::where('source_of_fund_id', $sourceConfig->id)
                                ->where(function ($q) use ($sheetDtrack) {
                                    $q->where('dtrack_no', $sheetDtrack)
                                    ->orWhere('dtrack_no_new', $sheetDtrack);
                                })
                                ->when($isCosSalary && $creditorName, function ($q) use ($creditorName) {
                                    $q->where('creditor', $creditorName);
                                })
                                ->first();
                        }

                        // Check Procurement PO DTrack (procurement_transaction_items -> transaction() / procurement_transactions)
                        if (!$existingFund && !empty($sheetDtrack)) {
                            $parentFunds = $this->syncProcurementPoFromRaods(
                                $sheetDtrack,
                                $tracker,
                                $sheetSerial,
                                $creditorName,
                                $status
                            );

                            if (!empty($parentFunds)) {
                                $matchingBulkRows = [];
                                foreach ($rows as $rData) {
                                    $rDtrack = isset($rData[0]) ? trim((string) $rData[0]) : '';
                                    $rSerial = isset($rData[3]) ? trim((string) $rData[3]) : '';

                                    if (($rDtrack === $sheetDtrack && !empty($sheetDtrack)) || (!empty($sheetSerial) && $rSerial === $sheetSerial)) {
                                        $matchingBulkRows[] = $rData;
                                    }
                                }

                                foreach ($parentFunds as $parentFund) {
                                    $this->syncDisbursementRecords($parentFund, $tracker['disbursements'], false);
                                    $this->syncNorsaAdjustments($parentFund, $tracker['norsa_entries'] ?? [], $sourceConfig->id);

                                    if (!empty($matchingBulkRows)) {
                                        $this->syncFundDtrackLogs($parentFund, $matchingBulkRows, $rows);
                                    }
                                }

                                continue;
                            }
                        }

                        if ($existingFund) {
                            // Do NOT let an old obligated row overwrite a Cancelled transaction back to Obligated
                            if (strtolower($existingFund->status) === 'cancelled') {
                                continue;
                            }

                            $isFinishedStatus = in_array($existingFund->status, [
                                'Disbursed', 'Disbursed (with savings)', 'Disbursed (Partially)', 'Completed'
                            ]);

                            $hasExistingLogs = \App\Models\FundDtrackLog::where('fund_id', $existingFund->id)->exists();

                            if ($isFinishedStatus && $hasExistingLogs) {
                                continue;
                            }
                        }

                        $targetFund = null;

                        if (!$existingFund && $isCosSalary) {
                            $targetFund = Fund::create([
                                'dtrack_no'           => !empty($sheetDtrack) ? $sheetDtrack : 'HR-COS-SALARY',
                                'obligation_serial'   => !empty($sheetSerial) ? $sheetSerial : null,
                                'creditor'            => $creditorName,
                                'particulars'         => !empty($particulars) ? $particulars : 'Imported HR COS Salary/Wages',
                                'transaction_date'    => $tracker['latestObDate'] ?? now()->format('Y-m-d'),
                                'amount'              => ($tracker['netOb'] > 0) ? $tracker['netOb'] : 0.00,
                                'user_id'             => $userId,
                                'source_of_fund_id'   => $sourceConfig->id,
                                'obligation_amount'   => $tracker['netOb'],
                                'obligation_date'     => $tracker['latestObDate'],
                                'disbursement_amount' => $tracker['netDisb'],
                                'disbursement_date'   => $tracker['latestDisbDate'],
                                'status'              => $status,
                                'status_date'         => now(),
                                'remarks'             => 'Imported HR COS Salary/Wages',
                                'remarks_salary'      => 'Imported HR COS Salary/Wages',
                                'cos_contract_id'     => $contractId,
                                'disbursed_months'    => $disbursedMonthCount,
                                'secid'               => $secid,
                            ]);

                            $importedItems[] = [
                                'id'         => $targetFund->id,
                                'serial'     => !empty($targetFund->obligation_serial) ? $targetFund->obligation_serial : $targetFund->dtrack_no,
                                'status'     => $status,
                                'amount'     => number_format($displayAmount, 2),
                                'duplicates' => []
                            ];
                        } elseif ($existingFund) {
                            $updateData = [
                                'creditor'            => $creditorName ?: $existingFund->creditor,
                                'obligation_serial'   => (!empty($sheetSerial)) ? $sheetSerial : $existingFund->obligation_serial,
                                'obligation_amount'   => $tracker['netOb'],
                                'obligation_date'     => $tracker['latestObDate'] ?: $existingFund->obligation_date,
                                'disbursement_amount' => $tracker['netDisb'],
                                'disbursement_date'   => $tracker['latestDisbDate'] ?: $existingFund->disbursement_date,
                                'disbursed_months'    => $disbursedMonthCount,
                                'status'              => $status,
                                'status_date'         => now(),
                                'secid'               => $existingFund->secid ?? $secid
                            ];

                            if ($isCosSalary) {
                                if ($contractId) $updateData['cos_contract_id'] = $contractId;
                                $updateData['remarks_salary'] = 'Imported HR COS Salary/Wages';
                            }

                            $existingFund->update($updateData);
                            $targetFund = $existingFund;
                        }

                        // SYNC DISBURSEMENTS, NORSA, AND LOGS
                        if ($targetFund) {
                            $this->syncDisbursementRecords($targetFund, $tracker['disbursements'], $isCosSalary);
                            $this->syncNorsaAdjustments($targetFund, $tracker['norsa_entries'] ?? [], $sourceConfig->id);

                            $matchingBulkRows = [];
                            foreach ($rows as $rData) {
                                $rDtrack = isset($rData[0]) ? trim((string) $rData[0]) : '';
                                $rSerial = isset($rData[3]) ? trim((string) $rData[3]) : '';

                                if (($rDtrack === $sheetDtrack && !empty($sheetDtrack)) || (!empty($sheetSerial) && $rSerial === $sheetSerial)) {
                                    $matchingBulkRows[] = $rData;
                                }
                            }

                            if (!empty($matchingBulkRows)) {
                                $this->syncFundDtrackLogs($targetFund, $matchingBulkRows, $rows);
                            }
                        }
                    }
                }

                unset($rows);

            } catch (\Exception $e) {
                logger()->error("Bulk Sync Error (Source {$sourceConfig->id}): " . $e->getMessage());
            }

            $processedSources++;
            $this->updateCache($cacheKey, $processedSources, $totalSources);
        }

        $this->finalizeProgress($cacheKey, $totalSources, $totalSources, 'completed');

        return response()->json([
            'success'        => true,
            'imported_items' => $importedItems
        ]);
    }

    /**
     * Helper: Sync matched RAODS PO DTrack into all linked ProcurementTransactionItems -> ProcurementTransactions -> Funds
     */
    private function syncProcurementPoFromRaods(string $poDtrack, array $tracker, ?string $sheetSerial, ?string $creditorName, string $status): array
    {
        $matchedItems = \App\Models\ProcurementTransactionItem::with('transaction')
            ->where('po_dtrack_no', $poDtrack)
            ->get();

        $affectedProcTransactions = collect();

        if ($matchedItems->isNotEmpty()) {
            $totalGroupPoAmount = (float) $matchedItems->sum('po_amount');
            $fallbackTotal      = (float) $matchedItems->sum('item_amount');

            foreach ($matchedItems as $item) {
                $weight = $totalGroupPoAmount > 0
                    ? ((float) $item->po_amount / $totalGroupPoAmount)
                    : ($fallbackTotal > 0 ? ((float) $item->item_amount / $fallbackTotal) : (1 / $matchedItems->count()));

                $item->update([
                    'obligation_serial'   => !empty($sheetSerial) ? $sheetSerial : $item->obligation_serial,
                    'obligation_amount'   => round($tracker['netOb'] * $weight, 2),
                    'obligation_date'     => $tracker['latestObDate'] ?: $item->obligation_date,
                    'disbursement_amount' => round($tracker['netDisb'] * $weight, 2),
                    'disbursement_date'   => $tracker['latestDisbDate'] ?: $item->disbursement_date,
                    'status'              => $status,
                ]);

                if ($item->transaction) {
                    $affectedProcTransactions->put($item->transaction->id, $item->transaction);
                }
            }
        } else {
            $directProcTransactions = \App\Models\ProcurementTransaction::where('po_dtrack_no', $poDtrack)->get();
            if ($directProcTransactions->isEmpty()) {
                return [];
            }

            $totalDirectPo = (float) $directProcTransactions->sum('po_amount');

            foreach ($directProcTransactions as $procTx) {
                $weight = $totalDirectPo > 0
                    ? ((float) $procTx->po_amount / $totalDirectPo)
                    : (1 / $directProcTransactions->count());

                $procTx->update([
                    'obligation_serial'   => !empty($sheetSerial) ? $sheetSerial : $procTx->obligation_serial,
                    'obligation_amount'   => round($tracker['netOb'] * $weight, 2),
                    'obligation_date'     => $tracker['latestObDate'] ?: $procTx->obligation_date,
                    'disbursement_amount' => round($tracker['netDisb'] * $weight, 2),
                    'disbursement_date'   => $tracker['latestDisbDate'] ?: $procTx->disbursement_date,
                    'status'              => $status,
                ]);

                $affectedProcTransactions->put($procTx->id, $procTx);
            }
        }

        if ($affectedProcTransactions->isEmpty()) {
            return [];
        }

        $updatedFunds = [];

        foreach ($affectedProcTransactions as $procTransaction) {
            // Roll up all items under this specific procurement_transaction
            $allItems = \App\Models\ProcurementTransactionItem::where('procurement_transaction_id', $procTransaction->id)->get();

            if ($allItems->isNotEmpty()) {
                $totalObligated = (float) $allItems->sum('obligation_amount');
                $totalDisbursed = (float) $allItems->sum('disbursement_amount');
                $uniqueSerials  = $allItems->pluck('obligation_serial')->filter()->unique()->values()->all();
                $latestObDate   = $allItems->pluck('obligation_date')->filter()->max();
                $latestDisbDate = $allItems->pluck('disbursement_date')->filter()->max();

                $totalItemCount     = $allItems->count();
                $obligatedItemCount = $allItems->where('obligation_amount', '>', 0)->count();

                if ($totalDisbursed > 0) {
                    $rolledStatus = ($totalObligated > 0 && $totalDisbursed < $totalObligated)
                        ? 'Disbursed (with savings)'
                        : 'Disbursed';
                } elseif ($obligatedItemCount > 0 && $obligatedItemCount < $totalItemCount) {
                    $rolledStatus = 'Partially Obligated';
                } elseif ($obligatedItemCount > 0) {
                    $rolledStatus = 'Obligated';
                } else {
                    $rolledStatus = $procTransaction->status;
                }

                $procTransaction->update([
                    'obligation_serial'   => !empty($uniqueSerials) ? implode(', ', $uniqueSerials) : $procTransaction->obligation_serial,
                    'obligation_amount'   => $totalObligated,
                    'obligation_date'     => $latestObDate ?: $procTransaction->obligation_date,
                    'disbursement_amount' => $totalDisbursed,
                    'disbursement_date'   => $latestDisbDate ?: $procTransaction->disbursement_date,
                    'status'              => $rolledStatus,
                ]);
            } else {
                $totalObligated = (float) $procTransaction->obligation_amount;
                $totalDisbursed = (float) $procTransaction->disbursement_amount;
                $uniqueSerials  = array_filter([$procTransaction->obligation_serial]);
                $latestObDate   = $procTransaction->obligation_date;
                $latestDisbDate = $procTransaction->disbursement_date;
                $rolledStatus   = $procTransaction->status;
            }

            // Roll up to master `funds` row (`fund_transaction_id`)
            $parentFund = Fund::find($procTransaction->fund_transaction_id);
            if ($parentFund && strtolower($parentFund->status) !== 'cancelled') {
                $distinctPoDtracks = $allItems->isNotEmpty()
                    ? $allItems->pluck('po_dtrack_no')->filter()->unique()->values()
                    : collect(array_filter([$procTransaction->po_dtrack_no]));

                $parentFund->update([
                    'dtrack_no_new'       => $distinctPoDtracks->count() === 1 ? $distinctPoDtracks->first() : $parentFund->dtrack_no_new,
                    'creditor'            => $creditorName ?: $parentFund->creditor,
                    'obligation_serial'   => !empty($uniqueSerials) ? implode(', ', $uniqueSerials) : $parentFund->obligation_serial,
                    'obligation_amount'   => $totalObligated,
                    'obligation_date'     => $latestObDate ?: $parentFund->obligation_date,
                    'disbursement_amount' => $totalDisbursed,
                    'disbursement_date'   => $latestDisbDate ?: $parentFund->disbursement_date,
                    'status'              => $rolledStatus,
                    'status_date'         => now(),
                ]);

                $updatedFunds[$parentFund->id] = $parentFund;
            }
        }

        return array_values($updatedFunds);
    }

    /**
     * Helper: Automatically capture RAODS Deobligation / Cancelled ORS entries into fund_cancellations
     */
    private function syncCancellationRecords(Fund $fund, array $cancellationRows, int $sourceOfFundId, int $userId): void
    {
        foreach ($cancellationRows as $rowData) {
            $cancelSerial   = isset($rowData[3])  ? trim((string) $rowData[3])  : '';
            $rawCancelDate  = isset($rowData[2])  ? trim((string) $rowData[2])  : '';
            $rawCancelAmt   = isset($rowData[8])  ? trim((string) $rowData[8])  : (isset($rowData[6]) ? trim((string) $rowData[6]) : '0');
            $particularsRow = isset($rowData[11]) ? trim((string) $rowData[11]) : '';

            if (empty($cancelSerial)) continue;

            // Parse RAODS amount (handles "(27,000.00)" or "-27000.00") as a positive deobligated amount
            $cleanAmt = preg_replace('/[^0-9.\-]/', '', str_replace(['(', ')'], ['-', ''], $rawCancelAmt));
            $cancelledAmount = abs((float) $cleanAmt);

            if ($cancelledAmount <= 0) {
                $cancelledAmount = (float) ($fund->obligation_amount > 0 ? $fund->obligation_amount : $fund->amount);
            }

            $cancelDate = now()->format('Y-m-d');
            if (!empty($rawCancelDate)) {
                try {
                    $cancelDate = \Carbon\Carbon::parse($rawCancelDate)->format('Y-m-d');
                } catch (\Exception $e) {
                    $cancelDate = now()->format('Y-m-d');
                }
            }

            \App\Models\FundCancellation::updateOrCreate(
                [
                    'fund_id'                        => $fund->id,
                    'cancellation_obligation_serial' => $cancelSerial,
                ],
                [
                    'source_of_fund_id'          => $sourceOfFundId,
                    'dtrack_no'                  => $fund->dtrack_no,
                    'original_obligation_serial' => $fund->obligation_serial,
                    'cancellation_date'          => $cancelDate,
                    'cancelled_amount'           => $cancelledAmount,
                    'reason'                     => $particularsRow ?: $fund->remarks,
                    'cancelled_by'               => $userId,
                ]
            );
        }
    }

    /**
     * Parses Google Sheet columns, accumulates positive obligations & disbursements, 
     * and routes negative obligation values into norsa_entries.
     */
    private function processApiRowData($rowData, &$tracker, $particulars = '', $isCosSalary = false) {
        $clean = function($val) {
            if (is_null($val) || $val === '') return 0.0;
            $raw = str_replace([',', '₱', ' '], '', trim((string)$val));
            if (str_starts_with($raw, '(')) $raw = '-' . trim($raw, '()');
            return is_numeric($raw) ? (float)$raw : 0.0;
        };

        if (!isset($tracker['norsa_entries'])) {
            $tracker['norsa_entries'] = [];
        }

        // Obligation Amount (Column J = Index 9)
        $obVal = isset($rowData[9]) ? $clean($rowData[9]) : 0.0;
        $entryDate = (isset($rowData[2]) && !empty($rowData[2])) ? $this->parseApiDate($rowData[2]) : null;

        // --- SEPARATE NORSA SAVINGS (NEGATIVE OBLIGATIONS) FROM POSITIVE OBLIGATIONS ---
        if ($obVal < 0) {
            $tracker['norsa_entries'][] = [
                'amount'            => abs($obVal), // Stored as positive savings magnitude
                'raw_amount'        => $obVal,
                'entry_date'        => $entryDate,
                'dtrack_no'         => isset($rowData[0]) ? trim((string)$rowData[0]) : null,
                'obligation_serial' => isset($rowData[3]) ? trim((string)$rowData[3]) : null,
                'creditor'          => isset($rowData[10]) ? trim((string)$rowData[10]) : null,
                'particulars'       => $particulars,
            ];
        } else {
            $tracker['netOb'] += $obVal;
            if ($entryDate) {
                $tracker['latestObDate'] = $entryDate;
            }
        }

        // EXACT DISBURSEMENT COLUMN PAIRINGS: [ Total Column Index => Date Column Index ]
        $disbursementMap = [
            17 => 13, // Jan (R => N)
            24 => 20, // Feb (Y => U)
            31 => 27, // Mar (AF => AB)
            38 => 34, // Apr (AM => AI)
            45 => 41, // May (AT => AP)
            52 => 48, // June (BA => AW)
            59 => 55, // July (BH => BD)
            66 => 62, // Aug (BO => BK)
            73 => 69, // Sept (BV => BR)
            80 => 76, // Oct (CC => BY)
            87 => 83, // Nov (CJ => CF)
            94 => 90, // Dec (CQ => CM)
        ];

        if (!isset($tracker['disbursements']) || !is_array($tracker['disbursements'])) {
            $tracker['disbursements'] = [];
        }

        foreach ($disbursementMap as $tIdx => $dIdx) {
            if (isset($rowData[$tIdx])) {
                $val = $clean($rowData[$tIdx]);
                
                if ($val != 0) {
                    $tracker['netDisb'] += $val;
                    
                    $columnSpecificDate = null;
                    if (isset($rowData[$dIdx]) && !empty($rowData[$dIdx])) {
                        $rawDateVal = trim((string)$rowData[$dIdx]);
                        
                        // FIX: Handle combined/multi-date cells (e.g., "3/12/2026 & 3/17/2026")
                        if (preg_match('/[&,]|and/i', $rawDateVal)) {
                            $parts = preg_split('/[&,]|\sand\s/i', $rawDateVal);
                            $rawDateVal = trim($parts[0]); // Take the primary/first date reference
                        }

                        $columnSpecificDate = $this->parseApiDate($rawDateVal);
                    }

                    $existingIndex = null;
                    foreach ($tracker['disbursements'] as $key => $existing) {
                        if ($existing['column_index'] === $tIdx) {
                            $existingIndex = $key;
                            break;
                        }
                    }

                    if ($existingIndex !== null) {
                        $tracker['disbursements'][$existingIndex]['amount'] += $val;
                        if ($columnSpecificDate) {
                            $tracker['disbursements'][$existingIndex]['disbursement_date'] = $columnSpecificDate;
                        }
                    } else {
                        $tracker['disbursements'][] = [
                            'amount'            => $val,
                            'disbursement_date' => $columnSpecificDate,
                            'column_index'      => $tIdx,
                        ];
                    }

                    if ($columnSpecificDate) {
                        $tracker['latestDisbDate'] = $columnSpecificDate;
                    }
                }
            }
        }
    }

    private function syncFundDtrackLogs(Fund $fund, array $matchedRows, array $allSheetRows = [])
    {
        $processedIds = [];

        // Count occurrences of each dtrack_no across the sheet if provided
        $dtrackCounts = [];
        if (!empty($allSheetRows)) {
            foreach ($allSheetRows as $r) {
                $dt = isset($r[0]) ? trim((string)$r[0]) : '';
                if (!empty($dt)) {
                    $dtrackCounts[$dt] = ($dtrackCounts[$dt] ?? 0) + 1;
                }
            }
        }

        foreach ($matchedRows as $rowData) {
            $dtrackNo = isset($rowData[0]) ? trim((string)$rowData[0]) : '';
            if (empty($dtrackNo)) continue;

            // RULE: Only save to fund_dtrack_logs if this dtrack_no is duplicated (appears > 1 time) in the sheet
            $isDuplicated = isset($dtrackCounts[$dtrackNo]) && $dtrackCounts[$dtrackNo] > 1;

            if (!$isDuplicated) {
                // Skip logging if it's not a duplicate DTrack
                continue;
            }

            $clean = function($val) {
                if (is_null($val) || $val === '') return 0.0;
                $raw = str_replace([',', '₱', ' '], '', trim((string)$val));
                if (str_starts_with($raw, '(')) $raw = '-' . trim($raw, '()');
                return is_numeric($raw) ? (float)$raw : 0.0;
            };

            $rowObAmount = isset($rowData[9]) ? $clean($rowData[9]) : 0.0;
            $rowObDate = isset($rowData[2]) && !empty($rowData[2]) ? $this->parseApiDate($rowData[2]) : null;
            $serial = isset($rowData[3]) ? trim((string)$rowData[3]) : null;

            $rowDisbAmount = 0.0;
            $rowDisbDate = null;
            $disbursementMap = [17=>13, 24=>20, 31=>27, 38=>34, 45=>41, 52=>48, 59=>55, 66=>62, 73=>69, 80=>76, 87=>83, 94=>90];
            
            foreach ($disbursementMap as $tIdx => $dIdx) {
                if (isset($rowData[$tIdx])) {
                    $dVal = $clean($rowData[$tIdx]);
                    if ($dVal != 0) {
                        $rowDisbAmount += $dVal;
                        if (isset($rowData[$dIdx]) && !empty($rowData[$dIdx])) {
                            $rowDisbDate = $this->parseApiDate(trim((string)$rowData[$dIdx]));
                        }
                    }
                }
            }

            $log = \App\Models\FundDtrackLog::where('fund_id', $fund->id)
                ->where('dtrack_no', $dtrackNo)
                ->where('obligation_serial', $serial)
                ->where('obligation_amount', $rowObAmount)
                ->where('obligation_date', $rowObDate)
                ->where('disbursement_amount', $rowDisbAmount)
                ->where('disbursement_date', $rowDisbDate)
                ->first();

            if ($log) {
                $processedIds[] = $log->id;
            } else {
                $newLog = \App\Models\FundDtrackLog::create([
                    'fund_id'             => $fund->id,
                    'dtrack_no'           => $dtrackNo,
                    'obligation_serial'   => $serial,
                    'obligation_amount'   => $rowObAmount,
                    'obligation_date'     => $rowObDate,
                    'disbursement_amount' => $rowDisbAmount,
                    'disbursement_date'   => $rowDisbDate,
                ]);

                $processedIds[] = $newLog->id;
            }
        }

        // Clean up logs that are no longer present or no longer duplicated
        if (!empty($processedIds)) {
            \App\Models\FundDtrackLog::where('fund_id', $fund->id)
                ->whereNotIn('id', $processedIds)
                ->delete();
        } else {
            // If none of the matched rows for this fund are duplicates, clear out old logs for this fund
            \App\Models\FundDtrackLog::where('fund_id', $fund->id)->delete();
        }
    }

    /**
     * Persists NORSA / negative obligation adjustments into the norsa_adjustments table.
     */
    private function syncNorsaAdjustments(Fund $fund, array $norsaEntries, $sourceConfigId)
    {
        $multiActivities = \App\Models\FundMultiActivity::where('fund_id', $fund->id)->get();

        // Only evaluate per-line-item discrepancy if there are genuinely multiple (> 1) activities
        if ($fund->is_multi_activity && $multiActivities->count() > 1) {
            foreach ($multiActivities as $multi) {
                $difference = round((float) $multi->routed_amount - (float) $multi->obligation_amount, 2);

                if ($difference > 0) {
                    \App\Models\NorsaAdjustment::updateOrCreate(
                        [
                            'fund_id'             => $fund->id,
                            'transaction_type_id' => $multi->transaction_type_id,
                        ],
                        [
                            'dtrack_no'           => $fund->dtrack_no,
                            'obligation_serial'   => $multi->obligation_serial ?: $fund->obligation_serial,
                            'creditor'            => $multi->creditor ?: $fund->creditor,
                            'particulars'         => $multi->particulars ?: $fund->particulars,
                            'entry_date'          => $fund->transaction_date ?? now()->format('Y-m-d'),
                            'amount'              => $difference,
                            'source_of_fund_id'   => $multi->source_of_fund_id ?? $sourceConfigId,
                        ]
                    );
                } else {
                    \App\Models\NorsaAdjustment::where('fund_id', $fund->id)
                        ->where('transaction_type_id', $multi->transaction_type_id)
                        ->delete();
                }
            }
        } else {
            // Single activity transaction logic (1 activity)
            if (empty($norsaEntries)) {
                return;
            }

            $singleMulti = $multiActivities->first();
            $txTypeId    = $singleMulti->transaction_type_id ?? $fund->transaction_type_id;

            foreach ($norsaEntries as $norsa) {
                $serial    = !empty($norsa['obligation_serial']) ? $norsa['obligation_serial'] : $fund->obligation_serial;
                $entryDate = $norsa['entry_date'] ?? $norsa['norsa_date'] ?? now()->format('Y-m-d');

                \App\Models\NorsaAdjustment::updateOrCreate(
                    [
                        'fund_id'             => $fund->id,
                        'transaction_type_id' => $txTypeId,
                        'obligation_serial'   => $serial,
                        'entry_date'          => $entryDate,
                    ],
                    [
                        'dtrack_no'           => !empty($norsa['dtrack_no']) ? $norsa['dtrack_no'] : $fund->dtrack_no,
                        'creditor'            => !empty($norsa['creditor']) ? $norsa['creditor'] : $fund->creditor,
                        'particulars'         => !empty($norsa['particulars']) ? $norsa['particulars'] : $fund->particulars,
                        'amount'              => abs((float) ($norsa['amount'] ?? 0)),
                        'source_of_fund_id'   => $sourceConfigId,
                    ]
                );
            }
        }
    }

    /**
     * Refreshes and persists individual COS salary disbursements into the database.
     */
    private function syncDisbursementRecords(Fund $fund, array $disbursements, bool $isCosSalary = false)
    {
        // STRICT GUARD: Skip saving if this fund is NOT a COS Salary record or has no disbursements
        if (!$isCosSalary || empty($disbursements)) {
            return;
        }

        foreach ($disbursements as $item) {
            \App\Models\CosSalaryDisbursement::updateOrCreate(
                [
                    'fund_id'      => $fund->id,
                    'column_index' => $item['column_index'],
                ],
                [
                    'amount'            => $item['amount'],
                    'disbursement_date' => $item['disbursement_date'] ?: null, 
                ]
            );
        }
    }

    /**
     * Helper to safely parse dates coming from Google Sheets into Y-m-d format.
     */
    private function parseApiDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            $trimmed = trim((string)$dateString);

            // Handle Excel / Google Sheets serial numeric dates (e.g., 45123)
            if (is_numeric($trimmed)) {
                return \Carbon\Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($trimmed)
                )->format('Y-m-d');
            }

            // Parse standard date strings (e.g., "05/26/2026", "2026-05-26", "26-May-2026")
            return \Carbon\Carbon::parse($trimmed)->format('Y-m-d');
        } catch (\Exception $e) {
            // Fallback if parsing fails
            return null;
        }
    }

    //for getting of the COS Salary wages from the RAODS Particulars column
    private function parseCosParticulars($particulars, $creditorName = null)
    {
        $datePattern = '/period\s+of\s+([A-Za-z]+\.?\s+\d{1,2},\s+\d{4})\s+to\s+([A-Za-z]+\.?\s+\d{1,2},\s+\d{4})/i';
        $ratePattern = '/renumeration\s+of\s+([\d,]+(?:\.\d{2})?)(?:.*?premium\s+of\s+([\d,]+(?:\.\d{2})?))?/i';

        $startDate = null;
        $endDate = null;
        $months = 0;
        $remuneration = 0.00;
        $premium = 0.00;

        if (preg_match($datePattern, $particulars, $matches)) {
            try {
                $startDate = \Carbon\Carbon::parse(trim($matches[1]))->format('Y-m-d');
                $endDate   = \Carbon\Carbon::parse(trim($matches[2]))->format('Y-m-d');
                $months    = \Carbon\Carbon::parse($startDate)->diffInMonths(\Carbon\Carbon::parse($endDate)) + 1;
            } catch (\Exception $e) {}
        }

        if (preg_match($ratePattern, $particulars, $matches)) {
            $remuneration = (float) str_replace(',', '', $matches[1] ?? 0);
            $premium      = (float) str_replace(',', '', $matches[2] ?? 0);
        }

        return [
            'creditor_name'         => $creditorName,
            'start_date'            => $startDate,
            'end_date'              => $endDate,
            'total_months'          => (int) $months,
            'monthly_remuneration'  => $remuneration,
            'premium_amount'        => $premium,
            'total_contract_amount' => ($remuneration + $premium) * $months,
        ];
    }

    /**
     * Helper to update progress cache
     */
    private function updateCache($key, $current, $total) {
        $percent = round(($current / $total) * 100);
        Cache::put($key, [
            'current' => $current,
            'total' => $total,
            'percent' => $percent,
            'status' => 'processing'
        ], 600);
    }

    private function finalizeProgress($key, $current, $total, $status) {
        Cache::put($key, [
            'current' => $current,
            'total' => $total,
            'percent' => round(($current / $total) * 100),
            'status' => $status
        ], 600);
    }

    public function cancelSync()
    {
        // Set the cancel signal for the specific user
        Cache::put("sync_cancel_" . Auth::id(), true, 60);
        return response()->json(['success' => true, 'message' => 'Cancellation signal sent.']);
    }

    public function getSyncProgress()
    {
        $userId = Auth::id();
        $data = Cache::get("sync_progress_{$userId}");
        
        // If no data exists, the sync hasn't started or cache expired
        if (!$data) {
            return response()->json(['percent' => 0, 'status' => 'idle']);
        }
        
        return response()->json($data);
    }

    public function destroy($id)
    {
        try {
            // 1. Find the target record first to get its DTrack Number
            $targetFund = \App\Models\Fund::findOrFail($id);
            $dtrackNo = $targetFund->dtrack_no;

            // 2. Security check: Ensure EVERY row in this group is still "Routed"
            // We check the whole group because we don't want to delete a transaction
            // if one part of it has already been Obligated or Disbursed.
            $group = \App\Models\Fund::where('dtrack_no', $dtrackNo)->get();
            
            foreach ($group as $item) {
                if ($item->status !== 'Routed') {
                    return response()->json([
                        'success' => false,
                        'message' => "Transaction $dtrackNo cannot be deleted because one or more allocations are already {$item->status}."
                    ], 403);
                }
            }

            // 3. Perform a Bulk Delete
            // This ensures all fund sources linked to this DTrack are removed at once.
            \DB::transaction(function () use ($dtrackNo) {
                \App\Models\Fund::where('dtrack_no', $dtrackNo)->forceDelete();
            });

            return response()->json([
                'success' => true,
                'message' => "Transaction $dtrackNo and all its associated fund sources have been permanently deleted."
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting: ' . $e->getMessage()
            ], 500);
        }
    }

    public function syncAllDTrack()
    {
        set_time_limit(300);
        try {
            $funds = Fund::where('status', '!=', 'Disbursed')->get();
            $dtrackService = new \App\Services\DTrackService();
            $updatedData = [];

            foreach ($funds as $fund) {
                // Skip if there is no DTrack number to search for
                if (!$fund->dtrack_no) continue;

                $externalData = $dtrackService->getDTrackStatus($fund->dtrack_no);
               
                $logs = $externalData['doc_register_destination'] ?? [];

                // TARGET THE LATEST ENTRY: DTrack logs are typically in chronological order, so we take the last one as the most recent status.
                $latestLog = !empty($logs) ? end($logs) : null;

                if ($latestLog) {
                    $dtrackStatus = $latestLog['actreq_desc'] ?? '';
                    $office = $latestLog['dest_office'] ?? 'Unknown Office';
                    $dtrackUpdateDate = $latestLog['docdet_rlsd_dateupdated'] ?? null;

                    // Apply Logic
                    if ($fund->status === 'Obligated') {
                        $fund->remarks = "Currently at: {$office} ({$dtrackStatus})";
                    } else {
                        // Update status based on DTrack movement
                        $fund->status = $this->mapStatus($dtrackStatus); 
                        $fund->remarks = "Currently at: {$office}";
                    }

                    if ($dtrackUpdateDate) {
                        $fund->dtrack_update_date = \Carbon\Carbon::parse($dtrackUpdateDate);
                    }

                    $fund->status_date = now();
                    
                    // 3. Save triggers the $touches in your Model, 
                    // which updates the Dashboard's "Last Updated" timestamp!
                    $fund->save();

                    $updatedData[] = [
                        'id' => $fund->id,
                        'status' => $fund->status,
                        'remarks' => $fund->remarks,
                        'doc_update' => $fund->dtrack_update_date ? $fund->dtrack_update_date->format('M d, Y') : 'N/A'
                    ];
                }
            }

            return response()->json(['success' => true, 'data' => $updatedData]);

        } catch (\Exception $e) {
            \Log::error("AJAX Sync Error: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function mapStatus($dtrackStatus) {
        return match ($dtrackStatus) {
            'For Signature' => 'For Signature',
            'For CAF/Obligation (Budget)' => 'For CAF/Obligation',
            default => $dtrackStatus,
        };
    }

    public function updateTransactionType(Request $request, $id)
    {
        $user = auth()->user();

        $isAdminOrBudget = \Illuminate\Support\Facades\Gate::allows('budget-section');
        $isDivision       = \Illuminate\Support\Facades\Gate::allows('division-access');

        // 1. Determine allowed section IDs
        $allowedSectionIds = [];

        if (!$isAdminOrBudget) {
            if ($isDivision) {
                $allowedSectionIds = $user->getDivisionSectionIds();
            } else {
                $localDetail = \DB::table('employee_details')->where('user_id', $user->id)->first();
                if ($localDetail) {
                    $userSecId = \DB::connection('db_common')
                        ->table('tbl_emp_details')
                        ->where('dbedid', $localDetail->dbedid)
                        ->value('secid');

                    if ($userSecId) {
                        $allowedSectionIds = [$userSecId];
                    }
                }
            }
        }

        // 2. Validate activity ID & scope
        $request->validate([
            'transaction_type_id' => [
                'required',
                'exists:activities,id',
                function ($attribute, $value, $fail) use ($isAdminOrBudget, $allowedSectionIds) {
                    if ($isAdminOrBudget) {
                        return; // Superadmin / Budget users bypass section check
                    }

                    if (empty($allowedSectionIds)) {
                        $fail('Your account is not assigned to any valid section.');
                        return;
                    }

                    $isValidSectionActivity = \DB::table('activities')
                        ->where('id', $value)
                        ->whereIn('section_id', $allowedSectionIds)
                        ->exists();

                    if (!$isValidSectionActivity) {
                        $fail('The selected activity does not belong to your assigned section.');
                    }
                }
            ]
        ]);

        try {
            $fund = Fund::findOrFail($id);
            
            $fund->update([
                'transaction_type_id' => $request->transaction_type_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'WFP Activity successfully assigned!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign activity: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateManualRemarks(Request $request, $id)
    {
        $request->validate([
            'manual_remarks' => 'nullable|string|max:1000',
        ]);

        try {
            $fund = Fund::findOrFail($id);
            $fund->update([
                'manual_remarks' => $request->manual_remarks,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Manual remark updated successfully!',
                'manual_remarks' => $fund->manual_remarks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update manual remark: ' . $e->getMessage()
            ], 500);
        }
    }

}