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
                        <i class="fas fa-tasks mr-1"></i> Activity Allocation (WFP)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tabs-employees-tab" data-toggle="pill" href="#tabs-employees" role="tab">
                        <i class="fas fa-users mr-1"></i> Employee/Staff
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tabs-realignment-tab" data-toggle="pill" href="#tabs-realignment" role="tab">
                        <i class="fas fa-random mr-1"></i> Budget Realignment
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
                                                <button type="button" 
                                                    class="btn btn-sm btn-outline-danger delete-source-btn" 
                                                    data-id="{{ $source->id }}" 
                                                    data-name="{{ $source->name }}" 
                                                    data-count="{{ $source->activities->count() }}"
                                                    title="Delete Source">
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

                    {{-- IMPORT SECTION --}}
                    <form action="{{ route('settings.activity.import') }}" method="POST" enctype="multipart/form-data" id="importForm">
                        @csrf
                        <div class="card card-outline card-success shadow-sm">
                            <div class="card-header">
                                <h3 class="card-title font-weight-bold"><i class="fas fa-file-excel mr-2"></i>Bulk Import WFP</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <a href="{{ route('settings.template.download') }}" class="btn btn-info btn-sm">
                                            <i class="fas fa-download"></i> Download Sample WFP Template
                                        </a>
                                        <small class="text-muted d-block mt-1">
                                            * Use this template to ensure your Fund Source names match our records.
                                        </small>
                                    </div>
                                    
                                    {{-- Added the required Fund Source Selector for the Import --}}
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label>Default Fund Source (Backup)</label>
                                            <select name="fund_source_id" class="form-control select2" required>
                                                <option value="">-- Select Source --</option>
                                                @foreach($sources as $source)
                                                    <option value="{{ $source->id }}">{{ $source->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-7">
                                        <div class="form-group">
                                            <label for="wfp_file">Choose WFP Excel File</label>
                                            <div class="input-group">
                                                <div class="custom-file">
                                                    <input type="file" name="wfp_file" class="custom-file-input" id="wfp_file" required>
                                                    <label class="custom-file-label" for="wfp_file">Choose file...</label>
                                                </div>
                                                <div class="input-group-append">
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="fas fa-file-import mr-1"></i> Import Data
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <small class="text-info d-block">
                                    <i class="fas fa-cog mr-1"></i> Current mapping expects headers on row <b>{{ $template->header_row ?? 1 }}</b>.
                                </small>
                            </div>
                        </div>
                    </form>

                    <hr class="my-4">

                    {{-- MANUAL ENTRY FORM (Existing) --}}
                    {{-- <h5 class="text-muted mb-3"><i class="fas fa-keyboard mr-2"></i>Manual Activity Entry</h5>
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
                    </form> --}}
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
                                        <td class="text-right text-primary">₱{{ number_format($activity->budget_adjusted, 2) }}</td>
                                        <td class="text-center">
                                            @php
                                                $isLocked = \App\Models\Fund::where('source_of_fund_id', $activity->source_of_fund_id)
                                                            ->where('transaction_type_id', $activity->id)
                                                            ->exists();
                                            @endphp

                                            @if(!$isLocked)
                                                <form action="{{ route('settings.activity.destroy', $activity->id) }}" method="POST">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Confirm deletion?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <button class="btn btn-sm btn-secondary" title="Locked: Transactions exist for this source" disabled>
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            @endif
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
                                                    <td class="pl-3 align-middle font-weight-bold">Objective</td>
                                                    <td>
                                                        <input type="text" name="objective_col" class="form-control form-control-sm" 
                                                            value="{{ old('objective_col', $template->objective_col ?? 'OBJECTIVE') }}">
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="pl-3 align-middle font-weight-bold">Budget Line Item</td>
                                                    <td>
                                                        <input type="text" name="budget_line_col" class="form-control form-control-sm" 
                                                            value="{{ old('budget_line_col', $template->budget_line_col ?? 'BUDGET LINE ITEM') }}">
                                                    </td>
                                                </tr>
                                                {{-- NEW UACS CODE ROW --}}
                                                <tr>
                                                    <td class="pl-3 align-middle font-weight-bold">
                                                        UACS Code <i class="fas fa-info-circle text-xs text-muted" title="Unified Accounts Code Structure"></i>
                                                    </td>
                                                    <td>
                                                        <input type="text" name="uacs_col" class="form-control form-control-sm" 
                                                            value="{{ old('uacs_col', $template->uacs_col ?? 'UACS CODE') }}">
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="pl-3 align-middle font-weight-bold">Activity Name</td>
                                                    <td>
                                                        <input type="text" name="activity_col" class="form-control form-control-sm" 
                                                            value="{{ old('activity_col', $template->activity_col ?? 'ACTIVITIES TO ATTAIN THE SUCCESS INDICATORS') }}">
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

                {{-- TAB 5: BUDGET REALIGNMENT --}}
                <div class="tab-pane fade" id="tabs-realignment" role="tabpanel">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="text-muted"><i class="fas fa-sync-alt mr-2"></i>Realignment Tool</h5>
                                <p class="small text-secondary">
                                    Select a fund source to view and redistribute its unobligated balances for 
                                    <span class="badge badge-info font-weight-bold">FY {{ date('Y') }}</span>.
                                </p>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label>Select Fund Source to Realign</label>
                                    <select id="realign_source_selector" class="form-control select2" style="width: 100%;">
                                        <option value="">-- Choose Source --</option>
                                        @php $hasCurrentYearSources = false; @endphp
                                        
                                        @foreach($sources as $source)
                                            @if($source->fiscal_year == date('Y'))
                                                <option value="{{ $source->id }}">
                                                    {{ $source->name }} (FY {{ $source->fiscal_year }})
                                                </option>
                                                @php $hasCurrentYearSources = true; @endphp
                                            @endif
                                        @endforeach
                                    </select>
                                    
                                    @if(!$hasCurrentYearSources)
                                        <div class="alert alert-light border mt-2 py-2 small text-warning">
                                            <i class="fas fa-exclamation-circle mr-1"></i> No fund sources found for the current year.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- This container will be populated via AJAX based on the selection --}}
                    <div id="realignment_container" class="border rounded bg-light-alt" style="min-height: 300px; border-style: dashed !important;">
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-hand-pointer fa-3x mb-3 text-secondary opacity-50"></i>
                            <h5 class="font-weight-bold">Ready to Realign</h5>
                            <p>Select a current year fund source above to start redistributing balances.</p>
                        </div>
                    </div>
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
                                <select name="fiscal_year" id="edit_fiscal_year" class="form-control" required>
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

