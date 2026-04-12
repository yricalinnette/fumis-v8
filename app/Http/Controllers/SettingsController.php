<?php

namespace App\Http\Controllers;
use App\Models\SourceOfFund;
use App\Models\Employee; 
use App\Models\Activity; 
use App\Models\Fund; 
use App\Models\ImportTemplate; 
use App\Imports\WfpActivitiesImport;
use App\Models\External\CommonDivision;
use App\Models\External\CommonEmpDetail;
use App\Models\External\CommonEmployee;
use App\Models\External\CommonPosition;
use App\Models\External\CommonSection;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\BudgetLineItem;
use App\Models\UacsCode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Barryvdh\DomPDF\Facade\Pdf;

class SettingsController extends Controller
{
    public function index()
    {
        // 1. Current Year for logic checks
        $currentYear = date('Y');

        // 2. Fetch all Sources (showing all years for the list)
        $sources = SourceOfFund::orderBy('fiscal_year', 'desc')->get();

        // 4. Fetch ALL Activities from all years
        $activities = Activity::with('source')
            ->join('source_of_funds', 'activities.source_of_fund_id', '=', 'source_of_funds.id')
            ->select('activities.*')
            ->orderBy('source_of_funds.fiscal_year', 'desc')
            ->get();

        // 5. Fetch template
        $template = \App\Models\ImportTemplate::first();

        // 6. Fetch helper lists for the form
        $objectives = \App\Models\Activity::whereNotNull('objective')->distinct()->pluck('objective');
        $budgetLineItems = \App\Models\BudgetLineItem::where('is_active', 1)
                        ->orderBy('budget_line_item_name', 'asc')
                        ->get();

        // UPDATED: Filter fund sources to only show the current fiscal year
        $fundSources = \App\Models\SourceOfFund::where('fiscal_year', $currentYear)
            ->orderBy('name')
            ->get();

        $uacsCodes = \App\Models\UacsCode::orderBy('uacs_code', 'asc')->get();

        //CONNECT TO SPMS API to get the list of objectives for the dropdown
        // Change this flag to true to enable live API fetching. Remember to set the SPMS_API_URL, SPMS_SYSTEM_NAME, and SPMS_KEY in your .env file for it to work.
        $isSpmsOnline = false; 

        if ($isSpmsOnline) {
            $objectives = Cache::remember('spms_objectives', 86400, function () {
                try {
                    $response = Http::timeout(5)->post(env('SPMS_API_URL'), [
                        'systemname' => env('SPMS_SYSTEM_NAME'),
                        'key'        => env('SPMS_KEY'),
                    ]);
                    return $response->successful() ? $response->json()['data'] : [];
                } catch (\Exception $e) {
                    return [];
                }
            });
        } else {
            // MOCK DATA: Exactly how the SPMS JSON is expected to look
            $objectives = [
                ['objectives' => 'To provide LGUs with competencies and resources for health system strengthening in support of the Health Sector 8-Point Action Agenda'],
                ['objectives' => 'To catalyze the transformation of local health systems to province-wide and city-wide health system'],
                ['objectives' => 'To strengthen engagements with stakeholders towards a well-coordinated and aligned implementation of the 8-Point Action Agenda'],
                ['objectives' => 'To ensure that relevant policies, guidelines, and programs are cascaded to LGUs and other health partners'],
                ['objectives' => 'To ensure efficacy on the provision of technical assistance to LGUs and other health partners towards the achievement of UHC'],
                ['objectives' => 'To ensure systematic preventive and corrective maintenance of all IT equipment and the effective delivery of other related ICT services.'],
                ['objectives' => 'To ensure that internal clients are effectively supported through the transformation of office processes and that external clients receive reliable, high-quality information via the agency website, thereby enhancing operational efficiency and service delivery.'],
                ['objectives' => 'To ensure the cybersecurity posture of DOH-EVCHD digital solutions and applications, safeguarding data integrity, confidentiality, and availability.'],
                ['objectives' => 'To ensure alignment of policies, programs and standards towards sectoral goals on equity, access and quality of care'],
                ['objectives' => 'To ensure efficient utilization of DOH funds'],
                ['objectives' => 'To increase capacity of DOH personnel in order to improve workplace performance.'],
                ['objectives' => 'To ensure compliance with cross-cutting requirements based on standard procedures and timelines in accordance to Anti-Red Tape Authority (ARTA) and other relevant laws'],
                ['objectives' => 'Submission of reportorial requirements.'],
                ['objectives' => 'To ensure delivery of quality service through performance of other task assigned in Committees (As clearing house and Inspection for ICT Supplies / Equipment and Licenses).']
            ];
        }
        return view('admin.settings', compact(
            'sources', 
            'activities', 
            'template', 
            'currentYear', 
            'objectives', 
            'budgetLineItems', 
            'fundSources',
            'uacsCodes',
            'objectives'
        ));
    }

