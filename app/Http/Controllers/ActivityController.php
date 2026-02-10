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
            $file = $request->file('wfp_file');
            $import = new \App\Imports\WfpActivitiesImport($request->fund_source_id, $file->getPathname());
            \Maatwebsite\Excel\Facades\Excel::import($import, $file);

            // 1. Separate actual failures from warnings if possible, 
            // but for now, let's just fix the math:
            $created = $import->createdCount;
            $updated = $import->updatedCount;
            $notesCount = count($import->failures);

            // 2. Logic: If the row was created or updated, it's NOT an "Issue Found" 
            // even if it has a note. "Issues" should be for rows that totally failed.
            // For a quick fix, let's label them "Notifications" instead of "Issues".

            return back()
                ->with('success', "Import completed.")
                ->with('import_notes', $import->failures)
                ->with('import_summary', [
                    'created'  => $created,
                    'updated'  => $updated,
                    'failures' => $notesCount, // These are warnings/notes
                    'total'    => $created + $updated, // Total successfully synced
                ]);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Import Error: ' . $e->getMessage()]);
        }
    }

    public function downloadTemplate()
    {
        $fileName = 'WFP_Template_' . now()->format('Y-m-d') . '.xlsx';
        return Excel::download(new WfpTemplateExport, $fileName);
    }

}