<?php

namespace App\Http\Controllers;

use App\Models\Fund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\SourceOfFund;
use App\Models\Employee;
use App\Models\Activity;
use Google\Client;            
use Google\Service\Sheets; 
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

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
        // 1. Use 'with' to eager load the relationships
        // This allows $fund->fundSource->name to work in your table
        $query = auth()->user()->is_admin 
            ? \App\Models\Fund::with(['fundSource', 'creditors']) 
            : \App\Models\Fund::with(['fundSource', 'creditors'])->where('user_id', auth()->id());

        $funds = Fund::with(['creditors', 'fundSource', 'activity'])
                ->latest() // This is shorthand for orderBy('created_at', 'desc')
                ->get();
        
        $sources = SourceOfFund::all(); 
        $employees = Employee::orderBy('last_name')->get();
        $activities = Activity::all(); 

        // Count for the notification badge (remains the same)
        $awaitingSyncCount = Fund::where('status', 'Obligated')
                                ->whereNull('disbursement_date')
                                ->whereNotNull('obligation_serial')
                                ->count();

        // Count for the notification badge (remains the same)
        $awaitingOBRN = Fund::where('status', 'Obligated')
                                ->whereNull('obligation_serial')
                                ->count();
        
        return view('funds.index', compact('funds', 'sources', 'employees', 'activities', 'awaitingSyncCount', 'awaitingOBRN'));
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
        
        $validated = $request->validate([
            'dtrack_no' => ['required', 'unique:funds,dtrack_no', 'regex:/^\d{4}-\d{6}$/'],
            'transaction_date' => 'required|date',
            'source_of_fund_id' => 'required|exists:source_of_funds,id',
            'activity_id' => 'required|exists:activities,id',
            'amount' => 'required|numeric|min:0',
            'particulars' => 'required|string',
            'creditor_ids' => 'nullable|array',
            'creditor_ids.*' => 'exists:employees,id',
        ]);

        // Fetch related records
        $activity = Activity::findOrFail($request->activity_id);

        // --- START BUDGET CHECK ---
        // FIXED: Summing based on transaction_type_id (Foreign Key)
        $totalSpent = Fund::where('transaction_type_id', $activity->id)->sum('amount');
        $remaining = $activity->budget - $totalSpent;

        if ($request->amount > $remaining) {
            return response()->json([
                'message' => 'The amount exceeds the remaining budget of ₱' . number_format($remaining, 2)
            ], 422);
        }
        // --- END BUDGET CHECK ---

        // Create the record
        $fund = new Fund();
        $fund->dtrack_no = $validated['dtrack_no'];
        $fund->transaction_date = $validated['transaction_date'];
        $fund->amount = $validated['amount'];
        $fund->particulars = $validated['particulars'];
        $fund->user_id = auth()->id();
        
        // FIXED: Storing the ID instead of the descriptive name
        $fund->source_of_fund_id = $validated['source_of_fund_id'];
        $fund->transaction_type_id = $activity->id; 
        
        // Set default status
        $fund->status = 'Routed';
        $fund->save();

        // Sync the relationships
        $fund->creditors()->sync($request->creditor_ids ?? []);
        
        // Load relationships for response (using the 'activity' relationship we added to the model)
        $fund->load(['fundSource', 'activity', 'creditors']);

        return response()->json([
            'success' => true,
            'message' => 'Transaction ' . $fund->dtrack_no . ' logged successfully!',
            'data'    => $fund 
        ]);
    }

    public function create()
    {
        return view('funds.create');
    }

    public function updateStatus(Request $request, $id)
    {
        $fund = Fund::findOrFail($id);

        // 1. Prepare the update array with fields that are ALWAYS editable
        $updateData = [
            'status_date' => $request->status_date,
            'remarks'     => $request->remarks,
        ];

        // 2. Logic for Status and Serial Number
        // If the transaction is ALREADY synced, we keep the existing status and serial
        if ($fund->status === 'Obligated' && $fund->obligation_amount > 0) {
            // Do nothing for status/serial, they stay as they are in the DB
        } else {
            // If not synced, we allow updating the status and the serial
            $updateData['status'] = $request->status;
            
            if ($request->status === 'Obligated') {
                $updateData['obligation_serial'] = $request->obligation_serial;
            } else {
                // If they move it back to 'For Signature', clear the serial
                $updateData['obligation_serial'] = null;
            }
        }

        $fund->update($updateData);

        return response()->json([
            'success' => true, 
            'message' => 'Status updated successfully!',
            'debug_data' => $updateData // Optional: helps you see what was saved
        ]);
    }

    public function edit(Fund $fund) {
        if ($fund->status !== 'Routed') {
            return response()->json(['message' => 'Editing is only allowed for Routed status.'], 403);
        }
       return response()->json($fund->load('creditors'));
    }

    public function update(Request $request, $id)
    {
        $fund = Fund::findOrFail($id);

        // 1. Validation 
        $validated = $request->validate([
            'dtrack_no'        => 'required|regex:/^\d{4}-\d{6}$/|unique:funds,dtrack_no,' . $id,
            'transaction_date' => 'required|date',
            'source_of_fund_id'=> 'required|exists:source_of_funds,id',
            'activity_id'      => 'required|exists:activities,id',
            'amount'           => 'required|numeric|min:0',
            'particulars'      => 'nullable|string',
            'creditor_ids'     => 'nullable|array', 
            'creditor_ids.*'   => 'exists:employees,id',
        ]);

        // 2. Budget Validation Check
        $activity = Activity::findOrFail($request->activity_id);
        
        // Sum total spent on this activity EXCLUDING the current record's old amount
        $totalSpent = Fund::where('transaction_type_id', $activity->id)
                        ->where('id', '!=', $id)
                        ->sum('amount');
                        
        $remaining = $activity->budget - $totalSpent;

        if ($request->amount > $remaining) {
            return response()->json([
                'message' => 'Update failed. The new amount exceeds the remaining budget of ₱' . number_format($remaining, 2)
            ], 422);
        }

        // 3. Update the Record
        $fund->update([
            'dtrack_no'           => $validated['dtrack_no'],
            'transaction_date'    => $validated['transaction_date'],
            'amount'              => $validated['amount'],
            'particulars'         => $validated['particulars'],
            'source_of_fund_id'   => $validated['source_of_fund_id'],
            'transaction_type_id' => $activity->id, // FIXED: Storing ID, not name
        ]);

        // 4. Sync Relationships
        $fund->creditors()->sync($request->input('creditor_ids', []));

        // 5. Load the relationships (including the 'activity' relationship)
        $fund->load(['fundSource', 'activity', 'creditors']);

        return response()->json([
            'success' => true,
            'message' => 'Transaction ' . $fund->dtrack_no . ' updated successfully!',
            'data'    => $fund 
        ]);
    }

    public function checkBalance(Request $request)
    {
        try {
            $activityId = $request->query('activity_id');
            $inputAmount = (float)$request->query('amount', 0);

            // 1. Find the activity by ID
            $activity = Activity::find($activityId);

            if (!$activity) {
                return response()->json(['error' => 'Activity not found'], 404);
            }

            // 2. Determine the limit (Use budget_adjusted if available, otherwise original budget)
            $activeLimit = !is_null($activity->budget_adjusted) 
                ? (float)$activity->budget_adjusted 
                : (float)$activity->budget;

            // 3. Calculate total spent using transaction_type_id
            // We exclude the current fund ID in one query to be more efficient
            $currentFundId = $request->query('current_fund_id');
            
            $totalSpent = Fund::where('transaction_type_id', $activity->id)
                ->when($currentFundId, function ($query) use ($currentFundId) {
                    return $query->where('id', '!=', $currentFundId);
                })
                ->sum('amount');

            // 4. Calculate the real-time balance using our activeLimit
            $remainingBalance = $activeLimit - (float)$totalSpent;

            return response()->json([
                'status' => 'success',
                'original_budget' => (float)$activity->budget,
                'adjusted_budget' => $activity->budget_adjusted ? (float)$activity->budget_adjusted : null,
                'active_limit' => $activeLimit, // This tells the JS which one was used
                'remaining' => $remainingBalance,
                'is_sufficient' => round($remainingBalance, 2) >= round($inputAmount, 2),
                'formatted_remaining' => number_format($remainingBalance, 2)
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $fund = Fund::findOrFail($id);
        return view('funds.show', compact('fund'));
    }

    public function syncWithGoogleSheet($id)
    {
        set_time_limit(300); 
        $tempFile = null;

        try {
            $fund = Fund::with('fundSource')->findOrFail($id);
            $sourceConfig = SourceOfFund::find($fund->source_of_fund_id);

            if (!$sourceConfig || !$sourceConfig->spreadsheet_id || !$sourceConfig->sheet_name) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Configuration missing. Please set Spreadsheet ID and Sheet Name in Settings.'
                ], 422);
            }

            $client = new \Google\Client();
            $client->setAuthConfig(storage_path('app/google-credentials.json'));
            $client->addScope(\Google_Service_Drive::DRIVE_READONLY);
            
            $driveService = new \Google_Service_Drive($client);
            $spreadsheetId = $sourceConfig->spreadsheet_id;

            $content = $driveService->files->get($spreadsheetId, ['alt' => 'media']);
            $data = $content->getBody()->getContents();

            $tempFile = tempnam(sys_get_temp_dir(), 'excel_'); 
            file_put_contents($tempFile, $data);

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            
            // Define the range you want to read to save memory
            $startRow = 1;
            $endRow = 10000; // Adjust based on your typical sheet size
            $columns = range('B', 'Q'); 
            $reader->setReadFilter(new MyReadFilter($startRow, $endRow, $columns));
            
            $spreadsheet = $reader->load($tempFile);
            $sheet = $spreadsheet->getSheetByName($sourceConfig->sheet_name);
            
            if (!$sheet) {
                throw new \Exception("Tab name '{$sourceConfig->sheet_name}' not found in the Google Sheet.");
            }

            $highestRow = $sheet->getHighestRow();
            $found = false;
            
            // Initialize aggregation variables
            $netObligationAmount = 0;
            $netDisbursementAmount = 0;
            $latestObDate = null;
            $latestDisbDate = null;
            $dbFundSourceName = trim((string)($fund->fundSource->name ?? ''));

            // UPDATED: Helper to handle (95,000.00) as -95000.00
            $cleanAmount = function($val) {
                if ($val === null || $val === '') return 0.0;
                
                $val = trim((string)$val);
                
                // Check for accounting negative format: (1,000.00)
                if (str_starts_with($val, '(') && str_ends_with($val, ')')) {
                    $val = '-' . trim($val, '()');
                }

                // Remove commas, currency symbols, and spaces
                $raw = str_replace([',', '₱', ' '], '', $val);
                
                return (float) $raw;
            };

            // Iterate through all rows to sum original entries and adjustments
            for ($row = 1; $row <= $highestRow; $row++) { 
                $sheetSerial = trim((string)$sheet->getCell("C$row")->getValue());
                $sheetFundSource = trim((string)$sheet->getCell("G$row")->getValue());

                // Match Serial Number (Column C)
                if ($sheetSerial == trim((string)$fund->obligation_serial)) {
                    
                    // Match Fund Source (Column G) if specified
                    if ($sheetFundSource !== '' && $sheetFundSource !== $dbFundSourceName) {
                        continue; 
                    }

                    $found = true;

                    // SUM OBLIGATIONS FROM COLUMN I (Netting out deobligations)
                    $valI = $cleanAmount($sheet->getCell("I$row")->getCalculatedValue());
                    $netObligationAmount += $valI;

                    // Capture obligation date (Column B)
                    $currentObDate = $this->parseExcelDate($sheet->getCell("B$row")->getValue());
                    if ($currentObDate) $latestObDate = $currentObDate;

                    // SUM DISBURSEMENTS FROM COLUMN Q (Netting out adjustments)
                    $valQ = $cleanAmount($sheet->getCell("Q$row")->getCalculatedValue());
                    $netDisbursementAmount += $valQ;

                    // Capture disbursement date (Column M)
                    $currentDisbDate = $this->parseExcelDate($sheet->getCell("M$row")->getValue());
                    if ($currentDisbDate) $latestDisbDate = $currentDisbDate;
                }
            }

            if ($found) {
                $finalUpdateData = [
                    'obligation_amount' => $netObligationAmount,
                    'obligation_date'   => $latestObDate,
                ];

                // Logical check for status based on NET disbursement
                if ($netDisbursementAmount > 0) {
                    $finalUpdateData['status'] = 'Disbursed';
                    $finalUpdateData['disbursement_amount'] = $netDisbursementAmount;
                    $finalUpdateData['disbursement_date']   = $latestDisbDate;
                    $finalUpdateData['status_date'] = now()->format('Y-m-d');
                } else {
                    // Revert to Obligated if net disbursement is 0 (Cancellation)
                    $finalUpdateData['status'] = 'Obligated';
                    $finalUpdateData['disbursement_amount'] = 0;
                    $finalUpdateData['disbursement_date'] = null;
                }

                $fund->update($finalUpdateData);
            }

            if ($tempFile && file_exists($tempFile)) { unlink($tempFile); }

            if (!$found) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Serial number ' . $fund->obligation_serial . ' not found in sheet.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Synced successfully!',
                'new_amount' => number_format($netObligationAmount, 2),
                'new_status' => $fund->status
            ]);

        } catch (\Exception $e) {
            if ($tempFile && file_exists($tempFile)) { unlink($tempFile); }
            \Log::error("Sync Error: " . $e->getMessage()); 
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
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
            $fund = Fund::findOrFail($id);

            // Security check: ensure it's still "Routed" before deleting
            // Transactions beyond "Routed" usually have audit trails or serial numbers
            if ($fund->status !== 'Routed') {
                return response()->json([
                    'success' => false,
                    'message' => 'This transaction can no longer be deleted because it is already ' . $fund->status
                ], 403);
            }

            // Use forceDelete() to permanently remove the record from the database.
            // This frees up the DTRACK NO. so it can be used again immediately.
            $fund->forceDelete(); 

            return response()->json([
                'success' => true,
                'message' => 'Transaction permanently deleted. DTRACK NO. is now available.'
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
                $externalData = $dtrackService->getDTrackStatus($fund->dtrack_no);
                $latestLog = $externalData['doc_register_destination'][0] ?? null;

                if ($latestLog) {
                    $dtrackStatus = $latestLog['actreq_desc'] ?? '';
                    $office = $latestLog['dest_office'] ?? 'Unknown Office';
                    $dtrackUpdateDate = $latestLog['docdet_rlsd_dateupdated'] ?? null;

                    // Apply logic
                    if ($fund->status === 'Obligated') {
                        $fund->remarks = "Currently at: {$office} ({$dtrackStatus})";
                    } else {
                        $fund->status = $this->mapStatus($dtrackStatus); // Now this method exists!
                        $fund->remarks = "Currently at: {$office}";
                    }

                    if ($dtrackUpdateDate) {
                        $fund->dtrack_update_date = \Carbon\Carbon::parse($dtrackUpdateDate);
                    }

                    $fund->status_date = now();
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
            'For CAF/Obligation (Budget)' => 'Obligated',
            'For Processing', 'For Processing (Accounting)' => 'Processing',
            default => $dtrackStatus,
        };
    }


}