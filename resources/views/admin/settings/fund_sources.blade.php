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
                <table class="table table-hover mb-0" id="fundSourceTable" style="border-collapse: separate; border-spacing: 0;">
                    <thead class="bg-light">
                        <tr>
                            <th class="border-0 py-3 pl-4 text-uppercase small font-weight-bold" style="width: 30%;">Budget Line / Fund Name</th>
                            <th class="border-0 py-3 text-uppercase small font-weight-bold text-center">Sync Status</th>
                            <th class="border-0 py-3 text-uppercase small font-weight-bold text-right">Original Allotment</th>
                            <th class="border-0 py-3 text-uppercase small font-weight-bold text-center">Pooled Funds</th>
                            <th class="border-0 py-3 text-uppercase small font-weight-bold text-center">Net Allotment</th>
                            <th class="border-0 py-3 text-uppercase small font-weight-bold text-center">FY</th> 
                            <th class="border-0 py-3 pr-4 text-uppercase small font-weight-bold text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sources->groupBy('section_id') as $sectionId => $groupedSources)
                            @php
                                $section = $sections->firstWhere('id', $sectionId);
                                $sectionName = $section->section_name ?? 'Unassigned / General';
                            @endphp

                            {{-- Professional Section Header --}}
                            <tr class="section-header-row" style="background-color: #f8f9fa;">
                                <td colspan="7" class="py-2 pl-4 border-bottom border-top">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-folder-open text-primary mr-2"></i>
                                        <span class="text-dark font-weight-bold text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">
                                            {{ $sectionName }}
                                        </span>
                                        <span class="badge badge-pill badge-secondary ml-2" style="font-size: 0.7rem; opacity: 0.8;">
                                            {{ $groupedSources->count() }}
                                        </span>
                                    </div>
                                </td>
                            </tr>

                            @foreach($groupedSources as $source)
                                @php
                                    $totalPooled = $source->activities->sum('pooled_amount');
                                    $netAmount = $source->total_amount - $totalPooled;
                                @endphp
                                <tr>
                                    {{-- Fund Name Column --}}
                                    <td class="align-middle pl-5 border-top-0"> {{-- Indented for hierarchy --}}
                                        <div class="d-flex flex-column">
                                            <span class="text-muted mb-1" style="font-size: 10px; font-weight: 600; text-transform: uppercase;">
                                                {{ $source->budgetLineItem->budget_line_item_name ?? 'N/A' }}
                                            </span>
                                            <span class="text-dark font-weight-bold" style="font-size: 0.95rem;">
                                                {{ $source->name }}
                                            </span>
                                        </div>
                                    </td>

                                    {{-- Dedicated Sync Status Column --}}
                                    <td class="align-middle text-center border-top-0">
                                        @if($source->spreadsheet_id)
                                            <a href="https://docs.google.com/spreadsheets/d/{{ $source->spreadsheet_id }}" target="_blank" 
                                            class="btn btn-xs btn-outline-success rounded-pill px-2" 
                                            style="font-size: 11px; font-weight: 600;"
                                            data-toggle="tooltip" title="View Google Sheet">
                                                <i class="fas fa-file-excel mr-1"></i> Linked
                                            </a>
                                        @else
                                            <span class="badge badge-light text-muted border py-1 px-2 rounded-pill" style="font-size: 10px;">
                                                <i class="fas fa-keyboard mr-1"></i> Manual
                                            </span>
                                        @endif
                                    </td>

                                    <td class="align-middle text-right font-weight-500 border-top-0">
                                        ₱{{ number_format($source->total_amount, 2) }}
                                    </td>

                                    <td class="align-middle text-center border-top-0">
                                        @if($totalPooled > 0)
                                            <span class="text-danger font-weight-500" style="font-size: 0.9rem;">
                                                <i class="fas fa-arrow-down mr-1 small"></i>₱{{ number_format($totalPooled, 2) }}
                                            </span>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>

                                    <td class="align-middle text-center border-top-0">
                                        <span class="text-primary font-weight-bold" style="font-size: 1.05rem;">
                                            ₱{{ number_format($netAmount, 2) }}
                                        </span>
                                    </td>

                                    <td class="align-middle text-center border-top-0">
                                        <span class="badge badge-dark py-1 px-2" style="font-weight: 400;">{{ $source->fiscal_year }}</span>
                                    </td> 

                                    <td class="align-middle text-center border-top-0 pr-4">
                                        <div class="btn-group shadow-sm">
                                            <button type="button" class="btn btn-sm btn-white border btn-edit-source" 
                                                data-id="{{ $source->id }}" 
                                                data-source_type="{{ $source->source_type }}"
                                                data-name="{{ $source->name }}" 
                                                data-budget_line_item_id="{{ $source->budget_line_item_id }}"
                                                data-fiscal_year="{{ $source->fiscal_year }}"
                                                data-allotment_class="{{ $source->allotment_class }}"
                                                data-total_amount="{{ $source->total_amount }}" 
                                                data-section_id="{{ $source->section_id }}"
                                                data-saa_date="{{ $source->saa_date ? \Carbon\Carbon::parse($source->saa_date)->format('Y-m-d') : '' }}"
                                                data-reference_number="{{ $source->reference_number }}"
                                                data-fund_code="{{ $source->fund_code }}"
                                                data-approp_code="{{ $source->approp_code }}"
                                                data-spreadsheet_id="{{ $source->spreadsheet_id }}" 
                                                data-sheet_name="{{ $source->sheet_name }}"
                                                data-toggle="tooltip" title="Edit">
                                                <i class="fas fa-edit text-info"></i>
                                            </button>

                                            <button type="button" class="btn btn-sm btn-white border delete-source-btn" 
                                                data-id="{{ $source->id }}" 
                                                data-name="{{ $source->name }}"
                                                data-count="{{ $source->activities->count() }}"
                                                data-toggle="tooltip" title="Delete">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Edit Fund source --}}
