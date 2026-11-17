@extends('layouts.adminlte')

@section('content')
@php
    $allSources = $groupedReport->flatten(1);
    $kpiGrossAllotted      = $allSources->sum('source_total');
    $kpiPooled             = $allSources->sum('total_pooled');
    $kpiAdjustedAllotment  = $allSources->sum('adjusted_allotment');
    $kpiNetObligated       = $allSources->sum('total_obligated');
    $kpiNorsa              = $allSources->sum('total_norsa');
    $kpiDisbursed          = $allSources->sum('total_disbursed');
    $kpiPending            = $allSources->sum('total_pending');
    $kpiSavings            = $allSources->sum('total_savings');
    $kpiUnpaid             = max(0, $kpiNetObligated - $kpiDisbursed);
    $kpiUnobligAllotment   = max(0, $kpiGrossAllotted - $kpiNetObligated);
    $kpiUnobligMinusPend   = max(0, $kpiAdjustedAllotment - ($kpiNetObligated + $kpiPending));
    $kpiObligRate          = $kpiAdjustedAllotment > 0 ? ($kpiNetObligated / $kpiAdjustedAllotment) * 100 : 0;
    $kpiDisbRate           = $kpiNetObligated > 0 ? ($kpiDisbursed / $kpiNetObligated) * 100 : 0;

    $activeSectionName = !empty(request('section_id')) && isset($sectionOptions[request('section_id')])
        ? $sectionOptions[request('section_id')]
        : null;

    $hasCustomDateRange = !empty(request('date_from')) || !empty(request('date_to'));
@endphp

