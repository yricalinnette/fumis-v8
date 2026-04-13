<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; }
        .header-table, .main-table { width: 100%; border-collapse: collapse; }
        .main-table th, .main-table td { border: 1px solid black; padding: 4px; text-align: center; }
        .text-left { text-align: left !important; }
        .text-right { text-align: right !important; }
        .bg-gray { background-color: #f2f2f2; }
        .logo { width: 60px; }
        
        .category-row { 
            background-color: #f2f2f2; 
            font-weight: bold; 
            text-align: left !important; 
            padding: 6px;
        }

        .subtotal-row {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .grandtotal-row {
            background-color: #e4baba; 
            font-weight: bold;
        }

        @page { margin: 1cm; }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            margin-top: 10px;
            margin-bottom: 5px;
        }
        .header-table td {
            padding: 2px 0;
            vertical-align: bottom;
        }
        .label-cell {
            white-space: nowrap;
            padding-right: 5px;
        }
        .underlined-value {
            border-bottom: 0.5px solid black;
            font-weight: bold;
            padding-left: 10px;
        }

        /* Signatory Specific Styling */
        .signatory-label { width: 12%; text-align: center; }
        .signatory-name { width: 21.3%; height: 50px; vertical-align: middle; }
        .date-row td { text-align: left; padding-left: 5px; height: 15px; }
    </style>
</head>
<body>
    {{-- Header Section --}}
    <table class="header-table">
        <tr>
            <td width="20%"><img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images\doh_seal.png'))) }}" class="logo"></td>
            <td width="60%" align="center">
                Republic of the Philippines<br>
                <strong>Department of Health</strong><br><br>
                <strong>WFP Form 1. Work and Financial Plan</strong>
            </td>
            <td width="20%" align="right">Document Code: [_______]</td>
        </tr>
    </table>

    <br>

    <table class="header-table">
        <tr>
            <td class="label-cell" style="width: 100px;">Department:</td>
            <td class="underlined-value" style="width: 350px;">Department of Health</td>
            <td></td> 
        </tr>
        <tr>
            <td class="label-cell" style="width: 450px;">
                Central Office / Bureau / Office / CHD / Hospitals / Sanitaria / DA-TRC / Other Health Facilities:
            </td>
            <td class="underlined-value">{{ $divisionName }}</td>
        </tr>
        <tr>
            <td class="label-cell">Calendar Year:</td>
            <td class="underlined-value" style="width: 250px;">{{ $calendarYear }}</td>
            <td></td> 
        </tr>
    </table>

    {{-- UPDATED Signatories Section to match image_925daa.png --}}
    <table class="main-table">
        <tr>
            <td class="signatory-label">Prepared by:</td>
            <td class="signatory-name">
                <strong>{{ $meta['prepared_by'] }}</strong><br>
                Computer Maintenance Technologist III
            </td>
            <td class="signatory-label">Recommending Approval by:</td>
            <td class="signatory-name">
                <strong>{{ $meta['recommending'] }}</strong><br>
                Head, UHC-PSC
            </td>
            <td class="signatory-label">Approved by:</td>
            <td class="signatory-name">
                <strong>{{ $meta['approved_by'] }}</strong><br>
                Chief, LHSD
            </td>
        </tr>
        <tr class="date-row">
            <td>Date:</td>
            <td></td>
            <td>Date:</td>
            <td></td>
            <td>Date:</td>
            <td></td>
        </tr>
    </table>

    <br>

    {{-- Main Content --}}
    <table class="main-table">
        <thead class="bg-gray">
            <tr>
                <th rowspan="2">BUDGET LINE ITEM</th>
                <th rowspan="2">OBJECTIVE</th>
                <th rowspan="2">ACTIVITIES</th>
                <th colspan="2">TIMEFRAME</th>
                <th colspan="4">TARGETS</th>
                <th colspan="2">RESOURCE REQUIREMENTS</th>
                <th rowspan="2">RESPONSIBLE UNIT</th>
            </tr>
            <tr>
                <th>Start</th>
                <th>End</th>
                <th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th>
                <th>COST</th>
                <th>SOURCE OF FUND</th>
            </tr>
        </thead>
        <tbody>
            @php
                $order = [
                    'Strategic' => 'A. Strategic Function',
                    'Core'      => 'B. Core Function',
                    'Support'   => 'C. Support Function'
                ];
                $grandTotal = 0;
            @endphp

            @foreach($order as $key => $label)
                @php $subTotal = 0; @endphp
                <tr>
                    <td colspan="12" class="category-row">{{ $label }}</td>
                </tr>

                @if(isset($groupedActivities[$key]) && count($groupedActivities[$key]) > 0)
                    @foreach($groupedActivities[$key] as $activity)
                    @php 
                        $subTotal += $activity->budget_adjusted;
                        $grandTotal += $activity->budget_adjusted;
                    @endphp
                    <tr>
                        <td class="text-left">{{ $activity->budgetLineItem->budget_line_item_name ?? 'N/A' }}</td>
                        <td class="text-left">{{ $activity->objective }}</td>
                        <td class="text-left"><strong>{{ $activity->name }}</strong></td>
                        <td class="text-center">{{ \Carbon\Carbon::parse($activity->start_date)->format('j M Y') }}</td>
                        <td class="text-center">{{ \Carbon\Carbon::parse($activity->end_date)->format('j M Y') }}</td>
                        
                        @php 
                            $targets = is_array($activity->physical_targets) 
                                    ? $activity->physical_targets 
                                    : json_decode($activity->physical_targets, true) ?? []; 
                        @endphp
                        
                        <td>{{ $targets['Q1'] ?? '' }}</td>
                        <td>{{ $targets['Q2'] ?? '' }}</td>
                        <td>{{ $targets['Q3'] ?? '' }}</td>
                        <td>{{ $targets['Q4'] ?? '' }}</td>
                        
                        <td class="text-right">{{ number_format($activity->budget_adjusted, 2) }}</td>
                        <td>{{ $activity->source->name ?? 'N/A' }}</td>
                        <td class="text-left">{{ $activity->computed_secname }}</td>
                    </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="12" style="color: #ffffff; font-style: italic;">No activities recorded under this classification.</td>
                    </tr>
                @endif

                <tr class="subtotal-row">
                    <td colspan="9" class="text-right">Sub-total {{ str_replace(['A. ', 'B. ', 'C. '], '', $label) }}</td>
                    <td class="text-right">{{ number_format($subTotal, 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            @endforeach

            <tr class="grandtotal-row">
                <td colspan="9" class="text-right">Total Cost (Strategic + Core + Support) Functions</td>
                <td class="text-right">{{ number_format($grandTotal, 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
</body>
</html>