<?php

namespace App\Exports;

use App\Models\ImportTemplate;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WfpTemplateExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    /**
     * Define the headings based on the dynamic names saved in your settings.
     */
    public function headings(): array
    {
        $template = ImportTemplate::first();
        
        // We export the EXACT values stored in the mapping settings
        return [
            $template->objective_col,
            $template->budget_line_col,
            $template->uacs_col,
            $template->activity_col,
            $template->budget_col,
            $template->source_col,
        ];
    }

    /**
     * Return an empty array for the data (since this is just a template).
     */
    public function array(): array
    {
        return [];
    }

    /**
     * Style the header row.
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4B5563'] // Professional Dark Gray
                ],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}