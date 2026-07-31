<?php

namespace App\Http\Controllers;

use App\Models\SourceOfFund;
use App\Models\Fund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $selectedYear = $request->get('year', date('Y'));
        $currentUser = auth()->user();
        
        // 1. Check Permissions via Gates
        $isAdminOrBudget = Gate::allows('budget-section');
        $isDivision = Gate::allows('division-access');

        // 2. Build Base Query for Sources of Fund
        $query = SourceOfFund::where('fiscal_year', $selectedYear);

        // --- DASHBOARD SCOPING LOGIC ---
        if (!$isAdminOrBudget) {
            if ($isDivision) {
                // Division Users see fund sources for ALL sections in their division
                $divisionSecIds = $currentUser->getDivisionSectionIds();
                $query->whereIn('section_id', $divisionSecIds);
            } else {
                // Regular Staff sees only their single home section
                $localDetail = DB::table('employee_details')
                    ->where('user_id', $currentUser->id)
                    ->first();

                if ($localDetail) {
                    $userSecId = DB::connection('db_common')
                        ->table('tbl_emp_details')
                        ->where('dbedid', $localDetail->dbedid)
                        ->value('secid');
                    
                    if ($userSecId) {
                        $query->where('section_id', $userSecId);
                    }
                }
            }
        }

        // 3. Get results with Eager Loaded Relations
        $sources = $query->with(['activities', 'funds' => function($query) use ($selectedYear) {
            $query->where(function($q) use ($selectedYear) {
                $q->whereYear('obligation_date', $selectedYear)
                ->orWhere(function($sub) use ($selectedYear) {
                    $sub->whereNull('obligation_date')->whereYear('created_at', $selectedYear);
                });
            })->whereNotIn('status', ['Cancelled', 'Rejected']);
        }])->get();

        // 4. Fetch Section Names from db_common for grouping labels
        $sectionNames = DB::connection('db_common')
            ->table('tbl_section')
            ->pluck('secname', 'secid');

        // 5. Map and Process Data
        $chartData = $sources->map(function ($source) use ($selectedYear, $sectionNames) {
            $originalAllotted = (float) $source->total_amount;
            $totalPooled = (float) $source->activities->sum('pooled_amount');
            $adjustedAllotted = $originalAllotted - $totalPooled;

            $obligatedTotal = $source->funds->filter(fn($f) => $f->obligation_date && Carbon::parse($f->obligation_date)->year == $selectedYear)->sum('obligation_amount');
            $disbursedTotal = $source->funds->filter(fn($f) => $f->obligation_date && Carbon::parse($f->obligation_date)->year == $selectedYear)->sum('disbursement_amount');
            
            $pendingTotal = $source->funds->filter(function($f) use ($selectedYear) {
                return (empty($f->obligation_date) || $f->obligation_amount <= 0) && ($f->disbursement_amount <= 0) && $f->created_at->year == $selectedYear;
            })->sum('amount');

            $processedTotal = $obligatedTotal + $pendingTotal;
            $untouchedBudget = $adjustedAllotted - $processedTotal;

            return [
                'section_id'        => $source->section_id,
                'section_name'      => $sectionNames[$source->section_id] ?? 'General/Unassigned',
                'name'              => $source->name,
                'total_allotted'    => $adjustedAllotted,
                'processed_total'   => $processedTotal, 
                'obligated_total'   => $obligatedTotal,
                'disbursed_total'   => $disbursedTotal,
                'remaining_budget'  => $untouchedBudget > 0 ? $untouchedBudget : 0,
                'ob_rate'           => $adjustedAllotted > 0 ? round(($obligatedTotal / $adjustedAllotted) * 100, 1) : 0,
                'disb_rate'          => $obligatedTotal > 0 ? round(($disbursedTotal / $obligatedTotal) * 100, 1) : 0,
                'percent'           => $adjustedAllotted > 0 ? round(($processedTotal / $adjustedAllotted) * 100, 1) : 0,
                'last_updated'      => $source->funds->max('updated_at') ? $source->funds->max('updated_at')->diffForHumans() : 'No activity',
            ];
        });

        // 6. Group Data by Section
        $groupedData = $chartData->groupBy('section_id');

        return view('dashboard', compact(
            'groupedData', 
            'selectedYear', 
            'isAdminOrBudget', 
            'isDivision', 
            'chartData'
        ));
    }
}