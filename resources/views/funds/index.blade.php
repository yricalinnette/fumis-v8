@extends('layouts.adminlte')

@section('content')

<style>
    /* Force the warning label to be visible and red when over budget */
    #budget-warning {
        display: block !important;
        margin-top: 8px;
        min-height: 20px;
    }

    /* Standard Bootstrap invalid input styling */
    .is-invalid {
        border-color: #dc3545 !important;
        background-image: none !important; /* Removes the default bootstrap exclamation icon if preferred */
    }

    select:not([disabled]) + .select2-container .select2-selection {
        cursor: pointer !important;
        pointer-events: auto !important;
    }
    .select2-container--bootstrap4.select2-container--disabled {
        pointer-events: none !important;
    }
    /* Make Select2 tags (badges) match AdminLTE info colors */
    .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice {
        background-color: #17a2b8;
        border: 1px solid #138496;
        color: #fff;
        padding: 1px 10px;
        margin-top: 0.35rem;
    }

    /* Adjust the placeholder text color */
    .select2-container--bootstrap4 .select2-selection--multiple .select2-search__field {
        color: #6c757d;
    }

    /* Fix vertical alignment for the clear button */
    .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove {
        color: rgba(255,255,255,0.7);
        margin-right: 5px;
    }
    .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #fff;
    }

    /* The temporary highlight for the new row */
    .new-row-highlight {
        background-color: #d4edda !important; /* Light green success glow */
        transition: background-color 3s ease; /* Slowly fades out over 3 seconds */
    }

    /* Ensure the search bar container is visible and aligned */
    .dataTables_wrapper .dataTables_filter {
        float: right;
        margin-top: 10px;
        margin-right: 15px;
    }

    .dataTables_wrapper .dataTables_filter input {
        width: 250px !important; /* Force a visible width */
        height: 38px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        padding: 0.375rem 0.75rem;
    }

    /* Adjust button spacing so they don't crash into the search bar */
    .dt-buttons {
        margin-top: 10px;
        margin-left: 15px;
        margin-bottom: 10px;
    }
    /* 5. Force Grid Lines (Vertical and Horizontal) */
    #funds-table.table-bordered {
        border: 1px solid #dee2e6 !important;
        border-collapse: collapse !important;
    }

    #funds-table.table-bordered th,
    #funds-table.table-bordered td {
        border: 1px solid #dee2e6 !important; /* Forces the gray lines back */
    }

    /* Ensure lines remain visible even on hover */
    #funds-table tbody tr:hover td {
        border-color: #dee2e6 !important;
    }

    /* Fix for the 'Disbursed' rows which might be using a color that hides lines */
    #funds-table tbody td {
        background-clip: padding-box; /* Prevents background from covering the border */
    }
    /* This targets any label with the 'required' class */
    label.required:after {
        content: " *";
        color: #dc3545; /* Bootstrap red */
        font-weight: bold;
    }
    /* Add this to your style block */
    .is-invalid + .invalid-feedback {
        display: block;
    }

</style>

<div class="row mb-3">
    <div class="col-12 text-right">
        <div class="text-muted small mb-2">
            <i class="fas fa-sync-alt fa-spin text-success mr-1"></i> 
            DTrack Auto-Sync: <span id="sync-status">Active</span>
        </div>
        <button type="button" class="btn shadow-sm">
            <i class="fas fa-sync-alt mr-1"></i> Awaiting ORSN
            @if(isset($awaitingOBRN) && $awaitingOBRN > 0)
                <span class="badge badge-warning ml-1">{{ $awaitingOBRN }}</span>
            @endif
        </button>
        <button type="button" id="btn-bulk-sync" class="btn btn-info shadow-sm">
                <i class="fas fa-sync-alt mr-1"></i> Bulk Sync (RAODS)
                @if(isset($awaitingSyncCount) && $awaitingSyncCount > 0)
                    <span class="badge badge-warning ml-1">{{ $awaitingSyncCount }}</span>
                @endif
            </button>
        {{-- <button type="button" id="btn-sync-all" class="btn btn-primary   shadow-sm">
            <i class="fas fa-sync-alt mr-1"></i> Bulk Sync (DTRACK)
        </button> --}}
        <button type="button" class="btn btn-success btn-add-new">
            <i class="fas fa-plus"></i> Add New Transaction
        </button>
    </div>
</div>

