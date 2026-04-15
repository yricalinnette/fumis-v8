@extends('layouts.adminlte')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-navy mb-0 font-weight-bold">
                <i class="fas fa-list-ul mr-2 text-muted"></i>Activity Transaction Ledger
            </h4>
            <p class="text-muted small mb-0">Detailed history for FY {{ $year }} (Line Item Logic)</p>
        </div>
    </div>

    @foreach($groupedData as $report)
        <div class="card card-navy card-outline mb-5 shadow-sm">
            <div class="card-header bg-white">
                <h3 class="card-title text-navy font-weight-bold">
                    <i class="fas fa-university mr-2"></i> {{ $report['source_name'] }}
                </h3>
            </div>
            
            <div class="card-body p-0">
                @foreach($report['activities'] as $act)
                    <div class="border-top p-3">
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
                                        <small class="d-block text-primary">Untouched</small>
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
                                            <th class="pl-3">Date</th>
                                            <th>DTrack No.</th>
                                            <th>Particulars</th>
                                            <th class="text-right">Amount</th>
                                            <th class="text-right">Obligated</th>
                                            <th class="text-right">Disbursed</th>
                                            <th class="text-center">Status</th>
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
                                                <td class="text-center align-middle">
                                                    @if($tx->disbursement_amount > 0)
                                                        <span class="badge badge-success">Disbursed</span>
                                                    @elseif($tx->obligation_amount > 0)
                                                        <span class="badge badge-info">Obligated</span>
                                                    @else
                                                        <span class="badge badge-warning text-white">Pending</span>
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