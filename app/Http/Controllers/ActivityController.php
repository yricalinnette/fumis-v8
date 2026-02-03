<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\SourceOfFund;
use Illuminate\Http\Request;

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
}