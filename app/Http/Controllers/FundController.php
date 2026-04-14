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

class MyReadFilter implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter
{
    private $startRow = 0;
    private $endRow   = 0;
    private $columns  = [];

    public function __construct($startRow, $endRow, $columns) {
        $this->startRow = $startRow;
        $this->endRow   = $endRow;
        $this->columns  = $columns;
    }

    // REMOVE 'string' and 'int' from the parameters here:
    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        // Check if row is within the range
        if ($row >= $this->startRow && $row <= $this->endRow) {
            // Check if column is in the list of columns to read
            if (in_array($columnAddress, $this->columns)) {
                return true;
            }
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
        $userSecId = null;
        if (!$isAdmin) {
            $localDetail = \DB::table('employee_details')->where('user_id', $currentUser->id)->first();
            if ($localDetail) {
                $userSecId = \DB::connection('db_common')->table('tbl_emp_details')
                    ->where('dbedid', $localDetail->dbedid)
                    ->value('secid');
            }
        }

        $baseQuery = \App\Models\Fund::query();
        if (!$isAdmin) {
            $baseQuery->where(function($q) use ($currentUser, $userSecId) {
                $q->where('user_id', $currentUser->id);
                if ($userSecId) $q->orWhere('secid', $userSecId);
            });
        }

        $fundsCollection = $baseQuery->with(['fundSource', 'activity'])
            ->whereNotNull('dtrack_no')
            ->where('dtrack_no', '!=', '')
            ->orderBy('created_at', 'desc')
            ->get();

        // --- 2. EMPLOYEE & SECTION LOGIC ---
        $key = config('app.db_common_key') ?? env('DB_COMMON_ENCRYPTION_KEY');
        
        // Optimized Employee Fetching (Same as your logic but cleaned)
        $involvedIds = \DB::table('employee_fund')
            ->whereIn('fund_id', $fundsCollection->pluck('id'))
            ->pluck('user_id')->unique()->toArray();

        $query = DB::connection('db_common')->table('tbl_employee')
            ->join('tbl_emp_details', 'tbl_employee.empid', '=', 'tbl_emp_details.empid')
            ->leftJoin('tbl_section', 'tbl_emp_details.secid', '=', 'tbl_section.secid')
            ->where('tbl_employee.isactive', 1)
            ->select(
                'tbl_emp_details.dbedid', 'tbl_emp_details.secid', 'tbl_section.secname',
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.fname, '{$key}') AS CHAR)) as fname"),
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.mname, '{$key}') AS CHAR)) as mname"),
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.lname, '{$key}') AS CHAR)) as lname"),
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.suffix, '{$key}') AS CHAR)) as suffix")
            );

        // Filter employees based on section or involvement
        if (!$isAdmin) {
            $query->where(function($q) use ($userSecId, $involvedIds) {
                $q->where('tbl_emp_details.secid', $userSecId);
                if (!empty($involvedIds)) $q->orWhereIn('tbl_emp_details.dbedid', $involvedIds);
            });
        }

        $employees = $query->get()->map(function($emp) {
            $mi = $emp->mname ? ' ' . substr($emp->mname, 0, 1) . '.' : '';
            $emp->fullname = "{$emp->lname}, {$emp->fname}{$mi}" . ($emp->suffix ? " {$emp->suffix}" : "");
            return $emp;
        })->keyBy('dbedid');

        // --- 3. GROUP AND MAP WITH GRANULAR FINANCIALS ---
        $funds = $fundsCollection->groupBy('dtrack_no')->map(function ($group) use ($employees) {
            $first = $group->first();

            // Financial Accumulators
            $totalNetBudget = 0;
            $totalObligated = 0;
            $totalDisbursed = 0;
            $totalRoutedPending = 0; // "In-Transit"

            $breakdown = $group->map(function($item) use (&$totalNetBudget, &$totalObligated, &$totalDisbursed, &$totalRoutedPending) {
                $pooled = $item->pooled_amount ?? 0;
                $net = ($item->amount > 0 ? $item->amount : ($item->budget_original ?? 0)) - $pooled;
                
                $ob = $item->obligation_amount ?? 0;
                $db = $item->disbursement_amount ?? 0;

                // Logic: Pending Routed are items "For CAF/Obligation" but not yet "Obligated"
                $isPending = ($item->status === 'For CAF/Obligation' && $ob == 0);
                $pendingAmt = $isPending ? $net : 0;

                // Totals for the Group Row
                $totalNetBudget += $net;
                $totalObligated += $ob;
                $totalDisbursed += $db;
                $totalRoutedPending += $pendingAmt;

                return (object) [
                    'id' => $item->id,
                    'source_name' => $item->fundSource->name ?? 'N/A',
                    'activity_name' => $item->activity->name ?? 'N/A',
                    'net_budget' => $net,
                    'status' => $item->status,
                    'obligation_amount' => $ob,
                    'disbursement_amount' => $db,
                    'pending_routed' => $pendingAmt,
                    'savings' => ($db > 0 && $db < $ob) ? ($ob - $db) : 0,
                    'untouched' => ($ob == 0 && !$isPending) ? $net : 0,
                    'obligation_serial' => $item->obligation_serial,
                ];
            });

            // Map Creditors
            $dbedids = \DB::table('employee_fund')->whereIn('fund_id', $group->pluck('id'))->pluck('user_id')->unique();
            $mappedCreditors = $dbedids->map(fn($id) => (object)['full_name' => $employees->get($id)->fullname ?? "ID: $id"]);

            return (object) [
                'id'             => $first->id,
                'dtrack_no'      => $first->dtrack_no,
                'particulars'    => $first->particulars,
                'transaction_date' => $first->transaction_date,
                'created_at'     => $first->created_at,
                'creditors'      => $mappedCreditors,
                'total_net'      => $totalNetBudget,
                'total_ob'       => $totalObligated,
                'total_db'       => $totalDisbursed,
                'total_pending'  => $totalRoutedPending,
                'total_savings'  => $breakdown->sum('savings'),
                'total_untouched'=> $breakdown->sum('untouched'),
                'group_status'   => $this->getPriorityStatus($group),
                'breakdown'      => $breakdown
            ];
        })->values();

        // Stats for Dashboard Widgets
        $awaitingSyncCount = \App\Models\Fund::where('status', 'Obligated')->whereNull('disbursement_date')->count();
        $awaitingOBRN = \App\Models\Fund::where('status', 'For CAF/Obligation')->whereNull('obligation_serial')->count();
        $allSections = $isAdmin ? \DB::connection('db_common')->table('tbl_section')->where('isactive', 1)->pluck('secname', 'secid')->toArray() : [];

        return view('funds.index', compact('funds', 'employees', 'awaitingSyncCount', 'awaitingOBRN', 'allSections', 'isAdmin'));
    }

    /**
     * Helper to determine the priority status of a group
     */
    private function getPriorityStatus($group) {
        if ($group->contains('status', 'Disbursed')) return 'Disbursed';
        if ($group->contains('status', 'Obligated')) return 'Obligated';
        return $group->first()->status;
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

    public function create()
    {
        // Fetch users who have an employee detail link
        // We 'eager load' employeeDetail to avoid N+1 query issues
        $users = \App\Models\User::has('employeeDetail')->with('employeeDetail')->get();

        return view('your-view-name', compact('users'));
    }

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

    //FOR UPDATE TRANSACTION
    public function update(Request $request, $id)
    {
        $primaryFund = Fund::findOrFail($id);
        $oldDtrack = $primaryFund->dtrack_no;

        $validated = $request->validate([
            'dtrack_no'         => 'required|regex:/^\d{4}-\d{6}$/', 
            'transaction_date'  => 'required|date',
            'particulars'       => 'required|string',
            'creditor_ids'      => 'nullable|array',
            'allocations'               => 'required|array|min:1',
            'allocations.*.id'          => 'nullable|exists:funds,id', 
            'allocations.*.source_id'   => 'required|exists:source_of_funds,id',
            'allocations.*.activity_id' => 'required|exists:activities,id',
            'allocations.*.amount'      => 'required|numeric|min:0',
        ]);

        // --- FETCH SECID LOGIC ---
        $userId = auth()->id();
        $secid = null;

        // Handle Admin exception - usually Admin is secid 0
        if (auth()->user()->username === 'admin') {
            $secid = 0; 
        } else {
            // 1. Get dbedid from local table
            $localDetail = \DB::table('employee_details')
                ->where('user_id', $userId)
                ->select('dbedid')
                ->first();

            if ($localDetail && $localDetail->dbedid) {
                // 2. Get secid from db_common.tbl_emp_details
                $secid = \DB::connection('db_common')->table('tbl_emp_details')
                    ->where('dbedid', $localDetail->dbedid)
                    ->value('secid');
            }
        }

        // Safety check: if it's still null and NOT admin, prevent saving to avoid "orphaned" data
        if (is_null($secid) && auth()->user()->username !== 'admin') {
            return response()->json(['message' => 'Unable to determine your Section Assignment. Please update your profile.'], 422);
        }
        // -------------------------

        try {
            DB::beginTransaction();

            // 1. Get IDs of all current rows in the database for this DTrack
            $existingRowIds = Fund::where('dtrack_no', $oldDtrack)->pluck('id')->toArray();
            
            // 2. Get IDs sent from the form
            $submittedRowIds = collect($request->allocations)->pluck('id')->filter()->toArray();

            // 3. Delete rows that were removed in the UI
            $rowsToDelete = array_diff($existingRowIds, $submittedRowIds);
            Fund::whereIn('id', $rowsToDelete)->delete();

            // 4. Process each allocation
            foreach ($request->allocations as $alloc) {
                $data = [
                    'dtrack_no'           => $request->dtrack_no,
                    'transaction_date'    => $request->transaction_date,
                    'particulars'         => $request->particulars,
                    'source_of_fund_id'   => $alloc['source_id'],
                    'transaction_type_id' => $alloc['activity_id'],
                    'amount'              => $alloc['amount'],
                    'status'              => $primaryFund->status,
                    'secid'               => $secid, // Apply the fetched secid
                ];

                if (!empty($alloc['id'])) {
                    // UPDATE existing row
                    $row = Fund::find($alloc['id']);
                    $row->update($data);
                } else {
                    // CREATE new row (for multi-fund splits added during edit)
                    $data['user_id'] = auth()->id(); 
                    $row = Fund::create($data);
                }

                // Sync Payees for this specific row
                $row->creditors()->sync($request->input('creditor_ids', []));
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Updated successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
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

    public function syncWithGoogleSheet($id)
    {
        set_time_limit(600);
        $tempFile = null;

        try {
            $clickedFund = Fund::findOrFail($id);
            $dtrackNo = $clickedFund->dtrack_no;

            // 1. Get the entire group and group them by Source ID
            $fundGroup = Fund::with('fundSource')
                ->where('dtrack_no', $dtrackNo)
                ->get()
                ->groupBy('source_of_fund_id');

            $syncedDetails = []; // To store rich data for the modal

            // 2. Iterate through each unique Source/Spreadsheet
            foreach ($fundGroup as $sourceId => $fundsInSource) {
                $sourceConfig = SourceOfFund::find($sourceId);
                
                if (!$sourceConfig || !$sourceConfig->spreadsheet_id || !$sourceConfig->sheet_name) {
                    continue;
                }

                // 3. Download and Load Spreadsheet
                $client = new \Google\Client();
                $client->setAuthConfig(storage_path('app/google-credentials.json'));
                $client->addScope(\Google_Service_Drive::DRIVE_READONLY);
                $driveService = new \Google_Service_Drive($client);
                $content = $driveService->files->get($sourceConfig->spreadsheet_id, ['alt' => 'media']);
                
                $tempFile = tempnam(sys_get_temp_dir(), 'excel_'); 
                file_put_contents($tempFile, $content->getBody()->getContents());

                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
                $reader->setReadDataOnly(true);
                $reader->setReadFilter(new MyReadFilter(1, 25000, range('B', 'Q'))); 
                $spreadsheet = $reader->load($tempFile);
                $sheet = $spreadsheet->getSheetByName($sourceConfig->sheet_name);
                
                if (!$sheet) continue;

                // 4. Create tracker for this specific source
                $tracker = [];
                foreach ($fundsInSource as $f) {
                    $serial = trim($f->obligation_serial);
                    $tracker[$serial] = [
                        'models' => [], 'netOb' => 0.0, 'netDisb' => 0.0,
                        'latestObDate' => null, 'latestDisbDate' => null,
                        'seenOb' => [], 'seenDisb' => [], 'found' => false
                    ];
                    $tracker[$serial]['models'][] = $f;
                }

                // 5. Scan sheet
                $highestRow = $sheet->getHighestRow();
                for ($row = 1; $row <= $highestRow; $row++) {
                    $sheetSerial = trim((string)$sheet->getCell("C$row")->getValue());
                    if (isset($tracker[$sheetSerial])) {
                        $tracker[$sheetSerial]['found'] = true;
                        $this->processRowData($sheet, $row, $tracker[$sheetSerial]);
                    }
                }

                // 6. Update Database and build the detail list
                foreach ($tracker as $serial => $data) {
                    if ($data['found']) {
                        foreach ($data['models'] as $model) {
                            $finalOb = $data['netOb'];
                            $finalDisb = min($data['netDisb'], $finalOb);
                            $status = $finalDisb > 0 ? 'Disbursed' : 'Obligated';

                            $model->update([
                                'obligation_amount'   => $finalOb,
                                'obligation_date'     => $data['latestObDate'],
                                'disbursement_amount' => $finalDisb,
                                'disbursement_date'   => $data['latestDisbDate'],
                                'status'              => $status,
                                'status_date'         => now()->format('Y-m-d')
                            ]);

                            // ADD TO DETAILS FOR MODAL
                            $syncedDetails[] = [
                                'name'   => $model->fundSource->name ?? 'Unknown',
                                'serial' => $serial,
                                'amount' => number_format($finalOb, 2),
                                'status' => $status
                            ];
                        }
                    }
                }

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $sheet);
                if (file_exists($tempFile)) @unlink($tempFile);
            }

            // Return detailed array to the frontend
            return response()->json([
                'success' => true,
                'details' => [
                    'dtrack_no'    => $dtrackNo,
                    'synced_items' => $syncedDetails, // Detailed objects for JS loop
                    'count'        => count($syncedDetails)
                ]
            ]);

        } catch (\Exception $e) {
            if ($tempFile && file_exists($tempFile)) @unlink($tempFile);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Helper to keep code clean
    private function processRowData($sheet, $row, &$data) {
        $clean = function($val) {
            $raw = str_replace([',', '₱', ' '], '', trim((string)$val));
            if (str_starts_with($raw, '(')) $raw = '-' . trim($raw, '()');
            return is_numeric($raw) ? (float)$raw : 0.0;
        };

        // Obligation
        $obKey = md5($sheet->getCell("B$row")->getValue().$sheet->getCell("I$row")->getValue());
        if (!in_array($obKey, $data['seenOb'])) {
            $data['netOb'] += $clean($sheet->getCell("I$row")->getCalculatedValue());
            $data['seenOb'][] = $obKey;
            $data['latestObDate'] = $this->parseExcelDate($sheet->getCell("B$row")->getValue());
        }

        // Disbursement
        $valQ = $clean($sheet->getCell("Q$row")->getCalculatedValue());
        if ($valQ != 0) {
            $disbKey = md5($sheet->getCell("M$row")->getValue().$valQ);
            if (!in_array($disbKey, $data['seenDisb'])) {
                $data['netDisb'] += $valQ;
                $data['seenDisb'][] = $disbKey;
                $data['latestDisbDate'] = $this->parseExcelDate($sheet->getCell("M$row")->getValue());
            }
        }
    }

    private function parseExcelDate($dateValue)
    {
        if (empty($dateValue)) return null;

        try {
            // If it's a numeric value (Excel Serial Date)
            if (is_numeric($dateValue)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateValue)->format('Y-m-d');
            }

            // If it's a string like "01/23/2026"
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

        $funds = Fund::where('status', 'Obligated')
                    ->whereNull('disbursement_date')
                    ->get();

        $total = $funds->count();
        if ($total === 0) return response()->json(['success' => false]);

        // Initial state
        Cache::put($cacheKey, ['current' => 0, 'total' => $total, 'percent' => 0, 'status' => 'processing'], 600);
        
        // --- CRITICAL: RELEASE SESSION LOCK ---
        // This allows the browser to perform the GET progress request 
        // while this POST request is still looping.
        session_write_close();

        foreach ($funds as $index => $fund) {
            // Check for cancellation signal
            if (Cache::has($cancelKey)) {
                return response()->json(['success' => false, 'message' => 'Cancelled']);
            }

            try {
                $this->syncWithGoogleSheet($fund->id);
            } catch (\Exception $e) {
                \Log::error("Sync failed for ID {$fund->id}");
            }

            $current = $index + 1;
            $percent = round(($current / $total) * 100);

            // Update Cache
            Cache::put($cacheKey, [
                'current' => $current,
                'total' => $total,
                'percent' => $percent,
                'status' => 'processing'
            ], 600);
            
            // --- CRITICAL: FORCE DATABASE COMMIT ---
            // Since you use 'database' for cache, we need to ensure 
            // the record is actually written so the other request sees it.
            if (config('cache.default') == 'database') {
                // Some environments require a small sleep or DB flush to see changes
                usleep(100000); // 0.1 second delay to give the DB breathing room
            }
        }

        return response()->json(['success' => true]);
    }

    // Method to trigger the cancellation
    public function cancelSync()
    {
        Cache::put("sync_cancel_" . Auth::id(), true, 60);
        return response()->json(['success' => true]);
    }

    public function getSyncProgress()
    {
        // Clear the internal Laravel cache for this request to get fresh DB data
        Cache::forget("sync_progress_" . Auth::id()); 
        
        $data = Cache::get("sync_progress_" . Auth::id());
        return response()->json($data ?: ['percent' => 0]);
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
                
                // 1. Get the array of logs
                $logs = $externalData['doc_register_destination'] ?? [];

                // 2. TARGET THE LATEST ENTRY: 
                // We use end() to get the last item in the array, 
                // which represents the most recent movement in DTrack.
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