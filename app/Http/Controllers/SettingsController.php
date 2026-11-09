<?php

namespace App\Http\Controllers;
use App\Models\SourceOfFund;
use App\Models\Employee; 
use App\Models\Activity; 
use App\Models\User; 
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
use Illuminate\Validation\Rules\Password;
use App\Models\Objective;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;
use App\Models\WfpVerification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;

class SettingsController extends Controller
{
    public function index()
    {
        $currentYear = (int) date('Y');
        $nextYear = $currentYear + 1;

        // 1. Fetch section names for mapping (from common database)
        $sectionNames = \DB::connection('db_common')
            ->table('tbl_section')
            ->pluck('secname', 'secid');

        // 2. Build the Fund Sources Query (Fetch ALL years so Indicative & Final sources load)
        $isAdminOrBudget = \Illuminate\Support\Facades\Gate::allows('budget-section');
        $fundSourcesQuery = \App\Models\SourceOfFund::with('activities'); // <-- REMOVED where('fiscal_year', $currentYear)

        if (!$isAdminOrBudget) {
            $localDetail = \DB::table('employee_details')->where('user_id', auth()->id())->first();
            $userSecId = $localDetail ? \DB::connection('db_common')->table('tbl_emp_details')
                ->where('dbedid', $localDetail->dbedid)->value('secid') : null;
            $fundSourcesQuery->where('section_id', $userSecId ?? 0);
        }

        $fundSources = $fundSourcesQuery->get();

        // 3. GROUPING LOGIC: Map section names and group by section
        $groupedBySection = $fundSources->map(function($source) use ($sectionNames) {
            $source->section_display_name = $sectionNames[$source->section_id] ?? 'UNASSIGNED UNIT';
            return $source;
        })->groupBy('section_display_name');

        // 4. Helper Data for Modals/Forms
        // Final WFP: Current year only
        $finalSources = \App\Models\SourceOfFund::where('fiscal_year', $currentYear)
            ->orderBy('name', 'asc')
            ->get();

        // Indicative WFP: Restrict strictly to NEP fund sources for the modal
        $indicativeSources = \App\Models\SourceOfFund::where('funding_source', 'NEP')
            ->orderBy('fiscal_year', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        $template = \App\Models\ImportTemplate::first();
        $uacsCodes = \App\Models\UacsCode::orderBy('uacs_code', 'asc')->get();
        
        $budgetLineItems = \App\Models\BudgetLineItem::where('is_active', 1)
            ->whereHas('fundSources', function ($query) {
                $query->where('funding_source', 'NEP');
            })
            ->orderBy('budget_line_item_name')
            ->get();
        
        $activities = Activity::with('source')
            ->join('source_of_funds', 'activities.source_of_fund_id', '=', 'source_of_funds.id')
            ->select('activities.*')
            ->orderBy('source_of_funds.fiscal_year', 'desc')
            ->get();

        // 5. Connect to SPMS API / Sync Objectives
        $isSpmsOnline = env('SPMS_ONLINE', true); 

        if ($isSpmsOnline) {
            try {
                $spmsData = Cache::remember('spms_raw_objectives', 86400, function () {
                    $response = Http::withoutVerifying()->timeout(5)->post(env('SPMS_API_URL'), [
                        'systemname' => env('SPMS_SYSTEM_NAME'),
                        'key'        => env('SPMS_KEY'),
                    ]);

                    if ($response->successful() && is_array($response->json())) {
                        return $response->json();
                    }

                    return null;
                });

                if (!empty($spmsData)) {
                    foreach ($spmsData as $item) {
                        $text = $item['objectives'] ?? $item['title'] ?? null;
                        if (!empty($text)) {
                            Objective::firstOrCreate(
                                ['title' => trim($text)],
                                [
                                    'source'    => 'spms',
                                    'is_active' => true
                                ]
                            );
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('SPMS API Sync Error: ' . $e->getMessage());
            }
        }

        $objectives = Objective::where('is_active', true)
            ->orderBy('title', 'asc')
            ->get();

        $key = config('app.db_common_key') ?? env('DB_COMMON_ENCRYPTION_KEY');

        $userSectionId = DB::table('employee_details as local_emp')
            ->join('db_common.tbl_emp_details as common_emp', 'local_emp.dbedid', '=', 'common_emp.dbedid')
            ->where('local_emp.user_id', auth()->id())
            ->value('common_emp.secid');

        $signatoryQuery = DB::table('wfp_signatories')
            ->leftJoin('db_common.tbl_section', 'wfp_signatories.section_id', '=', 'tbl_section.secid')
            ->leftJoin('db_common.tbl_emp_details', 'wfp_signatories.employee_id', '=', 'tbl_emp_details.dbedid')
            ->leftJoin('db_common.tbl_employee', 'tbl_emp_details.empid', '=', 'tbl_employee.empid')
            ->leftJoin('db_common.tbl_position', 'tbl_emp_details.dbpid', '=', 'tbl_position.dbpid')
            ->select(
                'wfp_signatories.*',
                'tbl_section.secname as section_display_name',
                'tbl_position.dbposition as designation',
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.fname, '{$key}') AS CHAR)) as fname"),
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.mname, '{$key}') AS CHAR)) as mname"),
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.lname, '{$key}') AS CHAR)) as lname"),
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.suffix, '{$key}') AS CHAR)) as suffix")
            );

        if (!$isAdminOrBudget) {
            $signatoryQuery->where('wfp_signatories.section_id', $userSectionId);
        }

        $signatorySettings = $signatoryQuery->orderBy('wfp_type')->get()->map(function($emp) {
            $mi = $emp->mname ? ' ' . substr($emp->mname, 0, 1) . '.' : '';
            $sfx = $emp->suffix ? ' ' . $emp->suffix : '';
            $emp->employee_name = $emp->lname ? "{$emp->lname}, {$emp->fname}{$mi}{$sfx}" : "PERSONNEL RECORD NOT FOUND";
            return $emp;
        });
        
        return view('admin.settings', compact(
            'finalSources', 
            'indicativeSources', 
            'activities', 
            'template', 
            'currentYear', 
            'budgetLineItems', 
            'fundSources',
            'uacsCodes',
            'objectives',
            'signatorySettings',
            'groupedBySection',
            'isAdminOrBudget'
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
        // 1. NEP Registry: Shows pure NEP funds AND those that transitioned to NEP-Current
        $nepSources = \App\Models\SourceOfFund::with(['budgetLineItem', 'activities'])
                    ->whereIn('funding_source', ['NEP', 'NEP-Current'])
                    ->orderBy('fiscal_year', 'desc')
                    ->orderBy('name', 'asc')
                    ->get()
                    ->groupBy('fiscal_year');

        // 2. Approved Funds Registry: Shows NEP-Current, direct Current, and Conap funds
        $approvedSources = \App\Models\SourceOfFund::with(['budgetLineItem', 'activities'])
                    ->whereIn('funding_source', ['NEP-Current', 'Current', 'Conap'])
                    ->orderBy('fiscal_year', 'desc')
                    ->orderBy('name', 'asc')
                    ->get()
                    ->groupBy('fiscal_year');

        $sources = \App\Models\SourceOfFund::with(['budgetLineItem', 'activities'])->get();
        $budgetLineItems = \App\Models\BudgetLineItem::orderBy('budget_line_item_name', 'asc')->get();
        
        $allotmentClasses = \App\Models\UacsCode::select('allotment_class')
                    ->distinct()
                    ->whereNotNull('allotment_class')
                    ->orderBy('allotment_class', 'asc')
                    ->get();

        $sections = \DB::connection('db_common')
            ->table('tbl_section')
            ->where('isactive', 1)
            ->orderBy('secname', 'asc')
            ->get([
                'secid as id',    
                'secname as section_name' 
            ]);

        return view('admin.settings.fund_sources', compact('sources', 'nepSources', 'approvedSources', 'budgetLineItems', 'allotmentClasses', 'sections'));  
    }

    // Delete Fund Sources
    public function destroyFundSource($id)
    {
        SourceOfFund::findOrFail($id)->delete();
        return redirect()->back()->with('success', 'Fund source deleted successfully!');
    }


    public function storeSource(Request $request) 
    {
        // 1. Validation Logic
        $validated = $request->validate([
            'funding_source'      => 'required|in:NEP,Current,Conap',
            'source_type'         => 'required|in:GAA,SAA',
            'section_id'          => 'required|integer',
            'name'                => [
                'required',
                'string',
                'max:255',
                Rule::unique('source_of_funds')->where(function ($query) use ($request) {
                    // Determine name with updated space prefix for validation check
                    $prefixName = $request->name;
                    if ($request->funding_source === 'Conap' && !str_starts_with(strtoupper($prefixName), 'CONAP ')) {
                        $prefixName = 'CONAP ' . $prefixName;
                    } elseif ($request->funding_source === 'NEP' && !str_starts_with(strtoupper($prefixName), 'NEP ')) {
                        $prefixName = 'NEP ' . $prefixName;
                    }

                    return $query->where('fiscal_year', $request->fiscal_year)
                                ->where('budget_line_item_id', $request->budget_line_item_id)
                                ->where('source_type', $request->source_type)
                                ->where('funding_source', $request->funding_source)
                                ->where('section_id', $request->section_id)
                                ->where('name', $prefixName);
                }),
            ],
            'budget_line_item_id' => 'required|exists:budget_line_items,id',
            'fiscal_year'         => 'required|integer',
            'total_amount'        => 'required|numeric|min:0',
            'allotment_class'     => 'required|string',
            'spreadsheet_id'      => 'nullable|string',
            'sheet_name'          => 'nullable|string',
            'saa_date'            => 'required_if:source_type,SAA|nullable|date',
            'reference_number'    => 'required_if:source_type,SAA|nullable|string|max:255',
            'fund_code'           => 'required_if:source_type,SAA|nullable|string|max:255',
            'approp_code'         => 'required_if:source_type,SAA|nullable|string|max:255',
        ], [
            'name.unique' => "This fund source already exists with this configuration in {$request->fiscal_year}.",
        ]);

        try {
            $data = $validated;

            // BACKEND AUTO-PREFIX ENFORCEMENT (USING SPACE)
            $cleanName = preg_replace('/^(CONAP\s|NEP\s|CURRENT\s)\s*/i', '', $data['name']);
            if ($data['funding_source'] === 'Conap') {
                $data['name'] = 'CONAP ' . $cleanName;
            } elseif ($data['funding_source'] === 'NEP') {
                $data['name'] = 'NEP ' . $cleanName;
            } else {
                $data['name'] = $cleanName;
            }

            // Rule: NEP is automatically under GAA
            if ($request->funding_source === 'NEP') {
                $data['source_type'] = 'GAA';
            }

            if ($data['source_type'] === 'SAA') {
                $data['entity_name'] = 'CHD8 - Eastern Visayas Centers for Health Development';
            } else {
                $data['entity_name'] = null;
                $data['saa_date'] = null;
                $data['reference_number'] = null;
                $data['fund_code'] = null;
                $data['approp_code'] = null;
            }

            SourceOfFund::create($data);

            return back()->with('success', 'Fund source added successfully!');

        } catch (\Exception $e) {
            logger()->error("Fund Source Storage Error: " . $e->getMessage());
            return back()->withErrors(['error' => "A system error occurred: " . $e->getMessage()]);
        }
    }

    public function updateSource(Request $request, $id)
    {
        $source = SourceOfFund::findOrFail($id);
        
        $validated = $request->validate([
            'funding_source'      => 'required|in:NEP,Current,Conap,NEP-Current', // Added NEP-Current here
            'source_type'         => 'required|in:GAA,SAA',
            'section_id'          => 'required|integer',
            'name'                => [
                'required',
                'string',
                'max:255',
                Rule::unique('source_of_funds')->where(function ($query) use ($request) {
                    return $query->where('fiscal_year', $request->fiscal_year)
                                ->where('budget_line_item_id', $request->budget_line_item_id)
                                ->where('source_type', $request->source_type)
                                ->where('funding_source', $request->funding_source)
                                ->where('section_id', $request->section_id);
                })->ignore($id), 
            ],
            'budget_line_item_id' => 'required|exists:budget_line_items,id',
            'fiscal_year'         => 'required|integer',
            'total_amount'        => 'required|numeric|min:0',
            'allotment_class'     => 'required|string',
            'spreadsheet_id'      => 'nullable|string',
            'sheet_name'          => 'nullable|string',
            'saa_date'            => 'required_if:source_type,SAA|nullable|date',
            'reference_number'    => 'required_if:source_type,SAA|nullable|string|max:255',
            'fund_code'           => 'required_if:source_type,SAA|nullable|string|max:255',
            'approp_code'         => 'required_if:source_type,SAA|nullable|string|max:255',
        ], [
            'name.unique' => "This fund source already exists for this section in {$request->fiscal_year}.",
        ]);

        try {
            $totalAllocatedToActivities = $source->activities()->sum('budget_adjusted');

            if ($request->total_amount < $totalAllocatedToActivities) {
                return back()->withErrors([
                    'total_amount' => "Constraint Error: Total amount is less than already allocated. Please adjust activity budgets first."
                ])->withInput();
            }

            $data = $validated;

            if ($request->funding_source === 'NEP') {
                $data['source_type'] = 'GAA';
            }

            if ($data['source_type'] === 'SAA') {
                $data['entity_name'] = 'CHD8 - Eastern Visayas Centers for Health Development';
            } else {
                $data['entity_name'] = null;
                $data['saa_date'] = null;
                $data['reference_number'] = null;
                $data['fund_code'] = null;
                $data['approp_code'] = null;
            }

            $source->update($data);

            return back()->with('success', 'Fund source updated successfully!');

        } catch (\Exception $e) {
            \Log::error("Fund Source Update Error: " . $e->getMessage());
            return back()->withErrors(['error' => "A system error occurred."]);
        }
    }

    public function convertToApproved(Request $request, $id)
    {
        $nepSource = SourceOfFund::findOrFail($id);

        $validated = $request->validate([
            'funding_source'      => 'required|in:NEP-Current,Conap', // Updated allowed target types
            'source_type'         => 'required|in:GAA,SAA',
            'section_id'          => 'required|integer',
            'name'                => ['required', 'string', 'max:255'],
            'budget_line_item_id' => 'required|exists:budget_line_items,id',
            'fiscal_year'         => 'required|integer',
            'total_amount'        => 'required|numeric|min:0',
            'allotment_class'     => 'required|string',
        ]);

        try {
            $data = $validated;
            
            // Format naming conventions cleanly
            $cleanName = preg_replace('/^(CONAP\s|NEP\s|CURRENT\s)\s*/i', '', $data['name']);
            $data['name'] = ($data['funding_source'] === 'Conap') ? 'CONAP ' . $cleanName : $cleanName;

            if ($data['source_type'] === 'SAA') {
                $data['entity_name'] = 'CHD8 - Eastern Visayas Centers for Health Development';
            } else {
                $data['entity_name'] = null;
                $data['saa_date'] = null;
                $data['reference_number'] = null;
                $data['fund_code'] = null;
                $data['approp_code'] = null;
            }

            $data['spreadsheet_id'] = $data['spreadsheet_id'] ?? $nepSource->spreadsheet_id;
            $data['sheet_name'] = $data['sheet_name'] ?? $nepSource->sheet_name;
            
            // Flag as converted so it maintains its footprint
            $data['is_converted'] = true;

            $nepSource->update($data);

            return redirect()->back()->with('success', 'NEP fund successfully approved and transitioned to NEP-Current!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => "Conversion failed: " . $e->getMessage()]);
        }
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

    // for BUDGET REALIGNMENT
    public function updateAllocation(Request $request)
    {
        $request->validate([
            'source_id' => 'required|exists:source_of_funds,id',
            'adjustments' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $source = SourceOfFund::findOrFail($request->source_id);
            
            // 1. Clean input numbers
            $adjustments = collect($request->adjustments)->map(function ($value) {
                return (float) str_replace(',', '', $value);
            })->toArray();

            // 2. Strict sum validation
            if (round(array_sum($adjustments), 2) !== round($source->total_amount, 2)) {
                return back()->with('error', 'The total adjusted budget (₱' . number_format(array_sum($adjustments), 2) . ') must match Source Total (₱' . number_format($source->total_amount, 2) . ').');
            }

            $currentYear = date('Y');

            // 3. Process each activity adjustment
            foreach ($adjustments as $activityId => $newAmount) {
                $activity = Activity::findOrFail($activityId);
                
                // A. Obligated Amount
                $obligated = $activity->transactions()
                    ->whereYear('funds.obligation_date', $currentYear)
                    ->sum('obligation_amount') ?? 0;

                // B. NORSA sum (norsa_adjustments -> funds.transaction_type_id -> activity.id)
                $norsa = DB::table('norsa_adjustments')
                    ->join('funds', 'norsa_adjustments.fund_id', '=', 'funds.id')
                    ->where('funds.transaction_type_id', $activityId)
                    ->whereYear('norsa_adjustments.entry_date', $currentYear)
                    ->sum('norsa_adjustments.amount') ?? 0;

                // C. Net Obligated (Obligated - NORSA)
                $netObligated = max(0, $obligated - $norsa);

                // D. Pending/Routed
                $pending = $activity->transactions()
                    ->whereYear('funds.created_at', $currentYear)
                    ->where(function($q) {
                        $q->whereNull('obligation_amount')
                        ->orWhere('obligation_amount', 0);
                    })
                    ->whereNotIn('status', ['Cancelled', 'Rejected'])
                    ->sum('amount') ?? 0;

                // E. Pooled
                $pooled = $activity->pooled_amount ?? 0;

                // Absolute minimum floor allowance
                $minLimit = $netObligated + $pending + $pooled;

                if (round($newAmount, 2) < round($minLimit, 2)) {
                    return back()->with('error', "Cannot reduce {$activity->name} below minimum required limit of ₱" . number_format($minLimit, 2) . ".");
                }

                // F. Save updated activity budget
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
            return redirect(url()->previous() . '#tabs-realignment')->with('success', 'Budget realignment applied successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Critical Error: ' . $e->getMessage());
        }
    }

    public function getRealignmentTable($id)
    {
        // 1. Fetch the source with relations
        $source = SourceOfFund::with(['activities.funds'])->findOrFail($id);
        
        // 2. Check Permissions (Admin/Budget vs Section User)
        $isAdminOrBudget = \Illuminate\Support\Facades\Gate::allows('budget-section');

        if (!$isAdminOrBudget) {
            // Get the section ID of the logged-in user
            $localDetail = \DB::table('employee_details')->where('user_id', auth()->id())->first();
            
            if ($localDetail) {
                $userSecId = \DB::connection('db_common')->table('tbl_emp_details')
                    ->where('dbedid', $localDetail->dbedid)
                    ->value('secid');

                // Block access if the Source doesn't belong to the user's section
                if ($source->section_id != $userSecId) {
                    return response()->json(['error' => 'Unauthorized access to this section.'], 403);
                }
            } else {
                return response()->json(['error' => 'Employee details not found.'], 403);
            }
        }

        // 3. Render the table partial
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

    // for manual encoding of WFP
    public function storeWfp(Request $request)
    {
        // 0. PRE-SANITIZATION: Strip commas from numeric inputs to avoid validation errors with formatted inputs (e.g. "1,000.00")
        if ($request->has('budget_amount')) {
            $request->merge([
                'budget_amount' => str_replace(',', '', $request->input('budget_amount'))
            ]);
        }

        if ($request->has('targets') && is_array($request->input('targets'))) {
            $sanitizedTargets = [];
            foreach ($request->input('targets') as $key => $val) {
                $sanitizedTargets[$key] = is_string($val) ? str_replace(',', '', $val) : $val;
            }
            $request->merge(['targets' => $sanitizedTargets]);
        }

        $isEdit = $request->filled('id');

        // 1. Unified Validation
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
            'targets.*'           => 'nullable|numeric|min:0',
            'classification'      => 'required|in:Strategic,Core,Support',
            'is_for_procurement'  => 'required|boolean',
            'wfp_type'            => 'nullable|in:indicative,final',
        ], [
            'budget_amount.numeric'    => 'The budget amount must be a valid numeric value.',
            'target_quarters.required' => 'Please select at least one target quarter.',
            'target_quarters.min'      => 'Please select at least one target quarter.',
            'targets.*.numeric'        => 'Quarterly targets must be valid numeric values.',
        ]);

        // PREPARE BOOLEAN & STATUS VALUES
        $isForProcurement = $request->boolean('is_for_procurement');
        
        // Determine status: 0 for Indicative WFP, 1 for Final WFP
        $isConverted = ($request->input('wfp_type') === 'final') ? 1 : 0;

        // AUTO-SAVE CUSTOM TYPED OBJECTIVE TO DATABASE
        if ($request->filled('objective')) {
            $objectiveText = trim($request->objective);
            Objective::firstOrCreate(
                ['title' => $objectiveText],
                [
                    'source'    => 'user_added',
                    'is_active' => true
                ]
            );
        }

        // 2. Validate selected quarter targets
        $selectedQuarters = $request->target_quarters ?? [];
        $physicalTargets = [];

        foreach ($selectedQuarters as $q) {
            $targetValue = $request->targets[$q] ?? null;

            if ($targetValue === null || !is_numeric($targetValue) || (float)$targetValue <= 0) {
                return back()->withInput()->withErrors(['targets' => "Please enter a valid numeric target greater than zero for {$q}."]);
            }

            $physicalTargets[$q] = (float) $targetValue;
        }

        // --- SHARED: FETCH USER AND SECTION INFO ---
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

        if (is_null($secid) && auth()->user()->username !== 'admin') {
            return back()->withInput()->withErrors(['error' => 'Your account is not correctly linked to a DOH Section.']);
        }

        // 3. Handle Store / Update Logic
        if ($isEdit) {
            $activity = Activity::findOrFail($request->id);
            
            // Fix: Use newly submitted source_of_fund_id if available, otherwise fallback to existing
            $targetSourceId = $request->input('source_of_fund_id', $activity->source_of_fund_id);
            $fundSource = SourceOfFund::findOrFail($targetSourceId);
            
            $hasTransactions = $activity->transactions()->exists(); 
            $isLocked = $activity->is_locked ?? false;

            // BUDGET VALIDATION FOR EDIT
            if (!$isLocked && !$hasTransactions && $request->filled('budget_amount')) {
                $newAmount = (float) $request->budget_amount;

                $totalSpentByOthers = Activity::where('source_of_fund_id', $fundSource->id)
                    ->where('id', '!=', $activity->id)
                    ->sum('budget_adjusted');

                $pooledFunds = method_exists($fundSource, 'pooledFunds') ? $fundSource->pooledFunds()->sum('amount') : ($fundSource->pooled_amount ?? 0);
                $adjustedAllottedBudget = $fundSource->total_amount - $pooledFunds;
                $remainingBalance = $adjustedAllottedBudget - $totalSpentByOthers;

                if ($newAmount > $remainingBalance) {
                    $errorMessage = "You have exceeded the adjusted allotted budget for {$fundSource->name}. Available Balance: ₱" . number_format($remainingBalance, 2) . ". To realign or replan activities, please set the amount to zero \"0\" first on the new activity to be added, then go to the Budget Realignment tab to adjust the amount for realignment.";

                    return back()->withInput()->with('budget_exceeded_error', $errorMessage);
                }
            }

            // HANDLE THE ACTUAL UPDATE
            if ($hasTransactions || $isLocked) {
                $activity->update([
                    'user_id'            => $userId,
                    'section_id'         => $secid,
                    'start_date'         => $validated['start_date'],
                    'end_date'           => $validated['end_date'],
                    'is_for_procurement' => $isForProcurement, 
                    'target_quarters'    => $selectedQuarters,
                    'physical_targets'   => $physicalTargets,
                ]);
                $msg = 'Activity timeframe and targets updated. Financial fields were locked due to existing transactions.';
            } else {
                $uacsRecord = \App\Models\UacsCode::findOrFail($request->uacs_code_id);
                $activity->update([
                    'user_id'             => $userId,
                    'section_id'          => $secid,
                    'budget_line_item_id' => $request->budget_line_item_id, // Added
                    'source_of_fund_id'   => $request->source_of_fund_id,   // Added
                    'objective'           => $request->objective,
                    'uacs_code_id'        => $request->uacs_code_id,
                    'uacs_code'           => $uacsRecord->uacs_code,
                    'name'                => $request->name,
                    'is_for_procurement'  => $isForProcurement, 
                    'budget'              => $request->budget_amount,
                    'budget_adjusted'     => $request->budget_amount,
                    'start_date'          => $validated['start_date'],
                    'end_date'            => $validated['end_date'],
                    'target_quarters'     => $selectedQuarters,
                    'physical_targets'    => $physicalTargets,
                    'classification'      => $request->classification,
                ]);
                $msg = 'Activity updated successfully!';
            }
        } else {
            // BUDGET VALIDATION FOR NEW ACTIVITY CREATION
            $fundSource = SourceOfFund::findOrFail($request->source_of_fund_id);
            $newAmount = (float) $request->budget_amount;

            $totalExistingBudget = Activity::where('source_of_fund_id', $fundSource->id)->sum('budget_adjusted');
            
            $pooledFunds = method_exists($fundSource, 'pooledFunds') ? $fundSource->pooledFunds()->sum('amount') : ($fundSource->pooled_amount ?? 0);
            $adjustedAllottedBudget = $fundSource->total_amount - $pooledFunds;
            $remainingBalance = $adjustedAllottedBudget - $totalExistingBudget;

            if ($newAmount > $remainingBalance) {
                $errorMessage = "You have exceeded the adjusted allotted budget for {$fundSource->name} (Available Adjusted Balance: ₱" . number_format($remainingBalance, 2) . "). To realign or replan activities, please set the amount to zero \"0\" first on the new activity, then go to the Budget Realignment tab to adjust the amount for realignment.";

                return back()->withInput()->with('budget_exceeded_error', $errorMessage);
            }

            // Create New Activity
            $uacsRecord = \App\Models\UacsCode::findOrFail($request->uacs_code_id);

            Activity::create([
                'user_id'             => $userId,
                'section_id'          => $secid,
                'budget_line_item_id' => $request->budget_line_item_id,
                'source_of_fund_id'   => $request->source_of_fund_id,
                'objective'           => $request->objective,
                'uacs_code_id'        => $request->uacs_code_id,
                'uacs_code'           => $uacsRecord->uacs_code,
                'name'                => $request->name,
                'is_for_procurement'  => $isForProcurement, 
                'is_converted'        => $isConverted, // 0 = Indicative WFP, 1 = Final WFP
                'budget'              => $request->budget_amount,
                'budget_adjusted'     => $request->budget_amount,
                'start_date'          => $validated['start_date'],
                'end_date'            => $validated['end_date'],
                'target_quarters'     => $selectedQuarters,
                'physical_targets'    => $physicalTargets,
                'classification'      => $request->classification,
            ]);

            $msg = 'Indicative activity created successfully!';
        }

        return back()->with('success', $msg);
    }

    public function editActivity($id)
    {
        $activity = Activity::findOrFail($id);
        return response()->json($activity);
    }

    public function convertIndicativeToWfp($id)
    {
        // Use 'source' relationship instead of 'sourceOfFund'
        $activity = Activity::with('source')->findOrFail($id);

        // 1. Check if already converted
        if ($activity->is_converted) {
            return redirect()->back()->with('error', 'This activity has already been converted to Final WFP.');
        }

        // 2. Validate Fund Source Approval Status
        $fundSource = $activity->source;
        $approvedStatuses = ['NEP-Current', 'GAA', 'Current'];

        if (!$fundSource || !in_array($fundSource->funding_source, $approvedStatuses)) {
            return redirect()->back()->with('error', "Cannot carry over '{$activity->name}'. Its Fund Source is still pending approval (tagged as NEP).");
        }

        // 3. Perform conversion
        $activity->update(['is_converted' => 1]);

        return redirect()->back()->with('success', 'Activity successfully carried over to Final WFP!');
    }

    public function convertMultipleIndicativeToWfp(Request $request)
    {
        $request->validate([
            'activity_ids'   => 'required|array',
            'activity_ids.*' => 'exists:activities,id'
        ]);

        try {
            $approvedStatuses = ['NEP-Current', 'GAA', 'Current'];
            $convertedCount = 0;
            $skippedCount = 0;

            // Fetch activities with 'source' relationship
            $activities = Activity::with('source')
                ->whereIn('id', $request->activity_ids)
                ->where('is_converted', 0)
                ->get();

            foreach ($activities as $activity) {
                $fundSource = $activity->source;

                if ($fundSource && in_array($fundSource->funding_source, $approvedStatuses)) {
                    $activity->update(['is_converted' => 1]);
                    $convertedCount++;
                } else {
                    $skippedCount++;
                }
            }

            if ($convertedCount === 0) {
                return redirect()->back()->with('error', 'None of the selected activities could be carried over because their Fund Sources are still pending approval (tagged as NEP).');
            }

            $message = "Successfully carried over {$convertedCount} activity/activities to the Final WFP!";
            if ($skippedCount > 0) {
                $message .= " ({$skippedCount} activity/activities were skipped because their Fund Source is still pending NEP approval).";
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            logger()->error("Bulk Conversion Error: " . $e->getMessage());
            return back()->withErrors(['error' => "An error occurred during bulk conversion: " . $e->getMessage()]);
        }
    }

    // print wfp
    public function printWfp(Request $request, $id = null)
    {
        // Increase memory limit and execution time for PDF generation
        set_time_limit(300);
        ini_set('memory_limit', '512M');
        
        $key = config('app.db_common_key') ?? env('DB_COMMON_ENCRYPTION_KEY');
        $calendarYear = date('Y');

        $sourceId = $id ?: $request->input('source_of_fund_id');
        if (!$sourceId) {
            return "Please select a specific fund source to print.";
        }

        $fundSource = \App\Models\SourceOfFund::find($sourceId);
        if (!$fundSource) {
            return "Fund Source not found.";
        }

        // Get view type flag ('final' or 'indicative') from the request
        $wfpViewType = $request->input('wfp_view_type', 'indicative');

        $query = Activity::with(['uacsCode', 'budgetLineItem'])
            ->where('source_of_fund_id', $fundSource->id);

        // STRICT FILTERING BASED ON TAB VIEW TYPE
        if ($wfpViewType === 'final') {
            // Final WFP: Only fetch converted activities
            $query->where('is_converted', true);

            // Filter selected activities if not selecting all (Modal Selection)
            $selectedActivityIds = $request->input('activity_ids', []);
            $isSelectAll = $request->input('select_all', false);

            if (!$isSelectAll && !empty($selectedActivityIds)) {
                $query->whereIn('id', $selectedActivityIds);
            }
        } else {
            // Indicative WFP: Print ALL non-converted activities directly AS-IS
            $query->where('is_converted', false);
        }

        $activities = $query->get();

        // Plan Title Modifier (only applicable for Final WFP replans/catch-ups)
        $planModifier = ($wfpViewType === 'final') ? $request->input('plan_modifier', '') : ''; 

        // Logic for SAA or Consolidated
        $fundName = $fundSource->name ?? '';
        $isSAA = str_contains(strtoupper($fundName), 'SAA');
        $firstAct = $activities->first();
        $isConsolidated = false;

        if ($firstAct && $firstAct->budgetLineItem) {
            if (trim(strtoupper($firstAct->budgetLineItem->budget_line_item_name)) == trim(strtoupper($fundName))) {
                $isConsolidated = true;
            }
        }

        $currentWfpType = ($isSAA || $isConsolidated) ? 'saa' : 'program';

        // --- 1. DETERMINE FISCAL YEAR & SECTION ---
        $wfpFiscalYear = $fundSource->fiscal_year ?? $calendarYear;

        $currentUser = auth()->user();
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

        $targetSectionId = $userSecId; 
        if (!$targetSectionId) {
            return "Unable to determine your section ID. Please ensure your employee profile is properly linked.";
        }

        // --- 2. REUSE OR CREATE TRACKING CODE USING WFP FISCAL YEAR ---
        $verificationRecord = WfpVerification::where('source_of_fund_id', $fundSource->id)
            ->where('section_id', $targetSectionId)
            ->where('wfp_type', $wfpViewType)
            ->latest()
            ->first();

        if (!$verificationRecord) {
            // Generate code using WFP Fiscal Year (e.g. WFP-2026-X8F9A2K1)
            $trackingCode = 'WFP-' . $wfpFiscalYear . '-' . strtoupper(\Illuminate\Support\Str::random(8));

            $verificationRecord = WfpVerification::create([
                'tracking_code'        => $trackingCode,
                'source_of_fund_id'    => $fundSource->id,
                'section_id'           => $targetSectionId,
                'wfp_type'             => $wfpViewType,
                'generated_by_user_id' => $currentUser->id,
                'generated_at'         => now(),
            ]);
        } else {
            $trackingCode = $verificationRecord->tracking_code;
        }

        // --- 3. ENCRYPT & GENERATE QR CODE ---
        $rawPayload = json_encode([
            'code' => $trackingCode,
            'url'  => route('settings.activity.verifyWfp', ['code' => $trackingCode])
        ]);
        $encryptedPayload = \Illuminate\Support\Facades\Crypt::encryptString($rawPayload);

        // Generate SVG QR Code (Pure PHP, no imagick required)
        $qrCodeImage = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(90)->margin(0)->generate($encryptedPayload));
        $qrCodeUri = 'data:image/svg+xml;base64,' . $qrCodeImage;

        // --- 3. FETCH SIGNATORIES STRICTLY BY USER SECTION & WFP TYPE ---
        $signatories = \DB::table('wfp_signatories')
            ->where('wfp_type', $currentWfpType)
            ->where('section_id', $targetSectionId)
            ->leftJoin('db_common.tbl_emp_details', 'wfp_signatories.employee_id', '=', 'tbl_emp_details.dbedid')
            ->leftJoin('db_common.tbl_employee', 'tbl_emp_details.empid', '=', 'tbl_employee.empid')
            ->leftJoin('db_common.tbl_position', 'tbl_emp_details.dbpid', '=', 'tbl_position.dbpid')
            ->select(
                'wfp_signatories.label',
                'tbl_position.dbposition as designation',
                \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.fname, '{$key}') AS CHAR)) as fname"),
                \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.mname, '{$key}') AS CHAR)) as mname"),
                \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.lname, '{$key}') AS CHAR)) as lname"),
                \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.extname, '{$key}') AS CHAR)) as extname"),
                \DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.suffix, '{$key}') AS CHAR)) as suffix")
            )
            ->get()
            ->map(function($emp) {
                $mi = $emp->mname ? ' ' . substr($emp->mname, 0, 1) . '.' : '';
                $sfx = $emp->suffix ? ' ' . $emp->suffix : '';
                $emp->employee_name = $emp->lname ? "{$emp->fname}{$mi}{$sfx} {$emp->lname}, {$emp->extname}" : "NOT FOUND";
                $emp->clean_label = strtolower(trim(str_replace(':', '', $emp->label)));
                return $emp;
            })
            ->keyBy('clean_label');

        // Section/Division Mapping
        $divisionName = 'OFFICE OF THE DIRECTOR';
        if ($activities->isNotEmpty()) {
            $sectionIds = $activities->pluck('section_id')->unique()->filter()->toArray();
            $lookupData = \DB::connection('db_common')->table('tbl_section')
                ->leftJoin('tbl_division', 'tbl_section.divid', '=', 'tbl_division.divid')
                ->whereIn('tbl_section.secid', $sectionIds)
                ->select('tbl_section.secid', 'tbl_section.secabbrev', 'tbl_division.divname')
                ->get()->keyBy('secid');

            foreach ($activities as $activity) {
                if ($activity->section_id == 0) {
                    $activity->computed_secname = 'REGIONAL OFFICE';
                    $activity->computed_divname = 'OFFICE OF THE DIRECTOR';
                } else {
                    $info = $lookupData->get($activity->section_id);
                    $activity->computed_secname = $info->secabbrev ?? 'N/A';
                    $activity->computed_divname = $info->divname ?? 'N/A';
                }
            }
            $divisionName = $activities->first()->computed_divname;
        }

        $groupedActivities = $activities->groupBy('classification');

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reports.wfp_pdf', compact(
            'activities', 
            'signatories', 
            'calendarYear',
            'groupedActivities',
            'divisionName',
            'fundName',
            'currentWfpType',
            'planModifier',
            'trackingCode',
            'qrCodeUri'
        ))->setPaper('a4', 'landscape')->stream("WFP_{$fundName}.pdf");
    }

    public function verifyWfp(Request $request)
    {
        // 1. BACKEND ACCESS CONTROL CHECK USING LARAVEL GATES / PERMISSIONS
        $currentUser = auth()->user();
        
        // Check using Gate permissions defined in your app (matches 'can:budget-section' and 'can:admin-only')
        $isAdminOrBudget = Gate::allows('budget-section') || Gate::allows('admin-only') || ($currentUser->user_type ?? '') === 'budget';

        if (!$isAdminOrBudget) {
            return response()->json([
                'valid'   => false,
                'message' => 'Unauthorized access: Only Budget Section personnel can verify WFP tracking codes.'
            ], 403);
        }

        // 2. VERIFICATION LOGIC
        $input = trim($request->input('tracking_code', ''));

        if (empty($input)) {
            return response()->json([
                'valid'   => false,
                'message' => 'Please provide a valid tracking code or QR payload.'
            ], 400);
        }

        $searchCode = $input;

        // Decrypt if QR code payload was pasted
        if (!str_starts_with($input, 'WFP-')) {
            try {
                $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($input);
                $jsonData = json_decode($decrypted, true);
                if (isset($jsonData['code'])) {
                    $searchCode = $jsonData['code'];
                }
            } catch (\Exception $e) {
                // Keep original string if decryption fails
            }
        }

        // Query database record
        $record = \App\Models\WfpVerification::with(['sourceOfFund', 'generatedBy'])
            ->where('tracking_code', $searchCode)
            ->first();

        if (!$record) {
            return response()->json([
                'valid'   => false,
                'message' => 'Invalid or counterfeit WFP code. No matching record found in system logs.'
            ], 404);
        }

        // Lookup section name from db_common
        $sectionData = \DB::connection('db_common')
            ->table('tbl_section')
            ->where('secid', $record->section_id)
            ->select('secabbrev', 'secname')
            ->first();

        $sectionName = $sectionData->secabbrev ?? $sectionData->secname ?? 'N/A';

        return response()->json([
            'valid'          => true,
            'tracking_code'  => $record->tracking_code,
            'fund_source'    => $record->sourceOfFund->name ?? 'N/A',
            'wfp_type'       => strtoupper($record->wfp_type),
            'section_name'   => strtoupper($sectionName),
            'generated_by'   => $record->generatedBy->name ?? 'System User',
            'generated_at'   => $record->generated_at ? $record->generated_at->format('F d, Y h:i A') : 'N/A',
        ]);
    }

    public function getActivitiesByFund(Request $request, $id)
    {
        $query = \App\Models\Activity::where('source_of_fund_id', $id);

        // If requested from the Final WFP tab, only fetch activities that are converted
        if ($request->input('type') === 'final') {
            $query->where('is_converted', true);
        }

        $activities = $query->select('id', 'name', 'budget_adjusted')->get();

        return response()->json([
            'success' => true,
            'activities' => $activities
        ]);
    }

    public function searchEmployees(Request $request)
    {
        $q = $request->get('q');
        $key = config('app.db_common_key') ?? env('DB_COMMON_ENCRYPTION_KEY');

        /**
         * 1. Subquery to identify the LATEST dbedid for every empid.
         * This ensures we get the most recent position and record ID.
         */
        $latestDetails = DB::connection('db_common')->table('tbl_emp_details')
            ->select('empid', DB::raw('MAX(dbedid) as latest_dbedid'))
            ->groupBy('empid');

        /**
         * 2. Main Query starting from the Employee Master table
         */
        $employees = DB::connection('db_common')->table('tbl_employee')
            // Only pull employees where the master record is ACTIVE
            ->where('isactive', 1) 
            // Join with our subquery to find their latest employment record
            ->joinSub($latestDetails, 'latest_map', function ($join) {
                $join->on('tbl_employee.empid', '=', 'latest_map.empid');
            })
            // Join to tbl_emp_details using that latest ID to get designation
            ->join('tbl_emp_details', 'latest_map.latest_dbedid', '=', 'tbl_emp_details.dbedid')
            ->leftJoin('tbl_position', 'tbl_emp_details.dbpid', '=', 'tbl_position.dbpid')
            ->select(
                'tbl_emp_details.dbedid as id', // This is what gets saved to wfp_signatories
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.fname, '{$key}') AS CHAR)) as fname"),
                DB::raw("UPPER(CAST(AES_DECRYPT(tbl_employee.lname, '{$key}') AS CHAR)) as lname"),
                'tbl_position.dbposition as designation'
            )
            ->where(function($query) use ($q, $key) {
                // Search logic for encrypted names
                $query->where(DB::raw("CAST(AES_DECRYPT(tbl_employee.lname, '{$key}') AS CHAR)"), 'LIKE', "%{$q}%")
                    ->orWhere(DB::raw("CAST(AES_DECRYPT(tbl_employee.fname, '{$key}') AS CHAR)"), 'LIKE', "%{$q}%");
            })
            ->limit(15)
            ->get()
            ->map(function($emp) {
                // Formatting for Select2 dropdown
                $emp->text = "{$emp->lname}, {$emp->fname} (" . ($emp->designation ?? 'N/A') . ")";
                return $emp;
            });

        return response()->json($employees);
    }

    public function saveSignatory(Request $request)
    {
        $request->validate([
            'wfp_type' => 'required',
            'empid'    => 'required',
            'label'    => 'required'
        ]);

        // 1. Try to find the section
        $sectionId = DB::table('employee_details as local_emp')
            ->join('db_common.tbl_emp_details as common_emp', 'local_emp.dbedid', '=', 'common_emp.dbedid')
            ->where('local_emp.user_id', auth()->id())
            ->value('common_emp.secid');

        // 2. If no section is found but the user is an admin, assign a default value
        if (is_null($sectionId)) {
            if (auth()->user()->is_admin == 1) { // Adjust 'role' to your actual admin check
                $sectionId = 0; // Use 0 or another designated ID for Global/Admin signatories
            } else {
                return response()->json(['error' => 'User section not found.'], 422);
            }
        }

        DB::table('wfp_signatories')->updateOrInsert(
            [
                'wfp_type'   => $request->wfp_type,
                'label'      => $request->label,
                'section_id' => $sectionId 
            ],
            [
                'employee_id' => $request->empid,
                'updated_at'  => now()
            ]
        );

        return response()->json(['success' => 'Signatory updated successfully!']);
    }

    public function getSignatories()
    {
        $orderMap = [
            'Prepared by:' => 1,
            'Checked by:' => 2,
            'Recommending Approval:' => 3,
            'Approved by:' => 4,
        ];

        // Get current user's section first to filter the list
        $userSection = DB::table('employee_details as local_emp')
            ->join('db_common.tbl_emp_details as common_emp', 'local_emp.dbedid', '=', 'common_emp.dbedid')
            ->where('local_emp.user_id', auth()->id())
            ->value('common_emp.secid');

        $signatories = DB::table('wfp_signatories')
            ->where('section_id', $userSection)
            ->get()
            ->sortBy(function($item) use ($orderMap) {
                return $orderMap[$item->label] ?? 99;
            });

        return view('settings.signatories', compact('signatories'));
    }

    public function deleteSignatory($id)
    {
        DB::table('wfp_signatories')->where('id', $id)->delete();
        return response()->json(['success' => 'Signatory deleted successfully!']);
    }

    // register new user account or update existing user accounts
    public function registerEmployee(Request $request)
    {
        // 1. Validation
        $request->validate([
            'empid'    => 'required|integer', // Represents the dbedid
            'username' => 'required|string|max:255', 
            'password' => 'required|min:8',
            'role'     => 'required|in:staff,division,budget,admin',
        ]);

        $key = config('app.db_common_key') ?? env('DB_COMMON_ENCRYPTION_KEY');
        $usernameHash = hash('sha256', strtolower($request->username));
        $encryptedUsername = DB::raw("AES_ENCRYPT('{$request->username}', '{$key}')");

        DB::beginTransaction();

        try {
            $existingDetail = \App\Models\EmployeeDetail::where('dbedid', $request->empid)->first();

            if ($existingDetail) {
                $user = $existingDetail->user;
                $user->update([
                    'username'      => $encryptedUsername,
                    'username_hash' => $usernameHash,
                    'password'      => Hash::make($request->password),
                    'is_admin'      => ($request->role === 'admin') ? 1 : 0,
                ]);

                // Save role to employee_details
                $existingDetail->update([
                    'role' => $request->role,
                ]);

                $message = 'User account credentials and access role updated!';
            } 
            else {
                $user = \App\Models\User::where('username_hash', $usernameHash)->first();

                if ($user) {
                    $user->employeeDetail()->updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'dbedid' => $request->empid,
                            'role'   => $request->role,
                        ]
                    );
                    
                    $user->update([
                        'username' => $encryptedUsername,
                        'password' => Hash::make($request->password),
                        'is_admin' => ($request->role === 'admin') ? 1 : 0,
                    ]);
                    
                    $message = 'Existing user account linked and access role assigned!';
                } 
                else {
                    $user = \App\Models\User::create([
                        'username'      => $encryptedUsername,
                        'username_hash' => $usernameHash,
                        'password'      => Hash::make($request->password),
                        'is_admin'      => ($request->role === 'admin') ? 1 : 0,
                    ]);

                    $user->employeeDetail()->create([
                        'dbedid' => $request->empid,
                        'role'   => $request->role,
                    ]);

                    $message = 'New user account created with assigned access role!';
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

    //search employee for account registration
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
        // Validation ensures the password meets security requirements
        $request->validate([
            'password' => 'nullable|min:6|confirmed', 
        ]);

        $user = User::findOrFail($id);
        
        // The 'filled' method checks if the user actually typed a new password
        if ($request->filled('password')) {
            // Using the imported Hash facade
            $user->password = Hash::make($request->password);
        }
        
        $user->save();
        
        return redirect()->back()->with('success', 'User details updated successfully.');
    }
    
    
    /**
     * Show the user profile password modification view.
     */
    public function editPassword()
    {
        return view('admin.settings.profile_password');
    }

    /**
     * Handle updates to the authenticated user's password securely.
     */
    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'The provided password does not match your current credential records.',
        ]);

        // Update the database record using the modern encrypter interface
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return redirect()->back()->with('success', 'Your password database records have been modified securely!');
    }
}
