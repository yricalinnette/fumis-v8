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
                {{-- <li class="nav-item">
                    <a class="nav-link" id="tabs-templates-tab" data-toggle="pill" href="#tabs-templates" role="tab">
                        <i class="fas fa-file-excel mr-1"></i> WFP Template Settings
                    </a>
                </li> --}}
                <li class="nav-item">
                    <a class="nav-link" id="tabs-activities-tab" data-toggle="pill" href="#tabs-activities" role="tab">
                        <i class="fas fa-tasks mr-1"></i> Activity Allocation (WFP)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tabs-realignment-tab" data-toggle="pill" href="#tabs-realignment" role="tab">
                        <i class="fas fa-random mr-1"></i> Budget Realignment
                    </a>
                </li>
                {{-- <li class="nav-item">
                    <a class="nav-link" id="tabs-employees-tab" data-toggle="pill" href="#tabs-employees" role="tab">
                        <i class="fas fa-users mr-1"></i> User Registration
                    </a>
                </li> --}}
                
            </ul>
        </div>

        <div class="card-body">
            <div class="tab-content" id="settingsCustomTabContent">

                {{-- TAB 1: Fund Source Registry --}}
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
                                    <th style="width: 15%">Sync Info</th>
                                    <th style="width: 15%" class="text-right">Original Allotment</th>
                                    <th style="width: 10%" class="text-center">Pooled Funds</th>
                                    <th style="width: 20%" class="text-center">Net Allotment</th>
                                    <th style="width: 5%" class="text-center">FY</th> 
                                    <th style="width: 10%" class="text-center">Action</th>
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
                                        <strong>{{ $source->name }}</strong>
                                    </td>
                                    
                                    <td class="align-middle small">
                                        @if($source->spreadsheet_id)
                                            <div class="text-info mb-1"><i class="fas fa-link mr-1"></i> Linked</div>
                                            <code class="text-truncate d-block"title="{{ $source->spreadsheet_id }}">
                                                {{ $source->spreadsheet_id }}
                                            </code>
                                        @else
                                            <span class="text-muted"><i class="fas fa-keyboard mr-1"></i> Manual</span>
                                        @endif
                                    </td>

                                    <td class="align-middle text-right text-muted">
                                        ₱{{ number_format($source->total_amount, 2) }}
                                    </td>

                                    <td class="align-middle text-center">
                                        @if($totalPooled > 0)
                                            @php
                                                $remarksContent = "<b>Pooled Remarks:</b><br>";
                                                foreach($source->activities->where('pooled_amount', '>', 0) as $act) {
                                                    // Using a div wrapper for better spacing inside the tooltip
                                                    $remarksContent .= "<div class='text-left mb-1'>• " . e($act->name) . ": " . e($act->pooled_remarks ?? 'No remarks') . "</div>";
                                                }
                                            @endphp
                                            
                                            <span class="badge badge-danger p-2" 
                                                style="cursor: pointer;"
                                                data-toggle="tooltip" 
                                                data-html="true" 
                                                data-placement="auto" {{-- Let Bootstrap find the best open space --}}
                                                data-container="body" {{-- Prevents the tooltip from being inside the table cell --}}
                                                title="{{ $remarksContent }}">
                                                <i class="fas fa-arrow-down mr-1"></i> ₱{{ number_format($totalPooled, 2) }}
                                            </span>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>

                                    <td class="align-middle text-center font-weight-bold text-navy">
                                        ₱{{ number_format($netAmount, 2) }}
                                    </td>

                                    <td class="align-middle text-center">
                                        <span class="badge badge-info">{{ $source->fiscal_year }}</span>
                                    </td> 

                                    <td class="align-middle text-center">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-info edit-source-btn" 
                                                data-id="{{ $source->id }}" 
                                                data-name="{{ $source->name }}" 
                                                data-fiscal_year="{{ $source->fiscal_year }}"
                                                data-amount="{{ $source->total_amount }}" 
                                                data-sheetid="{{ $source->spreadsheet_id }}" 
                                                data-sheetname="{{ $source->sheet_name }}">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <form action="{{ route('settings.source.destroy', $source->id) }}" method="POST" class="d-inline" 
                                                onsubmit="return confirm('Are you sure you want to delete this source?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
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

                {{-- Activity Allocation --}}
                <div class="tab-pane fade" id="tabs-activities" role="tabpanel">
    
                    <div id="balance_info" class="alert alert-info d-none mb-3">
                        <i class="fas fa-info-circle"></i> <span id="balance_text"></span>
                    </div>

                    {{-- IMPORT SECTION --}}
                    <form action="{{ route('settings.activity.import') }}" method="POST" enctype="multipart/form-data" id="importForm">
                        @csrf
                        <div class="card card-outline card-success shadow-sm">
                            <div class="card-header">
                                <h3 class="card-title font-weight-bold">
                                    <i class="fas fa-file-excel mr-2"></i>Bulk Import WFP (FY {{ $currentYear }})
                                </h3>
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
                                    
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label>Default Fund Source (FY {{ $currentYear }})</label>
                                            <select name="fund_source_id" class="form-control select2" required>
                                                <option value="">-- Select Current Source --</option>
                                                @foreach($sources->where('fiscal_year', $currentYear) as $source)
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
                                                        <i class="fas fa-file-import mr-1"></i> Import
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    {{-- MANUAL ENCODING SECTION --}}
                    <div class="card card-outline card-primary shadow-sm mt-3">
                        <div class="card-header p-0">
                            <button class="btn btn-link btn-block text-left text-dark font-weight-bold py-2 px-3" 
                                    type="button" 
                                    data-toggle="collapse" 
                                    data-target="#manualEncodingCollapse" 
                                    aria-expanded="false" 
                                    aria-controls="manualEncodingCollapse">
                                <i class="fas fa-edit mr-2 text-primary"></i> 
                                MANUAL WFP ENCODING
                                <i class="fas fa-chevron-down float-right mt-1 text-muted"></i>
                            </button>
                        </div>

                        <div id="manualEncodingCollapse" class="collapse">
                            <form action="{{ route('settings.activity.storeWfp') }}" method="POST">
                                @csrf
                                <div class="card-body">
                                    <div class="row">
                                        {{-- Objective --}}
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Objective</label>
                                                <select name="objective" class="form-control select2" required style="width: 100%;">
                                                    <option value="">-- Select Objective --</option>
                                                    @foreach($objectives as $obj)
                                                        <option value="{{ $obj }}">{{ $obj }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        {{-- Budget Line Item --}}
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Budget Line Item</label>
                                                <select name="budget_line_item" class="form-control select2" required style="width: 100%;">
                                                    <option value="">-- Select Line Item --</option>
                                                    @foreach($budgetLineItems as $item)
                                                        <option value="{{ $item }}">{{ $item }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        {{-- Fund Source --}}
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Fund Source</label>
                                                <select name="source_of_fund_id" class="form-control select2" required style="width: 100%;">
                                                    <option value="">-- Select Fund Source --</option>
                                                    @foreach($fundSources as $fs)
                                                        <option value="{{ $fs->id }}">{{ $fs->name }} (FY {{ $fs->fiscal_year }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        {{-- UACS Code --}}
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>UACS Code</label>
                                                <input type="number" name="uacs_code" class="form-control" placeholder="e.g. 5020101000">
                                            </div>
                                        </div>

                                        {{-- Activity Name --}}
                                        <div class="col-md-7">
                                            <div class="form-group">
                                                <label>Activity Name</label>
                                                <textarea name="name" class="form-control" rows="3" placeholder="Enter activity description" required></textarea>
                                            </div>
                                        </div>

                                        {{-- Cost/Budget --}}
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Cost / Budget (Original)</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">₱</span>
                                                    </div>
                                                    <input type="number" name="budget_amount" step="0.01" class="form-control" placeholder="0.00" required>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Timeframe & Quarters --}}
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Start Date</label>
                                                <input type="date" name="start_date" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>End Date</label>
                                                <input type="date" name="end_date" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="d-block">Target Quarters</label>
                                                <div class="d-flex justify-content-between border rounded p-2 bg-light">
                                                    @foreach(['Q1', 'Q2', 'Q3', 'Q4'] as $q)
                                                        <div class="custom-control custom-checkbox">
                                                            <input class="custom-control-input" type="checkbox" name="target_quarters[]" id="encoding{{ $q }}" value="{{ $q }}">
                                                            <label for="encoding{{ $q }}" class="custom-control-label font-weight-normal">{{ $q }}</label>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-right">
                                    <button type="submit" class="btn btn-primary shadow-sm">
                                        <i class="fas fa-save mr-1"></i> Save Activity
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <hr class="my-4">

                    {{-- START OF ACTIVITY GROUPS --}}
                    <div id="activitiesAccordion">
                        {{-- FIX: Restored the Outer Loop grouping by Fiscal Year --}}
                        @foreach($activities->groupBy('source.fiscal_year') as $year => $yearGroups)
                            @php 
                                $isCurrent = ($year == $currentYear); 
                                $collapseId = "collapseYear" . $year;
                            @endphp
                            
                            <div class="card card-dark card-outline mb-4">
                                <div class="card-header p-0">
                                    <button class="btn btn-link btn-block text-left text-dark font-weight-bold py-2 px-3" 
                                            type="button" data-toggle="collapse" data-target="#{{ $collapseId }}">
                                        <i class="fas {{ $isCurrent ? 'fa-folder-open' : 'fa-folder' }} mr-2 text-warning"></i> 
                                        FISCAL YEAR {{ $year }} 
                                        @if($isCurrent) <span class="badge badge-success ml-2">Current</span> @endif
                                    </button>
                                </div>

                                <div id="{{ $collapseId }}" class="collapse {{ $isCurrent ? 'show' : '' }}">
                                    <div class="card-body p-0">
                                        
                                        {{-- Grouping by Source Name within the Year --}}
                                        @foreach($yearGroups->groupBy('source.name') as $sourceName => $groupedActivities)
                                            @php
                                                $source = $groupedActivities->first()->source;
                                                $totalPooled = $groupedActivities->sum('pooled_amount');

                                                // 1. Get the IDs of activities belonging to this specific source group
                                                $activityIds = $groupedActivities->pluck('id');

                                                // 2. Sum obligations strictly by activity IDs and selected year
                                                $totalObligations = \App\Models\Fund::whereIn('transaction_type_id', $activityIds)
                                                    ->whereYear('obligation_date', $year) 
                                                    ->sum('obligation_amount');

                                                // 3. CORRECTED DISTRIBUTED CALCULATION
                                                // Sum of all budget_adjusted for these activities
                                                $sumDistributedRaw = $groupedActivities->sum('budget_adjusted');
                                                
                                                // Total Distributed minus what was pooled back
                                                $netDistributedTotal = $sumDistributedRaw - $totalPooled;
                                                
                                                // If you still need to see the Source's original starting balance
                                                $originalSourceTotal = $source->total_amount; 
                                                $pooledAdjusted = $originalSourceTotal - $totalPooled;
                                            @endphp

                                            <div class="mx-3 mt-3 mb-2">
                                                <div class="callout callout-danger py-2 bg-light shadow-sm border-left-3">
                                                    <div class="row align-items-center">
                                                        <div class="col-md-3">
                                                            <h6 class="mb-0 font-weight-bold">
                                                                <i class="fas fa-university mr-2 text-secondary"></i>{{ $sourceName }}
                                                            </h6>
                                                        </div>
                                                        <div class="col-md-9 text-right">
                                                            <span class="mr-3">
                                                                <small class="text-muted font-weight-bold">DISTRIBUTED/ADJUSTED AMOUNT:</small>
                                                                    <span class="ml-1 font-weight-bold">
                                                                        <span class="text-primary" title="Net Distributed to Activities (Total minus Pooled)">
                                                                            ₱{{ number_format($netDistributedTotal, 2) }}
                                                                        </span>
                                                                        <span class="text-muted mx-1">/</span>
                                                                        <span class="text-dark" title="Total Original Source Fund Allotment">
                                                                            ₱{{ number_format($pooledAdjusted, 2) }}
                                                                        </span>
                                                                    </span>
                                                            </span>

                                                            <span class="mr-3">
                                                                <small class="text-muted font-weight-bold">POOLED:</small>
                                                                <span class="text-danger font-weight-bold ml-1">₱{{ number_format($totalPooled, 2) }}</span>
                                                            </span>

                                                            <span class="mr-3">
                                                                <small class="text-muted font-weight-bold">OBLIGATED:</small>
                                                                <span class="text-orange font-weight-bold ml-1">₱{{ number_format($totalObligations, 2) }}</span>
                                                            </span>

                                                            {{-- <span class="badge badge-dark p-2">
                                                                <small class="font-weight-bold">SOURCE BAL:</small>
                                                                <span class="ml-1">₱{{ number_format($effectiveSourceFund, 2) }}</span>
                                                            </span> --}}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <table class="table table-bordered table-sm m-0 mb-4">
                                                <thead>
                                                    <tr class="bg-gray-light">
                                                        <th class="pl-3">Activity Details</th>
                                                        <th class="text-right" style="width: 250px;">Budget (Distributed / Adjusted)</th>
                                                        <th class="text-center" style="width: 120px;">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($groupedActivities as $activity)
                                                        @php
                                                            $netAllotment = $activity->budget_adjusted - $activity->pooled_amount;
                                                            $hasTransactions = \App\Models\Fund::where('transaction_type_id', $activity->id)->exists();
                                                        @endphp
                                                        <tr>
                                                            <td class="pl-4">
                                                                <i class="fas fa-caret-right mr-1 text-muted"></i> {{ $activity->name }}
                                                                @if($activity->pooled_amount > 0)
                                                                    <small class="text-danger font-italic ml-2">(₱{{ number_format($activity->pooled_amount, 2) }} pooled)</small>
                                                                @endif
                                                            </td>
                                                            <td class="text-right">
                                                                @if($activity->pooled_amount > 0)
                                                                    <span class="text-muted small" style="text-decoration: line-through;">
                                                                        ₱{{ number_format($activity->budget_adjusted, 2) }}
                                                                    </span>
                                                                    <div class="font-weight-bold text-primary">
                                                                        ₱{{ number_format($netAllotment, 2) }}
                                                                    </div>
                                                                @else
                                                                    <span class="text-primary font-weight-bold">
                                                                        ₱{{ number_format($activity->budget_adjusted, 2) }}
                                                                    </span>
                                                                @endif
                                                            </td>
                                                            <td class="text-center">
                                                                @if($isCurrent && !$hasTransactions)
                                                                    <button type="button" class="btn btn-xs btn-warning" 
                                                                        onclick="openPoolModal({{ $activity->id }}, '{{ addslashes($activity->name) }}', {{ $activity->budget_adjusted }}, {{ $activity->pooled_amount }}, '{{ addslashes($activity->pooled_remarks) }}')">
                                                                        <i class="fas fa-hand-holding-usd"></i>
                                                                    </button>
                                                                    <form action="{{ route('settings.activity.destroy', $activity->id) }}" method="POST" class="d-inline">
                                                                        @csrf @method('DELETE')
                                                                        <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Confirm deletion?')">
                                                                            <i class="fas fa-trash"></i>
                                                                        </button>
                                                                    </form>
                                                                @else
                                                                    <i class="fas fa-lock text-muted" title="Locked"></i>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr class="bg-light">
                                                        <td class="text-right font-weight-bold">Total Amount:</td>
                                                        <td class="text-right font-weight-bold text-dark">
                                                            ₱{{ number_format($netDistributedTotal, 2) }}
                                                        </td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        @endforeach {{-- End Source Loop --}}

                                    </div>
                                </div>
                            </div>
                        @endforeach {{-- End Year Loop --}}
                    </div>
                </div>

                {{-- TAB 3: User Registration --}}
                {{-- <div class="tab-pane fade" id="tabs-employees" role="tabpanel">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="card card-primary card-outline">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-user-plus mr-1"></i> Register New User</h3>
                                </div>
                                <form action="{{ route('settings.register.employee') }}" method="POST">
                                    @csrf
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="employee_select">
                                                <i class="fas fa-search-user mr-1 text-primary"></i> Search Employee
                                            </label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text bg-white"><i class="fas fa-user-tie"></i></span>
                                                </div>
                                                <select name="empid" id="employee_select" class="form-control select2" required>
                                                    <option value="">-- Start typing name --</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="username"><i class="fas fa-user-circle mr-1 text-primary"></i> Login Username</label>
                                                        <input type="text" name="username" id="username" class="form-control" placeholder="Generated username..." required>
                                                        <small class="form-text text-muted">Auto-generated based on selected employee.</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="password"><i class="fas fa-lock mr-1 text-primary"></i> Password</label>
                                                        <div class="input-group">
                                                            <input type="password" name="password" id="password" class="form-control" required>
                                                            <div class="input-group-append">
                                                                <button class="btn btn-outline-secondary" type="button" id="toggle_password">
                                                                    <i class="fas fa-eye" id="password_icon"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="custom-control custom-checkbox mb-3">
                                                        <input type="checkbox" class="custom-control-input" id="use_default_password">
                                                        <label class="custom-control-label font-weight-normal" for="use_default_password" style="cursor: pointer;">
                                                            Use default password <span class="badge badge-light border text-monospace">Zaq12wsx</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <input type="hidden" name="password_confirmation" id="password_confirmation">
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <button type="submit" class="btn btn-primary float-right">Create Account</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="col-md-5">
                            <div id="preview_card" class="card card-widget widget-user-2 shadow-sm" style="display: none;">
                                <div class="widget-user-header bg-info">
                                    <div class="widget-user-image">
                                        <div id="preview_initials" class="img-circle elevation-2 d-flex align-items-center justify-content-center bg-white text-info font-weight-bold" style="width: 65px; height: 65px; font-size: 24px; float: left;">
                                            --
                                        </div>
                                    </div>
                                    <div class="ml-5 pl-4">
                                        <h3 id="preview_name" class="widget-user-username font-weight-bold" style="font-size: 1.5rem; margin-left: 15px;">Employee Name</h3>
                                        <h5 id="preview_position" class="widget-user-desc" style="margin-left: 15px; opacity: 0.9;">Position</h5>
                                    </div>
                                </div>
                                <div class="card-footer p-0">
                                    <ul class="nav flex-column">
                                        <li class="nav-item">
                                            <span class="nav-link text-dark">
                                                Section: <span id="preview_section" class="float-right badge bg-primary">N/A</span>
                                            </span>
                                        </li>
                                        <li class="nav-item">
                                            <span class="nav-link text-dark">
                                                Division: <span id="preview_division" class="float-right badge bg-success">N/A</span>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div id="preview_placeholder" class="text-center p-5">
                                <i class="fas fa-id-card fa-4x mb-3 d-block"></i>
                                <p class="text-muted mt-2">Select an employee to see their details</p>
                            </div>

                            <div id="preview_card" class="card card-widget widget-user-2 shadow-sm" style="display: none;">
                                </div>
                        </div>
                    </div>
                </div> --}}

                {{-- TAB 4: WFP TEMPLATE SETTINGS (DYNAMIC TEMPLATE)
                <div class="tab-pane fade" id="tabs-templates" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="text-muted mb-0"><i class="fas fa-sliders-h mr-2"></i>WFP Template Configuration</h5>
                    </div>

                    <form action="{{ route('settings.template.update', $template->id ?? 1) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row"> --}}
                            {{-- Header Settings --}}
                            {{-- <div class="col-md-4">
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
                            </div> --}}

                            {{-- Column Mapping --}}
                            {{-- <div class="col-md-12">
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
                                                </tr> --}}
                                                {{-- NEW UACS CODE ROW --}}
                                                {{-- <tr>
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
                </div> --}}

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


<div class="modal fade" id="poolFundModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <form action="{{ route('settings.activity.pool') }}" method="POST">
            @csrf
            <input type="hidden" name="activity_id" id="pool_activity_id">
            <div class="modal-content shadow-lg">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-university mr-2"></i>Pool Funds</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Activity: <strong id="pool_activity_name" class="text-primary"></strong></p>
                    <div class="form-group">
                        <label>Enter Amount to Pool:</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                            <input type="number" step="0.01" name="amount" id="pool_input_amount" class="form-control" required>
                        </div>
                        <small class="text-muted">Maximum available: ₱<span id="pool_max_display"></span></small>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-block btn-sm" onclick="setFullPool()">
                        <i class="fas fa-arrow-down mr-1"></i> Pool Entire Activity Budget
                    </button>
                    <div class="form-group mt-3">
                        <label>Reason for Pooling:</label>
                        <textarea name="remarks" id="pool_remarks" class="form-control" rows="3" placeholder="e.g., Activity cancelled, excess funds, or realignment..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-warning font-weight-bold">Confirm Transaction</button>
                </div>
            </div>
        </form>
    </div>
</div>



@endsection

@section('js')
<script>
    $(document).ready(function() {

        // Auto-hide alerts after 5 seconds
        window.setTimeout(function() {
            $(".alert-success").fadeTo(500, 0).slideUp(500, function(){
                $(this).remove(); 
            });
        }, 5000);
        
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

    // $(document).ready(function() {
    //     // 1. Initialize Select2 with AJAX Search
    //     const employeeSelect = $('#employee_select').select2({
    //         minimumInputLength: 3,
    //         placeholder: 'Search by First Name or Last Name...',
    //         allowClear: true,
    //         theme: 'bootstrap4', 
    //         width: '95%', // Changed to 100% to ensure it fills the container
    //         ajax: {
    //             url: "{{ route('employees.external.search') }}",
    //             dataType: 'json',
    //             delay: 250,
    //             data: function (params) {
    //                 return { q: params.term };
    //             },
    //             processResults: function (data) {
    //                 return { results: data };
    //             },
    //             cache: true
    //         }
    //     });

    //     // --- AUTO-FOCUS SEARCH BOX ON OPEN ---
    //     $(document).on('select2:open', function() {
    //         setTimeout(() => {
    //             document.querySelector('.select2-search__field').focus();
    //         }, 10);
    //     });

    //     // --- AUTO-OPEN SELECT2 WHEN TAB IS CLICKED ---
    //     $('a[data-toggle="pill"], a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    //         if ($(e.target).attr('href') === '#tabs-employees') {
    //             employeeSelect.select2('open');
    //         }
    //     });

    //     // 2. Listen for Selection to Update Preview
    //     $('#employee_select').on('select2:select', function (e) {
    //         let empid = e.params.data.id; // This is the dbedid

    //         $.get("{{ url('settings/employees/external/details') }}/" + empid, function(data) {
                
    //             // UI Toggle
    //             $('#preview_placeholder').hide();
    //             $('#preview_card').fadeIn();

    //             /**
    //              * Cleaning Helper
    //              * Now handles the UPPERCASE strings returned from the encrypted DB
    //              */
    //             const clean = (str) => {
    //                 if (!str || str === null) return ""; 
    //                 return str.toString()
    //                     .normalize("NFD")
    //                     .replace(/[\u0300-\u036f]/g, "") // Remove accents
    //                     .replace(/[^a-zA-Z\s]/g, "");    // Keep only letters and spaces
    //             };

    //             // --- USERNAME GENERATION (Force Lowercase) ---
    //             // Example: "JUAN DELA" -> ["JUAN", "DELA"] -> "jd"
    //             let firstNames = clean(data.fname).trim().split(/\s+/); 
    //             let fInitials = firstNames.map(name => name.charAt(0)).join('').toLowerCase();
                
    //             // Middle Initial logic
    //             let mInitial = data.mname ? clean(data.mname).trim().charAt(0).toLowerCase() : '';
                
    //             // Last Name logic: "CRUZ" -> "cruz"
    //             let lName = clean(data.lname).replace(/\s+/g, '').toLowerCase();

    //             // Result: jdccruz
    //             let username = fInitials + mInitial + lName;
    //             $('input[name="username"]').val(username);

    //             // --- Update Preview Card ---
    //             // Initials for Avatar (Always Uppercase)
    //             let avatarInitials = (clean(data.fname).charAt(0) + clean(data.lname).charAt(0)).toUpperCase();
    //             $('#preview_initials').text(avatarInitials);

    //             // Display Name and Details (Using data directly from AES_DECRYPT)
    //             $('#preview_name').text(data.name);
    //             $('#preview_position').text(data.position);
    //             $('#preview_section').text(data.section);
    //             $('#preview_division').text(data.division);
    //         });
    //     });

    //     // 3. Handle Clear
    //     $('#employee_select').on('select2:clear', function (e) {
    //         $('#preview_card').hide();
    //         $('#preview_placeholder').fadeIn();
    //         $('input[name="username"]').val('');
    //         $('#use_default_password').prop('checked', false).trigger('change');
    //     });

    //     // 4. Default Password Logic
    //     $('#use_default_password').on('change', function() {
    //         const passFields = $('#password, #password_confirmation');
    //         if ($(this).is(':checked')) {
    //             passFields.val("Zaq12wsx").attr('readonly', true);
    //             $('#password').attr('type', 'text');
    //             $('#password_icon').removeClass('fa-eye').addClass('fa-eye-slash');
    //         } else {
    //             passFields.val('').attr('readonly', false);
    //             $('#password').attr('type', 'password');
    //             $('#password_icon').removeClass('fa-eye-slash').addClass('fa-eye');
    //         }
    //     });

    //     // 5. Toggle Password Visibility
    //     $('#toggle_password').on('click', function() {
    //         if ($('#use_default_password').is(':checked')) return;
    //         let input = $('#password');
    //         let icon = $('#password_icon');
            
    //         if (input.attr('type') === 'password') {
    //             input.attr('type', 'text');
    //             icon.removeClass('fa-eye').addClass('fa-eye-slash');
    //         } else {
    //             input.attr('type', 'password');
    //             icon.removeClass('fa-eye-slash').addClass('fa-eye');
    //         }
    //     });
    // });

    // $(document).ready(function() {
    //     const defaultPass = "Zaq12wsx";

    //     // 1. Default Password Checkbox Logic
    //     $('#use_default_password').on('change', function() {
    //         const isChecked = $(this).is(':checked');
            
    //         if (isChecked) {
    //             // Fill both fields (confirmation included for backend validation)
    //             $('#password, #password_confirmation').val(defaultPass).attr('readonly', true);
    //             // Change to text so user can see it's filled correctly
    //             $('#password').attr('type', 'text');
    //             $('#password_icon').removeClass('fa-eye').addClass('fa-eye-slash');
    //         } else {
    //             $('#password, #password_confirmation').val('').attr('readonly', false);
    //             $('#password').attr('type', 'password');
    //             $('#password_icon').removeClass('fa-eye-slash').addClass('fa-eye');
    //         }
    //     });

    //     // 2. Show/Hide Password Toggle Logic
    //     $('#toggle_password').on('click', function() {
    //         // Don't toggle if default password is being used (optional)
    //         if ($('#use_default_password').is(':checked')) return;

    //         const passInput = $('#password');
    //         const icon = $('#password_icon');

    //         if (passInput.attr('type') === 'password') {
    //             passInput.attr('type', 'text');
    //             icon.removeClass('fa-eye').addClass('fa-eye-slash');
    //         } else {
    //             passInput.attr('type', 'password');
    //             icon.removeClass('fa-eye-slash').addClass('fa-eye');
    //         }
    //     });
    // });

</script>
@endsection