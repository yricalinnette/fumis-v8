@extends('layouts.adminlte')

@section('content')
<style>
    /* Design Consistency Tweaks */
    .financial-number { font-family: 'Courier New', Courier, monospace; font-weight: 600; }
    .card-navy.card-outline { border-top: 3px solid #001f3f; }
    .bg-navy-light { background-color: #f4f6f9; color: #001f3f; }
    .border-left-info { border-left: 4px solid #17a2b8 !important; }
    .border-left-success { border-left: 4px solid #28a745 !important; }
    .border-left-warning { border-left: 4px solid #ffc107 !important; }
    /* Section Divider Styling */
    .section-header-row { background-color: #e9ecef !important; color: #495057; }
    .table-sticky thead th { position: sticky; top: 0; z-index: 10; background: #fff; box-shadow: inset 0 -1px 0 #dee2e6; }
    @media print { .filter-section { display: none; } }
</style>

<div class="container-fluid">
    {{-- Header Section remains the same --}}
    <div class="row pt-3 mb-2">
        <div class="col-12 d-flex justify-content-between align-items-end">
            <div>
                <h4 class="text-navy mb-0 font-weight-bold">
                    <i class="fas fa-layer-group mr-2 text-muted"></i>By Source Budget Tracking
                </h4>
                <p class="text-muted small mb-0">
                    Summary for <strong>{{ request('year', date('Y')) }}</strong> 
                    @if(request('month')) | {{ date('F', mktime(0, 0, 0, request('month'), 1)) }}
                    @elseif(request('quarter')) | Quarter {{ request('quarter') }} @endif
                </p>
            </div>
            <div class="btn-group shadow-sm">
                <button class="btn btn-sm btn-default" data-toggle="collapse" data-target="#filterCard">
                    <i class="fas fa-filter mr-1"></i> Filters
                </button>
            </div>
        </div>
    </div>

    {{-- Filter Card remains the same --}}
    <div class="collapse show filter-section" id="filterCard">
        <div class="card shadow-sm mb-4 border-navy">
            <div class="card-body py-3">
                <form action="{{ route('reports.by_source') }}" method="GET" class="row align-items-end">
                    {{-- ... (Your existing filter selects) ... --}}
                    <div class="col-md-3">
                        <label class="text-xs font-weight-bold text-muted text-uppercase">Financial Quarter</label>
                        <select name="quarter" class="form-control form-control-sm border-navy">
                            <option value="">Full Year</option>
                            <option value="1" {{ request('quarter') == '1' ? 'selected' : '' }}>Q1 (Jan - Mar)</option>
                            <option value="2" {{ request('quarter') == '2' ? 'selected' : '' }}>Q2 (Apr - Jun)</option>
                            <option value="3" {{ request('quarter') == '3' ? 'selected' : '' }}>Q3 (Jul - Sep)</option>
                            <option value="4" {{ request('quarter') == '4' ? 'selected' : '' }}>Q4 (Oct - Dec)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="text-xs font-weight-bold text-muted text-uppercase">Specific Month</label>
                        <select name="month" class="form-control form-control-sm border-navy">
                            <option value="">All Months</option>
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="text-xs font-weight-bold text-muted text-uppercase">Fiscal Year</label>
                        <select name="year" class="form-control form-control-sm border-navy">
                            @php $currentYear = date('Y'); @endphp
                            @foreach(range($currentYear, $currentYear - 4) as $year)
                                <option value="{{ $year }}" {{ request('year', $currentYear) == $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-sm btn-primary px-4 shadow-sm">Update Report</button>
                        <a href="{{ route('reports.by_source') }}" class="btn btn-sm btn-default border ml-2">Reset</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <hr class="mt-2 mb-4">

    <div class="card card-navy card-outline shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover table-sticky mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase text-xs">
                            <th class="pl-4 py-3">Fund Source</th>
                            <th class="text-center py-3">Procurable</th>
                            <th class="text-center py-3">Non-Procurable</th>
                            <th class="text-right py-3">Total Allotment</th>
                            <th class="text-right py-3 bg-light border-left-info">Obligated</th>
                            <th class="text-center py-3 bg-light">Oblig. Rate(%)</th>
                            <th class="text-right py-3 border-left-success">Disbursed</th>
                            <th class="text-center py-3">Disb. Rate(%)</th>
                            <th class="text-right py-3 border-left-warning">Pending Transactions</th>
                            <th class="text-right py-3 bg-light">Unpaid Obligations</th>
                            <th class="text-right py-3 pr-4">Unobligated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $grandTotals = [
                                'allotted' => 0, 'obligated' => 0, 'disbursed' => 0, 
                                'pending' => 0, 'procurable' => 0, 'non_procurable' => 0
                            ];
                        @endphp

                        @foreach($groupedReport as $sectionName => $sources)
                            @php
                                // Initialize Section Totals
                                $sectionTotals = [
                                    'allotted' => 0, 'obligated' => 0, 'disbursed' => 0, 
                                    'pending' => 0, 'procurable' => 0, 'non_procurable' => 0
                                ];
                            @endphp

                            <tr class="section-header-row">
                                <td colspan="11" class="pl-4 py-2">
                                    <i class="fas fa-building mr-2 text-muted"></i>
                                    <strong>{{ $sectionName }}</strong>
                                </td>
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

                                    // Update Section Totals
                                    $sectionTotals['allotted']       += $currentAllotted;
                                    $sectionTotals['obligated']      += $currentObligated;
                                    $sectionTotals['disbursed']      += $currentDisbursed;
                                    $sectionTotals['pending']        += $currentPending;
                                    $sectionTotals['procurable']     += $budgetP;
                                    $sectionTotals['non_procurable'] += $budgetNP;

                                    // Update Grand Totals
                                    $grandTotals['allotted']       += $currentAllotted;
                                    $grandTotals['obligated']      += $currentObligated;
                                    $grandTotals['disbursed']      += $currentDisbursed;
                                    $grandTotals['pending']        += $currentPending;
                                    $grandTotals['procurable']     += $budgetP;
                                    $grandTotals['non_procurable'] += $budgetNP;

                                    $obligClass = $data['overall_oblig_rate'] >= 90 ? 'badge-success' : ($data['overall_oblig_rate'] > 0 ? 'badge-warning' : 'badge-danger');
                                @endphp
                                {{-- Source Row --}}
                                <tr>
                                    <td class="pl-4 align-middle">
                                        <span class="text-navy font-weight-bold text-uppercase small d-block">{{ $data['source_name'] }}</span>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="text-xs font-weight-bold text-info">{{ number_format($percP, 1) }}%</span>
                                        <small class="d-block text-muted text-xs">₱{{ number_format($budgetP, 2) }}</small>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="text-xs font-weight-bold text-secondary">{{ number_format($percNP, 1) }}%</span>
                                        <small class="d-block text-muted text-xs">₱{{ number_format($budgetNP, 2) }}</small>
                                    </td>
                                    <td class="text-right align-middle financial-number">₱{{ number_format($currentAllotted, 2) }}</td>
                                    <td class="text-right align-middle financial-number text-info font-weight-bold border-left-info bg-light">₱{{ number_format($currentObligated, 2) }}</td>
                                    <td class="text-center align-middle bg-light">
                                        <span class="badge {{ $obligClass }} shadow-none">{{ number_format($data['overall_oblig_rate'], 1) }}%</span>
                                    </td>
                                    <td class="text-right align-middle financial-number text-success font-weight-bold border-left-success">₱{{ number_format($currentDisbursed, 2) }}</td>
                                    <td class="text-center align-middle">
                                        <span class="badge badge-light border">{{ number_format($data['overall_disb_rate'], 1) }}%</span>
                                    </td>
                                    <td class="text-right align-middle financial-number text-warning border-left-warning">₱{{ number_format($currentPending, 2) }}</td>
                                    <td class="text-right align-middle financial-number bg-light">₱{{ number_format($unpaid, 2) }}</td>
                                    <td class="text-right align-middle financial-number pr-4 {{ $unobligated < 0 ? 'text-danger' : 'text-primary' }}">₱{{ number_format($unobligated, 2) }}</td>
                                </tr>
                            @endforeach

                            {{-- Section Subtotal Row --}}
                            @php
                                $secObligRate = $sectionTotals['allotted'] > 0 ? ($sectionTotals['obligated'] / $sectionTotals['allotted']) * 100 : 0;
                                $secDisbRate = $sectionTotals['obligated'] > 0 ? ($sectionTotals['disbursed'] / $sectionTotals['obligated']) * 100 : 0;
                            @endphp
                            <tr class="bg-navy-light shadow-sm">
                                <td class="pl-4 py-3 align-middle text-uppercase small"><b>Total: {{ $sectionName }}</b></td>
                                <td class="text-center align-middle text-xs">{{ number_format($sectionTotals['allotted'] > 0 ? ($sectionTotals['procurable'] / $sectionTotals['allotted']) * 100 : 0, 1) }}%</td>
                                <td class="text-center align-middle text-xs">{{ number_format($sectionTotals['allotted'] > 0 ? ($sectionTotals['non_procurable'] / $sectionTotals['allotted']) * 100 : 0, 1) }}%</td>
                                <td class="text-right align-middle financial-number">₱{{ number_format($sectionTotals['allotted'], 2) }}</td>
                                <td class="text-right align-middle financial-number border-left-info text-info">₱{{ number_format($sectionTotals['obligated'], 2) }}</td>
                                <td class="text-center align-middle"><span class="text-xs">{{ number_format($secObligRate, 2) }}%</span></td>
                                <td class="text-right align-middle financial-number border-left-success text-success">₱{{ number_format($sectionTotals['disbursed'], 2) }}</td>
                                <td class="text-center align-middle"><span class="text-xs">{{ number_format($secDisbRate, 2) }}%</span></td>
                                <td class="text-right align-middle financial-number border-left-warning text-warning">₱{{ number_format($sectionTotals['pending'], 2) }}</td>
                                <td class="text-right align-middle financial-number">₱{{ number_format($sectionTotals['obligated'] - $sectionTotals['disbursed'], 2) }}</td>
                                <td class="text-right align-middle financial-number pr-4 text-primary">₱{{ number_format($sectionTotals['allotted'] - $sectionTotals['obligated'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    {{-- <tfoot class="bg-navy-light shadow-sm">
                        @php
                            $gtObligRate = $grandTotals['allotted'] > 0 ? ($grandTotals['obligated'] / $grandTotals['allotted']) * 100 : 0;
                            $gtDisbRate = $grandTotals['obligated'] > 0 ? ($grandTotals['disbursed'] / $grandTotals['obligated']) * 100 : 0;
                        @endphp
                        <tr class="font-weight-bold">
                            <td class="pl-4 py-3 align-middle text-uppercase small">Grand Total</td>
                            <td class="text-center align-middle text-navy text-xs">{{ number_format($grandTotals['allotted'] > 0 ? ($grandTotals['procurable'] / $grandTotals['allotted']) * 100 : 0, 1) }}%</td>
                            <td class="text-center align-middle text-navy text-xs">{{ number_format($grandTotals['allotted'] > 0 ? ($grandTotals['non_procurable'] / $grandTotals['allotted']) * 100 : 0, 1) }}%</td>
                            <td class="text-right align-middle financial-number">₱{{ number_format($grandTotals['allotted'], 2) }}</td>
                            <td class="text-right text-info align-middle financial-number border-left-info">₱{{ number_format($grandTotals['obligated'], 2) }}</td>
                            <td class="text-center align-middle bg-light"><span class="text-xs">{{ number_format($gtObligRate, 2) }}%</span></td>
                            <td class="text-right text-success align-middle financial-number border-left-success">₱{{ number_format($grandTotals['disbursed'], 2) }}</td>
                            <td class="text-center align-middle"><span class="text-xs">{{ number_format($gtDisbRate, 2) }}%</span></td>
                            <td class="text-right text-warning align-middle financial-number border-left-warning">₱{{ number_format($grandTotals['pending'], 2) }}</td>
                            <td class="text-right align-middle financial-number">₱{{ number_format($grandTotals['obligated'] - $grandTotals['disbursed'], 2) }}</td>
                            <td class="text-right align-middle financial-number pr-4 text-primary">₱{{ number_format($grandTotals['allotted'] - $grandTotals['obligated'], 2) }}</td>
                        </tr>
                    </tfoot> --}}
                </table>
            </div>
        </div>
    </div>
</div>
@endsection