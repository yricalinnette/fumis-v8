@extends('layouts.adminlte')

@section('content')
<style>
    /* Force Select2 to match Bootstrap Input Group height and alignment */
    .select2-container--default .select2-selection--single {
        border: 1px solid #ced4da !important;
        height: calc(2.25rem + 2px) !important; /* Standard Bootstrap Height */
        display: flex !important;
        align-items: center !important;
    }

    /* Remove left rounded corners to connect to the icon */
    .input-group > .select2-container--default {
        flex: 1 1 auto !important;
        width: 1% !important;
    }

    .input-group > .select2-container--default .select2-selection--single {
        border-top-left-radius: 0 !important;
        border-bottom-left-radius: 0 !important;
    }

    /* Style the text inside the search box */
    .select2-selection__rendered {
        color: #495057 !important;
        padding-left: 0.75rem !important;
    }

    /* Fix the arrow position */
    .select2-selection__arrow {
        height: 34px !important;
        top: 3px !important;
    }

    /* Modern Dropdown Item Look */
    .select2-results__option {
        padding: 10px !important;
    }

    .select2-result-employee__title {
        font-weight: 600;
        color: #2c3e50;
        display: block;
    }

    .select2-result-employee__id {
        font-size: 11px;
        color: #007bff;
        background: #e7f1ff;
        padding: 1px 5px;
        border-radius: 3px;
        margin-top: 3px;
        display: inline-block;
    }

    .widget-user-2 .widget-user-header {
        padding: 1rem;
        border-top-left-radius: .25rem;
        border-top-right-radius: .25rem;
    }

    #preview_initials {
        border: 2px solid rgba(255,255,255,0.2);
        text-transform: uppercase;
    }

    .badge {
        padding: 0.5em 0.8em;
        font-size: 85%;
        font-weight: 500;
    }

    .tooltip-inner {
        max-width: 350px; 
        text-align: left;
        padding: 10px;
    }
    .badge-soft-danger {
        background-color: #fceaea;
        color: #dc3545;
        border: 1px solid #f5c6cb;
    }
    /* Container for the buttons */
    .action-btn-group {
        display: flex;
        justify-content: center;
        gap: 8px; /* Professional spacing instead of cramped grouping */
    }

    /* Base button style */
    .btn-action {
        background-color: #ffffff;
        border: 1.5px solid;
        border-radius: 6px;
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease-in-out;
        padding: 0;
        cursor: pointer;
    }

    /* Edit Button (Info/Teal) */
    .btn-edit {
        color: #17a2b8;
        border-color: #17a2b8;
    }

    .btn-edit:hover {
        background-color: #17a2b8;
        color: #ffffff;
        box-shadow: 0 4px 8px rgba(23, 162, 184, 0.2);
        transform: translateY(-1px);
    }

    /* Delete Button (Danger/Red) */
    .btn-delete {
        color: #dc3545;
        border-color: #dc3545;
    }

    .btn-delete:hover {
        background-color: #dc3545;
        color: #ffffff;
        box-shadow: 0 4px 8px rgba(220, 53, 69, 0.2);
        transform: translateY(-1px);
    }

    /* Icon Sizing */
    .btn-action i {
        font-size: 0.9rem;
    }

    /* Fix for Tooltip z-index */
    .tooltip {
        z-index: 1060 !important;
    }
