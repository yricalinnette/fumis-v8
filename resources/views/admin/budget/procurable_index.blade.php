@extends('layouts.adminlte')

@push('css')
<!-- Select2 CSS & Custom Table/Form Overrides -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    /* Prevent text wrapping on buttons and badges */
    .text-nowrap { white-space: nowrap !important; }
    
    /* Equalize Control Heights */
    .custom-filter-input, 
    .custom-filter-btn, 
    .select2-container--bootstrap-5 .select2-selection {
        height: 38px !important;
        min-height: 38px !important;
        border-radius: 0.375rem !important;
    }

    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        line-height: 24px !important;
        color: #333 !important;
        font-size: 0.875rem;
    }

    /* Table visual refinements */
    .table-custom th {
        background-color: #f8f9fa;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
    }
    .table-custom td {
        vertical-align: middle;
        padding: 0.85rem 0.75rem;
    }

    /* Pagination Alignment Fix */
    .custom-pagination .pagination {
        margin-bottom: 0 !important;
        gap: 2px;
    }
    .custom-pagination .page-item .page-link {
        border-radius: 0.375rem !important;
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
        color: #495057;
        box-shadow: none !important;
    }
    .custom-pagination .page-item.active .page-link {
        background-color: #0d6efd !important;
        border-color: #0d6efd !important;
        color: #ffffff !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">
                <i class="fas fa-boxes text-primary me-2"></i>Procurable Fund Transactions
            </h4>
            <p class="text-muted small mb-0">Monitor and process activities/funds flagged for procurement and CAF certification.</p>
        </div>
        <div>
            <a href="{{ route('budget.caf.tracking') }}" class="btn btn-outline-primary btn-sm shadow-sm rounded-2 text-nowrap">
                <i class="fas fa-file-signature me-1"></i> CAF Tracking Dashboard
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="close btn-close" data-dismiss="alert" data-bs-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Filter & Search Toolbar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3 bg-white rounded-3">
            <form action="{{ route('budget.procurable.index') }}" method="GET" id="procurableFilterForm">
                <div class="row g-3 align-items-end">
                    
                    <!-- Search Keyword Input -->
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label text-xs fw-bold text-uppercase text-secondary mb-1">
                            <i class="fas fa-search me-1 text-primary"></i> Search Keyword
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted pe-2">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0 bg-light" placeholder="Search DTrack No., Particulars, Creditor..." value="{{ request('search') }}">
                        </div>
                    </div>

                    <!-- Clean & Robust Section Dropdown -->
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label text-xs fw-bold text-uppercase text-secondary mb-1">
                            <i class="fas fa-building me-1 text-primary"></i> Requesting Section
                        </label>
                        <select name="section_id" id="section_select" class="form-select bg-light text-dark fw-normal w-100" style="height: 38px;" onchange="document.getElementById('procurableFilterForm').submit()">
                            <option value="">-- All Requesting Sections --</option>
                            @foreach($allSections as $secId => $secName)
                                <option value="{{ $secId }}" {{ request('section_id') == $secId ? 'selected' : '' }}>
                                    {{ $secName }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Status Filter Dropdown -->
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label text-xs fw-bold text-uppercase text-secondary mb-1">
                            <i class="fas fa-tasks me-1 text-primary"></i> Status Filter
                        </label>
                        <select name="caf_status" class="form-select bg-light text-dark fw-normal w-100" style="height: 38px;" onchange="document.getElementById('procurableFilterForm').submit()">
                            <option value="">-- All Statuses --</option>
                            <option value="Tagged as CAF" {{ request('caf_status') == 'Tagged as CAF' ? 'selected' : '' }}>Tagged as CAF</option>
                            <option value="Tagged as PO" {{ request('caf_status') == 'Tagged as PO' ? 'selected' : '' }}>Tagged as PO</option>
                        </select>
                    </div>

                    <!-- Actions -->
                    <div class="col-lg-3 col-md-6 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100 fw-semibold shadow-sm d-inline-flex align-items-center justify-content-center" style="height: 38px;">
                            <i class="fas fa-filter me-2"></i> Apply Filter
                        </button>
                        @if(request('search') || request('section_id') || request('caf_status'))
                            <a href="{{ route('budget.procurable.index') }}" class="btn btn-outline-secondary w-50 d-inline-flex align-items-center justify-content-center shadow-sm" style="height: 38px;" title="Reset Filters">
                                <i class="fas fa-redo me-1"></i> Reset
                            </a>
                        @endif
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-custom">
                <thead class="text-uppercase text-muted fw-bold">
                    <tr>
                        <th class="ps-4" style="width: 140px;">DTrack No.</th>
                        <th>Particulars & Creditor</th>
                        <th class="text-center" style="width: 180px;">Fund Source</th>
                        <th class="text-end" style="width: 140px;">Amount</th>
                        <th class="text-center" style="width: 150px;">CAF Status</th>
                        <th class="text-end pe-4" style="width: 150px;">Action</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    @forelse($procurableFunds as $fund)
    @php
        // Get the specific PR/CAF transaction linked precisely to this unique fund record ID
        $cafTransaction = $fund->prTransactions->first(); 
        $hasCaf = !is_null($cafTransaction);
        $hasPo = $hasCaf && ($cafTransaction->caf_status === 'Tagged as PO' || !empty($cafTransaction->po_number));
    @endphp
    <tr>
        <!-- DTrack No. -->
        <td class="ps-4">
            <span class="badge bg-light text-primary border border-primary-subtle fw-bold px-2 py-1 text-nowrap fs-12">
                {{ $fund->dtrack_no }}
            </span>
        </td>

        <!-- Particulars & Creditor -->
        <td>
            <div class="fw-semibold text-dark text-break mb-1">{{ $fund->particulars }}</div>
            <span class="text-muted small"><i class="far fa-user me-1"></i>{{ $fund->creditor ?? 'N/A' }}</span>
        </td>

        <!-- Fund Source -->
        <td class="text-center">
            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 text-nowrap">
                {{ $fund->fundSource->name ?? 'N/A' }}
            </span>
        </td>

        <!-- Amount -->
        <td class="text-end fw-bold text-dark text-nowrap">
            ₱{{ number_format($fund->amount, 2) }}
        </td>

        <!-- Status Column -->
        <td class="text-center">
            @if($hasPo)
                <!-- Tagged as PO -->
                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3 fw-semibold text-nowrap">
                    <i class="fas fa-check-double me-1"></i> Tagged as PO
                </span>
            @elseif($hasCaf)
                <!-- Tagged as CAF -->
                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 fw-semibold text-nowrap">
                    <i class="fas fa-check-circle me-1"></i> Tagged as CAF
                </span>
            @else
                <!-- Pending CAF -->
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 fw-semibold text-nowrap">
                    <i class="fas fa-clock me-1"></i> Pending CAF
                </span>
            @endif
        </td>

        <!-- Action Column -->
        <td class="text-end pe-4" style="width: 140px;">
            @if($fund->activity)
                @if($hasCaf)
                    <div class="d-flex flex-column gap-1 align-items-end ms-auto" style="max-width: 130px;">
                        <!-- Edit CAF Details Button -->
                        <button class="btn btn-sm btn-outline-warning shadow-sm px-2 w-100 text-nowrap" 
                                data-toggle="modal" 
                                data-target="#editCafModal-{{ $cafTransaction->id }}"
                                data-bs-toggle="modal" 
                                data-bs-target="#editCafModal-{{ $cafTransaction->id }}"
                                title="Edit CAF Details">
                            <i class="fas fa-edit me-1"></i> Edit CAF
                        </button>

                        @if($hasPo)
                            <!-- EDIT PO BUTTON -->
                            <button class="btn btn-sm btn-outline-info shadow-sm px-2 w-100 text-nowrap" 
                                    data-toggle="modal" 
                                    data-target="#addPoModal-{{ $fund->id }}"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#addPoModal-{{ $fund->id }}"
                                    title="Edit PO Details">
                                <i class="fas fa-edit me-1"></i> Edit PO
                            </button>
                        @else
                            <!-- ADD PO BUTTON -->
                            <button class="btn btn-sm btn-outline-primary shadow-sm px-2 w-100 text-nowrap" 
                                    data-toggle="modal" 
                                    data-target="#addPoModal-{{ $fund->id }}"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#addPoModal-{{ $fund->id }}"
                                    title="Add PO Details">
                                <i class="fas fa-file-contract me-1"></i> Add PO
                            </button>
                        @endif
                    </div>
                @else
                    <!-- Add PR Item Button -->
                    <button class="btn btn-sm btn-primary shadow-sm px-3 text-nowrap ms-auto" 
                            data-toggle="modal" 
                            data-target="#tagCafModal-{{ $fund->id }}"
                            data-bs-toggle="modal" 
                            data-bs-target="#tagCafModal-{{ $fund->id }}">
                        <i class="fas fa-plus-circle me-1"></i> Add PR Item
                    </button>
                @endif
            @else
                <button class="btn btn-sm btn-light text-muted border text-nowrap" disabled>No Activity</button>
            @endif
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6" class="text-center py-5 text-muted">
            <i class="fas fa-inbox fa-2x mb-3 text-black-50 d-block"></i>
            <span>No procurable fund transactions found matching your criteria.</span>
        </td>
    </tr>
@endforelse
                </tbody>
            </table>
        </div>

        @if($procurableFunds->hasPages())
            <div class="card-footer bg-white border-top-0 d-flex flex-column flex-md-row justify-content-between align-items-center py-3 gap-2">
                <span class="text-muted small">
                    Showing <span class="fw-semibold text-dark">{{ $procurableFunds->firstItem() }}</span> 
                    to <span class="fw-semibold text-dark">{{ $procurableFunds->lastItem() }}</span> 
                    of <span class="fw-semibold text-dark">{{ $procurableFunds->total() }}</span> records
                </span>
                <div class="custom-pagination">
                    {{ $procurableFunds->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @endif
    </div>
</div>

<!-- MODALS SECTION -->
@foreach($procurableFunds as $fund)
    @if($fund->activity)
        @php
            // UPDATE THIS LINE: Allow both 'Tagged as CAF' and 'Tagged as PO'
            $cafTransaction = $fund->activity->procurementTransactions
                ->whereIn('caf_status', ['Tagged as CAF', 'Tagged as PO'])
                ->first();
        @endphp

        <!-- ------------------------------------------------------------- -->
        <!-- MODAL 1: Tag CAF & Create PR Item (When not yet tagged as CAF) -->
        <!-- ------------------------------------------------------------- -->
        @if(!$cafTransaction)
            <div class="modal fade" id="tagCafModal-{{ $fund->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                    <div class="modal-content border-0 shadow">
                        <form action="{{ route('budget.transaction.store', $fund->activity->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="source_of_fund_id" value="{{ $fund->source_of_fund_id }}">
                            <input type="hidden" name="fund_transaction_id" value="{{ $fund->id }}">
                            
                            <div class="modal-header bg-light border-bottom-0 py-3 d-flex align-items-center justify-content-between">
                                <h5 class="modal-title fw-bold text-dark mb-0">
                                    <i class="fas fa-file-contract text-primary me-2"></i>Add PR Item — <span class="text-primary">{{ $fund->dtrack_no }}</span>
                                </h5>
                                <button type="button" class="btn btn-sm btn-light text-muted border-0 rounded-circle shadow-none px-2 py-1" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                                    <i class="fas fa-times fs-5"></i>
                                </button>
                            </div>
                            
                            <div class="modal-body p-4 text-start">
                                <!-- Readonly Reference & CAF Date Block -->
                                <div class="p-3 bg-light rounded-3 border mb-4">
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-3">
                                            <label class="form-label text-xs fw-bold text-uppercase text-secondary mb-1">DTrack No.</label>
                                            <input type="text" name="dtrack_no" class="form-control form-control-sm bg-white fw-bold text-primary" value="{{ $fund->dtrack_no }}" readonly>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label text-xs fw-bold text-uppercase text-secondary mb-1">Function / Classification</label>
                                            <input type="text" class="form-control form-control-sm bg-white text-dark" value="{{ $fund->activity->classification ?? 'N/A' }}" readonly>
                                        </div>
                                        <!-- NEW CAF DATE FIELD -->
                                        <div class="col-md-4">
                                            <label class="form-label text-xs fw-bold text-uppercase text-primary mb-1">
                                                <i class="far fa-calendar-alt me-1"></i> CAF Date
                                            </label>
                                            <input type="date" name="caf_date" class="form-control form-control-sm border-primary-subtle fw-semibold" value="{{ date('Y-m-d') }}" required>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label text-xs fw-bold text-uppercase text-secondary mb-1">Activity Name</label>
                                        <textarea class="form-control form-control-sm bg-white text-dark" rows="2" style="resize: none;" readonly>{{ $fund->activity->name ?? 'N/A' }}</textarea>
                                    </div>
                                    <div>
                                        <label class="form-label text-xs fw-bold text-uppercase text-secondary mb-1">PR / Transaction Name</label>
                                        <textarea name="item_name" class="form-control form-control-sm bg-white text-dark fw-semibold" rows="2" style="resize: none;" readonly>{{ $fund->particulars }}</textarea>
                                    </div>
                                </div>

                                <!-- Input Fields -->
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted small mb-1">End User / Requesting Section</label>
                                        <input type="text" name="end_user" class="form-control" placeholder="e.g. ICTU">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted small mb-1">ABC / Approved Allotment (₱)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">₱</span>
                                            <input type="number" step="0.01" name="abc_allotment" class="form-control" value="{{ $fund->amount }}" required>
                                        </div>
                                    </div>
                                    <!-- NEW PR NUMBER FIELD -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted small mb-1">PR Number</label>
                                        <input type="text" name="pr_number" class="form-control" placeholder="e.g. PR-2026-08-001">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label fw-semibold text-muted small mb-1">Remarks</label>
                                        <textarea name="remarks" class="form-control" rows="2" placeholder="Enter optional notes or remarks..."></textarea>
                                    </div>
                                </div>

                                <!-- Optional Items Breakdown -->
                                <div class="p-3 bg-light rounded-3 border mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark d-inline-block">
                                                <i class="fas fa-list-ul me-2 text-primary"></i>Procurement Items Breakdown
                                            </h6>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-2">Optional</span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-success shadow-none add-item-btn">
                                            <i class="fas fa-plus me-1"></i> Add Item
                                        </button>
                                    </div>

                                    <!-- Items Dynamic List -->
                                    <div class="items-container">
                                        @if(isset($cafTransaction) && $cafTransaction->items && $cafTransaction->items->count() > 0)
                                            @foreach($cafTransaction->items as $index => $item)
                                                <div class="row g-2 mb-2 item-row">
                                                    <div class="col-md-7">
                                                        <input type="text" name="items[{{ $index }}][name]" class="form-control form-control-sm bg-white" placeholder="Item Description" value="{{ $item->pr_item_name }}" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text bg-white">₱</span>
                                                            <input type="number" step="0.01" name="items[{{ $index }}][amount]" class="form-control form-control-sm bg-white pr-item-amt" placeholder="Amount" value="{{ $item->item_amount }}" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-1">
                                                        <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-item-btn"><i class="fas fa-trash"></i></button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>

                                    <!-- Live Total & Validation Indicator Footer -->
                                    <div class="p-2 bg-white rounded border mt-2 d-flex justify-content-between align-items-center flex-wrap gap-2 item-total-bar">
                                        <div class="text-secondary small">
                                            <span>Total Items Sum: </span>
                                            <strong class="text-dark fs-6">₱<span class="items-sum-display">0.00</span></strong>
                                        </div>
                                        <div>
                                            <span class="badge bg-secondary-subtle text-secondary border match-status-badge">No Items Added</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer bg-light border-top-0 py-3">
                                <button type="button" class="btn btn-light border px-4" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success px-4"><i class="fas fa-check-circle me-1"></i> Save & Tag as CAF</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <!-- ------------------------------------------------------------- -->
        <!-- MODAL 2: Add / Edit PO Details                                -->
        <!-- ------------------------------------------------------------- -->
        @if($cafTransaction)
        @php
            $isEditPo = !empty($cafTransaction->po_number) || $cafTransaction->caf_status === 'Tagged as PO';
        @endphp

        <div class="modal fade" id="addPoModal-{{ $fund->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content border-0 shadow">
                    <form action="{{ route('budget.transaction.updatePo', $cafTransaction->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <!-- Header -->
                        <div class="modal-header bg-light border-bottom-0 py-3 d-flex align-items-center justify-content-between">
                            <h5 class="modal-title fw-bold text-dark mb-0">
                                <i class="fas {{ $isEditPo ? 'fa-edit text-info' : 'fa-file-contract text-primary' }} me-2"></i>
                                {{ $isEditPo ? 'Edit PO Details' : 'Add PO Details' }} — <span class="text-primary">{{ $fund->dtrack_no }}</span>
                            </h5>
                            <button type="button" class="btn btn-sm btn-light text-muted border-0 rounded-circle shadow-none px-2 py-1" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                                <i class="fas fa-times fs-5"></i>
                            </button>
                        </div>

                        <div class="modal-body p-4 text-start">
                            <!-- Reference Block -->
                            <div class="p-3 bg-light rounded-3 border mb-4">
                                <div class="row g-2 mb-2">
                                    <div class="col-md-3">
                                        <label class="form-label text-xs fw-bold text-uppercase text-secondary mb-1">DTrack No.</label>
                                        <input type="text" class="form-control form-control-sm bg-white fw-bold text-primary" value="{{ $fund->dtrack_no }}" readonly>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label text-xs fw-bold text-uppercase text-secondary mb-1">Function / Classification</label>
                                        <input type="text" class="form-control form-control-sm bg-white text-dark" value="{{ $fund->activity->classification ?? 'N/A' }}" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label text-xs fw-bold text-uppercase text-secondary mb-1">
                                            <i class="far fa-calendar-alt me-1"></i> CAF Date
                                        </label>
                                        <input type="date" class="form-control form-control-sm bg-white fw-semibold" value="{{ $cafTransaction->caf_date ? \Carbon\Carbon::parse($cafTransaction->caf_date)->format('Y-m-d') : '' }}" readonly>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label text-xs fw-bold text-uppercase text-secondary mb-1">Activity Name</label>
                                    <textarea class="form-control form-control-sm bg-white text-dark" rows="2" style="resize: none;" readonly>{{ $fund->activity->name ?? 'N/A' }}</textarea>
                                </div>
                                <div>
                                    <label class="form-label text-xs fw-bold text-uppercase text-secondary mb-1">PR / Transaction Name</label>
                                    <textarea class="form-control form-control-sm bg-white text-dark fw-semibold" rows="2" style="resize: none;" readonly>{{ $cafTransaction->item_name }}</textarea>
                                </div>
                            </div>

                            <!-- PR Info (Readonly) -->
                            <div class="row g-3 mb-3">
                                <div class="{{ ($cafTransaction->items && $cafTransaction->items->count() > 0) ? 'col-md-6' : 'col-md-4' }}">
                                    <label class="form-label fw-semibold text-muted small mb-1">End User / Requesting Section</label>
                                    <input type="text" class="form-control bg-light" value="{{ $cafTransaction->end_user }}" readonly>
                                </div>

                                <div class="{{ ($cafTransaction->items && $cafTransaction->items->count() > 0) ? 'col-md-6' : 'col-md-4' }}">
                                    <label class="form-label fw-semibold text-muted small mb-1">ABC / Approved Allotment (₱)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">₱</span>
                                        <input type="number" step="0.01" class="form-control bg-light" value="{{ $cafTransaction->abc_allotment }}" readonly>
                                    </div>
                                </div>

                                @if(!$cafTransaction->items || $cafTransaction->items->count() === 0)
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-muted small mb-1">PR Number</label>
                                        <input type="text" class="form-control bg-light" value="{{ $cafTransaction->pr_number ?? 'N/A' }}" readonly>
                                    </div>
                                @endif
                            </div>

                            @php
                                $hasItems = $cafTransaction->items && $cafTransaction->items->count() > 0;
                            @endphp

                            <!-- SINGLE ITEM PO IDENTIFIERS -->
                            @if(!$hasItems)
                                <div class="col-md-12 mb-3">
                                    <div class="p-3 rounded-3 border border-primary border-2 bg-primary-subtle bg-opacity-10 shadow-sm">
                                        <label class="form-label fw-bold text-primary mb-2 d-flex align-items-center justify-content-between">
                                            <span><i class="fas fa-file-contract me-1 fs-6"></i> Purchase Order (PO) Details</span>
                                            <span class="badge bg-primary text-white font-monospace px-2 py-1">Single Item PO</span>
                                        </label>
                                        
                                        <div class="row g-3">
                                            <!-- PO Number -->
                                            <div class="col-md-3">
                                                <label class="form-label fw-bold text-dark small mb-1">
                                                    <i class="fas fa-hashtag text-primary me-1"></i> PO Number
                                                </label>
                                                <input type="text" 
                                                    name="po_number" 
                                                    class="form-control border-primary fw-semibold" 
                                                    placeholder="e.g. 001" 
                                                    value="{{ $cafTransaction->po_number ?? '' }}" 
                                                    required autofocus>
                                            </div>

                                            <!-- PO Date -->
                                            <div class="col-md-3">
                                                <label class="form-label fw-bold text-dark small mb-1">
                                                    <i class="far fa-calendar-alt text-primary me-1"></i> PO Date
                                                </label>
                                                <input type="date" 
                                                    name="po_date" 
                                                    class="form-control border-primary fw-semibold" 
                                                    value="{{ $cafTransaction->po_date ? \Carbon\Carbon::parse($cafTransaction->po_date)->format('Y-m-d') : '' }}">
                                            </div>

                                            <!-- PO DTrack Number -->
                                            <div class="col-md-3">
                                                <label class="form-label fw-bold text-dark small mb-1">
                                                    <i class="fas fa-barcode text-primary me-1"></i> PO DTrack No.
                                                </label>
                                                <input type="text" 
                                                    name="po_dtrack_no" 
                                                    class="form-control border-primary fw-semibold" 
                                                    placeholder="e.g. 2026-000001" 
                                                    value="{{ $cafTransaction->po_dtrack_no ?? '' }}">
                                            </div>

                                            <!-- PO Amount -->
                                            <div class="col-md-3">
                                                <label class="form-label fw-bold text-dark small mb-1">
                                                    <i class="fas fa-coins text-primary me-1"></i> Actual PO Amount (₱)
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-white border-primary text-primary fw-bold">₱</span>
                                                    <input type="number" 
                                                        step="0.01" 
                                                        name="po_amount" 
                                                        class="form-control border-primary fw-bold text-dark" 
                                                        placeholder="0.00" 
                                                        value="{{ $cafTransaction->po_amount ?? $cafTransaction->abc_allotment }}" 
                                                        required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- MULTI-ITEM PO BREAKDOWN TABLE -->
                            @if($hasItems)
                                <div class="col-md-12 mb-3">
                                    <div class="card border shadow-sm rounded-3 overflow-hidden">
                                        <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark small"><i class="fas fa-list text-primary me-1"></i> Itemized PO Award & Identifier Breakdown</span>
                                            <span class="badge bg-secondary-subtle text-secondary border">
                                                ABC Total: ₱{{ number_format($cafTransaction->abc_allotment, 2) }}
                                            </span>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover align-middle mb-0">
                                                <thead class="bg-light text-secondary text-xs text-uppercase">
                                                    <tr>
                                                        <th class="ps-3" style="min-width: 160px;">Item Particulars</th>
                                                        <th style="width: 130px;">Item PO No.</th>
                                                        <th style="width: 140px;">PO Date</th>
                                                        <th style="width: 130px;">PO DTrack No.</th>
                                                        <th class="text-end" style="width: 110px;">ABC Amount</th>
                                                        <th class="text-end pe-3" style="width: 130px;">PO Amount (₱)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($cafTransaction->items as $item)
                                                        <tr>
                                                            <td class="ps-3">
                                                                <div class="fw-semibold text-dark">{{ $item->pr_item_name }}</div>
                                                                @if($item->pr_number)
                                                                    <small class="text-muted">PR No: {{ $item->pr_number }}</small>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <input type="text" 
                                                                    name="items[{{ $item->id }}][po_number]" 
                                                                    class="form-control form-control-sm" 
                                                                    placeholder="e.g. 001" 
                                                                    value="{{ $item->po_number ?? '' }}">
                                                            </td>
                                                            <td>
                                                                <input type="date" 
                                                                    name="items[{{ $item->id }}][po_date]" 
                                                                    class="form-control form-control-sm" 
                                                                    value="{{ $item->po_date ? \Carbon\Carbon::parse($item->po_date)->format('Y-m-d') : '' }}">
                                                            </td>
                                                            <td>
                                                                <input type="text" 
                                                                    name="items[{{ $item->id }}][po_dtrack_no]" 
                                                                    class="form-control form-control-sm" 
                                                                    placeholder="e.g. 2026-000001" 
                                                                    value="{{ $item->po_dtrack_no ?? '' }}">
                                                            </td>
                                                            <td class="text-end text-muted small">₱{{ number_format($item->item_amount, 2) }}</td>
                                                            <td class="pe-3">
                                                                <div class="input-group input-group-sm">
                                                                    <span class="input-group-text bg-white fw-bold text-primary">₱</span>
                                                                    <input type="number" 
                                                                        step="0.01" 
                                                                        name="items[{{ $item->id }}][po_amount]" 
                                                                        class="form-control form-control-sm text-end fw-bold po-item-input" 
                                                                        placeholder="0.00" 
                                                                        value="{{ $item->po_amount !== null ? $item->po_amount : '' }}" 
                                                                        oninput="calculateTotalPoAmount(this)">
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot class="bg-light fw-bold border-top">
                                                    <tr>
                                                        <td colspan="5" class="text-end text-uppercase text-secondary pe-2">Total PO Amount (Obligation):</td>
                                                        <td class="text-end pe-3 text-primary fs-6">
                                                            ₱<span class="total-po-display">{{ number_format($cafTransaction->po_amount ?? $cafTransaction->abc_allotment, 2) }}</span>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="mb-0">
                                <label class="form-label fw-semibold text-muted small mb-1">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="2" placeholder="Enter optional notes or remarks...">{{ $cafTransaction->remarks }}</textarea>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="modal-footer bg-light border-top-0 py-3">
                            <button type="button" class="btn btn-light border px-4" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn {{ $isEditPo ? 'btn-info text-white' : 'btn-primary' }} px-4">
                                <i class="fas fa-save me-1"></i> {{ $isEditPo ? 'Update PO Details' : 'Save PO Details' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        <!-- ------------------------------------------------------------- -->
        <!-- MODAL 3: Edit CAF Details (When already tagged as CAF)        -->
        <!-- ------------------------------------------------------------- -->
        @if($cafTransaction)
            <!-- Changed ID from $fund->id to $cafTransaction->id to match button triggers -->
            <div class="modal fade" id="editCafModal-{{ $cafTransaction->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                    <div class="modal-content border-0 shadow">
                        <form action="{{ route('budget.transaction.update', $cafTransaction->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="fund_transaction_id" value="{{ $fund->id }}">

                            <div class="modal-header bg-light border-bottom-0 py-3 d-flex align-items-center justify-content-between">
                                <h5 class="modal-title fw-bold text-dark mb-0">
                                    <i class="fas fa-edit text-warning me-2"></i>Edit CAF Transaction — <span class="text-primary">{{ $fund->dtrack_no }}</span>
                                </h5>
                                <button type="button" class="btn btn-sm btn-light text-muted border-0 rounded-circle shadow-none px-2 py-1" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                                    <i class="fas fa-times fs-5"></i>
                                </button>
                            </div>

                            <div class="modal-body p-4 text-start">
                                <!-- Readonly Reference & Editable CAF Date Block -->
                                <div class="p-3 bg-light rounded-3 border mb-4">
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-3">
                                            <label class="form-label text-xs fw-bold text-uppercase text-secondary mb-1">DTrack No.</label>
                                            <input type="text" class="form-control form-control-sm bg-white fw-bold text-primary" value="{{ $fund->dtrack_no }}" readonly>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label text-xs fw-bold text-uppercase text-secondary mb-1">Function / Classification</label>
                                            <input type="text" class="form-control form-control-sm bg-white text-dark" value="{{ $fund->activity->classification ?? 'N/A' }}" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-xs fw-bold text-uppercase text-primary mb-1">
                                                <i class="far fa-calendar-alt me-1"></i> CAF Date
                                            </label>
                                            <input type="date" 
                                                name="caf_date" 
                                                class="form-control form-control-sm border-primary-subtle fw-semibold" 
                                                value="{{ $cafTransaction->caf_date ? \Carbon\Carbon::parse($cafTransaction->caf_date)->format('Y-m-d') : date('Y-m-d') }}" 
                                                required>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label text-xs fw-bold text-uppercase text-secondary mb-1">Activity Name</label>
                                        <textarea class="form-control form-control-sm bg-white text-dark" rows="2" style="resize: none;" readonly>{{ $fund->activity->name ?? 'N/A' }}</textarea>
                                    </div>
                                    <div>
                                        <label class="form-label text-xs fw-bold text-uppercase text-secondary mb-1">PR / Transaction Name</label>
                                        <textarea class="form-control form-control-sm bg-white text-dark fw-semibold" rows="2" style="resize: none;" readonly>{{ $cafTransaction->item_name }}</textarea>
                                    </div>
                                </div>

                                <!-- Editable PR / CAF Fields -->
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted small mb-1">End User / Requesting Section</label>
                                        <input type="text" name="end_user" class="form-control" value="{{ $cafTransaction->end_user }}">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted small mb-1">ABC / Approved Allotment (₱)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">₱</span>
                                            <input type="number" step="0.01" name="abc_allotment" class="form-control" value="{{ $cafTransaction->abc_allotment }}" required>
                                        </div>
                                    </div>

                                    <!-- HIDE TOP PR NUMBER IF ITEM BREAKDOWN EXISTS -->
                                    @if(!$cafTransaction->items || $cafTransaction->items->count() === 0)
                                        <div class="col-md-12">
                                            <label class="form-label fw-semibold text-muted small mb-1">PR Number</label>
                                            <input type="text" name="pr_number" class="form-control" placeholder="e.g. PR-2026-08-001" value="{{ $cafTransaction->pr_number ?? '' }}">
                                        </div>
                                    @endif

                                    <div class="col-md-12">
                                        <label class="form-label fw-semibold text-muted small mb-1">Remarks</label>
                                        <textarea name="remarks" class="form-control" rows="2" placeholder="Enter optional notes or remarks...">{{ $cafTransaction->remarks }}</textarea>
                                    </div>
                                </div>

                                <!-- Optional Procurement Items Breakdown Container -->
                                <div class="p-3 bg-light rounded-3 border mb-2">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark d-inline-block">
                                                <i class="fas fa-list-ul me-2 text-primary"></i>Procurement Items Breakdown
                                            </h6>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-2">Optional</span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-success shadow-none add-item-btn">
                                            <i class="fas fa-plus me-1"></i> Add Item
                                        </button>
                                    </div>

                                    <div class="items-container">
                                        @if($cafTransaction->items && $cafTransaction->items->count() > 0)
                                            @foreach($cafTransaction->items as $index => $item)
                                                <div class="row g-2 mb-2 item-row">
                                                    <!-- Use $item->id as the array key so the controller knows which record to update -->
                                                    <input type="hidden" name="items[{{ $item->id }}][id]" value="{{ $item->id }}">
                                                    
                                                    <div class="col-md-7">
                                                        <input type="text" name="items[{{ $item->id }}][name]" class="form-control form-control-sm bg-white" placeholder="Item Description" value="{{ $item->pr_item_name }}" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text bg-white">₱</span>
                                                            <input type="number" step="0.01" name="items[{{ $item->id }}][amount]" class="form-control form-control-sm bg-white pr-item-amt" placeholder="Amount" value="{{ $item->item_amount }}" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-1">
                                                        <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-item-btn"><i class="fas fa-trash"></i></button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer bg-light border-top-0 py-3">
                                <button type="button" class="btn btn-light border px-4" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-warning px-4 fw-semibold"><i class="fas fa-save me-1"></i> Update CAF Details</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

    @endif
@endforeach

@endsection

<script>
    document.addEventListener('click', function(e) {
        // 1. Find Add Item Button Click
        var addBtn = e.target.closest('.add-item-btn');
        if (addBtn) {
            e.preventDefault();
            
            // Traverse to parent box and locate the items container inside the active modal
            var wrapper = addBtn.closest('.p-3');
            var container = wrapper ? wrapper.querySelector('.items-container') : null;

            if (container) {
                var count = container.querySelectorAll('.item-row').length;
                var row = document.createElement('div');
                row.className = 'row g-2 mb-2 item-row';
                row.innerHTML = `
                    <div class="col-md-7">
                        <input type="text" name="items[${count}][name]" class="form-control bg-white" placeholder="Item Particulars" required>
                    </div>
                    <div class="col-md-4">
                        <input type="number" step="0.01" name="items[${count}][amount]" class="form-control bg-white" placeholder="Amount (₱)" required>
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-outline-danger w-100 remove-item-btn">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;
                container.appendChild(row);
            }
            return;
        }

        // 2. Find Remove Item Button Click
        var removeBtn = e.target.closest('.remove-item-btn');
        if (removeBtn) {
            e.preventDefault();
            var itemRow = removeBtn.closest('.item-row');
            if (itemRow) {
                itemRow.remove();
            }
        }
    });

    function calculateTotalPoAmount(input) {
        var modal = input.closest('.modal');
        var total = 0;
        
        modal.querySelectorAll('.po-item-input').forEach(function(el) {
            total += parseFloat(el.value) || 0;
        });

        var display = modal.querySelector('.total-po-display');
        if (display) {
            display.innerText = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Recalculate total items amount and update match indicator
        function updatePrItemsTotal(modal) {
            var abcInput = modal.querySelector('input[name="abc_allotment"]');
            var abcAmount = parseFloat(abcInput ? abcInput.value : 0) || 0;
            var totalSum = 0;
            var itemRows = modal.querySelectorAll('.item-row');

            itemRows.forEach(function (row) {
                // TARGET numeric input directly inside the row
                var amtInput = row.querySelector('input[type="number"]');
                if (amtInput) {
                    totalSum += parseFloat(amtInput.value) || 0;
                }
            });

            // Round to 2 decimal places to avoid floating point precision issues
            totalSum = Math.round(totalSum * 100) / 100;
            abcAmount = Math.round(abcAmount * 100) / 100;

            var sumDisplay = modal.querySelector('.items-sum-display');
            var badge = modal.querySelector('.match-status-badge');

            if (sumDisplay) {
                sumDisplay.innerText = totalSum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            if (badge) {
                if (itemRows.length === 0) {
                    badge.className = 'badge bg-secondary-subtle text-secondary border';
                    badge.innerText = 'No Items Added';
                } else if (Math.abs(totalSum - abcAmount) < 0.01) {
                    badge.className = 'badge bg-success-subtle text-success border border-success-subtle';
                    badge.innerText = '✓ Matches ABC Allotment';
                } else {
                    var diff = Math.abs(totalSum - abcAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    badge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle';
                    badge.innerText = '✕ Mismatch (Diff: ₱' + diff + ')';
                }
            }
        }

        // Trigger update when opening any modal
        document.addEventListener('show.bs.modal', function (e) {
            updatePrItemsTotal(e.target);
        });
        
        // Support Bootstrap 4 / AdminLTE modal events
        $(document).on('shown.bs.modal', '.modal', function () {
            updatePrItemsTotal(this);
        });

        // Listen to changes on inputs
        document.addEventListener('input', function (e) {
            var modal = e.target.closest('.modal');
            if (modal && (e.target.matches('.item-row input') || e.target.name === 'abc_allotment')) {
                updatePrItemsTotal(modal);
            }
        });

        // Listen to removal of item rows
        document.addEventListener('click', function (e) {
            if (e.target.closest('.remove-item-btn')) {
                var btn = e.target.closest('.remove-item-btn');
                var modal = btn.closest('.modal');
                btn.closest('.item-row').remove();
                if (modal) updatePrItemsTotal(modal);
            }
        });

        // Form submission validation guard
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                var modal = form.closest('.modal');
                if (!modal) return;

                var itemRows = modal.querySelectorAll('.item-row');
                if (itemRows.length > 0) {
                    var abcAmount = parseFloat(modal.querySelector('input[name="abc_allotment"]').value) || 0;
                    var totalSum = 0;

                    itemRows.forEach(function (row) {
                        var amtInput = row.querySelector('input[type="number"]');
                        if (amtInput) totalSum += parseFloat(amtInput.value) || 0;
                    });

                    totalSum = Math.round(totalSum * 100) / 100;
                    abcAmount = Math.round(abcAmount * 100) / 100;

                    if (Math.abs(totalSum - abcAmount) >= 0.01) {
                        e.preventDefault();
                        alert('Error: The total item breakdown sum (₱' + totalSum.toFixed(2) + ') must be equal to the ABC / Approved Allotment (₱' + abcAmount.toFixed(2) + ').');
                    }
                }
            });
        });
    });
</script>