    //FOR ACCOUNT MANAGEMENT
    public function userIndex()
    {
        // 1. Filter: where('is_admin', 0) excludes the administrator
        // 2. Eager Load: loading the full 4-table chain to prevent N+1 errors
        $users = \App\Models\User::where('is_admin', 0)
            ->with([
                'employeeDetail.commonDetail.employee',
                'employeeDetail.commonDetail.position',
                'employeeDetail.commonDetail.section'
            ])
            ->get();

        return view('admin.settings.account_management', compact('users'));
    }

    //FOR BUDGET LINE ITEM MANAGEMENT
    public function budgetLineItems()
    {
        // Fetch only the budget line items for simplified management
        $items = \App\Models\BudgetLineItem::orderBy('budget_line_item_name', 'asc')
                    ->get();

        return view('admin.settings.budget_line_items', compact('items'));
    }

    //FOR ADDING OF BUDGET LINE ITEM
    public function storeBudgetLineItem(Request $request)
    {
        $request->validate([
            'budget_line_item_name' => 'required|unique:budget_line_items,budget_line_item_name',
        ]);

        \App\Models\BudgetLineItem::create([
            'budget_line_item_name' => $request->budget_line_item_name,
            // If the checkbox 'is_active' is missing, default to false (0)
            'is_active' => $request->has('is_active'), 
        ]);

        return redirect()->back()->with('success', 'Line Item added!');
    }

    // Update Budget Line Item
    public function updateBudgetLineItem(Request $request, $id) 
    {
        // Validate
        $request->validate([
            'budget_line_item_name' => 'required|unique:budget_line_items,budget_line_item_name,' . $id,
        ]);

        // Find and Update
        $item = \App\Models\BudgetLineItem::findOrFail($id);
        $item->update([
            'budget_line_item_name' => $request->budget_line_item_name,
            'is_active' => $request->has('is_active') ? 1 : 0,
        ]);

        return redirect()->back()->with('success', 'Updated successfully!');
    }

    // Delete Budget Line Item
    public function destroyBudgetLineItem($id)
    {
        BudgetLineItem::findOrFail($id)->delete();
        return redirect()->back()->with('success', 'Item deleted successfully!');
    }

    //FOR FUND SOURCE MANAGEMENT
    public function fundSources()
    {
        // 1. Fetch Fund Sources with relationships to avoid N+1 query issues in the table
        // 'budgetLineItem' allows us to see the name in the badge
        // 'activities' allows us to sum the pooled_amount
        $sources = \App\Models\SourceOfFund::with(['budgetLineItem', 'activities'])
                    ->orderBy('name', 'asc')
                    ->get();

        // 2. Fetch all Budget Line Items for the "Add/Edit" modal dropdowns
        // This is the variable that was missing!
        $budgetLineItems = \App\Models\BudgetLineItem::orderBy('budget_line_item_name', 'asc')->get();

        // 3. Pass both variables to the view
        return view('admin.settings.fund_sources', compact('sources', 'budgetLineItems'));
    }

    // Delete Fund Sources
    public function destroyFundSource($id)
    {
        SourceOfFund::findOrFail($id)->delete();
        return redirect()->back()->with('success', 'Fund source deleted successfully!');
    }


