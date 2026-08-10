@extends('layouts.adminlte')

@section('content')
<style>
    .financial-number { font-family: 'Consolas', 'Courier New', Courier, monospace; font-weight: 600; letter-spacing: -0.3px; }
    .card-navy.card-outline { border-top: 3px solid #001f3f; }
    .bg-navy-light { background-color: #f1f4f8; color: #001f3f; }
    .bg-soft-light { background-color: #f8f9fa; }
    
    /* Prominent Section Divider Styling */
    .section-banner {
        background: linear-gradient(135deg, #001f3f 0%, #003366 100%);
        color: #ffffff;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    }

    .border-left-info { border-left: 3px solid #17a2b8 !important; }
    .border-left-success { border-left: 3px solid #28a745 !important; }
    .border-left-warning { border-left: 3px solid #ffc107 !important; }
    .border-left-teal { border-left: 3px solid #20c997 !important; }
    .border-left-primary { border-left: 3px solid #007bff !important; }
    
    @media print { .filter-section { display: none !important; } }
</style>

<div class="container-fluid py-3">
    {{-- Top Header Strip --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-navy mb-1 font-weight-bold">
                <i class="fas fa-list-ul mr-2 text-primary"></i>Activity Transaction Report
            </h4>
            <p class="text-muted small mb-0">
                Financial Performance Analysis for Fiscal Year <strong>{{ request('year', $year ?? date('Y')) }}</strong>
                @if(request('month'))
                    | Month: <strong>{{ date('F', mktime(0, 0, 0, request('month'), 1)) }}</strong>
                @elseif(!empty($selectedQuarters))
                    | Quarters: <strong>Q{{ implode(', Q', (array)$selectedQuarters) }} (Cumulative)</strong>
                @endif
            </p>
            <div class="mt-1 d-flex align-items-center text-xs text-muted">
                <i class="fas fa-info-circle mr-1 text-info"></i>
                <span>Obligations and Disbursements are strictly filtered based on transaction dates in the selected period.</span>
            </div>
        </div>
        
        {{-- Toolbar Buttons --}}
        <div class="btn-group shadow-sm">
            <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-sm btn-success">
                <i class="fas fa-file-excel mr-1"></i> Export Excel
            </a>
            <button class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#filterCard">
                <i class="fas fa-filter mr-1"></i> Filter Options
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="$('.card').CardWidget('expand')" title="Expand All Cards">
                <i class="fas fa-expand-alt"></i> Expand All
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="fas fa-print mr-1"></i> Print
            </button>
        </div>
    </div>

    {{-- FILTER & SEARCH TOOLBAR --}}
    <div class="card card-default filter-section collapse show shadow-sm mb-4" id="filterCard">
        <div class="card-body p-3">
            <form method="GET" action="{{ url()->current() }}" id="filterForm">
                <div class="row align-items-center">
                    
                    {{-- Fiscal Year --}}
                    <div class="col-md-2 col-6 mb-2 mb-md-0">
                        <label class="small font-weight-bold mb-1">Fiscal Year</label>
                        <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                            @foreach(range(date('Y') + 1, 2022) as $y)
                                <option value="{{ $y }}" {{ ($year ?? date('Y')) == $y ? 'selected' : '' }}>FY {{ $y }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Section Filter (Budget/Admin/Division) --}}
                    @if($canFilter ?? false)
                        <div class="col-md-3 col-6 mb-2 mb-md-0">
                            <label class="small font-weight-bold mb-1">Filter Section</label>
                            <select name="section_id" class="form-control form-control-sm select2" onchange="this.form.submit()">
                                <option value="">-- All Sections --</option>
                                @foreach($sectionNames as $secId => $secName)
                                    <option value="{{ $secId }}" {{ ($selectedSection ?? '') == $secId ? 'selected' : '' }}>
                                        {{ $secName }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Cumulative Quarters Checkboxes --}}
                    <div class="col-md-4 col-12 mb-2 mb-md-0">
                        <label class="small font-weight-bold mb-1 d-block">Select Quarters (Cumulative)</label>
                        <div class="d-flex align-items-center flex-wrap bg-white border rounded px-2 py-1" style="gap: 12px; height: 31px;">
                            @foreach([1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4'] as $qNum => $qLabel)
                                <div class="custom-control custom-checkbox custom-control-inline m-0">
                                    <input type="checkbox" 
                                        name="quarters[]" 
                                        value="{{ $qNum }}" 
                                        id="q_chk_{{ $qNum }}" 
                                        class="custom-control-input"
                                        {{ in_array($qNum, $selectedQuarters ?? []) ? 'checked' : '' }}
                                        onchange="this.form.submit()">
                                    <label class="custom-control-label small font-weight-bold text-dark" for="q_chk_{{ $qNum }}">
                                        {{ $qLabel }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Search & Reset --}}
                    <div class="col-md-3 col-12">
                        <label class="small font-weight-bold mb-1">Search Keywords</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="search" class="form-control" placeholder="DTrack, OBRN, Payee..." value="{{ $search ?? '' }}">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                                @if(!empty($search) || !empty($selectedSection) || !empty($selectedQuarters))
                                    <a href="{{ url()->current() }}" class="btn btn-default" title="Reset Filters"><i class="fas fa-undo"></i></a>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- 1. SECTION LOOP --}}
    @foreach($groupedReport as $section)
        <div class="mb-5">
            {{-- Prominent Section Banner --}}
            <div class="section-banner py-2 px-3 mb-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <i class="fas fa-university mr-2 text-warning" style="font-size: 1.1rem;"></i>
                    <h5 class="font-weight-bold mb-0 text-uppercase" style="letter-spacing: 0.5px; font-size: 1.05rem;">
                        {{ $section['section_name'] }}
                    </h5>
                </div>
                <span class="badge badge-light text-navy font-weight-bold px-2 py-1" style="font-size: 0.75rem;">
                    {{ $section['sources']->count() }} Fund Sources
                </span>
            </div>

            {{-- 2. FUND SOURCE LOOP --}}
            @foreach($section['sources'] as $source)
                <div class="card card-navy card-outline mb-4 shadow-sm">
                    {{-- Compact Header --}}
                    <div class="card-header bg-white py-2 px-3" style="cursor: pointer;" data-card-widget="collapse">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-folder text-warning mr-2"></i>
                                <h6 class="card-title text-navy font-weight-bold mb-0 mr-3" style="font-size: 0.95rem;">
                                    {{ $source['source_name'] }}
                                </h6>
                                
                                <div class="d-flex align-items-center border-left pl-3" style="height: 16px; border-color: #dee2e6 !important;">
                                    <span class="text-uppercase text-muted mr-2" style="font-size: 0.65rem; font-weight: 700;">Source Total:</span>
                                    <span class="text-dark font-weight-bold financial-number" style="font-size: 0.95rem;">
                                        ₱{{ number_format($source['source_total'] ?? 0, 2) }}
                                    </span>
                                </div>
                            </div>

                            <div class="card-tools d-flex align-items-center">
                                <button type="button" class="btn btn-tool btn-xs text-primary mr-1" title="Copy Card Summary">
                                    <i class="fas fa-camera"></i>
                                </button>
                                <button type="button" class="btn btn-tool btn-xs" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body p-0">
                        {{-- 3. ACTIVITY LOOP --}}
                        @foreach($source['activities'] as $act)
                            <div class="border-top p-3 {{ $act['is_pooled'] ? 'bg-light border-left-warning' : 'bg-white' }}">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
                                            <h6 class="font-weight-bold text-dark mb-0" style="font-size: 0.9rem;">
                                                {{ $act['details']->name }}
                                            </h6>

                                            {{-- POOLED ACTIVITY BADGE TAG --}}
                                            @if($act['is_pooled'])
                                                <span class="badge badge-warning text-dark font-weight-bold px-2 py-1 shadow-sm" style="font-size: 0.65rem;" title="This activity has pooled funds">
                                                    <i class="fas fa-layer-group mr-1"></i> POOLED ACTIVITY
                                                </span>
                                            @endif
                                        </div>

                                        <div class="d-flex align-items-center mt-2">
                                            <button class="btn btn-xs btn-outline-info font-weight-bold text-uppercase px-2 mr-2" 
                                                    data-toggle="collapse" 
                                                    data-target="#collapse-{{ $act['details']->id }}">
                                                <i class="fas fa-search-dollar mr-1"></i> 
                                                Transactions ({{ $act['transactions']->count() }})
                                            </button>
                                        </div>
                                    </div>

                                    <div class="col-md-8">
                                        <div class="row text-center no-gutters bg-soft-light rounded border py-2">
                                            <div class="col border-right">
                                                <small class="d-block text-muted text-uppercase" style="font-size: 0.6rem; font-weight: 700;">Net Budget</small>
                                                <span class="font-weight-bold financial-number">₱{{ number_format($act['net_budget'], 2) }}</span>
                                            </div>

                                            {{-- POOLED AMOUNT COLUMN --}}
                                            @if($act['is_pooled'])
                                                <div class="col border-right bg-warning-subtle">
                                                    <small class="d-block text-danger text-uppercase" style="font-size: 0.6rem; font-weight: 700;">
                                                        <i class="fas fa-minus-circle mr-0.5"></i> Pooled Amount
                                                    </small>
                                                    <span class="font-weight-bold text-danger financial-number">
                                                        ₱{{ number_format($act['pooled_amount'], 2) }}
                                                    </span>
                                                </div>
                                            @endif

                                            <div class="col border-right border-left-warning">
                                                <small class="d-block text-warning text-uppercase" style="font-size: 0.6rem; font-weight: 700;">Pending</small>
                                                <span class="font-weight-bold text-warning financial-number">₱{{ number_format($act['pending'], 2) }}</span>
                                            </div>
                                            <div class="col border-right border-left-info">
                                                <small class="d-block text-info text-uppercase" style="font-size: 0.6rem; font-weight: 700;">Obligated</small>
                                                <span class="font-weight-bold text-info financial-number">₱{{ number_format($act['obligated'], 2) }}</span>
                                            </div>
                                            <div class="col border-right border-left-success">
                                                <small class="d-block text-success text-uppercase" style="font-size: 0.6rem; font-weight: 700;">Disbursed</small>
                                                <span class="font-weight-bold text-success financial-number">₱{{ number_format($act['disbursed'], 2) }}</span>
                                            </div>
                                            
                                            {{-- SAVINGS COLUMN --}}
                                            <div class="col border-right border-left-teal">
                                                <small class="d-block text-teal text-uppercase" style="font-size: 0.6rem; font-weight: 700;">Savings (COS)</small>
                                                <span class="font-weight-bold text-teal financial-number">
                                                    @if($act['has_cos_salary'] && $act['savings'] > 0)
                                                        ₱{{ number_format($act['savings'], 2) }}
                                                    @else
                                                        <span class="text-muted font-weight-normal">-</span>
                                                    @endif
                                                </span>
                                            </div>

                                            <div class="col border-left-primary">
                                                <small class="d-block text-primary text-uppercase" style="font-size: 0.6rem; font-weight: 700;">Unobligated</small>
                                                <span class="font-weight-bold text-primary financial-number">₱{{ number_format($act['untouched'], 2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- POOLED REASON BANNER --}}
                                @if($act['is_pooled'])
                                    <div class="mt-2 p-2 bg-warning-light border border-warning rounded text-dark small d-flex align-items-center justify-content-between" style="background-color: #fff9e6;">
                                        <div>
                                            <i class="fas fa-exclamation-triangle text-warning mr-1.5"></i>
                                            <strong>Pooling Info:</strong> 
                                            <span>Amount of <strong>₱{{ number_format($act['pooled_amount'], 2) }}</strong> was pooled from the total budget (₱{{ number_format($act['gross_budget'], 2) }}).</span>
                                            @if(!empty($act['pooled_remarks']))
                                                <span class="ml-2 border-left border-warning pl-2 font-italic text-muted">
                                                    <i class="far fa-comment-alt mr-1"></i>Reason: "{{ $act['pooled_remarks'] }}"
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                {{-- Transactions Table Collapse --}}
                                <div class="collapse mt-3" id="collapse-{{ $act['details']->id }}">
                                    <div class="table-responsive shadow-sm border rounded">
                                        <table class="table table-sm table-hover mb-0 bg-white">
                                            <thead>
                                                <tr class="bg-navy-light text-uppercase text-xs" style="font-size: 0.68rem;">
                                                    <th class="pl-3 py-2 text-nowrap" style="width: 100px;">Oblig. Date</th>
                                                    <th class="py-2 text-nowrap" style="width: 100px;">Disb. Date</th>
                                                    <th class="py-2 text-nowrap" style="width: 150px;">DTrack / OBRN</th>
                                                    <th class="py-2" style="width: 160px;">Payee / Creditor</th>
                                                    <th class="py-2">Particulars</th>
                                                    <th class="text-right py-2">Amount</th>
                                                    <th class="text-right py-2 border-left-info">Obligated</th>
                                                    <th class="text-right py-2 border-left-success">Disbursed</th>
                                                    <th class="text-center py-2 border-left-primary" style="width: 220px;">Status & Remarks</th>
                                                </tr>
                                            </thead>
                                            <tbody class="text-xs">
                                                @forelse($act['transactions'] as $tx)
                                                    @php
                                                        $creditorName = $tx->creditor ?? null;
                                                        if (!$creditorName && isset($tx->creditors) && $tx->creditors->isNotEmpty()) {
                                                            $creditorName = $tx->creditors->map(function($c) {
                                                                return $c->employeeDetail->fullname ?? $c->full_name ?? null;
                                                            })->filter()->implode(', ');
                                                        }

                                                        $isCosSalary = (isset($tx->remarks_salary) && $tx->remarks_salary === 'Imported HR COS Salary/Wages') 
                                                                    || \Illuminate\Support\Str::contains(strtolower($tx->remarks ?? ''), ['cos', 'salary', 'wages', 'payroll']);
                                                    @endphp
                                                    <tr>
                                                        {{-- Obligation Date --}}
                                                        <td class="pl-3 align-middle text-nowrap">
                                                            @if(!empty($tx->obligation_date))
                                                                <span class="text-dark font-weight-bold">
                                                                    {{ \Carbon\Carbon::parse($tx->obligation_date)->format('M d, Y') }}
                                                                </span>
                                                            @else
                                                                <span class="text-muted font-italic" title="Created date fallback">{{ $tx->created_at->format('M d, Y') }}</span>
                                                            @endif
                                                        </td>

                                                        {{-- Disbursement Date --}}
                                                        <td class="align-middle text-nowrap">
                                                            @if(!empty($tx->disbursement_date))
                                                                <span class="text-success font-weight-bold">
                                                                    {{ \Carbon\Carbon::parse($tx->disbursement_date)->format('M d, Y') }}
                                                                </span>
                                                            @else
                                                                <span class="text-muted font-italic">-</span>
                                                            @endif
                                                        </td>

                                                        {{-- DTrack No. & Obligation Serial --}}
                                                        <td class="align-middle text-nowrap">
                                                            @if(!empty($tx->dtrack_no))
                                                                <div>
                                                                    <span class="badge badge-light text-danger border border-danger-subtle font-weight-normal" style="font-size: 0.65rem;">
                                                                        {{ $tx->dtrack_no }}
                                                                    </span>
                                                                </div>
                                                            @endif

                                                            @if($tx->obligation_serial)
                                                                <div class="{{ !empty($tx->dtrack_no) ? 'mt-1' : '' }}">
                                                                    <span class="text-primary font-weight-bold financial-number" style="font-size: 0.72rem;">
                                                                        <i class="fas fa-barcode mr-1 text-primary"></i>{{ $tx->obligation_serial }}
                                                                    </span>
                                                                </div>
                                                            @elseif(empty($tx->dtrack_no))
                                                                <span class="text-muted font-italic">-</span>
                                                            @endif
                                                        </td>

                                                        {{-- Creditor / Payee --}}
                                                        <td class="align-middle font-weight-bold text-dark">
                                                            @if($creditorName)
                                                                <span class="d-inline-flex align-items-center">
                                                                    <i class="fas fa-user-tag text-muted mr-1.5" style="font-size: 0.65rem;"></i>
                                                                    <span>{{ $creditorName }}</span>
                                                                </span>
                                                            @else
                                                                <span class="text-muted font-italic">-</span>
                                                            @endif
                                                        </td>

                                                        {{-- Particulars --}}
                                                        <td class="align-middle text-wrap" style="line-height: 1.3;">{{ $tx->particulars }}</td>

                                                        {{-- Amounts --}}
                                                        <td class="text-right align-middle financial-number">₱{{ number_format($tx->amount, 2) }}</td>
                                                        <td class="text-right align-middle font-weight-bold text-info border-left-info financial-number">
                                                            ₱{{ number_format($tx->obligation_amount, 2) }}
                                                        </td>
                                                        <td class="text-right align-middle font-weight-bold text-success border-left-success financial-number">
                                                            ₱{{ number_format($tx->disbursement_amount, 2) }}
                                                        </td>

                                                        {{-- Status & Coexisting Remarks --}}
                                                        <td class="text-center align-middle bg-soft-light py-2 border-left-primary">
                                                            <div class="d-inline-block text-center" style="max-width: 200px;">
                                                                <div>
                                                                    @php
                                                                        $statusName = $tx->status ?? 'N/A';
                                                                    @endphp

                                                                    <span class="badge px-2 py-1 shadow-sm {{ 
                                                                        in_array($statusName, ['Disbursed', 'Completed']) ? 'badge-success' : 
                                                                        ($statusName == 'Disbursed (with savings)' ? 'badge-info' : 
                                                                        ($statusName == 'Cancelled' || $statusName == 'Rejected' ? 'badge-danger' : 
                                                                        ($statusName == 'Routed' ? 'badge-primary' : 
                                                                        ($statusName == 'For CAF/Obligation' ? 'badge-warning text-dark' : 
                                                                        ($statusName == 'Obligated' ? 'bg-orange text-white' : 'badge-secondary')))))
                                                                    }}">
                                                                        {{ $statusName }}
                                                                    </span>
                                                                </div>

                                                                {{-- 1. COS / DTRACK REMARK --}}
                                                                @if(!empty($tx->remarks))
                                                                    <div class="mt-1 text-left small border-left pl-2 {{ $isCosSalary ? 'border-warning text-dark' : 'border-secondary text-muted' }}" style="line-height: 1.25; font-style: italic; font-size: 0.68rem;">
                                                                        <i class="fas {{ $isCosSalary ? 'fa-id-badge text-warning' : 'fa-route text-secondary' }} mr-1"></i>
                                                                        <strong>{{ $isCosSalary ? 'COS Remark' : 'DTrack Remark' }}:</strong> {{ $tx->remarks }}
                                                                    </div>
                                                                @endif

                                                                {{-- 2. INTERNAL MANUAL REMARK --}}
                                                                @if(!empty($tx->manual_remarks))
                                                                    <div class="mt-1 text-left small border-left border-primary pl-2 text-dark rounded bg-white py-1 shadow-sm" style="line-height: 1.25; font-size: 0.68rem;">
                                                                        <i class="fas fa-user-edit text-primary mr-1"></i>
                                                                        <strong class="text-primary">Internal Remark:</strong> {{ $tx->manual_remarks }}
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="9" class="text-center py-3 text-muted">No transactions found for this activity.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
@endsection