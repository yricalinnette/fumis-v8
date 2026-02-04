<?php

namespace App\Http\Controllers;
use App\Models\SourceOfFund;
use App\Models\Employee; 
use App\Models\Activity; 
use App\Models\ImportTemplate; 
use App\Imports\WfpActivitiesImport;
use Maatwebsite\Excel\Facades\Excel;

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
        $config = \App\Models\ImportTemplate::first() ?? new \App\Models\ImportTemplate();

        return view('admin.settings', compact('sources', 'employees', 'activities', 'config'));
    }

    public function storeSource(Request $request) {
        $request->validate([
            'name' => 'required|string|unique:source_of_funds,name',
            'total_amount' => 'required|numeric',
            'spreadsheet_id' => 'nullable|string', // Not required
            'sheet_name' => 'nullable|string',    // Not required
        ]);

        \App\Models\SourceOfFund::create($request->all());
        return redirect()->back()->with('success', 'New fund source added successfully!');
    }

    public function updateSource(Request $request, $id)
    {
        $source = SourceOfFund::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|unique:source_of_funds,name,' . $id,
            'total_amount' => 'required|numeric',
            'spreadsheet_id' => 'nullable|string',
            'sheet_name' => 'nullable|string',
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
        // 1. Find the source or fail with 404
        $source = SourceOfFund::findOrFail($id);

        // 2. Prevent deletion if there are activities linked to it
        // This prevents database integrity issues (orphaned records)
        if ($source->activities()->exists()) {
            return back()->withErrors([
                'error' => "Cannot delete '{$source->name}'. You must delete or move its linked activities first."
            ]);
        }

        // 3. Delete the source
        $source->delete();

        return back()->with('success', 'Fund source deleted successfully!');
    }

    //for updating the wfp import settings configurations
    public function updateTemplate(Request $request, $id)
    {
        // find the template or create a fresh one if ID 1 doesn't exist yet
        $template = \App\Models\ImportTemplate::findOrNew($id);
        
        $template->fill($request->all());
        $template->save();

        return back()->with('success', 'Excel mapping updated successfully!');
    }

    
    
}
