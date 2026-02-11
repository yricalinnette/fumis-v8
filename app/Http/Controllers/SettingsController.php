<?php

namespace App\Http\Controllers;
use App\Models\SourceOfFund;
use App\Models\Employee; 
use App\Models\Activity; 
use App\Models\Fund; 
use App\Models\ImportTemplate; 
use App\Imports\WfpActivitiesImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        // Fetch Sources (you likely already have this)
        $sources = SourceOfFund::all();

        // Fetch Employees (you likely already have this)
        $employees = Employee::all();

        // MISSING PART: Fetch Activities
        // We eager load 'source' to avoid N+1 issues if needed later
        $activities = Activity::with('source')->get();

        // Fetch the first template record, or create a blank object if none exists
        $template = \App\Models\ImportTemplate::first();

        return view('admin.settings', compact('sources', 'employees', 'activities', 'template'));
    }

    public function storeSource(Request $request) 
    {
        // 1. Validation with composite unique check
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                // This checks if the 'name' + 'fiscal_year' combination already exists
                Rule::unique('source_of_funds')->where(function ($query) use ($request) {
                    return $query->where('fiscal_year', $request->fiscal_year);
                }),
            ],
            'fiscal_year'    => 'required|integer',
            'total_amount'   => 'required|numeric',
            'spreadsheet_id' => 'nullable|string',
            'sheet_name'     => 'nullable|string',
        ], [
            // Custom error message for the unique constraint
            'name.unique' => "The fund source '{$request->name}' already exists for the year {$request->fiscal_year}."
        ]);

        try {
            // 2. Simple creation (Validation already handled the duplicate check)
            SourceOfFund::create($validated);
            return back()->with('success', 'Fund source added successfully!');

        } catch (\Exception $e) {
            // 3. Catch only genuine system failures (DB down, etc.)
            Log::error("Fund Source Storage Error: " . $e->getMessage());
            return back()->withErrors(['error' => "A system error occurred while saving. Please try again."]);
        }
    }

    public function updateSource(Request $request, $id)
    {
        $source = SourceOfFund::findOrFail($id);
        
        $validated = $request->validate([
            'name' => [
                'required', 'string',
                Rule::unique('source_of_funds')->where(function ($query) use ($request) {
                    return $query->where('fiscal_year', $request->fiscal_year);
                })->ignore($id),
            ],
            'fiscal_year' => 'required|integer',
            'total_amount'   => 'required|numeric|min:0', // Ensure it's not negative
            'spreadsheet_id' => 'nullable|string',
            'sheet_name'     => 'nullable|string',
        ]);

        // 1. Calculate the total budget already distributed to activities
        $totalAllocatedToActivities = $source->activities()->sum('budget');

        // 2. Compare with the new proposed total_amount
        if ($request->total_amount < $totalAllocatedToActivities) {
            return back()->withErrors([
                'total_amount' => "Cannot reduce the total amount to ₱" . number_format($request->total_amount, 2) . 
                                ". Currently, ₱" . number_format($totalAllocatedToActivities, 2) . 
                                " is already allocated to activities. Please reduce activity budgets first."
            ])->withInput();
        }

        // 3. If check passes, update
        $source->update($validated);

        return back()->with('success', 'Fund source updated successfully!');
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

            // 2. Validate Total Sum against Fund Source (using sanitized numbers)
            if (round(array_sum($adjustments), 2) > round($source->total_amount, 2)) {
                return back()->with('error', 'The total exceeds the available Fund Source limit.');
            }

            // 3. Process Adjustments
            foreach ($adjustments as $activityId => $newAmount) {
                $activity = Activity::findOrFail($activityId);
                
                // Check against obligation_amount (matching your frontend calculation)
                $obligated = $activity->funds()->sum('obligation_amount');

                // Safety check: Don't allow reduction below already spent/obligated funds
                if (round($newAmount, 2) < round($obligated, 2)) {
                    return back()->with('error', "Cannot reduce {$activity->name} below its obligations (₱" . number_format($obligated, 2) . ").");
                }

                // CORE LOGIC: If the activity was newly uploaded with 0 budget
                if ((float)$activity->budget == 0) {
                    $activity->update([
                        'budget' => $newAmount,          // Set the "Base" budget
                        'budget_adjusted' => $newAmount  // Set the "Current" budget
                    ]);
                } else {
                    // For existing activities, only update the adjusted column
                    $activity->update([
                        'budget_adjusted' => $newAmount
                    ]);
                }
            }

            DB::commit();
            return redirect(url()->previous() . '#tabs-realignment')->with('success', 'Budget replanned successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function getRealignmentTable($id)
    {
        $source = SourceOfFund::with(['activities.funds'])->findOrFail($id);
        
        // Point this specifically to the small file we created
        return view('admin.settings.partials._realignment_table', compact('source'))->render();
    }
    
    
}
