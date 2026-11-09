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

                    {{-- SUB-TABS NAVIGATION FOR INDICATIVE & FINAL WFP --}}
                    <div class="card card-outline card-secondary mb-3">
                        <div class="card-header p-2">
                            <ul class="nav nav-pills" id="wfpSubTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active font-weight-bold" id="subtab-indicative-link" data-toggle="pill" href="#subtab-indicative" role="tab">
                                        <i class="fas fa-file-alt mr-1 text-warning"></i> Indicative WFP
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link font-weight-bold" id="subtab-final-link" data-toggle="pill" href="#subtab-final" role="tab">
                                        <i class="fas fa-check-double mr-1 text-success"></i> Final WFP
                                    </a>
                                </li>
                                @if($isAdminOrBudget)
                                    {{-- Verify Button: Only visible to Budget Staff / Admins --}}
                                    <button type="button" 
                                            class="btn btn-outline-info font-weight-bold shadow-sm" 
                                            data-toggle="modal" 
                                            data-target="#modalVerifyWfp"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalVerifyWfp">
                                        <i class="fas fa-qrcode mr-1"></i> Verify WFP Code
                                    </button>
                                @endif
                            </ul>
                        </div>
                    </div>

                    <hr class="my-4">

                    {{-- SUB-TABS CONTENT CONTAINER --}}
                    <div class="tab-content">

                        {{-- ==================== SUB-TAB 1: INDICATIVE WFP (is_converted = 0) ==================== --}}
                        <div class="tab-pane fade show active" id="subtab-indicative" role="tabpanel">

                            <div class="text-right mb-3">
                                <button class="btn btn-warning font-weight-bold shadow-sm" data-toggle="modal" data-target="#modalIndicativeWfp">
                                    <i class="fas fa-plus mr-1"></i> Add Indicative Activity
                                </button>
                            </div>
                            
                            {{-- Bulk Action Bar --}}
                            <div class="row mb-3 align-items-center bg-light p-2 rounded mx-0 shadow-sm">
                                <div class="col-md-6">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="selectAllIndicative">
                                        <label class="custom-control-label font-weight-bold text-dark" for="selectAllIndicative">Select All Convertable Indicative Activities</label>
                                    </div>
                                </div>
                                <div class="col-md-6 text-right">
                                    <button type="button" id="btnBulkConvert" class="btn btn-sm btn-primary font-weight-bold shadow-sm">
                                        <i class="fas fa-exchange-alt mr-1"></i> Convert Selected to Final WFP
                                    </button>
                                </div>
                            </div>

                            <div id="indicativeActivitiesAccordion">
                                @php
                                    $indicativeYears = $activities->groupBy('source.fiscal_year')->filter(function($yearGroups) {
                                        return $yearGroups->contains(function($act) {
                                            return !$act->is_converted;
                                        });
                                    });

                                    $latestIndicativeYear = $indicativeYears->keys()->max();
                                @endphp

                                @foreach($indicativeYears as $year => $yearGroups)
                                    @php 
                                        $isCurrent = ($year == $currentYear); 
                                        $collapseId = "collapseIndYear" . $year;
                                        $shouldExpand = ($year == $latestIndicativeYear) || $isCurrent;
                                    @endphp
                                    
                                    <div class="card card-dark card-outline mb-4">
                                        <div class="card-header p-0">
                                            <button class="btn btn-link btn-block text-left text-dark font-weight-bold py-2 px-3" type="button" data-toggle="collapse" data-target="#{{ $collapseId }}">
                                                <i class="fas {{ $shouldExpand ? 'fa-folder-open' : 'fa-folder' }} mr-2 text-warning"></i> 
                                                FISCAL YEAR {{ $year }} (Indicative) 
                                                @if($isCurrent) <span class="badge badge-success ml-2">Current</span> @endif
                                            </button>
                                        </div>

                                        <div id="{{ $collapseId }}" class="collapse {{ $shouldExpand ? 'show' : '' }}">
                                            <div class="card-body p-0">
                                                @foreach($groupedBySection as $sectionName => $fundSourcesInThisSection)
                                                    @php
                                                        $hasIndicativeInSection = $fundSourcesInThisSection->contains(function($source) use ($year) {
                                                            return $source->activities->contains(function($act) use ($year) {
                                                                return !$act->is_converted && optional($act->source)->fiscal_year == $year;
                                                            });
                                                        });
                                                        if(!$hasIndicativeInSection) continue;
                                                    @endphp

                                                    <div class="section-divider mt-4 mb-3 ml-3">
                                                        <h4 class="text-navy font-weight-bold border-bottom pb-2">
                                                            <i class="fas fa-layer-group mr-2 text-primary"></i> {{ strtoupper($sectionName) }}
                                                        </h4>
                                                    </div>

                                                    @foreach($fundSourcesInThisSection as $source)
                                                        @php
                                                            $indicativeActivities = $source->activities->filter(function($act) use ($year) {
                                                                return !$act->is_converted && optional($act->source)->fiscal_year == $year;
                                                            });
                                                            if($indicativeActivities->isEmpty()) continue;

                                                            $totalPooled = $indicativeActivities->sum('pooled_amount');
                                                            $activityIds = $indicativeActivities->pluck('id');

                                                            $totalObligations = \App\Models\Fund::whereIn('transaction_type_id', $activityIds)
                                                                ->whereYear('obligation_date', $currentYear) 
                                                                ->sum('obligation_amount');

                                                            $netDistributedTotal = $indicativeActivities->sum('budget_adjusted') - $totalPooled; 
                                                            $totalFundSourceAmount = $source->total_amount ?? 0;
                                                            $adjustedAmount = $totalFundSourceAmount - $totalPooled;

                                                            $isSourceApproved = in_array($source->funding_source, ['NEP-Current', 'GAA', 'Current']);
                                                        @endphp

                                                        <div class="mx-3 mt-4 mb-3">
                                                            <div class="card card-outline card-primary shadow-sm mb-2">
                                                                <div class="card-header bg-navy py-2">
                                                                    <div class="row align-items-center">
                                                                        <div class="col-md-5">
                                                                            <h5 class="mb-0 font-weight-bold text-white">
                                                                                <i class="fas fa-university mr-2 text-warning"></i>
                                                                                {{ strtoupper($source->name) }}
                                                                                @if($isSourceApproved)
                                                                                    <span class="badge badge-success ml-2" style="font-size: 0.75rem;"><i class="fas fa-check-circle"></i> Approved</span>
                                                                                @else
                                                                                    <span class="badge badge-warning text-dark ml-2" style="font-size: 0.75rem;"><i class="fas fa-clock"></i> Pending NEP</span>
                                                                                @endif
                                                                            </h5>
                                                                        </div>
                                                                        <div class="col-md-7 text-right">
                                                                            <div class="d-flex justify-content-end align-items-center">
                                                                                <div class="px-3 border-right border-secondary">
                                                                                    <button type="button" class="btn btn-xs btn-danger shadow-sm font-weight-bold btn-print-indicative-wfp" 
                                                                                        data-source-id="{{ $source->id }}" 
                                                                                        data-source-name="{{ $source->name }}">
                                                                                        <i class="fas fa-file-pdf mr-1"></i> PRINT WFP
                                                                                    </button>
                                                                                </div>
                                                                                <div class="px-3 border-right border-secondary">
                                                                                    <small class="text-gray d-block text-uppercase font-weight-bold" style="font-size: 0.65rem;">Distributed / Adjusted</small>
                                                                                    <span class="text-white font-weight-bold">
                                                                                        <span class="text-warning">₱{{ number_format($netDistributedTotal, 2) }}</span>
                                                                                        <small class="mx-1">/</small>
                                                                                        <span>₱{{ number_format($adjustedAmount, 2) }}</span>
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

                                                            <table class="table table-bordered table-sm m-0 mb-4 shadow-sm">
                                                                <thead>
                                                                    <tr class="bg-gray-light text-center">
                                                                        <th rowspan="2" style="width: 4%; vertical-align: middle;"><i class="fas fa-check-square"></i></th>
                                                                        <th rowspan="2" style="width: 16%; vertical-align: middle;">OBJECTIVE</th>
                                                                        <th rowspan="2" style="width: 25%; vertical-align: middle;">ACTIVITIES</th>
                                                                        <th colspan="2" style="width: 14%;">TIMEFRAME</th>
                                                                        <th colspan="4" style="width: 16%;">TARGETS</th>
                                                                        <th rowspan="2" style="width: 11%; vertical-align: middle;">COST</th>
                                                                        <th rowspan="2" style="width: 10%; vertical-align: middle;">ACTIONS</th>
                                                                    </tr>
                                                                    <tr class="bg-gray-light text-center">
                                                                        <th>Start</th><th>End</th>
                                                                        <th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($indicativeActivities->groupBy('objective') as $objective => $activitiesByObjective)
                                                                        @foreach($activitiesByObjective as $index => $activity)
                                                                            @php
                                                                                $hasTransactions = \App\Models\Fund::where('transaction_type_id', $activity->id)->exists();
                                                                                $targets = is_array($activity->physical_targets) ? $activity->physical_targets : json_decode($activity->physical_targets, true) ?? [];
                                                                            @endphp
                                                                            <tr>
                                                                                <td class="text-center align-middle">
                                                                                    @if($isSourceApproved)
                                                                                        <input type="checkbox" class="indicative-checkbox" value="{{ $activity->id }}">
                                                                                    @else
                                                                                        <input type="checkbox" disabled data-toggle="tooltip" title="Cannot carry over: Fund source is still pending NEP approval.">
                                                                                    @endif
                                                                                </td>

                                                                                @if($index === 0)
                                                                                    <td rowspan="{{ $activitiesByObjective->count() }}" class="align-top font-weight-bold p-2 bg-white" style="word-break: break-word;">
                                                                                        {{ $objective }}
                                                                                    </td>
                                                                                @endif

                                                                                <td class="p-2 align-middle" style="word-break: break-word;">
                                                                                    {{ $activity->name }}
                                                                                    @if(!$isSourceApproved)
                                                                                        <span class="badge badge-warning text-dark ml-1"><i class="fas fa-clock"></i> Pending NEP</span>
                                                                                    @endif
                                                                                    @if($activity->pooled_amount > 0)
                                                                                        <div class="small text-danger font-italic">(₱{{ number_format($activity->pooled_amount, 2) }} pooled)</div>
                                                                                    @endif
                                                                                </td>

                                                                                <td class="text-center align-middle">{{ $activity->start_date ? \Carbon\Carbon::parse($activity->start_date)->format('d M Y') : '-' }}</td>
                                                                                <td class="text-center align-middle">{{ $activity->end_date ? \Carbon\Carbon::parse($activity->end_date)->format('d M Y') : '-' }}</td>

                                                                                <td class="text-center align-middle">{{ $targets['Q1'] ?? '' }}</td>
                                                                                <td class="text-center align-middle">{{ $targets['Q2'] ?? '' }}</td>
                                                                                <td class="text-center align-middle">{{ $targets['Q3'] ?? '' }}</td>
                                                                                <td class="text-center align-middle">{{ $targets['Q4'] ?? '' }}</td>

                                                                                <td class="text-right align-middle font-weight-bold">
                                                                                    ₱{{ number_format($activity->budget_adjusted - $activity->pooled_amount, 2) }}
                                                                                </td>

                                                                                <td class="text-center align-middle">
                                                                                    <div class="btn-group">
                                                                                        @if($isSourceApproved)
                                                                                            <button type="button" 
                                                                                                    class="btn btn-xs btn-success mr-1 btn-single-convert" 
                                                                                                    data-id="{{ $activity->id }}" 
                                                                                                    data-name="{{ $activity->name }}" 
                                                                                                    title="Carry over to Final WFP">
                                                                                                <i class="fas fa-exchange-alt"></i>
                                                                                            </button>
                                                                                        @else
                                                                                            <button type="button" 
                                                                                                    class="btn btn-xs btn-secondary mr-1" 
                                                                                                    disabled 
                                                                                                    data-toggle="tooltip" 
                                                                                                    title="Pending NEP Approval">
                                                                                                <i class="fas fa-exchange-alt"></i>
                                                                                            </button>
                                                                                        @endif

                                                                                        <button type="button" class="btn btn-xs btn-info" onclick="openEditWfpModal({{ $activity->id }}, {{ $hasTransactions ? 'true' : 'false' }})" title="Edit Activity">
                                                                                            <i class="fas fa-edit"></i>
                                                                                        </button>

                                                                                        @if(!$hasTransactions)
                                                                                            <form action="{{ route('settings.activity.destroy', $activity->id) }}" method="POST" class="d-inline ml-1">
                                                                                                @csrf @method('DELETE')
                                                                                                <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Delete this activity?')" title="Delete Activity">
                                                                                                    <i class="fas fa-trash"></i>
                                                                                                </button>
                                                                                            </form>
                                                                                        @else
                                                                                            <span class="badge badge-secondary ml-1 p-1" title="Locked due to existing transactions"><i class="fas fa-lock"></i></span>
                                                                                        @endif
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- ==================== SUB-TAB 2: FINAL WFP (is_converted = 1) ==================== --}}
                        <div class="tab-pane fade" id="subtab-final" role="tabpanel">

                            <div class="text-right mb-3">
                                <button class="btn btn-success font-weight-bold shadow-sm" data-toggle="modal" data-target="#modalFinalWfp">
                                    <i class="fas fa-plus mr-1"></i> Add Final Activity
                                </button>
                            </div>
                            
                            <div id="finalActivitiesAccordion">
                                @foreach($activities->groupBy('source.fiscal_year') as $year => $yearGroups)
                                    @php 
                                        $isCurrent = ($year == $currentYear); 
                                        $collapseId = "collapseFinalYear" . $year;

                                        $hasAnyConvertedInYear = $yearGroups->contains(function($activity) {
                                            return $activity->is_converted;
                                        });
                                        if(!$hasAnyConvertedInYear) continue;
                                    @endphp
                                    
                                    <div class="card card-dark card-outline mb-4">
                                        <div class="card-header p-0">
                                            <button class="btn btn-link btn-block text-left text-dark font-weight-bold py-2 px-3" type="button" data-toggle="collapse" data-target="#{{ $collapseId }}">
                                                <i class="fas {{ $isCurrent ? 'fa-folder-open' : 'fa-folder' }} mr-2 text-success"></i> 
                                                FISCAL YEAR {{ $year }} (Final WFP) 
                                                @if($isCurrent) <span class="badge badge-success ml-2">Current</span> @endif
                                            </button>
                                        </div>

                                        <div id="{{ $collapseId }}" class="collapse {{ $isCurrent ? 'show' : '' }}">
                                            <div class="card-body p-0">
                                                @foreach($groupedBySection as $sectionName => $fundSourcesInThisSection)
                                                    @php
                                                        $hasConvertedInSection = $fundSourcesInThisSection->contains(function($source) use ($year) {
                                                            return $source->activities->contains(function($act) use ($year) {
                                                                return $act->is_converted && optional($act->source)->fiscal_year == $year;
                                                            });
                                                        });
                                                        if(!$hasConvertedInSection) continue;
                                                    @endphp

                                                    <div class="section-divider mt-4 mb-3 ml-3">
                                                        <h4 class="text-navy font-weight-bold border-bottom pb-2">
                                                            <i class="fas fa-layer-group mr-2 text-success"></i> {{ strtoupper($sectionName) }}
                                                        </h4>
                                                    </div>

                                                    @foreach($fundSourcesInThisSection as $source)
                                                        @php
                                                            $convertedActivities = $source->activities->filter(function($act) use ($year) {
                                                                return $act->is_converted && optional($act->source)->fiscal_year == $year;
                                                            });
                                                            if($convertedActivities->isEmpty()) continue;

                                                            $totalPooled = $convertedActivities->sum('pooled_amount');
                                                            $activityIds = $convertedActivities->pluck('id');
                                                            
                                                            $totalObligations = \App\Models\Fund::whereIn('transaction_type_id', $activityIds)
                                                                ->whereYear('obligation_date', $currentYear) 
                                                                ->sum('obligation_amount');

                                                            $netDistributedTotal = $convertedActivities->sum('budget_adjusted') - $totalPooled; 
                                                            $totalFundSourceAmount = $source->total_amount ?? 0;
                                                            $adjustedAmount = $totalFundSourceAmount - $totalPooled;
                                                        @endphp

                                                        <div class="mx-3 mt-4 mb-3">
                                                            <div class="card card-outline card-success shadow-sm mb-2">
                                                                <div class="card-header bg-success py-2">
                                                                    <div class="row align-items-center">
                                                                        <div class="col-md-5">
                                                                            <h5 class="mb-0 font-weight-bold text-white">
                                                                                <i class="fas fa-university mr-2"></i> {{ strtoupper($source->name) }}
                                                                            </h5>
                                                                        </div>
                                                                        <div class="col-md-7 text-right">
                                                                            <div class="d-flex justify-content-end align-items-center">
                                                                                <div class="px-3 border-right border-light">
                                                                                    <button type="button" class="btn btn-xs btn-dark shadow-sm font-weight-bold open-wfp-modal" 
                                                                                        data-source-id="{{ $source->id }}" 
                                                                                        data-source-name="{{ $source->name }}" 
                                                                                        data-wfp-type="final">
                                                                                        <i class="fas fa-file-pdf mr-1"></i> PRINT WFP
                                                                                    </button>
                                                                                </div>
                                                                                <div class="px-3 border-right border-light">
                                                                                    <small class="text-white-50 d-block text-uppercase font-weight-bold" style="font-size: 0.65rem;">Distributed / Adjusted</small>
                                                                                    <span class="text-white font-weight-bold">
                                                                                        <span class="text-warning">₱{{ number_format($netDistributedTotal, 2) }}</span>
                                                                                        <small class="mx-1">/</small>
                                                                                        <span>₱{{ number_format($adjustedAmount, 2) }}</span>
                                                                                    </span>
                                                                                </div>
                                                                                <div class="px-3 border-right border-light text-center">
                                                                                    <small class="text-white-50 d-block text-uppercase font-weight-bold" style="font-size: 0.65rem;">Pooled</small>
                                                                                    <span class="text-warning font-weight-bold">₱{{ number_format($totalPooled, 2) }}</span>
                                                                                </div>
                                                                                <div class="pl-3 text-center">
                                                                                    <small class="text-white-50 d-block text-uppercase font-weight-bold" style="font-size: 0.65rem;">Obligated</small>
                                                                                    <span class="text-light font-weight-bold">₱{{ number_format($totalObligations, 2) }}</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <table class="table table-bordered table-sm m-0 mb-4 shadow-sm">
                                                                <thead>
                                                                    <tr class="bg-gray-light text-center">
                                                                        <th rowspan="2" style="width: 4%; vertical-align: middle;"><i class="fas fa-check-square"></i></th>
                                                                        <th rowspan="2" style="width: 16%; vertical-align: middle;">OBJECTIVE</th>
                                                                        <th rowspan="2" style="width: 25%; vertical-align: middle;">ACTIVITIES</th>
                                                                        <th colspan="2" style="width: 14%;">TIMEFRAME</th>
                                                                        <th colspan="4" style="width: 16%;">TARGETS</th>
                                                                        <th rowspan="2" style="width: 11%; vertical-align: middle;">COST</th>
                                                                        <th rowspan="2" style="width: 10%; vertical-align: middle;">ACTIONS</th>
                                                                    </tr>
                                                                    <tr class="bg-gray-light text-center">
                                                                        <th>Start</th><th>End</th>
                                                                        <th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($convertedActivities->groupBy('objective') as $objective => $activitiesByObjective)
                                                                        @foreach($activitiesByObjective as $index => $activity)
                                                                            @php
                                                                                $hasTransactions = \App\Models\Fund::where('transaction_type_id', $activity->id)->exists();
                                                                                $targets = is_array($activity->physical_targets) ? $activity->physical_targets : json_decode($activity->physical_targets, true) ?? [];
                                                                            @endphp
                                                                            <tr>
                                                                                <td class="text-center align-middle">
                                                                                    <span class="text-success"><i class="fas fa-check-circle"></i></span>
                                                                                </td>

                                                                                @if($index === 0)
                                                                                    <td rowspan="{{ $activitiesByObjective->count() }}" class="align-top font-weight-bold p-2 bg-white" style="word-break: break-word;">
                                                                                        {{ $objective }}
                                                                                    </td>
                                                                                @endif

                                                                                <td class="p-2 align-top">
                                                                                    <div class="text-dark" style="white-space: pre-line; word-break: break-word; line-height: 1.35;">
                                                                                        {{ trim($activity->name) }}
                                                                                        @if($activity->pooled_amount > 0)
                                                                                            <div class="small text-danger font-italic mt-1">(₱{{ number_format($activity->pooled_amount, 2) }} pooled)</div>
                                                                                        @endif
                                                                                    </div>

                                                                                    <div class="mt-1">
                                                                                        <span class="badge badge-success px-2 py-1 shadow-sm font-weight-bold" style="font-size: 10px; letter-spacing: 0.5px;" title="Approved Final WFP">
                                                                                            <i class="fas fa-check-circle mr-1"></i> APPROVED
                                                                                        </span>
                                                                                    </div>
                                                                                </td>

                                                                                <td class="text-center align-middle">{{ $activity->start_date ? \Carbon\Carbon::parse($activity->start_date)->format('d M Y') : '-' }}</td>
                                                                                <td class="text-center align-middle">{{ $activity->end_date ? \Carbon\Carbon::parse($activity->end_date)->format('d M Y') : '-' }}</td>

                                                                                <td class="text-center align-middle">{{ $targets['Q1'] ?? '' }}</td>
                                                                                <td class="text-center align-middle">{{ $targets['Q2'] ?? '' }}</td>
                                                                                <td class="text-center align-middle">{{ $targets['Q3'] ?? '' }}</td>
                                                                                <td class="text-center align-middle">{{ $targets['Q4'] ?? '' }}</td>

                                                                                <td class="text-right align-middle font-weight-bold">
                                                                                    ₱{{ number_format($activity->budget_adjusted - $activity->pooled_amount, 2) }}
                                                                                </td>

                                                                                <td class="text-center align-middle">
                                                                                    <div class="btn-group">
                                                                                        <button type="button" class="btn btn-xs btn-info mr-1" onclick="openEditWfpModal({{ $activity->id }}, {{ $hasTransactions ? 'true' : 'false' }})" title="Edit Activity">
                                                                                            <i class="fas fa-edit"></i>
                                                                                        </button>
                                                                                        
                                                                                        @if(!$hasTransactions)
                                                                                            <button type="button" class="btn btn-xs btn-warning btn-pool-fund mr-1" 
                                                                                                    data-id="{{ $activity->id }}" data-name="{{ $activity->name }}"
                                                                                                    data-budget="{{ $activity->budget }}" data-pooled="{{ $activity->pooled_amount ?? 0 }}"
                                                                                                    data-remarks="{{ $activity->remarks ?? '' }}" title="Pool Funds">
                                                                                                <i class="fas fa-hand-holding-usd"></i>
                                                                                            </button>

                                                                                            <form action="{{ route('settings.activity.destroy', $activity->id) }}" method="POST" class="d-inline">
                                                                                                @csrf @method('DELETE')
                                                                                                <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Delete this final activity?')" title="Delete Activity">
                                                                                                    <i class="fas fa-trash"></i>
                                                                                                </button>
                                                                                            </form>
                                                                                        @else
                                                                                            <span class="badge badge-secondary p-1" title="Locked due to existing transactions"><i class="fas fa-lock"></i></span>
                                                                                        @endif
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                    </div>
                </div>



            {{-- WFP SIGNATORIES --}}
            <div class="tab-pane fade" id="tabs-signatories" role="tabpanel" aria-labelledby="tabs-signatories-tab">
                <div class="card mt-3">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title text-bold mb-0">
                                <i class="fas fa-users-cog mr-1 text-primary"></i> Signatory Management
                            </h3>
                            <button type="button" class="btn btn-primary btn-sm" onclick="addSignatoryModal()">
                                <i class="fas fa-plus mr-1"></i> Add Signatory Rule
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr class="bg-gray-light">
                                        <th style="width: 15%">Section</th> {{-- NEW COLUMN --}}
                                        <th style="width: 15%">WFP Type</th>
                                        <th style="width: 15%">Label</th>
                                        <th style="width: 35%">Assigned Official</th>
                                        <th style="width: 10%" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($signatorySettings as $setting)
                                    <tr>
                                        <td>
                                            <span class="badge badge-info px-2 py-1">
                                                {{ $setting->section_display_name ?? 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="text-uppercase text-bold text-primary">{{ $setting->wfp_type }}</td>
                                        <td>{{ $setting->label }}</td>
                                        <td>
                                            <span class="text-bold">{{ $setting->employee_name }}</span><br>
                                            <small class="text-muted text-uppercase">{{ $setting->designation }}</small>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button class="btn btn-default btn-xs mr-1" 
                                                        onclick="editSignatory({{ json_encode($setting) }})" 
                                                        title="Edit">
                                                    <i class="fas fa-edit text-info"></i>
                                                </button>
                                                <button class="btn btn-default btn-xs" 
                                                        onclick="deleteSignatory({{ $setting->id }})" 
                                                        title="Delete">
                                                    <i class="fas fa-trash text-danger"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="fas fa-folder-open fa-2x mb-2"></i><br>
                                            No signatory rules found for your section.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted">
                            <i class="fas fa-info-circle mr-1 text-info"></i> 
                            <strong>Visibility:</strong> 
                            @if($isAdminOrBudget)
                                You are viewing signatories for <strong>ALL Sections</strong>.
                            @else
                                You are viewing signatories only for your <strong>assigned Section</strong>.
                            @endif
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
                                        
                                        {{-- Use $fundSources which is pre-filtered by Section in the Controller --}}
                                        @foreach($fundSources as $source)
                                            @if($source->fiscal_year == $currentYear)
                                                <option value="{{ $source->id }}">
                                                    {{ $source->name }} (FY {{ $source->fiscal_year }})
                                                </option>
                                                @php $hasCurrentYearSources = true; @endphp
                                            @endif
                                        @endforeach

                                        @if(!$hasCurrentYearSources)
                                            <option value="" disabled>No sources available for your section in {{ $currentYear }}</option>
                                        @endif
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

{{-- FINAL WFP MODAL --}}
<div class="modal fade" id="modalFinalWfp" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="finalWfpModalTitle"><i class="fas fa-edit mr-2"></i> Add Final WFP Activity</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            
            <form action="{{ route('settings.activity.storeWfp') }}" method="POST" id="formFinalWfp">
                @csrf
                {{-- ID for Edit Mode --}}
                <input type="hidden" name="id" class="activity-id-input">
                <input type="hidden" name="wfp_type" value="final">
                
                <div class="modal-body">
                    <div class="row">
                        {{-- Budget Line Item --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Budget Line Item</label>
                                <select name="budget_line_item_id" class="form-control select2-modal budget-line-dropdown" required style="width: 100%;">
                                    <option value="">-- Select Line Item --</option>
                                    @foreach($budgetLineItems as $item)
                                        <option value="{{ $item->id }}">{{ $item->budget_line_item_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Fund Source --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Fund Source</label>
                                <select name="source_of_fund_id" class="form-control select2-modal fund-source-dropdown" required style="width: 100%;" disabled>
                                    <option value="">-- Select Budget Line First --</option>
                                    @foreach($finalSources as $fs)
                                        <option value="{{ $fs->id }}" data-line="{{ $fs->budget_line_item_id }}">
                                            {{ $fs->name }} (FY {{ $fs->fiscal_year }}) - [{{ $fs->funding_source ?? 'N/A' }}]
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Classification --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Activity Classification</label>
                                <select name="classification" class="form-control select2-modal" required style="width: 100%;">
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
                                <label class="font-weight-bold">Objective <span class="text-danger">*</span></label>
                                <select name="objective" class="form-control select2-objective-tags" required style="width: 100%;">
                                    <option value="">-- Select or Type New Objective --</option>
                                    @foreach($objectives as $obj)
                                        @php $title = is_array($obj) ? ($obj['objectives'] ?? $obj['title'] ?? '') : ($obj->title ?? $obj->objectives ?? ''); @endphp
                                        @if(!empty($title)) <option value="{{ $title }}">{{ $title }}</option> @endif
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
                                    <input type="text" name="budget_amount" class="form-control price-format" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        {{-- UACS Code --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">UACS Code / Account Title</label>
                                <select name="uacs_code_id" class="form-control select2-modal" required style="width: 100%;">
                                    <option value="">-- Search Code or Title --</option>
                                    @foreach($uacsCodes as $uacs)
                                        <option value="{{ $uacs->id }}">[{{ $uacs->allotment_class }}] {{ $uacs->uacs_code }} - {{ $uacs->account_title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Activity Name --}}
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="font-weight-bold">Activity Name</label>
                                <textarea name="name" class="form-control" rows="3" required></textarea>
                            </div>
                        </div>

                        {{-- Procurement --}}
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="font-weight-bold text-primary"><i class="fas fa-shopping-cart mr-1"></i> For Procurement? <span class="text-danger">*</span></label>
                                <select name="is_for_procurement" class="form-control" required>
                                    <option value="">-- Please Select --</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>

                        {{-- Timeframe --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">End Date</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                        </div>

                        {{-- Targets --}}
                        <div class="col-md-12 mt-3">
                            <label class="font-weight-bold">Target Quarters & Physical Targets</label>
                            <div class="row border rounded p-3 bg-light mx-0">
                                @foreach(['Q1', 'Q2', 'Q3', 'Q4'] as $q)
                                    <div class="col-md-3">
                                        <div class="form-group mb-0">
                                            <div class="custom-control custom-checkbox mb-2">
                                                <input class="custom-control-input q-checkbox" type="checkbox" name="target_quarters[]" id="final_check_{{ strtolower($q) }}" value="{{ $q }}">
                                                <label for="final_check_{{ strtolower($q) }}" class="custom-control-label font-weight-bold">{{ $q }} Target</label>
                                            </div>
                                            <input type="number" name="targets[{{ $q }}]" class="form-control form-control-sm q-input" placeholder="0" min="0" disabled>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success shadow-sm"><i class="fas fa-save mr-1"></i> Save Final Activity</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- INDICATIVE WFP MODAL --}}
<div class="modal fade" id="modalIndicativeWfp" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="indicativeWfpModalTitle"><i class="fas fa-edit mr-2"></i> Add Indicative WFP Activity</h5>
                <button type="button" class="close text-dark" data-dismiss="modal"><span>&times;</span></button>
            </div>
            
            <form action="{{ route('settings.activity.storeWfp') }}" method="POST" id="formIndicativeWfp">
                @csrf
                {{-- ID for Edit Mode --}}
                <input type="hidden" name="id" class="activity-id-input">
                <input type="hidden" name="wfp_type" value="indicative">
                
                <div class="modal-body">
                    <div class="row">
                        {{-- Budget Line Item --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Budget Line Item</label>
                                <select name="budget_line_item_id" class="form-control select2-modal budget-line-dropdown" required style="width: 100%;">
                                    <option value="">-- Select Line Item --</option>
                                    @foreach($budgetLineItems as $item)
                                        <option value="{{ $item->id }}">{{ $item->budget_line_item_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Fund Source --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Fund Source</label>
                                <select name="source_of_fund_id" class="form-control select2-modal fund-source-dropdown" required style="width: 100%;" disabled>
                                    <option value="">-- Select Budget Line First --</option>
                                    @foreach($indicativeSources as $fs)
                                        <option value="{{ $fs->id }}" data-line="{{ $fs->budget_line_item_id }}">
                                            {{ $fs->name }} (FY {{ $fs->fiscal_year }}) - [{{ $fs->funding_source ?? 'N/A' }}]
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Classification --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Activity Classification</label>
                                <select name="classification" class="form-control select2-modal" required style="width: 100%;">
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
                                <label class="font-weight-bold">Objective <span class="text-danger">*</span></label>
                                <select name="objective" class="form-control select2-objective-tags" required style="width: 100%;">
                                    <option value="">-- Select or Type New Objective --</option>
                                    @foreach($objectives as $obj)
                                        @php $title = is_array($obj) ? ($obj['objectives'] ?? $obj['title'] ?? '') : ($obj->title ?? $obj->objectives ?? ''); @endphp
                                        @if(!empty($title)) <option value="{{ $title }}">{{ $title }}</option> @endif
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
                                    <input type="text" name="budget_amount" class="form-control price-format" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        {{-- UACS Code --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">UACS Code / Account Title</label>
                                <select name="uacs_code_id" class="form-control select2-modal" required style="width: 100%;">
                                    <option value="">-- Search Code or Title --</option>
                                    @foreach($uacsCodes as $uacs)
                                        <option value="{{ $uacs->id }}">[{{ $uacs->allotment_class }}] {{ $uacs->uacs_code }} - {{ $uacs->account_title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Activity Name --}}
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="font-weight-bold">Activity Name</label>
                                <textarea name="name" class="form-control" rows="3" required></textarea>
                            </div>
                        </div>

                        {{-- Procurement --}}
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="font-weight-bold text-primary"><i class="fas fa-shopping-cart mr-1"></i> For Procurement? <span class="text-danger">*</span></label>
                                <select name="is_for_procurement" class="form-control" required>
                                    <option value="">-- Please Select --</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>

                        {{-- Timeframe --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">End Date</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                        </div>

                        {{-- Targets --}}
                        <div class="col-md-12 mt-3">
                            <label class="font-weight-bold">Target Quarters & Physical Targets</label>
                            <div class="row border rounded p-3 bg-light mx-0">
                                @foreach(['Q1', 'Q2', 'Q3', 'Q4'] as $q)
                                    <div class="col-md-3">
                                        <div class="form-group mb-0">
                                            <div class="custom-control custom-checkbox mb-2">
                                                <input class="custom-control-input q-checkbox" type="checkbox" name="target_quarters[]" id="ind_check_{{ strtolower($q) }}" value="{{ $q }}">
                                                <label for="ind_check_{{ strtolower($q) }}" class="custom-control-label font-weight-bold">{{ $q }} Target</label>
                                            </div>
                                            <input type="number" name="targets[{{ $q }}]" class="form-control form-control-sm q-input" placeholder="0" min="0" disabled>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-warning shadow-sm"><i class="fas fa-save mr-1"></i> Save Indicative Activity</button>
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
                        <select name="wfp_type" id="wfp_type" class="form-control" required>
                            <option value="program">Cluster / Program / Unit</option>
                            <option value="saa">Consolidated / SAA</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Label</label>
                        <select name="label" id="label" class="form-control" required>
                            </select>
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
{{-- <div class="modal fade" id="importSummaryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document"> 
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
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button> --}}
                {{-- <a href="#tabs-realignment" class="btn btn-primary" data-toggle="pill">Go to Realignment</a> --}}
            {{-- </div>
        </div>
    </div>
</div> --}}

{{-- delete source modal --}}
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

{{-- pool fund modal --}}
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


<!-- WFP Print Options Modal -->
<div class="modal fade" id="wfpPrintModal" tabindex="-1" role="dialog" aria-labelledby="wfpPrintModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form id="wfpPrintForm" action="" method="GET" target="_blank">
            <input type="hidden" name="wfp_view_type" id="printWfpTypeInput" value="indicative">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="wfpPrintModalLabel">Print Work and Financial Plan (WFP)</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="source_of_fund_id" id="modalSourceId">
                    
                    <div class="form-group">
                        <label class="font-weight-bold">Fund Source:</label>
                        <span id="modalSourceName" class="text-muted font-italic"></span>
                    </div>

                    <!-- Plan Modifier Option -->
                    <div class="form-group">
                        <label for="planModifierSelect">Plan Type / Title Modifier:</label>
                        <select name="plan_modifier" id="planModifierSelect" class="form-control">
                            <option value="">-- Initial Plan (Default Title) --</option>
                            <option value="Catch-up Plan">Catch-up Plan</option>
                            <option value="Supplemental Plan">Supplemental Plan</option>
                        </select>
                        <small class="form-text text-muted">Select if this is a Catch-up Plan or Supplemental Plan realignment report.</small>
                    </div>

                    <hr>

                    <!-- Select All Toggle -->
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="selectAllActivities" name="select_all" value="1" checked>
                        <label class="form-check-label font-weight-bold" for="selectAllActivities">Select All Activities (Print whole WFP)</label>
                    </div>

                    <!-- Specific Activities Multi-select Container -->
                    <div class="form-group" id="activitySelectionContainer" style="display: none;">
                        <label for="activitySelect">Select Specific Activities (For Realignment / Replan):</label>
                        <select name="activity_ids[]" id="activitySelect" class="form-control select2" multiple="multiple" style="width: 100%;" data-placeholder="Choose activities to include...">
                            <!-- Dynamically loaded via AJAX -->
                        </select>
                        <small class="form-text text-danger" id="activityRequiredMsg" style="display: none;">Please select at least one activity when not using "Select All".</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="generatePdfBtn"><i class="fas fa-print mr-1"></i> Generate PDF</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Budget Exceeded Error Modal Popup -->
@if(session('budget_exceeded_error'))
<div class="modal fade" id="budgetExceededModal" tabindex="-1" role="dialog" aria-labelledby="budgetExceededModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title font-weight-bold" id="budgetExceededModalLabel">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Budget Limit Exceeded
                </h5>
            </div>
            <div class="modal-body">
                <p class="text-dark font-weight-bold" style="font-size: 1.05rem; line-height: 1.5;">
                    {{ session('budget_exceeded_error') }}
                </p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-danger font-weight-bold px-4" data-dismiss="modal" onclick="closeAndCleanupModal()">
                    <i class="fas fa-check mr-1"></i> OK
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Self-executing script that runs immediately when rendered, bypassing stack issues
    document.addEventListener("DOMContentLoaded", function() {
        if (typeof $ !== 'undefined') {
            $('#budgetExceededModal').modal({
                backdrop: 'static',
                keyboard: false,
                show: true
            });
        }
    });

    function closeAndCleanupModal() {
        if (typeof $ !== 'undefined') {
            $('#budgetExceededModal').modal('hide');
        }
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        const modalEl = document.getElementById('budgetExceededModal');
        if (modalEl) modalEl.remove();
        document.body.classList.remove('modal-open');
        document.body.style.paddingRight = '';
    }
</script>
@endif

@if($isAdminOrBudget)
    <!-- VERIFY WFP MODAL -->
    <div class="modal fade" id="modalVerifyWfp" tabindex="-1" role="dialog" aria-labelledby="modalVerifyWfpLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 8px; overflow: hidden;">
                
                {{-- Modal Header --}}
                <div class="modal-header bg-navy text-white px-4 py-3">
                    <div class="d-flex align-items-center">
                        <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 38px; height: 38px;">
                            <i class="fas fa-shield-alt fa-lg"></i>
                        </div>
                        <div>
                            <h5 class="modal-title font-weight-bold mb-0" id="modalVerifyWfpLabel">WFP Verification</h5>
                            <small class="text-white-50">Verify authenticity of system-generated WFP documents</small>
                        </div>
                    </div>
                    <button type="button" class="close text-white opacity-75" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="modal-body p-4 bg-light">
                    <form id="formVerifyWfp">
                        <div class="form-group mb-3">
                            <label for="tracking_code_input" class="font-weight-bold text-secondary small text-uppercase mb-2">
                                Tracking Code or Encrypted QR Payload
                            </label>
                            <div class="input-group shadow-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-white border-right-0 text-muted">
                                        <i class="fas fa-barcode"></i>
                                    </span>
                                </div>
                                <input type="text" 
                                       class="form-control border-left-0 text-uppercase font-weight-bold tracking-input" 
                                       id="tracking_code_input" 
                                       name="tracking_code" 
                                       placeholder="e.g. WFP-2026-EB3GVEKR" 
                                       required 
                                       autocomplete="off"
                                       style="letter-spacing: 0.5px;">
                                <div class="input-group-append">
                                    <button class="btn btn-primary px-4 font-weight-bold" type="submit" id="btnVerifySubmit">
                                        <i class="fas fa-search mr-1"></i> Verify
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted mt-2">
                                <i class="fas fa-info-circle mr-1"></i> Enter the tracking code printed at the footer or paste a scanned QR code payload string.
                            </small>
                        </div>
                    </form>

                    {{-- Dynamic Verification Result Container --}}
                    <div id="verificationResultContainer" class="mt-4" style="display: none;"></div>
                </div>

                {{-- Modal Footer --}}
                <div class="modal-footer bg-white border-top-0 px-4 py-2">
                    <button type="button" class="btn btn-sm btn-secondary px-4 font-weight-bold" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
                </div>

            </div>
        </div>
    </div>
@endif

@endsection

@section('js')
<script>
    /* --- GLOBAL DATA & HELPERS (Outside DOM Ready) --- */
    const labelGroups = {
        program: [
            { val: 'Prepared By:', text: 'Prepared By:' },
            { val: 'Approved By:', text: 'Approved By:' }
        ],
        saa: [
            { val: 'Prepared By:', text: 'Prepared By:' },
            { val: 'Recommending Approval By:', text: 'Recommending Approval By:' },
            { val: 'Approved By:', text: 'Approved By:' }
        ]
    };

    function updateLabelOptions(type, selectedValue = null) {
        const labelSelect = $('#label'); 
        if (!labelSelect.length) return;
        
        labelSelect.empty();
        const options = labelGroups[type] || labelGroups.program;
        
        options.forEach(opt => {
            const isSelected = (selectedValue === opt.val) ? 'selected' : '';
            labelSelect.append(`<option value="${opt.val}" ${isSelected}>${opt.text}</option>`);
        });
    }

    function addSignatoryModal() {
        $('#signatoryModalTitle').text('Add New Signatory Rule');
        $('#signatoryForm')[0].reset();
        $('.select2-employee').val(null).trigger('change'); 
        updateLabelOptions($('#wfp_type').val());
        $('#modal-signatory').modal('show');
    }

    function editSignatory(data) {
        $('#signatoryModalTitle').text('Edit Signatory Rule');
        $('.select2-employee').val(null).trigger('change');$('#wfp_type').val(data.wfp_type);
        updateLabelOptions(data.wfp_type, data.label);

        if (data.employee_id) {
            var newOption = new Option(data.employee_name, data.employee_id, true, true);
            $('.select2-employee').empty().append(newOption).trigger('change');
        }

        $('#modal-signatory').modal('show');
    }

    function deleteSignatory(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This signatory will be removed from the WFP Form layout.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "/settings/signatories/delete/" + id,
                    method: "DELETE",
                    data: { _token: "{{ csrf_token() }}" },
                    success: function(response) {
                        Swal.fire('Deleted!', response.success, 'success').then(() => {
                            location.reload();
                        });
                    },
                    error: function() {
                        Swal.fire('Error!', 'Could not delete the record.', 'error');
                    }
                });
            }
        });
    }

    function setFullPool() {
        const maxAmount = $('#pool_input_amount').attr('max');
        $('#pool_input_amount').val(maxAmount);
    }

    function formatNumber(n) {
        return n.replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // Helper function to safely update Fund Source dropdowns based on Budget Line selection
    function updateFundSourceDropdown(modal, selectedLine) {
        let fundDropdown = modal.find('.fund-source-dropdown');

        if (fundDropdown.hasClass("select2-hidden-accessible")) {
            fundDropdown.select2('destroy');
        }

        if (!fundDropdown.data('all-options')) {
            fundDropdown.data('all-options', fundDropdown.html());
        }

        fundDropdown.html(fundDropdown.data('all-options'));

        if (selectedLine) {
            fundDropdown.prop('disabled', false);
            fundDropdown.find('option').each(function() {
                let optionLine = $(this).attr('data-line');
                if ($(this).val() !== "" && optionLine != selectedLine) {
                    $(this).remove();
                }
            });
        } else {
            fundDropdown.prop('disabled', true).val('');
        }

        fundDropdown.select2({
            dropdownParent: modal,
            width: '100%'
        });
    }

    // Global function to handle opening and populating the Edit WFP Modal
    function openEditWfpModal(activityId, hasTransactions) {
        $.ajax({
            url: '/settings/activities/' + activityId + '/edit',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                var isFinal = data.is_converted == 1;
                var modal = isFinal ? $('#modalFinalWfp') : $('#modalIndicativeWfp');
                var modalTitle = isFinal ? $('#finalWfpModalTitle') : $('#indicativeWfpModalTitle');
                
                modalTitle.html('<i class="fas fa-edit mr-2"></i> Edit ' + (isFinal ? 'Final' : 'Indicative') + ' WFP Activity');

                // Set hidden ID
                modal.find('.activity-id-input').val(data.id);

                // Static Form Fields
                modal.find('textarea[name="name"]').val(data.name);
                modal.find('input[name="budget_amount"]').val(data.budget);
                modal.find('select[name="classification"]').val(data.classification);
                modal.find('select[name="is_for_procurement"]').val(data.is_for_procurement ? '1' : '0');

                // Formatted Dates (YYYY-MM-DD)
                if (data.start_date) {
                    modal.find('input[name="start_date"]').val(data.start_date.split(' ')[0]);
                }
                if (data.end_date) {
                    modal.find('input[name="end_date"]').val(data.end_date.split(' ')[0]);
                }

                // Populate and trigger Select2 fields
                modal.find('select[name="budget_line_item_id"]').val(data.budget_line_item_id).trigger('change');
                
                var fundSourceDropdown = modal.find('select[name="source_of_fund_id"]');
                fundSourceDropdown.prop('disabled', false).val(data.source_of_fund_id).trigger('change');

                modal.find('select[name="uacs_code_id"]').val(data.uacs_code_id).trigger('change');

                // Objective Select2 Tagging
                var objSelect = modal.find('select[name="objective"]');
                if (objSelect.find("option[value='" + data.objective + "']").length === 0) {
                    var newOption = new Option(data.objective, data.objective, true, true);
                    objSelect.append(newOption).trigger('change');
                } else {
                    objSelect.val(data.objective).trigger('change');
                }

                // Quarter Targets Reset
                modal.find('.q-checkbox').prop('checked', false);
                modal.find('.q-input').val('').prop('disabled', true).removeAttr('required');

                // Populate Physical Targets
                var targets = data.physical_targets || {};
                if (typeof targets === 'string') {
                    try { targets = JSON.parse(targets); } catch(e) { targets = {}; }
                }

                $.each(targets, function(q, val) {
                    var qLower = q.toLowerCase();
                    var prefix = isFinal ? 'final_check_' : 'ind_check_';
                    var checkElem = modal.find('#' + prefix + qLower);
                    if (checkElem.length) {
                        checkElem.prop('checked', true);
                        var inputElem = checkElem.closest('.form-group').find('.q-input');
                        inputElem.prop('disabled', false).attr('required', true).val(val);
                    }
                });

                // Lock financial inputs if activity has transactions
                if (hasTransactions) {
                    modal.find('select[name="budget_line_item_id"], select[name="source_of_fund_id"], select[name="uacs_code_id"], input[name="budget_amount"]').prop('disabled', true);
                } else {
                    modal.find('select[name="budget_line_item_id"], select[name="uacs_code_id"], input[name="budget_amount"]').prop('disabled', false);
                }

                modal.modal('show');
            },
            error: function(xhr) {
                alert('Failed to fetch activity details.');
            }
        });
    }

    /* --- MAIN JQUERY INITIALIZATION --- */
    $(document).ready(function() {

        // 1. Overriding Bootstrap Modal focus enforcement to prevent Select2 search conflicts
        $.fn.modal.Constructor.prototype._enforceFocus = function() {};

        // 2. Auto-hide alerts after 5 seconds
        const $flashAlerts =$(".alert-success, .alert-danger");
        if ($flashAlerts.length > 0) {
            window.setTimeout(function() {
                $flashAlerts.fadeTo(500, 0).slideUp(500, function(){$(this).remove(); 
                });
            }, 5000);
        }

        // 3. Tab Persistence Logic
        $('a[data-toggle="pill"]').on('shown.bs.tab', function (e) {
            localStorage.setItem('activeTab', $(e.target).attr('href'));
        });

        var activeTab = localStorage.getItem('activeTab');
        if (window.location.hash) {
            var hashTab = window.location.hash;
            $('#settingsCustomTab a[href="' + hashTab + '"]').tab('show');
            localStorage.setItem('activeTab', hashTab);
        } else if (activeTab && $('#settingsCustomTab a[href="' + activeTab + '"]').length) {
            $('#settingsCustomTab a[href="' + activeTab + '"]').tab('show');
        } else {
            $('#settingsCustomTab a[href="#tabs-activities"]').tab('show');
        }

        // 4. Safe Modal Select2 Initializer Function
        function initModalSelect2($modal) {$modal.find('.select2-modal').each(function() {
                if ($(this).hasClass("select2-hidden-accessible")) {
                    $(this).select2('destroy');
                }
                $(this).select2({
                    dropdownParent: $modal,
                    width: '100%'
                });
            });

            $modal.find('.select2-objective-tags').each(function() {
                if ($(this).hasClass("select2-hidden-accessible")) {
                    $(this).select2('destroy');
                }
                $(this).select2({
                    dropdownParent: $modal,
                    tags: true,
                    placeholder: "-- Select or Type New Objective --",
                    allowClear: true,
                    width: '100%'
                });
            });
        }

        // Initialize Select2 when modals are shown
        $('#modalFinalWfp, #modalIndicativeWfp').on('shown.bs.modal', function () {
            initModalSelect2($(this));
        });

        // Event listener for Budget Line changes across both modals
        $(document).on('change', '.budget-line-dropdown', function() {
            let modal = $(this).closest('.modal');
            let selectedLine = $(this).val();
            updateFundSourceDropdown(modal, selectedLine);
        });

        // Reset Modals when opened for "Add New Activity"
        $('#modalFinalWfp, #modalIndicativeWfp').on('show.bs.modal', function (e) {
            if (e.relatedTarget) {
                var modal = $(this);
                var isFinal = modal.attr('id') === 'modalFinalWfp';

                modal.find('form')[0].reset();
                modal.find('.activity-id-input').val('');
                
                if (isFinal) {
                    $('#finalWfpModalTitle').html('<i class="fas fa-edit mr-2"></i> Add Final WFP Activity');
                } else {
                    $('#indicativeWfpModalTitle').html('<i class="fas fa-edit mr-2"></i> Add Indicative WFP Activity');
                }

                modal.find('select[name="source_of_fund_id"]').prop('disabled', true);
                modal.find('select[name="budget_line_item_id"], select[name="uacs_code_id"], input[name="budget_amount"]').prop('disabled', false);
                modal.find('.q-input').prop('disabled', true);
                
                modal.find('.select2-modal, .select2-objective-tags').val('').trigger('change');
            }
        });

        // Toggle Quarter Target inputs via Checkboxes
        $(document).on('change', '.q-checkbox', function() {
            let targetInput = $(this).closest('.form-group').find('.q-input');
            if ($(this).is(':checked')) {
                targetInput.prop('disabled', false).attr('required', true);
            } else {
                targetInput.prop('disabled', true).removeAttr('required').val('');
            }
        });

        // 5. Currency Masking & Balance Checks
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

        // 6. Custom File Label & Import Form Spinner
        $('.custom-file-input').on('change', function() {
            let fileName = $(this).val().split('\\').pop();$(this).next('.custom-file-label').addClass("selected").html(fileName);
        });

        $('#importForm').on('submit', function() {
            let btn = $(this).find('button[type="submit"]');
            btn.prop('disabled', true);
            btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
            return true;
        });

        @if(session('import_summary'))
            $('#importSummaryModal').modal('show');
        @endif

        // 7. Realignment Dynamic Loader
        $('#realign_source_selector').on('change', function() {
            let sourceId = $(this).val();
            if (!sourceId) return;

            let container = $('#realignment_container');
            container.html('<div class="text-center py-5"><i class="fas fa-sync fa-spin fa-2x"></i></div>');
            let fetchUrl = "{{ url('admin/settings/get-realignment-table') }}/" + sourceId;

            $.get(fetchUrl, function(data) {
                container.html(data);
            }).fail(function(xhr) {
                console.log(xhr.responseText);
                container.html('<div class="alert alert-danger">Route not found (404). Check web.php.</div>');
            });
        });

        // 8. Pool Fund Modal Logic
        $('.btn-pool-fund').on('click', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const budget = parseFloat($(this).data('budget'));
            const currentPooled = $(this).data('pooled');
            const currentRemarks = $(this).data('remarks');

            $('#pool_activity_id').val(id);
            $('#pool_activity_name').text(name);
            $('#pool_max_display').text(budget.toLocaleString(undefined, { minimumFractionDigits: 2 }));
            $('#pool_input_amount').val(currentPooled).attr('max', budget); 
            $('#pool_remarks').val(currentRemarks); 

            $('#poolFundModal').modal('show');
        });

        // 9. Tooltips
        $('[data-toggle="tooltip"]').tooltip({ html: true });

        // 10. Price Format Sanitization on Submit
        $(document).on('input', '.price-format', function() {
            let value = $(this).val().replace(/,/g, '');
            if (!isNaN(value) && value.length > 0) {
                let parts = value.split(".");
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                $(this).val(parts.join("."));
            }
        });

        // 11. Employee Signatories Select2 AJAX
        $('.select2-employee').select2({
            theme: 'bootstrap4',
            placeholder: 'Search Official...',
            minimumInputLength: 3,
            dropdownParent: $('#modal-signatory'),
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
            templateResult: function (repo) {
                if (repo.loading) return repo.text;
                return $("<div class='select2-result-employee'><div><strong>" + repo.text + "</strong></div></div>");
            },
            templateSelection: function (repo) {
                return repo.text || repo.placeholder;
            }
        });

        $('#wfp_type').on('change', function() {
            updateLabelOptions($(this).val());
        });

        $('#signatoryForm').on('submit', function(e) {
            e.preventDefault();
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: "{{ route('settings.signatories.save') }}",
                method: "POST",
                data: $(this).serialize(),
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.success,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                },
                error: function(xhr) {
                    submitBtn.prop('disabled', false).text('Save Signatory');
                    let errorMsg = xhr.responseJSON?.message || 'Could not save signatory.';
                    Swal.fire({ icon: 'error', title: 'Oops...', text: errorMsg });
                }
            });
        });

        // 12. Print Modal WFP Selection & Logic
        if ($.fn.select2) {
            $('#activitySelect').select2({
                dropdownParent: $('#wfpPrintModal')
            });
        }

        $('.open-wfp-modal').click(function() {
            var sourceId = $(this).data('source-id');
            var sourceName = $(this).data('source-name');
            var wfpType = $(this).data('wfp-type') || 'indicative';

            $('#modalSourceId').val(sourceId);
            $('#modalSourceName').text(sourceName);
            $('#printWfpTypeInput').val(wfpType); 
            $('#wfpPrintForm').attr('action', '{{ route("settings.print") }}');

            $('#selectAllActivities').prop('checked', true).trigger('change');
            $('#planModifierSelect').val('');
            $('#activitySelect').empty().trigger('change');

            $.ajax({
                url: '{{ route("settings.get-activities", "") }}/' + sourceId,
                type: 'GET',
                data: { type: wfpType },
                success: function(response) {
                    var options = '';
                    if(response.activities && response.activities.length > 0) {
                        $.each(response.activities, function(index, act) {
                            options += '<option value="' + act.id + '">' + act.name + ' (Cost: ₱' + Number(act.budget_adjusted).toLocaleString(undefined, {minimumFractionDigits: 2}) + ')</option>';
                        });
                    }
                    $('#activitySelect').html(options).trigger('change');
                }
            });

            $('#wfpPrintModal').modal('show');
        });

        $('#selectAllActivities').change(function() {
            if ($(this).is(':checked')) {$('#activitySelectionContainer').hide();
                $('#activitySelect').prop('required', false);
                $('#activityRequiredMsg').hide();
            } else {
                $('#activitySelectionContainer').show();
                $('#activitySelect').prop('required', true);
            }
        });

        $('#wfpPrintForm').submit(function(e) {
            var selectAll = $('#selectAllActivities').is(':checked');
            var selectedActs = $('#activitySelect').val();

            if (!selectAll && (!selectedActs || selectedActs.length === 0)) {
                e.preventDefault();
                $('#activityRequiredMsg').show();
                return false;
            }
            
            setTimeout(function() {
                $('#wfpPrintModal').modal('hide');
            }, 500);
        });

        // 13. Bulk Convert Checkbox & Action Handlers
        $('#selectAllIndicative').on('change', function() {
            $('.indicative-checkbox').prop('checked',$(this).is(':checked'));
        });

        $('#btnBulkConvert').on('click', function(e) {
            e.preventDefault();
            
            var selectedIds = [];
            $('.indicative-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });

            if (selectedIds.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Selection',
                    text: 'Please select at least one approved activity to convert.'
                });
                return;
            }

            Swal.fire({
                title: 'Carry Over Selected Activities?',
                text: "Only activities with approved Fund Sources (NEP-Current/GAA) will be carried over to Final WFP.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Carry Over'
            }).then((result) => {
                if (result.isConfirmed) {
                    var form = $('<form>', {
                        'method': 'POST',
                        'action': '{{ route("settings.indicative.bulk-convert") }}'
                    });

                    form.append($('<input>', {
                        'type': 'hidden',
                        'name': '_token',
                        'value': '{{ csrf_token() }}'
                    }));

                    $.each(selectedIds, function(index, id) {
                        form.append($('<input>', {
                            'type': 'hidden',
                            'name': 'activity_ids[]',
                            'value': id
                        }));
                    });

                    $('body').append(form);
                    form.submit();
                }
            });
        });

        // Handler for Single Activity Conversion with SweetAlert2 Modal
        $(document).on('click', '.btn-single-convert', function(e) {
            e.preventDefault();
            
            var activityId = $(this).data('id');
            var activityName = $(this).data('name');
            var convertUrl = '{{ route("settings.indicative.convert", ":id") }}'.replace(':id', activityId);

            Swal.fire({
                title: 'Carry Over Activity?',
                html: "Are you sure you want to carry over <strong>" + activityName + "</strong> to the Final WFP?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Carry Over',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create form dynamically and submit
                    var form = $('<form>', {
                        'method': 'POST',
                        'action': convertUrl
                    });

                    form.append($('<input>', {
                        'type': 'hidden',
                        'name': '_token',
                        'value': '{{ csrf_token() }}'
                    }));

                    $('body').append(form);
                    form.submit();
                }
            });
        });

        // --- 1. INDICATIVE WFP DIRECT PRINT (Bypasses Modal) ---
        $(document).on('click', '.btn-print-indicative-wfp', function(e) {
            e.preventDefault();
            
            let sourceId = $(this).data('source-id');
            let printUrl = "{{ route('settings.activity.printWfp') }}?" + $.param({
                source_of_fund_id: sourceId,
                wfp_view_type: 'indicative',
                select_all: 1
            });

            // Open direct PDF stream in a new tab
            window.open(printUrl, '_blank');
        });


        // --- 2. FINAL WFP PRINT MODAL TRIGGER ---
        $(document).on('click', '.open-wfp-modal', function(e) {
            e.preventDefault();

            let sourceId = $(this).data('source-id');
            let sourceName = $(this).data('source-name');
            let wfpType = $(this).data('wfp-type') || 'final';

            // Populate Modal Fields
            $('#wfp_source_id').val(sourceId);
            $('#modalSourceName').text(sourceName);
            $('#wfp_view_type').val(wfpType);

            // Fetch Activities for Select2 filtering in Final WFP Print Modal
            fetchActivitiesForPrintModal(sourceId, wfpType);

            $('#wfpPrintModal').modal('show');
        });

        // --- 3. VERIFY WFP AJAX LOGIC ---
        $('#formVerifyWfp').on('submit', function(e) {
            e.preventDefault();

            let codeInput = $('#tracking_code_input').val().trim();
            let resultBox = $('#verificationResultContainer');
            let btn = $('#btnVerifySubmit');

            if (!codeInput) return;

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Verifying...');
            resultBox.hide().empty();

            $.ajax({
                url: "{{ route('settings.activity.verifyWfp') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    tracking_code: codeInput
                },
                success: function(response) {
                    btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i> Verify');

                    let html = `
                        <div class="card border-0 shadow-sm mb-0">
                            <div class="card-header bg-success text-white py-2 px-3 d-flex align-items-center justify-content-between">
                                <span class="font-weight-bold small text-uppercase">
                                    <i class="fas fa-check-circle mr-1"></i> Official System Document
                                </span>
                                <span class="badge badge-light text-success font-weight-bold px-2 py-1">VERIFIED</span>
                            </div>
                            <div class="card-body bg-white p-3">
                                <table class="table table-sm table-borderless mb-0 text-dark" style="font-size: 13px;">
                                    <tbody>
                                        <tr class="border-bottom">
                                            <td class="text-muted font-weight-bold py-2" style="width: 42%;">Tracking Code:</td>
                                            <td class="py-2"><span class="font-weight-bold text-primary">${response.tracking_code}</span></td>
                                        </tr>
                                        <tr class="border-bottom">
                                            <td class="text-muted font-weight-bold py-2">Fund Source:</td>
                                            <td class="py-2 font-weight-bold text-dark">${response.fund_source}</td>
                                        </tr>
                                        <tr class="border-bottom">
                                            <td class="text-muted font-weight-bold py-2">WFP Plan Type:</td>
                                            <td class="py-2">
                                                <span class="badge ${response.wfp_type === 'FINAL' ? 'badge-success' : 'badge-warning'} font-weight-bold px-2 py-1">
                                                    ${response.wfp_type}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr class="border-bottom">
                                            <td class="text-muted font-weight-bold py-2">Generating Section:</td>
                                            <td class="py-2 font-weight-bold text-navy">${response.section_name}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted font-weight-bold py-2">Date Generated:</td>
                                            <td class="py-2 text-secondary">${response.generated_at}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;

                    resultBox.html(html).fadeIn();
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i> Verify');

                    let errorMsg = xhr.responseJSON && xhr.responseJSON.message 
                        ? xhr.responseJSON.message 
                        : 'Verification failed. No matching WFP record was found in system logs.';

                    let html = `
                        <div class="card border-0 shadow-sm mb-0">
                            <div class="card-header bg-danger text-white py-2 px-3 d-flex align-items-center justify-content-between">
                                <span class="font-weight-bold small text-uppercase">
                                    <i class="fas fa-times-circle mr-1"></i> Verification Failed
                                </span>
                                <span class="badge badge-light text-danger font-weight-bold px-2 py-1">UNVERIFIED</span>
                            </div>
                            <div class="card-body bg-white p-3 text-center">
                                <p class="text-muted mb-0 small">${errorMsg}</p>
                            </div>
                        </div>
                    `;

                    resultBox.html(html).fadeIn();
                }
            });
        });

        // Reset results when modal closes
        $('#modalVerifyWfp').on('hidden.bs.modal', function() {
            $('#verificationResultContainer').hide().empty();
            $('#tracking_code_input').val('');
        });


    });
</script>
@endsection