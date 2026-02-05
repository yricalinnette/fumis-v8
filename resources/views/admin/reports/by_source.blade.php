@extends('layouts.adminlte')

@section('content')
<style>
    /* Design Consistency Tweaks */
    .financial-number { font-family: 'Courier New', Courier, monospace; font-weight: 600; }
    .card-navy.card-outline { border-top: 3px solid #001f3f; }
    .bg-navy-light { background-color: #f4f6f9; color: #001f3f; }
    .border-left-info { border-left: 4px solid #17a2b8 !important; }
    .border-left-success { border-left: 4px solid #28a745 !important; }
    .table-sticky thead th { position: sticky; top: 0; z-index: 10; background: #fff; box-shadow: inset 0 -1px 0 #dee2e6; }
    @media print { .filter-section { display: none; } }
</style>

<div class="container-fluid">
    <div class="row pt-3 mb-2">
        <div class="col-12 d-flex justify-content-between align-items-end">
            <div>
                <h4 class="text-navy mb-0 font-weight-bold">
                    <i class="fas fa-layer-group mr-2 text-muted"></i>By Source Budget Tracking
                </h4>
                <p class="text-muted small mb-0">
                    Summary for <strong>{{ request('year', date('Y')) }}</strong> 
                    @if(request('month')) 
                        | {{ date('F', mktime(0, 0, 0, request('month'), 1)) }}
                    @elseif(request('quarter'))
                        | Quarter {{ request('quarter') }}
                    @endif
                </p>
            </div>
            <div class="btn-group shadow-sm">
                <button class="btn btn-sm btn-default" data-toggle="collapse" data-target="#filterCard">
                    <i class="fas fa-filter mr-1"></i> Filters
                </button>
                {{-- <button class="btn btn-sm btn-navy px-3" onclick="window.print()">
                    <i class="fas fa-print mr-1"></i> Print Report
                </button> --}}
            </div>
        </div>
    </div>

    <div class="collapse {{ request('month') || request('quarter') ? 'show' : 'show' }} filter-section" id="filterCard">
        <div class="card shadow-sm mb-4 border-navy">
            <div class="card-body py-3">
                <form action="{{ route('reports.by_source') }}" method="GET" class="row align-items-end">
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
                                <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>
                                    {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="text-xs font-weight-bold text-muted text-uppercase">Fiscal Year</label>
                        <select name="year" class="form-control form-control-sm border-navy">
                            @php
                                $currentYear = date('Y');
                                $selectedYear = request('year', $currentYear);
                            @endphp
                            {{-- Generates options for the current year and 4 years back --}}
                            @foreach(range($currentYear, $currentYear - 4) as $year)
                                <option value="{{ $year }}" {{ $selectedYear == $year ? 'selected' : '' }}>
                                    {{ $year }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-sm btn-navy px-4 shadow-sm">
                            <i class="fas fa-sync-alt mr-1"></i> Update Report
                        </button>
                        <a href="{{ route('reports.by_source') }}" class="btn btn-sm btn-default border ml-2">
                            <i class="fas fa-undo mr-1"></i> Reset
                        </a>
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
                            <th class="pl-4 py-3" style="width: 20%">Fund Source</th>
                            <th class="text-right py-3">Total Allotted</th>
                            <th class="text-right py-3 bg-light border-left-info">Obligated</th>
                            <th class="text-center py-3 bg-light">Obligation Rate(%)</th>
                            <th class="text-right py-3 border-left-success">Disbursed</th>
                            <th class="text-center py-3">Disbursement Rate(%)</th>
                            <th class="text-right py-3 pr-4">Unobligated Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totals = ['allotted' => 0, 'obligated' => 0, 'disbursed' => 0];
                        @endphp

                        @foreach($reportData as $data)
                            @php
                                $totals['allotted'] += $data['allotted'];
                                $totals['obligated'] += $data['obligated'];
                                $totals['disbursed'] += $data['disbursed'];
                                
                                $obligClass = $data['obligation_rate'] >= 90 ? 'badge-success' : ($data['obligation_rate'] > 0 ? 'badge-warning' : 'badge-danger');
                                $disbClass = $data['disbursement_rate'] >= 90 ? 'badge-success' : ($data['disbursement_rate'] > 0 ? 'badge-warning' : 'badge-danger');
                            @endphp
                            <tr>
                                <td class="pl-4 align-middle">
                                    <span class="text-navy font-weight-bold text-uppercase small">{{ $data['name'] }}</span>
                                </td>
                                <td class="text-right align-middle financial-number text-muted">
                                    ₱{{ number_format($data['allotted'], 2) }}
                                </td>
                                <td class="text-right align-middle financial-number text-primary font-weight-bold border-left-info">
                                    ₱{{ number_format($data['obligated'], 2) }}
                                </td>
                                <td class="text-center align-middle bg-light">
                                    <span class="badge {{ $obligClass }} shadow-none" style="width: 55px;">
                                        {{ number_format($data['obligation_rate'], 1) }}%
                                    </span>
                                </td>
                                <td class="text-right align-middle financial-number text-success font-weight-bold border-left-success">
                                    ₱{{ number_format($data['disbursed'], 2) }}
                                </td>
                                <td class="text-center align-middle">
                                    <span class="badge {{ $disbClass }} shadow-none" style="width: 55px;">
                                        {{ number_format($data['disbursement_rate'], 1) }}%
                                    </span>
                                </td>
                                <td class="text-right align-middle financial-number pr-4 {{ $data['balance'] < 0 ? 'text-danger' : 'text-dark' }}">
                                    ₱{{ number_format($data['balance'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-navy-light shadow-sm">
                        @php
                            $overallObligRate = $totals['allotted'] > 0 ? ($totals['obligated'] / $totals['allotted']) * 100 : 0;
                            $overallDisbRate = $totals['obligated'] > 0 ? ($totals['disbursed'] / $totals['obligated']) * 100 : 0;
                        @endphp
                        <tr class="font-weight-bold">
                            <td class="pl-4 py-3 align-middle text-uppercase small">Grand Total</td>
                            <td class="text-right align-middle financial-number">
                                ₱{{ number_format($totals['allotted'], 2) }}
                            </td>
                            <td class="text-right text-primary align-middle financial-number border-left-info">
                                ₱{{ number_format($totals['obligated'], 2) }}
                            </td>
                            <td class="text-center align-middle bg-light">
                                <div class="progress progress-xxs mb-1 mx-auto" style="width: 60px; background: #dee2e6;">
                                    <div class="progress-bar bg-info" style="width: {{ min($overallObligRate, 100) }}%"></div>
                                </div>
                                <span class="text-xs text-navy">{{ number_format($overallObligRate, 1) }}%</span>
                            </td>
                            <td class="text-right text-success align-middle financial-number border-left-success">
                                ₱{{ number_format($totals['disbursed'], 2) }}
                            </td>
                            <td class="text-center align-middle">
                                <div class="progress progress-xxs mb-1 mx-auto" style="width: 60px; background: #dee2e6;">
                                    <div class="progress-bar bg-success" style="width: {{ min($overallDisbRate, 100) }}%"></div>
                                </div>
                                <span class="text-xs text-navy">{{ number_format($overallDisbRate, 1) }}%</span>
                            </td>
                            <td class="text-right align-middle financial-number pr-4">
                                ₱{{ number_format($totals['allotted'] - $totals['obligated'], 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection