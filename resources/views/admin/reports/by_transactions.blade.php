@extends('layouts.adminlte')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-navy mb-0 font-weight-bold">
                <i class="fas fa-list-ul mr-2 text-muted"></i>Activity Transaction Report
            </h4>
            <p class="text-muted small mb-0">Detailed history for FY {{ $year }} (Section Grouping)</p>
        </div>
    </div>

    {{-- 1. SECTION LOOP --}}
    @foreach($groupedReport as $section)
        <div class="mb-5">
            {{-- Section Title Divider --}}
            <div class="d-flex align-items-center mb-3">
                <i class="fas fa-university text-navy mr-2" style="font-size: 1.2rem;"></i>
                <h5 class="text-navy font-weight-bold mb-0 text-uppercase" style="letter-spacing: 0.5px;">
                    {{ $section['section_name'] }}
                </h5>
                <div class="flex-grow-1 ml-3 border-bottom" style="border-color: #dee2e6 !important;"></div>
            </div>

            {{-- 2. FUND SOURCE LOOP --}}
            @foreach($section['sources'] as $source)
                <div class="card card-navy card-outline mb-4 shadow-sm border-top-0">
                    {{-- Ultra-Compact Professional Header --}}
                    <div class="card-header bg-white py-1 px-3" style="cursor: pointer;" data-card-widget="collapse">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-folder text-muted mr-2" style="font-size: 0.8rem;"></i>
                                <h3 class="card-title text-navy font-weight-bold mb-0 mr-3" style="font-size: 0.95rem;">
                                    {{ $source['source_name'] }}
                                </h3>
                                
                                <div class="d-flex align-items-center border-left pl-3" style="height: 15px; border-color: #dee2e6 !important;">
                                    <span class="text-uppercase text-muted mr-2" style="font-size: 0.6rem; font-weight: 800; letter-spacing: 0.3px;">Source Total:</span>
                                    <span class="text-dark font-weight-bold" style="font-size: 0.9rem;">
                                        ₱{{ number_format($source['source_total'] ?? 0, 2) }}
                                    </span>
                                </div>
                            </div>

                            <div class="card-tools d-flex align-items-center">
                                <button type="button" class="btn btn-tool btn-xs text-primary btn-copy-card mr-1 p-1" title="Copy Card">
                                    <i class="fas fa-camera" style="font-size: 0.8rem;"></i>
                                </button>
                                <button type="button" class="btn btn-tool btn-xs p-1" data-card-widget="collapse">
                                    <i class="fas fa-minus" style="font-size: 0.8rem;"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body p-0">
                        {{-- 3. ACTIVITY LOOP --}}
                        @foreach($source['activities'] as $act)
                            <div class="border-top p-3">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <h6 class="font-weight-bold text-dark mb-1" style="font-size: 0.9rem;">{{ $act['details']->name }}</h6>
                                        <button class="btn btn-link p-0 text-xs font-weight-bold text-info text-uppercase" 
                                                data-toggle="collapse" 
                                                data-target="#collapse-{{ $act['details']->id }}">
                                            <i class="fas fa-search-dollar mr-1"></i> 
                                            Transactions ({{ $act['transactions']->count() }})
                                        </button>
                                    </div>

                                    <div class="col-md-8">
                                        <div class="row text-center no-gutters bg-light rounded border py-2">
                                            <div class="col border-right">
                                                <small class="d-block text-muted text-uppercase" style="font-size: 0.6rem;">Net Budget</small>
                                                <span class="font-weight-bold">₱{{ number_format($act['net_budget'], 2) }}</span>
                                            </div>
                                            <div class="col border-right">
                                                <small class="d-block text-warning text-uppercase" style="font-size: 0.6rem;">Pending</small>
                                                <span class="font-weight-bold text-warning">₱{{ number_format($act['pending'], 2) }}</span>
                                            </div>
                                            <div class="col border-right">
                                                <small class="d-block text-info text-uppercase" style="font-size: 0.6rem;">Obligated</small>
                                                <span class="font-weight-bold text-info">₱{{ number_format($act['obligated'], 2) }}</span>
                                            </div>
                                            <div class="col border-right">
                                                <small class="d-block text-success text-uppercase" style="font-size: 0.6rem;">Disbursed</small>
                                                <span class="font-weight-bold text-success">₱{{ number_format($act['disbursed'], 2) }}</span>
                                            </div>
                                            <div class="col">
                                                <small class="d-block text-primary text-uppercase" style="font-size: 0.6rem;">Unobligated</small>
                                                <span class="font-weight-bold text-primary">₱{{ number_format($act['untouched'], 2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Transactions Table Collapse --}}
                                <div class="collapse mt-3" id="collapse-{{ $act['details']->id }}">
                                    <div class="table-responsive shadow-sm border rounded">
                                        <table class="table table-sm table-hover mb-0 bg-white">
                                            <thead class="bg-gray-light text-xs text-muted text-uppercase">
                                                <tr>
                                                    <th class="pl-3" style="width: 120px;">Date</th>
                                                    <th style="width: 130px;">DTrack No.</th>
                                                    <th>Particulars</th>
                                                    <th class="text-right">Amount</th>
                                                    <th class="text-right">Obligated</th>
                                                    <th class="text-right">Disbursed</th>
                                                    <th class="text-center" style="width: 250px;">Status & Remarks</th>
                                                </tr>
                                            </thead>
                                            <tbody class="text-xs">
                                                @forelse($act['transactions'] as $tx)
                                                    <tr>
                                                        <td class="pl-3 align-middle">{{ $tx->obligation_date ? \Carbon\Carbon::parse($tx->obligation_date)->format('M d, Y') : $tx->created_at->format('M d, Y') }}</td>
                                                        <td class="align-middle"><code>{{ $tx->dtrack_no ?? 'N/A' }}</code></td>
                                                        <td class="align-middle text-wrap">{{ $tx->particulars }}</td>
                                                        <td class="text-right align-middle font-weight-bold">₱{{ number_format($tx->amount, 2) }}</td>
                                                        <td class="text-right align-middle text-info">₱{{ number_format($tx->obligation_amount, 2) }}</td>
                                                        <td class="text-right align-middle text-success">₱{{ number_format($tx->disbursement_amount, 2) }}</td>
                                                        <td class="text-center align-middle bg-light">
                                                            @if($tx->disbursement_amount > 0)
                                                                <span class="badge badge-success px-2">Disbursed</span>
                                                            @elseif($tx->obligation_amount > 0)
                                                                <span class="badge badge-info px-2">Obligated</span>
                                                                @if($tx->remarks)
                                                                    <div class="mt-1 small text-muted font-italic" style="line-height: 1.1;">
                                                                        <i class="fas fa-map-marker-alt text-xs mr-1 text-info"></i>{{ $tx->remarks }}
                                                                    </div>
                                                                @endif
                                                            @else
                                                                <span class="badge badge-warning text-white px-2">Pending</span>
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
                                                        <td colspan="7" class="text-center py-3 text-muted">No transactions found.</td>
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