<div class="modal fade" id="importSummaryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document"> {{-- Changed to modal-lg for better table visibility --}}
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-file-import mr-2"></i> Import Results Summary</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center p-4">
                <div class="mb-3">
                    <i class="fas fa-check-circle text-success fa-4x"></i>
                </div>
                <h4 class="font-weight-bold">WFP Processed Successfully!</h4>
                <p class="text-muted">The Excel data has been synchronized with your database.</p>
                
                <div class="row mt-4">
                    <div class="col-4 border-right">
                        <h3 class="text-primary font-weight-bold">{{ session('import_summary.created', 0) }}</h3>
                        <span class="text-uppercase small font-weight-bold text-muted">New Activities</span>
                    </div>
                    <div class="col-4 border-right">
                        <h3 class="text-info font-weight-bold">{{ session('import_summary.updated', 0) }}</h3>
                        <span class="text-uppercase small font-weight-bold text-muted">Updated Records</span>
                    </div>
                    <div class="col-4">
                        <h3 class="text-danger font-weight-bold">{{ session('import_summary.failures', 0) }}</h3>
                        <span class="text-uppercase small font-weight-bold text-muted">Issues/Warnings Found</span>
                    </div>
                </div>

                @if(session('import_notes') && count(session('import_notes')) > 0)
                    <hr class="mt-4">
                    <h6 class="text-left font-weight-bold text-muted mb-2 uppercase small">Issue Details & Row Warnings:</h6>
                    <div class="table-responsive border rounded" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0 text-left">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th class="pl-3">Row</th>
                                    <th>Reason / Warning</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(session('import_notes') as $note)
                                    <tr>
                                        <td class="pl-3">{{ $note['row'] }}</td>
                                        <td class="text-secondary small">{{ $note['reason'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mt-2 text-left">
                        <i class="fas fa-info-circle mr-1 text-warning"></i> 
                        Rows with issues may have been skipped or imported with a ₱0.00 budget. Please review the table above.
                    </p>
                @endif
                
                <div class="mt-4 p-3 bg-light rounded-pill d-inline-block px-5 border">
                    <span class="mb-0 font-weight-bold">Total Rows Processed: {{ session('import_summary.total', 0) }}</span>
                </div>
            </div>
            <div class="modal-footer bg-white border-0">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
                {{-- <a href="#tabs-realignment" class="btn btn-primary" data-toggle="pill">Go to Realignment</a> --}}
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="deleteSourceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i> Confirm Deletion</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="deleteSourceForm" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-body text-center p-4">
                    <i class="fas fa-folder-minus text-danger fa-4x mb-3"></i>
                    <h4>Are you sure?</h4>
                    <p class="text-muted">You are about to delete <strong id="sourceNameDisplay" class="text-dark"></strong>.</p>
                    
                    <div class="alert alert-warning border-warning">
                        <i class="fas fa-info-circle mr-1"></i>
                        This will also permanently delete <strong><span id="activityCountDisplay"></span> activities</strong> linked to this source.
                    </div>
                    <p class="small text-danger">This action cannot be undone if no transactions exist.</p>
                </div>
                <div class="modal-footer bg-light justify-content-between">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">Yes, Delete Everything</button>
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

    $(document).ready(function() {
        // Check if the import_summary session exists
        @if(session('import_summary'))
            $('#importSummaryModal').modal('show');
        @endif
    });

    $('#realign_source_selector').on('change', function() {
        let sourceId = $(this).val();
        if (!sourceId) return;

        let container = $('#realignment_container');
        container.html('<div class="text-center py-5"><i class="fas fa-sync fa-spin fa-2x"></i></div>');

        // Use a template-friendly URL structure
        let fetchUrl = "{{ url('admin/settings/get-realignment-table') }}/" + sourceId;

        $.get(fetchUrl, function(data) {
            container.html(data);
        }).fail(function(xhr) {
            console.log(xhr.responseText); // This will show the actual error in the console
            container.html('<div class="alert alert-danger">Route not found (404). Check web.php.</div>');
        });
    });

    $(document).ready(function() {
        $('.delete-source-btn').on('click', function() {
            // Get data from button
            let id = $(this).data('id');
            let name = $(this).data('name');
            let count = $(this).data('count');
            
            // Update Modal Content
            $('#sourceNameDisplay').text(name);
            $('#activityCountDisplay').text(count);
            
            // Update Form Action URL (Adjust 'sources' to your actual route name)
            let deleteUrl = "{{ route('settings.source.destroy', ':id') }}";
            deleteUrl = deleteUrl.replace(':id', id);
            $('#deleteSourceForm').attr('action', deleteUrl);
            
            // Show Modal
            $('#deleteSourceModal').modal('show');
        });
    });


</script>
@endsection