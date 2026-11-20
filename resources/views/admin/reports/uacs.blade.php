@extends('layouts.adminlte')

@section('content')
@php
    $hasCustomDateRange = !empty(request('date_from')) || !empty(request('date_to'));
@endphp

<style>
    .financial-number { font-family: 'Consolas', 'Courier New', Courier, monospace; font-weight: 600; letter-spacing: -0.3px; }
    .card-navy.card-outline { border-top: 3px solid #001f3f; }
    .bg-navy-light { background-color: #f1f4f8; color: #001f3f; }
    .bg-soft-light { background-color: #f8f9fa; }
    .row-cancelled { background-color: #fff5f5 !important; }
    .row-cancelled td { color: #6c757d; }
    .strike-cancelled { text-decoration: line-through; opacity: 0.75; }

    .section-banner {
        background: linear-gradient(135deg, #001f3f 0%, #003366 100%);
        color: #ffffff;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    }
    @media print { .filter-section, .btn-group { display: none !important; } }
</style>

<div class="container-fluid py-3">
    {{-- Top Header Strip --}}
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <h4 class="text-navy mb-1 font-weight-bold">
                <i class="fas fa-barcode mr-2 text-primary"></i>Financial Report by UACS (Per Section &amp; Fund Source)
            </h4>
            <p class="text-muted small mb-0">
                UACS Utilization Breakdown for Fiscal Year <strong>{{ request('year', $year ?? date('Y')) }}</strong>
                @if(request('section')) | Section: <strong>{{ request('section') }}</strong> @endif
                @if(request('fund_source')) | Fund Source: <strong>{{ request('fund_source') }}</strong> @endif
                @if($hasCustomDateRange)
                    | Date Range:
                    <span class="badge badge-info font-weight-bold px-2 py-1">
                        {{ request('date_from') ? \Carbon\Carbon::parse(request('date_from'))->format('M d, Y') : 'Start of FY' }}
                        &ndash;
                        {{ request('date_to') ? \Carbon\Carbon::parse(request('date_to'))->format('M d, Y') : 'Present' }}
                    </span>
                @endif
            </p>
        </div>

        <div class="btn-group shadow-sm">
            <button class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#filterCard">
                <i class="fas fa-filter mr-1"></i> Filter Options
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="expandAllBtn">
                <i class="fas fa-expand-arrows-alt mr-1"></i> Expand All
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="fas fa-print mr-1"></i> Print
            </button>
        </div>
    </div>
    <p class="text-muted small mb-4">
        <i class="fas fa-info-circle text-info mr-1"></i> Grouped by Section/Unit and Fund Source so you can easily identify UACS allocations, obligations, NORSA adjustments, disbursements, and unpaid obligations.
    </p>

    {{-- FILTER & SEARCH TOOLBAR (Single-Row Layout) --}}
    <div class="card card-default filter-section collapse show shadow-sm mb-4" id="filterCard">
        <div class="card-body py-2 px-3">
            <form method="GET" action="{{ url()->current() }}" id="filterForm">
                <div class="d-flex flex-wrap flex-xl-nowrap align-items-end" style="gap: 8px;">
                    {{-- Fiscal Year --}}
                    <div style="min-width: 92px; flex: 0 0 96px;">
                        <label class="text-xs font-weight-bold text-muted text-uppercase mb-1 d-block">Fiscal Year</label>
                        <select name="year" id="uacsFilterYear" class="form-control form-control-sm" onchange="this.form.submit()">
                            @foreach(range(date('Y') + 1, 2022) as $y)
                                <option value="{{ $y }}" {{ ($year ?? date('Y')) == $y ? 'selected' : '' }}>FY {{ $y }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Section --}}
                    <div class="flex-grow-1" style="min-width: 165px;">
                        <label class="text-xs font-weight-bold text-muted text-uppercase mb-1 d-block">Section / Unit</label>
                        <select name="section" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="">-- All Sections --</option>
                            @foreach($groupedReport as $sec)
                                <option value="{{ $sec['section_name'] }}" {{ request('section') == $sec['section_name'] ? 'selected' : '' }}>
                                    {{ $sec['section_name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Fund Source --}}
                    <div class="flex-grow-1" style="min-width: 165px;">
                        <label class="text-xs font-weight-bold text-muted text-uppercase mb-1 d-block">Fund Source</label>
                        <select name="fund_source" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="">-- All Fund Sources --</option>
                            @foreach($allFundSources as $srcName)
                                <option value="{{ $srcName }}" {{ request('fund_source') == $srcName ? 'selected' : '' }}>
                                    {{ $srcName }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Allotment Class --}}
                    <div style="min-width: 115px; flex: 0 1 130px;">
                        <label class="text-xs font-weight-bold text-muted text-uppercase mb-1 d-block">Allotment Class</label>
                        <select name="allotment_class" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="">-- All Classes --</option>
                            @foreach($allotmentClasses as $class)
                                <option value="{{ $class }}" {{ ($allotmentClass ?? '') == $class ? 'selected' : '' }}>
                                    {{ $class }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Date Range (From & To) --}}
                    <div style="min-width: 130px; flex: 0 0 134px;">
                        <label class="text-xs font-weight-bold text-muted text-uppercase mb-1 d-block">Date From</label>
                        <input type="date" name="date_from" id="uacsDateFrom"
                               value="{{ request('date_from') }}"
                               class="form-control form-control-sm px-2">
                    </div>

                    <div style="min-width: 130px; flex: 0 0 134px;">
                        <label class="text-xs font-weight-bold text-muted text-uppercase mb-1 d-block">Date To</label>
                        <input type="date" name="date_to" id="uacsDateTo"
                               value="{{ request('date_to') }}"
                               class="form-control form-control-sm px-2">
                    </div>

                    {{-- Search Keywords & Actions --}}
                    <div style="min-width: 195px; flex: 0 1 225px;">
                        <label class="text-xs font-weight-bold text-muted text-uppercase mb-1 d-block">Search UACS</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="search" class="form-control" placeholder="Code or Title..." value="{{ $search ?? '' }}">
                            <div class="input-group-append">
                                @if($hasCustomDateRange)
                                    <button type="button" id="uacsClearDatesBtn" class="btn btn-outline-danger" title="Clear Date Range">
                                        <i class="fas fa-times"></i>
                                    </button>
                                @endif
                                <button type="submit" class="btn btn-primary" title="Apply Filters"><i class="fas fa-search"></i></button>
                                <a href="{{ url()->current() }}" class="btn btn-secondary" title="Reset All"><i class="fas fa-undo"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- SECTION -> FUND SOURCE -> UACS MASTER LAYOUT --}}
    @forelse($groupedReport as $secIdx => $section)
        @if(request('section') && request('section') !== $section['section_name'])
            @continue
        @endif

        <div class="mb-5">
            {{-- Navy Section Banner --}}
            <div class="section-banner py-2 px-3 mb-2 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <i class="fas fa-university mr-2 text-warning"></i>
                    <h5 class="font-weight-bold mb-0 text-uppercase" style="font-size: 1rem;">{{ $section['section_name'] }}</h5>
                </div>
                <span class="badge badge-light text-navy font-weight-bold px-2 py-1">{{ $section['sources']->count() }} Fund Sources</span>
            </div>

            @foreach($section['sources'] as $srcIdx => $source)
                @php
                    $srcCafCount = $source['caf_tagged_count'] ?? 0;
                    $srcPoCount  = $source['po_tagged_count'] ?? 0;
                @endphp
                <div class="card card-navy card-outline mb-4 shadow-sm">
                    <div class="card-header bg-white py-2 px-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="card-title text-navy font-weight-bold mb-0">
                                <i class="fas fa-folder text-warning mr-2"></i>{{ $source['source_name'] }}
                                {{-- @if($srcCafCount > 0 || $srcPoCount > 0)
                                    <span class="ml-2">
                                        @if($srcCafCount > 0)
                                            <span class="badge badge-warning text-dark font-weight-bold" style="font-size: 0.68rem;">CAF: {{ $srcCafCount }}</span>
                                        @endif
                                        @if($srcPoCount > 0)
                                            <span class="badge badge-primary font-weight-bold" style="font-size: 0.68rem;">PO: {{ $srcPoCount }}</span>
                                        @endif
                                    </span>
                                @endif --}}
                            </h6>
                            <span class="text-dark font-weight-bold financial-number">
                                Total Allotted: ₱{{ number_format($source['source_total'] ?? 0, 2) }}
                            </span>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0 text-xs">
                                <thead class="bg-navy-light text-uppercase">
                                    <tr>
                                        <th style="width: 10%;">UACS Code</th>
                                        <th style="width: 21%;">Account Title</th>
                                        <th class="text-center" style="width: 5%;">Class</th>
                                        <th class="text-right" style="width: 8.5%;">Allotted</th>
                                        <th class="text-right" style="width: 8%;">Pending</th>
                                        <th class="text-right" style="width: 8.5%;">Gross Oblig.</th>
                                        <th class="text-right" style="width: 7.5%;">NORSA</th>
                                        <th class="text-right" style="width: 8.5%;">Net Oblig.</th>
                                        <th class="text-right" style="width: 8.5%;">Disbursed</th>
                                        <th class="text-right" style="width: 8.5%;">Unpaid Oblig.</th>
                                        <th class="text-right" style="width: 8.5%;">Unobligated</th>
                                        <th class="text-center" style="width: 6.5%;">Txns</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($source['uacs_items'] as $uIdx => $uacs)
                                        @php
                                            $collapseId   = "uacs-trx-{$secIdx}-{$srcIdx}-{$uIdx}";
                                            $uacsUnpaid   = $uacs['unpaid'] ?? max(0, round(($uacs['net_obligated'] ?? 0) - ($uacs['disbursed'] ?? 0), 2));
                                            $uacsCafCount = $uacs['caf_tagged_count'] ?? 0;
                                            $uacsPoCount  = $uacs['po_tagged_count'] ?? 0;
                                        @endphp
                                        <tr>
                                            <td class="align-middle font-weight-bold text-navy financial-number text-nowrap">
                                                {{ $uacs['uacs_code'] }}
                                            </td>
                                            <td class="align-middle font-weight-bold text-dark">
                                                {{ $uacs['account_title'] }}
                                                {{-- @if($uacsCafCount > 0 || $uacsPoCount > 0)
                                                    <div class="mt-1">
                                                        @if($uacsCafCount > 0)
                                                            <span class="badge badge-warning text-dark mr-1" style="font-size: 0.62rem;">
                                                                <i class="fas fa-tag mr-1"></i>CAF ({{ $uacsCafCount }})
                                                            </span>
                                                        @endif
                                                        @if($uacsPoCount > 0)
                                                            <span class="badge badge-primary" style="font-size: 0.62rem;">
                                                                <i class="fas fa-file-contract mr-1"></i>PO ({{ $uacsPoCount }})
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif --}}
                                            </td>
                                            <td class="text-center align-middle">
                                                <span class="badge badge-light border">{{ $uacs['allotment_class'] ?? '—' }}</span>
                                            </td>
                                            <td class="text-right align-middle financial-number">₱{{ number_format($uacs['allotment'], 2) }}</td>
                                            <td class="text-right align-middle financial-number text-warning">₱{{ number_format($uacs['pending'], 2) }}</td>
                                            <td class="text-right align-middle financial-number text-info">₱{{ number_format($uacs['gross_obligated'], 2) }}</td>
                                            <td class="text-right align-middle financial-number text-danger">
                                                {{ $uacs['norsa_amount'] > 0 ? '(₱' . number_format($uacs['norsa_amount'], 2) . ')' : '-' }}
                                            </td>
                                            <td class="text-right align-middle financial-number text-primary">₱{{ number_format($uacs['net_obligated'], 2) }}</td>
                                            <td class="text-right align-middle financial-number text-success">₱{{ number_format($uacs['disbursed'], 2) }}</td>
                                            <td class="text-right align-middle financial-number {{ $uacsUnpaid > 0 ? 'text-orange font-weight-bold' : 'text-muted' }}" style="{{ $uacsUnpaid > 0 ? 'color: #d97706;' : '' }}">
                                                ₱{{ number_format($uacsUnpaid, 2) }}
                                            </td>
                                            <td class="text-right align-middle financial-number text-dark">₱{{ number_format($uacs['unobligated'], 2) }}</td>
                                            <td class="text-center align-middle">
                                                <button class="btn btn-xs btn-outline-info font-weight-bold px-2" data-toggle="collapse" data-target="#{{ $collapseId }}">
                                                    <i class="fas fa-list mr-1"></i> ({{ $uacs['transactions']->count() }})
                                                </button>
                                            </td>
                                        </tr>

                                        {{-- Inline Collapsible Transaction Breakdown --}}
                                        <tr class="collapse bg-soft-light" id="{{ $collapseId }}">
                                            <td colspan="12" class="p-3">
                                                <div class="border rounded bg-white p-2">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="font-weight-bold text-navy">
                                                            <i class="fas fa-layer-group mr-1 text-primary"></i>
                                                            Activities / Line Items under {{ $uacs['uacs_code'] }} — {{ $uacs['account_title'] }}
                                                        </span>
                                                        <span class="badge badge-info">
                                                            {{ $uacs['activities']->count() }} {{ \Illuminate\Support\Str::plural('Activity', $uacs['activities']->count()) }}
                                                        </span>
                                                    </div>

                                                    @forelse($uacs['activities'] as $actItem)
                                                        @php
                                                            $actUnpaid   = $actItem['unpaid'] ?? max(0, round(($actItem['net_obligated'] ?? 0) - ($actItem['disbursed'] ?? 0), 2));
                                                            $actCafCount = $actItem['caf_tagged_count'] ?? 0;
                                                            $actPoCount  = $actItem['po_tagged_count'] ?? 0;
                                                        @endphp
                                                        <div class="border rounded mb-3 overflow-hidden">
                                                            {{-- Activity Header Strip with Per-Activity Totals --}}
                                                            <div class="bg-navy-light px-3 py-2 border-bottom d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px;">
                                                                <div class="font-weight-bold text-dark" style="font-size: 0.78rem; max-width: 48%;">
                                                                    <i class="fas fa-tag text-primary mr-1"></i> {{ $actItem['activity_name'] }}
                                                                    {{-- @if($actCafCount > 0)
                                                                        <span class="badge badge-warning text-dark ml-1" style="font-size: 0.62rem;">CAF ({{ $actCafCount }})</span>
                                                                    @endif
                                                                    @if($actPoCount > 0)
                                                                        <span class="badge badge-primary ml-1" style="font-size: 0.62rem;">PO ({{ $actPoCount }})</span>
                                                                    @endif --}}
                                                                </div>
                                                                <div class="d-flex flex-wrap align-items-center text-xs" style="gap: 12px;">
                                                                    <span>Allotted: <strong class="financial-number text-dark">₱{{ number_format($actItem['allotment'], 2) }}</strong></span>
                                                                    <span>Pending: <strong class="financial-number text-warning">₱{{ number_format($actItem['pending'], 2) }}</strong></span>
                                                                    <span>Net Oblig.: <strong class="financial-number text-primary">₱{{ number_format($actItem['net_obligated'], 2) }}</strong></span>
                                                                    @if($actItem['norsa_amount'] > 0)
                                                                        <span>NORSA: <strong class="financial-number text-danger">(₱{{ number_format($actItem['norsa_amount'], 2) }})</strong></span>
                                                                    @endif
                                                                    <span>Disbursed: <strong class="financial-number text-success">₱{{ number_format($actItem['disbursed'], 2) }}</strong></span>
                                                                    <span>Unpaid: <strong class="financial-number" style="color: #d97706;">₱{{ number_format($actUnpaid, 2) }}</strong></span>
                                                                    <span>Unobligated: <strong class="financial-number text-dark">₱{{ number_format($actItem['unobligated'], 2) }}</strong></span>
                                                                    <span class="badge badge-secondary">{{ $actItem['transactions']->count() }} txns</span>
                                                                </div>
                                                            </div>

                                                            {{-- Transactions Table for this Activity --}}
                                                            <table class="table table-sm table-bordered mb-0">
                                                                <thead class="bg-light text-uppercase" style="font-size: 0.65rem;">
                                                                    <tr>
                                                                        <th style="width: 13%;">DTrack / OBRN</th>
                                                                        <th style="width: 18%;">Payee / Creditor</th>
                                                                        <th style="width: 27%;">Particulars</th>
                                                                        <th class="text-right" style="width: 8.5%;">Routed / Amt</th>
                                                                        <th class="text-right" style="width: 8.5%;">Net Oblig.</th>
                                                                        <th class="text-right text-danger" style="width: 7%;">NORSA</th>
                                                                        <th class="text-right" style="width: 8.5%;">Disbursed</th>
                                                                        <th class="text-center" style="width: 9.5%;">Status &amp; Remarks</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="text-xs">
                                                                    @forelse($actItem['transactions'] as $tx)
                                                                        @php
                                                                            $txNorsaAmt  = (float) ($tx->norsa_amount ?? 0);
                                                                            $storedOb    = (float) ($tx->obligation_amount ?? 0);
                                                                            $baseGrossOb = (float) ($tx->prior_gross_obligated ?? (!empty($tx->is_multi_activity) ? ($storedOb + $txNorsaAmt) : $storedOb));

                                                                            $isFullNorsaCancel = !empty($tx->is_norsa_cancelled) || ($baseGrossOb > 0 && abs($baseGrossOb - $txNorsaAmt) < 0.01);
                                                                            $isTxCancelled     = !empty($tx->is_cancelled)
                                                                                || strtolower((string) ($tx->status ?? '')) === 'cancelled'
                                                                                || $isFullNorsaCancel;

                                                                            $netObligAmt = $isTxCancelled ? 0.00 : max(0.00, $baseGrossOb - $txNorsaAmt);

                                                                            $isCosSalary = !empty($tx->is_cos_salary)
                                                                                || (isset($tx->remarks_salary) && $tx->remarks_salary === 'Imported HR COS Salary/Wages')
                                                                                || !empty($tx->cos_contract_id)
                                                                                || \Illuminate\Support\Str::contains(strtolower((string) ($tx->remarks ?? '')), ['cos', 'salary', 'wages', 'payroll']);

                                                                            $cosDisbursements  = $tx->cosSalaryDisbursements ?? collect();
                                                                            $isCosConsolidated = !empty($tx->is_cos_consolidated) || $cosDisbursements->contains(function ($d) {
                                                                                return ((int) ($d->months_covered ?? $d->months_count ?? 1) > 1)
                                                                                    || !empty($d->is_consolidated)
                                                                                    || \Illuminate\Support\Str::contains(strtolower((string) ($d->remarks ?? $d->notes ?? $d->period_label ?? '')), 'consolidated');
                                                                            });

                                                                            // Procurable Status (Tagged as CAF vs. Tagged as PO)
                                                                            $procurementTag   = $tx->procurement_tag ?? null;
                                                                            $procurementBadge = $tx->procurement_badge ?? null;
                                                                            if (!$isTxCancelled && !$isCosSalary && empty($procurementBadge)) {
                                                                                $stLower = strtolower((string) ($tx->status ?? ''));
                                                                                $rmLower = strtolower((string) ($tx->remarks ?? ''));
                                                                                if (!empty($tx->dtrack_no_new) || \Illuminate\Support\Str::contains($stLower, 'po') || \Illuminate\Support\Str::contains($rmLower, 'tagged as po')) {
                                                                                    $procurementTag   = 'PO';
                                                                                    $procurementBadge = 'Tagged as PO';
                                                                                } elseif (\Illuminate\Support\Str::contains($stLower, 'caf') || \Illuminate\Support\Str::contains($rmLower, 'caf') || ($actCafCount > 0 && empty($tx->obligation_date))) {
                                                                                    $procurementTag   = 'CAF';
                                                                                    $procurementBadge = $actCafCount > 1 ? "Tagged as CAF ({$actCafCount})" : 'Tagged as CAF';
                                                                                } elseif ($actPoCount > 0 && empty($tx->obligation_date)) {
                                                                                    $procurementTag   = 'PO';
                                                                                    $procurementBadge = $actPoCount > 1 ? "Tagged as PO ({$actPoCount})" : 'Tagged as PO';
                                                                                }
                                                                            }

                                                                            $displayStatus = $isTxCancelled ? 'Cancelled' : ($tx->status ?? 'N/A');
                                                                            if (!$isTxCancelled && !$isCosSalary && !empty($procurementBadge) && in_array(strtolower(trim($displayStatus)), ['', 'routed', 'pending', 'for obligation', 'tagged as caf', 'tagged as po'])) {
                                                                                $displayStatus = $procurementBadge;
                                                                            }

                                                                            $isStatusCaf = \Illuminate\Support\Str::contains(strtolower($displayStatus), 'caf');
                                                                            $isStatusPo  = \Illuminate\Support\Str::contains(strtolower($displayStatus), 'tagged as po') || \Illuminate\Support\Str::startsWith(strtolower($displayStatus), 'po (');
                                                                        @endphp
                                                                        <tr class="{{ $isTxCancelled ? 'row-cancelled' : '' }}">
                                                                            <td class="text-nowrap">
                                                                                @if($tx->dtrack_no)
                                                                                    <div><span class="badge badge-light text-danger border">{{ $tx->dtrack_no }}</span></div>
                                                                                @endif
                                                                                @if(!empty($tx->dtrack_no_new))
                                                                                    <div class="mt-1"><span class="badge badge-light text-dark border">PO: {{ $tx->dtrack_no_new }}</span></div>
                                                                                @endif
                                                                                @if($tx->obligation_serial)
                                                                                    <div class="text-primary financial-number" style="font-size: 0.65rem;">{{ $tx->obligation_serial }}</div>
                                                                                @endif
                                                                                @if(!empty($tx->is_multi_activity))
                                                                                    <div class="mt-1"><span class="badge badge-info" style="font-size: 0.6rem;">Consolidated Transaction in one DTRACK</span></div>
                                                                                @endif
                                                                            </td>
                                                                            <td class="font-weight-bold">{{ $tx->creditor ?: '-' }}</td>
                                                                            <td>
                                                                                <div class="{{ $isTxCancelled ? 'strike-cancelled' : '' }}">{{ $tx->particulars }}</div>
                                                                                @if($isTxCancelled && !empty($tx->cancellation_remarks))
                                                                                    <div class="text-danger small mt-1"><i class="fas fa-info-circle mr-1"></i>{{ $tx->cancellation_remarks }}</div>
                                                                                @endif
                                                                            </td>
                                                                            <td class="text-right financial-number">₱{{ number_format($tx->routed_amount, 2) }}</td>
                                                                            <td class="text-right financial-number text-primary">₱{{ number_format($netObligAmt, 2) }}</td>
                                                                            <td class="text-right financial-number text-danger">{{ $txNorsaAmt > 0 ? '(₱' . number_format($txNorsaAmt, 2) . ')' : '-' }}</td>
                                                                            <td class="text-right financial-number text-success">{{ $isTxCancelled ? '-' : '₱' . number_format($tx->disbursement_amount, 2) }}</td>
                                                                            <td class="text-center align-middle">
                                                                                <div>
                                                                                    <span class="badge px-2 py-1 shadow-sm {{
                                                                                        in_array($displayStatus, ['Disbursed', 'Completed']) ? 'badge-success' :
                                                                                        ($displayStatus == 'Disbursed (with savings)' ? 'badge-info' :
                                                                                        ($isTxCancelled || $displayStatus == 'Rejected' ? 'badge-danger' :
                                                                                        ($isStatusPo ? 'badge-primary' :
                                                                                        ($isStatusCaf ? 'badge-warning text-dark' : 'badge-secondary')))) }}">
                                                                                        @if($isTxCancelled)
                                                                                            <i class="fas fa-ban mr-1"></i>
                                                                                        @elseif($isStatusPo)
                                                                                            <i class="fas fa-file-contract mr-1"></i>
                                                                                        @elseif($isStatusCaf)
                                                                                            <i class="fas fa-tag mr-1"></i>
                                                                                        @endif
                                                                                        {{ $displayStatus }}
                                                                                    </span>
                                                                                </div>
                                                                                @if(!$isTxCancelled && !empty($procurementBadge) && $displayStatus !== $procurementBadge)
                                                                                    <div class="mt-1">
                                                                                        {{-- <span class="badge px-2 py-0 {{ $procurementTag === 'PO' ? 'badge-primary' : ($procurementTag === 'CAF' ? 'badge-warning text-dark' : 'badge-info') }}" style="font-size: 0.62rem;">
                                                                                            <i class="fas {{ $procurementTag === 'PO' ? 'fa-file-contract' : 'fa-tag' }} mr-1"></i>{{ $procurementBadge }}
                                                                                        </span> --}}
                                                                                    </div>
                                                                                @endif
                                                                                @if(!$isTxCancelled && $isCosConsolidated)
                                                                                    <div class="mt-1">
                                                                                        <span class="badge badge-primary px-2 py-0" style="font-size: 0.62rem;">
                                                                                            <i class="fas fa-layer-group mr-1"></i>Consolidated
                                                                                        </span>
                                                                                    </div>
                                                                                @endif
                                                                            </td>
                                                                        </tr>
                                                                    @empty
                                                                        <tr>
                                                                            <td colspan="8" class="text-center text-muted py-2">
                                                                                No transactions recorded for this activity yet (Full allotment remains unobligated).
                                                                            </td>
                                                                        </tr>
                                                                    @endforelse
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    @empty
                                                        <p class="text-center text-muted mb-0 py-2">No activities found under this UACS code.</p>
                                                    @endforelse
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                {{-- Fund Source Subtotal Footer --}}
                                @php
                                    $srcUnpaid = $source['total_unpaid'] ?? max(0, round(($source['total_net_oblig'] ?? 0) - ($source['total_disbursed'] ?? 0), 2));
                                @endphp
                                <tfoot class="bg-light font-weight-bold">
                                    <tr>
                                        <td colspan="3" class="text-right text-uppercase">Subtotal ({{ $source['source_name'] }}):</td>
                                        <td class="text-right financial-number">₱{{ number_format($source['source_total'], 2) }}</td>
                                        <td class="text-right financial-number text-warning">₱{{ number_format($source['total_pending'], 2) }}</td>
                                        <td class="text-right financial-number text-info">₱{{ number_format($source['total_obligated'], 2) }}</td>
                                        <td class="text-right financial-number text-danger">
                                            {{ $source['total_norsa'] > 0 ? '(₱' . number_format($source['total_norsa'], 2) . ')' : '-' }}
                                        </td>
                                        <td class="text-right financial-number text-primary">₱{{ number_format($source['total_net_oblig'], 2) }}</td>
                                        <td class="text-right financial-number text-success">₱{{ number_format($source['total_disbursed'], 2) }}</td>
                                        <td class="text-right financial-number" style="color: #d97706;">₱{{ number_format($srcUnpaid, 2) }}</td>
                                        <td class="text-right financial-number text-dark">₱{{ number_format($source['total_unobligated'], 2) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="alert alert-light border text-center text-muted py-4">
            No UACS records found for the selected filters.
        </div>
    @endforelse
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        let isExpanded = false;
        $('#expandAllBtn').on('click', function() {
            isExpanded = !isExpanded;
            if (isExpanded) {
                $('.collapse:not(#filterCard)').collapse('show');
                $(this).html('<i class="fas fa-compress-arrows-alt mr-1"></i> Collapse All');
            } else {
                $('.collapse:not(#filterCard)').collapse('hide');
                $(this).html('<i class="fas fa-expand-arrows-alt mr-1"></i> Expand All');
            }
        });

        const $dateFrom = $('#uacsDateFrom');
        const $dateTo   = $('#uacsDateTo');

        $dateFrom.on('change', function() {
            const fromVal = $(this).val();
            if (fromVal) {
                $dateTo.attr('min', fromVal);
                if ($dateTo.val() && $dateTo.val() < fromVal) {
                    $dateTo.val(fromVal);
                }
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
            } else {
                $dateFrom.removeAttr('max');
            }
        });

        $('#uacsClearDatesBtn').on('click', function() {
            $dateFrom.val('').removeAttr('max');
            $dateTo.val('').removeAttr('min');
            $('#filterForm').submit();
        });
    });
</script>
@endsection