<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; }
        .header-table, .main-table { width: 100%; border-collapse: collapse; }
        .main-table th, .main-table td { border: 1px solid black; padding: 4px; text-align: center; }
        .text-left { text-align: left !important; }
        .bg-gray { background-color: #f2f2f2; }
        .logo { width: 60px; }
        @page { margin: 1cm; }
    </style>
</head>
<body>
    {{-- Header Section --}}
    <table class="header-table">
        <tr>
            <td width="20%"><img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images\doh_logo.jpg'))) }}" class="logo"></td>
            <td width="60%" align="center">
                Republic of the Philippines<br>
                <strong>Department of Health</strong><br><br>
                <strong>WFP Form 1. Work and Financial Plan</strong>
            </td>
            <td width="20%" align="right">Document Code: [_______]</td>
        </tr>
    </table>

    <br>

    <table style="width: 100%; border: none; font-family: sans-serif; font-size: 12px;">
        <tr>
            <td style="width: 15%;">Department:</td>
            <td style="border-bottom: 1px solid black; font-weight: bold;">
                Department of Health - Eastern Visayas Center for Health Development
            </td>
        </tr>
        <tr>
            <td style="width: 40%;">Central Office / Bureau / Office / CHD / Hospitals / Sanitaria / DA-TRC / Other Health Facilities:</td>
            <td style="border-bottom: 1px solid black; font-weight: bold; text-align: right;">
                Local Health Support Division - UHC-PSC - {{ $sectionName }}
            </td>
        </tr>
        <tr>
            <td>Calendar Year:</td>
            <td style="border-bottom: 1px solid black; font-weight: bold; width: 150px;">
                {{ $calendarYear }}
            </td>
        </tr>
    </table>

    {{-- Signatories Section --}}
    <table class="main-table">
        <tr class="bg-gray">
            <th>Prepared by:</th>
            <th>Recommending Approval by:</th>
            <th>Approved by:</th>
        </tr>
        <tr>
            <td><strong>{{ $meta['prepared_by'] }}</strong><br>Computer Maintenance Technologist III</td>
            <td><strong>{{ $meta['recommending'] }}</strong><br>Director III</td>
            <td><strong>{{ $meta['approved_by'] }}</strong><br>Director IV</td>
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
            @foreach($activities as $activity) {{-- This line creates the $activity variable --}}
            <tr>
                <td class="text-left">
                    {{ $activity->budgetLineItem->budget_line_item_name ?? 'N/A' }}
                </td>
                <td class="text-left">{{ $activity->objective }}</td>
                <td class="text-left">
                    <strong>{{ $activity->name }}</strong>
                </td>
                {{-- Start Date formatted as: 1 May 2026 --}}
                <td class="text-center">
                    {{ \Carbon\Carbon::parse($activity->start_date)->format('j M Y') }}
                </td>

                {{-- End Date formatted as: 31 Dec 2026 --}}
                <td class="text-center">
                    {{ \Carbon\Carbon::parse($activity->end_date)->format('j M Y') }}
                </td>
                
                @php 
                    // If it's already an array, use it. If it's a string, decode it.
                    $targets = is_array($activity->physical_targets) 
                            ? $activity->physical_targets 
                            : json_decode($activity->physical_targets, true) ?? []; 
                @endphp
                
                <td>{{ $targets['Q1'] ?? '' }}</td>
                <td>{{ $targets['Q2'] ?? '' }}</td>
                <td>{{ $targets['Q3'] ?? '' }}</td>
                <td>{{ $targets['Q4'] ?? '' }}</td>
                
                <td>{{ number_format($activity->budget_adjusted, 2) }}</td>
                <td>{{ $activity->source->name ?? 'N/A' }}</td>
                <td class="text-left">{{ $activity->computed_secname }}</td>
                
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>