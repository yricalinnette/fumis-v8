@extends('layouts.adminlte')

@section('content')
<style>
    /* ==========================================================================
       1. SELECT2 & FORM CONTROL STANDARDIZATION
       ========================================================================== */
    .select2-container--default .select2-selection--single {
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
    .badge {
        padding: 0.45em 0.75em;
        font-size: 82%;
        font-weight: 600;
        letter-spacing: 0.02em;
    }
    .tooltip {
        z-index: 1060 !important;
    }
    .fy-toggle-icon {
        transition: transform 0.2s ease-in-out;
    }

    /* ==========================================================================
       2. MAIN SETTINGS SHELL & PRIMARY TABS
       ========================================================================== */
    .fs-shell-card {
        border: 1px solid #e2e8f0;
        border-radius: 0.625rem;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        overflow: hidden;
    }

    .fs-main-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.75rem 1.25rem 0 1.25rem;
    }

    #fundSourceTab .nav-link {
        color: #64748b;
        font-weight: 600;
        font-size: 0.9rem;
        padding: 0.65rem 1.35rem;
        border: 1px solid transparent;
        border-top-left-radius: 0.5rem;
        border-top-right-radius: 0.5rem;
        transition: all 0.15s ease;
    }

    #fundSourceTab .nav-link:hover:not(.active) {
        color: #0f172a;
        background-color: rgba(255, 255, 255, 0.65);
    }

    #fundSourceTab .nav-link.active {
        color: #0f172a;
        background-color: #ffffff;
        border-color: #e2e8f0 #e2e8f0 #ffffff;
    }

    #fundSourceTab .nav-link#nep-tab-link.active {
        box-shadow: inset 0 3px 0 #2563eb;
    }

    #fundSourceTab .nav-link#approved-tab-link.active {
        box-shadow: inset 0 3px 0 #10b981;
    }

    /* ==========================================================================
       3. CONTEXT STRIPS, TOOLBARS & TABLES
       ========================================================================== */
    .fs-context-strip {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 1rem 1.25rem;
    }

    .fs-context-strip.nep-strip {
        border-left: 4px solid #2563eb;
        background: linear-gradient(90deg, rgba(219, 234, 254, 0.35) 0%, #ffffff 35%);
    }

    .fs-context-strip.approved-strip {
        border-left: 4px solid #10b981;
        background: linear-gradient(90deg, rgba(209, 250, 229, 0.35) 0%, #ffffff 35%);
    }

    .fs-filter-bar {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 0.65rem 1rem;
    }

    .fs-table-card {
        border: 1px solid #cbd5e1;
        border-radius: 0.5rem;
        overflow: hidden;
        background: #ffffff;
    }

    .fs-table thead th {
        background-color: #f8fafc;
        color: #334155;
        font-size: 0.73rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        border-bottom: 1px solid #cbd5e1;
        border-top: 0;
    }

    .fs-table tbody td {
        font-size: 0.86rem;
        border-color: #e2e8f0;
        vertical-align: middle;
    }

    .fs-table tbody tr.fund-row:hover {
        background-color: #f8fafc;
    }

    .fy-header-row {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
        color: #ffffff;
        cursor: pointer;
    }

    .section-header-row {
        background-color: #f1f5f9 !important;
    }

    .fs-empty-state {
        background: #f8fafc;
        border: 1.5px dashed #cbd5e1;
        border-radius: 0.625rem;
        padding: 3rem 1.5rem;
        text-align: center;
    }

    .fs-empty-icon {
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

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 font-weight-bold text-dark mb-0">Fund Source Settings</h1>
            <small class="text-muted">Manage National Expenditure Program (NEP) proposals and approved GAA / SAA / CONAP allotments.</small>
        </div>
    </div>

    {{-- NAVIGATION TABS --}}
    <div class="card fs-shell-card bg-white">
        <div class="fs-main-header">
            <ul class="nav nav-tabs border-bottom-0" id="fundSourceTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="nep-tab-link" data-toggle="pill" href="#nep-tab-content" role="tab" aria-controls="nep-tab-content" aria-selected="true">
                        <i class="fas fa-file-invoice-dollar mr-2 text-primary"></i>NEP Funds (Indicative Planning)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="approved-tab-link" data-toggle="pill" href="#approved-tab-content" role="tab" aria-controls="approved-tab-content" aria-selected="false">
                        <i class="fas fa-check-circle mr-2 text-success"></i>Approved Funds (Current &amp; Conap)
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="fundSourceTabContent">
                
                {{-- ==================== TAB 1: NEP SOURCES ==================== --}}
                <div class="tab-pane fade show active" id="nep-tab-content" role="tabpanel" aria-labelledby="nep-tab-link">

                    {{-- Context Strip --}}
                    <div class="fs-context-strip nep-strip shadow-sm d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div class="pr-md-3">
                            <div class="d-flex align-items-center mb-1">
                                <span class="badge badge-primary font-weight-bold px-2 py-1 mr-2">NEP REGISTRY</span>
                                <h6 class="font-weight-bold text-dark mb-0">National Expenditure Program (Indicative Planning)</h6>
                            </div>
                            <p class="text-muted small mb-0">
                                Encode proposed budget allotments for Indicative WFP planning. Once approved under GAA/SAA, convert them to unlock obligation tracking.
                            </p>
                        </div>
                        <div class="mt-3 mt-md-0">
                            <button type="button" class="btn btn-primary font-weight-bold shadow-sm px-3" data-toggle="modal" data-target="#modal-add-nep">
                                <i class="fas fa-plus-circle mr-1"></i> Add New NEP
                            </button>
                        </div>
                    </div>

                    {{-- Filter Toolbar --}}
                    <div class="fs-filter-bar d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <span class="small font-weight-bold text-uppercase text-muted mb-2 mb-md-0">
                            <i class="fas fa-filter mr-1"></i> Filter Records
                        </span>
                        <div class="d-flex align-items-center flex-wrap" style="gap: 0.5rem;">
                            <select id="filter_nep_section" class="form-control form-control-sm" style="width: 210px;">
                                <option value="">-- All Sections --</option>
                                @foreach($sections as $sec)
                                    <option value="{{ $sec->id }}">{{ $sec->section_name }}</option>
                                @endforeach
                            </select>

                            <select id="filter_nep_fy" class="form-control form-control-sm" style="width: 140px;">
                                <option value="">-- All FY --</option>
                                @php $currentYear = date('Y'); @endphp
                                @for($i = $currentYear - 1; $i <= $currentYear + 3; $i++)
                                    <option value="{{ $i }}">FY {{ $i }}</option>
                                @endfor
                            </select>

                            <button type="button" class="btn btn-outline-secondary btn-sm" id="reset_nep_filters" title="Reset Filters">
                                <i class="fas fa-sync-alt mr-1"></i> Reset
                            </button>
                        </div>
                    </div>

                    <div class="fs-table-card shadow-sm">
                        <div class="table-responsive p-0">
                            <table class="table table-hover fs-table mb-0" id="nepTable">
                                <thead>
                                    <tr>
                                        <th class="py-3 pl-4" style="width: 32%;">Budget Line / Fund Name</th>
                                        <th class="py-3 text-center">Sync Status</th>
                                        <th class="py-3 text-right">Original Allotment</th>
                                        <th class="py-3 text-center">Pooled Funds</th>
                                        <th class="py-3 text-right">Adjusted Allotment</th>
                                        <th class="py-3 text-center">FY</th> 
                                        <th class="py-3 pr-4 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($nepSources as $fiscalYear => $yearSources)
                                        <tr class="fy-header-row" data-fy-header="{{ $fiscalYear }}">
                                            <td colspan="7" class="py-2 pl-4 font-weight-bold text-uppercase" style="letter-spacing: 0.06em; font-size: 0.82rem;">
                                                <div class="d-flex justify-content-between align-items-center pr-3">
                                                    <span><i class="fas fa-calendar-alt mr-2 text-info"></i> Fiscal Year {{ $fiscalYear }}</span>
                                                    <i class="fas fa-chevron-down fy-toggle-icon"></i>
                                                </div>
                                            </td>
                                        </tr>

                                        @foreach($yearSources->groupBy('section_id') as $sectionId => $groupedSources)
                                            @php
                                                $section = $sections->firstWhere('id', $sectionId);
                                                $sectionName = $section->section_name ?? 'Unassigned / General';
                                            @endphp
                                            <tr class="section-header-row" data-section-header="{{ $sectionId }}" data-fy="{{ $fiscalYear }}">
                                                <td colspan="7" class="py-2 pl-4 border-bottom border-top">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-folder-open text-primary mr-2"></i>
                                                        <span class="text-dark font-weight-bold text-uppercase" style="font-size: 0.78rem; letter-spacing: 0.05em;">{{ $sectionName }}</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            @foreach($groupedSources as $source)
                                                @php
                                                    $totalPooled = $source->activities->sum('pooled_amount');
                                                    $netAmount = $source->total_amount - $totalPooled;
                                                    $isConverted = ($source->funding_source === 'NEP-Current');
                                                @endphp
                                                <tr class="fund-row" data-section="{{ $source->section_id }}" data-fy="{{ $source->fiscal_year }}">
                                                    <td class="pl-5">
                                                        <div class="d-flex flex-column">
                                                            <span class="text-muted mb-1" style="font-size: 10.5px; font-weight: 600; text-transform: uppercase;">
                                                                {{ $source->budgetLineItem->budget_line_item_name ?? 'N/A' }}
                                                            </span>
                                                            <div class="d-flex align-items-center flex-wrap" style="gap: 0.4rem;">
                                                                <span class="text-dark font-weight-bold" style="font-size: 0.92rem;">{{ $source->name }}</span>
                                                                
                                                                @if($isConverted)
                                                                    <span class="badge badge-success px-2 py-1" style="font-size: 10px;" data-toggle="tooltip" title="Converted to Approved Fund (NEP-Current)">
                                                                        <i class="fas fa-check-circle mr-1"></i>Converted (NEP-Current)
                                                                    </span>
                                                                @else
                                                                    <span class="badge badge-primary px-2 py-1" style="font-size: 10px;">NEP (Indicative)</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        @if($source->spreadsheet_id)
                                                            <a href="https://docs.google.com/spreadsheets/d/{{ $source->spreadsheet_id }}" target="_blank" class="btn btn-xs btn-outline-success rounded-pill px-2 font-weight-bold" style="font-size: 11px;">
                                                                <i class="fas fa-file-excel mr-1"></i> Linked
                                                            </a>
                                                        @else
                                                            <span class="badge badge-light text-muted border py-1 px-2 rounded-pill" style="font-size: 10px;"><i class="fas fa-keyboard mr-1"></i> Manual</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-right font-weight-bold text-secondary text-nowrap">₱{{ number_format($source->total_amount, 2) }}</td>
                                                    <td class="text-center text-nowrap">
                                                        @if($totalPooled > 0)
                                                            <span class="text-danger font-weight-bold" style="font-size: 0.85rem;"><i class="fas fa-arrow-down mr-1 small"></i>₱{{ number_format($totalPooled, 2) }}</span>
                                                        @else
                                                            <span class="text-muted small">&mdash;</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-right text-nowrap">
                                                        <span class="text-primary font-weight-bold" style="font-size: 0.98rem;">₱{{ number_format($netAmount, 2) }}</span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge badge-dark py-1 px-2">{{ $source->fiscal_year }}</span>
                                                    </td> 
                                                    <td class="text-center pr-4">
                                                        <div class="btn-group shadow-sm">
                                                            @if(!$isConverted)
                                                                <button type="button" class="btn btn-xs btn-white border btn-convert-source px-2 py-1" 
                                                                    data-id="{{ $source->id }}" data-funding_source="{{ $source->funding_source }}" data-source_type="{{ $source->source_type }}" data-name="{{ str_replace('NEP ', '', $source->name) }}" data-budget_line_item_id="{{ $source->budget_line_item_id }}" data-fiscal_year="{{ $source->fiscal_year }}" data-allotment_class="{{ $source->allotment_class }}" data-total_amount="{{ $source->total_amount }}" data-section_id="{{ $source->section_id }}" data-spreadsheet_id="{{ $source->spreadsheet_id }}" data-sheet_name="{{ $source->sheet_name }}" data-toggle="tooltip" title="Convert to Approved Fund">
                                                                    <i class="fas fa-exchange-alt text-success"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-xs btn-white border btn-edit-source px-2 py-1" 
                                                                    data-id="{{ $source->id }}" 
                                                                    data-funding_source="{{ $source->funding_source }}" 
                                                                    data-source_type="{{ $source->source_type }}" 
                                                                    data-name="{{ $source->name }}" 
                                                                    data-budget_line_item_id="{{ $source->budget_line_item_id }}" 
                                                                    data-fiscal_year="{{ $source->fiscal_year }}" 
                                                                    data-allotment_class="{{ $source->allotment_class }}" 
                                                                    data-total_amount="{{ $source->total_amount }}" 
                                                                    data-section_id="{{ $source->section_id }}" 
                                                                    data-spreadsheet_id="{{ $source->spreadsheet_id }}" 
                                                                    data-sheet_name="{{ $source->sheet_name }}" 
                                                                    data-saadate="{{ optional($source->saa_date)->format('Y-m-d') }}"
                                                                    data-reference_number="{{ $source->reference_number }}" 
                                                                    data-fund_code="{{ $source->fund_code }}" 
                                                                    data-approp_code="{{ $source->approp_code }}" 
                                                                    data-url="{{ route('settings.fund_sources.update', $source->id) }}"
                                                                    data-toggle="tooltip" title="Edit">
                                                                    <i class="fas fa-edit text-info"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-xs btn-white border delete-source-btn px-2 py-1" data-id="{{ $source->id }}" data-name="{{ $source->name }}" data-count="{{ $source->activities->count() }}" data-toggle="tooltip" title="Delete">
                                                                    <i class="fas fa-trash-alt text-danger"></i>
                                                                </button>
                                                            @else
                                                                <span class="badge badge-light border text-muted px-2 py-1" style="font-size: 11px;" data-toggle="tooltip" title="Managed under Approved Funds tab">
                                                                    <i class="fas fa-lock mr-1"></i>Locked (Approved)
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    @empty
                                        <tr>
                                            <td colspan="7" class="p-4">
                                                <div class="fs-empty-state border-0 bg-transparent py-3">
                                                    <div class="fs-empty-icon bg-primary text-white">
                                                        <i class="fas fa-file-invoice-dollar fa-lg"></i>
                                                    </div>
                                                    <h6 class="font-weight-bold text-dark mb-1">No NEP Fund Sources Found</h6>
                                                    <p class="text-muted small mb-3">Click <strong>"Add New NEP"</strong> above to encode indicative allotments.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                    <tr id="no_nep_results" style="display: none;">
                                        <td colspan="7" class="text-center py-4 text-muted font-italic">No records match the selected filters.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- ==================== TAB 2: APPROVED FUNDS (Current, NEP-Current & Conap) ==================== --}}
                <div class="tab-pane fade" id="approved-tab-content" role="tabpanel" aria-labelledby="approved-tab-link">

                    {{-- Context Strip --}}
                    <div class="fs-context-strip approved-strip shadow-sm d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div class="pr-md-3">
                            <div class="d-flex align-items-center mb-1">
                                <span class="badge badge-success font-weight-bold px-2 py-1 mr-2">ACTIVE ALLOTMENTS</span>
                                <h6 class="font-weight-bold text-dark mb-0">Approved Funds Registry (Current, NEP-Current &amp; Conap)</h6>
                            </div>
                            <p class="text-muted small mb-0">
                                Active GAA, SAA, and Continuing Appropriations available for Final WFP activity allocation, obligation, and disbursement.
                            </p>
                        </div>
                        <div class="mt-3 mt-md-0">
                            <button type="button" class="btn btn-success font-weight-bold shadow-sm px-3" data-toggle="modal" data-target="#modal-add-approved">
                                <i class="fas fa-plus-circle mr-1"></i> Add Approved Fund
                            </button>
                        </div>
                    </div>

                    {{-- Filter Toolbar --}}
                    <div class="fs-filter-bar d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <span class="small font-weight-bold text-uppercase text-muted mb-2 mb-md-0">
                            <i class="fas fa-filter mr-1"></i> Filter Records
                        </span>
                        <div class="d-flex align-items-center flex-wrap" style="gap: 0.5rem;">
                            <select id="filter_approved_category" class="form-control form-control-sm" style="width: 170px;">
                                <option value="">-- All Categories --</option>
                                <option value="Current">Current</option>
                                <option value="NEP-Current">NEP-Current</option>
                                <option value="Conap">Conap</option>
                            </select>

                            <select id="filter_approved_section" class="form-control form-control-sm" style="width: 210px;">
                                <option value="">-- All Sections --</option>
                                @foreach($sections as $sec)
                                    <option value="{{ $sec->id }}">{{ $sec->section_name }}</option>
                                @endforeach
                            </select>

                            <select id="filter_approved_fy" class="form-control form-control-sm" style="width: 140px;">
                                <option value="">-- All FY --</option>
                                @php $currentYear = date('Y'); @endphp
                                @for($i = $currentYear - 1; $i <= $currentYear + 3; $i++)
                                    <option value="{{ $i }}">FY {{ $i }}</option>
                                @endfor
                            </select>

                            <button type="button" class="btn btn-outline-secondary btn-sm" id="reset_approved_filters" title="Reset Filters">
                                <i class="fas fa-sync-alt mr-1"></i> Reset
                            </button>
                        </div>
                    </div>

                    <div class="fs-table-card shadow-sm">
                        <div class="table-responsive p-0">
                            <table class="table table-hover fs-table mb-0" id="approvedTable">
                                <thead>
                                    <tr>
                                        <th class="py-3 pl-4" style="width: 28%;">Budget Line / Fund Name</th>
                                        <th class="py-3 text-center" style="width: 13%;">Funding Source</th>
                                        <th class="py-3 text-center" style="width: 11%;">Sync Status</th>
                                        <th class="py-3 text-right">Original Allotment</th>
                                        <th class="py-3 text-center">Pooled Funds</th>
                                        <th class="py-3 text-right">Adjusted Allotment</th>
                                        <th class="py-3 text-center">FY</th> 
                                        <th class="py-3 pr-4 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($approvedSources as $fiscalYear => $yearSources)
                                        <tr class="fy-header-row" data-fy-header="{{ $fiscalYear }}">
                                            <td colspan="8" class="py-2 pl-4 font-weight-bold text-uppercase" style="letter-spacing: 0.06em; font-size: 0.82rem;">
                                                <div class="d-flex justify-content-between align-items-center pr-3">
                                                    <span><i class="fas fa-calendar-alt mr-2 text-success"></i> Fiscal Year {{ $fiscalYear }}</span>
                                                    <i class="fas fa-chevron-down fy-toggle-icon"></i>
                                                </div>
                                            </td>
                                        </tr>

                                        @foreach($yearSources->groupBy('section_id') as $sectionId => $groupedSources)
                                            @php
                                                $section = $sections->firstWhere('id', $sectionId);
                                                $sectionName = $section->section_name ?? 'Unassigned / General';
                                            @endphp
                                            <tr class="section-header-row" data-section-header="{{ $sectionId }}" data-fy="{{ $fiscalYear }}">
                                                <td colspan="8" class="py-2 pl-4 border-bottom border-top">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-folder-open text-success mr-2"></i>
                                                        <span class="text-dark font-weight-bold text-uppercase" style="font-size: 0.78rem; letter-spacing: 0.05em;">{{ $sectionName }}</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            @foreach($groupedSources as $source)
                                                @php
                                                    $totalPooled = $source->activities->sum('pooled_amount');
                                                    $netAmount = $source->total_amount - $totalPooled;
                                                @endphp
                                                <tr class="fund-row" data-section="{{ $source->section_id }}" data-fy="{{ $source->fiscal_year }}" data-category="{{ $source->funding_source }}">
                                                    <td class="pl-5">
                                                        <div class="d-flex flex-column">
                                                            <span class="text-muted mb-1" style="font-size: 10.5px; font-weight: 600; text-transform: uppercase;">{{ $source->budgetLineItem->budget_line_item_name ?? 'N/A' }}</span>
                                                            <span class="text-dark font-weight-bold" style="font-size: 0.92rem;">{{ $source->name }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        @if($source->funding_source === 'Conap')
                                                            <span class="badge badge-warning text-dark px-2 py-1" style="font-size: 11px;">Conap</span>
                                                        @elseif($source->funding_source === 'NEP-Current')
                                                            <span class="badge badge-success px-2 py-1" style="font-size: 11px;">NEP-Current</span>
                                                        @else
                                                            <span class="badge badge-info px-2 py-1" style="font-size: 11px;">Current</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        @if($source->spreadsheet_id)
                                                            <a href="https://docs.google.com/spreadsheets/d/{{ $source->spreadsheet_id }}" target="_blank" class="btn btn-xs btn-outline-success rounded-pill px-2 font-weight-bold" style="font-size: 11px;">
                                                                <i class="fas fa-file-excel mr-1"></i> Linked
                                                            </a>
                                                        @else
                                                            <span class="badge badge-light text-muted border py-1 px-2 rounded-pill" style="font-size: 10px;"><i class="fas fa-keyboard mr-1"></i> Manual</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-right font-weight-bold text-secondary text-nowrap">₱{{ number_format($source->total_amount, 2) }}</td>
                                                    <td class="text-center text-nowrap">
                                                        @if($totalPooled > 0)
                                                            <span class="text-danger font-weight-bold" style="font-size: 0.85rem;"><i class="fas fa-arrow-down mr-1 small"></i>₱{{ number_format($totalPooled, 2) }}</span>
                                                        @else
                                                            <span class="text-muted small">&mdash;</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-right text-nowrap">
                                                        <span class="text-success font-weight-bold" style="font-size: 0.98rem;">₱{{ number_format($netAmount, 2) }}</span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge badge-dark py-1 px-2">{{ $source->fiscal_year }}</span>
                                                    </td> 
                                                    <td class="text-center pr-4">
                                                        <div class="btn-group shadow-sm">
                                                            <button type="button" class="btn btn-xs btn-white border btn-edit-source px-2 py-1" 
                                                                data-id="{{ $source->id }}" data-funding_source="{{ $source->funding_source }}" data-source_type="{{ $source->source_type }}" data-name="{{ $source->name }}" data-budget_line_item_id="{{ $source->budget_line_item_id }}" data-fiscal_year="{{ $source->fiscal_year }}" data-allotment_class="{{ $source->allotment_class }}" data-total_amount="{{ $source->total_amount }}" data-section_id="{{ $source->section_id }}" data-spreadsheet_id="{{ $source->spreadsheet_id }}" data-sheet_name="{{ $source->sheet_name }}" data-saa_date="{{ $source->saa_date }}" data-reference_number="{{ $source->reference_number }}" data-fund_code="{{ $source->fund_code }}" data-approp_code="{{ $source->approp_code }}" data-toggle="tooltip" title="Edit">
                                                                <i class="fas fa-edit text-info"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-xs btn-white border delete-source-btn px-2 py-1" data-id="{{ $source->id }}" data-name="{{ $source->name }}" data-count="{{ $source->activities->count() }}" data-toggle="tooltip" title="Delete">
                                                                <i class="fas fa-trash-alt text-danger"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    @empty
                                        <tr>
                                            <td colspan="8" class="p-4">
                                                <div class="fs-empty-state border-0 bg-transparent py-3">
                                                    <div class="fs-empty-icon bg-success text-white">
                                                        <i class="fas fa-check-circle fa-lg"></i>
                                                    </div>
                                                    <h6 class="font-weight-bold text-dark mb-1">No Approved Fund Sources Found</h6>
                                                    <p class="text-muted small mb-0">Click <strong>"Add Approved Fund"</strong> above or convert an existing NEP fund source.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                    <tr id="no_approved_results" style="display: none;">
                                        <td colspan="8" class="text-center py-4 text-muted font-italic">No records match the selected filters.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

{{-- MODAL 1: ADD NEW NEP SOURCE --}}
<div class="modal fade" id="modal-add-nep">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('settings.fund_sources.store') }}" method="POST">
                @csrf
                <input type="hidden" name="funding_source" value="NEP">
                <input type="hidden" name="source_type" value="GAA">
                
                <div class="modal-header bg-primary text-white">
                    <h4 class="modal-title"><i class="fas fa-plus-circle mr-1"></i> Add New NEP Fund Source</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Fund Source Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control nep-name-input" placeholder="e.g., Indicative WFP 2027" required>
                                <small class="text-muted">NEP prefix will be appended automatically if missing.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">P/P/A Title / Budget Line Item <span class="text-danger">*</span></label>
                                <select name="budget_line_item_id" class="form-control" required>
                                    <option value="">-- Select Line Item --</option>
                                    @foreach($budgetLineItems as $item)
                                        <option value="{{ $item->id }}">{{ $item->budget_line_item_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Fiscal Year</label>
                                <select name="fiscal_year" class="form-control" required>
                                    @php $currentYear = date('Y'); @endphp
                                    @for($i = $currentYear - 1; $i <= $currentYear + 3; $i++)
                                        <option value="{{ $i }}" {{ $i == $currentYear ? 'selected' : '' }}>{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Allotment Class <span class="text-danger">*</span></label>
                                <select name="allotment_class" class="form-control" required>
                                    <option value="">-- Select Class --</option>
                                    @foreach($allotmentClasses as $class)
                                        <option value="{{ $class->allotment_class }}">{{ $class->allotment_class }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label class="font-weight-bold">Allocated Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                                <input type="text" class="form-control amount-mask-display" placeholder="0.00" required>
                                <input type="hidden" name="total_amount" class="amount-mask-raw">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Responsible Section <span class="text-danger">*</span></label>
                                <select name="section_id" class="form-control border-primary" required>
                                    <option value="">-- Select Section --</option>
                                    @foreach($sections as $section)
                                        <option value="{{ $section->id }}">{{ $section->section_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- GOOGLE SHEET INTEGRATION SECTION --}}
                    <div class="bg-light p-3 border rounded mt-3">
                        <h6 class="font-weight-bold text-muted small text-uppercase mb-3"><i class="fab fa-google-drive mr-1"></i> Optional: Google Sheet Integration</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label class="small">Spreadsheet ID</label>
                                    <input type="text" name="spreadsheet_id" class="form-control form-control-sm" placeholder="Paste ID from URL">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="small">Sheet/Tab Name</label>
                                    <input type="text" name="sheet_name" class="form-control form-control-sm" placeholder="e.g., Sheet1">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary shadow-sm"><i class="fas fa-save mr-1"></i> Save NEP Source</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 2: ADD NEW APPROVED FUND SOURCE (Current & Conap) --}}
<div class="modal fade" id="modal-add-approved">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('settings.fund_sources.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-success text-white">
                    <h4 class="modal-title"><i class="fas fa-plus-circle mr-1"></i> Add Approved Fund Source</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Funding Category <span class="text-danger">*</span></label>
                                <select name="funding_source" class="form-control border-success" required>
                                    <option value="">-- Select Category --</option>
                                    <option value="Current">Current</option>
                                    <option value="Conap">Conap</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Source Type <span class="text-danger">*</span></label>
                                <select name="source_type" class="form-control border-success" required>
                                    <option value="GAA">GAA (General Appropriations Act)</option>
                                    <option value="SAA">SAA (Sub-Allotment Advice)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Fund Source Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g., Medical Assistance Program" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">P/P/A Title / Budget Line Item <span class="text-danger">*</span></label>
                                <select name="budget_line_item_id" class="form-control" required>
                                    <option value="">-- Select Line Item --</option>
                                    @foreach($budgetLineItems as $item)
                                        <option value="{{ $item->id }}">{{ $item->budget_line_item_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Fiscal Year</label>
                                <select name="fiscal_year" class="form-control" required>
                                    @php $currentYear = date('Y'); @endphp
                                    @for($i = $currentYear - 1; $i <= $currentYear + 3; $i++)
                                        <option value="{{ $i }}" {{ $i == $currentYear ? 'selected' : '' }}>{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Allotment Class <span class="text-danger">*</span></label>
                                <select name="allotment_class" class="form-control" required>
                                    <option value="">-- Select Class --</option>
                                    @foreach($allotmentClasses as $class)
                                        <option value="{{ $class->allotment_class }}">{{ $class->allotment_class }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label class="font-weight-bold">Allocated Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                                <input type="text" class="form-control amount-mask-display" placeholder="0.00" required>
                                <input type="hidden" name="total_amount" class="amount-mask-raw">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Responsible Section <span class="text-danger">*</span></label>
                                <select name="section_id" class="form-control border-success" required>
                                    <option value="">-- Select Section --</option>
                                    @foreach($sections as $section)
                                        <option value="{{ $section->id }}">{{ $section->section_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- GOOGLE SHEET INTEGRATION SECTION --}}
                    <div class="bg-light p-3 border rounded mt-3">
                        <h6 class="font-weight-bold text-muted small text-uppercase mb-3"><i class="fab fa-google-drive mr-1"></i> Optional: Google Sheet Integration</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label class="small">Spreadsheet ID</label>
                                    <input type="text" name="spreadsheet_id" class="form-control form-control-sm" placeholder="Paste ID from URL">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="small">Sheet/Tab Name</label>
                                    <input type="text" name="sheet_name" class="form-control form-control-sm" placeholder="e.g., Sheet1">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success shadow-sm"><i class="fas fa-save mr-1"></i> Save Approved Fund</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL: CONVERT NEP TO APPROVED FUND --}}
<div class="modal fade" id="modal-convert-source">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="convert-source-form" method="POST">
                @csrf 
                <div class="modal-header bg-success text-white">
                    <h4 class="modal-title"><i class="fas fa-exchange-alt mr-2"></i>Approve &amp; Convert NEP Fund</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small">
                        <i class="fas fa-info-circle mr-1"></i> Converting this record will shift it into the <strong>Approved Funds</strong> list as <strong>NEP-Current</strong> while retaining its full historical footprint inside the NEP registry.
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Target Funding Category <span class="text-danger">*</span></label>
                                <select id="convert_funding_source_display" class="form-control border-success" disabled>
                                    <option value="NEP-Current" selected>NEP-Current (Converted from NEP)</option>
                                </select>
                                <input type="hidden" name="funding_source" id="convert_funding_source" value="NEP-Current">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Source Type <span class="text-danger">*</span></label>
                                <select id="convert_source_type_display" class="form-control border-success" disabled>
                                    <option value="GAA" selected>GAA (General Appropriations Act)</option>
                                    <option value="SAA">SAA (Sub-Allotment Advice)</option>
                                </select>
                                <input type="hidden" name="source_type" id="convert_source_type" value="GAA">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Fund Source Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="convert_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">P/P/A Title / Budget Line Item <span class="text-danger">*</span></label>
                                <select name="budget_line_item_id" id="convert_budget_line_item_id" class="form-control" required>
                                    <option value="">-- Select Line Item --</option>
                                    @foreach($budgetLineItems as $item)
                                        <option value="{{ $item->id }}">{{ $item->budget_line_item_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Fiscal Year</label>
                                <select name="fiscal_year" id="convert_fiscal_year" class="form-control" required>
                                    @php $currentYear = date('Y'); @endphp
                                    @for($i = $currentYear - 1; $i <= $currentYear + 3; $i++)
                                        <option value="{{ $i }}">{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Allotment Class <span class="text-danger">*</span></label>
                                <select name="allotment_class" id="convert_allotment_class" class="form-control" required>
                                    <option value="">-- Select Class --</option>
                                    @foreach($allotmentClasses as $class)
                                        <option value="{{ $class->allotment_class }}">{{ $class->allotment_class }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label class="font-weight-bold">Allocated Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text bg-light">₱</span></div>
                                <input type="text" class="form-control amount-mask-display" id="convert_display_amount" required>
                                <input type="hidden" name="total_amount" id="convert_raw_amount" class="amount-mask-raw">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Responsible Section <span class="text-danger">*</span></label>
                                <select name="section_id" id="convert_section_id" class="form-control border-success" required>
                                    <option value="">-- Select Section --</option>
                                    @foreach($sections as $section)
                                        <option value="{{ $section->id }}">{{ $section->section_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="spreadsheet_id" id="convert_spreadsheet_id">
                    <input type="hidden" name="sheet_name" id="convert_sheet_name">
                </div>
                <div class="modal-footer justify-content-between bg-light">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success shadow-sm"><i class="fas fa-check-circle mr-1"></i> Confirm &amp; Approve Fund</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- EDIT MODAL --}}
<div class="modal fade" id="modal-edit-source">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="edit-source-form" method="POST">
                @csrf @method('PUT')
                <div class="modal-header bg-info text-white">
                    <h4 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Fund Source</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Funding Category <span class="text-danger">*</span></label>
                                <select name="funding_source" id="edit_funding_source" class="form-control border-info" required>
                                    <option value="NEP">NEP (Indicative)</option>
                                    <option value="NEP-Current">NEP-Current (Converted)</option>
                                    <option value="Current">Current</option>
                                    <option value="Conap">Conap (Continuing Appropriation)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Source Type <span class="text-danger">*</span></label>
                                <select name="source_type" id="edit_source_type" class="form-control border-info" required>
                                    <option value="GAA">GAA (General Appropriations Act)</option>
                                    <option value="SAA">SAA (Sub-Allotment Advice)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Source Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="edit_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">P/P/A Title / Budget Line Item <span class="text-danger">*</span></label>
                                <select name="budget_line_item_id" id="edit_budget_line_item_id" class="form-control" required>
                                    <option value="">-- Select Line Item --</option>
                                    @foreach($budgetLineItems as $item)
                                        <option value="{{ $item->id }}">{{ $item->budget_line_item_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Fiscal Year</label>
                                <select name="fiscal_year" id="edit_fiscal_year" class="form-control" required>
                                    @php $currentYear = date('Y'); @endphp
                                    @for($i = $currentYear - 2; $i <= $currentYear + 3; $i++)
                                        <option value="{{ $i }}">{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Allotment Class <span class="text-danger">*</span></label>
                                <select name="allotment_class" id="edit_allotment_class" class="form-control" required>
                                    <option value="">-- Select Class --</option>
                                    @foreach($allotmentClasses as $class)
                                        <option value="{{ $class->allotment_class }}">{{ $class->allotment_class }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label class="font-weight-bold">Allocated Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text bg-light">₱</span></div>
                                <input type="text" class="form-control amount-mask-display" id="edit_display_amount" required>
                                <input type="hidden" name="total_amount" id="edit_raw_amount" class="amount-mask-raw">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Responsible Section <span class="text-danger">*</span></label>
                                <select name="section_id" id="edit_section_id" class="form-control border-info" required>
                                    <option value="">-- Select Section --</option>
                                    @foreach($sections as $section)
                                        <option value="{{ $section->id }}">{{ $section->section_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- SAA Conditional Fields for Edit Modal --}}
                    <div id="edit_saa_fields_container" class="border p-3 rounded bg-light mb-3" style="display: none;">
                        <h6 class="font-weight-bold text-info mb-2"><i class="fas fa-file-alt mr-1"></i> SAA Specific Details</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="small font-weight-bold">SAA Date</label>
                                    <input type="date" name="saa_date" id="edit_saa_date" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Reference Number</label>
                                    <input type="text" name="reference_number" id="edit_reference_number" class="form-control form-control-sm">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="small font-weight-bold">Fund Code</label>
                                    <input type="text" name="fund_code" id="edit_fund_code" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="small font-weight-bold">Appropriation Code</label>
                                    <input type="text" name="approp_code" id="edit_approp_code" class="form-control form-control-sm">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- GOOGLE SHEET INTEGRATION SECTION --}}
                    <div class="bg-light p-3 border rounded mt-3">
                        <h6 class="font-weight-bold text-muted small text-uppercase mb-3"><i class="fab fa-google-drive mr-1"></i> Optional: Google Sheet Integration</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label class="small">Spreadsheet ID</label>
                                    <input type="text" name="spreadsheet_id" id="edit_spreadsheet_id" class="form-control form-control-sm" placeholder="Paste ID from URL">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="small">Sheet/Tab Name</label>
                                    <input type="text" name="sheet_name" id="edit_sheet_name" class="form-control form-control-sm" placeholder="e.g., Sheet1">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-light">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info shadow-sm"><i class="fas fa-save mr-1"></i> Update Source</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- DELETE MODAL --}}
<div class="modal fade" id="deleteSourceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-exclamation-triangle mr-2"></i>Confirm Deletion</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="deleteSourceForm" method="POST">
                @csrf @method('DELETE')
                <div class="modal-body text-center py-4">
                    <div class="mb-3"><i class="fas fa-trash-alt text-danger fa-4x"></i></div>
                    <h5 class="text-dark">Are you sure?</h5>
                    <p class="text-muted">You are about to delete <span id="sourceNameDisplay" class="font-weight-bold text-danger"></span>.</p>
                    <div class="badge badge-light border p-2 w-100" style="font-size: 0.9rem;">
                        <i class="fas fa-link mr-1"></i> Linked Activities: <span id="activityCountDisplay" class="text-primary font-weight-bold">0</span>
                    </div>
                </div>
                <div class="modal-footer bg-light justify-content-between">
                    <button type="button" class="btn btn-secondary px-4 shadow-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4 shadow-sm"><i class="fas fa-check mr-1"></i> Yes, Delete it</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Flash Alerts Auto-dismiss
        const $flashAlerts = $(".alert-success, .alert-danger");
        if ($flashAlerts.length > 0) {
            window.setTimeout(function() {
                $flashAlerts.fadeTo(500, 0).slideUp(500, function(){ $(this).remove(); });
            }, 5000);
        }

        // Remember active tab via localStorage
        $('a[data-toggle="pill"]').on('shown.bs.tab', function (e) {
            localStorage.setItem('activeFundTab', $(e.target).attr('href'));
        });
        var activeFundTab = localStorage.getItem('activeFundTab');
        if(activeFundTab){
            $('.nav-tabs a[href="' + activeFundTab + '"]').tab('show');
        }

        // Currency Masking Helpers
        function formatNumber(n) {
            return n.replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        $(document).on('input', '.amount-mask-display', function() {
            let displayInput = $(this);
            let rawInput = displayInput.siblings('.amount-mask-raw');
            let numericVal = displayInput.val().replace(/[^0-9.]/g, ''); 
            
            if (numericVal.indexOf(".") >= 0) {
                let decimalPos = numericVal.indexOf(".");
                let leftSide = formatNumber(numericVal.substring(0, decimalPos));
                let rightSide = numericVal.substring(decimalPos, decimalPos + 3);
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
        });

        // NEP Auto Prefixing logic for Add NEP Modal
        $('.nep-name-input').on('blur', function() {
            let val = $(this).val().trim();
            if (val && !val.toUpperCase().startsWith('NEP ')) {
                $(this).val('NEP ' + val.replace(/^(NEP|CONAP|CURRENT|NEP-CURRENT)\s*/i, ''));
            }
        });

        // Toggle SAA fields based on source_type in Edit Modal
        $('#edit_source_type').on('change', function() {
            if ($(this).val() === 'SAA') {
                $('#edit_saa_fields_container').slideDown(200);
            } else {
                $('#edit_saa_fields_container').slideUp(200);
            }
        });

        // -----------------------------------------------------------------
        // FILTER LOGIC SUPPORTING FISCAL YEAR & SECTION GROUPING
        // -----------------------------------------------------------------
        function applyFilters(tableId, sectionId, fyId, noResultRowId, categoryId = null) {
            let selectedSection = $(sectionId).val();
            let selectedFy = $(fyId).val();
            let selectedCategory = categoryId ? $(categoryId).val() : "";
            let visibleRowsCount = 0;

            let fyVisibility = {};
            let sectionVisibility = {};

            $(`${tableId} tbody tr.fund-row`).each(function() {
                let row = $(this);
                let rowSection = row.data('section').toString();
                let rowFy = row.data('fy').toString();
                let rowCategory = categoryId ? (row.data('category') ? row.data('category').toString() : "") : "";

                let matchSection = (selectedSection === "" || rowSection === selectedSection);
                let matchFy = (selectedFy === "" || rowFy === selectedFy);
                let matchCategory = (!categoryId || selectedCategory === "" || rowCategory === selectedCategory);

                if (matchSection && matchFy && matchCategory) {
                    row.show();
                    visibleRowsCount++;
                    fyVisibility[rowFy] = true;
                    sectionVisibility[rowSection] = true;
                } else {
                    row.hide();
                }
            });

            // Show or hide Section headers based on child visibility
            $(`${tableId} tbody tr.section-header-row`).each(function() {
                let headerRow = $(this);
                let headerSectionId = headerRow.data('section-header').toString();
                let headerFy = headerRow.data('fy').toString();

                let matchSectionFilter = (selectedSection === "" || headerSectionId === selectedSection);
                let matchFyFilter = (selectedFy === "" || headerFy === selectedFy);

                if (matchSectionFilter && matchFyFilter && sectionVisibility[headerSectionId]) {
                    headerRow.show();
                } else {
                    headerRow.hide();
                }
            });

            // Show or hide Fiscal Year headers based on whether they contain visible elements
            $(`${tableId} tbody tr.fy-header-row`).each(function() {
                let fyHeader = $(this);
                let fyVal = fyHeader.data('fy-header').toString();

                if (selectedFy === "" || selectedFy === fyVal) {
                    if (fyVisibility[fyVal]) {
                        fyHeader.show();
                    } else {
                        fyHeader.hide();
                    }
                } else {
                    fyHeader.hide();
                }
            });

            if (visibleRowsCount === 0) {
                $(noResultRowId).show();
            } else {
                $(noResultRowId).hide();
            }
        }

        // NEP Tab Filters trigger
        $('#filter_nep_section, #filter_nep_fy').on('change', function() {
            applyFilters('#nepTable', '#filter_nep_section', '#filter_nep_fy', '#no_nep_results');
        });

        $('#reset_nep_filters').on('click', function() {
            $('#filter_nep_section').val('');
            $('#filter_nep_fy').val('');
            applyFilters('#nepTable', '#filter_nep_section', '#filter_nep_fy', '#no_nep_results');
        });

        // Approved Tab Filters trigger
        $('#filter_approved_category, #filter_approved_section, #filter_approved_fy').on('change', function() {
            applyFilters('#approvedTable', '#filter_approved_section', '#filter_approved_fy', '#no_approved_results', '#filter_approved_category');
        });

        $('#reset_approved_filters').on('click', function() {
            $('#filter_approved_category').val('');
            $('#filter_approved_section').val('');
            $('#filter_approved_fy').val('');
            applyFilters('#approvedTable', '#filter_approved_section', '#filter_approved_fy', '#no_approved_results', '#filter_approved_category');
        });

        // Edit Button Data Populating
        $('.btn-edit-source').on('click', function() {
            const data = $(this).data();
            $('#edit-source-form').attr('action', data.url || `{{ url('/settings/fund_sources') }}/${data.id}`);
            
            $('#edit_funding_source').val(data.funding_source);
            $('#edit_source_type').val(data.source_type).trigger('change');
            
            $('#edit_name').val(data.name);
            $('#edit_budget_line_item_id').val(data.budget_line_item_id);
            $('#edit_fiscal_year').val(data.fiscal_year);
            $('#edit_allotment_class').val(data.allotment_class);
            $('#edit_section_id').val(data.section_id);
            
            const amount = parseFloat(data.total_amount) || 0;
            $('#edit_raw_amount').val(amount.toFixed(2));
            $('#edit_display_amount').val(amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            
            $('#edit_spreadsheet_id').val(data.spreadsheet_id);
            $('#edit_sheet_name').val(data.sheet_name);

            let rawDate = data.saa_date || data.saadate || data.saaDate || '';
            if (rawDate) {
                rawDate = rawDate.toString().split(' ')[0];
            }
            $('#edit_saa_date').val(rawDate);

            $('#edit_reference_number').val(data.reference_number || '');
            $('#edit_fund_code').val(data.fund_code || '');
            $('#edit_approp_code').val(data.approp_code || '');

            if (data.source_type === 'SAA') {
                $('#edit_saa_fields_container').show();
            } else {
                $('#edit_saa_fields_container').hide();
            }

            $('#modal-edit-source').modal('show');
        });

        // Delete confirmation handler
        $('.delete-source-btn').on('click', function() {
            const btn = $(this);
            $('#sourceNameDisplay').text(btn.data('name'));
            $('#activityCountDisplay').text(btn.data('count') || 0);
            $('#deleteSourceForm').attr('action', `/settings/fund_sources/${btn.data('id')}`);
            $('#deleteSourceModal').modal('show');
        });

        // Open Convert Modal and populate fields from NEP row
        $('.btn-convert-source').on('click', function() {
            const data = $(this).data();
            $('#convert-source-form').attr('action', `/settings/fund_sources/${data.id}/convert`);
            
            let cleanName = (data.name || '').replace(/^(NEP|NEP-CURRENT)\s+/i, '');
            $('#convert_name').val(cleanName); 
            
            let sType = data.source_type || 'GAA';
            $('#convert_source_type_display').val(sType);
            $('#convert_source_type').val(sType);

            $('#convert_budget_line_item_id').val(data.budget_line_item_id);
            $('#convert_fiscal_year').val(data.fiscal_year);
            $('#convert_allotment_class').val(data.allotment_class);
            $('#convert_section_id').val(data.section_id);
            $('#convert_spreadsheet_id').val(data.spreadsheet_id);
            $('#convert_sheet_name').val(data.sheet_name);
            
            const amount = parseFloat(data.total_amount) || 0;
            $('#convert_raw_amount').val(amount.toFixed(2));
            $('#convert_display_amount').val(amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

            $('#modal-convert-source').modal('show');
        });

        // COLLAPSIBLE FISCAL YEAR GROUPS
        $(document).on('click', '.fy-header-row', function() {
            let fyHeader = $(this);
            let targetFy = fyHeader.data('fy-header').toString();
            let table = fyHeader.closest('table');
            
            let relatedSections = table.find(`tr.section-header-row[data-fy="${targetFy}"]`);
            let relatedFunds = table.find(`tr.fund-row[data-fy="${targetFy}"]`);
            let icon = fyHeader.find('.fy-toggle-icon');

            if (relatedFunds.is(':visible')) {
                relatedFunds.slideUp(200);
                relatedSections.slideUp(200);
                icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
            } else {
                relatedSections.slideDown(200);
                relatedFunds.slideDown(200);
                icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
            }
        });

        $('[data-toggle="tooltip"]').tooltip({ html: true });
    });
</script>