</style>
<div class="container-fluid">
    {{-- Notifications --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <i class="icon fas fa-check"></i> {{ session('success') }}
        </div>
    @endif
    
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <ul class="mb-0">@foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
        </div>
    @endif

    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0 text-dark">Fund Source Settings</h1>
        </div>
    </div>

    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header bg-white py-3">
            <h3 class="card-title font-weight-bold text-dark">
                <i class="fas fa-database mr-2 text-primary"></i>Fund Source Registry
            </h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" data-toggle="modal" data-target="#modal-add-source">
                    <i class="fas fa-plus-circle mr-1"></i> Add New Source
                </button>
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="fundSourceTable">
                    <thead class="thead-light">
                        <tr>
                            <th class="border-top-0 py-3 text-uppercase small font-weight-bold">Budget Line / Fund Name</th>
                            <th class="border-top-0 py-3 text-uppercase small font-weight-bold text-center">Sync Info</th>
                            <th class="border-top-0 py-3 text-uppercase small font-weight-bold text-right">Original Allotment</th>
                            <th class="border-top-0 py-3 text-uppercase small font-weight-bold text-center">Pooled Funds</th>
                            <th class="border-top-0 py-3 text-uppercase small font-weight-bold text-center">Net Allotment</th>
                            <th class="border-top-0 py-3 text-uppercase small font-weight-bold text-center">FY</th> 
                            <th class="border-top-0 py-3 text-uppercase small font-weight-bold text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sources as $source)
                        @php
                            $totalPooled = $source->activities->sum('pooled_amount');
                            $netAmount = $source->total_amount - $totalPooled;
                        @endphp
                        <tr>
                            <td class="align-middle">
                                <div class="d-flex flex-column">
                                    <span class="badge badge-light border text-muted mb-1 w-fit-content" style="font-size: 10px; width: fit-content;">
                                        {{ $source->budgetLineItem->budget_line_item_name ?? 'N/A' }}
                                    </span>
                                    <span class="text-dark font-weight-bold" style="font-size: 1rem;">{{ $source->name }}</span>
                                </div>
                            </td>
                            <td class="align-middle text-center">
                                @if($source->spreadsheet_id)
                                    <a href="https://docs.google.com/spreadsheets/d/{{ $source->spreadsheet_id }}" target="_blank" 
                                    class="btn btn-xs btn-outline-success rounded-pill px-2" data-toggle="tooltip" title="View Google Sheet">
                                        <i class="fas fa-file-excel mr-1"></i> Linked
                                    </a>
                                @else
                                    <span class="badge badge-light text-muted border py-1 px-2 rounded-pill">
                                        <i class="fas fa-keyboard mr-1"></i> Manual
                                    </span>
                                @endif
                            </td>
                            <td class="align-middle text-right font-weight-500">
                                ₱{{ number_format($source->total_amount, 2) }}
                            </td>
                            <td class="align-middle text-center">
                                @if($totalPooled > 0)
                                    <span class="badge badge-soft-danger py-2 px-3 rounded-pill text-danger" 
                                        style="background-color: #fceaea; border: 1px solid #f5c6cb; cursor: help;"
                                        data-toggle="tooltip" data-html="true" title="Pooled from activities">
                                        <i class="fas fa-arrow-down mr-1"></i> ₱{{ number_format($totalPooled, 2) }}
                                    </span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="align-middle text-center">
                                <span class="text-primary font-weight-bold" style="font-size: 1.1rem;">
                                    ₱{{ number_format($netAmount, 2) }}
                                </span>
                            </td>
                            <td class="align-middle text-center">
                                <span class="badge badge-dark py-1 px-2">{{ $source->fiscal_year }}</span>
                            </td> 
                            <td class="align-middle text-center">
                                <div class="action-btn-group">
                                    <button type="button" class="btn btn-action btn-edit edit-source-btn" 
                                        data-id="{{ $source->id }}" 
                                        data-name="{{ $source->name }}" 
                                        data-budget_line_item_id="{{ $source->budget_line_item_id }}"
                                        data-fiscal_year="{{ $source->fiscal_year }}"
                                        data-amount="{{ $source->total_amount }}" 
                                        data-sheetid="{{ $source->spreadsheet_id }}" 
                                        data-sheetname="{{ $source->sheet_name }}"
                                        data-toggle="tooltip" title="Edit Fund Source">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <button type="button" class="btn btn-action btn-delete delete-source-btn" 
                                        data-id="{{ $source->id }}" 
                                        data-name="{{ $source->name }}"
                                        data-count="{{ $source->activities->count() }}"
                                        data-toggle="tooltip" title="Delete Source">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Edit Fund source --}}
