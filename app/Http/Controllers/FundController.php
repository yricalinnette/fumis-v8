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

        // --- 3. GROUP AND MAP (WITH MULTI-ACTIVITY & NORSA INTEGRATION) ---
        $funds = $fundsCollection->groupBy(function ($item) {
            if ($item->remarks_salary === 'Imported HR COS Salary/Wages') {
                return 'COS_SALARY_' . $item->id;
            }
            return $item->dtrack_no;
        })->map(function ($group) use ($employees) {
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
            
            // 1. Check if lineItems exist on parent records
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

            // 2. Fallback to direct activity relation on grouped funds
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
                'breakdown' => $group->map(function($item) {
                    $norsaAmount = $item->norsaAdjustments ? (float)$item->norsaAdjustments->sum('amount') : 0.00;
                    $netObligationAmount = max(0.00, (float)$item->obligation_amount - $norsaAmount);

                    // Fetch all procurement transaction records linked to this fund item
                    $procurements = \DB::table('procurement_transactions')
                        ->where('fund_transaction_id', $item->id)
                        ->get();

                    $totalProcurements = $procurements->count();
                    $poCount = $procurements->where('caf_status', 'Tagged as PO')->count();
                    $cafCount = $procurements->where('caf_status', 'Tagged as CAF')->count();

                    // Determine partial vs full status
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
                        // Procurement tracking additions:
                        'procurements'          => $procurements,
                        'procurement_status'    => $procurementStatus,
                        'po_numbers'            => $procurements->pluck('po_number')->filter()->unique()->implode(', '),
                        'po_dtrack_nos'         => $procurements->pluck('dtrack_no')->filter()->unique()->implode(', '),
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

        // VALIDATION
        $validated = $request->validate([
            'dtrack_no'                 => ['required', 'regex:/^\d{4}-\d{6}$/'],
            'transaction_date'          => 'required|date',
            'particulars'               => 'required|string',
            'creditor_ids'              => 'nullable|array',
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

                // Primary source ID and activity ID from the first allocation item
                $primarySourceId   = $request->allocations[0]['source_id'];
                $primaryActivityId = $request->allocations[0]['activity_id'];
                $isMultiActivity   = count($request->allocations) > 1;

                // --- 1. CREATE SINGLE PARENT SUMMARY RECORD IN FUNDS ---
                $fundHeader = new \App\Models\Fund();
                $fundHeader->dtrack_no           = $validated['dtrack_no'];
                $fundHeader->transaction_date    = $validated['transaction_date'];
                $fundHeader->particulars         = $validated['particulars'];
                $fundHeader->amount              = $totalConsolidatedAmount; // Stores initial routed amount total
                $fundHeader->obligation_amount   = 0.00;                     // 0.00 until synced from RAODS
                $fundHeader->source_of_fund_id   = $primarySourceId;
                $fundHeader->transaction_type_id = $primaryActivityId; 
                $fundHeader->user_id             = auth()->id();
                $fundHeader->secid               = $secid; 
                $fundHeader->status              = 'Routed';
                $fundHeader->is_multi_activity   = $isMultiActivity;
                $fundHeader->save();

                // Sync creditors to the summary header record
                if (!empty($request->creditor_ids)) {
                    $fundHeader->creditors()->sync($request->creditor_ids);
                }

                // --- 2. RECORD EACH ALLOCATION INTO FUND_MULTI_ACTIVITIES ---
                foreach ($request->allocations as $allocation) {
                    $activityId = $allocation['activity_id']; // Explicitly pulls each row's unique activity id
                    $activity = \App\Models\Activity::findOrFail($activityId);
                    $allocatedAmount = round((float)$allocation['amount'], 2);

                    \App\Models\FundMultiActivity::create([
                        'fund_id'             => $fundHeader->id,
                        'source_of_fund_id'   => $allocation['source_id'] ?? $primarySourceId,
                        'transaction_type_id' => $activityId, // Correctly assigns each loop's specific activity ID
                        'dtrack_no'           => $validated['dtrack_no'],
                        'routed_amount'       => $allocatedAmount,
                        'creditor'            => $activity->name,
                        'particulars'         => $validated['particulars'],
                        'obligation_amount'   => 0.00,  // 0.00 until synced from RAODS
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

        // 2. Fetch all allocations in this group
        $funds = Fund::where('dtrack_no', $dtrack)->get();

        DB::beginTransaction();
        try {
            foreach ($funds as $fund) {
                // Prepare common update data
                $updateData = [
                    'status_date' => $request->status_date,
                    'remarks'     => $request->remarks,
                ];

                // 3. Logic for Status and Serial Number
                // If the specific row is ALREADY obligated/synced, we protect its status/serial
                if ($fund->status === 'Obligated' && $fund->obligation_amount > 0) {
                    // Keep existing status and serial for this row
                } else {
                    $updateData['status'] = $request->status;

                    if ($request->status === 'Obligated') {
                        // Match the serial number from the request to this specific row ID
                        // We look for the 'serials' array sent from the modal
                        $submittedSerials = collect($request->serials);
                        $rowSerial = $submittedSerials->firstWhere('id', $fund->id);
                        
                        $updateData['obligation_serial'] = $rowSerial ? $rowSerial['serial_no'] : null;
                    } else {
                        $updateData['obligation_serial'] = null;
                    }
                }

                $fund->update($updateData);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Group status updated successfully!',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 422);
        }
    }

    public function getGroupByDtrack($dtrack)
    {
        $allocations = Fund::where('dtrack_no', $dtrack)
            ->with(['fundSource']) 
            ->get()
            ->map(function ($fund) {
                return [
                    'id' => $fund->id,
                    'source_name' => $fund->fundSource->name ?? 'N/A',
                    'amount' => number_format($fund->amount, 2), 
                    'obligation_serial' => $fund->obligation_serial,
                    // ADD THESE FIELDS:
                    'status' => $fund->status,
                    'remarks' => $fund->remarks,
                    'status_date' => $fund->status_date ? \Carbon\Carbon::parse($fund->status_date)->format('Y-m-d') : date('Y-m-d'),
                ];
            });

        return response()->json(['allocations' => $allocations]);
    }

    public function edit(\App\Models\Fund $fund) 
    {
        // 1. Validation (matches your JS editableStatuses)
        // Using strtolower to be safe with casing
        if (strtolower($fund->status) !== 'routed') {
            return response()->json([
                'success' => false,
                'message' => 'Editing is only allowed for transactions in Routed status.'
            ], 403);
        }

        // 2. Fetch all allocations sharing the same DTrack Number
        $allAllocations = \App\Models\Fund::where('dtrack_no', $fund->dtrack_no)
            ->with(['fundSource', 'activity'])
            ->get();

        /**
         * 3. Load Creditors (Pivot table)
         * We bypass the Eloquent relationship because the IDs (1077, etc.) 
         * don't exist in the local users table.
         */
        $creditorIds = \DB::table('employee_fund')
            ->whereIn('fund_id', $allAllocations->pluck('id'))
            ->pluck('user_id') // user_id column stores the dbedid
            ->unique()
            ->values()
            ->toArray();

        // 4. Return as a structured JSON object
        return response()->json([
            'success'      => true,
            'main'         => $fund,
            'allocations'  => $allAllocations,
            'creditor_ids' => $creditorIds // This will now correctly contain [1077, ...]
        ]);
    }

    public function update(Request $request, $id, \App\Services\DTrackService $dtrackService)
    {
        // 1. Initial Fetch and Guard Clause
        $primaryFund = \App\Models\Fund::findOrFail($id);
        
        // REQUIREMENT: Only 'Routed' status can be edited
        if ($primaryFund->status !== 'Routed') {
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

        // 3. VALIDATION
        $validated = $request->validate([
            'dtrack_no'         => ['required', 'regex:/^\d{4}-\d{6}$/'], 
            'transaction_date'  => 'required|date',
            'particulars'       => 'required|string',
            'creditor_ids'      => 'nullable|array',
            'allocations'               => 'required|array|min:1',
            'allocations.*.id'          => 'nullable|exists:funds,id', 
            'allocations.*.source_id'   => 'required|exists:source_of_funds,id',
            'allocations.*.activity_id' => 'required|exists:activities,id',
            'allocations.*.amount'      => 'required|numeric|min:0.01',
        ]);

        // 4. SECID Logic (Standardized)
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
            return response()->json(['success' => false, 'message' => 'System Error: Profile section link missing.'], 422);
        }

        try {
            return \DB::transaction(function () use ($request, $validated, $secid, $oldDtrack) {
                
                // A. Handle Deletions (Rows removed from the UI)
                $existingRowIds = \App\Models\Fund::where('dtrack_no', $oldDtrack)->pluck('id')->toArray();
                $submittedRowIds = collect($request->allocations)->pluck('id')->filter()->toArray();
                \App\Models\Fund::whereIn('id', array_diff($existingRowIds, $submittedRowIds))->delete();

                // B. Process Allocations
                foreach ($request->allocations as $index => $item) {
                    $activity = \App\Models\Activity::findOrFail($item['activity_id']);

                    // BUDGET CHECK LOGIC
                    // Sum all funds for this activity, EXCLUDING the one we are currently updating
                    $currentFundId = $item['id'] ?? 0;
                    $totalSpentByOthers = \App\Models\Fund::where('transaction_type_id', $activity->id)
                        ->where('id', '!=', $currentFundId)
                        ->sum('amount');

                    $remaining = (float)$activity->budget - (float)$activity->pooled_amount - $totalSpentByOthers;

                    if ($item['amount'] > $remaining) {
                        throw new \Exception("Row " . ($index + 1) . " exceeds budget for {$activity->name}. Available: ₱" . number_format($remaining, 2));
                    }

                    // C. Update or Create the fund row
                    $fund = \App\Models\Fund::updateOrCreate(
                        ['id' => $item['id'] ?? null],
                        [
                            'dtrack_no'         => $validated['dtrack_no'],
                            'transaction_date'  => $validated['transaction_date'],
                            'particulars'       => $validated['particulars'],
                            'source_of_fund_id' => $item['source_id'],
                            'transaction_type_id' => $activity->id, 
                            'amount'            => $item['amount'],
                            'user_id'           => auth()->id(),
                            'secid'             => $secid,
                            'status'            => 'Routed', // Maintain status
                        ]
                    );

                    // D. Sync Creditors (Applied to each allocation row)
                    $fund->creditors()->sync($request->creditor_ids ?? []);
                }

                return response()->json(['success' => true, 'message' => 'Transaction updated successfully!']);
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
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

    public function syncWithGoogleSheet(Request $request, $id)
    {
        ini_set('memory_limit', '512M'); 
        set_time_limit(300);

        try {
            $clickedFund = Fund::findOrFail($id);
            $targetDtrack = trim((string)$clickedFund->dtrack_no);

            if (empty($targetDtrack)) {
                return response()->json(['success' => false, 'message' => 'DTrack number is missing.']);
            }

            $sourceConfig = \App\Models\SourceOfFund::find($clickedFund->source_of_fund_id);
            if (!$sourceConfig || !$sourceConfig->spreadsheet_id || !$sourceConfig->sheet_name) {
                return response()->json(['success' => false, 'message' => 'Fund source configuration missing.']);
            }

            $syncedDetails = [];
            $userId = Auth::id() ?? 1;

            $client = new \Google\Client();
            $httpClient = new \GuzzleHttp\Client(['verify' => 'C:\xampp\php\extras\ssl\cacert.pem']);
            $client->setHttpClient($httpClient);
            $client->setAuthConfig(storage_path('app/google-credentials.json'));
            $client->addScope(\Google_Service_Sheets::SPREADSHEETS_READONLY);
            $service = new \Google_Service_Sheets($client);

            $range = "'{$sourceConfig->sheet_name}'!A:CQ";
            $response = $service->spreadsheets_values->get($sourceConfig->spreadsheet_id, $range);
            $rows = $response->getValues();

            if (!empty($rows)) {
                $tracker = [
                    'netOb'          => 0.0, 
                    'netDisb'        => 0.0,
                    'latestObDate'   => null, 
                    'latestDisbDate' => null,
                    'found_serial'   => null, 
                    'found'          => false,
                    'disbursements'  => [],
                    'norsa_entries'  => []
                ];

                $creditorName = null;
                $particulars  = '';
                $sheetSerial  = null;
                $isCosSalary  = false;
                $matchedRows  = [];

                foreach ($rows as $rowIndex => $rowData) {
                    $sheetDtrack    = isset($rowData[0])  ? trim((string)$rowData[0])  : '';
                    $sheetSerialRow = isset($rowData[3])  ? trim((string)$rowData[3])  : '';
                    $allotment      = isset($rowData[5])  ? trim((string)$rowData[5])  : '';
                    $creditorRow    = isset($rowData[10]) ? trim((string)$rowData[10]) : null;
                    $particularsRow = isset($rowData[11]) ? trim((string)$rowData[11]) : '';

                    if (empty($sheetDtrack) && empty($sheetSerialRow)) continue;

                    $isTargetMatch = ($sheetDtrack === $targetDtrack) || 
                                     (!empty($sheetSerialRow) && $sheetSerialRow === $clickedFund->obligation_serial);

                    if (!$isTargetMatch) continue;

                    if ($clickedFund->creditor && $creditorRow) {
                        $normTargetCreditor = strtolower(preg_replace('/\s+/', ' ', trim($clickedFund->creditor)));
                        $normSheetCreditor  = strtolower(preg_replace('/\s+/', ' ', trim($creditorRow)));
                        if ($normTargetCreditor !== $normSheetCreditor) {
                            continue;
                        }
                    }

                    $tracker['found'] = true;
                    $matchedRows[] = $rowData;

                    if (!empty($sheetSerialRow)) $sheetSerial = $sheetSerialRow;
                    if (!empty($creditorRow))    $creditorName = $creditorRow;
                    if (!empty($particularsRow)) $particulars = $particularsRow;

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

                    $totalObligatedFromRaods = $tracker['netOb'];
                    $totalDisbursedFromRaods = $tracker['netDisb'];

                    // Calculate Total Sheet NORSA
                    $totalSheetNorsa = 0;
                    if (!empty($tracker['norsa_entries'])) {
                        foreach ($tracker['norsa_entries'] as $nEntry) {
                            $totalSheetNorsa += (float)($nEntry['amount'] ?? 0);
                        }
                    }

                    $netConsolidatedObligated = max(0, $totalObligatedFromRaods - $totalSheetNorsa);

                    // --- CHECK MULTI-ACTIVITY STATUS VIA LINE ITEMS ---
                    $lineItems = \App\Models\FundMultiActivity::where('fund_id', $clickedFund->id)->get();
                    $lineItemsCount = $lineItems->count();
                    $isConsolidatedMultiActivity = ($lineItemsCount > 1 || count($matchedRows) > 1) && !$isCosSalary;

                    $totalRoutedAmount = $lineItems->isNotEmpty() 
                        ? (float)$lineItems->sum('routed_amount') 
                        : ((float)($clickedFund->amount ?? $totalObligatedFromRaods));

                    // Determine discrepancy
                    $hasDiscrepancy = (abs($netConsolidatedObligated - $totalRoutedAmount) > 0.01) || ($totalSheetNorsa > 0);

                    // =====================================================================
                    // 1. INTERACTIVE MODAL TRIGGER IF DISCREPANCY EXISTS
                    // =====================================================================
                    if ($isConsolidatedMultiActivity && $hasDiscrepancy) {
                        if ($lineItems->isNotEmpty()) {
                            $activitiesBreakdown = $lineItems->map(function($item) {
                                return [
                                    'id'                  => $item->id,
                                    'fund_id'             => $item->fund_id,
                                    'transaction_type_id' => $item->transaction_type_id, // Retain unique Activity ID
                                    'source_of_fund_id'   => $item->source_of_fund_id,
                                    'activity_name'       => $item->creditor ?? ('Item #' . $item->id),
                                    'routed_amount'       => (float)$item->routed_amount,
                                    'obligation_amount'   => (float)$item->obligation_amount,
                                ];
                            })->values()->all();
                        } else {
                            $activitiesBreakdown = collect($matchedRows)->map(function($row, $idx) use ($clickedFund) {
                                return [
                                    'fund_id'             => $idx + 1,
                                    'transaction_type_id' => $clickedFund->transaction_type_id,
                                    'source_of_fund_id'   => $clickedFund->source_of_fund_id,
                                    'activity_name'       => trim((string)($row[10] ?? $row[11] ?? ('Activity Line #' . ($idx + 1)))),
                                    'routed_amount'       => (float)str_replace(',', '', $row[6] ?? 0),
                                ];
                            })->values()->all();
                        }

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
                    // 2. AUTO-DISTRIBUTION (NO DISCREPANCY / EXACT MATCH)
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

                    if ($isCosSalary) {
                        if ($contractId) $updateData['cos_contract_id'] = $contractId;
                        $updateData['disbursed_months'] = $disbursedMonthCount;
                        $updateData['remarks_salary']   = 'Imported HR COS Salary/Wages';
                        $updateData['is_multi_activity'] = 0;
                    }

                    $clickedFund->update($updateData);

                    // Update multi-activity line items proportionally without modifying transaction_type_id
                    if ($isConsolidatedMultiActivity && $lineItems->isNotEmpty()) {
                        foreach ($lineItems as $actItem) {
                            $actRouted = (float)$actItem->routed_amount;
                            
                            $actObligation = ($totalRoutedAmount > 0) 
                                ? round($netConsolidatedObligated * ($actRouted / $totalRoutedAmount), 2) 
                                : 0;

                            $actDisbursement = ($netConsolidatedObligated > 0) 
                                ? round($totalDisbursedFromRaods * ($actObligation / $netConsolidatedObligated), 2) 
                                : 0;

                            if ($actDisbursement > 0) {
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
                        'dtrack' => $targetDtrack,
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
            $dbSerial  = !empty($freshFund->obligation_serial) ? $freshFund->obligation_serial : $freshFund->dtrack_no;

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

        $client = new \Google\Client();
        $httpClient = new \GuzzleHttp\Client(['verify' => 'C:\xampp\php\extras\ssl\cacert.pem']);
        $client->setHttpClient($httpClient);
        $client->setAuthConfig(storage_path('app/google-credentials.json'));
        $client->addScope(\Google_Service_Sheets::SPREADSHEETS_READONLY);
        $service = new \Google_Service_Sheets($client);

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

                $allotClass = strtoupper(trim((string)($sourceConfig->allotment_class ?? '')));
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
                        $sheetDtrack = isset($rowData[0]) ? trim((string)$rowData[0]) : '';
                        $sheetSerial = isset($rowData[3]) ? trim((string)$rowData[3]) : '';
                        
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
                            'norsa_entries'  => []
                        ];

                        $creditorName = null;
                        $particulars  = '';
                        $sheetSerial  = null;
                        $sheetDtrack  = '';
                        $isCosSalary  = false;
                        $isCapitalOutlay = false;

                        foreach ($groupRows as $rowData) {
                            $sheetDtrack    = isset($rowData[0])  ? trim((string)$rowData[0])  : $sheetDtrack;
                            $sheetSerial    = isset($rowData[3])  ? trim((string)$rowData[3])  : $sheetSerial;
                            $allotment      = isset($rowData[5])  ? trim((string)$rowData[5])  : '';
                            $creditorRow    = isset($rowData[10]) ? trim((string)$rowData[10]) : null;
                            $particularsRow = isset($rowData[11]) ? trim((string)$rowData[11]) : '';

                            if (!empty($sheetSerial)) $sheetSerial = $sheetSerial;
                            if (!empty($creditorRow)) $creditorName = $creditorRow;
                            if (!empty($particularsRow)) $particulars = $particularsRow;

                            $cleanAllotment   = strtolower(preg_replace('/\s+/', ' ', $allotment));
                            $cleanParticulars = strtolower(preg_replace('/\s+/', ' ', $particularsRow));

                            $isOtherProfServ = (stripos($cleanAllotment, 'other professional services') !== false);
                            $isWages         = (stripos($cleanParticulars, 'wages') !== false);
                            if ($isOtherProfServ && $isWages) {
                                $isCosSalary = true;
                            }

                            if ($isCosSalary) {
                                $allClass = strtoupper(trim((string)($sourceConfig->allotment_class ?? '')));
                                $sourceName = $sourceConfig->name ?? '';
                                $sourceCode = $sourceConfig->code ?? '';
                                $isCapitalOutlay = ($allClass === 'CO') ||
                                    (stripos($sourceName, 'Capital Outlay') !== false) || 
                                    (stripos($sourceName, ' CO') !== false) ||
                                    (stripos($sourceCode, 'CO') !== false);
                            }

                            $this->processApiRowData($rowData, $tracker, $particularsRow, $isCosSalary);
                        }

                        if ($isCosSalary && $isCapitalOutlay) {
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
                                ->when($isCosSalary && $creditorName, function($q) use ($creditorName) {
                                    $q->where('creditor', $creditorName);
                                })
                                ->first();
                        }

                        if (!$existingFund && !empty($sheetDtrack)) {
                            $existingFund = Fund::where('source_of_fund_id', $sourceConfig->id)
                                ->where(function($q) use ($sheetDtrack) {
                                    $q->where('dtrack_no', $sheetDtrack)
                                    ->orWhere('dtrack_no_new', $sheetDtrack);
                                })
                                ->when($isCosSalary && $creditorName, function($q) use ($creditorName) {
                                    $q->where('creditor', $creditorName);
                                })
                                ->first();
                        }

                        if ($existingFund) {
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
                        } 
                        elseif ($existingFund) {
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

                        // SYNC DISBURSEMENTS, NORSA, AND LOGS ONLY (NO MULTI-ACTIVITY WRITES)
                        if ($targetFund) {
                            $this->syncDisbursementRecords($targetFund, $tracker['disbursements'], $isCosSalary);
                            $this->syncNorsaAdjustments($targetFund, $tracker['norsa_entries'] ?? [], $sourceConfig->id);

                            $matchingBulkRows = [];
                            foreach ($rows as $rData) {
                                $rDtrack = isset($rData[0]) ? trim((string)$rData[0]) : '';
                                $rSerial = isset($rData[3]) ? trim((string)$rData[3]) : '';
                                
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
        // For multi-activity transactions, evaluate NORSA per line item
        if ($fund->is_multi_activity) {
            $multiActivities = \App\Models\FundMultiActivity::where('fund_id', $fund->id)->get();

            foreach ($multiActivities as $multi) {
                // Determine line-item discrepancy between routed amount and obligated amount
                $difference = round((float)$multi->routed_amount - (float)$multi->obligation_amount, 2);

                if ($difference > 0) {
                    \App\Models\NorsaAdjustment::updateOrCreate(
                        [
                            'fund_id'             => $fund->id,
                            'transaction_type_id' => $multi->transaction_type_id, // Targets distinct activity ID (e.g. 48)
                        ],
                        [
                            'dtrack_no'           => $fund->dtrack_no,
                            'obligation_serial'   => $multi->obligation_serial ?: $fund->obligation_serial,
                            'creditor'            => $multi->creditor,
                            'particulars'         => $multi->particulars,
                            'entry_date'          => $fund->transaction_date ?? now(),
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
            // Single activity transaction logic
            if (empty($norsaEntries)) {
                return;
            }

            foreach ($norsaEntries as $norsa) {
                \App\Models\NorsaAdjustment::updateOrCreate(
                    [
                        'fund_id'             => $fund->id,
                        'transaction_type_id' => $fund->transaction_type_id,
                        'obligation_serial'   => $norsa['obligation_serial'],
                        'entry_date'          => $norsa['entry_date'],
                    ],
                    [
                        'dtrack_no'           => $norsa['dtrack_no'],
                        'creditor'            => $norsa['creditor'],
                        'particulars'         => $norsa['particulars'],
                        'amount'              => $norsa['amount'],
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