<div class="card card-outline card-success">
    <div class="card-body p-0">
        <div id="sync-progress-container" style="display: none;" class="card card-outline card-info mb-3">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-sm font-weight-bold text-info"><i class="fas fa-cog fa-spin mr-1"></i> Bulk Syncing...</span>
                    <button type="button" id="btn-cancel-sync" class="btn btn-xs btn-danger shadow-sm">
                        <i class="fas fa-stop mr-1"></i> Stop Sync
                    </button>
                </div>
                <div class="progress progress-sm">
                    <div id="sync-progress-bar" class="progress-bar bg-info progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                </div>
                <div class="text-center mt-1">
                    <small id="sync-percent-text" class="text-muted font-weight-bold">0% (0/0)</small>
                </div>
            </div>
        </div>
        <table class="table table-bordered table-striped table-hover" id="funds-table">
            <thead>
                <tr>
                    <th>DTRACK NO.</th>
                    <th>Date</th>
                    <th>Creditor</th> 
                    <th>Source</th>
                    <th>Activity</th> 
                    <th class="text-right">Amount</th>
                    <th>Status & Remarks</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($funds as $fund)
                @php $firstItem = $fund->breakdown->first(); @endphp
                
                <tr class="{{ $firstItem->status == 'Disbursed' ? 'table-light' : '' }}">
                    <td>
                        <a href="#" class="view-dtrack font-weight-bold" 
                        data-particulars="{{ e($fund->particulars ?? 'No particulars') }}" 
                        data-remarks="{{ e($fund->all_remarks ?? 'No remarks') }}">
                            {{ $fund->dtrack_no }}
                        </a>
                    </td>
                    <td data-order="{{ \Carbon\Carbon::parse($fund->transaction_date)->format('Y-m-d') }}">
                        {{ \Carbon\Carbon::parse($fund->transaction_date)->format('M d, Y') }}
                    </td>
                    <td>
                        @if($fund->creditors->count() > 0)
                            @foreach($fund->creditors as $creditor)
                                <span class="badge badge-info mr-1">{{ $creditor->full_name }}</span>
                            @endforeach
                        @else
                            <span class="text-muted italic">N/A</span>
                        @endif
                    </td>
                    
                    <td style="font-size: 0.85rem; line-height: 1.2;">
                        {!! $fund->source_names !!}
                    </td>
                    
                    <td style="font-size: 0.85rem; line-height: 1.2;">
                        {!! $fund->activity_names !!}
                    </td>
                    
                    <td class="text-right">
                        @if(count($fund->breakdown) > 1)
                            <div class="mb-1" style="border-bottom: 1px dashed #ddd; padding-bottom: 2px;">
                                @foreach($fund->breakdown as $item)
                                    <div class="text-muted" style="font-size: 0.7rem; line-height: 1;">
                                        {{ $item->source_name }}: 
                                        <span class="font-italic">
                                            @php
                                                // Logic: Use obligation_amount if it's set and the status warrants it, 
                                                // otherwise fallback to the original amount.
                                                $displayAmount = ($item->status !== 'Processed' && $item->obligation_amount > 0) 
                                                                ? $item->obligation_amount 
                                                                : $item->amount;
                                            @endphp
                                            ₱{{ number_format($displayAmount, 2) }}
                                        </span>

                                        {{-- Warning icon if it's Obligated but the amount hasn't synced yet --}}
                                        @if($item->status == 'Obligated' && (!$item->obligation_amount || $item->obligation_amount <= 0))
                                            <i class="fas fa-exclamation-circle text-danger" title="Pending Sync"></i>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="font-weight-bold" style="font-size: 1rem;">
                            {{-- Display Amount based on Status Color --}}
                            @if($fund->group_status == 'Disbursed' || $fund->group_status == 'Completed')
                                <span class="text-success">₱{{ number_format($fund->total_amount, 2) }}</span>
                                <div class="text-xs text-muted" style="font-size: 0.6rem;">(DISBURSED)</div>
                            @elseif($fund->group_status == 'Obligated')
                                <span class="text-primary">₱{{ number_format($fund->total_amount, 2) }}</span>
                                <div class="text-xs text-muted" style="font-size: 0.6rem;">(OBLIGATED)</div>
                            @else
                                <span>₱{{ number_format($fund->total_amount, 2) }}</span>
                                <div class="text-xs text-muted" style="font-size: 0.6rem;">(Processed)</div>
                            @endif

                            {{-- ALWAYS show Awaiting Sync if any part of the group needs it --}}
                            @if(!$fund->is_fully_synced)
                                <div class="text-xs text-danger font-italic mt-1" style="font-size: 0.6rem;">
                                    <i class="fas fa-sync fa-spin"></i> Awaiting sync
                                </div>
                            @endif
                        </div>
                    </td>

                    <td>
                        @php
                            // 1. Determine if we use Merged or Detailed view for Status
                            $hasSignificantStatus = $fund->breakdown->contains(function($item) {
                                return in_array($item->status, ['Obligated', 'Disbursed', 'Completed', 'Cancelled']);
                            });
                            $firstStatus = $fund->breakdown->first()->status;
                            $allSameStatus = $fund->breakdown->every('status', $firstStatus);

                            // 2. Remark Deduplication Logic
                            // Get all unique, non-empty remarks from the breakdown
                            $uniqueRemarks = $fund->breakdown->pluck('remarks')->filter()->unique();
                            $allRemarksSame = $uniqueRemarks->count() <= 1;
                        @endphp

                        @if(!$hasSignificantStatus && $allSameStatus)
                            {{-- MERGED VIEW (Early Stage) --}}
                            <div class="text-left">
                                <span class="badge {{ 
                                    $firstStatus == 'Routed' ? 'badge-primary' : 
                                    ($firstStatus == 'For CAF/Obligation' ? 'badge-warning' : 'badge-info') 
                                }}">
                                    {{ $firstStatus }}
                                </span>
                                
                                @if($uniqueRemarks->isNotEmpty())
                                    <div class="mt-1 text-muted small border-left pl-2" style="font-style: italic;">
                                        <i class="fas fa-comment-dots mr-1" style="font-size: 0.7rem;"></i> 
                                        {{ $uniqueRemarks->implode('; ') }}
                                    </div>
                                @endif
                            </div>
                        @else
                            {{-- DETAILED VIEW --}}
                            @foreach($fund->breakdown as $item)
                                <div class="allocation-row {{ !$loop->last ? 'mb-3 border-bottom pb-2' : '' }}">
                                    <div class="small font-weight-bold text-dark mb-1">
                                        <i class="fas fa-wallet text-muted mr-1"></i> {{ $item->source_name }}
                                    </div>

                                    <span class="badge {{ 
                                        in_array($item->status, ['Disbursed', 'Completed']) ? 'badge-success' : 
                                        ($item->status == 'Cancelled' ? 'badge-danger' : 
                                        ($item->status == 'Routed' ? 'badge-primary' : 
                                        ($item->status == 'For CAF/Obligation' ? 'badge-warning' : 
                                        ($item->status == 'Obligated' ? 'bg-orange text-white' : 'badge-info'))))
                                    }}">
                                        {{ $item->status }}
                                    </span>

                                    <div class="small mt-1">
                                        @if($item->status == 'Obligated')
                                            @if(empty($item->obligation_amount) || $item->obligation_amount == 0)
                                                <div class="text-danger font-italic">
                                                    <i class="fas fa-sync fa-spin mr-1" style="font-size: 0.7rem;"></i> Awaiting sync to RAODS
                                                </div>
                                            @else
                                                @if($item->obligation_date)
                                                    <div class="text-primary">
                                                        <i class="far fa-calendar-check mr-1"></i> Oblig: {{ \Carbon\Carbon::parse($item->obligation_date)->format('M d, Y') }}
                                                    </div>
                                                @endif
                                            @endif
                                        @elseif(in_array($item->status, ['Disbursed', 'Completed']))
                                            @if($item->disbursement_date)
                                                <div class="text-success font-weight-bold">
                                                    <i class="fas fa-check-circle mr-1"></i> Disb: {{ \Carbon\Carbon::parse($item->disbursement_date)->format('M d, Y') }}
                                                </div>
                                            @endif
                                        @endif

                                        @if($item->obligation_serial)
                                            <div class="text-primary font-weight-bold">
                                                <i class="fas fa-barcode mr-1"></i> {{ $item->obligation_serial }}
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Only show individual remarks if they are DIFFERENT from each other --}}
                                    @if(!$allRemarksSame && $item->remarks)
                                        <div class="mt-1 text-muted small border-left pl-2" style="font-style: italic; background-color: #f9f9f9;">
                                            <i class="fas fa-comment-dots mr-1" style="font-size: 0.7rem;"></i> {{ $item->remarks }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                            {{-- Show Merged Remarks at the bottom if all sources share the same remark --}}
                            @if($allRemarksSame && $uniqueRemarks->isNotEmpty())
                                <div class="mt-2 text-muted small border-left pl-2" style="font-style: italic; background-color: #f8f9fa; border-left: 3px solid #dee2e6 !important;">
                                    <i class="fas fa-comments mr-1" style="font-size: 0.7rem;"></i> 
                                    {{ $uniqueRemarks->first() }}
                                </div>
                            @endif
                        @endif
                    </td>

                    <td class="text-center">
                        @php 
                            $hasAnySerial = $fund->breakdown->contains(fn($i) => !empty($i->obligation_serial));
                            $isDeleteDisabled = ($firstItem->status !== 'Routed');
                            $isDisbursed = \App\Models\Fund::where('dtrack_no', $fund->dtrack_no)
                                ->where('status', 'Disbursed')
                                ->exists();
                        @endphp

                        <button type="button" class="btn btn-sm btn-default btn-flat shadow-sm btn-update-status"
                            style="border-left: 3px solid #17a2b8;"
                            data-id="{{ $fund->id }}" data-dtrack="{{ $fund->dtrack_no }}"
                            {{ $firstItem->status == 'Disbursed' ? 'disabled' : '' }}>
                            <i class="fas fa-history {{ $firstItem->status == 'Disbursed' ? 'text-muted' : 'text-info' }}"></i> 
                        </button>

                        <button type="button" class="btn btn-sm btn-default btn-edit-transaction"
                            data-id="{{ $fund->id }}"
                            {{ $firstItem->status !== 'Routed' ? 'disabled' : '' }}>
                            <i class="fas fa-edit {{ $firstItem->status !== 'Routed' ? 'text-muted' : 'text-warning' }}"></i> 
                        </button>

                        <button type="button" 
                                class="btn btn-sm {{ $isDisbursed ? 'btn-outline-secondary' : 'btn-outline-info' }} btn-sync-sheet" 
                                data-id="{{ $fund->id }}"
                                data-dtrack="{{ $fund->dtrack_no }}"
                                {{ ($isDisbursed || (isset($hasAnySerial) && !$hasAnySerial)) ? 'disabled' : '' }}
                                data-toggle="tooltip" 
                                title="{{ $isDisbursed ? 'Transaction is already Disbursed' : 'Sync with Google Sheet' }}">
                            <i class="fas {{ $isDisbursed ? 'fa-check-double' : 'fa-sync-alt' }}"></i>
                        </button>

                        <button type="button" class="btn btn-sm btn-default btn-flat shadow-sm btn-delete-transaction"
                            style="border-left: 3px solid {{ $isDeleteDisabled ? '#6c757d' : '#dc3545' }};" 
                            data-id="{{ $fund->id }}" {{ $isDeleteDisabled ? 'disabled' : '' }}>
                            <i class="fas fa-trash {{ $isDeleteDisabled ? 'text-muted' : 'text-danger' }}"></i>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@include('funds.modal_form')

<div class="modal fade" id="statusModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document"> <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title">Update Status: <span id="display_dtrack"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="status-update-form">
                @csrf
                @method('PATCH')
                <input type="hidden" name="fund_id" id="modal_fund_id">
               
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required"><i class="fas fa-calendar-alt mr-1"></i> Status Date</label>
                                <input type="date" name="status_date" id="modal_status_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required">Transaction Status</label>
                                <select name="status" id="modal_status_select" class="form-control" required>
                                    <option value="For Signature">For Signature</option>
                                    <option value="Obligated">Obligated</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="serial_no_section" style="display: none;">
                        <hr>
                        <label class="text-primary font-weight-bold"><i class="fas fa-barcode mr-1"></i> Obligation Reference Serial Numbers</label>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Source of Fund</th>
                                        <th>Amount</th>
                                        <th style="width: 40%;">Serial No. (from Google Sheet)</th>
                                    </tr>
                                </thead>
                                <tbody id="serial_inputs_container">
                                    </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <label>Remarks / Notes</label>
                        <textarea name="remarks" id="modal_remarks_input" class="form-control" rows="2" placeholder="Enter status updates or reasons here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Update Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="syncSummaryModal" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-info">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-list-check mr-2"></i> Bulk Sync Report</h5>
            </div>
            <div class="modal-body" style="max-height: 80vh; overflow-y: auto;">
                <div class="row text-center mb-4">
                    <div class="col-3">
                        <h2 id="sum-total" class="font-weight-bold mb-0 text-secondary">0</h2>
                        <small class="text-muted font-weight-bold">TOTAL ITEMS</small>
                    </div>
                    <div class="col-3 border-left">
                        <h2 id="sum-success" class="text-success font-weight-bold mb-0">0</h2>
                        <small class="text-muted font-weight-bold">SYNCED</small>
                    </div>
                    <div class="col-3 border-left">
                        <h2 id="sum-duplicates" class="text-warning font-weight-bold mb-0">0</h2>
                        <small class="text-muted font-weight-bold">HAD DUPLICATES</small>
                    </div>
                    <div class="col-3 border-left">
                        <h2 id="sum-failed" class="text-danger font-weight-bold mb-0">0</h2>
                        <small class="text-muted font-weight-bold">FAILED / NOT FOUND</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <h6 class="text-success border-bottom pb-2 font-weight-bold">
                            <i class="fas fa-check-circle mr-1"></i> Successful Syncs
                        </h6>
                        <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                            <table class="table table-sm table-hover border">
                                <thead class="bg-light sticky-top">
                                    <tr>
                                        <th>Serial Number</th>
                                        <th>New Status</th>
                                        <th>Amount</th>
                                        <th>Duplicates (Row #)</th>
                                    </tr>
                                </thead>
                                <tbody id="list-success-table" class="small">
                                    </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <h6 class="text-danger border-bottom pb-2 font-weight-bold text-center">
                            <i class="fas fa-times-circle mr-1"></i> Failed / Not Found
                        </h6>
                        <ul id="list-failed" class="list-group list-group-flush small" style="max-height: 350px; overflow-y: auto;">
                            </ul>
                    </div>
                </div>

                <div id="halted-warning" class="alert alert-warning mt-3 mb-0 d-none">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Process was halted manually.
                </div>
            </div>
            <div class="modal-footer bg-light justify-content-between">
                <small class="text-muted italic">* Duplicate rows skipped to prevent amount inflation.</small>
                <button type="button" class="btn btn-secondary shadow-sm" onclick="location.reload()">Close & Refresh Page</button>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="syncResultModal" tabindex="-1" role="dialog" aria-labelledby="syncResultModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-body p-4 text-center">
                <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                <h4>Sync Complete</h4>
                <p class="text-muted">DTrack: <span id="rs-serial" class="font-weight-bold"></span></p>

                <div class="text-left bg-light p-3 border rounded mb-3" style="max-height: 150px; overflow-y: auto;">
                    <small class="text-muted text-uppercase d-block mb-2" style="font-size: 0.7rem;">Sources Updated:</small>
                    <ul id="rs-fund-list" class="list-unstyled mb-0" style="font-size: 0.85rem; color: #333;"></ul>
                </div>

                <div class="row">
                    <div class="col-6 border-right">
                        <small class="text-muted d-block">Result</small>
                        <span id="rs-amount" class="font-weight-bold"></span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Status</small>
                        <span id="rs-status" class="badge"></span>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="button" class="btn btn-primary btn-block shadow-sm" data-dismiss="modal" onclick="window.location.reload();">
                        Done
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewTransactionModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title font-weight-bold" id="viewModalLabel">
                    <i class="fas fa-info-circle mr-2 text-warning"></i> Transaction Details: 
                    <span id="view_dtrack" class="text-warning ml-1"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <div class="modal-body px-4 py-4">
                <div class="row mb-4">
                    <div class="col-md-6 pr-md-4">
                        <table class="table table-sm table-borderless mb-0">
                            <tr class="py-1">
                                <th width="35%"><i class="fas fa-calendar mr-2 text-muted"></i> Date:</th> 
                                <td id="v_date" class="text-dark"></td>
                            </tr>
                            <tr class="py-1">
                                <th><i class="fas fa-user-tie mr-2 text-muted"></i> Creditors:</th> 
                                <td id="v_creditors"></td>
                            </tr>
                            <tr class="py-1">
                                <th><i class="fas fa-wallet mr-2 text-muted"></i> Source:</th> 
                                <td id="v_source" class="text-dark"></td>
                            </tr>
                            <tr class="py-1">
                                <th><i class="fas fa-tag mr-2 text-muted"></i> Activity:</th> 
                                <td id="v_activity" class="text-dark"></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6 pl-md-4 border-left">
                        <table class="table table-sm table-borderless mb-0">
                            <tr class="py-1">
                                <th width="35%"><i class="fas fa-money-bill-wave mr-2 text-muted"></i> Amount:</th> 
                                <td id="v_amount" class="h5 mb-0 text-success font-weight-bold"></td>
                            </tr>
                            <tr class="py-1">
                                <th><i class="fas fa-check-circle mr-2 text-muted"></i> Status:</th> 
                                <td id="v_status"></td>
                            </tr>
                            {{-- <tr class="py-1">
                                <th><i class="fas fa-barcode mr-2 text-muted"></i> Serial No:</th> 
                                <td id="v_serial" class="text-dark"></td>
                            </tr> --}}
                        </table>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="font-weight-bold text-uppercase small text-muted mb-2">
                        <i class="fas fa-file-alt mr-1"></i> Particulars
                    </h6>
                    <div id="v_particulars" class="p-3 bg-light border rounded-lg text-dark" style="min-height: 60px; line-height: 1.6;"></div>
                </div>

                <div>
                    <h6 class="font-weight-bold text-uppercase small text-muted mb-2">
                        <i class="fas fa-comment-alt mr-1"></i> Remarks/Notes
                    </h6>
                    <div id="v_remarks" class="p-3 bg-light border rounded-lg text-dark" style="min-height: 60px; line-height: 1.6;"></div>
                </div>
            </div>

            <div class="modal-footer bg-light border-0 px-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


@endsection

@section('js')

<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

<script>
    function formatDate(dateString) {
        if(!dateString) return "";
        const date = new Date(dateString);
        // Add a check to prevent "Invalid Date" text in the table
        if (isNaN(date.getTime())) return dateString; 
        
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: '2-digit',
            year: 'numeric'
        });
    }

    let isBudgetValid = true; // Global flag to track budget status

    function checkBudgetBalance() {
        let activityId = $('#modal_activity_select').val(); 
        let amount = parseFloat($('#amount_input').val()) || 0;
        let warningLabel = $('#budget-warning');
        let saveBtn = $('#fund-form button[type="submit"], .btn-save-fund');
        
        if (!activityId || amount <= 0) {
            warningLabel.html('').attr('style', '');
            return;
        }

        $.ajax({
            url: "{{ route('funds.check_balance') }}",
            method: "GET",
            data: {
                activity_id: activityId,
                amount: amount,
                current_fund_id: $('#edit_fund_id').val()
            },
            success: function(response) {
                if (response.is_sufficient) {
                    isBudgetValid = true;
                    // Reset to Green
                    warningLabel.html(`<i class="fas fa-check-circle"></i> Remaining Budget: ₱${response.formatted_remaining}`)
                                .attr('style', 'color: #28a745 !important; font-weight: normal;');
                    saveBtn.prop('disabled', false); // ENABLE BUTTON
                    $('#amount_input').removeClass('is-invalid');
                } else {
                    isBudgetValid = false;
                    // FORCE BOLD RED
                    warningLabel.html(`<i class="fas fa-exclamation-triangle"></i> EXCEEDS REMAINING BUDGET: ₱${response.formatted_remaining}`)
                                .attr('style', 'color: #dc3545 !important; font-weight: bold; font-size: 15px;');
                    
                    // Visual feedback on the input box
                    $('#amount_input').addClass('is-invalid');
                    
                    saveBtn.prop('disabled', true); // DISABLE BUTTON
                }
            }
        });
    }

    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 2
        }).format(amount);
    }


    // Trigger checks on input
    $(document).on('change', '#modal_activity_select', checkBudgetBalance);
    // 1. Live Formatting Logic
    $(document).on('input', '#amount_display', function() {
        // Remove everything except numbers and decimal point
        let value = $(this).val().replace(/[^0-9.]/g, '');
        
        // Split into integer and fraction
        let parts = value.split('.');
        if (parts.length > 2) value = parts[0] + '.' + parts.slice(1).join('');

        // Update the HIDDEN input with the clean number (e.g. 1500.50)
        $('#amount_input').val(value);

        // Format the DISPLAY input with commas (e.g. 1,500.50)
        if (parts[0]) {
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
        $(this).val(parts.join('.'));

        // 2. Trigger your existing Budget Check
        checkBudgetBalance();
    });

    // 3. Update Edit Logic
    // Inside your btn-edit-transaction click handler, update how you load the amount:
    function populateEditForm(data) {
        $('#amount_input').val(data.amount); // Hidden raw value
        
        // Format and show in display field
        let formattedAmount = parseFloat(data.amount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        $('#amount_display').val(formattedAmount);
    }

    function toggleSerialInput() {
        let status = $('#modal_status_select').val();
        
        if (status === 'Obligated') {
            $('#serial_no_container').show();
            // Force the attribute
            $('#modal_serial_input').attr('required', 'required').addClass('border-primary');
        } else {
            $('#serial_no_container').hide();
            // Remove the attribute and clear value so it doesn't block submission
            $('#modal_serial_input').removeAttr('required').removeClass('border-primary').val('');
        }
    }

    // Trigger when the dropdown changes
    $('#modal_status_select').on('change', function() {
        if ($(this).val() === 'Obligated') {
            $('#serial_no_container').slideDown();
        } else {
            $('#serial_no_container').slideUp();
        }
    });

    function refreshSyncCount() {
        // Optional: Call an endpoint to get the real-time count
        // For now, we can just hide it after a successful bulk sync
        let badge = $('#sync-count-badge');
        
        // If you want to fetch the real count via AJAX:
        $.get("{{ route('funds.sync-count') }}", function(data) {
            if (data.count > 0) {
                badge.text(data.count).show();
            } else {
                badge.hide();
            }
        });
    }

    $(document).on('click', '.btn-sync-sheet', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const fundId = $btn.data('id');
        const dtrackNo = $btn.data('dtrack'); 
        
        // Select all buttons sharing the same DTrack
        const $groupButtons = $(`.btn-sync-sheet[data-dtrack="${dtrackNo}"]`);
        const $groupIcons = $groupButtons.find('i');

        // UI Feedback
        $groupButtons.prop('disabled', true);
        $groupIcons.attr('class', 'fas fa-circle-notch fa-spin text-warning');

        $.ajax({
            url: `/funds/${fundId}/sync`,
            method: "GET",
            dataType: "json",
            success: function (response) {
                if (response.success) {
                    const details = response.details;
                    
                    // Populate Modal
                    $('#rs-serial').text(details.dtrack_no);
                    
                    const $list = $('#rs-fund-list');
                    $list.empty();
                    
                    if (details.synced_names.length > 0) {
                        details.synced_names.forEach(name => {
                            $list.append(`<li><i class="fas fa-check-circle text-success mr-2"></i> ${name}</li>`);
                        });
                    }

                    $('#rs-amount').text(`${details.count} Source(s) Updated`);
                    $('#rs-status').text('Success').addClass('badge-success');

                    $('#syncResultModal').modal('show');

                    // Silent reload for DataTables
                    if ($.fn.DataTable.isDataTable('#funds-table')) {
                        // Re-fetch the table instance and reload
                        $('#funds-table').DataTable().ajax.reload(null, false);
                    }
                }
            },
            error: function (xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Server Error'));
            },
            complete: function() {
                $groupButtons.prop('disabled', false);
                $groupIcons.attr('class', 'fas fa-sync-alt');
            }
        });
    });

    // 1. Force this into the global window scope
    window.bulkSyncStopSignal = false;

    $(document).ready(function() {

        // 2. The Stop Button - Force immediate recognition
        $(document).on('click', '#btn-cancel-sync', function(e) {
            e.preventDefault();
            if (confirm('Stop the process after the current transaction?')) {
                window.bulkSyncStopSignal = true; 
                console.log("STOP SIGNAL SENT: ", window.bulkSyncStopSignal);
                $(this).prop('disabled', true).addClass('btn-warning').html('<i class="fas fa-spinner fa-spin"></i> Halting...');
            }
        });

        $(document).on('click', '#btn-bulk-sync', function() {
            const btn = $(this);
            window.bulkSyncStopSignal = false;
            
            // 1. Results Object
            let results = { 
                success: 0, 
                failed: 0, 
                duplicateCount: 0,
                successList: [], 
                failedList: [] 
            };

            let table = $('#funds-table').DataTable();
            const items = [];

            // 2. Capture data from DataTable
            table.rows().every(function() {
                const rowNode = this.node();
                const syncBtn = $(rowNode).find('.btn-sync-sheet');
                
                if (syncBtn.length > 0 && !syncBtn.is(':disabled')) {
                    items.push({
                        id: syncBtn.data('id'),
                        serial: syncBtn.data('serial') || $(rowNode).find('td').eq(2).text().trim()
                    });
                }
            });

            if (items.length === 0) return alert('No valid transactions found.');
            if (!confirm(`Found ${items.length} item(s). Start Bulk Sync?`)) return;

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            $('#sync-progress-container').slideDown();
            
            let total = items.length;
            let currentIdx = 0;

            function processNext() {
                if (window.bulkSyncStopSignal || currentIdx >= total) {
                    showSummary(results, window.bulkSyncStopSignal);
                    btn.prop('disabled', false).html('<i class="fas fa-sync"></i> Bulk Sync');
                    return;
                }

                let currentItem = items[currentIdx];
                
                $.ajax({
                    url: `/funds/${currentItem.id}/sync`,
                    method: "GET",
                    success: function(response) {
                        if (response.success) {
                            const d = response.details;
                            results.success++;

                            let duplicateRows = [];
                            if (d.has_duplicates) {
                                results.duplicateCount++;
                                duplicateRows = [...(d.duplicate_ob_rows || []), ...(d.duplicate_disb_rows || [])];
                            }

                            results.successList.push({
                                serial: d.serial,
                                status: d.new_status,
                                amount: d.new_amount,
                                duplicates: duplicateRows
                            });
                        } else {
                            // Logic for "success: false" but 200 status (if any)
                            recordFailure(currentItem.serial, response.message || "Unknown Error");
                        }
                        
                        finishItem();
                    },
                    error: function(xhr) {
                        let errorMsg = xhr.responseJSON ? xhr.responseJSON.message : "Server Error";
                        recordFailure(currentItem.serial, errorMsg);
                        finishItem();
                    }
                });
            }

            function recordFailure(serial, reason) {
                results.failed++;
                results.failedList.push({ serial: serial, reason: reason });
            }

            function finishItem() {
                updateUI(currentIdx, total);
                currentIdx++;
                processNext();
            }

            function updateUI(idx, total) {
                let progress = Math.round(((idx + 1) / total) * 100);
                $('#sync-progress-bar').css('width', progress + '%');
                $('#sync-percent-text').text(`${progress}% (${idx + 1}/${total})`);
            }

            // 3. Updated showSummary Function
            function showSummary(res, halted) {
                $('#sum-total').text(total);
                $('#sum-success').text(res.success);
                $('#sum-failed').text(res.failed);
                $('#sum-duplicates').text(res.duplicateCount);

                // Populate Success Table
                let successHtml = '';
                if (res.successList.length > 0) {
                    res.successList.forEach(item => {
                        let dupBadge = item.duplicates.length > 0 
                            ? `<span class="badge badge-warning text-dark">Rows: ${item.duplicates.join(', ')}</span>` 
                            : '<span class="text-muted small">None</span>';

                        successHtml += `
                            <tr>
                                <td><strong>${item.serial}</strong></td>
                                <td><span class="badge ${item.status === 'Disbursed' ? 'badge-success' : 'badge-primary'}">${item.status}</span></td>
                                <td>₱${item.amount}</td>
                                <td>${dupBadge}</td>
                            </tr>`;
                    });
                } else {
                    successHtml = '<tr><td colspan="4" class="text-center text-muted">No successful updates.</td></tr>';
                }
                $('#list-success-table').html(successHtml);

                // Populate Failed List with Empty Check
                let failedHtml = '';
                if (res.failedList.length > 0) {
                    res.failedList.forEach(item => {
                        failedHtml += `
                            <li class="list-group-item list-group-item-danger py-1">
                                <strong>${item.serial}</strong>: ${item.reason}
                            </li>`;
                    });
                } else {
                    failedHtml = `
                        <li class="list-group-item list-group-item-light py-3 text-center">
                            <i class="fas fa-check-circle text-success mr-2"></i>
                            <span class="text-muted">No failed transactions.</span>
                        </li>`;
                }
                $('#list-failed').html(failedHtml);

                if (halted) $('#halted-warning').removeClass('d-none');
                
                $('#syncSummaryModal').modal('show');
            }

            processNext();
        });

        
        $(document).ready(function() {
            // Automatically trigger the sync every 5 minutes while the dashboard is open
            setInterval(function() {
                console.log("Auto-syncing DTrack statuses...");
                
                $.ajax({
                    url: "{{ route('funds.sync_all') }}",
                    method: "POST",
                    data: { _token: "{{ csrf_token() }}" },
                    success: function(response) {
                        if (response.success) {
                            // Update UI elements or reload specific sections
                            // location.reload(); // Use sparingly to avoid interrupting users
                        }
                    }
                });
            }, 300000); // 300,000ms = 5 minutes
        });

        function showSummary(res, halted) {
            $('#sync-progress-container').slideUp();
            
            // Fill Counters
            $('#sum-success').text(res.success);
            $('#sum-failed').text(res.failed);
            $('#sum-total').text(res.success + res.failed);
            
            // Populate Success List
            let successHtml = '';
            res.successList.forEach(item => {
                successHtml += `<li class="list-group-item py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small">#${item.dtrack}</span><br>
                            <strong>${item.serial} (${item.status})</strong>
                        </div>
                        <i class="fas fa-check text-success"></i>
                    </div>
                </li>`;
            });
            $('#list-success').html(successHtml || '<li class="list-group-item text-muted">None</li>');

            // Populate Failure List
            let failedHtml = '';
            res.failedList.forEach(item => {
                failedHtml += `<li class="list-group-item py-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small">#${item.dtrack}</span><br>
                            <strong>${item.serial}</strong>
                        </div>
                        <span class="badge badge-danger">Fail</span>
                    </div>
                    <div class="text-danger x-small mt-1" style="font-size: 0.75rem;">${item.reason}</div>
                </li>`;
            });
            $('#list-failed').html(failedHtml || '<li class="list-group-item text-muted">None</li>');
            
            if (halted) $('#halted-warning').removeClass('d-none');
            $('#syncSummaryModal').modal('show');
        }
    });

    $(document).ready(function() {

        // Prevent the "Invalid JSON" alert from appearing to the user
        $.fn.dataTable.ext.errMode = 'none';

        let table = $('#funds-table').DataTable({
            "processing": true, // Show indicator while loading
            "serverSide": false, // Change to true if using server-side processing
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "ordering": true,
            "order": [[1, 'desc']], 
            "dom": '<"row"<"col-md-6"B><"col-md-6"f>>rtip',
            "buttons": ["copy", "excel", "pdf", "print", "colvis"],
            "columnDefs": [
                { 
                    "width": "120px", 
                    "targets": 0, 
                    "className": "text-center",
                    "render": function(data, type, row) {
                        return `<span class="view-dtrack text-primary font-weight-bold" 
                                    style="cursor:pointer; text-decoration:underline;">${data}</span>`;
                    }
                },
                { 
                    "width": "100px", 
                    "targets": 1, 
                    "render": function(data, type, row) {
                        if (type === 'sort' || type === 'type') {
                            return (Array.isArray(row)) ? row[1] : data;
                        }
                        return typeof formatDate === 'function' ? formatDate(data) : data;
                    }
                },
                { "width": "180px", "targets": 2 },
                { "width": "100px", "targets": 3 },
                { "width": "150px", "targets": 4 }, 
                { "width": "110px", "targets": 5 }, 
                { "width": "180px", "targets": 6 }, 
                { "width": "100px", "targets": 7, "orderable": false }
            ],
            "language": {
                "searchPlaceholder": "Search transactions...",
                "search": ""
            }
        });

        // Capture and log errors quietly instead of using alerts
        $('#funds-table').on('error.dt', function(e, settings, techNote, message) {
            console.error('DataTables Error:', message);
        });
        
        // 1. Initialize Select2
        if ($.isFunction($.fn.select2)) {
            $('.select2').select2({
                theme: 'bootstrap4',
                placeholder: '🔍 Search and select staff...',
                allowClear: true,
                closeOnSelect: false, // Keeps the dropdown open for multiple selections
                width: '100%',
                dropdownParent: $('#addFundModal') // Crucial for focus within modals
            });
        }

        const allActivities = $('#modal_activity_select option').clone();
        $(document).on('change', '#modal_source_select', function() {
            let sourceId = $(this).val(); 
            let activitySelect = $('#modal_activity_select');
            
            // Destroy Select2 to manipulate the underlying HTML
            if (activitySelect.data('select2')) {
                activitySelect.select2('destroy');
            }

            // Clear the current options
            activitySelect.empty();

            if (sourceId) {
                // Enable the select
                activitySelect.prop('disabled', false).removeAttr('disabled');

                // Add the default placeholder option back
                activitySelect.append('<option value="">-- Select Activity --</option>');

                // Filter the master copy and append only matching ones
                allActivities.each(function() {
                    if ($(this).data('source') == sourceId || $(this).val() == "") {
                        // Only append if it belongs to this source
                        if($(this).val() !== "") {
                            activitySelect.append($(this).clone());
                        }
                    }
                });

                // If no matches were found
                if (activitySelect.find('option').length <= 1) {
                    activitySelect.prop('disabled', true);
                    activitySelect.find('option[value=""]').text('-- No Activities Found --');
                }
            } else {
                activitySelect.prop('disabled', true);
                activitySelect.append('<option value="">-- Select Source First --</option>');
            }

            // Re-initialize Select2
            activitySelect.select2({
                theme: 'bootstrap4',
                placeholder: "Select Activity",
                width: '100%',
                dropdownParent: $('#addFundModal')
            });

            // Final UI Cleanup
            setTimeout(function() {
                activitySelect.next('.select2-container').removeClass('select2-container--disabled');
            }, 50);
        });

        const currentYear = new Date().getFullYear() + '-';

        // 2. Modal Open Logic (Auto-Year Prefix)
        $('#addFundModal').on('shown.bs.modal', function () {
            const dtrackInput = $('#dtrack_input');
            const isEdit = $('#edit_fund_id').val() !== ''; // Check if we are editing

            // Only auto-prefix if it's a NEW record and empty
            if (!isEdit && !dtrackInput.val().startsWith(currentYear)) {
                dtrackInput.val(currentYear);
            }
            
            dtrackInput.focus();
            const len = dtrackInput.val().length;
            dtrackInput[0].setSelectionRange(len, len);
        });

        // 3. DTRACK Input Masking (Lock Year Prefix)
        $('#dtrack_input').on('keydown', function(e) {
            const cursorPosition = this.selectionStart;
            if ((e.keyCode === 8 || e.keyCode === 46) && cursorPosition <= 5) {
                e.preventDefault();
            }
        });

        $('#dtrack_input').on('input', function() {
            let val = $(this).val();
            if (!val.startsWith(currentYear)) {
                val = currentYear + val.replace(currentYear, '').replace(/\D/g, '');
            }
            let prefix = val.substring(0, 5);
            let numericPart = val.substring(5).replace(/\D/g, '').substring(0, 6);
            $(this).val(prefix + numericPart);
        });

        // 4. AJAX SUBMIT LOGIC (Unified and Fixed)
        $('#fund-form').on('submit', function(e) {
            e.preventDefault();
            if (!isBudgetValid) {
                $(document).Toasts('create', {
                    class: 'bg-danger',
                    title: 'Budget Error',
                    autohide: true,
                    delay: 3000,
                    body: 'Cannot save. The amount exceeds the allotted budget for this activity.'
                });
                return false;
            }

            const fundId = $('#edit_fund_id').val();
            const isEdit = fundId !== '';
            const ajaxUrl = isEdit ? "/funds/" + fundId : "{{ route('funds.store') }}";

            $.ajax({
                url: ajaxUrl,
                method: "POST",
                data: $(this).serialize(),
                success: function(response) {
                    // 1. Extract the data object consistently
                    const fundData = response.data ? response.data : response;

                    // 2. If it was an Edit, hide modal and reload to refresh relationships
                    if (isEdit) {
                        $('#addFundModal').modal('hide');
                        $(document).Toasts('create', { class: 'bg-info', title: 'Updated', autohide: true, delay: 2000, body: 'Transaction updated successfully!' });
                        setTimeout(() => { location.reload(); }, 1000);
                        return;
                    }

                    // 3. Define Status and Delete Logic
                    const currentStatus = fundData.status || 'Routed';
                    const isDeleteDisabled = (currentStatus !== 'Routed');
                    
                    let statusClass = 'primary'; 
                    if (currentStatus === 'Disbursed' || currentStatus === 'Completed') statusClass = 'success';
                    else if (currentStatus === 'Cancelled') statusClass = 'danger';
                    else if (currentStatus === 'Obligated') statusClass = 'navy';
                    else if (currentStatus === 'For Signature') statusClass = 'warning';

                    const deleteBtnColor = isDeleteDisabled ? '#6c757d' : '#dc3545';
                    const deleteIconClass = isDeleteDisabled ? 'text-muted' : 'text-danger';
                    let isSyncDisabled = (currentStatus === 'Routed' || !fundData.obligation_serial) ? 'disabled' : '';
                    let syncIconClass = (currentStatus === 'Routed') ? 'text-muted' : 'text-success';

                    // 4. UI Helpers
                    const formatDate = (dateStr) => {
                        if(!dateStr) return "";
                        const d = new Date(dateStr);
                        return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
                    };

                    const escapeHtml = (text) => {
                        if (!text) return "";
                        return text.toString().replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    };

                    // 5. Build Creditor Badges
                    let creditorHtml = '';
                    if (fundData.creditors && Array.isArray(fundData.creditors) && fundData.creditors.length > 0) {
                        fundData.creditors.forEach(creditor => {
                            creditorHtml += `<span class="badge badge-info mr-1">${creditor.full_name || 'N/A'}</span>`;
                        });
                    } else {
                        creditorHtml = '<span class="text-muted italic">N/A</span>';
                    }

                    // 6. Amount Display Logic
                    let amountContent = '';
                    let baseAmount = parseFloat(fundData.amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
                    if (currentStatus === 'Disbursed') {
                        let disbAmt = parseFloat(fundData.disbursement_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
                        amountContent = `<span class="text-success">₱${disbAmt}</span><div class="text-xs text-muted" style="font-size: 0.6rem;">(DISBURSED)</div>`;
                    } else if (currentStatus === 'Obligated') {
                        let oblAmt = parseFloat(fundData.obligation_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
                        amountContent = `<span class="text-primary">₱${oblAmt}</span><div class="text-xs text-muted" style="font-size: 0.6rem;">(OBLIGATED)</div>`;
                    } else {
                        amountContent = `<span>₱${baseAmount}</span><div class="text-xs text-muted" style="font-size: 0.6rem;">(Processed)</div>`;
                    }

                    // 7. NEW: Use Activity Relationship for the name
                    // Previously: fundData.transaction_type
                    let activityName = fundData.activity ? fundData.activity.name : 'N/A';
                    let sourceName = fundData.fund_source ? fundData.fund_source.name : 'N/A';

                    // 8. Construct Action Buttons (Data attributes updated to match relationship IDs)
                    let actionButtonsHtml = `
                        <div class="text-center">
                            <button type="button" class="btn btn-sm btn-default btn-flat shadow-sm btn-update-status"
                                style="border-left: 3px solid #17a2b8;" 
                                data-id="${fundData.id}" 
                                data-status="${currentStatus}" 
                                data-remarks="${escapeHtml(fundData.remarks)}"
                                data-particulars="${escapeHtml(fundData.particulars)}"> 
                                <i class="fas fa-history text-info"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-default btn-flat shadow-sm btn-edit-transaction"
                                style="border-left: 3px solid #ffc107;" 
                                data-id="${fundData.id}" 
                                data-status="${currentStatus}" 
                                data-activity-id="${fundData.transaction_type_id}">
                                <i class="fas fa-edit text-warning"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-default btn-flat shadow-sm btn-sync-sheet"
                                style="border-left: 3px solid #28a745;" 
                                data-id="${fundData.id}" 
                                ${isSyncDisabled}>
                                <i class="fas fa-sync-alt ${syncIconClass}"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-default btn-flat shadow-sm btn-delete-transaction"
                                ${isDeleteDisabled ? 'disabled' : ''}
                                style="border-left: 3px solid ${deleteBtnColor};"
                                data-id="${fundData.id}">
                                <i class="fas fa-trash ${deleteIconClass}"></i>
                            </button>
                        </div>`;
                    
                    // 9. ADD TO DATATABLE
                    let rowNode = table.row.add([
                        `<strong>${fundData.dtrack_no}</strong>`, 
                        // CHANGE THIS: Provide both display and @data-order values
                        {
                            display: formatDate(fundData.transaction_date),
                            '@data-order': fundData.transaction_date // This is the YYYY-MM-DD version
                        },
                        creditorHtml, 
                        sourceName, 
                        activityName, // Updated to use relationship-based name
                        `<div class="text-right font-weight-bold">${amountContent}</div>`, 
                        `<span class="badge badge-${statusClass} shadow-sm px-2">${currentStatus}</span>`, 
                        actionButtonsHtml 
                    ]).draw(false).node();

                    // Re-sort to ensure it stays at the top
                    table.order([1, 'desc']).draw();

                    // 10. Effects and Cleanup
                    $('#addFundModal').modal('hide');
                    $('#fund-form')[0].reset();
                    $('.select2').val(null).trigger('change');
                    
                    $(rowNode).addClass('new-row-highlight').hide();
                    $('#funds-table tbody').prepend(rowNode); 
                    $(rowNode).fadeIn(1000);
                    $(rowNode).find('[data-toggle="tooltip"]').tooltip();

                    $(document).Toasts('create', {
                        class: 'bg-success',
                        title: 'Success',
                        autohide: true,
                        delay: 3000,
                        body: 'Transaction ' + fundData.dtrack_no + ' logged successfully!'
                    });
                },
                error: function(xhr) {
                    $('.form-control').removeClass('is-invalid');
                    $('.invalid-feedback').remove();

                    if (xhr.status === 422 && xhr.responseJSON.errors) {
                        let errors = xhr.responseJSON.errors;
                        Object.keys(errors).forEach(key => {
                            let field = $(`[name="${key}"], [name="${key}[]"]`);
                            field.addClass('is-invalid');
                            field.after(`<span class="invalid-feedback d-block">${errors[key][0]}</span>`);
                        });
                    } else {
                        let msg = xhr.responseJSON ? xhr.responseJSON.message : 'System error occurred.';
                        $(document).Toasts('create', { class: 'bg-warning', title: 'Error', autohide: true, delay: 5000, body: msg });
                    }
                }
            });
        });

        $(document).on('click', '.btn-update-status', function(e) {
            e.preventDefault();
            
            const dtrack = $(this).data('dtrack');
            const id = $(this).data('id');

            // 1. Force close and reset to prevent ghosting data
            $('#statusModal').modal('hide');
            $('#display_dtrack').text(dtrack);
            $('#modal_fund_id').val(id);
            
            // 2. Placeholder while loading
            $('#serial_inputs_container').html('<tr><td colspan="3" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');

            $.get(`/funds/group/${dtrack}`)
                .done(function(data) {
                    // Get today's date in YYYY-MM-DD format
                    const today = new Date().toISOString().split('T')[0];
                    let rows = '';

                    if (data.allocations && data.allocations.length > 0) {
                        const firstAlloc = data.allocations[0];
                        
                        $('#modal_status_select').val(firstAlloc.status);
                        $('#modal_remarks_input').val(firstAlloc.remarks || '');
                        
                        // 2. Fix the date formatting
                        if (firstAlloc.status === 'Obligated' && firstAlloc.status_date) {
                            // If the date contains a 'T', split it; otherwise use it as is
                            const cleanDate = firstAlloc.status_date.includes('T') 
                                ? firstAlloc.status_date.split('T')[0] 
                                : firstAlloc.status_date;
                                
                            $('#modal_status_date').val(cleanDate);
                        } else {
                            $('#modal_status_date').val(today);
                        }

                        // Build table rows
                        data.allocations.forEach((alloc, index) => {
                            rows += `
                            <tr>
                                <td><small class="font-weight-bold">${alloc.source_name}</small></td>
                                <td class="text-right"><small>₱${alloc.amount}</small></td>
                                <td>
                                    <input type="hidden" name="serials[${index}][id]" value="${alloc.id}">
                                    <input type="text" name="serials[${index}][serial_no]" 
                                        class="form-control form-control-sm serial-field" 
                                        value="${alloc.obligation_serial || ''}" 
                                        placeholder="Enter Serial No."
                                        ${firstAlloc.status === 'Obligated' ? 'required' : ''}>
                                </td>
                            </tr>`;
                        });
                        
                        $('#serial_inputs_container').html(rows);

                        // Initial visibility check based on loaded status
                        toggleSerialVisibility($('#modal_status_select').val());
                        
                    } else {
                        $('#serial_inputs_container').html('<tr><td colspan="3" class="text-center text-danger">No allocations found.</td></tr>');
                    }

                    $('#statusModal').modal('show');
                });
        });

        /**
         * Helper function to handle showing/hiding serial fields
         * Also linked to the 'change' event of the select dropdown
         */
        function toggleSerialVisibility(status) {
            if (status === 'Obligated') {
                $('#serial_no_section').fadeIn();
                $('.serial-field').attr('required', true);
            } else {
                $('#serial_no_section').hide();
                $('.serial-field').attr('required', false).val(''); // Clear serials if not obligated
            }
        }

        // Attach listener to the dropdown for real-time UI changes
        $('#modal_status_select').on('change', function() {
            toggleSerialVisibility($(this).val());
        });

        // Toggle Serial Section visibility
        $('#modal_status_select').on('change', function() {
            if ($(this).val() === 'Obligated') {
                $('#serial_no_section').fadeIn();
                $('.serial-field').attr('required', true);
            } else {
                $('#serial_no_section').fadeOut();
                $('.serial-field').attr('required', false);
            }
        });

        // 2. Handle Form Submission via AJAX
        $('#status-update-form').on('submit', function(e) {
            e.preventDefault();
            const fundId = $('#modal_fund_id').val();
            
            // Create a data object from the form
            let formData = $(this).serialize();

            $.ajax({
                url: "/funds/" + fundId + "/status",
                method: "POST", // Browser sends as POST
                data: formData + "&_method=PATCH", // Laravel treats as PATCH
                success: function(response) {
                    $('#statusModal').modal('hide');
                    
                    $(document).Toasts('create', {
                        class: 'bg-info',
                        title: 'Success',
                        autohide: true,
                        delay: 3000,
                        body: 'Status updated successfully!'
                    });

                    // Reload to show the new badge and remarks
                    setTimeout(() => { location.reload(); }, 1000);
                },
                error: function(xhr) {
                    // Log the detailed error to console so you can see if validation failed
                    console.error(xhr.responseText);
                    alert('Error: ' + xhr.status + '. Check console for details.');
                }
            });
        });

        // 1. Reset Modal for "Add New"
        $('.btn-add-new').on('click', function() {
            // 1. Reset the form and hidden fields
            $('#fund-form')[0].reset();
            $('#edit_fund_id').val('');
            $('#form_method').val('POST');
            
            // 2. Clear out any rows left over from an "Edit" session
            $('#allocation-body').empty();
            
            // 3. Reset the Select2 Creditor dropdown
            $('#creditor_select').val(null).trigger('change');

            // 4. Set the Title
            $('.modal-title').html('<i class="fas fa-plus-circle mr-2"></i> New Transaction Log');

            // 5. Add the first blank allocation row (index 0)
            addNewAllocationRow(0);

            // 6. Reset Grand Total display
            updateGrandTotal();

            // 7. Show the modal
            $('#addFundModal').modal('show');
        });

        $(document).on('click', '.btn-edit-transaction', function() {
            const id = $(this).data('id');
            const status = ($(this).attr('data-status') || '').trim().toLowerCase();

            // 1. Safety check
            if (status !== 'routed' && status !== 'for signature') {
                $(document).Toasts('create', { 
                    class: 'bg-warning', 
                    title: 'Locked', 
                    body: 'This transaction is locked and cannot be edited.' 
                });
                return false;
            }

            $.get("/funds/" + id + "/edit", function(data) {
                if (!data.success) return;

                const main = data.main;
                const allocations = data.allocations;

                // 2. Setup Form Basics
                $('#fund-form')[0].reset();
                $('#edit_fund_id').val(main.id);
                $('#form_method').val('PATCH');
                $('.modal-title').html('<i class="fas fa-edit mr-2 text-warning"></i>Edit Transaction: ' + main.dtrack_no);
                
                // 3. Global Fields
                $('#dtrack_input').val(main.dtrack_no);
                
                // FIX: Improved Date Parsing to ensure YYYY-MM-DD format
                if (main.transaction_date) {
                    $('#transaction_date').val(main.transaction_date.split('T')[0]);
                }

                $('#particulars_input').val(main.particulars);

                // FIX: Match the key sent from Controller (creditor_ids)
                if (data.creditor_ids) {
                    $('#creditor_select').val(data.creditor_ids).trigger('change');
                }

                // 4. Populate Allocations
                $('#allocation-body').empty(); // Clear existing rows

                allocations.forEach((alloc, index) => {
                    // Add a new row for each allocation
                    addNewAllocationRow(index, alloc.id);
                    
                    // Get the specific row just added
                    const row = $(`.allocation-row`).last();
                    
                    // Set Source and Amount
                    row.find('.source-select').val(alloc.source_of_fund_id).trigger('change');
                    row.find('.amount-field').val(alloc.amount);

                    // 5. Dependent Dropdown Sync
                    // Poll for the activity select to be populated via the change trigger
                    let checkExist = setInterval(function() {
                        const activitySelect = row.find('.activity-select');
                        if (activitySelect.find('option[value="' + alloc.transaction_type_id + '"]').length) {
                            activitySelect.val(alloc.transaction_type_id).removeAttr('disabled');
                            clearInterval(checkExist);
                            updateGrandTotal(); 
                        }
                    }, 100);
                    
                    // Stop polling after 3 seconds
                    setTimeout(() => clearInterval(checkExist), 3000);
                });

                $('#addFundModal').modal('show');
            }).fail(function() {
                toastr.error('Failed to retrieve transaction data.');
            });
        });

        // Helper function to create rows (Reuse this for your "Add Funding Source" button too)
        function addNewAllocationRow(index, rowId = '') {
            const html = `
            <tr class="allocation-row">
                <input type="hidden" name="allocations[${index}][id]" value="${rowId}">
                
                <td>
                    <select name="allocations[${index}][source_id]" class="form-control source-select" required>
                        <option value="">-- Select --</option>
                        @foreach($sources as $source)
                            <option value="{{ $source->id }}">{{ $source->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <select name="allocations[${index}][activity_id]" class="form-control activity-select" required disabled>
                        <option value="">-- Select Source First --</option>
                    </select>
                </td>
                <td>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                        <input type="number" name="allocations[${index}][amount]" class="form-control amount-field" step="0.01" required>
                    </div>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm remove-row" ${index === 0 && rowId === '' ? 'disabled' : ''}>
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
            $('#allocation-body').append(html);
        }

        function updateGrandTotal() {
            let grandTotal = 0;

            // Loop through every input with the class 'amount-field'
            $('.amount-field').each(function() {
                let value = parseFloat($(this).val());
                if (!isNaN(value)) {
                    grandTotal += value;
                }
            });

            // Format the number to currency and update the display
            $('#grand-total-display').text('₱ ' + grandTotal.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }));
        }

        $(document).on('click', '[data-target="#addFundModal"]', function() {
            // Only reset if it's NOT the edit button being clicked
            if (!$(this).hasClass('btn-edit-transaction')) {
                $('#fund-form')[0].reset();
                $('#edit_fund_id').val('');
                $('#form_method').val('POST');
                $('.modal-title').html('<i class="fas fa-plus mr-2"></i>Add New Transaction');
                $('.btn-save-fund').text('Save Transaction').removeClass('btn-warning').addClass('btn-success');
                $('.select2').val(null).trigger('change');
            }
        });

        // For viewing of transaction info
        $(document).on('click', '.view-dtrack', function (e) {
            e.preventDefault();
            
            const $link = $(this);
            const modal = $('#viewTransactionModal');
            
            // Extraction
            let particulars = $link.attr('data-particulars');
            let remarks = $link.attr('data-remarks');

            // Responsive fallback
            if (particulars === undefined) {
                const tr = $link.closest('tr');
                particulars = tr.find('[data-particulars]').attr('data-particulars') || "";
                remarks = tr.find('[data-remarks]').attr('data-remarks') || "";
            }

            // Header
            modal.find('#view_dtrack').text($link.text().trim());
            
            // Inject and format
            // Replace semicolons with line breaks if you want them stacked in the modal
            const formattedRemarks = remarks ? remarks.replace(/; /g, '<br>') : 'No remarks provided.';

            $('#v_particulars').text(particulars || 'No particulars provided.');
            $('#v_remarks').html(formattedRemarks); // Use .html() if you added <br>

            // Populate remaining row data
            const tr = $link.closest('tr');
            const rowData = table.row(tr).data();
            if (rowData) {
                $('#v_date').html(rowData[1].display || rowData[1]);
                $('#v_creditors').html(rowData[2]);
                $('#v_source').html(rowData[3]);
                $('#v_activity').html(rowData[4]);
                $('#v_amount').html(rowData[5]);
                $('#v_status').html(rowData[6]);
            }

            modal.modal('show');
        });

        $('#statusModal').on('hidden.bs.modal', function () {
            // Reset the form values
            $(this).find('form').trigger('reset');
            // Clear the dynamic table
            $('#serial_inputs_container').empty();
            // Reset the DTrack text
            $('#display_dtrack').text('');
            // Remove modal backdrop if it gets stuck
            $('.modal-backdrop').remove();
            // Fix the body scrolling/padding issue
            $('body').removeClass('modal-open').css('padding-right', '');
        });

        $(document).on('click', '.btn-delete-transaction', function() {
            // Just in case the 'disabled' attribute doesn't stop the click
            if ($(this).is(':disabled')) return;

            const id = $(this).data('id');
            const dtrack = $(this).data('dtrack');
            const row = $(this).closest('tr');

            Swal.fire({
                title: 'Are you sure?',
                text: "You are about to delete Transaction: " + dtrack,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "/funds/" + id,
                        method: "POST", // Some servers prefer POST with _method spoofing
                        data: {
                            _token: "{{ csrf_token() }}",
                            _method: "DELETE" // Tells Laravel to treat this POST as a DELETE
                        },
                        success: function(response) {
                            if(response.success) {
                                table.row(row).remove().draw(false);
                                Swal.fire('Deleted!', response.message, 'success');
                            } else {
                                Swal.fire('Failed!', response.message, 'error');
                            }
                        }
                    });
                }
            });
        });
    });

    $(document).ready(function() {
        function updateCreditorStatus() {
            let isRequiredByAnyRow = false;

            // Loop through every activity select in the table
            $('.activity-select').each(function() {
                const $selectedOption = $(this).find("option:selected");
                
                // Only check if an option is actually selected
                if ($selectedOption.val()) {
                    const activityText = $selectedOption.text().toLowerCase();
                    
                    // Check against keywords
                    if (activityText.includes('salary') || 
                        activityText.includes('provision of tev') || 
                        activityText.includes('provision of plane tickets')) {
                        isRequiredByAnyRow = true;
                        return false; // Break the .each() loop early
                    }
                }
            });

            const $creditorSelect = $('#creditor_select');
            const $label = $creditorSelect.closest('.form-group').find('label');

            if (isRequiredByAnyRow) {
                // Enable and make required if at least one row matches keywords
                $creditorSelect.prop('disabled', false).attr('required', true);
                $label.addClass('required');
                $creditorSelect.closest('.form-group').css('opacity', '1');
            } else {
                // Disable and clear if NO rows match the keywords
                $creditorSelect.val(null).prop('disabled', true).attr('required', false);
                $label.removeClass('required');
                $creditorSelect.closest('.form-group').css('opacity', '0.6');
            }

            // Essential for Select2 to update its visual state
            $creditorSelect.trigger('change');
        }

        // 1. Listen for changes on any activity dropdown (existing or future rows)
        $(document).on('change', '.activity-select', function() {
            updateCreditorStatus();
        });

        // 2. Also trigger check when a row is removed 
        // (If the row requiring a creditor is deleted, we should disable the field)
        $(document).on('click', '.remove-row', function() {
            // Small delay to allow the row to be removed from DOM before checking
            setTimeout(updateCreditorStatus, 50);
        });
    });

    $(document).ready(function() {
        let rowCount = 1;

        // 1. Add New Row
        $('#add-allocation-row').click(function() {
            let newRow = `
                <tr class="allocation-row">
                    <td>
                        <select name="allocations[${rowCount}][source_id]" class="form-control source-select" required>
                            <option value="">-- Select --</option>
                            @foreach($sources as $source)
                                <option value="{{ $source->id }}">{{ $source->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select name="allocations[${rowCount}][activity_id]" class="form-control activity-select" required disabled>
                            <option value="">-- Select Source First --</option>
                        </select>
                    </td>
                    <td>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                            <input type="number" name="allocations[${rowCount}][amount]" class="form-control amount-field" step="0.01" placeholder="0.00" required>
                        </div>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
            $('#allocation-body').append(newRow);
            rowCount++;
            updateGrandTotal();
        });

        // 2. Remove Row
        $(document).on('click', '.remove-row', function() {
            $(this).closest('tr').remove();
            updateGrandTotal();
        });

        // 3. Dependent Dropdown (Source -> Activity)
        $(document).on('change', '.source-select', function() {
            const sourceId = $(this).val();
            const row = $(this).closest('tr');
            const activitySelect = row.find('.activity-select');

            if (!sourceId) {
                activitySelect.html('<option value="">-- Select Source First --</option>').prop('disabled', true);
                return;
            }

            // Fetch Activities via AJAX
            activitySelect.prop('disabled', false).html('<option value="">Loading...</option>');
            $.get(`/api/sources/${sourceId}/activities`, function(data) {
                let options = '<option value="">-- Select Activity --</option>';
                data.forEach(act => {
                    options += `<option value="${act.id}">${act.name}</option>`;
                });
                activitySelect.html(options);
            });
        });

        // 4. Calculate Grand Total
        $(document).on('input', '.amount-field', function() {
            updateGrandTotal();
        });

        function updateGrandTotal() {
            let total = 0;
            $('.amount-field').each(function() {
                let val = parseFloat($(this).val()) || 0;
                total += val;
            });
            $('#grand-total-display').text('₱ ' + total.toLocaleString(undefined, {minimumFractionDigits: 2}));
        }
    });
</script>
@endsection