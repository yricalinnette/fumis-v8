<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\SourceOfFund;
use Illuminate\Http\Request;
use App\Imports\WfpActivitiesImport;

class ActivityController extends Controller
{
    public function store(Request $request)
    {
        // Validate basics first
        $request->validate([
            'source_of_fund_id' => 'required|exists:source_of_funds,id',
            'name' => 'required|string',
            'budget' => 'required|numeric|min:0',
        ]);

        $source = SourceOfFund::findOrFail($request->source_of_fund_id);

        // Calculate how much has already been given to other activities
        $alreadyAllocated = $source->activities()->sum('budget');
        $remaining = $source->total_amount - $alreadyAllocated;

        // Strict Check: Don't allow the new budget to exceed what's left
        if ($request->budget > $remaining) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['budget' => 'Allocation failed. You only have ₱' . number_format($remaining, 2) . ' left in this source.']);
        }

        Activity::create($request->all());

        return redirect()->back()->with('success', 'Activity budget allocated successfully!');
    }

    public function destroy($id)
    {
        $activity = Activity::findOrFail($id);
        $activity->delete();
        return redirect()->back()->with('success', 'Activity removed successfully.');
    }

    public function import(Request $request)
    {
        $request->validate(['wfp_file' => 'required|mimes:xlsx,xls,csv']);

        try {
            \DB::beginTransaction();

            // Use the instance directly to ensure the constructor runs properly
            $import = new \App\Imports\WfpActivitiesImport();
            \Excel::import($import, $request->file('wfp_file'));

            \DB::commit();
            
            // Add a check here: if nothing was saved, it's not really a "success"
            return back()->with('success', 'Import process finished!');

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Import Error: ' . $e->getMessage());
            return back()->withErrors(['import_error' => $e->getMessage()])->withInput();
        }
    }
}