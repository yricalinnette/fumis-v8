@extends('layouts.adminlte')

@section('content')
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

    @if($errors->has('import_error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i> {{ $errors->first('import_error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="card card-primary card-outline card-tabs">
        <div class="card-header p-0 pt-1 border-bottom-0">
            <ul class="nav nav-tabs" id="settingsCustomTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="tabs-sources-tab" data-toggle="pill" href="#tabs-sources" role="tab">
                        <i class="fas fa-university mr-1"></i> Fund Sources
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tabs-templates-tab" data-toggle="pill" href="#tabs-templates" role="tab">
                        <i class="fas fa-file-excel mr-1"></i> Import Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tabs-activities-tab" data-toggle="pill" href="#tabs-activities" role="tab">
                        <i class="fas fa-tasks mr-1"></i> Activity Allocation
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tabs-employees-tab" data-toggle="pill" href="#tabs-employees" role="tab">
                        <i class="fas fa-users mr-1"></i> Employee/Staff
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body">
            <div class="tab-content" id="settingsCustomTabContent">
                <div class="tab-pane fade show active" id="tabs-sources" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="text-muted mb-0"><i class="fas fa-database mr-2"></i>Fund Source Registry</h5>
                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modal-add-source">
                            <i class="fas fa-plus-circle mr-1"></i> Add New Source
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover table-striped bg-white border">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 15%">Source Name</th>
                                    <th style="width: 20%">Spreadsheet ID</th>
                                    <th style="width: 15%">Tab Name</th>
                                    <th style="width: 15%" class="text-center">Allocation</th>
                                    <th style="width: 10%" class="text-center">Sync</th>
                                    <th style="width: 10%" class="text-center">FY</th> 
                                    <th style="width: 15%" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sources as $source)
                                <tr>
                                    <td class="align-middle"><strong>{{ $source->name }}</strong></td>
                                    
                                    <td class="align-middle">
                                        @if($source->spreadsheet_id)
                                            <code class="text-truncate d-inline-block"  title="{{ $source->spreadsheet_id }}">
                                                {{ $source->spreadsheet_id }}
                                            </code>
                                        @else
                                            <span class="text-muted small">N/A</span>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        @if($source->sheet_name)
                                            <span class="text-info"><i class="far fa-file-alt mr-1"></i> {{ $source->sheet_name }}</span>
                                        @else
                                            <span class="text-muted small">N/A</span>
                                        @endif
                                    </td>
                                    <td class="align-middle text-center font-weight-bold">
                                        ₱{{ number_format($source->total_amount, 2) }}
                                    </td>
                                    <td class="align-middle text-center ">
                                        @if($source->spreadsheet_id)
                                            <span class="badge badge-success"><i class="fas fa-sync-alt mr-1"></i> Linked</span>
                                        @else
                                            <span class="badge badge-secondary">Manual</span>
                                        @endif
                                    </td>
                                    <td class="align-middle text-center"><span class="badge badge-info">{{ $source->fiscal_year }}</span>
                                    </td> <td class="align-middle text-center">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-info edit-source-btn" 
                                                data-id="{{ $source->id }}" 
                                                data-name="{{ $source->name }}" 
                                                data-fiscal_year="{{ $source->fiscal_year }}" {{-- Added Data Attribute --}}
                                                data-amount="{{ $source->total_amount }}" 
                                                data-sheetid="{{ $source->spreadsheet_id }}" 
                                                data-sheetname="{{ $source->sheet_name }}">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <form action="{{ route('settings.source.destroy', $source->id) }}" method="POST" class="d-inline" 
                                                onsubmit="return confirm('Are you sure you want to delete this source? This action cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger ml-1" title="Delete Source">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- TAB 2: ACTIVITY ALLOCATION --}}
                <div class="tab-pane fade" id="tabs-activities" role="tabpanel">
                    <div id="balance_info" class="alert alert-info d-none mb-3">
                        <i class="fas fa-info-circle"></i> <span id="balance_text"></span>
                    </div>
                    <form action="{{ route('settings.activity.import') }}" method="POST" enctype="multipart/form-data" id="importForm">
                        @csrf
                        <div class="card card-outline card-success">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="wfp_file">Upload WFP Excel File</label>
                                    <div class="input-group">
                                        <div class="custom-file">
                                            <input type="file" name="wfp_file" class="custom-file-input" id="wfp_file" required>
                                            <label class="custom-file-label" for="wfp_file">Choose WFP Excel file...</label>
                                        </div>
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-file-import mr-1"></i> Import
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-info mt-2 d-block">
                                        <i class="fas fa-cog mr-1"></i> Current mapping uses header row <b>{{ $template->header_row }}</b>.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </form>
                    <form action="{{ route('settings.activity.store') }}" method="POST" id="activity_form" class="row">
                        @csrf
                        <div class="col-md-4">
                            <label>Fund Source</label>
                            <select name="source_of_fund_id" id="source_selector" class="form-control select2" required style="width: 100%;">
                                <option value="">-- Choose Source --</option>
                                @foreach($sources as $source)
                                    @php $rem = $source->total_amount - $source->activities->sum('budget'); @endphp
                                    <option value="{{ $source->id }}" data-remaining="{{ $rem }}" data-name="{{ $source->name }}">
                                        {{ $source->name }} (Available: ₱{{ number_format($rem, 2) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Activity Name</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Training Expenses">
                        </div>
                        <div class="col-md-3">
                            <label>Budget Limit</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                                <input type="text" class="form-control amount-mask-display" required>
                                <input type="hidden" name="budget" class="amount-mask-raw">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <label>&nbsp;</label>
                            <button type="submit" id="btn-save-activity" class="btn btn-success btn-block"><i class="fas fa-plus"></i></button>
                        </div>
                    </form>
                    <div class="mt-4">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr class="bg-dark text-white">
                                    <th>Activity Name</th>
                                    <th class="text-right">Alloted Budget</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activities->groupBy('source.name') as $sourceName => $groupedActivities)
                                    <tr class="bg-light font-weight-bold">
                                        <td colspan="2"><i class="fas fa-folder-open mr-2"></i> {{ $sourceName }}</td>
                                        <td class="text-center">Total: ₱{{ number_format($groupedActivities->sum('budget'), 2) }}</td>
                                    </tr>
                                    @foreach($groupedActivities as $activity)
                                    <tr>
                                        <td class="pl-5 text-secondary">{{ $activity->name }}</td>
                                        <td class="text-right text-primary">₱{{ number_format($activity->budget, 2) }}</td>
                                        <td class="text-center">
                                            <form action="{{ route('settings.activity.destroy', $activity->id) }}" method="POST">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- TAB 3: EMPLOYEES --}}
                <div class="tab-pane fade" id="tabs-employees" role="tabpanel">
                    <form action="{{ route('settings.employee.store') }}" method="POST" class="bg-light p-3 border rounded mb-4">
                        @csrf
                        <div class="row">
                            <div class="col-md-3"><label>First Name</label><input type="text" name="first_name" class="form-control" required></div>
                            <div class="col-md-2"><label>Middle Name</label><input type="text" name="middle_name" class="form-control"></div>
                            <div class="col-md-3"><label>Last Name</label><input type="text" name="last_name" class="form-control" required></div>
                            <div class="col-md-4">
                                <label>Position</label>
                                <div class="input-group">
                                    <input type="text" name="position" class="form-control" required>
                                    <div class="input-group-append"><button type="submit" class="btn btn-info">Add Employee</button></div>
                                </div>
                            </div>
                        </div>
                    </form>
                    <div class="table-responsive" style="max-height: 500px;">
                        <table class="table table-sm table-hover">
                            <thead class="bg-light sticky-top">
                                <tr><th>Full Name</th><th>Position</th></tr>
                            </thead>
                            <tbody>
                                @foreach($employees as $employee)
                                <tr>
                                    <td>{{ $employee->full_name }}</td>
                                    <td><span class="badge badge-info">{{ $employee->position }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- TAB 4: IMPORT SETTINGS (DYNAMIC TEMPLATE) --}}
                <div class="tab-pane fade" id="tabs-templates" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="text-muted mb-0"><i class="fas fa-sliders-h mr-2"></i>WFP Template Configuration</h5>
                    </div>

                    {{-- Use the ID from the database, or default to 1 for the first-time setup --}}
                    <form action="{{ route('settings.template.update', $template->id ?? 1) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            {{-- Header Settings --}}
                            <div class="col-md-4">
                                <div class="card card-outline card-info shadow-sm">
                                    <div class="card-header"><h3 class="card-title font-weight-bold">Header Settings</h3></div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label>Header Row Index</label>
                                            {{-- Logic: Old Input > Database Value > Default (15) --}}
                                            <input type="number" name="header_row" class="form-control" 
                                                value="{{ old('header_row', $template->header_row ?? 15) }}">
                                            <small class="text-muted">The row number where column titles exist in Excel.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Column Mapping --}}
                            <div class="col-md-8">
                                <div class="card card-outline card-primary shadow-sm">
                                    <div class="card-header">
                                        <h3 class="card-title font-weight-bold">Column Mapping (Exact Excel Header Names)</h3>
                                    </div>
                                    <div class="card-body p-0">
                                        <table class="table table-sm table-striped mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="pl-3 py-2">Database Field</th>
                                                    <th class="py-2">Excel Header Name</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td class="pl-3 align-middle font-weight-bold">Budget Line Item</td>
                                                    <td>
                                                        <input type="text" name="budget_line_col" class="form-control form-control-sm" 
                                                            value="{{ old('budget_line_col', $template->budget_line_col ?? 'BUDGET LINE ITEM') }}">
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="pl-3 align-middle font-weight-bold">Objective</td>
                                                    <td>
                                                        <input type="text" name="objective_col" class="form-control form-control-sm" 
                                                            value="{{ old('objective_col', $template->objective_col ?? 'OBJECTIVE') }}">
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="pl-3 align-middle font-weight-bold">Activity Name</td>
                                                    <td>
                                                        <input type="text" name="activity_col" class="form-control form-control-sm" 
                                                            value="{{ old('activity_col', $template->activity_col ?? 'ACTIVITIES TO ATTAIN THE SUCESS INDICATORS') }}">
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="pl-3 align-middle font-weight-bold">Cost / Budget</td>
                                                    <td>
                                                        <input type="text" name="budget_col" class="form-control form-control-sm" 
                                                            value="{{ old('budget_col', $template->budget_col ?? 'COST') }}">
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="pl-3 align-middle font-weight-bold">Source of Fund</td>
                                                    <td>
                                                        <input type="text" name="source_col" class="form-control form-control-sm" 
                                                            value="{{ old('source_col', $template->source_col ?? 'SOURCE OF FUND') }}">
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="card-footer bg-white border-top">
                                        <button type="submit" class="btn btn-primary float-right shadow-sm">
                                            <i class="fas fa-save mr-1"></i> Update Mapping
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

{{-- Modal remains the same --}}
<div class="modal fade" id="modal-edit-source">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="edit-source-form" method="POST">
                @csrf @method('PUT')
                <div class="modal-header bg-info">
                    <h4 class="modal-title">Edit Fund Source</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Source Name</label>
                                <input type="text" name="name" id="edit_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Fiscal Year</label>
                                <select name="fiscal_year" class="form-control" required>
                                    <option value="">-- Select Year --</option>
                                    <option value="2025">2025</option>
                                    <option value="2026">2026</option>
                                    <option value="2027">2027</option>
                                    <option value="2024">2028</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Allocated Amount</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                            <input type="text" class="form-control amount-mask-display" id="edit_display_amount" required>
                            <input type="hidden" name="total_amount" id="edit_raw_amount" class="amount-mask-raw">
                        </div>
                    </div>
                    
                    <div class="bg-light p-3 border rounded">
                        <h6 class="font-weight-bold"><i class="fab fa-google-drive mr-1"></i> Google Sheet Config</h6>
                        <div class="form-group">
                            <label class="small">Spreadsheet ID</label>
                            <input type="text" name="spreadsheet_id" id="edit_spreadsheet_id" class="form-control form-control-sm">
                        </div>
                        <div class="form-group mb-0">
                            <label class="small">Sheet/Tab Name</label>
                            <input type="text" name="sheet_name" id="edit_sheet_name" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Source</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Add Source Modal --}}
