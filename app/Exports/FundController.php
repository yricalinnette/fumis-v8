<?php

namespace App\Http\Controllers;

use App\Models\Fund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\SourceOfFund;
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
            // 1. Get the current user's section ID
            // We look at the local employee_details first to find the dbedid
            $localDetail = \DB::table('employee_details')
                ->where('user_id', $currentUser->id)
                ->first();

            $userSecId = null;
            if ($localDetail) {
                // Find the actual section ID from the common database
                $userSecId = \DB::connection('db_common')->table('tbl_emp_details')
                    ->where('dbedid', $localDetail->dbedid)
                    ->value('secid');
            }

            // 2. Fetch funds where the user is the owner OR it belongs to their section
            // This fixes the "Missing Transaction" 131 if it were assigned to your section
            $baseQuery = \App\Models\Fund::where(function($q) use ($currentUser, $userSecId) {
                $q->where('user_id', $currentUser->id);
                if ($userSecId !== null) {
                    $q->orWhere('secid', $userSecId);
                }
            });
        }

        // Execute the collection
        $fundsCollection = $baseQuery->with(['fundSource', 'activity', 'creditors.employeeDetail'])
            ->whereNotNull('dtrack_no')
            ->where('dtrack_no', '!=', '')
            ->orderBy('created_at', 'desc')
            ->get();

        // --- 2. EMPLOYEE LOGIC 
        $key = config('app.db_common_key') ?? env('DB_COMMON_ENCRYPTION_KEY');

        $involvedIds = \DB::table('employee_fund')
            ->whereIn('fund_id', $fundsCollection->pluck('id'))
            ->pluck('user_id')
            ->unique()
            ->toArray();

        // Get section for label
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
            
            // Filter: Show employees in my section OR anyone involved in the loaded transactions
            $query->where(function($q) use ($mySectionId, $involvedIds) {
                $q->where('tbl_emp_details.secid', '=', $mySectionId);
                
                if (!empty($involvedIds)) {
                    $q->orWhereIn('tbl_emp_details.dbedid', $involvedIds);
                }
            });
        }

        // Process and index by DBEDID
        $employees = $query->get()->map(function($emp) {
            // SAFETY: Force UTF-8 encoding to prevent "Malformed UTF-8" crashes
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
            
            // Update the properties with cleaned strings so JS doesn't crash later
            $emp->fname = $fname;
            $emp->mname = $mname;
            $emp->lname = $lname;
            $emp->suffix = $suffix;

            return $emp;
        })->keyBy('dbedid');

        // --- 3. GROUP AND MAP (NOW USING $employees) ---
        $funds = $fundsCollection->groupBy('dtrack_no')->map(function ($group) use ($employees) {
            $first = $group->first();

            $dbedids = \DB::table('employee_fund')
                ->whereIn('fund_id', $group->pluck('id'))
                ->pluck('user_id')
                ->unique();

            // 2. Map those IDs to the Names from your $employees collection
            $mappedCreditors = $dbedids->map(function($id) use ($employees) {
                // Look for the employee in the collection we fetched from db_common
                $emp = $employees->get($id);

                return (object) [
                    'full_name' => $emp ? $emp->fullname : "ID: $id (Not found in section)"
                ];
            });

            // Calculations and Status logic
            $calculatedTotal = $group->sum(function($item) {
                if (in_array($item->status, ['Disbursed', 'Completed']) && $item->disbursement_amount > 0) {
                    return $item->disbursement_amount;
                }
                return ($item->obligation_amount > 0) ? $item->obligation_amount : $item->amount;
            });

            $hasDisbursed = $group->contains('status', 'Disbursed');
            $hasCompleted = $group->contains('status', 'Completed');
            $hasObligated = $group->contains('status', 'Obligated');

            if ($hasDisbursed || $hasCompleted) {
                $priorityStatus = 'Disbursed';
            } elseif ($hasObligated) {
                $priorityStatus = 'Obligated';
            } else {
                $priorityStatus = $first->status;
            }

            return (object) [
                'id'                 => $first->id,
                'dtrack_no'          => $first->dtrack_no,
                'transaction_date'   => $first->transaction_date,
                'particulars'        => $first->particulars,
                'secid'              => $first->secid,
                'created_at'         => $first->created_at,
                'creditors'          => $mappedCreditors, 
                'total_amount'       => $calculatedTotal,
                'group_status'       => $priorityStatus,
                'source_names'       => $group->pluck('fundSource.name')->filter()->unique()->implode('<br>'),
                'activity_names'     => $group->pluck('activity.name')->filter()->unique()->implode('<br>'),
                'is_fully_synced'    => !$group->contains(function ($item) {
                        $isObligatedMissing = ($item->status === 'Obligated' && (empty($item->obligation_amount) || $item->obligation_amount <= 0));
                        $isDisbursedMissing = (in_array($item->status, ['Disbursed', 'Completed']) && (empty($item->disbursement_amount) || $item->disbursement_amount <= 0));
                        return $isObligatedMissing || $isDisbursedMissing;
                    }),
                'breakdown'          => $group->map(function($item) {
                    return (object) [
                        'id'     => $item->id,
                        'source_name'         => $item->fundSource->name ?? 'N/A', 
                        'activity_name'       => $item->activity->name ?? 'N/A',
                        'amount'              => $item->amount,
                        'status'              => $item->status,
                        'remarks'             => $item->remarks,
                        'obligation_serial'   => $item->obligation_serial,
                        'obligation_amount'   => $item->obligation_amount,
                        'obligation_date'     => $item->obligation_date,
                        'disbursement_amount' => $item->disbursement_amount,
                        'disbursement_date'   => $item->disbursement_date,
                        'status_date'         => $item->status_date,
                    ];
                }),
            ];
        })->sortByDesc('created_at')->values();

        $userSectionName = ($currentUser->is_admin) ? 'All Personnel' : ($myDetails->secname ?? 'Assigned Section');

        // For the Source of fund dropdown in the Modal Transaction Log
        // 1. Resolve the User's Section ID from db_common
        $userSecId = null;

        // Check local mapping table for dbedid
        $localDetail = \DB::table('employee_details')
            ->where('user_id', $currentUser->id)
            ->first();

        if ($localDetail && $localDetail->dbedid) {
            // Look up the secid in the external db_common database
            $userSecId = \DB::connection('db_common')
                ->table('tbl_emp_details')
                ->where('dbedid', $localDetail->dbedid)
                ->value('secid');
        }

        // 2. Fetch the Sources
        $sourcesQuery = \App\Models\SourceOfFund::where('fiscal_year', date('Y'))
            ->with(['activities', 'budgetLineItem'])
            ->orderBy('name');

        // 3. Apply Security Filter
        // If not admin, restrict to the resolved Section ID
        if (!$isAdmin) {
            if ($userSecId) {
                $sourcesQuery->where('section_id', $userSecId);
            } else {
                // Fallback: If no section is found, return empty to prevent data leakage
                $sourcesQuery->whereRaw('1 = 0');
            }
        }

        $sources = $sourcesQuery->get();

        $user = auth()->user();

        // 1. Determine the Section Filter
        $isSectionAdmin = ($user->id == 1 || $user->username === 'admin');

        // 2. Base Query for Awaiting Sync (Match what bulkSync() loops through)
        // bulkSync updates anything where status != 'Disbursed' and status != 'Cancelled'
        $syncQuery = \App\Models\Fund::whereNotIn('status', ['Disbursed', 'Cancelled']);

        // 3. Base Query for Awaiting OBRN (Keep if you still need it separately elsewhere)
        $obrnQuery = \App\Models\Fund::where('status', 'For CAF/Obligation')
                        ->whereNull('obligation_serial');

        // 4. Apply Section Filter if NOT Admin
        if (!$isSectionAdmin) {
            $localDetail = \DB::table('employee_details')
                ->where('user_id', $user->id)
                ->select('dbedid')
                ->first();

            if ($localDetail && $localDetail->dbedid) {
                $userSecId = \DB::connection('db_common')->table('tbl_emp_details')
                    ->where('dbedid', $localDetail->dbedid)
                    ->value('secid');
                
                // Apply the section block to both queries
                $syncQuery->where('secid', $userSecId);
                $obrnQuery->where('secid', $userSecId);
            } else {
                $syncQuery->whereRaw('1 = 0'); 
                $obrnQuery->whereRaw('1 = 0');
            }
        }

        // 5. EXECUTE THE FILTERED COUNTS
        $awaitingSyncCount = $syncQuery->count(); // This will now accurately return 7
        $awaitingOBRN      = $obrnQuery->count();

        $allSections = $isAdmin 
            ? \DB::connection('db_common')
                ->table('tbl_section')
                ->where('isactive', 1) // Only pull records where isactive is 1
                ->orderBy('secname', 'asc')
                ->pluck('secname', 'secid')
                ->toArray() 
            : [];

        return view('funds.index', compact('funds', 'sources', 'employees', 'userSectionName', 'awaitingSyncCount', 
        'awaitingOBRN', 'allSections', 'isAdmin'));
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
            'dtrack_no'         => ['required', 'regex:/^\d{4}-\d{6}$/'],
            'transaction_date'  => 'required|date',
            'particulars'       => 'required|string',
            'creditor_ids'      => 'nullable|array',
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
        // 1. Initialize variables
        $userId = auth()->id();
        $secid = null;

        // 2. CHECK: If user is admin, they might not need a secid (or set a default)
        if (auth()->user()->username === 'admin') {
            $secid = 0; // Or whatever ID you use for 'Regional Office'
        } else {
            // 3. Get dbedid from your LOCAL table 'employee_details'
            $localDetail = \DB::table('employee_details')
                ->where('user_id', $userId)
                ->select('dbedid')
                ->first();

            if ($localDetail && $localDetail->dbedid) {
                // 4. Get secid from db_common.tbl_emp_details
                // IMPORTANT: Ensure your db_common connection is working in config/database.php
                $secid = \DB::connection('db_common')->table('tbl_emp_details')
                    ->where('dbedid', $localDetail->dbedid)
                    ->value('secid');
            }
        }

        // 5. CRITICAL PROTECTION: Stop if secid is still null for non-admins
        if (is_null($secid) && auth()->user()->username !== 'admin') {
            return response()->json([
                'success' => false, 
                'message' => 'System Error: Your account is not correctly linked to a DOH Section. Please contact ICT to update your Employee Profile.'
            ], 422);
        }
        // -------------------------

        try {
            return \DB::transaction(function () use ($request, $validated, $secid) {
                foreach ($request->allocations as $index => $allocation) {
                    $activity = \App\Models\Activity::findOrFail($allocation['activity_id']);

                    // Budget Check
                    $totalSpent = \App\Models\Fund::where('transaction_type_id', $activity->id)->sum('amount');
                    $remaining = (float)$activity->budget - (float)$activity->pooled_amount - $totalSpent;

                    if ($allocation['amount'] > $remaining) {
                        throw new \Exception("Row " . ($index + 1) . " exceeds budget for {$activity->name}. Available: ₱" . number_format($remaining, 2));
                    }

                    // Create individual fund row
                    $fund = new \App\Models\Fund();
                    $fund->dtrack_no           = $validated['dtrack_no'];
                    $fund->transaction_date    = $validated['transaction_date'];
                    $fund->particulars         = $validated['particulars'];
                    $fund->amount              = $allocation['amount'];
                    $fund->source_of_fund_id   = $allocation['source_id'];
                    $fund->transaction_type_id = $activity->id; 
                    $fund->user_id             = auth()->id();
                    
                    // STAMP THE SECTION ID
                    $fund->secid               = $secid; 
                    
                    $fund->status              = 'Routed';
                    $fund->save();

                    // Sync creditors for this specific fund row
                    $fund->creditors()->sync($request->creditor_ids ?? []);
                }

                return response()->json(['success' => true, 'message' => 'Multi-fund transaction saved!']);
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
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

            // Cleaned lookup to prevent relationship naming collision issues
            $funds = Fund::where('dtrack_no', $targetDtrack)->get();
            if ($funds->isEmpty()) {
                return response()->json(['success' => false, 'message' => "No records for DTrack: $targetDtrack"]);
            }

            $fundGroup = $funds->groupBy('source_of_fund_id');
            $syncedDetails = []; 

            // Initialize Google Client Once
            $client = new \Google\Client();
            $httpClient = new \GuzzleHttp\Client(['verify' => '/etc/ssl/certs/ca-certificates.crt']);
            $client->setHttpClient($httpClient);
            $client->setAuthConfig(storage_path('app/google-credentials.json'));
            $client->addScope(\Google_Service_Sheets::SPREADSHEETS_READONLY);
            $service = new \Google_Service_Sheets($client);

            foreach ($fundGroup as $sourceId => $fundsInSource) {
                // Explicitly targeting the direct Source configuration table config
                $sourceConfig = \App\Models\SourceOfFund::find($sourceId);
                if (!$sourceConfig || !$sourceConfig->spreadsheet_id || !$sourceConfig->sheet_name) continue;

                // Fetch Range directly from Sheets API
                $range = "'{$sourceConfig->sheet_name}'!A:CQ";
                $response = $service->spreadsheets_values->get($sourceConfig->spreadsheet_id, $range);
                $rows = $response->getValues();

                if (empty($rows)) continue;

                $tracker = [
                    'netOb' => 0.0, 'netDisb' => 0.0,
                    'latestObDate' => null, 'latestDisbDate' => null,
                    'found_serial' => null, 'found' => false
                ];

                foreach ($rows as $rowData) {
                    $sheetDtrack = isset($rowData[0]) ? trim((string)$rowData[0]) : '';
                    if ($sheetDtrack === $targetDtrack) {
                        $tracker['found'] = true;
                        $tracker['found_serial'] = isset($rowData[3]) ? trim((string)$rowData[3]) : 'N/A';
                        $this->processApiRowData($rowData, $tracker);
                        break; 
                    }
                }

                if ($tracker['found']) {
                    foreach ($fundsInSource as $model) {
                        $status = ($tracker['netDisb'] >= $tracker['netOb'] && $tracker['netOb'] > 0) ? 'Disbursed' : 'Obligated';
                        
                        $model->update([
                            'obligation_serial'   => ($tracker['found_serial'] !== 'N/A') ? $tracker['found_serial'] : $model->obligation_serial,
                            'obligation_amount'   => $tracker['netOb'],
                            'obligation_date'     => $tracker['latestObDate'],
                            'disbursement_amount' => $tracker['netDisb'],
                            'disbursement_date'   => $tracker['latestDisbDate'],
                            'status'              => $status,
                            'status_date'         => now()
                        ]);

                        $syncedDetails[] = [
                            'name'   => $sourceConfig->name ?? 'HIT', // Safely pulls from our direct config variable
                            'dtrack' => $targetDtrack,
                            'serial' => $tracker['found_serial'],
                            'amount' => number_format($tracker['netOb'], 2),
                            'status' => $status
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'details' => [
                    'dtrack_no'    => $targetDtrack,
                    'synced_items' => $syncedDetails,
                    'count'        => count($syncedDetails)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => "API Error: " . $e->getMessage()], 500);
        }
    }

    private function processApiRowData($rowData, &$tracker) {
        $clean = function($val) {
            $raw = str_replace([',', '₱', ' '], '', trim((string)$val));
            if (str_starts_with($raw, '(')) $raw = '-' . trim($raw, '()');
            return is_numeric($raw) ? (float)$raw : 0.0;
        };

        // Column J is Index 9 (Obligation Amount)
        $obVal = isset($rowData[9]) ? $clean($rowData[9]) : 0.0;
        $tracker['netOb'] += $obVal;
        
        // Column C is Index 2 (Obligation Date)
        if (isset($rowData[2]) && !empty($rowData[2])) {
            $tracker['latestObDate'] = $this->parseApiDate($rowData[2]);
        }

        // DISBURSEMENT MAPPING (Index = Column Number - 1)
        // Dates: N(13), U(20), AB(27), AI(34), AP(41), AW(48), BD(55), BK(62), BR(69), BY(76), CF(83), CM(90)
        // Totals: R(17), Y(24), AF(31), AM(38), AT(45), BA(52), BH(59), BO(66), BV(73), CC(80), CJ(87), CQ(94)
        $dateIndexes  = [13, 20, 27, 34, 41, 48, 55, 62, 69, 76, 83, 90];
        $totalIndexes = [17, 24, 31, 38, 45, 52, 59, 66, 73, 80, 87, 94];

        foreach ($totalIndexes as $idx => $tIdx) {
            if (isset($rowData[$tIdx])) {
                $val = $clean($rowData[$tIdx]);
                if ($val != 0) {
                    $tracker['netDisb'] += $val;
                    
                    // Get the corresponding date
                    $dIdx = $dateIndexes[$idx];
                    if (isset($rowData[$dIdx]) && !empty($rowData[$dIdx])) {
                        $tracker['latestDisbDate'] = $this->parseApiDate($rowData[$dIdx]);
                    }
                }
            }
        }
    }

    private function parseApiDate($dateValue) {
        if (empty($dateValue)) return null;
        try {
            // Google API usually returns strings like "MM/DD/YYYY" or "YYYY-MM-DD"
            return \Carbon\Carbon::parse($dateValue)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function bulkSync()
    {
        $userId = Auth::id();
        $cacheKey = "sync_progress_{$userId}";
        $cancelKey = "sync_cancel_{$userId}";

        // 1. Get all funds except those already fully Disbursed
        $funds = Fund::where('status', '!=', 'Disbursed')->get();

        $total = $funds->count();
        if ($total === 0) {
            return response()->json(['success' => false, 'message' => 'No pending funds to sync.']);
        }

        // Initialize progress tracking
        Cache::forget($cancelKey);
        $this->updateCache($cacheKey, 0, $total);
        
        // Release session lock so the Progress Bar request can get through
        session_write_close();

        // 2. Setup Google Service
        $client = new \Google\Client();
        $httpClient = new \GuzzleHttp\Client(['verify' => '/etc/ssl/certs/ca-certificates.crt']);
        $client->setHttpClient($httpClient);
        $client->setAuthConfig(storage_path('app/google-credentials.json'));
        $client->addScope(\Google_Service_Sheets::SPREADSHEETS_READONLY);
        $service = new \Google_Service_Sheets($client);

        $groupedBySource = $funds->groupBy('source_of_fund_id');
        $processedCount = 0;
        $sourceCounter = 0;

        foreach ($groupedBySource as $sourceId => $fundsInSource) {
            // Stop immediately if user clicked "Cancel"
            if (Cache::has($cancelKey)) {
                $this->finalizeProgress($cacheKey, $processedCount, $total, 'cancelled');
                return response()->json(['success' => false, 'message' => 'Sync Cancelled']);
            }

            $sourceConfig = SourceOfFund::find($sourceId);
            if (!$sourceConfig || !$sourceConfig->spreadsheet_id) {
                $processedCount += $fundsInSource->count();
                continue;
            }

            try {
                // RATE LIMITING: Pause 1 second if we have many sources to stay under Google's 60rpm limit
                $sourceCounter++;
                if ($sourceCounter > 10) {
                    sleep(1); 
                }

                $range = "'{$sourceConfig->sheet_name}'!A:CQ";
                $response = $service->spreadsheets_values->get($sourceConfig->spreadsheet_id, $range);
                $rows = $response->getValues();

                if (!empty($rows)) {
                    foreach ($fundsInSource as $fund) {
                        $targetDtrack = trim((string)$fund->dtrack_no);
                        $tracker = [
                            'netOb' => 0.0, 'netDisb' => 0.0, 
                            'latestObDate' => null, 'latestDisbDate' => null, 
                            'found_serial' => null, 'found' => false
                        ];

                        // Match DTrack number in the fetched rows
                        foreach ($rows as $rowData) {
                            if (isset($rowData[0]) && trim((string)$rowData[0]) === $targetDtrack) {
                                $tracker['found'] = true;
                                $tracker['found_serial'] = isset($rowData[3]) ? trim((string)$rowData[3]) : 'N/A';
                                $this->processApiRowData($rowData, $tracker);
                                break; 
                            }
                        }

                        if ($tracker['found']) {
                            // Calculate new status based on sheet values
                            $newStatus = ($tracker['netDisb'] >= $tracker['netOb'] && $tracker['netOb'] > 0) ? 'Disbursed' : 'Obligated';
                            
                            $fund->update([
                                'obligation_serial'   => ($tracker['found_serial'] !== 'N/A') ? $tracker['found_serial'] : $fund->obligation_serial,
                                'obligation_amount'   => $tracker['netOb'],
                                'obligation_date'     => $tracker['latestObDate'],
                                'disbursement_amount' => $tracker['netDisb'],
                                'disbursement_date'   => $tracker['latestDisbDate'],
                                'status'              => $newStatus,
                                'status_date'         => now()
                            ]);
                        }
                        
                        $processedCount++;
                        $this->updateCache($cacheKey, $processedCount, $total);
                    }
                } else {
                    $processedCount += $fundsInSource->count();
                }

                // Memory cleanup: Clear the large rows array before next loop
                unset($rows);

            } catch (\Exception $e) {
                \Log::error("Bulk Sync Error (Source $sourceId): " . $e->getMessage());
                $processedCount += $fundsInSource->count();
                $this->updateCache($cacheKey, $processedCount, $total);
            }
        }

        $this->finalizeProgress($cacheKey, $total, $total, 'completed');
        return response()->json(['success' => true]);
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


}
