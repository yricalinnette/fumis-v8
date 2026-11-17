@extends('layouts.adminlte')

@section('content')
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
    @media print { .filter-section { display: none !important; } }
</style>

<div class="container-fluid py-3">
    {{-- Top Header Strip --}}
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <h4 class="text-navy mb-1 font-weight-bold">
                <i class="fas fa-list-ul mr-2 text-primary"></i>Activity Transaction Report
            </h4>
            <p class="text-muted small mb-0">
                Financial Performance Analysis for Fiscal Year <strong>{{ request('year', $year ?? date('Y')) }}</strong>
                @if(!empty($selectedQuarters))
                    | Quarters: <strong>Q{{ implode(', Q', (array)$selectedQuarters) }} (Cumulative)</strong>
                @endif
            </p>
        </div>
        
        <div class="btn-group shadow-sm">
            <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-sm btn-success">
                <i class="fas fa-file-excel mr-1"></i> Export Excel
            </a>
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
        <i class="fas fa-info-circle text-info mr-1"></i> Obligations, Cancellations, and Disbursements are strictly filtered based on transaction dates in the selected period.
    </p>

    {{-- FILTER & SEARCH TOOLBAR --}}
    <div class="card card-default filter-section collapse show shadow-sm mb-4" id="filterCard">
        <div class="card-body p-3">
            <form method="GET" action="{{ url()->current() }}" id="filterForm">
                <div class="row align-items-center">
                    {{-- Fiscal Year --}}
                    <div class="col-md-2 mb-2 mb-md-0">
                        <label class="small font-weight-bold mb-1">Fiscal Year</label>
                        <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                            @foreach(range(date('Y') + 1, 2022) as $y)
                                <option value="{{ $y }}" {{ ($year ?? date('Y')) == $y ? 'selected' : '' }}>FY {{ $y }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Section --}}
                    <div class="col-md-2 mb-2 mb-md-0">
                        <label class="small font-weight-bold mb-1">Filter Section</label>
                        <select name="section" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="">-- All Sections --</option>
                            @foreach($groupedReport as $sec)
                                <option value="{{ $sec['section_name'] }}" {{ request('section') == $sec['section_name'] ? 'selected' : '' }}>
                                    {{ $sec['section_name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Select Quarters (Cumulative) --}}
                    <div class="col-md-4 mb-2 mb-md-0">
                        <label class="small font-weight-bold mb-1 d-block">Select Quarters (Cumulative)</label>
                        <div class="d-flex align-items-center flex-wrap bg-white border rounded px-2 py-1" style="gap: 12px; height: 31px;">
                            @foreach([1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4'] as $qNum => $qLabel)
                                <div class="custom-control custom-checkbox custom-control-inline m-0">
                                    <input type="checkbox" name="quarters[]" value="{{ $qNum }}" id="q_chk_{{ $qNum }}" class="custom-control-input" {{ in_array($qNum, $selectedQuarters ?? []) ? 'checked' : '' }} onchange="this.form.submit()">
                                    <label class="custom-control-label small font-weight-bold text-dark cursor-pointer" for="q_chk_{{ $qNum }}">{{ $qLabel }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Search Keywords with Search & Reset Buttons --}}
                    <div class="col-md-4">
                        <label class="small font-weight-bold mb-1">Search Keywords</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="search" class="form-control" placeholder="DTrack, OBRN, Payee..." value="{{ $search ?? '' }}">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary" title="Search"><i class="fas fa-search"></i></button>
                                <a href="{{ url()->current() }}" class="btn btn-secondary" title="Reset Filters"><i class="fas fa-undo"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- CLEAN MASTER TABLE LAYOUT --}}
    @foreach($groupedReport as $section)
        {{-- If section filter is active, skip non-matching sections --}}
        @if(request('section') && request('section') !== $section['section_name'])
            @continue
        @endif

        <div class="mb-5">
            <div class="section-banner py-2 px-3 mb-2 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <i class="fas fa-university mr-2 text-warning"></i>
                    <h5 class="font-weight-bold mb-0 text-uppercase" style="font-size: 1rem;">{{ $section['section_name'] }}</h5>
                </div>
                <span class="badge badge-light text-navy font-weight-bold px-2 py-1">{{ $section['sources']->count() }} Fund Sources</span>
            </div>

            @foreach($section['sources'] as $source)
                <div class="card card-navy card-outline mb-4 shadow-sm">
                    <div class="card-header bg-white py-2 px-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="card-title text-navy font-weight-bold mb-0">
                                <i class="fas fa-folder text-warning mr-2"></i>{{ $source['source_name'] }}
                            </h6>
                            <span class="text-dark font-weight-bold financial-number">Total: ₱{{ number_format($source['source_total'] ?? 0, 2) }}</span>
                        </div>
                    </div>
                    
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0 text-xs">
                                <thead class="bg-navy-light text-uppercase">
                                    <tr>
                                        <th style="width: 26%;">Activity / Line Item</th>
                                        <th class="text-right" style="width: 9.5%;">Alloted Budget</th>
                                        <th class="text-right" style="width: 9%;">Pending</th>
                                        <th class="text-right" style="width: 9.5%;">Obligated</th>
                                        <th class="text-right" style="width: 8.5%;">NORSA</th>
                                        <th class="text-right" style="width: 9.5%;">Disbursed</th>
                                        <th class="text-right" style="width: 8.5%;">Savings</th>
                                        <th class="text-right" style="width: 9.5%;">Unobligated</th>
                                        <th class="text-center" style="width: 10%;">Transactions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($source['activities'] as $act)
                                        @php
                                            $cancelledCount = $act['transactions']->filter(function ($t) {
                                                $baseOb   = (float) ($t->gross_obligated ?? $t->obligation_amount ?? 0);
                                                $norsaAmt = (float) ($t->norsa_amount ?? 0);
                                                return !empty($t->is_cancelled)
                                                    || strtolower((string) ($t->status ?? '')) === 'cancelled'
                                                    || ($baseOb > 0 && abs($baseOb - $norsaAmt) < 0.01);
                                            })->count();
                                        @endphp
                                        <tr>
                                            <td class="align-middle font-weight-bold text-dark">
                                                {{ $act['details']->name }}
                                                @if($act['is_pooled'])
                                                    <span class="badge badge-warning text-dark ml-1 d-block mt-1" style="font-size: 0.65rem; width: fit-content;">
                                                        POOLED AMOUNT: ₱{{ number_format($act['pooled_amount'], 2) }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-right align-middle financial-number">₱{{ number_format($act['net_budget'], 2) }}</td>
                                            <td class="text-right align-middle financial-number text-warning">₱{{ number_format($act['pending'], 2) }}</td>
                                            <td class="text-right align-middle financial-number text-info">₱{{ number_format($act['obligated'], 2) }}</td>
                                            <td class="text-right align-middle financial-number text-danger">
                                                {{ ($act['norsa_amount'] ?? 0) > 0 ? '(₱'.number_format($act['norsa_amount'], 2).')' : '-' }}
                                            </td>
                                            <td class="text-right align-middle financial-number text-success">₱{{ number_format($act['disbursed'], 2) }}</td>
                                            <td class="text-right align-middle financial-number text-teal">
                                                {{ ($act['has_cos_salary'] && $act['savings'] > 0) ? '₱'.number_format($act['savings'], 2) : '-' }}
                                            </td>
                                            <td class="text-right align-middle financial-number text-primary">₱{{ number_format($act['untouched'], 2) }}</td>
                                            <td class="text-center align-middle">
                                                <button class="btn btn-xs btn-outline-info font-weight-bold px-2" data-toggle="collapse" data-target="#trx-collapse-{{ $act['details']->id }}">
                                                    <i class="fas fa-list mr-1"></i> ({{ $act['transactions']->count() }})
                                                </button>
                                                @if($cancelledCount > 0)
                                                    <span class="badge badge-danger d-block mx-auto mt-1" style="font-size: 0.62rem; width: fit-content;">
                                                        {{ $cancelledCount }} Cancelled
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>

                                        {{-- Inline Collapsible Transaction Breakdown Table --}}
                                        <tr class="collapse bg-soft-light" id="trx-collapse-{{ $act['details']->id }}">
                                            <td colspan="9" class="p-3">
                                                <div class="border rounded bg-white p-2">
                                                    <span class="font-weight-bold text-muted mb-2 d-block"><i class="fas fa-info-circle mr-1"></i> Transaction Breakdown</span>
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <thead class="bg-light text-uppercase" style="font-size: 0.65rem;">
                                                            <tr>
                                                                <th>Oblig. Date</th>
                                                                <th>Disb. Date</th>
                                                                <th>DTrack / OBRN</th>
                                                                <th>Payee / Creditor</th>
                                                                <th>Particulars</th>
                                                                <th class="text-right">Amount</th>
                                                                <th class="text-right border-left-info">Net Obligated</th>
                                                                <th class="text-right border-left-danger text-danger">NORSA</th>
                                                                <th class="text-right border-left-success">Disbursed</th>
                                                                <th class="text-center border-left-primary" style="width: 195px;">Status & Remarks</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="text-xs">
                                                            @forelse($act['transactions'] as $tx)
                                                                @php
                                                                    $creditorName = $tx->creditor ?? null;
                                                                    if (!$creditorName && isset($tx->creditors) && $tx->creditors->isNotEmpty()) {
                                                                        $creditorName = $tx->creditors->map(fn($c) => $c->employeeDetail->fullname ?? $c->full_name ?? null)->filter()->implode(', ');
                                                                    }

                                                                    $txNorsaAmt  = (float) ($tx->norsa_amount ?? 0);
                                                                    $storedOb    = (float) ($tx->obligation_amount ?? 0);
                                                                    $baseGrossOb = (float) ($tx->gross_obligated ?? (!empty($tx->ob_is_net_of_norsa) ? ($storedOb + $txNorsaAmt) : $storedOb));

                                                                    // Detect explicit Cancelled OR 100% NORSA reversal
                                                                    $isFullNorsaCancel = ($baseGrossOb > 0 && abs($baseGrossOb - $txNorsaAmt) < 0.01);
                                                                    $isTxCancelled     = !empty($tx->is_cancelled)
                                                                        || strtolower((string) ($tx->status ?? '')) === 'cancelled'
                                                                        || $isFullNorsaCancel;

                                                                    $txNetOblig = $isTxCancelled
                                                                        ? 0.00
                                                                        : (float) ($tx->net_obligated ?? max(0.00, $baseGrossOb - $txNorsaAmt));

                                                                    // COS Salary & Consolidated Salary Resolution
                                                                    $isCosSalary = !empty($tx->is_cos_salary)
                                                                        || (isset($tx->remarks_salary) && $tx->remarks_salary === 'Imported HR COS Salary/Wages')
                                                                        || !empty($tx->cos_contract_id)
                                                                        || \Illuminate\Support\Str::contains(strtolower($tx->remarks ?? ''), ['cos', 'salary', 'wages', 'payroll']);

                                                                    $cosDisbursements = $tx->cosSalaryDisbursements ?? collect();
                                                                    $isCosConsolidated = !empty($tx->is_cos_consolidated) || $cosDisbursements->contains(function ($d) {
                                                                        return ((int) ($d->months_covered ?? $d->months_count ?? 1) > 1)
                                                                            || !empty($d->is_consolidated)
                                                                            || \Illuminate\Support\Str::contains(strtolower((string) ($d->remarks ?? $d->notes ?? $d->period_label ?? '')), 'consolidated');
                                                                    });

                                                                    $displayStatus = $tx->status ?? 'N/A';
                                                                    if ($isTxCancelled) {
                                                                        $displayStatus = 'Cancelled';
                                                                    } elseif ($isCosSalary) {
                                                                        $effectiveMonthsPaid = $cosDisbursements->count() > 0
                                                                            ? (int) $cosDisbursements->sum(function ($d) {
                                                                                $m = (int) ($d->months_covered ?? $d->months_count ?? 0);
                                                                                if ($m > 0) return $m;
                                                                                return (!empty($d->is_consolidated) || \Illuminate\Support\Str::contains(strtolower((string) ($d->remarks ?? $d->notes ?? '')), 'consolidated')) ? 2 : 1;
                                                                            })
                                                                            : (int) ($tx->disbursed_months ?? 0);
                                                                        $totalContractMonths = (int) ($tx->cosContract->total_months ?? 0);

                                                                        $totalCosDisb = $cosDisbursements->count() > 0
                                                                            ? (float) $cosDisbursements->sum('amount')
                                                                            : (float) ($tx->disbursement_amount ?? 0);
                                                                        $cosSavingsDiff = round($storedOb - $totalCosDisb, 2);

                                                                        $isCosFullyDisbursed = in_array($displayStatus, ['Completed', 'Disbursed (with savings)'])
                                                                            || ($tx->cosContract && $tx->cosContract->status === 'Completed')
                                                                            || ($totalContractMonths > 0 && $effectiveMonthsPaid >= $totalContractMonths)
                                                                            || ($totalContractMonths > 0 && (int) ($tx->disbursed_months ?? 0) >= $totalContractMonths)
                                                                            || ($totalCosDisb > 0 && $txNorsaAmt > 0 && abs($cosSavingsDiff - $txNorsaAmt) < 0.01);

                                                                        if ($isCosFullyDisbursed) {
                                                                            $displayStatus = ($totalCosDisb > 0 && $cosSavingsDiff > 0.009)
                                                                                ? 'Disbursed (with savings)'
                                                                                : 'Completed';
                                                                        }
                                                                    }

                                                                    $displayRemarks = $isTxCancelled && !empty($tx->cancellation_remarks)
                                                                        ? $tx->cancellation_remarks
                                                                        : ($isFullNorsaCancel
                                                                            ? ('Cancelled via Full NORSA' . (!empty($tx->remarks) ? ' — ' . $tx->remarks : ''))
                                                                            : ($tx->remarks ?? $tx->manual_remarks ?? null));
                                                                @endphp
                                                                <tr class="{{ $isTxCancelled ? 'row-cancelled' : '' }}">
                                                                    <td class="align-middle text-nowrap">{{ !empty($tx->obligation_date) ? \Carbon\Carbon::parse($tx->obligation_date)->format('M d, Y') : '-' }}</td>
                                                                    <td class="align-middle text-nowrap">{{ !$isTxCancelled && !empty($tx->disbursement_date) ? \Carbon\Carbon::parse($tx->disbursement_date)->format('M d, Y') : '-' }}</td>
                                                                    <td class="align-middle text-nowrap">
                                                                        @if(!empty($tx->dtrack_no))
                                                                            <div><span class="badge badge-light text-danger border">{{ $tx->dtrack_no }}</span></div>
                                                                        @endif
                                                                        @if(!empty($tx->dtrack_no_new))
                                                                            <div class="mt-1"><span class="badge badge-light text-dark border">PO: {{ $tx->dtrack_no_new }}</span></div>
                                                                        @endif
                                                                        @if($tx->obligation_serial)
                                                                            <div class="text-primary financial-number mt-1" style="font-size: 0.68rem;">
                                                                                <i class="fas fa-barcode mr-1"></i>{{ $tx->obligation_serial }}
                                                                            </div>
                                                                        @endif
                                                                    </td>
                                                                    <td class="align-middle font-weight-bold">{{ $creditorName ?: '-' }}</td>
                                                                    <td class="align-middle text-wrap {{ $isTxCancelled ? 'strike-cancelled' : '' }}">{{ $tx->particulars }}</td>
                                                                    
                                                                    <td class="text-right align-middle financial-number font-weight-bold {{ $isTxCancelled ? 'strike-cancelled' : '' }}">
                                                                        ₱{{ number_format($tx->amount, 2) }}
                                                                    </td>

                                                                    <td class="text-right align-middle font-weight-bold text-info border-left-info financial-number {{ $isTxCancelled ? 'strike-cancelled' : '' }}">
                                                                        ₱{{ number_format($txNetOblig, 2) }}
                                                                    </td>

                                                                    <td class="text-right align-middle font-weight-bold text-danger border-left-danger financial-number">
                                                                        @if($txNorsaAmt > 0)
                                                                            (₱{{ number_format($txNorsaAmt, 2) }})
                                                                        @else
                                                                            -
                                                                        @endif
                                                                    </td>

                                                                    <td class="text-right align-middle font-weight-bold text-success border-left-success financial-number">
                                                                        {{ $isTxCancelled ? '-' : '₱'.number_format($tx->disbursement_amount, 2) }}
                                                                    </td>

                                                                    <td class="text-center align-middle bg-soft-light py-2 border-left-primary">
                                                                        <div>
                                                                            <span class="badge px-2 py-1 shadow-sm {{ 
                                                                                in_array($displayStatus, ['Disbursed', 'Completed']) ? 'badge-success' : 
                                                                                ($displayStatus == 'Disbursed (with savings)' ? 'badge-info' : 
                                                                                ($isTxCancelled || $displayStatus == 'Rejected' ? 'badge-danger' : 'badge-secondary')) }}">
                                                                                @if($isTxCancelled)
                                                                                    <i class="fas fa-ban mr-1"></i>
                                                                                @endif
                                                                                {{ $displayStatus }}
                                                                            </span>
                                                                        </div>

                                                                        @if(!$isTxCancelled && $isCosConsolidated)
                                                                            <div class="mt-1">
                                                                                <span class="badge badge-primary px-2 py-0" style="font-size: 0.62rem;">
                                                                                    <i class="fas fa-layer-group mr-1"></i>Consolidated
                                                                                </span>
                                                                            </div>
                                                                        @endif

                                                                        @if($isTxCancelled && !empty($tx->status_date))
                                                                            <div class="text-danger mt-1" style="font-size: 0.62rem;">
                                                                                Cancelled: {{ \Carbon\Carbon::parse($tx->status_date)->format('M d, Y') }}
                                                                            </div>
                                                                        @endif

                                                                        @if(!empty($displayRemarks))
                                                                            <div class="mt-1 text-left small border-left pl-2 {{ $isTxCancelled ? 'border-danger text-danger' : ($isCosSalary ? 'border-warning text-dark' : 'border-secondary text-muted') }}" style="font-size: 0.65rem; font-style: italic;">
                                                                                <strong>{{ $isTxCancelled ? 'Reason' : ($isCosSalary ? 'COS' : 'DTrack') }}:</strong> {{ $displayRemarks }}
                                                                            </div>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @empty
                                                                <tr>
                                                                    <td colspan="10" class="text-center text-muted py-2">No transactions recorded for this activity.</td>
                                                                </tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // Expand All / Collapse All functionality for cards/tables
        let isExpanded = false;
        $('#expandAllBtn').on('click', function() {
            isExpanded = !isExpanded;
            if (isExpanded) {
                $('.collapse').collapse('show');
                $(this).html('<i class="fas fa-compress-arrows-alt mr-1"></i> Collapse All');
            } else {
                $('.collapse').collapse('hide');
                $(this).html('<i class="fas fa-expand-arrows-alt mr-1"></i> Expand All');
            }
        });
    });
</script>
@endsection