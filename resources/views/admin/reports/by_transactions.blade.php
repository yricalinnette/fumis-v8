@extends('layouts.adminlte')

@section('content')
<style>
    .financial-number { font-family: 'Consolas', 'Courier New', Courier, monospace; font-weight: 600; letter-spacing: -0.3px; }
    .card-navy.card-outline { border-top: 3px solid #001f3f; }
    .bg-navy-light { background-color: #f1f4f8; color: #001f3f; }
    .bg-soft-light { background-color: #f8f9fa; }
    
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
        <i class="fas fa-info-circle text-info mr-1"></i> Obligations and Disbursements are strictly filtered based on transaction dates in the selected period.
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
                                        <th style="width: 28%;">Activity / Line Item</th>
                                        <th class="text-right" style="width: 9%;">Alloted Budget</th>
                                        <th class="text-right" style="width: 9%;">Pending</th>
                                        <th class="text-right" style="width: 9%;">Obligated</th>
                                        <th class="text-right" style="width: 8%;">NORSA</th>
                                        <th class="text-right" style="width: 9%;">Disbursed</th>
                                        <th class="text-right" style="width: 9%;">Savings</th>
                                        <th class="text-right" style="width: 9%;">Unobligated</th>
                                        <th class="text-center" style="width: 10%;">Transactions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($source['activities'] as $act)
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
                                            <td class="text-right align-middle financial-number text-info">₱{{ number_format($act['gross_obligated'], 2) }}</td>
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
                                                                <th class="text-right border-left-info">Gross Obligated</th>
                                                                <th class="text-right border-left-danger text-danger">NORSA</th>
                                                                <th class="text-right border-left-success">Disbursed</th>
                                                                <th class="text-center border-left-primary" style="width: 180px;">Status & Remarks</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="text-xs">
                                                            @forelse($act['transactions'] as $tx)
                                                                @php
                                                                    $creditorName = $tx->creditor ?? null;
                                                                    if (!$creditorName && isset($tx->creditors) && $tx->creditors->isNotEmpty()) {
                                                                        $creditorName = $tx->creditors->map(fn($c) => $c->employeeDetail->fullname ?? $c->full_name ?? null)->filter()->implode(', ');
                                                                    }
                                                                    $isCosSalary = (isset($tx->remarks_salary) && $tx->remarks_salary === 'Imported HR COS Salary/Wages') 
                                                                        || \Illuminate\Support\Str::contains(strtolower($tx->remarks ?? ''), ['cos', 'salary', 'wages', 'payroll']);
                                                                    
                                                                    $txGrossOblig = (float) $tx->obligation_amount;
                                                                    if (!empty($tx->is_multi_activity) && ($tx->norsa_amount ?? 0) > 0) {
                                                                        $txGrossOblig += (float) $tx->norsa_amount;
                                                                    }
                                                                @endphp
                                                                <tr>
                                                                    <td class="align-middle text-nowrap">{{ !empty($tx->obligation_date) ? \Carbon\Carbon::parse($tx->obligation_date)->format('M d, Y') : '-' }}</td>
                                                                    <td class="align-middle text-nowrap">{{ !empty($tx->disbursement_date) ? \Carbon\Carbon::parse($tx->disbursement_date)->format('M d, Y') : '-' }}</td>
                                                                    <td class="align-middle text-nowrap">
                                                                        @if(!empty($tx->dtrack_no))<div><span class="badge badge-light text-danger border">{{ $tx->dtrack_no }}</span></div>@endif
                                                                        @if($tx->obligation_serial)<div class="text-primary financial-number" style="font-size: 0.68rem;"><i class="fas fa-barcode mr-1"></i>{{ $tx->obligation_serial }}</div>@endif
                                                                    </td>
                                                                    <td class="align-middle font-weight-bold">{{ $creditorName ?: '-' }}</td>
                                                                    <td class="align-middle text-wrap">{{ $tx->particulars }}</td>
                                                                    
                                                                    <td class="text-right align-middle financial-number font-weight-bold">
                                                                        ₱{{ number_format($tx->amount, 2) }}
                                                                    </td>

                                                                    <td class="text-right align-middle font-weight-bold text-info border-left-info financial-number">
                                                                        ₱{{ number_format($txGrossOblig, 2) }}
                                                                    </td>

                                                                    <td class="text-right align-middle font-weight-bold text-danger border-left-danger financial-number">
                                                                        @if(($tx->norsa_amount ?? 0) > 0)
                                                                            (₱{{ number_format($tx->norsa_amount, 2) }})
                                                                        @else
                                                                            -
                                                                        @endif
                                                                    </td>

                                                                    <td class="text-right align-middle font-weight-bold text-success border-left-success financial-number">
                                                                        ₱{{ number_format($tx->disbursement_amount, 2) }}
                                                                    </td>

                                                                    <td class="text-center align-middle bg-soft-light py-2 border-left-primary">
                                                                        <div>
                                                                            <span class="badge px-2 py-1 shadow-sm {{ 
                                                                                in_array($tx->status, ['Disbursed', 'Completed']) ? 'badge-success' : 
                                                                                ($tx->status == 'Disbursed (with savings)' ? 'badge-info' : 
                                                                                ($tx->status == 'Cancelled' || $tx->status == 'Rejected' ? 'badge-danger' : 'badge-secondary')) }}">
                                                                                {{ $tx->status ?? 'N/A' }}
                                                                            </span>
                                                                        </div>
                                                                        @if(!empty($tx->remarks))
                                                                            <div class="mt-1 text-left small border-left pl-2 {{ $isCosSalary ? 'border-warning text-dark' : 'border-secondary text-muted' }}" style="font-size: 0.65rem; font-style: italic;">
                                                                                <strong>{{ $isCosSalary ? 'COS' : 'DTrack' }}:</strong> {{ $tx->remarks }}
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