    public function storeSource(Request $request) 
    {
        // 1. Validation with updated composite unique check
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                // Check if 'name' + 'fiscal_year' + 'budget_line_item_id' combination already exists
                Rule::unique('source_of_funds')->where(function ($query) use ($request) {
                    return $query->where('fiscal_year', $request->fiscal_year)
                                ->where('budget_line_item_id', $request->budget_line_item_id);
                }),
            ],
            'budget_line_item_id' => 'required|exists:budget_line_items,id', // Added validation
            'fiscal_year'         => 'required|integer',
            'total_amount'        => 'required|numeric|min:0',
            'spreadsheet_id'      => 'nullable|string',
            'sheet_name'          => 'nullable|string',
        ], [
            // Custom error messages
            'name.unique' => "The fund source '{$request->name}' already exists for this budget line in {$request->fiscal_year}.",
            'budget_line_item_id.required' => "Please select a Budget Line Item.",
        ]);

        try {
            // 2. Creation 
            // $validated now includes budget_line_item_id
            SourceOfFund::create($validated);

            return back()->with('success', 'Fund source added successfully!');

        } catch (\Exception $e) {
            // 3. Log genuine system failures
            Log::error("Fund Source Storage Error: " . $e->getMessage());
            return back()->withErrors(['error' => "A system error occurred while saving: " . $e->getMessage()]);
        }
    }

    public function updateSource(Request $request, $id)
    {
        $source = SourceOfFund::findOrFail($id);
        
        $validated = $request->validate([
            'name' => [
                'required', 
                'string',
                'max:255',
                // Composite unique check: Name + Year + Budget Line Item
                Rule::unique('source_of_funds')->where(function ($query) use ($request) {
                    return $query->where('fiscal_year', $request->fiscal_year)
                                ->where('budget_line_item_id', $request->budget_line_item_id);
                })->ignore($id),
            ],
            'budget_line_item_id' => 'required|exists:budget_line_items,id', // Added validation
            'fiscal_year'         => 'required|integer',
            'total_amount'        => 'required|numeric|min:0',
            'spreadsheet_id'      => 'nullable|string',
            'sheet_name'          => 'nullable|string',
        ], [
            'name.unique' => "This fund source name already exists for the selected budget line and year.",
            'budget_line_item_id.required' => "Please select a Budget Line Item.",
        ]);

        // 1. Calculate the total budget already distributed to activities
        // (Ensure your SourceOfFund model has the 'activities' relationship defined)
        $totalAllocatedToActivities = $source->activities()->sum('budget_adjusted');

        // 2. Safeguard: Prevent reducing Source Amount below what is already promised to activities
        if ($request->total_amount < $totalAllocatedToActivities) {
            return back()->withErrors([
                'total_amount' => "Constraint Error: Total amount (₱" . number_format($request->total_amount, 2) . 
                                ") is less than already allocated funds (₱" . number_format($totalAllocatedToActivities, 2) . 
                                "). Please adjust activity budgets before reducing the source allotment."
            ])->withInput();
        }

        // 3. Update the record
        $source->update($validated);

        return back()->with('success', 'Fund source updated successfully!');
    }

    //FOR UACS CODE MANAGEMENT
    public function uacsCodes(Request $request)
    {
        $query = UacsCode::query();

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('uacs_code', 'like', "%{$search}%")
                ->orWhere('account_title', 'like', "%{$search}%");
        }

        $uacs = $query->paginate(15)->withQueryString(); // withQueryString keeps the search term when you click "Next"
        return view('admin.settings.uacs_code', compact('uacs'));
    }

    public function storeUACSCodes(Request $request)
    {
        $validated = $request->validate([
            'uacs_code' => 'required|string|max:50|unique:uacs_codes,uacs_code',
            'account_title' => 'required|string|max:255',
            'allotment_class' => 'required|in:PS,MOOE,CO,FinEx',
        ], [
            'uacs_code.unique' => 'This UACS code already exists in the system.',
        ]);

        UacsCode::create($validated);

        return redirect()->back()->with('success', 'UACS Code added successfully!');
    }

    public function updateUACSCodes(Request $request, $id)
    {
        $uacs = UacsCode::findOrFail($id);

        $validated = $request->validate([
            // unique check ignores the current record ID
            'uacs_code' => 'required|string|max:50|unique:uacs_codes,uacs_code,' . $id,
            'account_title' => 'required|string|max:255',
            'allotment_class' => 'required|in:PS,MOOE,CO,FinEx',
        ]);

        $uacs->update($validated);

        return redirect()->back()->with('success', 'UACS Code updated successfully!');
    }

    public function destroyUACSCodes($id)
    {
        $uacs = UacsCode::findOrFail($id);
        
        // Optional: Add a check to see if this code is being used by activities
        // if ($uacs->activities()->exists()) {
        //     return redirect()->back()->with('error', 'Cannot delete! This code is linked to existing activities.');
        // }

        $uacs->delete();

        return redirect()->back()->with('success', 'UACS Code deleted successfully!');
    }

    public function storeEmployee(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name'=> 'nullable|string|max:255', // Not required
            'last_name'  => 'required|string|max:255',
            'suffix'     => 'nullable|string|max:10',  // Not required
            'position'   => 'required|string|max:255',
        ]);

        \App\Models\Employee::create($request->all());

        return redirect()->back()->with('success', 'Employee added successfully!');
    }

    public function storeActivity(Request $request)
    {
        // 1. Validate the input
        $request->validate([
            'source_of_fund_id' => 'required|exists:source_of_funds,id',
            'name' => 'required|string|max:255',
            'budget' => 'required|numeric|min:0',
        ]);

        // 2. Optional: Check if total activities exceed the Source's total amount
        $source = \App\Models\SourceOfFund::findOrFail($request->source_of_fund_id);
        $alreadyAllocated = \App\Models\Activity::where('source_of_fund_id', $source->id)->sum('budget');
        
        if (($alreadyAllocated + $request->budget) > $source->total_amount) {
            $remaining = $source->total_amount - $alreadyAllocated;
            return redirect()->back()->withErrors([
                'error' => "Insufficient budget in {$source->name}. Available: ₱" . number_format($remaining, 2)
            ]);
        }

        // 3. Create the Activity
        \App\Models\Activity::create([
            'source_of_fund_id' => $request->source_of_fund_id,
            'name' => $request->name,
            'budget' => $request->budget,
        ]);

        return redirect()->back()->with('success', 'Activity added successfully!');
    }

    public function destroyActivity($id)
    {
        // 1. Find the activity and its source_of_fund_id
        $activity = Activity::findOrFail($id);

        // 2. Query the Fund table for a match on BOTH columns
        $isUsed = \App\Models\Fund::where('source_of_fund_id', $activity->source_of_fund_id)
                    ->where('transaction_type_id', $activity->id) // Linking Activity ID to transaction_type_id
                    ->exists();

        // 3. Block deletion if a record is found
        if ($isUsed) {
            return redirect()->back()->with('error', "Deletion Denied: This activity has active transactions recorded under '{$activity->source->name}'.");
        }

        try {
            $activity->delete();
            return redirect()->back()->with('success', 'Activity deleted successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function destroySource($id)
    {
        // 1. Find the source or fail
        $source = SourceOfFund::findOrFail($id);

        // 2. Check for transactions in the funds table
        // If transactions exist, we stop here to prevent data orphans
        $hasTransactions = \App\Models\Fund::where('source_of_fund_id', $id)->exists();

        if ($hasTransactions) {
            return back()->withErrors([
                'error' => "Cannot delete '{$source->name}'. Financial transactions are already recorded against this source."
            ]);
        }

        // 3. Perform Cascade Delete
        // Since there are no transactions, we delete all linked activities first
        $activityCount = $source->activities()->count();
        $source->activities()->delete(); 

        // 4. Delete the Fund Source
        $source->delete();

        $message = "Fund source deleted successfully!";
        if ($activityCount > 0) {
            $message .= " Also removed {$activityCount} linked activities.";
        }

        return back()->with('success', $message);
    }

    //for updating the wfp import settings configurations
    public function updateTemplate(Request $request, $id)
    {
        // 1. Validate the input to ensure mapping names are provided
        $validated = $request->validate([
            'header_row'      => 'required|integer|min:1',
            'budget_line_col' => 'required|string',
            'objective_col'   => 'required|string',
            'activity_col'    => 'required|string',
            'budget_col'      => 'required|string',
            'source_col'      => 'required|string',
            'uacs_col'        => 'required|string',
        ]);

        // 2. Use updateOrCreate to handle both fresh installs and existing data
        // This looks for ID 1. If it doesn't exist, it creates it.
        \App\Models\ImportTemplate::updateOrCreate(
            ['id' => 1], 
            $validated
        );

        return redirect()->back()->with('success', 'WFP Template mapping updated successfully!');
    }

    //for BUDGET REALIGNMENT
    public function updateAllocation(Request $request)
    {
        $request->validate([
            'source_id' => 'required|exists:source_of_funds,id',
            'adjustments' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $source = SourceOfFund::findOrFail($request->source_id);
            
            // 1. Sanitize the adjustments array (remove commas)
            $adjustments = collect($request->adjustments)->map(function ($value) {
                return (float) str_replace(',', '', $value);
            })->toArray();

            // 2. Validate Total Sum against Fund Source (Strict Balance Check)
            // We use round to 2 decimals to handle float precision issues
            if (round(array_sum($adjustments), 2) !== round($source->total_amount, 2)) {
                return back()->with('error', 'The total adjusted budget (₱' . number_format(array_sum($adjustments), 2) . ') must exactly match the Source Total (₱' . number_format($source->total_amount, 2) . ').');
            }

            // 3. Process Adjustments
            foreach ($adjustments as $activityId => $newAmount) {
                $activity = Activity::findOrFail($activityId);
                
                // --- NEW: MATCHING FRONTEND LOCK LOGIC ---
                
                // A. Officially Obligated
                $obligated = $activity->transactions()->sum('obligation_amount') ?? 0;

                // B. Pending/Routed (Unobligated transactions)
                $pending = $activity->transactions()
                    ->where(function($q) {
                        $q->whereNull('obligation_amount')
                        ->orWhere('obligation_amount', 0);
                    })
                    ->sum('amount') ?? 0;

                // C. Pooled amount
                $pooled = $activity->pooled_amount ?? 0;

                // The Absolute Floor
                $minLimit = $obligated + $pending + $pooled;

                // Safety check: Don't allow reduction below the "Locked" funds
                if (round($newAmount, 2) < round($minLimit, 2)) {
                    return back()->with('error', "Cannot reduce {$activity->name} below its locked limit of ₱" . number_format($minLimit, 2) . " (Includes Pending, Obligated, and Pooled funds).");
                }

                // 4. Update the activity
                if ((float)$activity->budget == 0) {
                    $activity->update([
                        'budget' => $newAmount,
                        'budget_adjusted' => $newAmount
                    ]);
                } else {
                    $activity->update([
                        'budget_adjusted' => $newAmount
                    ]);
                }
            }

            DB::commit();
            // Redirect back to the specific tab
            return redirect(url()->previous() . '#tabs-realignment')->with('success', 'Budget realignment applied successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Critical Error: ' . $e->getMessage());
        }
    }

    public function getRealignmentTable($id)
    {
        $source = SourceOfFund::with(['activities.funds'])->findOrFail($id);
        
        // Point this specifically to the small file we created
        return view('admin.settings.partials._realignment_table', compact('source'))->render();
    }


    public function poolFunds(Request $request)
    {
        $request->validate([
            'activity_id' => 'required|exists:activities,id',
            'amount' => 'required|numeric|min:0',
            'remarks' => 'nullable|string|max:1000', // Validate the new field
        ]);

        $activity = Activity::findOrFail($request->activity_id);
        
        $activity->update([
            'pooled_amount' => $request->amount,
            'pooled_remarks' => $request->remarks, // Save to database
        ]);

        return back()->with('success', 'Funds pooled and remarks recorded.');
    }

    //for manual encoding of WFP
    public function storeWfp(Request $request)
    {
        // 1. Unified Validation
        $isEdit = $request->filled('id');

        $validated = $request->validate([
            'id'                  => 'nullable|exists:activities,id',
            'objective'           => $isEdit ? 'nullable|string' : 'required|string',
            'budget_line_item_id' => $isEdit ? 'nullable|exists:budget_line_items,id' : 'required|exists:budget_line_items,id', 
            'source_of_fund_id'   => $isEdit ? 'nullable|exists:source_of_funds,id' : 'required|exists:source_of_funds,id',
            'uacs_code_id'        => $isEdit ? 'nullable|exists:uacs_codes,id' : 'required|exists:uacs_codes,id',
            'name'                => $isEdit ? 'nullable|string' : 'required|string',
            'budget_amount'       => $isEdit ? 'nullable|numeric|min:0' : 'required|numeric|min:0',
            'start_date'          => 'required|date',
            'end_date'            => 'required|date|after_or_equal:start_date',
            'target_quarters'     => 'required|array|min:1',
            'targets'             => 'required|array',
            'classification'      => 'required|in:Strategic,Core,Support',
        ], [
            'target_quarters.required' => 'Please select at least one target quarter.',
        ]);

        // 2. Validate numeric targets
        foreach ($request->target_quarters as $q) {
            if (!isset($request->targets[$q]) || $request->targets[$q] <= 0) {
                return back()->withInput()->withErrors(['targets' => "Please enter a valid numeric target for {$q}."]);
            }
        }

        // Prepare Physical Targets
        $selectedQuarters = $request->target_quarters ?? [];
        $physicalTargets = [];
        foreach ($selectedQuarters as $q) {
            $physicalTargets[$q] = $request->targets[$q];
        }

        // 3. Handle Update Logic
        if ($isEdit) {
            $activity = Activity::findOrFail($request->id);
            $hasTransactions = $activity->transactions()->exists(); 
            $isLocked = $activity->is_locked ?? false;

            if ($hasTransactions || $isLocked) {
                $activity->update([
                    'start_date'       => $validated['start_date'],
                    'end_date'         => $validated['end_date'],
                    'target_quarters'  => $selectedQuarters,
                    'physical_targets' => $physicalTargets,
                ]);
                $msg = 'Activity timeframe and targets updated. Other fields were locked due to existing transactions.';
            } else {
                $uacsRecord = \App\Models\UacsCode::findOrFail($request->uacs_code_id);
                $activity->update([
                    'objective'        => $request->objective,
                    'uacs_code_id'     => $request->uacs_code_id,
                    'uacs_code'        => $uacsRecord->uacs_code,
                    'name'             => $request->name,
                    'budget'           => $request->budget_amount,
                    'budget_adjusted'  => $request->budget_amount,
                    'start_date'       => $validated['start_date'],
                    'end_date'         => $validated['end_date'],
                    'target_quarters'  => $selectedQuarters,
                    'physical_targets' => $physicalTargets,
                    'classification'   => $request->classification,
                ]);
                $msg = 'Activity updated successfully!';
            }
        } 
        // 4. Handle Create Logic
        else {
            // --- START: FETCH SECID LOGIC ---
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

            // Error if non-admin has no secid
            if (is_null($secid) && auth()->user()->username !== 'admin') {
                return back()->withInput()->withErrors(['error' => 'Your account is not correctly linked to a DOH Section.']);
            }
            // --- END: FETCH SECID LOGIC ---

            $fundSource = SourceOfFund::findOrFail($validated['source_of_fund_id']);
            $uacsRecord = \App\Models\UacsCode::findOrFail($validated['uacs_code_id']);

            // Budget Balance Check
            $totalSpent = Activity::where('source_of_fund_id', $fundSource->id)->sum('budget_adjusted');
            $remainingBalance = $fundSource->total_amount - $totalSpent;

            if ($validated['budget_amount'] > $remainingBalance) {
                return back()->withInput()->withErrors(['budget_amount' => "Insufficient funds. Remaining: ₱" . number_format($remainingBalance, 2)]);
            }

            // Duplicate Check
            $exists = Activity::where([
                ['objective', $validated['objective']],
                ['budget_line_item_id', $validated['budget_line_item_id']],
                ['source_of_fund_id', $validated['source_of_fund_id']],
                ['uacs_code_id', $validated['uacs_code_id']],
                ['name', $validated['name']],
                ['budget', $validated['budget_amount']],
            ])->exists();

            if ($exists) {
                return back()->withInput()->withErrors(['duplicate' => 'This exact activity already exists.']);
            }

            // CREATE WITH USER AND SECTION INFO
            Activity::create([
                'user_id'             => $userId,
                'section_id'          => $secid,
                'objective'           => $validated['objective'],
                'budget_line_item_id' => $validated['budget_line_item_id'],
                'source_of_fund_id'   => $validated['source_of_fund_id'],
                'uacs_code_id'        => $validated['uacs_code_id'],
                'uacs_code'           => $uacsRecord->uacs_code,
                'name'                => $validated['name'],
                'budget'              => $validated['budget_amount'],
                'budget_adjusted'     => $validated['budget_amount'],
                'start_date'          => $validated['start_date'],
                'end_date'            => $validated['end_date'],
                'target_quarters'     => $selectedQuarters,
                'physical_targets'    => $physicalTargets,
                'classification'      => $validated['classification'],
            ]);
            $msg = 'Activity saved successfully!';
        }

        return back()->with('success', $msg);
    }

    public function editWfp($id)
    {
        $activity = Activity::findOrFail($id);
        
        // Return as JSON so your JavaScript $.get() can read it
        return response()->json($activity);
    }

    // print wfp
    public function printWfp(Request $request, $id = null)
    {
        $query = Activity::with(['uacsCode', 'source', 'budgetLineItem']);

        // 1. Filter activities based on parameters
        if ($id) {
            $activities = $query->where('id', $id)->get();
        } elseif ($request->has('year')) {
            $activities = $query->whereHas('source', function($q) use ($request) {
                $q->where('fiscal_year', $request->year);
            })->get();
        } else {
            $activities = $query->whereHas('source', function($q) {
                $q->where('fiscal_year', 2026);
            })->get();
        }

        // 2. Map Section Names from db_common
        // Get all unique section IDs from the activities we just fetched
        $sectionIds = $activities->pluck('section_id')->unique()->filter()->toArray();

        // Fetch names from db_common using your join logic
        $sectionMap = \DB::connection('db_common')->table('tbl_section')
            ->whereIn('secid', $sectionIds)
            ->pluck('secabbrev', 'secid'); // Creates an array: [id => name]

        // 3. Attach names to activities and determine header section name
        foreach ($activities as $activity) {
            if ($activity->section_id == 0) {
                $activity->computed_secname = 'REGIONAL OFFICE';
            } else {
                $activity->computed_secname = $sectionMap[$activity->section_id] ?? 'N/A';
            }
        }

        // Use the first activity's section for the PDF header label
        $sectionName = $activities->first()->computed_secname ?? 'N/A';

        $calendarYear = $activity && $activity->source 
            ? $activity->source->fiscal_year 
            : ($request->year ?? 2026);

        $meta = [
            'prepared_by'  => 'ELMA BERNADETTE B. LEGO',
            'recommending' => 'CATHERINE L. MIRAL, MD, MPH, CESe',
            'approved_by'  => 'EXUPERIA B. SABALBERINO, MD, MPH, CESe',
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reports.wfp_pdf', compact('activities', 'meta', 'sectionName', 'calendarYear'))
                    ->setPaper('a4', 'landscape');

        return $pdf->stream('WFP_Report.pdf');
    }

    // register new user account or update existing user accounts
    public function registerEmployee(Request $request)
    {
        // 1. Validation
        $request->validate([
            'empid'    => 'required|integer', // Represents the dbedid
            'username' => 'required|string|max:255', 
            'password' => 'required|min:8',
        ]);

        $key = config('app.db_common_key') ?? env('DB_COMMON_ENCRYPTION_KEY');
        
        // 2. Generate the Searchable Hash (One-way)
        // We use strtolower to ensure "JohnDoe" and "johndoe" result in the same hash.
        $usernameHash = hash('sha256', strtolower($request->username));

        // 3. Prepare Encrypted Value for MySQL
        // Note: We use DB::raw for the update/create to let MySQL handle the AES encryption.
        $encryptedUsername = DB::raw("AES_ENCRYPT('{$request->username}', '{$key}')");

        DB::beginTransaction();

        try {
            // CASE A: Check if this Employee (dbedid) is already linked to ANY user
            $existingDetail = \App\Models\EmployeeDetail::where('dbedid', $request->empid)->first();

            if ($existingDetail) {
                $user = $existingDetail->user;
                $user->update([
                    'username'      => $encryptedUsername,
                    'username_hash' => $usernameHash, // Store the hash for login
                    'password'      => Hash::make($request->password),
                ]);
                $message = 'User account credentials updated and encrypted!';
            } 
            else {
                // CASE B & C: Check if a User with this username hash already exists (unlinked)
                $user = \App\Models\User::where('username_hash', $usernameHash)->first();

                if ($user) {
                    // Link existing unlinked user to this dbedid
                    $user->employeeDetail()->updateOrCreate(
                        ['user_id' => $user->id],
                        ['dbedid' => $request->empid]
                    );
                    
                    $user->update([
                        'username' => $encryptedUsername,
                        'password' => Hash::make($request->password)
                    ]);
                    
                    $message = 'Existing user account linked and secured!';
                } 
                else {
                    // CASE D: Brand new User and brand new Link
                    $user = \App\Models\User::create([
                        'username'      => $encryptedUsername,
                        'username_hash' => $usernameHash,
                        'password'      => Hash::make($request->password),
                        'is_admin'      => 0,
                    ]);

                    $user->employeeDetail()->create([
                        'dbedid' => $request->empid,
                    ]);

                    $message = 'New encrypted user account created and linked!';
                }
            }

            DB::commit();
            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'System error: ' . $e->getMessage()]);
        }
    }

    public function searchExternal(Request $request)
    {
        $term = $request->get('q');
        $key = config('app.db_common_key') ?? env('DB_COMMON_ENCRYPTION_KEY');

        // 1. Subquery for latest record
        $latestDetails = DB::connection('db_common')->table('tbl_emp_details')
            ->select('empid', DB::raw('MAX(dbedid) as latest_id'))
            ->groupBy('empid');

        // 2. Main Query with Decryption
        $employees = DB::connection('db_common')->table('tbl_employee')
            ->joinSub($latestDetails, 'latest_status', function ($join) {
                $join->on('tbl_employee.empid', '=', 'latest_status.empid');
            })
            ->join('tbl_emp_details', 'latest_status.latest_id', '=', 'tbl_emp_details.dbedid')
            ->where('tbl_employee.isactive', 1)
            ->where(function ($query) use ($term, $key) {
                // Decrypting in the WHERE clause so we can search via plain text
                $query->whereRaw("CAST(AES_DECRYPT(tbl_employee.fname, '{$key}') AS CHAR) LIKE ?", ["%$term%"])
                    ->orWhereRaw("CAST(AES_DECRYPT(tbl_employee.lname, '{$key}') AS CHAR) LIKE ?", ["%$term%"])
                    ->orWhereRaw("CAST(AES_DECRYPT(tbl_employee.mname, '{$key}') AS CHAR) LIKE ?", ["%$term%"]);
            })
            ->select(
                'tbl_emp_details.dbedid', 
                // This CAST is CRITICAL to prevent the Malformed UTF-8 error in JSON responses
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.fname, '{$key}') AS CHAR)) as fname"),
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.lname, '{$key}') AS CHAR)) as lname"),
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.mname, '{$key}') AS CHAR)) as mname")
            )
            ->limit(15)
            ->get()
            ->map(function ($item) {
                return [
                    'id'    => $item->dbedid, 
                    'text'  => $item->fname . ' ' . $item->lname,
                    'fname' => $item->fname,
                    'lname' => $item->lname,
                    'mname' => $item->mname,
                ];
            });

        return response()->json($employees);
    }

    /**
     * PREVIEW: For the Live Preview Card
     */
    public function getExternalDetails($dbedid)
    {
        $key = config('app.db_common_key') ?? env('DB_COMMON_ENCRYPTION_KEY');

        $details = DB::connection('db_common')->table('tbl_emp_details')
            ->join('tbl_employee', 'tbl_emp_details.empid', '=', 'tbl_employee.empid')
            ->leftJoin('tbl_section', 'tbl_emp_details.secid', '=', 'tbl_section.secid')
            ->leftJoin('tbl_division', 'tbl_emp_details.divid', '=', 'tbl_division.divid')
            ->leftJoin('tbl_position', 'tbl_emp_details.dbpid', '=', 'tbl_position.dbpid')
            ->where('tbl_emp_details.dbedid', $dbedid)
            ->select(
                'tbl_emp_details.dbedid',
                'tbl_section.secname as section',
                'tbl_division.divname as division',
                'tbl_position.dbposition as position_label',
                'tbl_emp_details.dbdesignation',
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.fname, '{$key}') AS CHAR)) as fname"),
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.mname, '{$key}') AS CHAR)) as mname"),
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.lname, '{$key}') AS CHAR)) as lname"),
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.suffix, '{$key}') AS CHAR)) as suffix")
            )
            ->first();

        if (!$details) {
            return response()->json(['error' => 'Record not found'], 404);
        }

        // Format Middle Initial and Suffix
        $mi = $details->mname ? ' ' . substr($details->mname, 0, 1) . '.' : '';
        $suffix = $details->suffix ? ' ' . $details->suffix : '';

        return response()->json([
            'dbedid'   => $details->dbedid,
            'fname'    => $details->fname,
            'mname'    => $details->mname,
            'lname'    => $details->lname,
            'name'     => "{$details->fname}{$mi} {$details->lname}{$suffix}",
            'position' => $details->dbdesignation ?: ($details->position_label ?: 'N/A'),
            'section'  => $details->section ?? 'N/A',
            'division' => $details->division ?? 'N/A',
        ]);
    }

    public function toggleStatus($id)
    {
        $user = \App\Models\User::findOrFail($id);
        
        // Toggle between 0 and 1
        $user->is_active = $user->is_active == 1 ? 0 : 1;
        $user->save();

        $statusText = $user->is_active == 1 ? 'activated' : 'deactivated';
        return redirect()->back()->with('success', "User account has been $statusText.");
    }

    // Update User Password/Details
    public function updateUser(Request $request, $id)
    {
        $request->validate([
            'password' => 'nullable|min:6|confirmed', // 'confirmed' looks for password_confirmation field
        ]);

        $user = \App\Models\User::findOrFail($id);
        
        if ($request->filled('password')) {
            $user->password = \Hash::make($request->password);
        }
        
        $user->save();
        return redirect()->back()->with('success', 'User details updated successfully.');
    }
    
    
}
