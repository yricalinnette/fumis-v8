<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
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

    @php
        // Determine layout width: 50% for 2 signatories, 33.3% for 3
        $isThreeColumn = ($currentWfpType == 'saa');
        $columnWidth = $isThreeColumn ? '33.3%' : '50%';
        
        // Define the middle key if it exists
        $midKey = $isThreeColumn ? 'recommending approval by' : null;
    @endphp

    <table style="width: 100%; border-collapse: collapse; margin-top: 30px; font-size: 11px; table-layout: fixed;">
        <tr>
            {{-- 1. Prepared By - Always Shows --}}
            <td style="width: {{ $columnWidth }}; border: 1px solid black; padding: 10px; text-align: center; vertical-align: top;">
                <div style="text-align: left;">Prepared by:</div>
                <br><br>
                <div style="font-weight: bold; text-decoration: underline; text-transform: uppercase;">
                    {{ $signatories['prepared by']->employee_name ?? '__________________________' }}
                </div>
                <div>{{ $signatories['prepared by']->designation ?? 'Designation' }}</div>
            </td>

            {{-- 2. Recommending Approval - ONLY shows if SAA --}}
            @if($isThreeColumn)
            <td style="width: {{ $columnWidth }}; border: 1px solid black; padding: 10px; text-align: center; vertical-align: top;">
                <div style="text-align: left;">Recommending Approval by:</div>
                <br><br>
                <div style="font-weight: bold; text-decoration: underline; text-transform: uppercase;">
                    {{ $signatories['recommending approval by']->employee_name ?? '__________________________' }}
                </div>
                <div>{{ $signatories['recommending approval by']->designation ?? 'Designation' }}</div>
            </td>
            @endif

            {{-- 3. Approved By / Reviewed By --}}
            <td style="width: {{ $columnWidth }}; border: 1px solid black; padding: 10px; text-align: center; vertical-align: top;">
                <div style="text-align: left;">
                    {{ $isThreeColumn ? 'Approved by:' : 'Approved by:' }}
                </div>
                <br><br>
                <div style="font-weight: bold; text-decoration: underline; text-transform: uppercase;">
                    @php 
                        $finalKey = $isThreeColumn ? 'approved by' : 'approved by'; 
                    @endphp
                    {{ $signatories[$finalKey]->employee_name ?? '__________________________' }}
                </div>
                <div>{{ $signatories[$finalKey]->designation ?? ($isThreeColumn ? 'Director IV' : 'Designation') }}</div>
            </td>
        </tr>
        
        {{-- Dynamic Date Row --}}
        <tr>
            <td style="border: 1px solid black; padding: 5px;">Date:</td>
            @if($isThreeColumn)
                <td style="border: 1px solid black; padding: 5px;">Date:</td>
            @endif
            <td style="border: 1px solid black; padding: 5px;">Date:</td>
        </tr>
    </table>

    <br>

    {{-- Main Content --}}
    <table class="main-table">
        <thead class="bg-white">
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
                            <td class="text-left" style="vertical-align: top;">{{ $activity->budgetLineItem->budget_line_item_name ?? 'N/A' }}</td>
                            <td class="text-left" style="vertical-align: top;">{{ $activity->objective }}</td>
                            
                            <td class="text-left" style="white-space: pre-wrap; vertical-align: top;">{{ $activity->name }}</td>
                            
                            <td class="text-center" style="vertical-align: top;">{{ \Carbon\Carbon::parse($activity->start_date)->format('j M Y') }}</td>
                            <td class="text-center" style="vertical-align: top;">{{ \Carbon\Carbon::parse($activity->end_date)->format('j M Y') }}</td>
                            
                            @php 
                                $targets = is_array($activity->physical_targets) 
                                        ? $activity->physical_targets 
                                        : json_decode($activity->physical_targets, true) ?? []; 
                            @endphp
                            
                            <td style="vertical-align: top;">{{ $targets['Q1'] ?? '' }}</td>
                            <td style="vertical-align: top;">{{ $targets['Q2'] ?? '' }}</td>
                            <td style="vertical-align: top;">{{ $targets['Q3'] ?? '' }}</td>
                            <td style="vertical-align: top;">{{ $targets['Q4'] ?? '' }}</td>
                            
                            <td class="text-right" style="vertical-align: top;">{{ number_format($activity->budget_adjusted, 2) }}</td>
                            <td style="vertical-align: top;">{{ $activity->source->name ?? 'N/A' }}</td>
                            <td class="text-left" style="vertical-align: top;">{{ $activity->computed_secname }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="12" style="color: #a0a0a0; font-style: italic; text-align: center;">No activities recorded under this classification.</td>
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