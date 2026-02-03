<?php

namespace App\Http\Controllers;

use App\Models\Fund;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $query = Fund::query();

        // 1. Year Filter (Required for all reports)
        $year = $request->get('year', date('Y'));
        $query->whereYear('transaction_date', $year);

        // 2. Monthly Filter
        if ($request->filled('month')) {
            $query->whereMonth('transaction_date', $request->month);
        }

        // 3. Quarterly Filter
        if ($request->filled('quarter')) {
            $quarter = $request->quarter;
            // Q1: Jan-Mar, Q2: Apr-Jun, etc.
            $months = match($quarter) {
                '1' => [1, 2, 3],
                '2' => [4, 5, 6],
                '3' => [7, 8, 9],
                '4' => [10, 11, 12],
                default => []
            };
            $query->whereIn(\DB::raw('MONTH(transaction_date)'), $months);
        }

        $funds = $query->orderBy('transaction_date', 'desc')->get();
        $totalAmount = $funds->sum('amount');

        return view('admin.reports', compact('funds', 'totalAmount', 'year'));
    }
}