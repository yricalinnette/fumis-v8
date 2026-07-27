<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class BudgetBySourceExport implements FromView, ShouldAutoSize
{
    protected $groupedReport;

    public function __construct($groupedReport)
    {
        $this->groupedReport = $groupedReport;
    }

    public function view(): View
    {
        return view('admin.reports.exports.by_source_excel', [
            'groupedReport' => $this->groupedReport
        ]);
    }
}