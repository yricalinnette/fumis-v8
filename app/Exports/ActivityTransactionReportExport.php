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

class ActivityTransactionReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnFormatting
{
    protected $groupedReport;

    public function __construct($groupedReport)
    {
        $this->groupedReport = $groupedReport;
    }

    public function collection()
    {
        $rows = collect();

        foreach ($this->groupedReport as $section) {
            foreach ($section['sources'] as $source) {
                foreach ($source['activities'] as $act) {
                    if ($act['transactions']->isNotEmpty()) {
                        foreach ($act['transactions'] as $tx) {
                            $rows->push([
                                'section_name' => $section['section_name'],
                                'source_name'  => $source['source_name'],
                                'activity_name'=> $act['details']->name,
                                'tx'           => $tx,
                                'act_summary'  => $act
                            ]);
                        }
                    } else {
                        // Include activities without transactions
                        $rows->push([
                            'section_name' => $section['section_name'],
                            'source_name'  => $source['source_name'],
                            'activity_name'=> $act['details']->name,
                            'tx'           => null,
                            'act_summary'  => $act
                        ]);
                    }
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
            'Activity Name',
            'Tx Date',
            'DTrack / OBRN',
            'Payee / Creditor',
            'Particulars',
            'Amount',
            'Obligated Amount',
            'Disbursed Amount',
            'Status',
            'Remarks'
        ];
    }

    public function map($row): array
    {
        $tx = $row['tx'];

        if (!$tx) {
            return [
                $row['section_name'],
                $row['source_name'],
                $row['activity_name'],
                '-', 
                '-', 
                '-', 
                'No Transactions Found',
                0.00, 
                0.00, 
                0.00,
                'N/A', 
                '-'
            ];
        }

        // Creditor mapping
        $creditorName = $tx->creditor ?? null;
        if (!$creditorName && isset($tx->creditors) && $tx->creditors->isNotEmpty()) {
            $creditorName = $tx->creditors->map(function($c) {
                return $c->employeeDetail->fullname ?? $c->full_name ?? null;
            })->filter()->implode(', ');
        }

        $date = $tx->obligation_date 
            ? \Carbon\Carbon::parse($tx->obligation_date)->format('Y-m-d') 
            : $tx->created_at->format('Y-m-d');

        $dtrackObrn = trim(($tx->dtrack_no ?? '') . ' ' . ($tx->obligation_serial ?? ''));

        return [
            $row['section_name'],
            $row['source_name'],
            $row['activity_name'],
            $date,
            $dtrackObrn ?: '-',
            $creditorName ?: '-',
            $tx->particulars ?? '-',
            (float) ($tx->amount ?? 0),
            (float) ($tx->obligation_amount ?? 0),
            (float) ($tx->disbursement_amount ?? 0),
            $tx->status ?? 'N/A',
            trim(($tx->remarks ?? '') . ' ' . ($tx->manual_remarks ?? ''))
        ];
    }

    /**
     * Excel Column Format Specifications (2 Decimal Places for amounts)
     */
    public function columnFormats(): array
    {
        return [
            'H' => '#,##0.00', // Amount
            'I' => '#,##0.00', // Obligated Amount
            'J' => '#,##0.00', // Disbursed Amount
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // 1. Set Header Row Height & Navy Blue Banner Style
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 11,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '001F3F'], // Corporate Navy Blue
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // 2. Set specific column widths & text wrapping for detailed text columns
        $sheet->getColumnDimension('A')->setWidth(25); // Section
        $sheet->getColumnDimension('B')->setWidth(25); // Fund Source

        $sheet->getStyle('C')->getAlignment()->setWrapText(true);
        $sheet->getColumnDimension('C')->setWidth(30); // Activity Name

        $sheet->getColumnDimension('D')->setWidth(14); // Tx Date
        $sheet->getColumnDimension('E')->setWidth(20); // DTrack / OBRN
        
        $sheet->getStyle('F')->getAlignment()->setWrapText(true);
        $sheet->getColumnDimension('F')->setWidth(25); // Payee / Creditor

        $sheet->getStyle('G')->getAlignment()->setWrapText(true);
        $sheet->getColumnDimension('G')->setWidth(35); // Particulars

        $sheet->getColumnDimension('K')->setWidth(18); // Status
        
        $sheet->getStyle('L')->getAlignment()->setWrapText(true);
        $sheet->getColumnDimension('L')->setWidth(30); // Remarks

        // 3. Auto-fit numerical columns (Amount, Obligated Amount, Disbursed Amount)
        foreach (range('H', 'J') as $col) {
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