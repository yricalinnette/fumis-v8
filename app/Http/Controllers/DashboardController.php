<?php

namespace App\Http\Controllers;

use App\Models\SourceOfFund;
use App\Models\Fund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $selectedYear = $request->get('year', date('Y'));
        $currentUser = auth()->user();
        
        // Use the Gate defined in your boot() method
        // This now correctly checks is_admin OR the "Budget Unit" section in db_common
        $isAdminOrBudget = \Illuminate\Support\Facades\Gate::allows('budget-section');

        // 2. Fetch Sources
        $query = \App\Models\SourceOfFund::where('fiscal_year', $selectedYear);

        // If the user is NOT Admin/Budget, filter the query by their specific section
        if (!$isAdminOrBudget) {
            $localDetail = \DB::table('employee_details')->where('user_id', $currentUser->id)->first();
            if ($localDetail) {
                $userSecId = \DB::connection('db_common')->table('tbl_emp_details')
                    ->where('dbedid', $localDetail->dbedid)
                    ->value('secid');
                
                if ($userSecId) {
                    $query->where('section_id', $userSecId);
                }
            }
        }

        // 3. Get results (If Budget Unit, this fetches all sections)
        $sources = $query->with(['activities', 'funds' => function($query) use ($selectedYear) {
            $query->where(function($q) use ($selectedYear) {
                $q->whereYear('obligation_date', $selectedYear)
                ->orWhere(function($sub) use ($selectedYear) {
                    $sub->whereNull('obligation_date')->whereYear('created_at', $selectedYear);
                });
            })->whereNotIn('status', ['Cancelled', 'Rejected']);
        }])->get();

        // 3. Fetch Section Names from db_common for grouping labels
        $sectionNames = \DB::connection('db_common')->table('tbl_section')
            ->pluck('secname', 'secid');

        // 4. Map and Process Data
        $chartData = $sources->map(function ($source) use ($selectedYear, $sectionNames) {
            $originalAllotted = (float) $source->total_amount;
            $totalPooled = (float) $source->activities->sum('pooled_amount');
            $adjustedAllotted = $originalAllotted - $totalPooled;

            $obligatedTotal = $source->funds->filter(fn($f) => $f->obligation_date && \Carbon\Carbon::parse($f->obligation_date)->year == $selectedYear)->sum('obligation_amount');
            $disbursedTotal = $source->funds->filter(fn($f) => $f->obligation_date && \Carbon\Carbon::parse($f->obligation_date)->year == $selectedYear)->sum('disbursement_amount');
            
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
                'obligated_total'  => $obligatedTotal,
                'disbursed_total'  => $disbursedTotal,
                'remaining_budget'  => $untouchedBudget > 0 ? $untouchedBudget : 0,
                'ob_rate'           => $adjustedAllotted > 0 ? round(($obligatedTotal / $adjustedAllotted) * 100, 1) : 0,
                'disb_rate'         => $obligatedTotal > 0 ? round(($disbursedTotal / $obligatedTotal) * 100, 1) : 0,
                'percent'           => $adjustedAllotted > 0 ? round(($processedTotal / $adjustedAllotted) * 100, 1) : 0,
                'last_updated'      => $source->funds->max('updated_at') ? $source->funds->max('updated_at')->diffForHumans() : 'No activity',
            ];
        });

        // 5. Group Data by Section
        $groupedData = $chartData->groupBy('section_id');

        return view('dashboard', compact('groupedData', 'selectedYear', 'isAdminOrBudget', 'chartData'));
    }
}