<table>
    <thead>
        <tr>
            <th style="font-weight: bold; background-color: #001f3f; color: #ffffff;">Fund Source</th>
            <th style="font-weight: bold; background-color: #001f3f; color: #ffffff; text-align: center;">Procurable %</th>
            <th style="font-weight: bold; background-color: #001f3f; color: #ffffff; text-align: center;">Non-Procurable %</th>
            <th style="font-weight: bold; background-color: #001f3f; color: #ffffff; text-align: right;">Total Allotment</th>
            <th style="font-weight: bold; background-color: #001f3f; color: #ffffff; text-align: right;">Obligated</th>
            <th style="font-weight: bold; background-color: #001f3f; color: #ffffff; text-align: center;">Oblig. Rate(%)</th>
            <th style="font-weight: bold; background-color: #001f3f; color: #ffffff; text-align: right;">Disbursed</th>
            <th style="font-weight: bold; background-color: #001f3f; color: #ffffff; text-align: center;">Disb. Rate(%)</th>
            <th style="font-weight: bold; background-color: #001f3f; color: #ffffff; text-align: right;">Pending Transactions</th>
            <th style="font-weight: bold; background-color: #001f3f; color: #ffffff; text-align: right;">Unpaid Obligations</th>
            <th style="font-weight: bold; background-color: #001f3f; color: #ffffff; text-align: right;">Unobligated</th>
        </tr>
    </thead>
    <tbody>
        @php
            $grandTotals = ['allotted' => 0, 'obligated' => 0, 'disbursed' => 0, 'pending' => 0, 'procurable' => 0, 'non_procurable' => 0];
        @endphp

        @foreach($groupedReport as $sectionName => $sources)
            @php
                $sectionTotals = ['allotted' => 0, 'obligated' => 0, 'disbursed' => 0, 'pending' => 0, 'procurable' => 0, 'non_procurable' => 0];
            @endphp
            
            {{-- Section Title Row --}}
            <tr>
                <td colspan="11" style="font-weight: bold; background-color: #e9ecef;">{{ $sectionName }}</td>
            </tr>

            @foreach($sources as $data)
                @php
                    $currentAllotted  = $data['source_total'];
                    $currentObligated = $data['total_obligated'];
                    $currentDisbursed = $data['total_disbursed'];
                    $currentPending   = $data['total_pending'];
                    $budgetP          = $data['procurable_budget_total']; 
                    $budgetNP         = $data['non_procurable_budget_total'];
                    
                    $percP  = $currentAllotted > 0 ? ($budgetP / $currentAllotted) * 100 : 0;
                    $percNP = $currentAllotted > 0 ? ($budgetNP / $currentAllotted) * 100 : 0;
                    $unpaid      = $currentObligated - $currentDisbursed;
                    $unobligated = $data['total_unobligated'];

                    $sectionTotals['allotted'] += $currentAllotted;
                    $sectionTotals['obligated'] += $currentObligated;
                    $sectionTotals['disbursed'] += $currentDisbursed;
                    $sectionTotals['pending'] += $currentPending;
                    $sectionTotals['procurable'] += $budgetP;
                    $sectionTotals['non_procurable'] += $budgetNP;

                    $grandTotals['allotted'] += $currentAllotted;
                    $grandTotals['obligated'] += $currentObligated;
                    $grandTotals['disbursed'] += $currentDisbursed;
                    $grandTotals['pending'] += $currentPending;
                    $grandTotals['procurable'] += $budgetP;
                    $grandTotals['non_procurable'] += $budgetNP;
                @endphp
                <tr>
                    <td>{{ $data['source_name'] }}</td>
                    <td style="text-align: center;">{{ number_format($percP, 1) }}%</td>
                    <td style="text-align: center;">{{ number_format($percNP, 1) }}%</td>
                    <td style="text-align: right;">{{ $currentAllotted }}</td>
                    <td style="text-align: right;">{{ $currentObligated }}</td>
                    <td style="text-align: center;">{{ number_format($data['overall_oblig_rate'], 1) }}%</td>
                    <td style="text-align: right;">{{ $currentDisbursed }}</td>
                    <td style="text-align: center;">{{ number_format($data['overall_disb_rate'], 1) }}%</td>
                    <td style="text-align: right;">{{ $currentPending }}</td>
                    <td style="text-align: right;">{{ $unpaid }}</td>
                    <td style="text-align: right;">{{ $unobligated }}</td>
                </tr>
            @endforeach

            {{-- Section Subtotal Row --}}
            @php
                $secObligRate = $sectionTotals['allotted'] > 0 ? ($sectionTotals['obligated'] / $sectionTotals['allotted']) * 100 : 0;
                $secDisbRate = $sectionTotals['obligated'] > 0 ? ($sectionTotals['disbursed'] / $sectionTotals['obligated']) * 100 : 0;
            @endphp
            <tr>
                <td style="font-weight: bold; background-color: #f4f6f9;">Total: {{ $sectionName }}</td>
                <td style="text-align: center; background-color: #f4f6f9;">{{ number_format($sectionTotals['allotted'] > 0 ? ($sectionTotals['procurable'] / $sectionTotals['allotted']) * 100 : 0, 1) }}%</td>
                <td style="text-align: center; background-color: #f4f6f9;">{{ number_format($sectionTotals['allotted'] > 0 ? ($sectionTotals['non_procurable'] / $sectionTotals['allotted']) * 100 : 0, 1) }}%</td>
                <td style="text-align: right; font-weight: bold; background-color: #f4f6f9;">{{ $sectionTotals['allotted'] }}</td>
                <td style="text-align: right; font-weight: bold; background-color: #f4f6f9;">{{ $sectionTotals['obligated'] }}</td>
                <td style="text-align: center; background-color: #f4f6f9;">{{ number_format($secObligRate, 2) }}%</td>
                <td style="text-align: right; font-weight: bold; background-color: #f4f6f9;">{{ $sectionTotals['disbursed'] }}</td>
                <td style="text-align: center; background-color: #f4f6f9;">{{ number_format($secDisbRate, 2) }}%</td>
                <td style="text-align: right; font-weight: bold; background-color: #f4f6f9;">{{ $sectionTotals['pending'] }}</td>
                <td style="text-align: right; font-weight: bold; background-color: #f4f6f9;">{{ $sectionTotals['obligated'] - $sectionTotals['disbursed'] }}</td>
                <td style="text-align: right; font-weight: bold; background-color: #f4f6f9;">{{ $sectionTotals['allotted'] - $sectionTotals['obligated'] }}</td>
            </tr>
        @endforeach
    </tbody>
    
    {{-- Grand Total Row --}}
    <tfoot>
        @php
            $gtObligRate = $grandTotals['allotted'] > 0 ? ($grandTotals['obligated'] / $grandTotals['allotted']) * 100 : 0;
            $gtDisbRate = $grandTotals['obligated'] > 0 ? ($grandTotals['disbursed'] / $grandTotals['obligated']) * 100 : 0;
        @endphp
        <tr>
            <td style="font-weight: bold; background-color: #d1d8e0;">Grand Total</td>
            <td style="text-align: center; background-color: #d1d8e0;">{{ number_format($grandTotals['allotted'] > 0 ? ($grandTotals['procurable'] / $grandTotals['allotted']) * 100 : 0, 1) }}%</td>
            <td style="text-align: center; background-color: #d1d8e0;">{{ number_format($grandTotals['allotted'] > 0 ? ($grandTotals['non_procurable'] / $grandTotals['allotted']) * 100 : 0, 1) }}%</td>
            <td style="text-align: right; font-weight: bold; background-color: #d1d8e0;">{{ $grandTotals['allotted'] }}</td>
            <td style="text-align: right; font-weight: bold; background-color: #d1d8e0;">{{ $grandTotals['obligated'] }}</td>
            <td style="text-align: center; background-color: #d1d8e0;">{{ number_format($gtObligRate, 2) }}%</td>
            <td style="text-align: right; font-weight: bold; background-color: #d1d8e0;">{{ $grandTotals['disbursed'] }}</td>
            <td style="text-align: center; background-color: #d1d8e0;">{{ number_format($gtDisbRate, 2) }}%</td>
            <td style="text-align: right; font-weight: bold; background-color: #d1d8e0;">{{ $grandTotals['pending'] }}</td>
            <td style="text-align: right; font-weight: bold; background-color: #d1d8e0;">{{ $grandTotals['obligated'] - $grandTotals['disbursed'] }}</td>
            <td style="text-align: right; font-weight: bold; background-color: #d1d8e0;">{{ $grandTotals['allotted'] - $grandTotals['obligated'] }}</td>
        </tr>
    </tfoot>
</table>