<?php

namespace App\Http\Controllers;
use App\Models\SourceOfFund;
use App\Models\Employee; 
use App\Models\Activity; 
use App\Models\ImportTemplate; 
use App\Imports\WfpActivitiesImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

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
                })->ignore($id), // IMPORTANT: Ignore the current record
            ],
            'fiscal_year' => 'required|integer',
            'total_amount'   => 'required|numeric',
            'spreadsheet_id' => 'nullable|string',
            'sheet_name'     => 'nullable|string',
        ]);

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
        $activity = \App\Models\Activity::findOrFail($id);
        $activity->delete();

        return redirect()->back()->with('success', 'Activity deleted successfully!');
    }

    public function destroySource($id)
    {
        // 1. Find the source or fail
        $source = SourceOfFund::findOrFail($id);

        // 2. Check for transactions in the funds table
        // Replace 'Fund' with your actual Transaction model name
        $hasTransactions = \App\Models\Fund::where('source_of_fund_id', $id)->exists();

        if ($hasTransactions) {
            return back()->withErrors([
                'error' => "Cannot delete '{$source->name}'. There are already transactions/funds recorded under this source."
            ]);
        }

        // 3. Optional: Check for linked activities as well
        if ($source->activities()->exists()) {
            return back()->withErrors([
                'error' => "Cannot delete '{$source->name}'. Please delete its linked activities first."
            ]);
        }

        // 4. If clean, delete
        $source->delete();

        return back()->with('success', 'Fund source deleted successfully!');
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

    

    
    
}
