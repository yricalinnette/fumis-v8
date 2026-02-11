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
        <button type="button" class="btn btn-success" id="btn-add-new">
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
                <tr class="{{ $fund->status == 'Disbursed' ? 'table-light' : '' }}">
                    <td>{{ $fund->dtrack_no }}</td>
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
                    <td>{{ $fund->fundSource->name ?? 'N/A' }}</td>
                    <td>{{ $fund->activity->name ?? 'N/A' }}</td>
                    
                    <td class="text-right font-weight-bold">
                        @if($fund->status == 'Disbursed')
                            {{-- Show Disbursement Amount --}}
                            <span class="text-success">₱{{ number_format($fund->disbursement_amount, 2) }}</span>
                            <div class="text-xs text-muted" style="font-size: 0.6rem;">(DISBURSED)</div>
                        @elseif($fund->status == 'Obligated')
                            {{-- Show Obligation Amount IF NOT NULL, otherwise fallback to initial amount --}}
                            @if($fund->obligation_amount > 0)
                                <span class="text-primary">₱{{ number_format($fund->obligation_amount, 2) }}</span>
                                <div class="text-xs text-muted" style="font-size: 0.6rem;">(OBLIGATED)</div>
                            @else
                                <span class="text-orange">₱{{ number_format($fund->amount, 2) }}</span>
                                <div class="text-xs text-muted" style="font-size: 0.6rem;">(AWAITING SYNC)</div>
                            @endif
                        @elseif($fund->status == 'For CAF/Obligation')
                            <span class="badge-warning">₱{{ number_format($fund->amount, 2) }}</span>
                            <div class="text-xs text-muted" style="font-size: 0.6rem;">(Awaiting ORSN)</div>
                        @else
                            {{-- Show original requested amount --}}
                            <span>₱{{ number_format($fund->amount, 2) }}</span>
                            <div class="text-xs text-muted" style="font-size: 0.6rem;">(Processed)</div>
                        @endif
                    </td>

                    <td>
                        <span class="badge {{ 
                            $fund->status == 'Disbursed' || $fund->status == 'Completed' ? 'badge-success' : 
                            ($fund->status == 'Cancelled' ? 'badge-danger' : 
                            ($fund->status == 'Routed' ? 'badge-primary' : 
                            ($fund->status == 'For CAF/Obligation' ? 'badge-warning' : 
                            ($fund->status == 'Obligated' ? 'bg-orange' : 'badge-info'))))
                        }}">
                            {{ $fund->status }}
                        </span>

                        <div class="small font-weight-bold mt-1">
                            @if($fund->status == 'Disbursed' && $fund->disbursement_date)
                                <i class="far fa-calendar-check text-success mr-1"></i> 
                                Disbursed: {{ \Carbon\Carbon::parse($fund->disbursement_date)->format('M d, Y') }}
                            @elseif($fund->status == 'Obligated' && $fund->obligation_date)
                                <i class="far fa-calendar-check text-primary mr-1"></i> 
                                Obligated: {{ \Carbon\Carbon::parse($fund->obligation_date)->format('M d, Y') }}
                            @elseif($fund->status_date)
                                <i class="far fa-clock text-muted mr-1"></i> 
                                As of: {{ \Carbon\Carbon::parse($fund->dtrack_update_date)->format('M d, Y') }}
                            @endif
                        </div>

                        @if($fund->obligation_serial)
                            <div class="mt-1">
                                <small class="text-primary font-weight-bold" style="letter-spacing: 0.5px;">
                                    <i class="fas fa-barcode mr-1"></i> {{ $fund->obligation_serial }}
                                </small>
                            </div>
                        @endif

                        @if($fund->remarks)
                            <div class="mt-1">
                                <small class="text-muted"><i class="fas fa-info-circle"></i> {{ $fund->remarks }}</small>
                            </div>
                        @endif
                    </td>

                    <td class="text-center">
                        <button type="button" 
                            class="btn btn-sm btn-default btn-flat shadow-sm btn-update-status"
                            style="border-left: 3px solid #17a2b8;"
                            data-id="{{ $fund->id }}"
                            data-particulars="{{ $fund->particulars }}"
                            data-dtrack="{{ $fund->dtrack_no }}"
                            data-status="{{ $fund->status }}"
                            data-statusdate="{{ $fund->status_date ? \Carbon\Carbon::parse($fund->status_date)->format('Y-m-d') : date('Y-m-d') }}"
                            data-remarks="{{ $fund->remarks }}"
                            data-serial="{{ $fund->obligation_serial }}"
                            data-obamount="{{ $fund->obligation_amount ?? 0 }}"
                            data-toggle="tooltip" 
                            {{-- DISABLE IF DISBURSED --}}
                            {{ $fund->status == 'Disbursed' ? 'disabled' : '' }}
                            title="{{ $fund->status == 'Disbursed' ? 'Transaction is finalized' : 'Update Status' }}">
                            <i class="fas fa-history {{ $fund->status == 'Disbursed' ? 'text-muted' : 'text-info' }} mr-1"></i> 
                        </button>

                        <button type="button" 
                            class="btn btn-sm btn-default btn-flat shadow-sm btn-edit-transaction"
                            style="border-left: 3px solid #ffc107;"
                            data-id="{{ $fund->id }}"
                            data-status="{{ $fund->status }}"
                            data-toggle="tooltip" 
                            {{-- DISABLE IF NOT ROUTED (This naturally includes Disbursed) --}}
                            {{ $fund->status !== 'Routed' ? 'disabled' : '' }}
                            title="{{ $fund->status !== 'Routed' ? 'Editing only allowed for Routed status' : 'Edit Transaction Details' }}">
                            <i class="fas fa-edit {{ $fund->status !== 'Routed' ? 'text-muted' : 'text-warning' }} mr-1"></i> 
                        </button>

                        <button type="button" 
                                class="btn btn-sm btn-outline-info btn-sync-sheet" 
                                data-id="{{ $fund->id }}"
                                data-serial="{{ $fund->obligation_serial }}" {{-- ADD THIS LINE --}}
                                {{-- DISABLE SYNC IF DISBURSED OR NO SERIAL --}}
                                {{ $fund->status == 'Disbursed' || !$fund->obligation_serial ? 'disabled' : '' }}
                                data-toggle="tooltip"
                                title="{{ $fund->status == 'Disbursed' ? 'Sync locked' : 'Sync with Google Sheet' }}">
                            <i class="fas fa-sync-alt {{ $fund->status == 'Disbursed' ? 'text-muted' : '' }}"></i>
                        </button>

                        @php
                            // Ensure this variable matches the one in your @foreach ($fund)
                            $isDeleteDisabled = ($fund->status !== 'Routed');
                        @endphp

                        <button type="button" class="btn btn-sm btn-default btn-flat shadow-sm btn-delete-transaction"
                            style="border-left: 3px solid {{ $isDeleteDisabled ? '#6c757d' : '#dc3545' }};" 
                            data-id="{{ $fund->id }}" 
                            data-dtrack="{{ $fund->dtrack_no }}"
                            {{ $isDeleteDisabled ? 'disabled' : '' }}
                            data-toggle="tooltip" 
                            title="{{ $isDeleteDisabled ? 'Only Routed transactions can be deleted' : 'Delete Transaction' }}">
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
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title"><i class="fas fa-sync-alt mr-2"></i>Update Status: <span id="display_dtrack"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="status-update-form">
                @csrf
                @method('PATCH') <input type="hidden" name="fund_id" id="modal_fund_id">
               
                <div class="modal-body">
                    <div class="form-group">
                        <label class="required"><i class="fas fa-calendar-alt mr-1"></i> Status Date</label>
                        <input type="date" name="status_date" id="modal_status_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        <small class="text-muted">Set this to the actual date the status changed.</small>
                    </div>
                    <div class="form-group">
                        <label class="required">Transaction Status</label>
                        <select name="status" id="modal_status_select" class="form-control" required>
                            <option value="For Signature">For Signature</option>
                            <option value="Obligated">Obligated</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group" id="serial_no_container" style="display: none;">
                        <label class="text-primary required"><i class="fas fa-barcode mr-1"></i> Obligation Reference Serial No.</label>
                        <input type="text" name="obligation_serial" id="modal_serial_input" class="form-control border-primary" placeholder="Enter Serial No. from Google Sheet">
                        <small class="text-muted">Required when marking as Obligated.</small>
                    </div>
                    <div class="form-group">
                        <label>Remarks / Notes</label>
                        <textarea name="remarks" id="modal_remarks_input" class="form-control" rows="3" placeholder="Enter status updates or reasons here..."></textarea>
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
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-info">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-list-check mr-2"></i> Bulk Sync Report</h5>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div class="row text-center mb-4">
                    <div class="col-4">
                        <h2 id="sum-success" class="text-success font-weight-bold mb-0">0</h2>
                        <small class="text-muted">SUCCESS</small>
                    </div>
                    <div class="col-4">
                        <h2 id="sum-failed" class="text-danger font-weight-bold mb-0">0</h2>
                        <small class="text-muted">FAILED/NOT FOUND</small>
                    </div>
                    <div class="col-4">
                        <h2 id="sum-total" class="font-weight-bold mb-0">0</h2>
                        <small class="text-muted">TOTAL</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-success border-bottom pb-2"><i class="fas fa-check-circle mr-1"></i> Updated</h6>
                        <ul id="list-success" class="list-group list-group-flush small" style="max-height: 200px; overflow-y: auto;">
                            </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-danger border-bottom pb-2"><i class="fas fa-times-circle mr-1"></i> Failed / Mismatch</h6>
                        <ul id="list-failed" class="list-group list-group-flush small" style="max-height: 200px; overflow-y: auto;">
                            </ul>
                    </div>
                </div>

                <div id="halted-warning" class="alert alert-warning mt-3 mb-0 d-none">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Process was halted manually.
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" onclick="location.reload()">Close & Refresh</button>
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
                            <tr class="py-1">
                                <th><i class="fas fa-barcode mr-2 text-muted"></i> Serial No:</th> 
                                <td id="v_serial" class="text-dark"></td>
                            </tr>
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

    $(document).on('click', '.btn-sync-sheet', function(e) {
        e.preventDefault();
        const btn = $(this);
        const id = btn.data('id');
        const originalHtml = btn.html();

        // Prevent double-clicking
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: `/funds/${id}/sync`,
            method: "GET",
            success: function(response) {
                // Use a Toast or small alert for individual success
                alert('Sync Successful: ' + response.new_amount);
                location.reload(); 
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(originalHtml);
                let msg = xhr.responseJSON ? xhr.responseJSON.message : "Connection failed";
                alert('Sync Error: ' + msg);
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
            
            // Initialize results with arrays to store details
            let results = { 
                success: 0, 
                failed: 0, 
                disbursed: 0, 
                obligated: 0,
                successList: [], // Store {dtrack, serial}
                failedList: []   // Store {dtrack, serial, reason}
            };

            let table = $('#funds-table').DataTable();
            const items = [];

            // Capture data from the table
            table.rows().every(function() {
                const rowNode = this.node();
                const syncBtn = $(rowNode).find('.btn-sync-sheet');
                
                if (syncBtn.length > 0 && !syncBtn.is(':disabled')) {
                    // Find the DTrack (usually the first <td>)
                    const dtrackVal = $(rowNode).find('td').eq(0).text().trim();
                    
                    // Find the Serial (look for it in the data attribute of the button first)
                    const serialVal = syncBtn.data('serial') || $(rowNode).find('td').eq(2).text().trim();

                    items.push({
                        id: syncBtn.data('id'),
                        serial: serialVal, 
                        dtrack: dtrackVal
                    });
                }
            });

            if (items.length === 0) return alert('No valid transactions found.');
            if (!confirm(`${items.length} item/s found with Obligation Reference Serial Number. Do you want to sync now?`)) return;

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            $('#sync-progress-container').slideDown();
            
            let total = items.length;
            let currentIdx = 0;

            function processNext() {
                if (window.bulkSyncStopSignal) {
                    showSummary(results, true);
                    return;
                }

                if (currentIdx >= total) {
                    showSummary(results, false);
                    return;
                }

                let currentItem = items[currentIdx];
                
                $.ajax({
                    url: `/funds/${currentItem.id}/sync`,
                    method: "GET",
                    success: function(response) {
                        results.success++;
                        results.successList.push(currentItem);
                        
                        if(response.new_status === 'Disbursed') results.disbursed++;
                        else results.obligated++;
                        
                        updateUI(currentIdx, total);
                        currentIdx++;
                        processNext();
                    },
                    error: function(xhr) {
                        results.failed++;
                        // Add to failed list with the error message from server
                        let errorMsg = xhr.responseJSON ? xhr.responseJSON.message : "Server Error";
                        results.failedList.push({
                            dtrack: currentItem.dtrack,
                            serial: currentItem.serial,
                            reason: errorMsg
                        });
                        
                        updateUI(currentIdx, total);
                        currentIdx++;
                        processNext();
                    }
                });
            }

            function updateUI(idx, total) {
                let progress = Math.round(((idx + 1) / total) * 100);
                $('#sync-progress-bar').css('width', progress + '%');
                $('#sync-percent-text').text(`${progress}% (${idx + 1}/${total})`);
            }

            processNext();
        });

        // $(document).on('click', '#btn-sync-all', function() {
        //     const btn = $(this);
        //     const originalHtml = btn.html();

        //     // 1. UI Feedback: Disable and show loading
        //     btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Syncing...');

        //     $.ajax({
        //         url: "{{ route('funds.sync_all') }}", // Ensure you define this in web.php
        //         method: "POST",
        //         data: { _token: "{{ csrf_token() }}" },
        //         success: function(response) {
        //             if (response.success) {
        //                 $(document).Toasts('create', {
        //                     class: 'bg-success',
        //                     title: 'Sync Successful',
        //                     autohide: true,
        //                     delay: 3000,
        //                     body: response.message
        //                 });
        //                 // Refresh page to show new statuses and badge colors
        //                 setTimeout(() => { location.reload(); }, 1500);
        //             } else {
        //                 $(document).Toasts('create', {
        //                     class: 'bg-warning',
        //                     title: 'Sync Note',
        //                     body: response.message
        //                 });
        //                 btn.prop('disabled', false).html(originalHtml);
        //             }
        //         },
        //         error: function() {
        //             $(document).Toasts('create', {
        //                 class: 'bg-danger',
        //                 title: 'Error',
        //                 body: 'Failed to connect to DTrack system.'
        //             });
        //             btn.prop('disabled', false).html(originalHtml);
        //         }
        //     });
        // });
        
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
                            <strong>${item.serial}</strong>
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

        let table = $('#funds-table').DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "ordering": true,
            "order": [[1, 'desc']], // Keeps latest date at top
            "dom": '<"row"<"col-md-6"B><"col-md-6"f>>rtip',
            "buttons": ["copy", "excel", "pdf", "print", "colvis"],
            "columnDefs": [
                { 
                    "width": "120px", 
                    "targets": 0, // DTrack Number
                    "className": "text-center",
                    "render": function(data, type, row) {
                        return '<span class="view-dtrack text-primary font-weight-bold" style="cursor:pointer; text-decoration:underline;">' + data + '</span>';
                    }
                },
                { 
                    "width": "100px", 
                    "targets": 1, // Date Column
                    "render": function(data, type, row) {
                        // If this is for sorting or type checking, use the raw data (YYYY-MM-DD)
                        if (type === 'sort' || type === 'type') {
                            // If row is an array (from AJAX), the raw date is at index 1
                            return (Array.isArray(row)) ? row[1] : data;
                        }
                        // For display, use our formatDate helper
                        return formatDate(data);
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

        $(document).on('click', '.btn-update-status', function() {
            const id = $(this).data('id');
            const status = $(this).data('status');
            const serial = $(this).data('serial');
            const dtrack = $(this).data('dtrack');
            const particulars = $(this).data('particulars');
            const obAmount = parseFloat($(this).data('obamount')) || 0; // Get the amount
            const statusDate = $(this).data('statusdate');

            $('#statusModal .modal-title').html(
                '<i class="fas fa-sync-alt mr-2"></i>Update Status: <span id="display_dtrack">' + dtrack + '</span>'
            );
            if (particulars && particulars.toString().trim() !== "") {
                $('#v_particulars').text(particulars).removeClass('text-muted italic');
            } else {
                $('#v_particulars').text('No particulars provided.').addClass('text-muted italic');
            }
            // Populate fields
            $('#modal_fund_id').val(id);
            $('#modal_status_date').val(statusDate);
            $('#modal_status_select').val(status);
            $('#modal_serial_input').val(serial);
            $('#display_dtrack').text(dtrack);

            // --- UPDATED LOCKDOWN LOGIC ---
            // Only lock if status is Obligated AND we have successfully pulled an amount from Google
            const isSynced = (status === 'Obligated' && obAmount > 0);

            if (isSynced) {
                // LOCK: Sync is finished
                $('#modal_status_select').css({
                    'pointer-events': 'none',
                    'background-color': '#e9ecef'
                }).attr('tabindex', '-1');
                
                $('#modal_serial_input').attr('readonly', true);
                console.log("Status: Synced. Fields Locked.");
            } else {
                // UNLOCK: Still for signature, or Obligated but waiting for sync
                $('#modal_status_select').css({
                    'pointer-events': 'auto',
                    'background-color': '#fff'
                }).removeAttr('tabindex');
                
                $('#modal_serial_input').attr('readonly', false);
                console.log("Status: Not Synced. Fields Editable.");
            }

            // Always show serial input if status is Obligated
            if (status === 'Obligated') {
                $('#serial_no_container').show();
            } else {
                $('#serial_no_container').hide();
            }

            $('#statusModal').modal('show');
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
        $(document).on('click', '#btn-add-new', function() {
            $('#fund-form')[0].reset();
            $('#edit_fund_id').val('');
            $('#form_method').val('POST');
            $('.modal-title').html('<i class="fas fa-plus mr-2"></i>Add New Transaction');
            $('.btn-save-fund').text('Save Transaction').removeClass('btn-warning').addClass('btn-success');
            $('.select2').val(null).trigger('change');
            $('#addFundModal').modal('show');
        });

        $(document).on('click', '.btn-edit-transaction', function() {
            const status = $(this).attr('data-status');
            
            // Safety check
            if ($(this).is(':disabled') || (status !== 'Routed' && status !== 'For Signature')) {
                $(document).Toasts('create', {
                    class: 'bg-warning',
                    title: 'Locked',
                    autohide: true,
                    delay: 3000,
                    body: 'Only transactions in "Routed" or "For Signature" status can be edited.'
                });
                return false; 
            }
            
            const id = $(this).data('id');

            $.get("/funds/" + id + "/edit", function(data) {
                // 1. Basic Setup
                $('#fund-form')[0].reset();
                $('#edit_fund_id').val(data.id);
                $('#form_method').val('PATCH');
                $('.modal-title').html('<i class="fas fa-edit mr-2 text-warning"></i>Edit Transaction: ' + data.dtrack_no);

                // 2. Standard Fields
                $('#dtrack_input').val(data.dtrack_no);
                $('#amount_input').val(data.amount);
                
                if (data.amount) {
                    let formatted = parseFloat(data.amount).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    $('#amount_display').val(formatted);
                } else {
                    $('#amount_display').val('');
                }
                $('#particulars_input').val(data.particulars);

                // 3. Creditors (Select2)
                if (data.creditors) {
                    let creditorIds = data.creditors.map(c => c.id);
                    $('#creditor_select').val(creditorIds).trigger('change');
                }

                if (data.transaction_date) {
                    let cleanDate = data.transaction_date.split(' ')[0].split('T')[0];
                    $('#transaction_date').val(cleanDate);
                }

                // 4. SOURCE OF FUND (Direct ID assignment)
                if (data.source_of_fund_id) {
                    // 1. Set the Source of Fund first
                    $('#modal_source_select').val(data.source_of_fund_id).trigger('change');

                    // 2. Use a more robust interval check or a longer delay
                    // This waits for the dependent dropdown logic to finish
                    let checkExist = setInterval(function() {
                        if ($('#modal_activity_select option[value="' + data.transaction_type_id + '"]').length) {
                            $('#modal_activity_select').val(data.transaction_type_id).trigger('change');
                            clearInterval(checkExist); // Stop checking once found
                        }
                    }, 100); // Check every 100ms

                    // Safety: Stop checking after 3 seconds so it doesn't loop forever if something is wrong
                    setTimeout(() => clearInterval(checkExist), 3000);
                }

                $('#addFundModal').modal('show');
            });
        });

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

        // Target ONLY the DTrack span class
        $('#funds-table tbody').on('click', '.view-dtrack', function (e) {
            e.preventDefault();

            const tr = $(this).closest('tr');
            const updateBtn = tr.find('.btn-update-status');
            const rowData = table.row(tr).data();

            // 1. Set Modal Title
            const dtrackValue = $(this).text().trim();
            $('#viewTransactionModal').find('#view_dtrack').text(dtrackValue);
            $('#viewTransactionModal').find('.modal-title').html(
                '<i class="fas fa-info-circle mr-2"></i> Transaction Details: <span class="text-warning">' + dtrackValue + '</span>'
            );

            // 2. Extract Hidden Data from the Update Button
            const particulars = updateBtn.attr('data-particulars'); 
            const remarks = updateBtn.attr('data-remarks');
            const serial = updateBtn.attr('data-serial');

            // 3. Populate Table Fields (Handling Orthogonal Data for Date)
            let dateDisplay = rowData[1];
            
            // Logic to handle the new {display, @data-order} object vs raw string
            if (typeof dateDisplay === 'object' && dateDisplay !== null) {
                dateDisplay = dateDisplay.display;
            } else {
                // Fallback: If it's a raw string, format it using our global helper
                dateDisplay = formatDate(dateDisplay);
            }

            $('#v_date').text(dateDisplay);
            $('#v_creditors').html(rowData[2]);
            $('#v_source').text(rowData[3]);
            $('#v_activity').text(rowData[4]);
            $('#v_amount').html(rowData[5]);
            $('#v_status').html(rowData[6]);
            $('#v_serial').text(serial || 'N/A');

            // 4. Handle Particulars
            if (particulars && particulars.trim() !== "" && particulars !== 'null') {
                $('#v_particulars').text(particulars).removeClass('text-muted italic');
            } else {
                $('#v_particulars').html('<span class="text-muted italic">No particulars provided.</span>').addClass('text-muted italic');
            }

            // 5. Handle Remarks
            if (remarks && remarks.trim() !== "" && remarks !== 'null') {
                $('#v_remarks').text(remarks).removeClass('text-muted italic');
            } else {
                $('#v_remarks').html('<span class="text-muted italic">No remarks provided.</span>').addClass('text-muted italic');
            }

            // 6. Launch Modal
            $('#viewTransactionModal').modal('show');
        });

        $('#statusModal').on('hidden.bs.modal', function () {
            $(this).find('.modal-title').html(''); // Clear title
            $('#v_particulars').text('');           // Clear particulars
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

        $('#modal_activity_select').on('change', function() {
            // Convert to lowercase once for comparison
            const activityText = $(this).find("option:selected").text().toLowerCase();
            
            // Check against lowercase keywords
            const isRequired = activityText.includes('salary') || 
                            activityText.includes('provision of tev') || 
                            activityText.includes('provision of plane tickets');

            const $creditorSelect = $('#creditor_select');
            const $label = $creditorSelect.closest('.form-group').find('label');

            if (isRequired) {
                // Enable and make required
                $creditorSelect.prop('disabled', false).attr('required', true);
                $label.addClass('required');
                $creditorSelect.closest('.form-group').css('opacity', '1');
            } else {
                // Disable, remove required, and clear any existing selection
                $creditorSelect.val(null).prop('disabled', true).attr('required', false);
                $label.removeClass('required');
                $creditorSelect.closest('.form-group').css('opacity', '0.6');
            }

            // Essential for Select2 to update its visual "disabled" look
            $creditorSelect.trigger('change');
        });
    });

    $(document).ready(function() {
        function startDTrackAutoSync() {
            console.log('Starting background DTrack sync...');
            
            $.ajax({
                url: '/funds/sync-all-dtrack',
                method: 'GET',
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        response.data.forEach(fund => {
                            // 1. Update the table row visuals
                            let row = $(`.fund-row[data-id="${fund.id}"]`);
                            if (row.length) {
                                row.find('.status-cell').text(fund.status);
                                row.find('.remarks-cell').text(fund.remarks);
                                
                                // 2. Update data-attributes for the View Modal
                                row.attr('data-remarks', fund.remarks);
                                row.attr('data-doc-update', fund.updated_at);
                            }
                        });
                        console.log('DTrack sync completed successfully.');
                    }
                },
                error: function() {
                    console.error('DTrack background sync failed.');
                }
            });
        }

        // Trigger on page load
        startDTrackAutoSync();

        // Repeat every 5 minutes (300,000 milliseconds)
        setInterval(startDTrackAutoSync, 300000);
    });
</script>
@endsection