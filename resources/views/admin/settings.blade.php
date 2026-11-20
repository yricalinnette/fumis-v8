@extends('layouts.adminlte')

@section('content')
<style>
    /* ==========================================================================
       1. SELECT2 & FORM CONTROL STANDARDIZATION
       ========================================================================== */
    .select2-container--default .select2-selection--single,
    .select2-container--bootstrap4 .select2-selection--single {
        border: 1px solid #cbd5e1 !important;
        height: calc(2.25rem + 2px) !important;
        display: flex !important;
        align-items: center !important;
        border-radius: 0.375rem;
    }

    .input-group > .select2-container--default {
        flex: 1 1 auto !important;
        width: 1% !important;
    }

    .input-group > .select2-container--default .select2-selection--single {
        border-top-left-radius: 0 !important;
        border-bottom-left-radius: 0 !important;
    }

    .select2-selection__rendered {
        color: #334155 !important;
        padding-left: 0.75rem !important;
    }

    .select2-selection__arrow {
        height: 34px !important;
        top: 3px !important;
    }

    .select2-results__option {
        padding: 10px 15px !important;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.9rem;
    }

    .select2-results__option--highlighted {
        background-color: #2563eb !important;
    }

    .select2-results__option .badge {
        font-size: 0.7rem;
        padding: 4px 6px;
        letter-spacing: 0.5px;
    }

    .select2-result-employee__title {
        font-weight: 600;
        color: #1e293b;
        display: block;
    }

    .select2-result-employee__id {
        font-size: 11px;
        color: #2563eb;
        background: #eff6ff;
        padding: 1px 5px;
        border-radius: 3px;
        margin-top: 3px;
        display: inline-block;
    }

    .badge {
        padding: 0.45em 0.75em;
        font-size: 82%;
        font-weight: 600;
        letter-spacing: 0.02em;
    }

    .tooltip-inner {
        max-width: 350px;
        text-align: left;
        padding: 10px;
    }

    /* ==========================================================================
       2. MAIN SETTINGS SHELL & PRIMARY TABS
       ========================================================================== */
    .settings-shell-card {
        border: 1px solid #e2e8f0;
        border-radius: 0.625rem;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        overflow: hidden;
    }

    .settings-main-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.75rem 1.25rem 0 1.25rem;
    }

    #settingsCustomTab .nav-link {
        color: #64748b;
        font-weight: 600;
        font-size: 0.9rem;
        padding: 0.65rem 1.25rem;
        border: 1px solid transparent;
        border-top-left-radius: 0.5rem;
        border-top-right-radius: 0.5rem;
        transition: all 0.15s ease;
    }

    #settingsCustomTab .nav-link:hover:not(.active) {
        color: #0f172a;
        background-color: rgba(255, 255, 255, 0.6);
    }

    #settingsCustomTab .nav-link.active {
        color: #0f172a;
        background-color: #ffffff;
        border-color: #e2e8f0 #e2e8f0 #ffffff;
        box-shadow: inset 0 3px 0 #2563eb;
    }

    /* ==========================================================================
       3. WFP STAGE SWITCHER (INDICATIVE vs. FINAL)
       ========================================================================== */
    .wfp-stage-switcher {
        background-color: #f1f5f9;
        border: 1px solid #cbd5e1;
        border-radius: 0.5rem;
        padding: 0.25rem;
        display: inline-flex;
        gap: 0.25rem;
    }

    .wfp-stage-switcher .nav-link {
        color: #475569;
        font-weight: 600;
        font-size: 0.875rem;
        padding: 0.5rem 1.35rem;
        border-radius: 0.375rem;
        border: 1px solid transparent;
        transition: all 0.18s ease;
        display: inline-flex;
        align-items: center;
    }

    .wfp-stage-switcher .nav-link:hover:not(.active) {
        color: #0f172a;
        background-color: rgba(255, 255, 255, 0.75);
    }

    .wfp-stage-switcher .nav-link.active {
        background-color: #ffffff;
        color: #0f172a;
        border-color: #cbd5e1;
        box-shadow: 0 2px 4px rgba(15, 23, 42, 0.08);
    }

    .wfp-stage-switcher .nav-link#subtab-indicative-link.active {
        box-shadow: inset 0 -3px 0 #f59e0b, 0 2px 4px rgba(15, 23, 42, 0.08);
    }

    .wfp-stage-switcher .nav-link#subtab-final-link.active {
        box-shadow: inset 0 -3px 0 #10b981, 0 2px 4px rgba(15, 23, 42, 0.08);
    }

    /* ==========================================================================
       4. CONTEXT STRIPS, FUND SOURCE CARDS & TABLES
       ========================================================================== */
    .wfp-context-strip {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 1rem 1.25rem;
    }

    .wfp-context-strip.indicative-strip {
        border-left: 4px solid #f59e0b;
        background: linear-gradient(90deg, rgba(254, 243, 199, 0.35) 0%, #ffffff 35%);
    }

    .wfp-context-strip.final-strip {
        border-left: 4px solid #10b981;
        background: linear-gradient(90deg, rgba(209, 250, 229, 0.35) 0%, #ffffff 35%);
    }

    .wfp-context-strip.neutral-strip {
        border-left: 4px solid #2563eb;
        background: linear-gradient(90deg, rgba(219, 234, 254, 0.35) 0%, #ffffff 35%);
    }

    .wfp-source-card {
        border: 1px solid #cbd5e1;
        border-radius: 0.5rem;
        overflow: hidden;
        background: #ffffff;
    }

    .wfp-source-header-indicative {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #ffffff;
        padding: 0.75rem 1rem;
    }

    .wfp-source-header-final {
        background: linear-gradient(135deg, #065f46 0%, #047857 100%);
        color: #ffffff;
        padding: 0.75rem 1rem;
    }

    .wfp-metric-box {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.14);
        border-radius: 0.375rem;
        padding: 0.3rem 0.65rem;
        text-align: right;
    }

    .wfp-metric-label {
        font-size: 0.62rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.75);
        display: block;
        font-weight: 700;
    }

    .wfp-table thead th {
        background-color: #f8fafc;
        color: #334155;
        font-size: 0.73rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        border-color: #cbd5e1;
    }

    .wfp-table tbody td {
        font-size: 0.84rem;
        border-color: #e2e8f0;
        vertical-align: middle;
    }

    .wfp-table tbody tr:hover {
        background-color: #f8fafc;
    }

    .wfp-empty-state {
        background: #f8fafc;
        border: 1.5px dashed #cbd5e1;
        border-radius: 0.625rem;
        padding: 3rem 1.5rem;
        text-align: center;
    }

    .wfp-empty-icon {
        width: 54px;
        height: 54px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
    }
</style>

<div class="container-fluid">
    {{-- Notifications --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible shadow-sm">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="icon fas fa-check-circle mr-1"></i> {{ session('success') }}
        </div>
    @endif
    
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible shadow-sm">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <ul class="mb-0 pl-3">@foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
        </div>
    @endif

    @if($errors->has('import_error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i> {{ $errors->first('import_error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="card settings-shell-card bg-white">
        {{-- PRIMARY NAVIGATION TABS --}}
        <div class="settings-main-header">
            <ul class="nav nav-tabs border-bottom-0" id="settingsCustomTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link" id="tabs-activities-tab" data-toggle="pill" href="#tabs-activities" role="tab">
                        <i class="fas fa-tasks mr-2 text-primary"></i>Activity Allocation (WFP)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tabs-realignment-tab" data-toggle="pill" href="#tabs-realignment" role="tab">
                        <i class="fas fa-random mr-2 text-info"></i>Budget Realignment
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tabs-signatories-tab" data-toggle="pill" href="#tabs-signatories" role="tab">
                        <i class="fas fa-users-cog mr-2 text-secondary"></i>WFP Signatories
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="settingsCustomTabContent">

                {{-- ==================== TAB 1: ACTIVITY ALLOCATION ==================== --}}
                <div class="tab-pane fade" id="tabs-activities" role="tabpanel">

                    <div id="balance_info" class="alert alert-info d-none mb-3">
                        <i class="fas fa-info-circle mr-1"></i> <span id="balance_text"></span>
                    </div>

                    {{-- SEGMENTED STAGE SWITCHER & VERIFY ACTION --}}
                    <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-3 border-bottom">
                        <ul class="nav wfp-stage-switcher" id="wfpSubTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="subtab-indicative-link" data-toggle="pill" href="#subtab-indicative" role="tab" aria-controls="subtab-indicative" aria-selected="true">
                                    <i class="fas fa-file-signature mr-2 text-warning"></i>
                                    <span>Indicative WFP</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="subtab-final-link" data-toggle="pill" href="#subtab-final" role="tab" aria-controls="subtab-final" aria-selected="false">
                                    <i class="fas fa-check-circle mr-2 text-success"></i>
                                    <span>Final WFP</span>
                                </a>
                            </li>
                        </ul>

                        @if($isAdminOrBudget)
                            <button type="button" 
                                    class="btn btn-outline-info btn-sm font-weight-bold shadow-sm mt-2 mt-sm-0 px-3" 
                                    data-toggle="modal" 
                                    data-target="#modalVerifyWfp"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalVerifyWfp">
                                <i class="fas fa-qrcode mr-1"></i> Verify WFP Code
                            </button>
                        @endif
                    </div>

                    {{-- SUB-TABS CONTENT CONTAINER --}}
                    <div class="tab-content" id="wfpSubTabsContent">

                        {{-- ==================== SUB-TAB 1: INDICATIVE WFP (is_converted = 0) ==================== --}}
                        <div class="tab-pane fade show active" id="subtab-indicative" role="tabpanel">

                            @php
                                $indicativeYears = $activities->groupBy('source.fiscal_year')->filter(function($yearGroups) {
                                    return $yearGroups->contains(function($act) {
                                        return !$act->is_converted;
                                    });
                                });
                                $latestIndicativeYear = $indicativeYears->keys()->max();
                            @endphp

                            {{-- Stage Context Strip --}}
                            <div class="wfp-context-strip indicative-strip shadow-sm d-flex flex-wrap justify-content-between align-items-center mb-3">
                                <div class="pr-md-3">
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="badge badge-warning text-dark font-weight-bold px-2 py-1 mr-2">INDICATIVE STAGE</span>
                                        <h6 class="font-weight-bold text-dark mb-0">Proposed / NEP Work &amp; Financial Plan</h6>
                                    </div>
                                    <p class="text-muted small mb-0">
                                        Activities encoded here are for <strong>budget proposal &amp; NEP planning</strong> and cannot be obligated until their fund source is approved and carried over to the Final WFP.
                                    </p>
                                </div>
                                <div class="mt-3 mt-md-0">
                                    <button class="btn btn-warning font-weight-bold shadow-sm px-3" data-toggle="modal" data-target="#modalIndicativeWfp">
                                        <i class="fas fa-plus mr-1"></i> Add Indicative Activity
                                    </button>
                                </div>
                            </div>

                            {{-- Bulk Action Bar (Only shown when Indicative records exist) --}}
                            @if($indicativeYears->isNotEmpty())
                                <div class="d-flex flex-wrap justify-content-between align-items-center bg-light border rounded px-3 py-2 mb-3 shadow-sm">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="selectAllIndicative">
                                        <label class="custom-control-label font-weight-bold text-dark small" for="selectAllIndicative">
                                            Select All Approved Indicative Activities
                                        </label>
                                    </div>
                                    <button type="button" id="btnBulkConvert" class="btn btn-sm btn-primary font-weight-bold shadow-sm mt-2 mt-sm-0">
                                        <i class="fas fa-exchange-alt mr-1"></i> Carry Over Selected to Final WFP
                                    </button>
                                </div>
                            @endif

                            <div id="indicativeActivitiesAccordion">
                                @forelse($indicativeYears as $year => $yearGroups)
                                    @php 
                                        $isCurrent = ($year == $currentYear); 
                                        $collapseId = "collapseIndYear" . $year;
                                        $shouldExpand = ($year == $latestIndicativeYear) || $isCurrent;
                                    @endphp
                                    
                                    <div class="card card-warning card-outline shadow-sm mb-4">
                                        <div class="card-header p-0 bg-white">
                                            <button class="btn btn-link btn-block text-left text-dark font-weight-bold py-2 px-3 d-flex justify-content-between align-items-center text-decoration-none" type="button" data-toggle="collapse" data-target="#{{ $collapseId }}">
                                                <span>
                                                    <i class="fas {{ $shouldExpand ? 'fa-folder-open' : 'fa-folder' }} mr-2 text-warning"></i> 
                                                    FISCAL YEAR {{ $year }} &mdash; <span class="text-muted">INDICATIVE WFP</span>
                                                    @if($isCurrent) <span class="badge badge-success ml-2">Current Year</span> @endif
                                                </span>
                                                <i class="fas fa-chevron-down text-muted small"></i>
                                            </button>
                                        </div>

                                        <div id="{{ $collapseId }}" class="collapse {{ $shouldExpand ? 'show' : '' }}">
                                            <div class="card-body pt-2 pb-4 px-3">
                                                @foreach($groupedBySection as $sectionName => $fundSourcesInThisSection)
                                                    @php
                                                        $hasIndicativeInSection = $fundSourcesInThisSection->contains(function($source) use ($year) {
                                                            return $source->activities->contains(function($act) use ($year) {
                                                                return !$act->is_converted && optional($act->source)->fiscal_year == $year;
                                                            });
                                                        });
                                                        if(!$hasIndicativeInSection) continue;
                                                    @endphp

                                                    <div class="mt-4 mb-3">
                                                        <h5 class="text-navy font-weight-bold border-bottom pb-2 mb-0">
                                                            <i class="fas fa-layer-group mr-2 text-primary"></i> {{ strtoupper($sectionName) }}
                                                        </h5>
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

                                                            $isSourceApproved = in_array(
                                                                strtolower(trim((string) $source->funding_source)),
                                                                ['nep-current', 'gaa', 'current', 'conap']
                                                            );
                                                        @endphp

                                                        <div class="wfp-source-card shadow-sm mb-4">
                                                            <div class="wfp-source-header-indicative">
                                                                <div class="row align-items-center">
                                                                    <div class="col-lg-5 mb-2 mb-lg-0">
                                                                        <div class="d-flex align-items-center flex-wrap">
                                                                            <h6 class="mb-0 font-weight-bold text-white mr-2">
                                                                                <i class="fas fa-university mr-2 text-warning"></i>{{ strtoupper($source->name) }}
                                                                            </h6>
                                                                            @if($isSourceApproved)
                                                                                <span class="badge badge-success px-2 py-1" style="font-size: 0.7rem;"><i class="fas fa-check-circle mr-1"></i> Approved &bull; Ready to Carry Over</span>
                                                                            @else
                                                                                <span class="badge badge-warning text-dark px-2 py-1" style="font-size: 0.7rem;"><i class="fas fa-clock mr-1"></i> Pending NEP</span>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-7">
                                                                        <div class="d-flex flex-wrap justify-content-lg-end align-items-center" style="gap: 0.5rem;">
                                                                            <button type="button" class="btn btn-xs btn-danger shadow-sm font-weight-bold px-2 py-1 btn-print-indicative-wfp" 
                                                                                data-source-id="{{ $source->id }}" 
                                                                                data-source-name="{{ $source->name }}">
                                                                                <i class="fas fa-file-pdf mr-1"></i> PRINT WFP
                                                                            </button>
                                                                            <div class="wfp-metric-box">
                                                                                <span class="wfp-metric-label">Distributed / Adjusted</span>
                                                                                <span class="font-weight-bold small">
                                                                                    <span class="text-warning">₱{{ number_format($netDistributedTotal, 2) }}</span>
                                                                                    <span class="text-white-50 mx-1">/</span>
                                                                                    <span>₱{{ number_format($adjustedAmount, 2) }}</span>
                                                                                </span>
                                                                            </div>
                                                                            <div class="wfp-metric-box">
                                                                                <span class="wfp-metric-label">Pooled</span>
                                                                                <span class="text-danger font-weight-bold small">₱{{ number_format($totalPooled, 2) }}</span>
                                                                            </div>
                                                                            <div class="wfp-metric-box">
                                                                                <span class="wfp-metric-label">Obligated</span>
                                                                                <span class="text-warning font-weight-bold small">₱{{ number_format($totalObligations, 2) }}</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="table-responsive">
                                                                <table class="table table-bordered table-sm wfp-table m-0">
                                                                    <thead>
                                                                        <tr class="text-center">
                                                                            <th rowspan="2" style="width: 4%; vertical-align: middle;"><i class="fas fa-check-square"></i></th>
                                                                            <th rowspan="2" style="width: 17%; vertical-align: middle;">Objective</th>
                                                                            <th rowspan="2" style="width: 26%; vertical-align: middle;">Activities</th>
                                                                            <th colspan="2" style="width: 14%;">Timeframe</th>
                                                                            <th colspan="4" style="width: 15%;">Targets</th>
                                                                            <th rowspan="2" style="width: 12%; vertical-align: middle;">Cost</th>
                                                                            <th rowspan="2" style="width: 12%; vertical-align: middle;">Actions</th>
                                                                        </tr>
                                                                        <tr class="text-center">
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
                                                                                    <td class="text-center">
                                                                                        @if($isSourceApproved)
                                                                                            <input type="checkbox" class="indicative-checkbox" value="{{ $activity->id }}">
                                                                                        @else
                                                                                            <input type="checkbox" disabled data-toggle="tooltip" title="Cannot carry over: Fund source is still pending NEP approval.">
                                                                                        @endif
                                                                                    </td>

                                                                                    @if($index === 0)
                                                                                        <td rowspan="{{ $activitiesByObjective->count() }}" class="align-top font-weight-bold p-2 bg-white text-dark" style="word-break: break-word;">
                                                                                            {{ $objective }}
                                                                                        </td>
                                                                                    @endif

                                                                                    <td class="p-2" style="word-break: break-word;">
                                                                                        <div style="white-space: pre-line; line-height: 1.35;">{{ trim($activity->name) }}</div>
                                                                                        <div class="mt-1">
                                                                                            @if(!$isSourceApproved)
                                                                                                <span class="badge badge-warning text-dark"><i class="fas fa-clock mr-1"></i>Pending NEP</span>
                                                                                            @else
                                                                                                <span class="badge badge-light border text-secondary">Indicative</span>
                                                                                            @endif
                                                                                            @if($activity->pooled_amount > 0)
                                                                                                <span class="badge badge-danger ml-1">₱{{ number_format($activity->pooled_amount, 2) }} pooled</span>
                                                                                            @endif
                                                                                        </div>
                                                                                    </td>

                                                                                    <td class="text-center text-nowrap">{{ $activity->start_date ? \Carbon\Carbon::parse($activity->start_date)->format('d M Y') : '-' }}</td>
                                                                                    <td class="text-center text-nowrap">{{ $activity->end_date ? \Carbon\Carbon::parse($activity->end_date)->format('d M Y') : '-' }}</td>

                                                                                    <td class="text-center">{{ $targets['Q1'] ?? '' }}</td>
                                                                                    <td class="text-center">{{ $targets['Q2'] ?? '' }}</td>
                                                                                    <td class="text-center">{{ $targets['Q3'] ?? '' }}</td>
                                                                                    <td class="text-center">{{ $targets['Q4'] ?? '' }}</td>

                                                                                    <td class="text-right font-weight-bold text-dark text-nowrap">
                                                                                        ₱{{ number_format($activity->budget_adjusted - $activity->pooled_amount, 2) }}
                                                                                    </td>

                                                                                    <td class="text-center">
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
                                                        </div>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="wfp-empty-state my-2">
                                        <div class="wfp-empty-icon bg-warning text-dark">
                                            <i class="fas fa-file-signature fa-lg"></i>
                                        </div>
                                        <h6 class="font-weight-bold text-dark mb-1">No Indicative WFP Activities Encoded</h6>
                                        <p class="text-muted small mb-3">
                                            Start building your proposed NEP Work &amp; Financial Plan by encoding your unit's planned activities.
                                        </p>
                                        <button class="btn btn-warning btn-sm font-weight-bold shadow-sm px-3" data-toggle="modal" data-target="#modalIndicativeWfp">
                                            <i class="fas fa-plus mr-1"></i> Encode First Indicative Activity
                                        </button>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        {{-- ==================== SUB-TAB 2: FINAL WFP (is_converted = 1) ==================== --}}
                        <div class="tab-pane fade" id="subtab-final" role="tabpanel">

                            @php
                                $finalYears = $activities->groupBy('source.fiscal_year')->filter(function($yearGroups) {
                                    return $yearGroups->contains(function($activity) {
                                        return $activity->is_converted;
                                    });
                                });
                            @endphp

                            {{-- Stage Context Strip --}}
                            <div class="wfp-context-strip final-strip shadow-sm d-flex flex-wrap justify-content-between align-items-center mb-3">
                                <div class="pr-md-3">
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="badge badge-success font-weight-bold px-2 py-1 mr-2">FINAL &amp; APPROVED</span>
                                        <h6 class="font-weight-bold text-dark mb-0">Active Work &amp; Financial Plan (GAA / SAA / CONAP)</h6>
                                    </div>
                                    <p class="text-muted small mb-0">
                                        Activities listed here are <strong>approved and active</strong> for fund routing, obligation, and disbursement tracking.
                                    </p>
                                </div>
                                <div class="mt-3 mt-md-0">
                                    <button class="btn btn-success font-weight-bold shadow-sm px-3" data-toggle="modal" data-target="#modalFinalWfp">
                                        <i class="fas fa-plus mr-1"></i> Add Final Activity
                                    </button>
                                </div>
                            </div>
                            
                            <div id="finalActivitiesAccordion">
                                @forelse($finalYears as $year => $yearGroups)
                                    @php 
                                        $isCurrent = ($year == $currentYear); 
                                        $collapseId = "collapseFinalYear" . $year;
                                    @endphp
                                    
                                    <div class="card card-success card-outline shadow-sm mb-4">
                                        <div class="card-header p-0 bg-white">
                                            <button class="btn btn-link btn-block text-left text-dark font-weight-bold py-2 px-3 d-flex justify-content-between align-items-center text-decoration-none" type="button" data-toggle="collapse" data-target="#{{ $collapseId }}">
                                                <span>
                                                    <i class="fas {{ $isCurrent ? 'fa-folder-open' : 'fa-folder' }} mr-2 text-success"></i> 
                                                    FISCAL YEAR {{ $year }} &mdash; <span class="text-success">FINAL WFP</span>
                                                    @if($isCurrent) <span class="badge badge-success ml-2">Current Year</span> @endif
                                                </span>
                                                <i class="fas fa-chevron-down text-muted small"></i>
                                            </button>
                                        </div>

                                        <div id="{{ $collapseId }}" class="collapse {{ $isCurrent ? 'show' : '' }}">
                                            <div class="card-body pt-2 pb-4 px-3">
                                                @foreach($groupedBySection as $sectionName => $fundSourcesInThisSection)
                                                    @php
                                                        $hasConvertedInSection = $fundSourcesInThisSection->contains(function($source) use ($year) {
                                                            return $source->activities->contains(function($act) use ($year) {
                                                                return $act->is_converted && optional($act->source)->fiscal_year == $year;
                                                            });
                                                        });
                                                        if(!$hasConvertedInSection) continue;
                                                    @endphp

                                                    <div class="mt-4 mb-3">
                                                        <h5 class="text-navy font-weight-bold border-bottom pb-2 mb-0">
                                                            <i class="fas fa-layer-group mr-2 text-success"></i> {{ strtoupper($sectionName) }}
                                                        </h5>
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

                                                        <div class="wfp-source-card shadow-sm mb-4">
                                                            <div class="wfp-source-header-final">
                                                                <div class="row align-items-center">
                                                                    <div class="col-lg-5 mb-2 mb-lg-0">
                                                                        <div class="d-flex align-items-center flex-wrap">
                                                                            <h6 class="mb-0 font-weight-bold text-white mr-2">
                                                                                <i class="fas fa-university mr-2"></i>{{ strtoupper($source->name) }}
                                                                            </h6>
                                                                            <span class="badge badge-light text-success font-weight-bold px-2 py-1" style="font-size: 0.7rem;">
                                                                                <i class="fas fa-check-circle mr-1"></i>FINAL WFP
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-7">
                                                                        <div class="d-flex flex-wrap justify-content-lg-end align-items-center" style="gap: 0.5rem;">
                                                                            <button type="button" class="btn btn-xs btn-dark shadow-sm font-weight-bold px-2 py-1 open-wfp-modal" 
                                                                                data-source-id="{{ $source->id }}" 
                                                                                data-source-name="{{ $source->name }}" 
                                                                                data-wfp-type="final">
                                                                                <i class="fas fa-file-pdf mr-1"></i> PRINT WFP
                                                                            </button>
                                                                            <div class="wfp-metric-box">
                                                                                <span class="wfp-metric-label">Distributed / Adjusted</span>
                                                                                <span class="font-weight-bold small">
                                                                                    <span class="text-warning">₱{{ number_format($netDistributedTotal, 2) }}</span>
                                                                                    <span class="text-white-50 mx-1">/</span>
                                                                                    <span>₱{{ number_format($adjustedAmount, 2) }}</span>
                                                                                </span>
                                                                            </div>
                                                                            <div class="wfp-metric-box">
                                                                                <span class="wfp-metric-label">Pooled</span>
                                                                                <span class="text-warning font-weight-bold small">₱{{ number_format($totalPooled, 2) }}</span>
                                                                            </div>
                                                                            <div class="wfp-metric-box">
                                                                                <span class="wfp-metric-label">Obligated</span>
                                                                                <span class="text-white font-weight-bold small">₱{{ number_format($totalObligations, 2) }}</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="table-responsive">
                                                                <table class="table table-bordered table-sm wfp-table m-0">
                                                                    <thead>
                                                                        <tr class="text-center">
                                                                            <th rowspan="2" style="width: 4%; vertical-align: middle;"><i class="fas fa-check-square"></i></th>
                                                                            <th rowspan="2" style="width: 17%; vertical-align: middle;">Objective</th>
                                                                            <th rowspan="2" style="width: 26%; vertical-align: middle;">Activities</th>
                                                                            <th colspan="2" style="width: 14%;">Timeframe</th>
                                                                            <th colspan="4" style="width: 15%;">Targets</th>
                                                                            <th rowspan="2" style="width: 12%; vertical-align: middle;">Cost</th>
                                                                            <th rowspan="2" style="width: 12%; vertical-align: middle;">Actions</th>
                                                                        </tr>
                                                                        <tr class="text-center">
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
                                                                                    <td class="text-center">
                                                                                        <span class="text-success"><i class="fas fa-check-circle"></i></span>
                                                                                    </td>

                                                                                    @if($index === 0)
                                                                                        <td rowspan="{{ $activitiesByObjective->count() }}" class="align-top font-weight-bold p-2 bg-white text-dark" style="word-break: break-word;">
                                                                                            {{ $objective }}
                                                                                        </td>
                                                                                    @endif

                                                                                    <td class="p-2 align-top">
                                                                                        <div class="text-dark" style="white-space: pre-line; word-break: break-word; line-height: 1.35;">
                                                                                            {{ trim($activity->name) }}
                                                                                        </div>

                                                                                        <div class="mt-1">
                                                                                            <span class="badge badge-success px-2 py-1 font-weight-bold" style="font-size: 10px; letter-spacing: 0.4px;" title="Approved Final WFP">
                                                                                                <i class="fas fa-check-circle mr-1"></i>APPROVED
                                                                                            </span>
                                                                                            @if($activity->pooled_amount > 0)
                                                                                                <span class="badge badge-danger ml-1">₱{{ number_format($activity->pooled_amount, 2) }} pooled</span>
                                                                                            @endif
                                                                                        </div>
                                                                                    </td>

                                                                                    <td class="text-center text-nowrap">{{ $activity->start_date ? \Carbon\Carbon::parse($activity->start_date)->format('d M Y') : '-' }}</td>
                                                                                    <td class="text-center text-nowrap">{{ $activity->end_date ? \Carbon\Carbon::parse($activity->end_date)->format('d M Y') : '-' }}</td>

                                                                                    <td class="text-center">{{ $targets['Q1'] ?? '' }}</td>
                                                                                    <td class="text-center">{{ $targets['Q2'] ?? '' }}</td>
                                                                                    <td class="text-center">{{ $targets['Q3'] ?? '' }}</td>
                                                                                    <td class="text-center">{{ $targets['Q4'] ?? '' }}</td>

                                                                                    <td class="text-right font-weight-bold text-dark text-nowrap">
                                                                                        ₱{{ number_format($activity->budget_adjusted - $activity->pooled_amount, 2) }}
                                                                                    </td>

                                                                                    <td class="text-center">
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
                                                        </div>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="wfp-empty-state my-2">
                                        <div class="wfp-empty-icon bg-success text-white">
                                            <i class="fas fa-check-double fa-lg"></i>
                                        </div>
                                        <h6 class="font-weight-bold text-dark mb-1">No Final WFP Activities Yet</h6>
                                        <p class="text-muted small mb-3">
                                            Encode an approved Final WFP activity directly or carry over approved items from the Indicative WFP tab.
                                        </p>
                                        <button class="btn btn-success btn-sm font-weight-bold shadow-sm px-3" data-toggle="modal" data-target="#modalFinalWfp">
                                            <i class="fas fa-plus mr-1"></i> Encode First Final Activity
                                        </button>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ==================== TAB 2: BUDGET REALIGNMENT ==================== --}}
                <div class="tab-pane fade" id="tabs-realignment" role="tabpanel">
                    <div class="wfp-context-strip neutral-strip shadow-sm mb-4">
                        <div class="row align-items-center">
                            <div class="col-lg-7 mb-3 mb-lg-0">
                                <div class="d-flex align-items-center mb-1">
                                    <span class="badge badge-primary font-weight-bold px-2 py-1 mr-2">FY {{ $currentYear }}</span>
                                    <h6 class="font-weight-bold text-dark mb-0">Fund Source Realignment Tool</h6>
                                </div>
                                <p class="text-muted small mb-0">
                                    Select a current-year fund source to view and redistribute unobligated or pooled balances across approved activities.
                                </p>
                            </div>
                            <div class="col-lg-5">
                                <label class="small font-weight-bold text-uppercase text-muted mb-1">Select Fund Source to Realign</label>
                                <select id="realign_source_selector" class="form-control select2" style="width: 100%;">
                                    <option value="">-- Choose Fund Source --</option>
                                    @php $hasCurrentYearSources = false; @endphp
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
                            </div>
                        </div>
                    </div>

                    <div id="realignment_container" class="wfp-empty-state" style="min-height: 280px;">
                        <div class="wfp-empty-icon bg-light border text-primary">
                            <i class="fas fa-hand-pointer fa-lg"></i>
                        </div>
                        <h6 class="font-weight-bold text-dark mb-1">Ready to Realign</h6>
                        <p class="text-muted small mb-0">Choose a Fiscal Year {{ $currentYear }} fund source from the selector above to begin redistributing balances.</p>
                    </div>
                </div>

                {{-- ==================== TAB 3: WFP SIGNATORIES ==================== --}}
                <div class="tab-pane fade" id="tabs-signatories" role="tabpanel" aria-labelledby="tabs-signatories-tab">
                    <div class="wfp-context-strip neutral-strip shadow-sm d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div class="pr-md-3">
                            <div class="d-flex align-items-center mb-1">
                                <span class="badge badge-info font-weight-bold px-2 py-1 mr-2">DOCUMENT LAYOUT</span>
                                <h6 class="font-weight-bold text-dark mb-0">WFP Signatory Rules</h6>
                            </div>
                            <p class="text-muted small mb-0">
                                Configure the officials printed in the signature blocks of generated WFP PDFs
                                (@if($isAdminOrBudget) viewing <strong>all sections</strong> @else scoped to your <strong>assigned section</strong> @endif).
                            </p>
                        </div>
                        <div class="mt-3 mt-md-0">
                            <button type="button" class="btn btn-primary font-weight-bold shadow-sm px-3" onclick="addSignatoryModal()">
                                <i class="fas fa-plus mr-1"></i> Add Signatory Rule
                            </button>
                        </div>
                    </div>

                    <div class="wfp-source-card shadow-sm">
                        <div class="table-responsive">
                            <table class="table table-hover wfp-table mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 18%;">Section / Unit</th>
                                        <th style="width: 14%;">WFP Type</th>
                                        <th style="width: 18%;">Signatory Label</th>
                                        <th style="width: 38%;">Assigned Official</th>
                                        <th style="width: 12%;" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($signatorySettings as $setting)
                                        <tr>
                                            <td>
                                                <span class="badge badge-light border text-dark px-2 py-1">
                                                    {{ $setting->section_display_name ?? 'N/A' }}
                                                </span>
                                            </td>
                                            <td class="text-uppercase font-weight-bold text-primary">{{ $setting->wfp_type }}</td>
                                            <td class="font-weight-bold text-dark">{{ $setting->label }}</td>
                                            <td>
                                                <div class="font-weight-bold text-dark">{{ $setting->employee_name }}</div>
                                                <small class="text-muted text-uppercase">{{ $setting->designation }}</small>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <button class="btn btn-xs btn-info mr-1" 
                                                            onclick="editSignatory({{ json_encode($setting) }})" 
                                                            title="Edit Signatory">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-xs btn-danger" 
                                                            onclick="deleteSignatory({{ $setting->id }})" 
                                                            title="Delete Signatory">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="p-4">
                                                <div class="wfp-empty-state border-0 bg-transparent py-3">
                                                    <div class="wfp-empty-icon bg-light border text-muted">
                                                        <i class="fas fa-user-tie fa-lg"></i>
                                                    </div>
                                                    <h6 class="font-weight-bold text-dark mb-1">No Signatory Rules Configured</h6>
                                                    <p class="text-muted small mb-0">Click <strong>"Add Signatory Rule"</strong> above to assign preparing and approving officials.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
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
                <h5 class="modal-title" id="finalWfpModalTitle"><i class="fas fa-plus-circle mr-2"></i> Add Final WFP Activity</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            
            <form action="{{ route('settings.activity.storeWfp') }}" method="POST" id="formFinalWfp">
                @csrf
                <input type="hidden" name="id" class="activity-id-input">
                <input type="hidden" name="wfp_type" value="final">
                
                <div class="modal-body">
                    <div class="row">
                        {{-- Budget Line Item --}}
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Budget Line Item <span class="text-danger">*</span></label>
                            @php
                                $activeFinalLines = $finalBudgetLineItems ?? $budgetLineItems;
                            @endphp
                            <select name="budget_line_item_id" class="form-control select2-modal budget-line-dropdown" style="width: 100%;" required>
                                @if($activeFinalLines->isEmpty())
                                    <option value="">-- No Approved Budget Line Assigned to Your Unit --</option>
                                @else
                                    <option value="">-- Select Budget Line Item --</option>
                                    @foreach($activeFinalLines as $item)
                                        <option value="{{ $item->id }}">{{ $item->budget_line_item_name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        {{-- Fund Source --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Fund Source <span class="text-danger">*</span></label>
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
                                <label class="font-weight-bold">Activity Classification <span class="text-danger">*</span></label>
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
                                <label class="font-weight-bold">Cost / Budget (Original) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text bg-light">₱</span></div>
                                    <input type="text" name="budget_amount" class="form-control price-format" placeholder="0.00" required>
                                </div>
                            </div>
                        </div>

                        {{-- UACS Code --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">UACS Code / Account Title <span class="text-danger">*</span></label>
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
                                <label class="font-weight-bold">Activity Name <span class="text-danger">*</span></label>
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
                                <label class="font-weight-bold">Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">End Date <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                        </div>

                        {{-- Targets --}}
                        <div class="col-md-12 mt-3">
                            <label class="font-weight-bold">Target Quarters &amp; Physical Targets <span class="text-danger">*</span></label>
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
                <h5 class="modal-title" id="indicativeWfpModalTitle"><i class="fas fa-plus-circle mr-2"></i> Add Indicative WFP Activity</h5>
                <button type="button" class="close text-dark" data-dismiss="modal"><span>&times;</span></button>
            </div>
            
            <form action="{{ route('settings.activity.storeWfp') }}" method="POST" id="formIndicativeWfp">
                @csrf
                <input type="hidden" name="id" class="activity-id-input">
                <input type="hidden" name="wfp_type" value="indicative">
                
                <div class="modal-body">
                    <div class="row">
                        {{-- Budget Line Item --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Budget Line Item <span class="text-danger">*</span></label>
                                @php
                                    $activeIndicativeLines = $indicativeBudgetLineItems ?? $budgetLineItems;
                                @endphp
                                <select name="budget_line_item_id" class="form-control select2-modal budget-line-dropdown" required style="width: 100%;">
                                    @if($activeIndicativeLines->isEmpty())
                                        <option value="">-- No Indicative (NEP) Budget Line Assigned to Your Unit --</option>
                                    @else
                                        <option value="">-- Select Line Item --</option>
                                        @foreach($activeIndicativeLines as $item)
                                            <option value="{{ $item->id }}">{{ $item->budget_line_item_name }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        </div>

                        {{-- Fund Source --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Fund Source <span class="text-danger">*</span></label>
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
                                <label class="font-weight-bold">Activity Classification <span class="text-danger">*</span></label>
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
                                <label class="font-weight-bold">Cost / Budget (Original) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text bg-light">₱</span></div>
                                    <input type="text" name="budget_amount" class="form-control price-format" placeholder="0.00" required>
                                </div>
                            </div>
                        </div>

                        {{-- UACS Code --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">UACS Code / Account Title <span class="text-danger">*</span></label>
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
                                <label class="font-weight-bold">Activity Name <span class="text-danger">*</span></label>
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
                                <label class="font-weight-bold">Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">End Date <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                        </div>

                        {{-- Targets --}}
                        <div class="col-md-12 mt-3">
                            <label class="font-weight-bold">Target Quarters &amp; Physical Targets <span class="text-danger">*</span></label>
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

{{-- SIGNATORY MODAL --}}
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
                        <select name="label" id="label" class="form-control" required></select>
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

{{-- DELETE SOURCE MODAL --}}
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

{{-- POOL FUND MODAL --}}
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

{{-- WFP PRINT OPTIONS MODAL --}}
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

                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="selectAllActivities" name="select_all" value="1" checked>
                        <label class="form-check-label font-weight-bold" for="selectAllActivities">Select All Activities (Print whole WFP)</label>
                    </div>

                    <div class="form-group" id="activitySelectionContainer" style="display: none;">
                        <label for="activitySelect">Select Specific Activities (For Realignment / Replan):</label>
                        <select name="activity_ids[]" id="activitySelect" class="form-control select2" multiple="multiple" style="width: 100%;" data-placeholder="Choose activities to include...">
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

{{-- BUDGET EXCEEDED ERROR MODAL POPUP --}}
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

{{-- VERIFY WFP MODAL --}}
@if($isAdminOrBudget)
    <div class="modal fade" id="modalVerifyWfp" tabindex="-1" role="dialog" aria-labelledby="modalVerifyWfpLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 8px; overflow: hidden;">
                
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

                    <div id="verificationResultContainer" class="mt-4" style="display: none;"></div>
                </div>

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
        $('.select2-employee').val(null).trigger('change');
        $('#wfp_type').val(data.wfp_type);
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
                    url: "{{ url('settings/signatories/delete') }}/" + id,
                    method: "DELETE",
                    data: { _token: "{{ csrf_token() }}" },
                    success: function(response) {
                        Swal.fire('Deleted!', response.success, 'success').then(() => {
                            location.reload();
                        });
                    },
                    error: function(xhr) {
                        let errorMsg = xhr.responseJSON?.message || 'Could not delete the record.';
                        Swal.fire('Error!', errorMsg, 'error');
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

    function openEditWfpModal(activityId, hasTransactions) {
        $.ajax({
            url: `{{ url('/settings/activities') }}/${activityId}/edit`,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                var isFinal = data.is_converted == 1;
                var modal = isFinal ? $('#modalFinalWfp') : $('#modalIndicativeWfp');
                var modalTitle = isFinal ? $('#finalWfpModalTitle') : $('#indicativeWfpModalTitle');
                
                modalTitle.html('<i class="fas fa-edit mr-2"></i> Edit ' + (isFinal ? 'Final' : 'Indicative') + ' WFP Activity');

                modal.find('.activity-id-input').val(data.id);
                modal.find('textarea[name="name"]').val(data.name);
                modal.find('input[name="budget_amount"]').val(data.budget);
                modal.find('select[name="classification"]').val(data.classification);
                modal.find('select[name="is_for_procurement"]').val(data.is_for_procurement ? '1' : '0');

                if (data.start_date) {
                    modal.find('input[name="start_date"]').val(data.start_date.split(' ')[0]);
                }
                if (data.end_date) {
                    modal.find('input[name="end_date"]').val(data.end_date.split(' ')[0]);
                }

                var lineItemSelect = modal.find('select[name="budget_line_item_id"]');
                var fundSourceDropdown = modal.find('select[name="source_of_fund_id"]');
                var uacsDropdown = modal.find('select[name="uacs_code_id"]');

                lineItemSelect.prop('disabled', false).val(data.budget_line_item_id).trigger('change.select2');
                updateFundSourceDropdown(modal, data.budget_line_item_id);
                fundSourceDropdown.prop('disabled', false).val(data.source_of_fund_id).trigger('change.select2');
                uacsDropdown.prop('disabled', false).val(data.uacs_code_id).trigger('change.select2');

                var objSelect = modal.find('select[name="objective"]');
                if (objSelect.find("option[value='" + data.objective + "']").length === 0) {
                    var newOption = new Option(data.objective, data.objective, true, true);
                    objSelect.append(newOption).trigger('change.select2');
                } else {
                    objSelect.val(data.objective).trigger('change.select2');
                }

                modal.find('.q-checkbox').prop('checked', false);
                modal.find('.q-input').val('').prop('disabled', true).removeAttr('required');

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

                if (hasTransactions) {
                    lineItemSelect.prop('disabled', true);
                    fundSourceDropdown.prop('disabled', true);
                    modal.find('input[name="budget_amount"]').prop('disabled', true);
                } else {
                    modal.find('input[name="budget_amount"]').prop('disabled', false);
                }

                modal.modal('show');
            },
            error: function(xhr) {
                console.error(xhr.responseText);
                alert('Failed to fetch activity details.');
            }
        });
    }

    /* --- MAIN JQUERY INITIALIZATION --- */
    $(document).ready(function() {

        $.fn.modal.Constructor.prototype._enforceFocus = function() {};

        const $flashAlerts = $(".alert-success, .alert-danger").not('#balance_info');
        if ($flashAlerts.length > 0) {
            window.setTimeout(function() {
                $flashAlerts.fadeTo(500, 0).slideUp(500, function() {
                    $(this).remove(); 
                });
            }, 5000);
        }

        // Main Tab & Sub-Tab Persistence
        $('#settingsCustomTab a[data-toggle="pill"], #settingsCustomTab a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            localStorage.setItem('activeMainSettingsTab', $(e.target).attr('href'));
        });

        $('#wfpSubTabs a[data-toggle="pill"]').on('shown.bs.tab', function (e) {
            localStorage.setItem('activeWfpSubTab', $(e.target).attr('href'));
        });

        $('#modalFinalWfp form, #poolFundModal form').on('submit', function () {
            localStorage.setItem('activeMainSettingsTab', '#tabs-activities');
            localStorage.setItem('activeWfpSubTab', '#subtab-final');
        });

        $('#modalIndicativeWfp form').on('submit', function () {
            localStorage.setItem('activeMainSettingsTab', '#tabs-activities');
            localStorage.setItem('activeWfpSubTab', '#subtab-indicative');
        });

        var activeMainTab = localStorage.getItem('activeMainSettingsTab');
        var activeSubTab  = localStorage.getItem('activeWfpSubTab');
        var hashTab       = window.location.hash;

        if (hashTab === '#subtab-final' || hashTab === '#subtab-indicative') {
            $('#settingsCustomTab a[href="#tabs-activities"]').tab('show');
            $('#wfpSubTabs a[href="' + hashTab + '"]').tab('show');
            localStorage.setItem('activeMainSettingsTab', '#tabs-activities');
            localStorage.setItem('activeWfpSubTab', hashTab);
        } else if (hashTab && $('#settingsCustomTab a[href="' + hashTab + '"]').length) {
            $('#settingsCustomTab a[href="' + hashTab + '"]').tab('show');
            localStorage.setItem('activeMainSettingsTab', hashTab);
        } else if (activeMainTab && $('#settingsCustomTab a[href="' + activeMainTab + '"]').length) {
            $('#settingsCustomTab a[href="' + activeMainTab + '"]').tab('show');
        } else {
            $('#settingsCustomTab a[href="#tabs-activities"]').tab('show');
        }

        if ((!hashTab || hashTab === '#tabs-activities') && activeSubTab && $('#wfpSubTabs a[href="' + activeSubTab + '"]').length) {
            $('#wfpSubTabs a[href="' + activeSubTab + '"]').tab('show');
        }

        function initModalSelect2($modal) {
            $modal.find('.select2-modal').each(function() {
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

        $('#modalFinalWfp, #modalIndicativeWfp').on('shown.bs.modal', function () {
            initModalSelect2($(this));
        });

        $(document).on('change', '.budget-line-dropdown', function() {
            let modal = $(this).closest('.modal');
            let selectedLine = $(this).val();
            updateFundSourceDropdown(modal, selectedLine);
        });

        $('#modalFinalWfp, #modalIndicativeWfp').on('show.bs.modal', function (e) {
            if (e.relatedTarget) {
                var modal = $(this);
                var isFinal = modal.attr('id') === 'modalFinalWfp';

                modal.find('form')[0].reset();
                modal.find('.activity-id-input').val('');
                
                if (isFinal) {
                    $('#finalWfpModalTitle').html('<i class="fas fa-plus-circle mr-2"></i> Add Final WFP Activity');
                } else {
                    $('#indicativeWfpModalTitle').html('<i class="fas fa-plus-circle mr-2"></i> Add Indicative WFP Activity');
                }

                modal.find('select[name="source_of_fund_id"]').prop('disabled', true);
                modal.find('select[name="budget_line_item_id"], select[name="uacs_code_id"], input[name="budget_amount"]').prop('disabled', false);
                modal.find('.q-input').prop('disabled', true);
                
                modal.find('.select2-modal, .select2-objective-tags').val('').trigger('change');
            }
        });

        $(document).on('change', '.q-checkbox', function() {
            let targetInput = $(this).closest('.form-group').find('.q-input');
            if ($(this).is(':checked')) {
                targetInput.prop('disabled', false).attr('required', true);
            } else {
                targetInput.prop('disabled', true).removeAttr('required').val('');
            }
        });

        // Currency Masking & Balance Checks
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

        // Realignment Dynamic Loader
        $('#realign_source_selector').on('change', function() {
            let sourceId = $(this).val();
            let container = $('#realignment_container');
            if (!sourceId) return;

            container.removeClass('wfp-empty-state').addClass('border rounded bg-white p-3 shadow-sm');
            container.html('<div class="text-center py-5 text-muted"><i class="fas fa-sync fa-spin fa-2x mb-2 text-primary"></i><div class="small font-weight-bold">Loading realignment ledger...</div></div>');
            let fetchUrl = "{{ url('admin/settings/get-realignment-table') }}/" + sourceId;

            $.get(fetchUrl, function(data) {
                container.html(data);
            }).fail(function(xhr) {
                console.log(xhr.responseText);
                container.html('<div class="alert alert-danger m-0">Route not found (404). Check web.php.</div>');
            });
        });

        // Pool Fund Modal Logic
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

        // Tooltips
        $('[data-toggle="tooltip"]').tooltip({ html: true });

        // Price Format Sanitization on Input
        $(document).on('input', '.price-format', function() {
            let value = $(this).val().replace(/,/g, '');
            if (!isNaN(value) && value.length > 0) {
                let parts = value.split(".");
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                $(this).val(parts.join("."));
            }
        });

        // Employee Signatories Select2 AJAX
        $('.select2-employee').select2({
            theme: 'bootstrap4',
            placeholder: 'Search Official...',
            minimumInputLength: 3,
            dropdownParent: $('#modal-signatory'),
            ajax: {
                url: "{{ url('settings/employees/search') }}",
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
                            };
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

        // Unified Print Modal WFP Selection & Logic
        if ($.fn.select2) {
            $('#activitySelect').select2({
                dropdownParent: $('#wfpPrintModal')
            });
        }

        $(document).off('click', '.open-wfp-modal').on('click', '.open-wfp-modal', function(e) {
            e.preventDefault();

            var sourceId   = $(this).data('source-id');
            var sourceName = $(this).data('source-name');
            var wfpType    = $(this).data('wfp-type') || 'final';

            $('#modalSourceId, #wfp_source_id').val(sourceId);
            $('#modalSourceName').text(sourceName);
            $('#printWfpTypeInput, #wfp_view_type').val(wfpType);
            $('#wfpPrintForm').attr('action', '{{ route("settings.print") }}');

            $('#selectAllActivities').prop('checked', true).trigger('change');
            $('#planModifierSelect').val('');
            $('#activitySelect').empty().trigger('change');

            if (typeof fetchActivitiesForPrintModal === 'function') {
                fetchActivitiesForPrintModal(sourceId, wfpType);
            } else {
                $.ajax({
                    url: '{{ route("settings.get-activities", "") }}/' + sourceId,
                    type: 'GET',
                    data: { type: wfpType },
                    success: function(response) {
                        var options = '';
                        if (response.activities && response.activities.length > 0) {
                            $.each(response.activities, function(index, act) {
                                options += '<option value="' + act.id + '">' + act.name + ' (Cost: ₱' + Number(act.budget_adjusted).toLocaleString(undefined, {minimumFractionDigits: 2}) + ')</option>';
                            });
                        }
                        $('#activitySelect').html(options).trigger('change');
                    }
                });
            }

            $('#wfpPrintModal').modal('show');
        });

        $('#selectAllActivities').change(function() {
            if ($(this).is(':checked')) {
                $('#activitySelectionContainer').hide();
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

        // Bulk Convert Checkbox & Action Handlers
        $('#selectAllIndicative').on('change', function() {
            $('.indicative-checkbox').prop('checked', $(this).is(':checked'));
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
                text: "Only activities with approved Fund Sources (NEP-Current/GAA/CONAP) will be carried over to Final WFP.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Carry Over'
            }).then((result) => {
                if (result.isConfirmed) {
                    localStorage.setItem('activeMainSettingsTab', '#tabs-activities');
                    localStorage.setItem('activeWfpSubTab', '#subtab-indicative');

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

        // Single Activity Conversion
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
                    localStorage.setItem('activeMainSettingsTab', '#tabs-activities');
                    localStorage.setItem('activeWfpSubTab', '#subtab-indicative');

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

        // Indicative WFP Direct Print
        $(document).on('click', '.btn-print-indicative-wfp', function(e) {
            e.preventDefault();
            
            let sourceId = $(this).data('source-id');
            let printUrl = "{{ route('settings.activity.printWfp') }}?" + $.param({
                source_of_fund_id: sourceId,
                wfp_view_type: 'indicative',
                select_all: 1
            });

            window.open(printUrl, '_blank');
        });

        // Verify WFP AJAX Logic
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

        $('#modalVerifyWfp').on('hidden.bs.modal', function() {
            $('#verificationResultContainer').hide().empty();
            $('#tracking_code_input').val('');
        });

    });
</script>
@endsection