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
        // 1. Capture selected year (defaults to 2026)
        $selectedYear = $request->get('year', date('Y'));

        // 2. Query Sources based on the fiscal_year column
        $sources = \App\Models\SourceOfFund::where('fiscal_year', $selectedYear)
            ->with(['funds' => function($query) use ($selectedYear) {
                // Strict Year Filter: Only transactions obligated in that year
                $query->whereYear('obligation_date', $selectedYear);
            }])->get();

        // 3. Transform for the Charts
        $chartData = $sources->map(function ($source) {
            $allotted = (float) $source->total_amount;
            $obligated = (float) $source->funds->sum('obligation_amount');
            $disbursed = (float) $source->funds->sum('disbursement_amount');
            
            return [
                'name'              => $source->name,
                'total_allotted'    => $allotted,
                'processed_total'   => $obligated, // We use obligation as "processed"
                'remaining_budget'  => $allotted - $obligated,
                'obligated_total'   => $obligated,
                'disbursed_total'   => $disbursed,
                'percent'           => $allotted > 0 ? round(($obligated / $allotted) * 100, 1) : 0,
                'ob_rate'           => $allotted > 0 ? round(($obligated / $allotted) * 100, 1) : 0,
                'disb_rate'         => $obligated > 0 ? round(($disbursed / $obligated) * 100, 1) : 0,
                'last_updated'      => $source->funds->max('updated_at') ? $source->funds->max('updated_at')->diffForHumans() : 'No activity',
            ];
        });

        return view('dashboard', compact('chartData', 'selectedYear'));
    }
}