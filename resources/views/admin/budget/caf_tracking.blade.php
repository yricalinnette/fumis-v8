@extends('layouts.adminlte')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">CAF Tracking & Savings Monitoring</h3>
            <p class="text-muted small mb-0">Overview of certified availability of funds and actual procurement savings per transaction and item breakdown.</p>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-primary text-white p-3 rounded-3">
                <small class="text-white-50 text-uppercase fw-bold">Total Allotment</small>
                <h3 class="fw-bold mb-0">₱{{ number_format($transactions->sum('abc_allotment'), 2) }}</h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-warning text-dark p-3 rounded-3">
                <small class="text-dark-50 text-uppercase fw-bold">Unobligated PR (Tagged as CAF)</small>
                <h3 class="fw-bold mb-0">₱{{ number_format($unobligatedCafTotal ?? 0, 2) }}</h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-info text-white p-3 rounded-3">
                <small class="text-white-50 text-uppercase fw-bold">Total PO Obligation</small>
                <h3 class="fw-bold mb-0">₱{{ number_format($transactions->sum('po_amount'), 2) }}</h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-success text-white p-3 rounded-3">
                <small class="text-white-50 text-uppercase fw-bold">Grand Total Savings</small>
                <h3 class="fw-bold mb-0">₱{{ number_format($grandTotalSavings ?? 0, 2) }}</h3>
            </div>
        </div>
    </div>

    <!-- CAF Table List -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary text-uppercase text-xs">
                        <tr>
                            <th class="ps-3">PR / Transaction Name</th>
                            <th>Status</th>
                            <th>Fund Source / Activity</th>
                            <th>End User</th>
                            <th>PO Details</th>
                            <th>ABC / Allotment</th>
                            <th>PO Obligation</th>
                            <th>Total Savings</th>
                            <th>Breakdown</th>
                            <th>Tagged Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $trx)
                            @php
                                $hasItems = $trx->items && $trx->items->count() > 0;
                                
                                // Determine transaction status based on items' po_amount presence
                                $isAllCaf = true;
                                $isAllPo = true;
                                
                                if ($hasItems) {
                                    foreach($trx->items as $i) {
                                        if (is_null($i->po_amount)) {
                                            $isAllPo = false;
                                        } else {
                                            $isAllCaf = false;
                                        }
                                    }
                                    $trxSavings = $trx->items->sum(function($i) {
                                        return !is_null($i->po_amount) ? max(0, $i->item_amount - $i->po_amount) : 0;
                                    });
                                } else {
                                    if (is_null($trx->po_amount)) {
                                        $isAllPo = false;
                                    } else {
                                        $isAllCaf = false;
                                    }
                                    $trxSavings = !is_null($trx->po_amount) ? max(0, $trx->abc_allotment - $trx->po_amount) : 0;
                                }
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">{{ $trx->item_name ?? 'N/A' }}</div>
                                    @if($trx->pr_number)
                                        <small class="text-muted">PR No: {{ $trx->pr_number }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($isAllPo)
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1">Tagged as PO</span>
                                    @elseif($isAllCaf)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1">Tagged as CAF</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1">Partially tagged (CAF / PO)</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-secondary mb-1">{{ $trx->fundSource->name ?? 'N/A' }}</span>
                                    <div class="small text-muted">{{ $trx->activity->name ?? 'N/A' }}</div>
                                </td>
                                <td>{{ $trx->end_user ?? 'N/A' }}</td>
                                
                                <!-- PO Details Column -->
                                <td class="text-center align-middle">
                                    @php
                                        $uniquePoNumbers = $trx->unique_po_numbers;
                                        $uniqueDtracks = $trx->unique_po_dtracks;
                                        $isFullyObligated = $trx->is_fully_obligated;
                                    @endphp

                                    @if($isFullyObligated && ($uniquePoNumbers->isNotEmpty() || $uniqueDtracks->isNotEmpty()))
                                        <div class="d-flex flex-column gap-1 text-start ps-2">
                                            <div>
                                                <span class="text-xs fw-bold text-muted text-uppercase">PO No:</span>
                                                <span class="fw-semibold text-dark font-monospace">{{ $uniquePoNumbers->join(', ') }}</span>
                                            </div>
                                            <div>
                                                <span class="text-xs fw-bold text-muted text-uppercase">DTrack:</span>
                                                <span class="fw-semibold text-primary font-monospace">{{ $uniqueDtracks->join(', ') }}</span>
                                            </div>
                                        </div>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 fw-semibold text-nowrap">
                                            Pending PO
                                        </span>
                                    @endif
                                </td>

                                <td class="fw-semibold">₱{{ number_format($trx->abc_allotment, 2) }}</td>
                                <td class="fw-bold text-primary">₱{{ number_format($trx->po_amount ?? 0, 2) }}</td>
                                <td class="fw-bold text-success">₱{{ number_format($trxSavings, 2) }}</td>
                                
                                <!-- Breakdown Column Action Button -->
                                <td>
                                    @if($hasItems)
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary shadow-none" 
                                                data-toggle="collapse" 
                                                data-target="#items-collapse-{{ $trx->id }}"
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#items-collapse-{{ $trx->id }}"
                                                aria-expanded="false" 
                                                aria-controls="items-collapse-{{ $trx->id }}">
                                            <i class="fas fa-list me-1"></i> {{ $trx->items->count() }} Item(s)
                                        </button>
                                    @else
                                        <span class="text-muted small">Single Item</span>
                                    @endif
                                </td>
                                <td><small>{{ $trx->caf_date ? \Carbon\Carbon::parse($trx->caf_date)->format('M d, Y') : 'N/A' }}</small></td>
                            </tr>

                            <!-- Collapsible Row for Items Breakdown -->
                            @if($hasItems)
                                <tr class="p-0">
                                    <td colspan="10" class="p-0 border-0">
                                        <div class="collapse p-3 bg-light" id="items-collapse-{{ $trx->id }}">
                                            <div class="card border shadow-sm rounded-3 mb-0">
                                                <div class="card-header bg-white py-2">
                                                    <h6 class="fw-bold mb-0 text-dark small">
                                                        <i class="fas fa-boxes text-primary me-2"></i>Itemized Breakdown & Status for {{ $trx->item_name }}
                                                    </h6>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered align-middle mb-0 text-sm">
                                                        <thead class="bg-light text-secondary text-uppercase text-xs">
                                                            <tr>
                                                                <th class="ps-3">Item Particulars</th>
                                                                <th>Item Status</th>
                                                                <th>PO Number</th>
                                                                <th>PO DTrack</th>
                                                                <th class="text-end">ABC Amount</th>
                                                                <th class="text-end">PO Amount</th>
                                                                <th class="text-end pe-3">Savings</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($trx->items as $subItem)
                                                                @php
                                                                    $itemSavings = !is_null($subItem->po_amount) ? max(0, $subItem->item_amount - $subItem->po_amount) : 0;
                                                                    $isItemCaf = is_null($subItem->po_amount);
                                                                @endphp
                                                                <tr>
                                                                    <td class="fw-semibold text-dark ps-3">{{ $subItem->pr_item_name }}</td>
                                                                    <td>
                                                                        @if($isItemCaf)
                                                                            <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace text-xs">Tagged as CAF</span>
                                                                        @else
                                                                            <span class="badge bg-info-subtle text-info border border-info-subtle font-monospace text-xs">Tagged as PO</span>
                                                                        @endif
                                                                    </td>
                                                                    <td>
                                                                        @if($subItem->po_number)
                                                                            <span class="badge bg-info-subtle text-info border border-info-subtle font-monospace">{{ $subItem->po_number }}</span>
                                                                        @else
                                                                            <span class="text-muted text-xs">Unassigned</span>
                                                                        @endif
                                                                    </td>
                                                                    <td>{{ $subItem->po_dtrack_no ?? 'N/A' }}</td>
                                                                    <td class="text-end text-muted">₱{{ number_format($subItem->item_amount, 2) }}</td>
                                                                    <td class="text-end fw-bold text-primary">
                                                                        {{ !is_null($subItem->po_amount) ? '₱' . number_format($subItem->po_amount, 2) : 'Pending (CAF)' }}
                                                                    </td>
                                                                    <td class="text-end fw-bold text-success pe-3">₱{{ number_format($itemSavings, 2) }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">No CAF or PO tagged transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection