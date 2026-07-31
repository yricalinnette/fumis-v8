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
            <p class="text-muted small mb-0">Detailed Financial Breakdown for Fiscal Year <strong>{{ $year }}</strong></p>
        </div>
        <div>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="fas fa-print mr-1"></i> Print Report
            </button>
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
                            <div class="border-top p-3 bg-white">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <h6 class="font-weight-bold text-dark mb-1" style="font-size: 0.9rem;">
                                            {{ $act['details']->name }}
                                        </h6>
                                        <button class="btn btn-xs btn-outline-info font-weight-bold text-uppercase mt-1 px-2" 
                                                data-toggle="collapse" 
                                                data-target="#collapse-{{ $act['details']->id }}">
                                            <i class="fas fa-search-dollar mr-1"></i> 
                                            Transactions ({{ $act['transactions']->count() }})
                                        </button>
                                    </div>

                                    <div class="col-md-8">
                                        <div class="row text-center no-gutters bg-soft-light rounded border py-2">
                                            <div class="col border-right">
                                                <small class="d-block text-muted text-uppercase" style="font-size: 0.6rem; font-weight: 700;">Net Budget</small>
                                                <span class="font-weight-bold financial-number">₱{{ number_format($act['net_budget'], 2) }}</span>
                                            </div>
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

                                {{-- Transactions Table Collapse --}}
                                <div class="collapse mt-3" id="collapse-{{ $act['details']->id }}">
                                    <div class="table-responsive shadow-sm border rounded">
                                        <table class="table table-sm table-hover mb-0 bg-white">
                                            <thead>
                                                <tr class="bg-navy-light text-uppercase text-xs" style="font-size: 0.68rem;">
                                                    <th class="pl-3 py-2" style="width: 120px;">Date</th>
                                                    <th style="width: 130px;">DTrack No.</th>
                                                    <th>Particulars</th>
                                                    <th class="text-right">Amount</th>
                                                    <th class="text-right border-left-info">Obligated</th>
                                                    <th class="text-right border-left-success">Disbursed</th>
                                                    <th class="text-center" style="width: 220px;">Status & Remarks</th>
                                                </tr>
                                            </thead>
                                            <tbody class="text-xs">
                                                @forelse($act['transactions'] as $tx)
                                                    <tr>
                                                        <td class="pl-3 align-middle">
                                                            {{ $tx->obligation_date ? \Carbon\Carbon::parse($tx->obligation_date)->format('M d, Y') : $tx->created_at->format('M d, Y') }}
                                                        </td>
                                                        <td class="align-middle"><code>{{ $tx->dtrack_no ?? 'N/A' }}</code></td>
                                                        <td class="align-middle text-wrap">{{ $tx->particulars }}</td>
                                                        <td class="text-right align-middle financial-number">₱{{ number_format($tx->amount, 2) }}</td>
                                                        <td class="text-right align-middle font-weight-bold text-info border-left-info financial-number">
                                                            ₱{{ number_format($tx->obligation_amount, 2) }}
                                                        </td>
                                                        <td class="text-right align-middle font-weight-bold text-success border-left-success financial-number">
                                                            ₱{{ number_format($tx->disbursement_amount, 2) }}
                                                        </td>
                                                        <td class="text-center align-middle bg-soft-light">
                                                            @if($tx->disbursement_amount > 0)
                                                                <span class="badge badge-success px-2 py-1">Disbursed</span>
                                                            @elseif($tx->obligation_amount > 0)
                                                                <span class="badge badge-info px-2 py-1">Obligated</span>
                                                                @if($tx->remarks)
                                                                    <div class="mt-1 small text-muted font-italic" style="line-height: 1.1;">
                                                                        <i class="fas fa-info-circle text-xs mr-1 text-info"></i>{{ $tx->remarks }}
                                                                    </div>
                                                                @endif
                                                            @else
                                                                <span class="badge badge-warning text-white px-2 py-1">Pending</span>
                                                                @if($tx->remarks)
                                                                    <div class="mt-1 small text-muted font-italic" style="line-height: 1.1;">
                                                                        <i class="fas fa-clock text-xs mr-1 text-warning"></i>{{ $tx->remarks }}
                                                                    </div>
                                                                @endif
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="7" class="text-center py-3 text-muted">No transactions found for this activity.</td>
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