<div class="modal fade" id="modal-add-source">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('settings.source.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary">
                    <h4 class="modal-title text-white">Add New Fund Source</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Source Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g., General Fund" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Fiscal Year</label>
                                <select name="fiscal_year" class="form-control" required>
                                    <option value="">-- Select Year --</option>
                                    <option value="2025">2025</option>
                                    <option value="2026">2026</option>
                                    <option value="2027">2027</option>
                                    <option value="2024">2028</option>
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


@endsection

@section('js')
<script>
    $(document).ready(function() {
        
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
            const year = $(this).data('fiscal_year'); // New
            const amount = $(this).data('amount');
            const sheetId = $(this).data('sheetid');
            const sheetName = $(this).data('sheetname');

            // Set form action
            $('#edit-source-form').attr('action', `/settings/source/${id}`);

            // Fill fields
            $('#edit_name').val(name);
            $('#edit_fiscal_year').val(year);
            $('#edit_display_amount').val(Number(amount).toLocaleString('en-US', {minimumFractionDigits: 2}));
            $('#edit_raw_amount').val(amount);
            $('#edit_spreadsheet_id').val(sheetId);
            $('#edit_sheet_name').val(sheetName);

            $('#modal-edit-source').modal('show');
        });
    });

    $(document).ready(function () {
        // 1. Fix the file label name update
        $('.custom-file-input').on('change', function() {
            let fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').addClass("selected").html(fileName);
        });

        // 2. Add spinner logic to the Import form
        $('#importForm').on('submit', function() {
            let btn = $(this).find('button[type="submit"]');
            
            // Change button text and add spinner icon
            btn.prop('disabled', true);
            btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
            
            return true; // allow form to submit
        });
    });
</script>
@endsection