<style>
    /* Clean Executive Financial Styling */
    .financial-number {
        font-family: 'Consolas', 'Courier New', Courier, monospace;
        font-weight: 600;
        letter-spacing: -0.25px;
        font-size: 0.8rem;
    }
    .card-navy.card-outline {
        border-top: 3px solid #001f3f;
    }
    .bg-navy-light {
        background-color: #eef3f8 !important;
        color: #001f3f;
    }

    /* Executive KPI Cards */
    .kpi-box {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 14px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        border-top: 3px solid #64748b;
        height: 100%;
    }
    .kpi-box.kpi-navy    { border-top-color: #001f3f; }
    .kpi-box.kpi-info    { border-top-color: #0284c7; }
    .kpi-box.kpi-success { border-top-color: #16a34a; }
    .kpi-box.kpi-warning { border-top-color: #d97706; }
    .kpi-box.kpi-indigo  { border-top-color: #4f46e5; }
    .kpi-box.kpi-primary { border-top-color: #0d9488; }

    .kpi-title {
        font-size: 0.67rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        margin-bottom: 4px;
    }
    .kpi-val {
        font-family: 'Consolas', 'Courier New', Courier, monospace;
        font-size: 1.08rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.25;
    }
    .kpi-meta {
        font-size: 0.68rem;
        color: #64748b;
        margin-top: 4px;
    }

    /* Subtle Group Separators (Clean, No Heavy Clutter) */
    .col-sep {
        border-left: 1px solid #cbd5e1 !important;
    }

    /* Full-Width Section Banner */
    .section-header-row {
        background-color: #e2e8f0 !important;
        color: #0f172a;
        font-weight: 700;
        border-top: 1px solid #cbd5e1;
        border-bottom: 1px solid #cbd5e1;
    }
    .section-header-sticky-text {
        position: sticky;
        left: 1.25rem;
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
    }

    /* Sticky Multi-Row Header & Left Column */
    .table-sticky thead tr.group-row th {
        position: sticky;
        top: 0;
        z-index: 12;
        font-size: 0.64rem;
        font-weight: 700;
        letter-spacing: 0.6px;
        padding: 5px 8px;
        border-bottom: 1px solid #cbd5e1;
    }
    .table-sticky thead tr.col-row th {
        position: sticky;
        top: 25px;
        z-index: 11;
        background: #f8fafc;
        color: #334155;
        box-shadow: inset 0 -2px 0 #94a3b8;
        font-size: 0.66rem;
        font-weight: 700;
        letter-spacing: 0.3px;
        vertical-align: middle;
        padding: 7px 8px;
        line-height: 1.2;
    }
    .th-subtext {
        display: block;
        font-size: 0.58rem;
        font-weight: 500;
        text-transform: none;
        letter-spacing: 0;
        color: #64748b;
        margin-top: 2px;
    }
    .sticky-col {
        position: sticky;
        left: 0;
        z-index: 9;
        background: #ffffff;
        box-shadow: 2px 0 5px rgba(15, 23, 42, 0.04);
    }
    .table-sticky thead .sticky-col {
        z-index: 14;
    }
    .bg-navy-light .sticky-col {
        background-color: #eef3f8 !important;
    }
    .bg-soft-light   { background-color: #f8fafc; }
    .bg-soft-indigo  { background-color: rgba(79, 70, 229, 0.04); }
    .bg-soft-teal    { background-color: rgba(13, 148, 136, 0.04); }

    /* Custom Checkbox Container Styling */
    .quarter-checkbox-group {
        background-color: #f8fafc;
        border: 1px solid #ced4da;
        border-radius: 4px;
        padding: 4px 12px;
        height: 31px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .quarter-checkbox-item {
        display: inline-flex;
        align-items: center;
        cursor: pointer;
        margin-bottom: 0;
        font-weight: 700;
        font-size: 0.8rem;
        color: #1e293b;
        user-select: none;
    }
    .quarter-checkbox-item input[type="checkbox"] {
        margin-right: 5px;
        width: 15px;
        height: 15px;
        cursor: pointer;
    }

    /* Plain-English Glossary Bar */
    .glossary-bar {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 8px 14px;
        font-size: 0.74rem;
        color: #475569;
    }

    /* Tooltip Custom styling */
    .tooltip-inner {
        white-space: pre-line !important;
        text-align: left !important;
        max-width: 320px !important;
    }

    @media print {
        .filter-section, .glossary-bar { display: none !important; }
    }
</style>

<div class="container-fluid py-3">
    {{-- Page Header --}}
    <div class="row mb-3">
        <div class="col-12 d-flex flex-wrap justify-content-between align-items-end">
            <div>
                <h4 class="text-navy mb-1 font-weight-bold">
                    <i class="fas fa-layer-group mr-2 text-primary"></i>By Source Budget Tracking
                </h4>
                <p class="text-muted small mb-0">
                    Financial Summary for Fiscal Year <strong>{{ request('year', date('Y')) }}</strong>
                    @if($activeSectionName)
                        | Unit/Section: <span class="badge badge-primary font-weight-bold px-2 py-1">{{ $activeSectionName }}</span>
                    @endif
                    @if(request('source_type'))
                        | Type: <span class="text-uppercase text-info font-weight-bold">{{ request('source_type') }}</span>
                    @endif
                    @if($hasCustomDateRange)
                        | Date Range:
                        <span class="badge badge-info font-weight-bold px-2 py-1">
                            {{ request('date_from') ? \Carbon\Carbon::parse(request('date_from'))->format('M d, Y') : 'Start of FY' }}
                            &ndash;
                            {{ request('date_to') ? \Carbon\Carbon::parse(request('date_to'))->format('M d, Y') : 'Present' }}
                        </span>
                    @elseif(request('month'))
                        | Month: <strong>{{ date('F', mktime(0, 0, 0, request('month'), 1)) }}</strong>
                    @elseif(!empty(request('quarters')))
                        | Quarters: <strong>{{ implode(', ', array_map(fn($q) => 'Q'.$q, (array)request('quarters'))) }}</strong>
                    @elseif(request('quarter'))
                        | Quarter: <strong>Q{{ request('quarter') }}</strong>
                    @endif
                </p>
            </div>
            <div class="btn-group shadow-sm mt-2 mt-md-0">
                <a href="{{ route('reports.by_source.export', request()->all()) }}" class="btn btn-sm btn-success font-weight-bold">
                    <i class="fas fa-file-excel mr-1"></i> Export Excel
                </a>
                <button class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#filterCard">
                    <i class="fas fa-filter mr-1"></i> Filters
                </button>
            </div>
        </div>
    </div>

    {{-- Executive KPI Summary Cards --}}
    <div class="row mb-3">
        <div class="col-xl-2 col-md-4 col-sm-6 mb-2">
            <div class="kpi-box kpi-navy">
                <div class="kpi-title">Total Fund Source</div>
                <div class="kpi-val">₱{{ number_format($kpiGrossAllotted, 2) }}</div>
                <div class="kpi-meta">
                    Usable Allotment: <strong>₱{{ number_format($kpiAdjustedAllotment, 2) }}</strong>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-2">
            <div class="kpi-box kpi-info">
                <div class="kpi-title">Net Obligated</div>
                <div class="kpi-val" style="color: #0284c7;">₱{{ number_format($kpiNetObligated, 2) }}</div>
                <div class="kpi-meta">
                    Utilization: <strong>{{ number_format($kpiObligRate, 1) }}%</strong>
                    @if($kpiNorsa > 0) · NORSA: <span class="text-danger">-₱{{ number_format($kpiNorsa, 2) }}</span> @endif
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-2">
            <div class="kpi-box kpi-success">
                <div class="kpi-title">Total Disbursed</div>
                <div class="kpi-val text-success">₱{{ number_format($kpiDisbursed, 2) }}</div>
                <div class="kpi-meta">
                    Paid Rate: <strong>{{ number_format($kpiDisbRate, 1) }}%</strong> · Unpaid: ₱{{ number_format($kpiUnpaid, 2) }}
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-2">
            <div class="kpi-box kpi-warning">
                <div class="kpi-title">Pending Transactions</div>
                <div class="kpi-val" style="color: #d97706;">₱{{ number_format($kpiPending, 2) }}</div>
                <div class="kpi-meta">Realized Savings: <strong class="text-teal">₱{{ number_format($kpiSavings, 2) }}</strong></div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-2">
            <div class="kpi-box kpi-indigo">
                <div class="kpi-title">Unobligated Allotment Bal.</div>
                <div class="kpi-val" style="color: #4f46e5;">₱{{ number_format($kpiUnobligAllotment, 2) }}</div>
                <div class="kpi-meta">Total Source minus Net Obligated</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-2">
            <div class="kpi-box kpi-primary">
                <div class="kpi-title">Unobligated (Minus Pending)</div>
                <div class="kpi-val" style="color: #0f766e;">₱{{ number_format($kpiUnobligMinusPend, 2) }}</div>
                <div class="kpi-meta">Free Balance After Pending</div>
            </div>
        </div>
    </div>

    {{-- Filter Card (Single-Row Layout) --}}
    <div class="collapse show filter-section" id="filterCard">
        <div class="card shadow-sm mb-3 border-navy">
            <div class="card-body py-2 px-3 bg-white">
                <form action="{{ route('reports.by_source') }}" method="GET" id="bySourceFilterForm">
                    <div class="d-flex flex-wrap flex-xl-nowrap align-items-end" style="gap: 8px;">
                        {{-- Section / Unit Dropdown --}}
                        @if(!empty($canFilterSection) && $canFilterSection)
                            <div class="flex-grow-1" style="min-width: 170px;">
                                <label class="text-xs font-weight-bold text-muted text-uppercase mb-1 d-block">Unit / Section</label>
                                <select name="section_id" class="form-control form-control-sm border-navy">
                                    <option value="">All Units / Sections</option>
                                    @foreach($sectionOptions ?? [] as $secId => $secName)
                                        <option value="{{ $secId }}" {{ (string)request('section_id') === (string)$secId ? 'selected' : '' }}>
                                            {{ $secName }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        {{-- Source Type --}}
                        <div style="min-width: 115px; flex: 0 1 130px;">
                            <label class="text-xs font-weight-bold text-muted text-uppercase mb-1 d-block">Source Type</label>
                            <select name="source_type" class="form-control form-control-sm border-navy text-uppercase">
                                <option value="">All Types</option>
                                @foreach($sourceTypes ?? ['saa', 'regular', 'continuing'] as $type)
                                    <option value="{{ $type }}" {{ request('source_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Cumulative Quarters Checkboxes --}}
                        <div style="min-width: 195px; flex: 0 0 auto;">
                            <label class="text-xs font-weight-bold text-muted text-uppercase mb-1 d-block">Cumulative Quarters</label>
                            @php
                                $selectedQuarters = (array) request('quarters', []);
                                if (empty($selectedQuarters) && request()->has('quarter') && request('quarter') !== '') {
                                    $selectedQuarters = [request('quarter')];
                                }
                            @endphp
                            <div class="quarter-checkbox-group px-2" style="gap: 8px;">
                                @foreach([1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4'] as $qVal => $qLabel)
                                    <label class="quarter-checkbox-item mb-0">
                                        <input type="checkbox" name="quarters[]" class="js-quarter-checkbox" value="{{ $qVal }}" {{ in_array($qVal, $selectedQuarters) ? 'checked' : '' }}>
                                        {{ $qLabel }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Specific Month --}}
                        <div style="min-width: 120px; flex: 0 1 135px;">
                            <label class="text-xs font-weight-bold text-muted text-uppercase mb-1 d-block">Specific Month</label>
                            <select name="month" id="filterMonth" class="form-control form-control-sm border-navy">
                                <option value="">All Months</option>
                                @foreach(range(1, 12) as $m)
                                    <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Fiscal Year --}}
                        <div style="min-width: 82px; flex: 0 0 86px;">
                            <label class="text-xs font-weight-bold text-muted text-uppercase mb-1 d-block">Fiscal Year</label>
                            <select name="year" id="filterYear" class="form-control form-control-sm border-navy">
                                @php $currentYear = date('Y'); @endphp
                                @foreach(range($currentYear, $currentYear - 4) as $yearOption)
                                    <option value="{{ $yearOption }}" {{ request('year', $currentYear) == $yearOption ? 'selected' : '' }}>{{ $yearOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Date Range (From & To) --}}
                        <div style="min-width: 132px; flex: 0 0 136px;">
                            <label class="text-xs font-weight-bold text-muted text-uppercase mb-1 d-block">Date From</label>
                            <input type="date" name="date_from" id="filterDateFrom"
                                   value="{{ request('date_from') }}"
                                   class="form-control form-control-sm border-navy px-2">
                        </div>

                        <div style="min-width: 132px; flex: 0 0 136px;">
                            <label class="text-xs font-weight-bold text-muted text-uppercase mb-1 d-block">Date To</label>
                            <input type="date" name="date_to" id="filterDateTo"
                                   value="{{ request('date_to') }}"
                                   class="form-control form-control-sm border-navy px-2">
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex align-items-center flex-shrink-0" style="gap: 4px;">
                            @if($hasCustomDateRange)
                                <button type="button" id="clearDateRangeBtn" class="btn btn-sm btn-outline-danger px-2 text-nowrap" title="Clear Date Range">
                                    <i class="fas fa-times"></i>
                                </button>
                            @endif
                            <button type="submit" class="btn btn-sm text-white px-3 shadow-sm font-weight-bold text-nowrap" style="background-color: #001f3f;">
                                <i class="fas fa-search mr-1"></i> Apply
                            </button>
                            <a href="{{ route('reports.by_source') }}" class="btn btn-sm btn-light border px-2 text-muted text-nowrap" title="Reset All Filters">
                                <i class="fas fa-undo"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Quick Guide for Easy Reading --}}
    <div class="glossary-bar mb-3 d-flex flex-wrap align-items-center justify-content-between">
        <div class="mb-1 mb-md-0">
            <strong class="text-navy mr-2"><i class="fas fa-book-open mr-1 text-primary"></i>Quick Guide:</strong>
            <span class="mr-3"><strong>Adjusted Allotment</strong> = Total Source minus Pooled funds</span>
            <span class="mr-3"><strong>Net Obligated</strong> = Obligated minus NORSA</span>
            <span class="mr-3"><strong>Unobligated Allotment Bal.</strong> = Total Source minus Net Obligated</span>
            <span><strong>Unobligated (Minus Pending)</strong> = Remaining free funds after deducting pending requests</span>
        </div>
        <span class="text-info small"><i class="fas fa-calendar-check mr-1"></i>Filtered by transaction date</span>
    </div>

    {{-- Main Financial Report Table --}}
    <div class="card card-navy card-outline shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 680px;">
                <table class="table table-sm table-hover table-sticky mb-0 border-0">
                    <thead>
                        {{-- Category Group Header Row --}}
                        <tr class="group-row text-uppercase text-center">
                            <th class="sticky-col pl-3 text-left" style="background-color: #e2e8f0; color: #0f172a;">Source</th>
                            <th colspan="4" style="background-color: #e0e7ff; color: #1e3a8a;">1. Allotment Allocation</th>
                            <th colspan="6" style="background-color: #dcfce7; color: #14532d;">2. Obligation &amp; Disbursement</th>
                            <th colspan="4" style="background-color: #fef3c7; color: #78350f;">3. Savings &amp; Remaining Balances</th>
                        </tr>
                        {{-- Column Header Row --}}
                        <tr class="col-row text-uppercase">
                            <th class="sticky-col pl-3" style="min-width: 195px;">Fund Source</th>
                            <th class="text-right">Total Fund Source</th>
                            <th class="text-center">Procurable</th>
                            <th class="text-center">Non-Procurable</th>
                            <th class="text-right">
                                Adjusted Allotment
                                <span class="th-subtext">Less Pooled</span>
                            </th>
                            <th class="text-right col-sep bg-soft-light">
                                Net Obligated
                                <span class="th-subtext">Gross − NORSA</span>
                            </th>
                            <th class="text-right text-danger">NORSA</th>
                            <th class="text-center bg-soft-light">Oblig. %</th>
                            <th class="text-right">Disbursed</th>
                            <th class="text-center">Disb. %</th>
                            <th class="text-right bg-soft-light">
                                Unpaid Oblig.
                                <span class="th-subtext">Oblig. − Disb.</span>
                            </th>
                            <th class="text-right col-sep text-teal">
                                Savings
                                <span class="th-subtext">Proc.</span>
                            </th>
                            <th class="text-right text-warning">
                                Pending
                                <span class="th-subtext">For Obligation</span>
                            </th>
                            <th class="text-right bg-soft-indigo" style="color: #4338ca; min-width: 145px;">
                                Unobligated Allotment Bal.
                                <span class="th-subtext">Source − Net Oblig.</span>
                            </th>
                            <th class="text-right pr-3 bg-soft-teal" style="color: #0f766e; min-width: 155px;">
                                Unobligated (Minus Pending)
                                <span class="th-subtext">Adj. − (Oblig. + Pending)</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groupedReport as $sectionName => $sources)
                            @php
                                $sectionTotals = [
                                    'gross_allotted' => 0, 'pooled' => 0, 'adjusted_allotment' => 0,
                                    'gross_obligated' => 0, 'norsa' => 0, 'obligated' => 0,
                                    'disbursed' => 0, 'pending' => 0, 'savings' => 0,
                                    'procurable' => 0, 'non_procurable' => 0,
                                    'unobligated_allotment' => 0, 'unobligated_minus_pending' => 0
                                ];
                            @endphp

                            {{-- Full-Width Section / Unit Banner --}}
                            <tr class="section-header-row">
                                <td colspan="15" class="pl-3 py-2">
                                    <div class="section-header-sticky-text">
                                        <i class="fas fa-building mr-2 text-primary"></i>
                                        <span class="text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.3px;">{{ $sectionName }}</span>
                                        <span class="badge badge-light border text-muted ml-2 font-weight-normal" style="font-size: 0.68rem;">
                                            {{ $sources->count() }} {{ \Illuminate\Support\Str::plural('fund source', $sources->count()) }}
                                        </span>
                                    </div>
                                </td>
                            </tr>

                            @foreach($sources as $data)
                                @php
                                    $currentGross          = $data['source_total'];
                                    $currentPooled         = $data['total_pooled'] ?? 0;
                                    $currentAdjusted       = $data['adjusted_allotment'];
                                    $currentGrossOblig     = $data['gross_obligated'] ?? $data['total_obligated'];
                                    $currentNorsa          = $data['total_norsa'] ?? 0;
                                    $currentObligated      = $data['total_obligated'];
                                    $currentDisbursed      = $data['total_disbursed'];
                                    $currentPending        = $data['total_pending'];
                                    $currentSavings        = $data['total_savings'] ?? 0;
                                    $budgetP               = $data['procurable_budget_total'];
                                    $budgetNP              = $data['non_procurable_budget_total'];

                                    $percP  = $currentAdjusted > 0 ? ($budgetP / $currentAdjusted) * 100 : 0;
                                    $percNP = $currentAdjusted > 0 ? ($budgetNP / $currentAdjusted) * 100 : 0;

                                    $unpaid                = max(0, $currentObligated - $currentDisbursed);
                                    $unobligAllotmentBal   = $data['unobligated_allotment_balance'] ?? max(0, $currentGross - $currentObligated);
                                    $unobligMinusPending   = $data['total_unobligated'] ?? max(0, $currentAdjusted - ($currentObligated + $currentPending));

                                    // Update Section Totals
                                    $sectionTotals['gross_allotted']            += $currentGross;
                                    $sectionTotals['pooled']                    += $currentPooled;
                                    $sectionTotals['adjusted_allotment']        += $currentAdjusted;
                                    $sectionTotals['gross_obligated']           += $currentGrossOblig;
                                    $sectionTotals['norsa']                     += $currentNorsa;
                                    $sectionTotals['obligated']                 += $currentObligated;
                                    $sectionTotals['disbursed']                 += $currentDisbursed;
                                    $sectionTotals['pending']                   += $currentPending;
                                    $sectionTotals['savings']                   += $currentSavings;
                                    $sectionTotals['procurable']                += $budgetP;
                                    $sectionTotals['non_procurable']            += $budgetNP;
                                    $sectionTotals['unobligated_allotment']     += $unobligAllotmentBal;
                                    $sectionTotals['unobligated_minus_pending'] += $unobligMinusPending;

                                    $obligClass = $data['overall_oblig_rate'] >= 90 ? 'badge-success' : ($data['overall_oblig_rate'] >= 50 ? 'badge-info' : 'badge-warning');
                                    $disbClass  = $data['overall_disb_rate'] >= 90 ? 'badge-success' : ($data['overall_disb_rate'] >= 50 ? 'badge-info' : 'badge-light border');
                                @endphp
                                <tr>
                                    {{-- Source Name --}}
                                    <td class="sticky-col pl-3 align-middle">
                                        <span class="text-navy font-weight-bold text-uppercase small d-block">{{ $data['source_name'] }}</span>
                                    </td>

                                    {{-- Total Fund Source (Gross) --}}
                                    <td class="text-right align-middle financial-number text-dark">
                                        ₱{{ number_format($currentGross, 2) }}
                                    </td>

                                    {{-- Procurable % & Amount --}}
                                    <td class="text-center align-middle">
                                        <span class="text-xs font-weight-bold text-info">{{ number_format($percP, 1) }}%</span>
                                        <small class="d-block text-muted financial-number" style="font-size: 0.64rem;">₱{{ number_format($budgetP, 2) }}</small>
                                    </td>

                                    {{-- Non-Procurable % & Amount --}}
                                    <td class="text-center align-middle">
                                        <span class="text-xs font-weight-bold text-secondary">{{ number_format($percNP, 1) }}%</span>
                                        <small class="d-block text-muted financial-number" style="font-size: 0.64rem;">₱{{ number_format($budgetNP, 2) }}</small>
                                    </td>

                                    {{-- Adjusted Allotment --}}
                                    <td class="text-right align-middle financial-number">
                                        <div class="font-weight-bold">₱{{ number_format($currentAdjusted, 2) }}</div>
                                        @if($data['has_pooled'])
                                            <div class="mt-0.5">
                                                <span class="badge badge-warning text-dark font-weight-bold px-1 py-0.5"
                                                      style="font-size: 0.6rem; cursor: help;"
                                                      data-toggle="tooltip"
                                                      data-placement="left"
                                                      title="Pooling Reason(s):&#10;{{ $data['pooled_reasons'] }}">
                                                    <i class="fas fa-layer-group mr-1"></i>Less Pooled: ₱{{ number_format($currentPooled, 2) }}
                                                </span>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Net Obligated --}}
                                    <td class="text-right align-middle financial-number font-weight-bold col-sep bg-soft-light" style="color: #0284c7;">
                                        <div>₱{{ number_format($currentObligated, 2) }}</div>
                                        @if($currentNorsa > 0)
                                            <small class="d-block text-muted font-weight-normal" style="font-size: 0.6rem;">
                                                (Original: ₱{{ number_format($currentGrossOblig, 2) }})
                                            </small>
                                        @endif
                                    </td>

                                    {{-- NORSA --}}
                                    <td class="text-right align-middle financial-number text-danger">
                                        @if($currentNorsa > 0)
                                            (₱{{ number_format($currentNorsa, 2) }})
                                        @else
                                            <span class="text-muted font-weight-normal">-</span>
                                        @endif
                                    </td>

                                    {{-- Obligated % --}}
                                    <td class="text-center align-middle bg-soft-light">
                                        <span class="badge {{ $obligClass }}">{{ number_format($data['overall_oblig_rate'], 1) }}%</span>
                                    </td>

                                    {{-- Disbursed --}}
                                    <td class="text-right align-middle financial-number text-success font-weight-bold">
                                        ₱{{ number_format($currentDisbursed, 2) }}
                                    </td>

                                    {{-- Disbursed % --}}
                                    <td class="text-center align-middle">
                                        <span class="badge {{ $disbClass }}">{{ number_format($data['overall_disb_rate'], 1) }}%</span>
                                    </td>

                                    {{-- Unpaid Obligations --}}
                                    <td class="text-right align-middle financial-number bg-soft-light text-dark">
                                        ₱{{ number_format($unpaid, 2) }}
                                    </td>

                                    {{-- Savings --}}
                                    <td class="text-right align-middle col-sep financial-number {{ $currentSavings > 0 ? 'text-teal font-weight-bold' : 'text-muted' }}">
                                        @if($currentSavings > 0)
                                            ₱{{ number_format($currentSavings, 2) }}
                                        @else
                                            <span class="text-muted font-weight-normal">-</span>
                                        @endif
                                    </td>

                                    {{-- Pending Transactions --}}
                                    <td class="text-right align-middle financial-number" style="color: #d97706;">
                                        ₱{{ number_format($currentPending, 2) }}
                                    </td>

                                    {{-- Unobligated Allotment Balance (Total Fund Source - Net Obligated) --}}
                                    <td class="text-right align-middle financial-number bg-soft-indigo font-weight-bold" style="color: #4338ca;">
                                        ₱{{ number_format($unobligAllotmentBal, 2) }}
                                    </td>

                                    {{-- Unobligated (Minus Pending Transactions) --}}
                                    <td class="text-right align-middle financial-number pr-3 bg-soft-teal font-weight-bold {{ $unobligMinusPending < 0 ? 'text-danger' : '' }}" style="{{ $unobligMinusPending >= 0 ? 'color: #0f766e;' : '' }}">
                                        ₱{{ number_format($unobligMinusPending, 2) }}
                                    </td>
                                </tr>
                            @endforeach

                            {{-- Section Subtotal Row --}}
                            @php
                                $secObligRate = $sectionTotals['adjusted_allotment'] > 0 ? ($sectionTotals['obligated'] / $sectionTotals['adjusted_allotment']) * 100 : 0;
                                $secDisbRate  = $sectionTotals['obligated'] > 0 ? ($sectionTotals['disbursed'] / $sectionTotals['obligated']) * 100 : 0;
                            @endphp
                            <tr class="bg-navy-light shadow-sm font-weight-bold" style="border-bottom: 2px solid #94a3b8;">
                                <td class="sticky-col pl-3 py-2 align-middle text-uppercase small"><b>Total: {{ $sectionName }}</b></td>
                                <td class="text-right align-middle financial-number">₱{{ number_format($sectionTotals['gross_allotted'], 2) }}</td>
                                <td class="text-center align-middle text-xs">{{ number_format($sectionTotals['adjusted_allotment'] > 0 ? ($sectionTotals['procurable'] / $sectionTotals['adjusted_allotment']) * 100 : 0, 1) }}%</td>
                                <td class="text-center align-middle text-xs">{{ number_format($sectionTotals['adjusted_allotment'] > 0 ? ($sectionTotals['non_procurable'] / $sectionTotals['adjusted_allotment']) * 100 : 0, 1) }}%</td>
                                <td class="text-right align-middle financial-number">
                                    ₱{{ number_format($sectionTotals['adjusted_allotment'], 2) }}
                                    @if($sectionTotals['pooled'] > 0)
                                        <small class="d-block text-danger font-weight-normal" style="font-size: 0.64rem;">(Pooled: ₱{{ number_format($sectionTotals['pooled'], 2) }})</small>
                                    @endif
                                </td>
                                <td class="text-right align-middle financial-number col-sep" style="color: #0284c7;">₱{{ number_format($sectionTotals['obligated'], 2) }}</td>
                                <td class="text-right align-middle financial-number text-danger">
                                    @if($sectionTotals['norsa'] > 0)
                                        (₱{{ number_format($sectionTotals['norsa'], 2) }})
                                    @else
                                        <span class="text-muted font-weight-normal">-</span>
                                    @endif
                                </td>
                                <td class="text-center align-middle"><span class="badge badge-info">{{ number_format($secObligRate, 1) }}%</span></td>
                                <td class="text-right align-middle financial-number text-success">₱{{ number_format($sectionTotals['disbursed'], 2) }}</td>
                                <td class="text-center align-middle"><span class="badge badge-light border">{{ number_format($secDisbRate, 1) }}%</span></td>
                                <td class="text-right align-middle financial-number">₱{{ number_format(max(0, $sectionTotals['obligated'] - $sectionTotals['disbursed']), 2) }}</td>
                                <td class="text-right align-middle financial-number col-sep text-teal">₱{{ number_format($sectionTotals['savings'], 2) }}</td>
                                <td class="text-right align-middle financial-number" style="color: #d97706;">₱{{ number_format($sectionTotals['pending'], 2) }}</td>
                                <td class="text-right align-middle financial-number" style="color: #4338ca;">₱{{ number_format($sectionTotals['unobligated_allotment'], 2) }}</td>
                                <td class="text-right align-middle financial-number pr-3" style="color: #0f766e;">₱{{ number_format($sectionTotals['unobligated_minus_pending'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-2x mb-2 d-block"></i>
                                    No records found matching the active filter configuration.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip({
            html: true
        });

        const $dateFrom = $('#filterDateFrom');
        const $dateTo   = $('#filterDateTo');
        const $month    = $('#filterMonth');
        const $quarters = $('.js-quarter-checkbox');

        // Keep Date To >= Date From and clear Month/Quarter overrides when using a custom date range
        $dateFrom.on('change', function() {
            const fromVal = $(this).val();
            if (fromVal) {
                $dateTo.attr('min', fromVal);
                if ($dateTo.val() && $dateTo.val() < fromVal) {
                    $dateTo.val(fromVal);
                }
                $month.val('');
                $quarters.prop('checked', false);
            } else {
                $dateTo.removeAttr('min');
            }
        });

        $dateTo.on('change', function() {
            const toVal = $(this).val();
            if (toVal) {
                $dateFrom.attr('max', toVal);
                if ($dateFrom.val() && $dateFrom.val() > toVal) {
                    $dateFrom.val(toVal);
                }
                $month.val('');
                $quarters.prop('checked', false);
            } else {
                $dateFrom.removeAttr('max');
            }
        });

        // Clear custom date range if user picks a specific Month or Quarter
        $month.on('change', function() {
            if ($(this).val()) {
                $dateFrom.val('').removeAttr('max');
                $dateTo.val('').removeAttr('min');
                $quarters.prop('checked', false);
            }
        });

        $quarters.on('change', function() {
            if ($quarters.is(':checked')) {
                $dateFrom.val('').removeAttr('max');
                $dateTo.val('').removeAttr('min');
                $month.val('');
            }
        });

        $('#clearDateRangeBtn').on('click', function() {
            $dateFrom.val('').removeAttr('max');
            $dateTo.val('').removeAttr('min');
            $('#bySourceFilterForm').submit();
        });
    });
</script>
@endsection