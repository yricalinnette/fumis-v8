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

    /* Styling Select2 result rows */
    .select2-results__option {
        padding: 10px 15px !important;
        border-bottom: 1px solid #f8f9fa;
        font-size: 0.9rem;
    }

    .select2-results__option--highlighted {
        background-color: #007bff !important;
    }

    /* Ensure the badge looks consistent */
    .select2-results__option .badge {
        font-size: 0.7rem;
        padding: 4px 6px;
        letter-spacing: 0.5px;
    }

    /* Match the height with your other modal inputs */
    .select2-container--bootstrap4 .select2-selection--single {
        height: calc(2.25rem + 2px) !important;
        display: flex;
        align-items: center;
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
                 <!-- <li class="nav-item">
                    <a class="nav-link" id="tabs-templates-tab" data-toggle="pill" href="#tabs-templates" role="tab">
                        <i class="fas fa-file-excel mr-1"></i> WFP Template Settings
                    </a>
                </li>  -->
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
                <li class="nav-item">
                    <a class="nav-link" id="tabs-signatories-tab" data-toggle="pill" href="#tabs-signatories" role="tab">
                        <i class="fas fa-users mr-1"></i> WFP Signatories
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body">
            <div class="tab-content" id="settingsCustomTabContent">

                {{-- Activity Allocation --}}
                <div class="tab-pane fade" id="tabs-activities" role="tabpanel">

                    <div id="balance_info" class="alert alert-info d-none mb-3">
                        <i class="fas fa-info-circle"></i> <span id="balance_text"></span>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12 text-right">
                            <button type="button" class="btn btn-success btn-add-wfp">
                                <i class="fas fa-plus"></i> Add New WFP
                            </button>
                        </div>
                    </div>
                    <hr class="my-4">

                    {{-- START OF ACTIVITY GROUPS --}}
                    <div id="activitiesAccordion">
                        @foreach($activities->groupBy('source.fiscal_year') as $year => $yearGroups)
                            @php 
                                $isCurrent = ($year == $currentYear); 
                                $collapseId = "collapseYear" . $year;
                            @endphp
                            
                            <div class="card card-dark card-outline mb-4">
                                <div class="card-header p-0">
                                    <div class="d-flex align-items-center pr-3">
                                        <button class="btn btn-link btn-block text-left text-dark font-weight-bold py-2 px-3" 
                                                type="button" data-toggle="collapse" data-target="#{{ $collapseId }}">
                                            <i class="fas {{ $isCurrent ? 'fa-folder-open' : 'fa-folder' }} mr-2 text-warning"></i> 
                                            FISCAL YEAR {{ $year }} 
                                            @if($isCurrent) <span class="badge badge-success ml-2">Current</span> @endif
                                        </button>
                                        
                                        {{-- NEW: Print Full Year Button --}}
                                        <a href="{{ route('settings.print', ['year' => $year]) }}" ...>
                                            <i class="fas fa-file-pdf mr-1"></i> Print FY {{ $year }} WFP
                                        </a>
                                    </div>
                                </div>

                                <div id="{{ $collapseId }}" class="collapse {{ $isCurrent ? 'show' : '' }}">
                                    <div class="card-body p-0">
                                        
                                        @foreach($yearGroups->groupBy('source.name') as $sourceName => $groupedActivities)
                                            @php
                                                $source = $groupedActivities->first()->source;
                                                $totalPooled = $groupedActivities->sum('pooled_amount');
                                                $activityIds = $groupedActivities->pluck('id');

                                                $totalObligations = \App\Models\Fund::whereIn('transaction_type_id', $activityIds)
                                                    ->whereYear('obligation_date', $year) 
                                                    ->sum('obligation_amount');

                                                $sumDistributedRaw = $groupedActivities->sum('budget_adjusted');
                                                $netDistributedTotal = $sumDistributedRaw - $totalPooled;
                                                $originalSourceTotal = $source->total_amount; 
                                                $pooledAdjusted = $originalSourceTotal - $totalPooled;
                                            @endphp

                                            <div class="mx-3 mt-4 mb-3">
                                                <div class="card card-outline card-primary shadow-sm">
                                                    <div class="card-header bg-navy py-2">
                                                        <div class="row align-items-center">
                                                            <div class="col-md-4">
                                                                <h5 class="mb-0 font-weight-bold text-white">
                                                                    <i class="fas fa-university mr-2 text-warning"></i>
                                                                    {{ strtoupper($sourceName) }}
                                                                </h5>
                                                            </div>

                                                            <div class="col-md-8 text-right">
                                                                <div class="d-flex justify-content-end align-items-center">
                                                                    <div class="px-3 border-right border-secondary">
                                                                        <small class="text-gray d-block text-uppercase font-weight-bold" style="font-size: 0.65rem;">Distributed / Adjusted</small>
                                                                        <span class="text-white font-weight-bold">
                                                                            <span class="text-warning">₱{{ number_format($netDistributedTotal, 2) }}</span>
                                                                            <small class="mx-1">/</small>
                                                                            ₱{{ number_format($pooledAdjusted, 2) }}
                                                                        </span>
                                                                    </div>
                                                                    
                                                                    <div class="px-3 border-right border-secondary text-center">
                                                                        <small class="text-gray d-block text-uppercase font-weight-bold" style="font-size: 0.65rem;">Pooled</small>
                                                                        <span class="text-danger font-weight-bold">₱{{ number_format($totalPooled, 2) }}</span>
                                                                    </div>

                                                                    <div class="pl-3 text-center">
                                                                        <small class="text-gray d-block text-uppercase font-weight-bold" style="font-size: 0.65rem;">Obligated</small>
                                                                        <span class="text-orange font-weight-bold">₱{{ number_format($totalObligations, 2) }}</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <table class="table table-bordered table-sm m-0 mb-4 shadow-sm">
                                                <thead>
                                                    <tr class="bg-gray-light text-center">
                                                        <th rowspan="2" style="width: 15%; vertical-align: middle;">OBJECTIVE</th>
                                                        <th rowspan="2" style="width: 25%; vertical-align: middle;">ACTIVITIES</th>
                                                        <th colspan="2" style="width: 15%;">TIMEFRAME</th>
                                                        <th colspan="4" style="width: 15%;">TARGETS</th>
                                                        <th rowspan="2" style="width: 10%; vertical-align: middle;">COST</th>
                                                        <th rowspan="2" style="width: 12%; vertical-align: middle;">ACTIONS</th>
                                                    </tr>
                                                    <tr class="bg-gray-light text-center">
                                                        <th>Start</th><th>End</th>
                                                        <th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($groupedActivities->groupBy('objective') as $objective => $activitiesByObjective)
                                                        @foreach($activitiesByObjective as $index => $activity)
                                                            @php
                                                                $hasTransactions = \App\Models\Fund::where('transaction_type_id', $activity->id)->exists();
                                                                $targets = is_array($activity->physical_targets) ? $activity->physical_targets : json_decode($activity->physical_targets, true) ?? [];
                                                            @endphp
                                                            <tr>
                                                                @if($index === 0)
                                                                    <td rowspan="{{ $activitiesByObjective->count() }}" class="align-top font-weight-bold p-2 bg-white">
                                                                        {{ $objective }}
                                                                    </td>
                                                                @endif

                                                                <td class="p-2">
                                                                    {{ $activity->name }}
                                                                    @if($activity->pooled_amount > 0)
                                                                        <div class="small text-danger font-italic">(₱{{ number_format($activity->pooled_amount, 2) }} pooled)</div>
                                                                    @endif
                                                                </td>

                                                                <td class="text-center">{{ \Carbon\Carbon::parse($activity->start_date)->format('d M Y') }}</td>
                                                                <td class="text-center">{{ \Carbon\Carbon::parse($activity->end_date)->format('d M Y') }}</td>

                                                                <td class="text-center">{{ $targets['Q1'] ?? '' }}</td>
                                                                <td class="text-center">{{ $targets['Q2'] ?? '' }}</td>
                                                                <td class="text-center">{{ $targets['Q3'] ?? '' }}</td>
                                                                <td class="text-center">{{ $targets['Q4'] ?? '' }}</td>

                                                                <td class="text-right font-weight-bold">
                                                                    ₱{{ number_format($activity->budget_adjusted - $activity->pooled_amount, 2) }}
                                                                </td>

                                                                <td class="text-center">
                                                                    <div class="btn-group">
                                                                        {{-- INDIVIDUAL PRINT BUTTON --}}
                                                                        {{-- <a href="{{ route('settings.print', ['id' => $activity->id]) }}" target="_blank" class="btn btn-xs btn-danger">
                                                                            <i class="fas fa-file-pdf"></i>
                                                                        </a> --}}

                                                                        <button type="button" class="btn btn-xs btn-info" onclick="openEditWfpModal({{ $activity->id }}, {{ $hasTransactions ? 'true' : 'false' }})">
                                                                            <i class="fas fa-edit"></i>
                                                                        </button>
                                                                        
                                                                        @if(!$hasTransactions)
                                                                            <button type="button" class="btn btn-xs btn-warning" onclick="openPoolModal({{ $activity->id }}, '{{ addslashes($activity->name) }}', {{ $activity->budget_adjusted }}, {{ $activity->pooled_amount }})">
                                                                                <i class="fas fa-hand-holding-usd"></i>
                                                                            </button>
                                                                            <form action="{{ route('settings.activity.destroy', $activity->id) }}" method="POST" class="d-inline">
                                                                                @csrf @method('DELETE')
                                                                                <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Delete this activity?')">
                                                                                    <i class="fas fa-trash"></i>
                                                                                </button>
                                                                            </form>
                                                                        @else
                                                                            <span class="badge badge-secondary"><i class="fas fa-lock"></i></span>
                                                                        @endif
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>



            {{-- WFP SIGNATORIES --}}
            <div class="tab-pane fade" id="tabs-signatories" role="tabpanel" aria-labelledby="tabs-signatories-tab">
                <div class="card mt-3">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title text-bold mb-0">
                                <i class="fas fa-users-cog mr-1"></i> Signatory Management
                            </h3>
                            <button type="button" class="btn btn-primary btn-sm" onclick="addSignatoryModal()">
                                <i class="fas fa-plus mr-1"></i> Add Signatory Rule
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr class="bg-gray-light">
                                    <th style="width: 20%">WFP Type</th>
                                    <th style="width: 20%">Label</th>
                                    <th style="width: 35%">Assigned Official</th>
                                    <th style="width: 15%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($signatorySettings as $setting)
                                <tr>
                                    <td class="text-uppercase text-bold text-primary">{{ $setting->wfp_type }}</td>
                                    <td>{{ $setting->label }}</td>
                                    <td>
                                        <span class="text-bold">{{ $setting->employee_name }}</span><br>
                                        <small class="text-muted">{{ $setting->designation }}</small>
                                    </td>
                                    
                                    <td>
                                        <button class="btn btn-info btn-xs" onclick="editSignatory({{ $setting->id }})">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-xs" onclick="deleteSignatory({{ $setting->id }})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted">
                            <i class="fas fa-info-circle mr-1"></i> 
                            <strong>Program</strong> type usually has 2 signatories. <strong>SAA/Consolidated</strong> usually has 3.
                        </small>
                    </div>
                </div>
            </div>

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

{{-- MANUAL ENCODING MODAL (Add & Edit) --}}
<div class="modal fade" id="modalManualEncoding" tabindex="-1" role="dialog" aria-labelledby="modalManualEncodingLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalManualEncodingLabel">
                    <i class="fas fa-edit mr-2"></i> Add New WFP Activity
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form id="editWfpForm" action="{{ route('settings.activity.storeWfp') }}" method="POST">
                @csrf
                <div id="method_field"></div> {{-- Placeholder for @method('PUT') when editing --}}
                
                <div class="modal-body">
                    <div class="row">
                        <input type="hidden" id="edit_activity_id" name="id">

                        {{-- Budget Line Item --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Budget Line Item</label>
                                <select name="budget_line_item_id" id="edit_budget_line_item" class="form-control select2-modal" required style="width: 100%;">
                                    <option value="">-- Select Line Item --</option>
                                    @foreach($budgetLineItems as $item)
                                        <option value="{{ $item->id }}">{{ $item->budget_line_item_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Fund Source (Initially Disabled) --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Fund Source</label>
                                <select name="source_of_fund_id" id="edit_source_of_fund_id" class="form-control select2-modal" required style="width: 100%;" disabled>
                                    <option value="">-- Select Budget Line First --</option>
                                    @foreach($fundSources as $fs)
                                        {{-- The data-line attribute is key for the filter --}}
                                        <option value="{{ $fs->id }}" data-line="{{ $fs->budget_line_item_id }}">
                                            {{ $fs->name }} (FY {{ $fs->fiscal_year }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Activity Type / Mandate --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Activity Classification</label>
                                <select name="classification" id="edit_classification" class="form-control select2-modal" required style="width: 100%;">
                                    <option value="">-- Select Classification --</option>
                                    <option value="Strategic">Strategic Function</option>
                                    <option value="Core">Core Function</option>
                                    <option value="Support">Support Function</option>
                                </select>
                            </div>
                        </div>
                        
                        {{-- Objective --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold text-dark">Objective</label>
                                <select name="objective" id="edit_objective" class="form-control select2-modal" required style="width: 100%;">
                                    <option value="">-- Select Objective --</option>
                                    @foreach($objectives as $obj)
                                        <option value="{{ $obj['objectives'] }}">{{ $obj['objectives'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        
                        {{-- Cost / Budget --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Cost / Budget (Original)</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text bg-light">₱</span></div>
                                    <input type="text" 
                                        name="budget_amount" 
                                        id="edit_budget_amount" 
                                        class="form-control price-format" 
                                        placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        {{-- UACS Code --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">UACS Code / Account Title</label>
                                <select name="uacs_code_id" id="edit_uacs_code_id" class="form-control select2-modal" required style="width: 100%;">
                                    <option value="">-- Search Code or Title --</option>
                                    @foreach($uacsCodes as $uacs)
                                        <option value="{{ $uacs->id }}" data-class="{{ $uacs->allotment_class }}" data-code="{{ $uacs->uacs_code }}">
                                            [{{ $uacs->allotment_class }}] {{ $uacs->uacs_code }} - {{ $uacs->account_title }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Activity Name --}}
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="font-weight-bold">Activity Name</label>
                                <textarea name="name" id="edit_name" class="form-control" rows="3" placeholder="Enter activity description" required></textarea>
                            </div>
                        </div>

                        {{-- Timeframe --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Start Date</label>
                                <input type="date" name="start_date" id="edit_start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">End Date</label>
                                <input type="date" name="end_date" id="edit_end_date" class="form-control" required>
                            </div>
                        </div>

                        {{-- Target Quarters & Numeric Targets --}}
                        <div class="col-md-12 mt-3">
                            <label class="font-weight-bold">Target Quarters & Physical Targets</label>
                            <div class="row border rounded p-3 bg-light mx-0">
                                @foreach(['Q1', 'Q2', 'Q3', 'Q4'] as $q)
                                    <div class="col-md-3">
                                        <div class="form-group mb-0">
                                            <div class="custom-control custom-checkbox mb-2">
                                                <input class="custom-control-input q-checkbox" type="checkbox" 
                                                    name="target_quarters[]" id="edit_check_{{ strtolower($q) }}" value="{{ $q }}">
                                                <label for="edit_check_{{ strtolower($q) }}" class="custom-control-label font-weight-bold">{{ $q }} Target</label>
                                            </div>
                                            <input type="number" 
                                                name="targets[{{ $q }}]" 
                                                id="edit_input_{{ strtolower($q) }}" 
                                                class="form-control form-control-sm q-input" 
                                                placeholder="0" min="0" disabled>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" id="btn_submit_wfp" class="btn btn-primary shadow-sm">
                        <i class="fas fa-save mr-1"></i> Save Activity
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Signatory Modal --}}
<div class="modal fade" id="modal-signatory">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="signatoryModalTitle">Add Signatory Rule</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="signatoryForm">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label>WFP Type</label>
                        <select name="wfp_type" class="form-control" required>
                            <option value="program">Cluster / Program / Unit</option>
                            <option value="saa">Consolidated / SAA</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Label</label>
                        <input type="text" name="label" class="form-control" placeholder="e.g., Prepared by:" required>
                    </div>

                    <div class="form-group">
                        <label for="employee_search">Search Official (from db_common)</label>
                        <select class="form-control select2-employee" name="empid" id="employee_search" style="width: 100%;">
                            <option value="">Search by Name...</option>
                        </select>
                        <small class="text-muted">Type at least 3 characters to search the personnel database.</small>
                    </div>

                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Signatory</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Import Summary Results --}}
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

    $(document).ready(function() {
        // Initialize Select2 specifically for the modal
        $('.select2-modal').select2({
            theme: 'bootstrap4',
            dropdownParent: $('#modalManualEncoding')
        });

        $(document).on('select2:open', () => {
            // We use a small delay because Bootstrap Modals and Select2 
            // fight for focus when the dropdown animation starts.
            setTimeout(() => {
                const searchField = document.querySelector('.select2-container--open .select2-search__field');
                if (searchField) {
                    searchField.focus();
                }
            }, 50); // 50ms is the "sweet spot" for most browsers
        });

        // Reset form when modal is closed
        $('#modalManualEncoding').on('hidden.bs.modal', function () {
            $(this).find('form').trigger('reset');
            $('.select2-modal').val(null).trigger('change');
        });
    });

    $(document).ready(function() {
        // Corrected Selectors
        const $budgetLineSelect = $('#edit_budget_line_item');
        const $fundSourceSelect = $('#edit_source_of_fund_id');
        
        // Initial Select2 Initialization
        $('.select2-modal').select2({
            theme: 'bootstrap4',
            dropdownParent: $('#modalManualEncoding')
        });

        // Store a "master list" of fund source options for filtering
        const $allFundOptions = $fundSourceSelect.find('option').clone();

        // Listen for Budget Line changes
        $budgetLineSelect.on('change', function() {
            const selectedLineId = $(this).val();

            // Destroy Select2 before modifying the underlying <select>
            if ($fundSourceSelect.data('select2')) {
                $fundSourceSelect.select2('destroy');
            }

            if (selectedLineId) {
                // Clear current and add placeholder
                $fundSourceSelect.empty().append('<option value="">-- Select Fund Source --</option>');

                // Filter the master list
                const $filteredOptions = $allFundOptions.filter(function() {
                    return $(this).data('line') == selectedLineId;
                });

                $fundSourceSelect.append($filteredOptions);
                $fundSourceSelect.prop('disabled', false); // Enable for user selection
            } else {
                // Reset to default state if no budget line is selected
                $fundSourceSelect.empty().append('<option value="">-- Select Budget Line First --</option>');
                $fundSourceSelect.val('').prop('disabled', true);
            }

            // Re-initialize Select2
            $fundSourceSelect.select2({
                theme: 'bootstrap4',
                dropdownParent: $('#modalManualEncoding')
            });
        });

        // --- Add Button Integration ---
        $('.btn-add-wfp').on('click', function() {
            // Force the dropdown back to its initial disabled state
            $fundSourceSelect.empty().append('<option value="">-- Select Budget Line First --</option>')
                            .val('').prop('disabled', true).trigger('change');
        });
    });

    $(document).ready(function() {
        $('#modal_uacs_select').select2({
            theme: 'bootstrap4',
            placeholder: "-- Search Code, Title, or Class --",
            allowClear: true,
            dropdownParent: $('#modalManualEncoding'), // Fixes focus issue in Bootstrap Modals
            
            // 1. Search Logic: Checks Title, Code, and the 'data-class' attribute
            matcher: function(params, data) {
                if ($.trim(params.term) === '') { return data; }
                if (typeof data.text === 'undefined') { return null; }

                var searchTerm = params.term.toLowerCase();
                var optionText = data.text.toLowerCase();
                var allotmentClass = $(data.element).data('class') ? $(data.element).data('class').toLowerCase() : '';

                // Match if search term is found in the visible text OR the allotment class
                if (optionText.indexOf(searchTerm) > -1 || allotmentClass.indexOf(searchTerm) > -1) {
                    return data;
                }
                return null;
            },

            // 2. Visual Template: Adds the colored badge in the dropdown list
            templateResult: function(data) {
                if (!data.id) { return data.text; }
                
                var allotmentClass = $(data.element).data('class');
                var badgeClass = 'badge-secondary'; // Default
                
                // Color coding based on class
                if(allotmentClass === 'PS') badgeClass = 'badge-primary';
                if(allotmentClass === 'MOOE') badgeClass = 'badge-success';
                if(allotmentClass === 'CO') badgeClass = 'badge-danger';

                // Strip the bracketed text from the display string to avoid duplication
                var cleanText = data.text.replace('[' + allotmentClass + '] ', '');

                var $result = $(
                    '<span><span class="badge ' + badgeClass + ' mr-2" style="width: 50px;">' + 
                    allotmentClass + '</span>' + cleanText + '</span>'
                );
                return $result;
            }
        });
    });

    function openEditWfpModal(activityId, hasTransactions) {
        let url = "{{ url('settings/activity') }}/" + activityId + "/edit";

        $.get(url, function(data) {
            // 0. RESET UI & ENABLE ALL (Start clean)
            $('#editWfpForm input, #editWfpForm select, #editWfpForm textarea').prop('disabled', false);
            $('#modalManualEncodingLabel').html('<i class="fas fa-edit mr-2"></i> EDIT WFP ACTIVITY');

            // 1. Basic Fields Mapping
            $('#edit_activity_id').val(data.id);
            $('#edit_name').val(data.name);
            // Inside openEditWfpModal $.get callback:
            let rawBudget = data.budget_original || data.budget_adjusted || 0;

            // Convert to formatted string: 1000 -> 1,000.00
            let formattedBudget = parseFloat(rawBudget).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            $('#edit_budget_amount').val(formattedBudget);
            
            if (data.start_date) $('#edit_start_date').val(data.start_date.split('T')[0]);
            if (data.end_date) $('#edit_end_date').val(data.end_date.split('T')[0]);

            // 2. Select2 Fields & Dependency Filtering
            $('#edit_objective').val(data.objective).trigger('change');
            if (data.uacs_code_id) $('#edit_uacs_code_id').val(data.uacs_code_id).trigger('change');

            // Trigger Budget Line first to filter the Fund Source options
            $('#edit_budget_line_item').val(data.budget_line_item_id).trigger('change');
            // Set the specific Fund Source value
            $('#edit_source_of_fund_id').val(data.source_of_fund_id).trigger('change');

            // Map the classification value from database to the dropdown
            if (data.classification) {
                $('#edit_classification').val(data.classification).trigger('change');
            } else {
                $('#edit_classification').val('').trigger('change');
            }

            // --- 3. APPLY RESTRICTION RULES (With Timing Fix) ---
            
            // Budget Line and Fund Source are ALWAYS disabled on Edit
            setTimeout(function() {
                $('#edit_budget_line_item').prop('disabled', true);
                $('#edit_source_of_fund_id').prop('disabled', true);
                $('.select2-modal').trigger('change.select2');
            }, 100);

            // Check for Transactions, Manual Lock, OR Pooled Amount
            let isRestricted = hasTransactions || data.is_locked || (data.pooled_amount > 0);

            if (isRestricted) {
                // Disable core fields
                $('#edit_objective, #edit_uacs_code_id, #edit_name, #edit_budget_amount, #edit_classification').prop('disabled', true);
                
                // Update Label to show specific reason
                let lockReason = "Restricted";
                if (data.pooled_amount > 0) lockReason = "Restricted - Amount Pooled";
                else if (hasTransactions) lockReason = "Restricted - Transactions Exist";

                
                $('#modalManualEncodingLabel').html(`<i class="fas fa-lock mr-2"></i> EDIT ACTIVITY (${lockReason})`);
            }

            // 4. Physical Targets Logic
            let targets = typeof data.physical_targets === 'string' 
                ? JSON.parse(data.physical_targets) 
                : (data.physical_targets || {});

            ['Q1', 'Q2', 'Q3', 'Q4'].forEach(q => {
                let lowerQ = q.toLowerCase();
                let hasValue = targets && (targets[q] !== undefined && targets[q] !== null && targets[q] !== "");

                if (hasValue) {
                    $(`#edit_check_${lowerQ}`).prop('checked', true);
                    $(`#edit_input_${lowerQ}`).prop('disabled', false).val(targets[q]);
                } else {
                    $(`#edit_check_${lowerQ}`).prop('checked', false);
                    $(`#edit_input_${lowerQ}`).prop('disabled', true).val(''); 
                }
            });

            $('#modalManualEncoding').modal('show');
        });
    }

    $(document).ready(function() {

        // --- ADD BUTTON LOGIC ---
        $('.btn-add-wfp').on('click', function(e) {
            e.preventDefault();
            $('#editWfpForm')[0].reset();
            $('#editWfpForm input, #editWfpForm select, #editWfpForm textarea').prop('disabled', false);
            $('.q-input').prop('disabled', true); // Keep these locked initially
            
            $('#edit_activity_id').val('');
            $('#method_field').html(''); 
            $('.select2-modal').val(null).trigger('change');
            $('#modalManualEncodingLabel').html('<i class="fas fa-edit mr-2"></i> MANUAL WFP ENCODING');

            $('#edit_source_of_fund_id').empty()
                .append('<option value="">-- Select Budget Line First --</option>')
                .prop('disabled', true)
                .trigger('change');
            
            $('#modalManualEncoding').modal('show');
        });

        // --- SAFETY RESET ON CLOSE ---
        $('#modalManualEncoding').on('hidden.bs.modal', function () {
            $(this).find('input, select, textarea').prop('disabled', false);
            $('.select2-modal').trigger('change');
        });

        // --- CHECKBOX TOGGLE LOGIC ---
        $(document).on('change', '.q-checkbox', function() {
            let targetInput = $(this).closest('.form-group').find('.q-input');
            if ($(this).is(':checked')) {
                targetInput.prop('disabled', false);
            } else {
                targetInput.prop('disabled', true).val('');
            }
        });

        // --- SUBMIT LOGIC (FIXED) ---
        $('#editWfpForm').on('submit', function() {
            // 1. Find the budget input
            let $budgetField = $('#edit_budget_amount');
            
            // 2. Remove commas: "1,250.50" -> "1250.50"
            let cleanValue = $budgetField.val().replace(/,/g, '');
            
            // 3. Set the value back to the clean version for the request
            $budgetField.val(cleanValue);

            // 4. IMPORTANT: Also re-enable all disabled fields
            // Otherwise, 'source_of_fund_id' and 'budget_line_item' won't be sent!
            $(this).find(':disabled').prop('disabled', false);
        });

        // 1. Format on Input
        $(document).on('input', '.price-format', function() {
            let value = $(this).val().replace(/,/g, ''); // Remove existing commas
            
            // Ensure it's a valid number
            if (!isNaN(value) && value.length > 0) {
                // Format with commas, allowing for decimals
                let parts = value.split(".");
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                $(this).val(parts.join("."));
            }
        });

    });

    $(document).ready(function() {
        $('.select2-employee').select2({
            theme: 'bootstrap4',
            placeholder: 'Search Official...',
            minimumInputLength: 3,
            dropdownParent: $('#modal-signatory'), // Ensures dropdown stays inside the modal
            ajax: {
                url: "/settings/employees/search",
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term };
                },
                processResults: function (data) {
                    return {
                        results: $.map(data, function (item) {
                            return {
                                id: item.id,
                                text: item.text,
                                designation: item.designation
                            }
                        })
                    };
                },
                cache: true
            },
            templateResult: formatEmployee,
            templateSelection: formatEmployeeSelection
        });

        // Custom formatting for the dropdown results
        function formatEmployee (repo) {
            if (repo.loading) return repo.text;
            var designation = repo.designation ? repo.designation : "DESIGNATION NOT SET";

            return $(
                "<div class='select2-result-employee'>" +
                    "<div><strong>" + repo.text + "</strong></div>" +
                    // "<div style='font-size: 11px; opacity: 0.8; text-transform: uppercase;'>" + designation + "</div>" +
                "</div>"
            );
        }

        // Custom formatting for the selected item
        function formatEmployeeSelection (repo) {
            return repo.text || repo.placeholder;
        }
    });

    function addSignatoryModal() {
        // Reset the form inside the modal
        $('#signatoryForm')[0].reset();
        
        // Clear the Select2 search selection
        $('.select2-employee').val(null).trigger('change');
        
        // Change modal title if you're reusing it for Edit
        $('#signatoryModalTitle').text('Add New Signatory Rule');
        
        // Show the modal
        $('#modal-signatory').modal('show');
    }

    $('#signatoryForm').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: "{{ route('settings.signatories.save') }}",
            method: "POST",
            data: $(this).serialize(),
            success: function(response) {
                $('#modal-signatory').modal('hide');
                toastr.success(response.success);
                location.reload(); // Refresh to show the updated table
            },
            error: function(xhr) {
                alert('Error saving signatory. Please check your inputs.');
            }
        });
    });

</script>
@endsection