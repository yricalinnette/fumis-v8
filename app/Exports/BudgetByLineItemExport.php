<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class BudgetByLineItemExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnFormatting
{
    protected $reportData;

    /**
     * Receive report data array/collection directly
     */
    public function __construct($reportData)
    {
        $this->reportData = $reportData;
    }

    public function collection()
    {
        $rows = collect();

        foreach ($this->reportData as $sectionName => $sources) {
            foreach ($sources as $source) {
                foreach ($source['line_items'] ?? [] as $item) {
                    $rows->push([
                        'section_name' => $sectionName,
                        'source_name'  => $source['source_name'],
                        'item_name'    => $item['name'],
                        'net_budget'   => (float) $item['net_budget'],
                        'obligated'    => (float) $item['obligated_amount'],
                        'oblig_rate'   => (float) ($item['obligation_rate'] / 100), // Pass raw decimal for Excel percentage format
                        'disbursed'    => (float) $item['disbursed_amount'],
                        'disb_rate'    => (float) ($item['disbursement_rate'] / 100), // Pass raw decimal for Excel percentage format
                        'unpaid'       => (float) ($item['obligated_amount'] - $item['disbursed_amount']),
                        'savings'      => (float) ($item['savings_amount'] ?? 0),
                        'pending'      => (float) $item['pending_amount'],
                        'untouched'    => (float) ($item['untouched_amount'] ?? 0),
                    ]);
                }
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Section',
            'Fund Source',
            'Activity Details',
            'Allotted Budget',
            'Obligated',
            'Oblig. %',
            'Disbursed',
            'Disb. %',
            'Unpaid Obligations',
            'Savings (COS)',
            'Pending Transactions',
            'Unobligated'
        ];
    }

    public function map($row): array
    {
        return [
            $row['section_name'],
            $row['source_name'],
            $row['item_name'],
            $row['net_budget'],
            $row['obligated'],
            $row['oblig_rate'],
            $row['disbursed'],
            $row['disb_rate'],
            $row['unpaid'],
            $row['savings'],
            $row['pending'],
            $row['untouched'],
        ];
    }

    /**
     * Excel Column Format Specifications (2 Decimal Places)
     */
    public function columnFormats(): array
    {
        return [
            'D' => '#,##0.00', // Allotted Budget
            'E' => '#,##0.00', // Obligated
            'F' => '0.0%',     // Oblig. %
            'G' => '#,##0.00', // Disbursed
            'H' => '0.0%',     // Disb. %
            'I' => '#,##0.00', // Unpaid Obligations
            'J' => '#,##0.00', // Savings (COS)
            'K' => '#,##0.00', // Pending Transactions
            'L' => '#,##0.00', // Unobligated
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // 1. Set Header Row Height & Navy Blue Banner Style
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '001F3F'], // Corporate Navy Blue
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // 2. Wrap text & set fixed width for Activity Details (Column C)
        $sheet->getStyle('C')->getAlignment()->setWrapText(true);
        $sheet->getColumnDimension('C')->setWidth(35);

        // 3. Set widths for Section and Fund Source
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(25);

        // Auto-fit numerical columns
        foreach (range('D', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 4. Align all data rows vertically in the MIDDLE
        $highestRow = $sheet->getHighestRow();
        if ($highestRow > 1) {
            $sheet->getStyle("A2:L{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        return [];
    }
}