<div class="modal fade" id="modal-edit-source">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="edit-source-form" method="POST">
                @csrf @method('PUT')
                <div class="modal-header bg-info">
                    <h4 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Fund Source</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    {{-- Added: Budget Line Item Selection --}}
                    <div class="form-group">
                        <label class="font-weight-bold">Budget Line Item</label>
                        <select name="budget_line_item_id" id="edit_budget_line_item_id" class="form-control" required>
                            <option value="">-- Select Line Item --</option>
                            @foreach($budgetLineItems as $item)
                                <option value="{{ $item->id }}">{{ $item->budget_line_item_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label class="font-weight-bold">Source Name</label>
                                <input type="text" name="name" id="edit_name" class="form-control" placeholder="e.g. GOP, MOOE" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-bold">Fiscal Year</label>
                                <select name="fiscal_year" id="edit_fiscal_year" class="form-control" required>
                                    <option value="">-- Year --</option>
                                    @php 
                                        $currentYear = date('Y'); 
                                    @endphp
                                    @for($i = $currentYear - 2; $i <= $currentYear + 3; $i++)
                                        <option value="{{ $i }}">{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Allocated Amount</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light">₱</span>
                            </div>
                            {{-- Visual display for formatting --}}
                            <input type="text" class="form-control amount-mask-display" id="edit_display_amount" required>
                            {{-- Actual value sent to Controller --}}
                            <input type="hidden" name="total_amount" id="edit_raw_amount" class="amount-mask-raw">
                        </div>
                    </div>
                    
                    <div class="bg-light p-3 border rounded shadow-sm">
                        <h6 class="font-weight-bold text-info border-bottom pb-2">
                            <i class="fab fa-google-drive mr-1"></i> Google Sheet Config
                        </h6>
                        <div class="form-group">
                            <label class="small font-weight-bold">Spreadsheet ID</label>
                            <input type="text" name="spreadsheet_id" id="edit_spreadsheet_id" class="form-control form-control-sm" placeholder="Paste ID from URL">
                        </div>
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold">Sheet/Tab Name</label>
                            <input type="text" name="sheet_name" id="edit_sheet_name" class="form-control form-control-sm" placeholder="e.g. Sheet1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light">
                    <button type="button" class="btn btn-default shadow-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info shadow-sm">
                        <i class="fas fa-save mr-1"></i> Update Source
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Add Source Modal --}}
<div class="modal fade" id="modal-add-source">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('settings.fund_sources.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary">
                    <h4 class="modal-title text-white">Add New Fund Source</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold">Budget Line Item</label>
                        <select name="budget_line_item_id" class="form-control" required>
                            <option value="">-- Select Line Item --</option>
                            @foreach($budgetLineItems as $item)
                                <option value="{{ $item->id }}">{{ $item->budget_line_item_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Fund Source Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g., General Fund" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Fiscal Year</label>
                                <select name="fiscal_year" class="form-control" required>
                                    <option value="">-- Select Year --</option>
                                    {{-- Range: Last Year to 3 Years from now --}}
                                    @php 
                                        $currentYear = date('Y'); 
                                    @endphp
                                    @for($i = $currentYear - 2; $i <= $currentYear + 3; $i++)
                                        <option value="{{ $i }}">{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Initial Allocated Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                            {{-- Using your existing mask logic classes --}}
                            <input type="text" class="form-control amount-mask-display" placeholder="0.00" required>
                            <input type="hidden" name="total_amount" class="amount-mask-raw">
                        </div>
                    </div>

                    <div class="bg-light p-3 border rounded">
                        <h6 class="font-weight-bold text-muted small uppercase mb-3">
                            <i class="fab fa-google-drive mr-1"></i> Optional: Google Sheet Integration
                        </h6>
                        <div class="form-group">
                            <label class="small">Spreadsheet ID</label>
                            <input type="text" name="spreadsheet_id" class="form-control form-control-sm" placeholder="Paste ID from URL">
                        </div>
                        <div class="form-group mb-0">
                            <label class="small">Sheet/Tab Name</label>
                            <input type="text" name="sheet_name" class="form-control form-control-sm" placeholder="e.g., Sheet1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Fund Source</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteSourceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Confirm Deletion
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form id="deleteSourceForm" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <i class="fas fa-trash-alt text-danger fa-4x animate__animated animate__shakeX"></i>
                    </div>
                    <h5 class="text-dark">Are you sure?</h5>
                    <p class="text-muted">You are about to delete <span id="sourceNameDisplay" class="font-weight-bold text-danger"></span>.</p>
                    
                    <div class="badge badge-light border p-2 w-100" style="font-size: 0.9rem;">
                        <i class="fas fa-link mr-1"></i> Linked Activities: 
                        <span id="activityCountDisplay" class="text-primary font-weight-bold">0</span>
                    </div>
                    <p class="small text-muted mt-3 mb-0">This action cannot be undone and may affect linked reports.</p>
                </div>
                
                <div class="modal-footer bg-light justify-content-between">
                    <button type="button" class="btn btn-secondary px-4 shadow-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4 shadow-sm">
                        <i class="fas fa-check mr-1"></i> Yes, Delete it
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>

    $(document).ready(function() {

        // Auto-hide alerts after 5 seconds
        const $flashAlerts = $(".alert-success, .alert-danger");

        if ($flashAlerts.length > 0) {
            window.setTimeout(function() {
                $flashAlerts.fadeTo(500, 0).slideUp(500, function(){
                    $(this).remove(); 
                });
            }, 5000); // 5 seconds
        }
        
        // Logic for preserving tab on refresh
        $('a[data-toggle="pill"]').on('shown.bs.tab', function (e) {
            localStorage.setItem('activeTab', $(e.target).attr('href'));
        });
        var activeTab = localStorage.getItem('activeTab');
        if(activeTab){
            $('#settingsCustomTab a[href="' + activeTab + '"]').tab('show');
        }

        // Reuse your existing JS logic for Currency Masking and Budget Validation below
        function formatNumber(n) {
            return n.replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        $(document).on('input', '.amount-mask-display', function() {
            let displayInput = $(this);
            let rawInput = displayInput.siblings('.amount-mask-raw');
            let inputVal = displayInput.val();
            let numericVal = inputVal.replace(/[^0-9.]/g, ''); 
            if (numericVal.indexOf(".") >= 0) {
                let decimalPos = numericVal.indexOf(".");
                let leftSide = numericVal.substring(0, decimalPos);
                let rightSide = numericVal.substring(decimalPos);
                leftSide = formatNumber(leftSide);
                rightSide = rightSide.substring(0, 3);
                displayInput.val(leftSide + rightSide);
                rawInput.val(leftSide.replace(/,/g, '') + rightSide);
            } else {
                let formatted = formatNumber(numericVal);
                displayInput.val(formatted);
                rawInput.val(numericVal);
            }
        });

        $(document).on('blur', '.amount-mask-display', function() {
            let displayInput = $(this);
            let rawInput = displayInput.siblings('.amount-mask-raw');
            let val = parseFloat(rawInput.val());
            if(!isNaN(val)) {
                rawInput.val(val.toFixed(2));
                displayInput.val(val.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            }
            checkBalance();
        });

        function checkBalance() {
            let selected = $('#source_selector').find(":selected");
            if (!$('#source_selector').val()) {
                $('#balance_info').addClass('d-none');
                return;
            }
            let remaining = parseFloat(selected.data('remaining')) || 0;
            let sourceName = selected.data('name');
            let inputAmount = parseFloat($('#activity_form .amount-mask-raw').val()) || 0;
            $('#balance_info').removeClass('d-none');
            if (inputAmount > remaining) {
                $('#balance_info').removeClass('alert-info').addClass('alert-danger');
                $('#balance_text').html(`<strong><i class="fas fa-exclamation-triangle"></i> Over Budget!</strong> ${sourceName} only has ₱${remaining.toLocaleString(undefined, {minimumFractionDigits: 2})} remaining.`);
                $('#btn-save-activity').prop('disabled', true);
            } else {
                $('#balance_info').removeClass('alert-danger').addClass('alert-info');
                $('#balance_text').html(`<strong>Available:</strong> ₱${remaining.toLocaleString(undefined, {minimumFractionDigits: 2})} for ${sourceName}.`);
                $('#btn-save-activity').prop('disabled', false);
            }
        }

        $('#source_selector').on('change', checkBalance);

        $('.edit-source-btn').on('click', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const budgetLineId = $(this).data('budget_line_item_id'); // Added
            const year = $(this).data('fiscal_year');
            const amount = $(this).data('amount');
            const sheetId = $(this).data('sheetid');
            const sheetName = $(this).data('sheetname');

            // Set form action
            $('#edit-source-form').attr('action', `/settings/source/${id}`);

            // Fill fields
            $('#edit_name').val(name);
            
            // Set the Budget Line Item Dropdown
            // Ensure your select element has id="edit_budget_line_item_id"
            $('#edit_budget_line_item_id').val(budgetLineId).trigger('change'); 
            
            $('#edit_fiscal_year').val(year);
            $('#edit_display_amount').val(Number(amount).toLocaleString('en-US', {minimumFractionDigits: 2}));
            $('#edit_raw_amount').val(amount);
            $('#edit_spreadsheet_id').val(sheetId);
            $('#edit_sheet_name').val(sheetName);

            $('#modal-edit-source').modal('show');
        });
    });

    $(document).on('click', '.delete-source-btn', function() {
        const btn = $(this);
        const id = btn.data('id');
        const name = btn.data('name');
        const count = btn.data('count') || 0;

        // Populate the modal
        $('#sourceNameDisplay').text(name);
        $('#activityCountDisplay').text(count);

        // Set the form action
        let url = "{{ route('settings.fund_sources.destroy', ':id') }}".replace(':id', id);
        $('#deleteSourceForm').attr('action', url);

        // Check if the modal element actually exists before showing
        if ($('#deleteSourceModal').length > 0) {
            $('#deleteSourceModal').modal('show');
        } else {
            alert("Error: Modal HTML with id='deleteSourceModal' not found in page!");
        }
    });

    function openPoolModal(id, name, budget, currentPooled, currentRemarks) {
        $('#pool_activity_id').val(id);
        $('#pool_activity_name').text(name);
        $('#pool_max_display').text(budget.toLocaleString());
        $('#pool_input_amount').val(currentPooled);
        $('#pool_input_amount').attr('max', budget); 
        
        // Set the existing remarks if any
        $('#pool_remarks').val(currentRemarks); 
        
        $('#poolFundModal').modal('show');
    }

    function setFullPool() {
        // Get the raw number from the max display text
        let fullAmount = $('#pool_max_display').text().replace(/,/g, '');
        $('#pool_input_amount').val(fullAmount);
    }

    $(function () {
        // Standard initialization
        $('[data-toggle="tooltip"]').tooltip({
            html: true // This ensures the <b> and <br> tags are rendered as HTML
        });
    });
</script>