<div class="modal fade" id="modal-edit-source">
    <div class="modal-dialog modal-lg"> {{-- Large modal for better field spacing --}}
        <div class="modal-content">
            <form id="edit-source-form" method="POST">
                @csrf @method('PUT')
                <div class="modal-header bg-info">
                    <h4 class="modal-title text-white"><i class="fas fa-edit mr-2"></i>Edit Fund Source</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    {{-- 1. Source Type Selection --}}
                    <div class="form-group">
                        <label class="font-weight-bold">Source Type <span class="text-danger">*</span></label>
                        <select name="source_type" id="edit_source_type" class="form-control border-info" required>
                            <option value="GAA">GAA (General Appropriations Act)</option>
                            <option value="SAA">SAA (Sub-Allotment Advice)</option>
                        </select>
                    </div>

                    <hr>

                    {{-- 2. SAA SPECIFIC FIELDS (Hidden by default, shown via JS) --}}
                    <div id="edit-saa-fields" style="display: none;">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Office / Entity</label>
                                    <input type="text" name="entity_name" class="form-control form-control-sm" value="CHD8 - Eastern Visayas Centers for Health Development" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Date <span class="text-danger">*</span></label>
                                    <input type="date" name="saa_date" id="edit_saa_date" class="form-control form-control-sm edit-saa-required">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Reference Number <span class="text-danger">*</span></label>
                                    <input type="text" name="reference_number" id="edit_reference_number" class="form-control form-control-sm edit-saa-required" placeholder="SAA-2024-XXX">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Fund Code <span class="text-danger">*</span></label>
                                    <input type="text" name="fund_code" id="edit_fund_code" class="form-control form-control-sm edit-saa-required">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Appropriations Code <span class="text-danger">*</span></label>
                                    <input type="text" name="approp_code" id="edit_approp_code" class="form-control form-control-sm edit-saa-required">
                                </div>
                            </div>
                        </div>
                    </div>  

                    {{-- 3. SHARED FIELDS --}}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Source Name</label>
                                <input type="text" name="name" id="edit_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">P/P/A Title / Budget Line Item</label>
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
                                <label class="font-weight-bold">Allotment Class</label>
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
                            <label class="font-weight-bold">Allocated Amount</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light">₱</span>
                                </div>
                                <input type="text" class="form-control amount-mask-display" id="edit_display_amount" required>
                                <input type="hidden" name="total_amount" id="edit_raw_amount" class="amount-mask-raw">
                            </div>
                        </div>

                        {{-- Responsible Section --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Responsible Section <span class="text-danger">*</span></label>
                                {{-- Added id="edit_section_id" for JS population --}}
                                <select name="section_id" id="edit_section_id" class="form-control border-info" required>
                                    <option value="">-- Select Section --</option>
                                    @foreach($sections as $section)
                                        <option value="{{ $section->id }}">{{ $section->section_name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Unit managing this fund.</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-light p-3 border rounded shadow-sm">
                        <h6 class="font-weight-bold text-info border-bottom pb-2">
                            <i class="fab fa-google-drive mr-1"></i> Google Sheet Config
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label class="small font-weight-bold">Spreadsheet ID</label>
                                    <input type="text" name="spreadsheet_id" id="edit_spreadsheet_id" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="small font-weight-bold">Sheet/Tab Name</label>
                                    <input type="text" name="sheet_name" id="edit_sheet_name" class="form-control form-control-sm">
                                </div>
                            </div>
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
    <div class="modal-dialog modal-lg"> {{-- Increased to large for better row layout --}}
        <div class="modal-content">
            <form action="{{ route('settings.fund_sources.store') }}" method="POST" id="fundSourceForm">
                @csrf
                <div class="modal-header bg-primary">
                    <h4 class="modal-title text-white">Add New Fund Source</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    {{-- 1. Source Type Selection --}}
                    <div class="form-group">
                        <label class="font-weight-bold">Source Type <span class="text-danger">*</span></label>
                        <select name="source_type" id="source_type" class="form-control border-primary" required>
                            <option value="">-- Select Type --</option>
                            <option value="GAA">GAA (General Appropriations Act)</option>
                            <option value="SAA">SAA (Sub-Allotment Advice)</option>
                        </select>
                    </div>

                    <hr>

                    {{-- 2. Common & Conditional Fields Wrapper --}}
                    <div id="dynamic-fields" style="display: none;">
                        
                        {{-- SAA SPECIFIC FIELDS: CHD/Hospital/Bureau, Date, Ref, Fund Code, Approp Code --}}
                        <div class="saa-only row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Office / Entity</label>
                                    <input type="text" name="entity_name" class="form-control" value="CHD8 - Eastern Visayas Centers for Health Development" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Date <span class="text-danger">*</span></label>
                                    <input type="date" name="saa_date" class="form-control saa-required">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Reference Number <span class="text-danger">*</span></label>
                                    <input type="text" name="reference_number" class="form-control saa-required" placeholder="e.g., SAA-2024-001">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Fund Code <span class="text-danger">*</span></label>
                                    <input type="text" name="fund_code" class="form-control saa-required">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Appropriations Code <span class="text-danger">*</span></label>
                                    <input type="text" name="approp_code" class="form-control saa-required">
                                </div>
                            </div>
                        </div>

                        {{-- SHARED FIELDS: Name and Budget Line Item (P/P/A) --}}
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Fund Source Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" placeholder="e.g., CONAP 2024" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>P/P/A Title / Budget Line Item <span class="text-danger">*</span></label>
                                    <select name="budget_line_item_id" class="form-control" required>
                                        <option value="">-- Select Line Item --</option>
                                        @foreach($budgetLineItems as $item)
                                            <option value="{{ $item->id }}">{{ $item->budget_line_item_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        {{-- SHARED FIELDS: Year, Allotment Class, and Responsible Section --}}
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Fiscal Year</label>
                                        <select name="fiscal_year" class="form-control" required>
                                            @php $currentYear = date('Y'); @endphp
                                            @for($i = $currentYear - 1; $i <= $currentYear + 2; $i++)
                                                <option value="{{ $i }}" {{ $i == $currentYear ? 'selected' : '' }}>{{ $i }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Allotment Class <span class="text-danger">*</span></label>
                                        <select name="allotment_class" class="form-control" required>
                                            <option value="">-- Select Class --</option>
                                            @foreach($allotmentClasses as $class)
                                                <option value="{{ $class->allotment_class }}">{{ $class->allotment_class }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                
                            </div>

                        {{-- SHARED FIELDS: Amount --}}
                        <div class="row">
                            <div class="col-md-6">
                                <label>Allocated Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                                    <input type="text" class="form-control amount-mask-display" placeholder="0.00" required>
                                    <input type="hidden" name="total_amount" class="amount-mask-raw">
                                </div>
                            </div>

                            {{-- Responsible Section --}}
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Responsible Section <span class="text-danger">*</span></label>
                                    <select name="section_id" class="form-control border-info" required>
                                        <option value="">-- Select Section --</option>
                                        @foreach($sections as $section)
                                            <option value="{{ $section->id }}">{{ $section->section_name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Unit managing this fund.</small>
                                </div>
                            </div>
                        </div>
                        

                        {{-- 3. Google Sheet Integration --}}
                        <div class="bg-light p-3 border rounded mt-3">
                            <h6 class="font-weight-bold text-muted small uppercase mb-3">
                                <i class="fab fa-google-drive mr-1"></i> Optional: Google Sheet Integration
                            </h6>
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
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="btnSave" style="display: none;">Save Fund Source</button>
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

        // 1. Auto-hide alerts after 5 seconds
        const $flashAlerts = $(".alert-success, .alert-danger");
        if ($flashAlerts.length > 0) {
            window.setTimeout(function() {
                $flashAlerts.fadeTo(500, 0).slideUp(500, function(){
                    $(this).remove(); 
                });
            }, 5000);
        }
        
        // 2. Preserve Tab on Refresh
        $('a[data-toggle="pill"]').on('shown.bs.tab', function (e) {
            localStorage.setItem('activeTab', $(e.target).attr('href'));
        });
        var activeTab = localStorage.getItem('activeTab');
        if(activeTab){
            $('#settingsCustomTab a[href="' + activeTab + '"]').tab('show');
        }

        // 3. Currency Masking Helpers
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
                let rightSide = numericVal.substring(decimalPos, decimalPos + 3); // max 2 decimals
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

        // 4. Budget Validation (Existing logic)
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

        // 5. ADD MODAL: Toggle Source Type
        $('#source_type').on('change', function() {
            const type = $(this).val();
            if (type === '') {
                $('#dynamic-fields, #btnSave').hide();
            } else {
                $('#dynamic-fields, #btnSave').show();
                if (type === 'GAA') {
                    $('.saa-only').hide();
                    $('.saa-required').prop('required', false);
                } else {
                    $('.saa-only').show();
                    $('.saa-required').prop('required', true);
                }
            }
        });

        // 6. EDIT MODAL: Toggle Source Type
        $('#edit_source_type').on('change', function() {
            if ($(this).val() === 'SAA') {
                $('#edit-saa-fields').slideDown();
                $('.edit-saa-required').prop('required', true);
            } else {
                $('#edit-saa-fields').slideUp();
                $('.edit-saa-required').prop('required', false);
            }
        });

        // 7. EDIT MODAL: Open and Populate
    $('.btn-edit-source').on('click', function() {
        const data = $(this).data();
        
        // Update Form Action
        $('#edit-source-form').attr('action', `/settings/fund_sources/${data.id}`);
        
        // Set Source Type and Trigger Toggle
        $('#edit_source_type').val(data.source_type).trigger('change');
        
        // Populate Common Fields
        $('#edit_name').val(data.name);
        $('#edit_budget_line_item_id').val(data.budget_line_item_id);
        $('#edit_fiscal_year').val(data.fiscal_year);
        $('#edit_allotment_class').val(data.allotment_class);

        // --- ADDED THIS LINE TO AUTO-FILL THE SECTION ---
        $('#edit_section_id').val(data.section_id);
        
        // Handle Amount Masking on load
        const amount = parseFloat(data.total_amount) || 0;
        $('#edit_raw_amount').val(amount.toFixed(2));
        $('#edit_display_amount').val(amount.toLocaleString(undefined, {
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2
        }));
        
        // SAA Specifics
        $('#edit_saa_date').val(data.saa_date);
        $('#edit_reference_number').val(data.reference_number);
        $('#edit_fund_code').val(data.fund_code);
        $('#edit_approp_code').val(data.approp_code);
        
        // Google Sheet Integration
        $('#edit_spreadsheet_id').val(data.spreadsheet_id);
        $('#edit_sheet_name').val(data.sheet_name);

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

    $(document).ready(function() {
        $('#source_type').on('change', function() {
            const type = $(this).val();
            
            if (type === '') {
                $('#dynamic-fields, #btnSave').hide();
            } else {
                $('#dynamic-fields, #btnSave').show();
                
                if (type === 'GAA') {
                    $('.saa-only').hide();
                    $('.saa-required').prop('required', false);
                } else {
                    $('.saa-only').show();
                    $('.saa-required').prop('required', true);
                }
            }
        });

        // Ensure raw amount is updated before submit (your existing mask logic)
        $('#fundSourceForm').on('submit', function() {
            // Any specific cleanup before submit
        });
    });
</script>