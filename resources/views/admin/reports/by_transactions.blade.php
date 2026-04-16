@extends('layouts.adminlte')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-navy mb-0 font-weight-bold">
                <i class="fas fa-list-ul mr-2 text-muted"></i>Activity Transaction Report
            </h4>
            <p class="text-muted small mb-0">Detailed history for FY {{ $year }} (Line Item Logic)</p>
        </div>
    </div>

    {{-- 1. MOVE OFFLINE ALERT TO THE TOP --}}
    @if(isset($dtrackOffline) && $dtrackOffline)
        <div class="alert alert-warning shadow-sm mb-4">
            <h5><i class="icon fas fa-exclamation-triangle"></i> DTrack System Offline</h5>
            The external tracking system is currently unreachable. Displaying the last known locations from the database.
        </div>
    @endif

    @foreach($groupedData as $report)
        <div class="card card-navy card-outline mb-5 shadow-sm">
            <div class="card-header bg-white">
                <h3 class="card-title text-navy font-weight-bold">
                    <i class="fas fa-university mr-2"></i> Fund Source: {{ $report['source_name'] }}
                </h3>
            </div>
            
            <div class="card-body p-0">
                @foreach($report['activities'] as $act)
                    <div class="border-top p-3">
                        {{-- Activity Header with Summary Totals --}}
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <h6 class="font-weight-bold text-dark mb-1">{{ $act['details']->name }}</h6>
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
                                        <small class="d-block text-muted">Net Budget</small>
                                        <span class="font-weight-bold">₱{{ number_format($act['net_budget'], 2) }}</span>
                                    </div>
                                    <div class="col border-right">
                                        <small class="d-block text-warning">Pending</small>
                                        <span class="font-weight-bold text-warning">₱{{ number_format($act['pending'], 2) }}</span>
                                    </div>
                                    <div class="col border-right">
                                        <small class="d-block text-info">Obligated</small>
                                        <span class="font-weight-bold text-info">₱{{ number_format($act['obligated'], 2) }}</span>
                                    </div>
                                    <div class="col border-right">
                                        <small class="d-block text-success">Disbursed</small>
                                        <span class="font-weight-bold text-success">₱{{ number_format($act['disbursed'], 2) }}</span>
                                    </div>
                                    <div class="col">
                                        <small class="d-block text-primary">Unobligated</small>
                                        <span class="font-weight-bold text-primary">₱{{ number_format($act['untouched'], 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

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
                                                <td class="align-middle">{{ $tx->particulars }}</td>
                                                <td class="text-right align-middle font-weight-bold">₱{{ number_format($tx->amount, 2) }}</td>
                                                <td class="text-right align-middle text-info">₱{{ number_format($tx->obligation_amount, 2) }}</td>
                                                <td class="text-right align-middle text-success">₱{{ number_format($tx->disbursement_amount, 2) }}</td>
                                                
                                                {{-- 2. UPDATED STATUS COLUMN --}}
                                                <td class="text-center align-middle bg-light">
                                                    @if($tx->disbursement_amount > 0)
                                                        {{-- CASE 1: DISBURSED - Show badge only, no DTrack tracking --}}
                                                        <span class="badge badge-success px-2">Disbursed</span>
                                                    @elseif($tx->obligation_amount > 0)
                                                        {{-- CASE 2: OBLIGATED - Show badge + DTrack Status --}}
                                                        <span class="badge badge-info px-2">Obligated</span>
                                                        
                                                        @if($tx->remarks)
                                                            <div class="mt-1 small text-muted font-italic" style="line-height: 1.1;">
                                                                <i class="fas fa-map-marker-alt text-xs mr-1 text-info"></i>
                                                                {{ $tx->remarks }}
                                                            </div>
                                                        @endif
                                                    @else
                                                        {{-- CASE 3: PENDING - Show badge + DTrack Status --}}
                                                        <span class="badge badge-warning text-white px-2">Pending</span>
                                                        
                                                        @if($tx->remarks)
                                                            <div class="mt-1 small text-muted font-italic" style="line-height: 1.1;">
                                                                <i class="fas fa-clock text-xs mr-1 text-warning"></i>
                                                                {{ $tx->remarks }}
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
@endsection