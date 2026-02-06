<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\SourceOfFund;
use Illuminate\Http\Request;
use App\Imports\WfpActivitiesImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\WfpTemplateExport;


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

    //for import of wfp template
    public function importWFP(Request $request)
    {
        $request->validate([
            'fund_source_id' => 'required|exists:source_of_funds,id',
            'wfp_file'       => 'required|mimes:xlsx,xls,csv'
        ]);

        try {
            $import = new \App\Imports\WfpActivitiesImport($request->fund_source_id);
            
            // This will now throw the Exception if headers are wrong
            \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('wfp_file'));

            // Check if anything actually happened
            if (($import->createdCount + $import->updatedCount + count($import->failures)) === 0) {
                return back()->withErrors(['error' => 'No rows were processed. Please ensure to use the Downloadable WFP Template.']);
            }

            // Now failures will definitely have data because the importer caught them row-by-row
            return back()->with('import_summary', [
                'created'  => $import->createdCount,
                'updated'  => $import->updatedCount,
                'failures' => $import->failures,
                'total'    => $import->createdCount + $import->updatedCount + count($import->failures),
            ]);
        } catch (\Exception $e) {
            // This catches the "Header Mismatch" exception
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function downloadTemplate()
    {
        $fileName = 'WFP_Template_' . now()->format('Y-m-d') . '.xlsx';
        return Excel::download(new WfpTemplateExport, $fileName);
    }

}