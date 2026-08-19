@extends('layouts.adminlte')

@section('content')

<style>
    /* Force the warning to be visible and occupy its own line */
    .budget-warning {
        display: block !important;
        height: 1.2rem;
        width: 100%;
        clear: both;
        visibility: visible !important;
    }

    /* Ensure the table cell expands to show the red text */
    #allocation-table td {
        vertical-align: top !important;
        padding-bottom: 10px;
    }
    .is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }

    /* Fix for the ARIA error - ensures the modal is accessible */
    .modal.show {
        aria-hidden: false !important;
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
    /* Premium Glass Badge */
    .glass-badge {
        background: rgba(255, 255, 255, 0.6);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        transition: all 0.3s ease;
    }

    .glass-badge:hover {
        background: rgba(255, 255, 255, 0.9);
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.07);
    }

    /* Typography Utility */
    .font-black { font-weight: 900 !important; }
    .tracking-tighter { letter-spacing: -0.05em !important; }
    .text-slate-200\/60 { border-color: rgba(226, 232, 240, 0.6) !important; }

    /* Section Text coloring */
    .text-primary { color: #004a99 !important; } /* DOH Blue */

    /* Professional Select Styling */
    .custom-select-pill {
        border-radius: 50px !important;
        border: 1px solid #d1d9e6 !important;
        padding-left: 1.25rem !important;
        background-color: #f8f9fa !important;
        font-weight: 500;
        color: #495057;
        transition: all 0.2s ease;
    }

    .custom-select-pill:focus {
        border-color: #007bff !important;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.15) !important;
        background-color: #fff !important;
    }

    /* Typography for filters */
    .tracking-wider {
        letter-spacing: 0.05em;
    }

    /* Subtle row highlighting when filtered */
    .fund-row {
        transition: opacity 0.3s ease;
    }

    /* 1. Reset the wrapper to handle horizontal alignment */
    .dataTables_wrapper .dataTables_length {
        float: left; /* Keep it on the left */
        margin-top: 15px !important; /* Adjust this value to move the whole unit up or down */
        margin-bottom: 10px !important;
    }

    /* 2. Fix the Label - This is the key for the horizontal look */
    .dataTables_wrapper .dataTables_length label {
        display: flex !important; /* Use flex to align items in a row */
        flex-direction: row !important;
        align-items: center !important;
        white-space: nowrap !important; /* Forces 'Show', select, and 'entries' to stay on 1 line */
        margin-bottom: 0 !important;
        font-weight: 700;
        color: #8898aa;
        font-size: 0.80rem;
        text-transform: uppercase;
    }

    /* 3. The Select Dropdown - Modern Pill Style */
    .dataTables_wrapper .dataTables_length select {
        width: auto !important;
        height: 32px !important;
        padding: 0 28px 0 12px !important; /* Extra right padding for the custom arrow */
        margin: 0 10px !important; /* Horizontal margin ONLY */
        border-radius: 12px !important;
        background-color: #f6f9fc !important;
        border: 1px solid #e9ecef !important;
        color: #32325d !important;
        cursor: pointer;
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238898aa' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        transition: all 0.2s ease;
    }

    /* 4. Ensure the text after the select ('entries') has no weird top margin */
    .dataTables_wrapper .dataTables_length label span {
        margin-top: 0 !important;
    }

</style>

<div class="row mb-3">
    <div class="col-12 text-right">
        <div class="d-flex align-items-center justify-content-between pt-4 pb-4 mb-2">
            <div class="d-flex align-items-center">
                {{-- Floating Icon Pod --}}
                <div class="bg-white shadow-sm border border-slate-200 rounded-lg d-flex align-items-center justify-content-center mr-3" style="width: 45px; height: 45px;">
                    <i class="fas fa-exchange-alt text-primary"></i>
                </div>
                
                <div class="d-flex align-items-baseline">
                    <h4 class="m-0 font-black text-slate-800 italic tracking-tighter" style="font-size: 1.8rem; line-height: 1;">
                        TRANSACTIONS
                    </h4>
                    <span class="mx-3 text-slate-300 font-weight-light" style="font-size: 1.4rem;">/</span>
                    <span class="text-xs font-black uppercase tracking-widest text-slate-500">
                        {{ Auth::user()->username === 'admin' ? 'System Admin' : Auth::user()->section_name }}
                    </span>
                </div>
            </div>

            <div class="glass-badge px-3 py-2 d-flex align-items-center">
                <small class="font-weight-bold text-slate-600 mr-2">{{ Auth::user()->username }}</small>
                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                    <i class="fas fa-user text-white" style="font-size: 10px;"></i>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                {{-- <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] ml-1">
                    Fund Utilization Monitoring Portal
                </span> --}}
            </div>
            
            <div class="d-flex align-items-center">
                {{-- Auto-Sync Status --}}
                <div class="mr-4 d-flex align-items-center text-[10px] font-bold text-success uppercase tracking-tighter">
                    <i class="fas fa-sync fa-spin mr-2 opacity-50"></i> DTrack Auto-Sync: Active
                </div>

                {{-- Action Buttons --}}
                <div class="btn-group shadow-sm rounded-lg overflow-hidden">
                    {{-- <button class="btn btn-white btn-sm font-weight-bold border-right px-3">
                        <i class="fas fa-history mr-2 text-warning"></i> Awaiting ORSN 
                        @if(isset($awaitingOBRN) && $awaitingOBRN > 0)
                            <span class="badge badge-warning ml-1">{{ $awaitingOBRN }}</span>
                        @endif
                    </button> --}}
                    <button type="button" id="btn-bulk-sync" class="btn btn-white btn-sm font-weight-bold border-right px-3">
                        <i class="fas fa-cloud-download-alt mr-2 text-info"></i> Bulk Sync
                        {{-- @if(isset($awaitingSyncCount) && $awaitingSyncCount > 0)
                            <span class="badge badge-warning ml-1">{{ $awaitingSyncCount }}</span>
                        @endif --}}
                    </button>
                    <button type="button" class="btn btn-success btn-sm font-weight-bold px-4 btn-add-new">
                        <i class="fas fa-plus mr-2"></i> Add Transaction
                    </button>
                </div>
            </div>
        </div>

        {{-- <hr class="border-slate-100 mb-4">
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
        </button>
        <button type="button" class="btn btn-success btn-add-new">
            <i class="fas fa-plus"></i> Add New Transaction
        </button> --}}
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
        {{-- FOR SECTION/UNIT FILTERING --}}
        @if($isAdmin)
            <div class="card card-outline card-primary shadow-sm mb-4 border-0">
                <div class="card-body py-3 px-4">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <h6 class="text-uppercase text-muted font-weight-bold mb-0 small tracking-wider">
                                <i class="fas fa-filter mr-1 text-primary"></i> Data Filtering
                            </h6>
                        </div>
                        <div class="col-md-4">
                            <select id="sectionFilter" class="form-control select2-modern custom-select-pill">
                                <option value="">All Administrative Sections</option>
                                @foreach($allSections as $id => $name)
                                    {{-- Use the exact name for the search value --}}
                                    <option value="{{ $name }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col text-right">
                            <span id="filterStatus" class="badge badge-pill badge-light border text-muted py-2 px-3">
                                <i class="fas fa-list-ul mr-1"></i> Showing all records
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        <table class="table table-bordered table-striped table-hover" id="funds-table">
            <thead>
                <tr>
                    <th style="width: 120px;">DTRACK NO.</th>
                    <th style="width: 80px;">Date</th>
                        @if(auth()->user()->is_admin)
                            <th style="width: 100px;">Section</th>
                        @endif
                    <th style="width: 160px;">Creditor</th> 
                    <th style="width: 90px;">Source</th>
                    <th style="width: 140px;">Activity</th> 
                    <th class="text-right" style="width: 110px;">Amount</th>
                    <th style="width: 140px;">Status & Remarks</th>
                    <th class="text-center" style="width: 80px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($funds as $fund)
                @php $firstItem = $fund->breakdown->first(); @endphp
                
                <tr class="{{ $firstItem->status == 'Disbursed' ? 'table-light' : '' }} fund-row" data-section="{{ $allSections[$fund->secid ?? 0] ?? 'Admin' }}">
                    <td class="text-center col-dtrack" data-order="{{ \Carbon\Carbon::parse($fund->created_at)->timestamp }}">
                        <a href="#" class="view-dtrack font-weight-bold" 
                        data-particulars="{{ e($fund->particulars ?? 'No particulars') }}" 
                        data-remarks="{{ e($fund->all_remarks ?? 'No remarks') }}">
                            {{ $fund->dtrack_no }}
                        </a>
                    </td>
                    <td class="col-date" data-order="{{ \Carbon\Carbon::parse($fund->transaction_date)->format('Y-m-d') }}">
                        {{ \Carbon\Carbon::parse($fund->transaction_date)->format('M d, Y') }}
                    </td>
                    @if($isAdmin)
                        <td>
                            @php
                                // Use ?? 0 to trigger the 'Admin' default if secid is missing or null
                                $sectionName = $allSections[$fund->secid ?? 0] ?? 'Admin';
                            @endphp

                            <span class="badge {{ ($fund->secid ?? null) ? 'bg-info text-dark' : 'bg-secondary' }}">
                                {{ $sectionName }}
                            </span>
                        </td>
                    @endif
                    <td class="col-creditor">
                        @if($fund->creditors->isNotEmpty())
                            @foreach($fund->creditors as $creditor)
                                <div class="badge badge-info mb-1">
                                    {{ $creditor->full_name }}
                                </div>
                            @endforeach
                        @else
                            <span class="text-muted small">No Creditor</span>
                        @endif
                    </td>
                    
                    <td class="col-source" style="font-size: 0.85rem; line-height: 1.2;">
                        {!! $fund->source_names !!}
                    </td>
                    
                    <td class="col-activity">
                        @if(empty($fund->transaction_type_id))
                            {{-- UNASSIGNED STATE --}}
                            <div class="unassigned-container-{{ $fund->id }}">
                                <span class="badge badge-warning text-dark mb-1">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Missing Activity
                                </span>
                                <select class="form-control form-control-sm select2-activity border-warning mt-1" data-id="{{ $fund->id }}" style="width: 100%;">
                                    <option value="" disabled selected>-- Select COS Position / Activity --</option>
                                    
                                    @php
                                        $user = auth()->user();
                                        $isAdminOrBudget = \Illuminate\Support\Facades\Gate::allows('budget-section');
                                        $isDivision       = \Illuminate\Support\Facades\Gate::allows('division-access');

                                        // Extract user section ID once
                                        $userSecId = null;
                                        if (!$isAdminOrBudget && !$isDivision) {
                                            $localDetail = \DB::table('employee_details')->where('user_id', $user->id)->first();
                                            if ($localDetail) {
                                                $userSecId = \DB::connection('db_common')
                                                    ->table('tbl_emp_details')
                                                    ->where('dbedid', $localDetail->dbedid)
                                                    ->value('secid');
                                            }
                                        }
                                        $divisionSecIds = $isDivision ? $user->getDivisionSectionIds() : [];
                                    @endphp

                                    {{-- Group activities by Fund Source Name --}}
                                    @foreach($allActivitiesList->groupBy(function($act) { return $act->source->name ?? 'Other / General Fund'; }) as $sourceName => $activitiesGroup)
                                        @php
                                            // Filter group items to matching section IDs
                                            $filteredGroup = $activitiesGroup->filter(function($act) use ($isAdminOrBudget, $isDivision, $divisionSecIds, $userSecId) {
                                                if ($isAdminOrBudget) return true;
                                                if ($isDivision) return in_array($act->section_id, $divisionSecIds);
                                                return $act->section_id == $userSecId;
                                            });
                                        @endphp

                                        @if($filteredGroup->count() > 0)
                                            <optgroup label="{{ $sourceName }}">
                                                @foreach($filteredGroup as $act)
                                                    <option value="{{ $act->id }}">{{ $act->name }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach

                                </select>
                            </div>
                        @else
                            {{-- ASSIGNED STATE --}}
                            <span class="font-weight-bold text-dark">{!! $fund->activity_names ?: 'N/A' !!}</span>
                        @endif
                    </td>
                    
                    <td class="col-amount text-right">
                        @if(count($fund->breakdown) > 1)
                            <div class="mb-1" style="border-bottom: 1px dashed #ddd; padding-bottom: 2px;">
                                @foreach($fund->breakdown as $item)
                                    <div class="text-muted" style="font-size: 0.7rem; line-height: 1;">
                                        {{ $item->source_name }}: 
                                        <span class="font-italic">
                                            @php
                                                /**
                                                 * Updated Logic Hierarchy:
                                                 * 1. If status is Disbursed/Completed -> Priority: disbursed_amount
                                                 * 2. If status is Obligated -> Priority: obligation_amount
                                                 * 3. Fallback -> amount (Processed)
                                                 */
                                                $status = $item->status;
                                                $valDisbursed = $item->disbursement_amount ?? 0;
                                                $valObligated = $item->obligation_amount ?? 0;
                                                $valProcessed = $item->amount ?? 0;

                                                if (($status === 'Disbursed' || $status === 'Completed') && $valDisbursed > 0) {
                                                    $displayAmount = $valDisbursed;
                                                } elseif ($status === 'Obligated' && $valObligated > 0) {
                                                    $displayAmount = $valObligated;
                                                } else {
                                                    $displayAmount = $valProcessed;
                                                }
                                            @endphp
                                            ₱{{ number_format($displayAmount, 2) }}
                                        </span>

                                        {{-- Warning icon if it's Obligated but the amount hasn't synced yet --}}
                                        @if($item->status == 'Obligated' && (!$item->obligation_amount || $item->obligation_amount <= 0))
                                            <i class="fas fa-exclamation-circle text-danger" title="Pending Sync"></i>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="font-weight-bold" style="font-size: 1rem;">
                            {{-- Display Amount based on Status Color --}}
                            @if($fund->group_status == 'Disbursed' || $fund->group_status == 'Completed')
                                <span class="text-success">₱{{ number_format($fund->total_amount, 2) }}</span>
                                <div class="text-xs text-muted" style="font-size: 0.6rem;">(DISBURSED)</div>
                            @elseif($fund->group_status == 'Obligated')
                                <span class="text-primary">₱{{ number_format($fund->total_amount, 2) }}</span>
                                <div class="text-xs text-muted" style="font-size: 0.6rem;">(OBLIGATED)</div>
                            @else
                                <span>₱{{ number_format($fund->total_amount, 2) }}</span>
                                <div class="text-xs text-muted" style="font-size: 0.6rem;">(Processed)</div>
                            @endif

                            {{-- ALWAYS show Awaiting Sync if any part of the group needs it --}}
                            @if(!$fund->is_fully_synced)
                                <div class="text-xs text-danger font-italic mt-1" style="font-size: 0.6rem;">
                                    <i class="fas fa-sync fa-spin"></i> Awaiting sync
                                </div>
                            @endif
                        </div>
                    </td>

                    <td class="col-status">
                        @php
                            // 1. Determine Merged vs. Detailed View Mode
                            $hasSignificantStatus = $fund->breakdown->contains(function($item) {
                                return in_array($item->status, ['Obligated', 'Disbursed', 'Disbursed (with savings)', 'Completed', 'Cancelled']);
                            });
                            $firstStatus = $fund->breakdown->first()->status ?? 'N/A';
                            $allSameStatus = $fund->breakdown->every('status', $firstStatus);

                            // 2. Synced Remarks Categorization Helper Function
                            $classifyRemark = function($item) {
                                if (empty($item->remarks)) return null;

                                $isCosSalary = (isset($item->remarks_salary) && $item->remarks_salary === 'Imported HR COS Salary/Wages') 
                                            || \Illuminate\Support\Str::contains(strtolower($item->remarks), ['cos', 'salary', 'wages', 'payroll']);

                                return [
                                    'text' => $item->remarks,
                                    'type' => $isCosSalary ? 'COS' : 'DTrack',
                                    'label' => $isCosSalary ? 'COS Remarks' : 'DTrack Remarks',
                                    'icon' => $isCosSalary ? 'fa-id-badge text-warning' : 'fa-route text-secondary',
                                    'class' => $isCosSalary ? 'border-warning' : 'border-secondary'
                                ];
                            };

                            // 3. Merged View Deduplication Collections
                            $uniqueSyncedRemarks = $fund->breakdown->map($classifyRemark)->filter()->unique('text');
                            $uniqueManualRemarks = $fund->breakdown->pluck('manual_remarks')->filter()->unique();
                        @endphp

                        @if(!$hasSignificantStatus && $allSameStatus)
                            {{-- ================= MERGED VIEW (EARLY STAGE) ================= --}}
                            <div class="text-left">
                                <span class="badge {{ 
                                    $firstStatus == 'Routed' ? 'badge-primary' : 
                                    ($firstStatus == 'For CAF/Obligation' ? 'badge-warning' : 'badge-info') 
                                }}">
                                    {{ $firstStatus }}
                                </span>

                                {{-- NORSA Savings Badge (Merged View) --}}
                                @if(isset($fund->has_norsa) && $fund->has_norsa)
                                    <div class="mt-1">
                                        <span class="badge badge-danger shadow-sm" title="This transaction has NORSA adjustments">
                                            <i class="fas fa-file-invoice-dollar mr-1"></i> NORSA Savings (₱{{ number_format($fund->total_norsa, 2) }})
                                        </span>
                                    </div>
                                @endif

                                {{-- COS Contract Duration Badge --}}
                                @if(isset($fund->remarks_salary) && $fund->remarks_salary === 'Imported HR COS Salary/Wages' && isset($fund->contract))
                                    <div class="mt-1">
                                        @if($fund->disbursed_months >= $fund->contract->total_months)
                                            <span class="badge badge-success" title="Period: {{ $fund->contract->start_date }} to {{ $fund->contract->end_date }}">
                                                <i class="fas fa-check-circle mr-1"></i> Contract Fully Disbursed ({{ $fund->disbursed_months }}/{{ $fund->contract->total_months }} mos)
                                            </span>
                                        @else
                                            <span class="badge badge-info" title="Period: {{ $fund->contract->start_date }} to {{ $fund->contract->end_date }}">
                                                <i class="fas fa-hourglass-half mr-1"></i> Paid {{ $fund->disbursed_months }} of {{ $fund->contract->total_months }} Months
                                            </span>
                                        @endif
                                    </div>
                                @endif

                                {{-- Classified Synced Remarks (COS vs DTrack) --}}
                                @foreach($uniqueSyncedRemarks as $remarkObj)
                                    <div class="mt-1 text-muted small border-left pl-2 {{ $remarkObj['class'] }}" style="font-style: italic;">
                                        <i class="fas {{ $remarkObj['icon'] }} mr-1" style="font-size: 0.7rem;"></i> 
                                        <strong class="text-dark">{{ $remarkObj['label'] }}:</strong> {{ $remarkObj['text'] }}
                                    </div>
                                @endforeach

                                {{-- Manual Internal Remarks Display & Input Trigger --}}
                                <div class="mt-2 manual-remarks-wrapper-{{ $fund->id }}">
                                    @if($uniqueManualRemarks->isNotEmpty())
                                        <div class="text-dark small border-left border-primary pl-2 bg-light rounded py-1 pr-2">
                                            <i class="fas fa-user-edit mr-1 text-primary" style="font-size: 0.75rem;"></i> 
                                            <strong class="text-primary">Internal Remarks:</strong> 
                                            <span class="manual-remarks-text-{{ $fund->id }}">{{ $uniqueManualRemarks->implode('; ') }}</span>
                                            <button class="btn btn-link btn-xs text-muted p-0 ml-1 btn-edit-manual-remark" data-id="{{ $fund->id }}" data-current="{{ $uniqueManualRemarks->first() }}" title="Edit Internal Remark">
                                                <i class="fas fa-pencil-alt" style="font-size: 0.65rem;"></i>
                                            </button>
                                        </div>
                                    @else
                                        <button class="btn btn-xs btn-outline-secondary mt-1 btn-edit-manual-remark" data-id="{{ $fund->id }}" data-current="" title="Add Internal Remark">
                                            <i class="fas fa-plus-circle mr-1"></i> Add Internal Remarks
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @else
                            {{-- ================= DETAILED VIEW SECTION ================= --}}
                            @foreach($fund->breakdown as $item)
                                @php
                                    $itemRemarkInfo = $classifyRemark($item);
                                @endphp
                                <div class="allocation-row {{ !$loop->last ? 'mb-3 border-bottom pb-2' : '' }}">
                                    <div class="small font-weight-bold text-dark mb-1">
                                        <i class="fas fa-wallet text-muted mr-1"></i> {{ $item->source_name }}
                                    </div>

                                    {{-- Status Badge Rendering --}}
                                    <span class="badge {{ 
                                        in_array($item->status, ['Disbursed', 'Completed']) ? 'badge-success' : 
                                        ($item->status == 'Disbursed (with savings)' ? 'badge-info' : 
                                        ($item->status == 'Cancelled' ? 'badge-danger' : 
                                        ($item->status == 'Routed' ? 'badge-primary' : 
                                        ($item->status == 'For CAF/Obligation' ? 'badge-warning' : 
                                        ($item->status == 'Obligated' ? 'bg-orange text-white' : 'badge-info')))))
                                    }}">
                                        {{ $item->status }}
                                    </span>

                                    {{-- NORSA SAVINGS BADGE TAG --}}
                                    @if(isset($item->has_norsa) && $item->has_norsa)
                                        <span class="badge badge-danger shadow-sm ml-1" title="NORSA savings adjustment recorded">
                                            <i class="fas fa-file-invoice-dollar mr-1"></i> NORSA: (₱{{ number_format($item->norsa_amount, 2) }})
                                        </span>
                                    @endif

                                    {{-- COS Contract Duration Badge --}}
                                    @if(isset($item->remarks_salary) && $item->remarks_salary === 'Imported HR COS Salary/Wages' && isset($item->contract))
                                        <div class="mt-1">
                                            @if($item->disbursed_months >= $item->contract->total_months)
                                                <span class="badge badge-success" title="Period: {{ $item->contract->start_date }} to {{ $item->contract->end_date }}">
                                                    <i class="fas fa-check-circle mr-1"></i> Contract Fully Disbursed ({{ $item->disbursed_months }}/{{ $item->contract->total_months }} mos)
                                                </span>
                                            @else
                                                <span class="badge badge-info" title="Period: {{ $item->contract->start_date }} to {{ $item->contract->end_date }}">
                                                    <i class="fas fa-hourglass-half mr-1"></i> Paid {{ $item->disbursed_months }} of {{ $item->contract->total_months }} Months
                                                </span>
                                            @endif
                                        </div>
                                    @endif

                                    <div class="small mt-1">
                                        {{-- OBLIGATED STATE --}}
                                        @if($item->status == 'Obligated')
                                            @if(empty($item->obligation_amount) || $item->obligation_amount == 0)
                                                <div class="text-danger font-italic">
                                                    <i class="fas fa-sync fa-spin mr-1" style="font-size: 0.7rem;"></i> Awaiting sync to RAODS
                                                </div>
                                            @else
                                                @if($item->obligation_date)
                                                    <div class="text-primary font-weight-bold">
                                                        <i class="far fa-calendar-check mr-1"></i> Oblig: {{ \Carbon\Carbon::parse($item->obligation_date)->format('M d, Y') }}
                                                    </div>
                                                @endif

                                                {{-- NET vs GROSS OBLIGATION BREAKDOWN (WHEN NORSA APPLIES) --}}
                                                @if($item->has_norsa)
                                                    <div class="text-info font-weight-bold mt-0.5" style="font-size: 0.72rem;">
                                                        Net Obligation: ₱{{ number_format($item->net_obligation_amount, 2) }}
                                                    </div>
                                                    <div class="text-muted" style="font-size: 0.65rem;">
                                                        (Gross: ₱{{ number_format($item->obligation_amount, 2) }})
                                                    </div>
                                                @endif
                                            @endif

                                        {{-- DISBURSED / DISBURSED (WITH SAVINGS) / COMPLETED STATES --}}
                                        @elseif(in_array($item->status, ['Disbursed', 'Disbursed (with savings)', 'Completed']))
                                            @if($item->obligation_date)
                                                <div class="text-muted" style="font-size: 0.7rem;">
                                                    <i class="far fa-calendar-alt mr-1"></i> Oblig: {{ \Carbon\Carbon::parse($item->obligation_date)->format('M d, Y') }}
                                                </div>
                                            @endif

                                            @if($item->disbursement_date)
                                                <div class="text-success font-weight-bold">
                                                    <i class="fas fa-check-circle mr-1"></i> Disb: {{ \Carbon\Carbon::parse($item->disbursement_date)->format('M d, Y') }}
                                                </div>

                                                @php
                                                    $ob = \Carbon\Carbon::parse($item->obligation_date);
                                                    $disb = \Carbon\Carbon::parse($item->disbursement_date);
                                                    $days = $ob->diffInDays($disb);
                                                @endphp
                                                <div class="text-info font-italic" style="font-size: 0.65rem;">
                                                    <i class="fas fa-hourglass-half mr-1"></i> Lead Time: {{ $days }} {{ Str::plural('day', $days) }}
                                                </div>
                                            @endif

                                            @if($item->status == 'Disbursed (with savings)' || ($item->obligation_amount > $item->disbursement_amount && $item->disbursement_amount > 0))
                                                @php
                                                    $savings = max(0, $item->obligation_amount - $item->disbursement_amount);
                                                @endphp
                                                @if($savings > 0)
                                                    <div class="text-success font-weight-bold mt-1" style="font-size: 0.75rem;">
                                                        <i class="fas fa-piggy-bank mr-1"></i> Savings: ₱{{ number_format($savings, 2) }}
                                                    </div>
                                                @endif
                                            @endif
                                        @endif

                                        {{-- Obligation Serial Number --}}
                                        @if($item->obligation_serial)
                                            <div class="text-primary font-weight-bold mt-1">
                                                <i class="fas fa-barcode mr-1"></i> {{ $item->obligation_serial }}
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Classified Individual Synced Remark --}}
                                    @if($itemRemarkInfo)
                                        <div class="mt-1 text-muted small border-left pl-2 {{ $itemRemarkInfo['class'] }}" style="font-style: italic; background-color: #fcfcfc;">
                                            <i class="fas {{ $itemRemarkInfo['icon'] }} mr-1" style="font-size: 0.7rem;"></i> 
                                            <strong class="text-dark">{{ $itemRemarkInfo['label'] }}:</strong> {{ $itemRemarkInfo['text'] }}
                                        </div>
                                    @endif

                                    {{-- Manual Internal Remarks (Per Allocation Item) --}}
                                    <div class="mt-1 manual-remarks-wrapper-{{ $item->id }}">
                                        @if($item->manual_remarks)
                                            <div class="text-dark small border-left border-primary pl-2 bg-light rounded py-1 pr-2">
                                                <i class="fas fa-user-edit mr-1 text-primary" style="font-size: 0.7rem;"></i> 
                                                <strong class="text-primary">Internal Remarks:</strong> 
                                                <span class="manual-remarks-text-{{ $item->id }}">{{ $item->manual_remarks }}</span>
                                                <button class="btn btn-link btn-xs text-muted p-0 ml-1 btn-edit-manual-remark" data-id="{{ $item->id }}" data-current="{{ $item->manual_remarks }}" title="Edit Internal Remark">
                                                    <i class="fas fa-pencil-alt" style="font-size: 0.65rem;"></i>
                                                </button>
                                            </div>
                                        @else
                                            <button class="btn btn-xs btn-outline-secondary mt-1 btn-edit-manual-remark" data-id="{{ $item->id }}" data-current="" title="Add Manual Remark">
                                                <i class="fas fa-plus-circle mr-1"></i> Add Internal Remarks
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @endif 
                    </td>

                    <td class="text-center">
                        @php 
                            // 1. Get the entire group for this DTrack
                            // We fetch this once to avoid multiple queries
                            $groupItems = \App\Models\Fund::where('dtrack_no', $fund->dtrack_no)->get();
                            
                            $totalInGroup = $groupItems->count();
                            $disbursedInGroup = $groupItems->where('status', 'Disbursed')->count();

                            // 2. Button Lock: ONLY lock if EVERY fund source in the group is 'Disbursed'
                            // We removed $hasAnySerial because DTrack is now our unique key to fetch data.
                            $isFullyDisbursed = ($totalInGroup > 0 && $totalInGroup === $disbursedInGroup);

                            // 3. Action Logic: Edit/Delete are usually disabled if ANY item has moved past 'Routed'
                            // This protects the data integrity once Budget/Accounting starts processing.
                            $firstItem = $groupItems->first();
                            $isActionDisabled = ($firstItem && $firstItem->status !== 'Routed');
                            
                            // 4. Status Check: Disable DTrack status updates only if fully finished
                            $isStatusUpdateDisabled = ($isFullyDisbursed || in_array($firstItem->status ?? '', ['Completed']));
                        @endphp

                        {{-- 1. Update Status / History Button --}}
                        <button type="button" class="btn btn-sm btn-default btn-flat shadow-sm btn-update-status"
                            style="border-left: 3px solid #17a2b8;"
                            data-id="{{ $fund->id }}" data-dtrack="{{ $fund->dtrack_no }}"
                            {{ $isStatusUpdateDisabled ? 'disabled' : '' }}
                            data-toggle="tooltip" title="Update Status">
                            <i class="fas fa-history {{ $isStatusUpdateDisabled ? 'text-muted' : 'text-info' }}"></i> 
                        </button>

                        {{-- 2. Edit Button --}}
                        <button type="button" class="btn btn-sm btn-default btn-edit-transaction"
                            data-id="{{ $fund->id }}"
                            {{ $isActionDisabled ? 'disabled' : '' }}
                            data-toggle="tooltip" 
                            title="{{ $isActionDisabled ? 'Locked: Only Routed transactions can be edited' : 'Edit Transaction' }}">
                            <i class="fas fa-edit {{ $isActionDisabled ? 'text-muted' : 'text-warning' }}"></i> 
                        </button>

                        {{-- 3. Sync Button (UPDATED) --}}
                        <button type="button" 
                            class="btn btn-sm {{ $isFullyDisbursed ? 'btn-outline-secondary' : 'btn-outline-info' }} btn-sync-sheet" 
                            data-id="{{ $fund->id }}"
                            data-dtrack="{{ $fund->dtrack_no }}"
                            {{-- 
                                The button is ONLY disabled if ALL items in the group are Disbursed. 
                                We no longer disable it based on the lack of a serial number 
                                because the system now fetches the serial for us using DTrack.
                            --}}
                            {{ $isFullyDisbursed ? 'disabled' : '' }}
                            data-toggle="tooltip" 
                            title="{{ $isFullyDisbursed ? 'All items for this DTrack are fully Disbursed' : 'Sync with RAODS Google Sheet' }}">
                            
                            <i class="fas {{ $isFullyDisbursed ? 'fa-check-double' : 'fa-sync-alt' }} {{ !$isFullyDisbursed ? 'fa-spin-hover' : '' }}"></i>
                        </button>

                        {{-- 4. Delete Button --}}
                        <button type="button" class="btn btn-sm btn-default btn-flat shadow-sm btn-delete-transaction"
                            style="border-left: 3px solid {{ $isActionDisabled ? '#6c757d' : '#dc3545' }};" 
                            data-id="{{ $fund->id }}" 
                            {{ $isActionDisabled ? 'disabled' : '' }}
                            data-toggle="tooltip"
                            title="{{ $isActionDisabled ? 'Locked: Only Routed transactions can be deleted' : 'Delete Transaction' }}">
                            <i class="fas fa-trash {{ $isActionDisabled ? 'text-muted' : 'text-danger' }}"></i>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@include('funds.modal_form')

{{-- Status Update Modal --}}
<div class="modal fade" id="statusModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document"> <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title">Update Status: <span id="display_dtrack"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="status-update-form">
                @csrf
                @method('PATCH')
                <input type="hidden" name="fund_id" id="modal_fund_id">
               
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required"><i class="fas fa-calendar-alt mr-1"></i> Status Date</label>
                                <input type="date" name="status_date" id="modal_status_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required">Transaction Status</label>
                                <select name="status" id="modal_status_select" class="form-control" required>
                                    <option value="For Signature">For Signature</option>
                                    <option value="Obligated">Obligated</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="serial_no_section" style="display: none;">
                        <hr>
                        <label class="text-primary font-weight-bold"><i class="fas fa-barcode mr-1"></i> Obligation Reference Serial Numbers</label>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Source of Fund</th>
                                        <th>Amount</th>
                                        <th style="width: 40%;">Serial No. (from Google Sheet)</th>
                                    </tr>
                                </thead>
                                <tbody id="serial_inputs_container">
                                    </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <label>Remarks / Notes</label>
                        <textarea name="remarks" id="modal_remarks_input" class="form-control" rows="2" placeholder="Enter status updates or reasons here..."></textarea>
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

{{-- Sync Summary Modal --}}
<div class="modal fade" id="syncSummaryModal" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-info">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-list-check mr-2"></i> Bulk Sync Report</h5>
            </div>
            <div class="modal-body" style="max-height: 80vh; overflow-y: auto;">
                <div class="row text-center mb-4">
                    <div class="col-3">
                        <h2 id="sum-total" class="font-weight-bold mb-0 text-secondary">0</h2>
                        <small class="text-muted font-weight-bold">TOTAL ITEMS</small>
                    </div>
                    <div class="col-3 border-left">
                        <h2 id="sum-success" class="text-success font-weight-bold mb-0">0</h2>
                        <small class="text-muted font-weight-bold">SYNCED</small>
                    </div>
                    <div class="col-3 border-left">
                        <h2 id="sum-duplicates" class="text-warning font-weight-bold mb-0">0</h2>
                        <small class="text-muted font-weight-bold">HAD DUPLICATES</small>
                    </div>
                    <div class="col-3 border-left">
                        <h2 id="sum-failed" class="text-danger font-weight-bold mb-0">0</h2>
                        <small class="text-muted font-weight-bold">FAILED / NOT FOUND</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <h6 class="text-success border-bottom pb-2 font-weight-bold">
                            <i class="fas fa-check-circle mr-1"></i> Successful Syncs
                        </h6>
                        <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                            <table class="table table-sm table-hover border">
                                <thead class="bg-light sticky-top">
                                    <tr>
                                        <th>Serial Number</th>
                                        <th>New Status</th>
                                        <th>Amount</th>
                                        <th>Duplicates (Row #)</th>
                                    </tr>
                                </thead>
                                <tbody id="list-success-table" class="small">
                                    </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <h6 class="text-danger border-bottom pb-2 font-weight-bold text-center">
                            <i class="fas fa-times-circle mr-1"></i> Failed / Not Found
                        </h6>
                        <ul id="list-failed" class="list-group list-group-flush small" style="max-height: 350px; overflow-y: auto;">
                            </ul>
                    </div>
                </div>

                <div id="halted-warning" class="alert alert-warning mt-3 mb-0 d-none">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Process was halted manually.
                </div>
            </div>
            <div class="modal-footer bg-light justify-content-between">
                <small class="text-muted italic">* Duplicate rows skipped to prevent amount inflation.</small>
                <button type="button" class="btn btn-secondary shadow-sm" onclick="location.reload()">Close & Refresh Page</button>
            </div>
        </div>
    </div>
</div>

{{-- Sync Result Modal --}}
<div class="modal fade" id="syncResultModal" tabindex="-1" role="dialog" aria-labelledby="syncResultModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-body p-4 text-center">
                <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                <h4>Sync Complete</h4>
                <p class="text-muted">DTrack: <span id="rs-serial" class="font-weight-bold"></span></p>

                <div class="text-left bg-light p-3 border rounded mb-3" style="max-height: 250px; overflow-y: auto;">
                    <small class="text-muted text-uppercase d-block mb-2" style="font-size: 0.7rem; letter-spacing: 1px;">Update Details per Source:</small>
                    <div id="rs-fund-list" style="font-size: 0.8rem;">
                        </div>
                </div>

                <div class="row">
                    <div class="col-6 border-right">
                        <small class="text-muted d-block">Result</small>
                        <span id="rs-amount" class="font-weight-bold"></span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Status</small>
                        <span id="rs-status" class="badge"></span>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="button" class="btn btn-primary btn-block shadow-sm" data-dismiss="modal" onclick="window.location.reload();">
                        Done
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- View Transaction Modal --}}
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
                            {{-- <tr class="py-1">
                                <th><i class="fas fa-barcode mr-2 text-muted"></i> Serial No:</th> 
                                <td id="v_serial" class="text-dark"></td>
                            </tr> --}}
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
    var isAdmin = {{ auth()->user()->is_admin ? 'true' : 'false' }};
</script>
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

    $(document).ready(function() {
        // 1. Establish a safe dynamic base URL for subfolder hosting
        // Laravel will print "http://192.168.2.211/fund-monitoring" natively here
        const APP_URL = "{{ url('/') }}";

        // 2. Handle dropdown changes immediately
        $(document).on('change', '.source-select, .activity-select', function() {
            const $row = $(this).closest('tr');
            const $amountField = $row.find('.amount-field');
            const $warningLabel = $row.find('.budget-warning');

            // Check if both dropdowns have a value
            if ($row.find('.source-select').val() && $row.find('.activity-select').val()) {
                
                // Show a "Checking..." state so the user knows something is happening
                $warningLabel.html('<span class="text-muted"><i class="fas fa-spinner fa-spin"></i> Checking balance...</span>').show();
                
                // Trigger the AJAX check
                checkRowBudgetBalance($row);
                
            } else {
                // RESET LOGIC: Re-enable and clear if selection is incomplete
                $warningLabel.html('').hide();
                $amountField.prop('disabled', false).val('').removeClass('is-invalid');
                
                // Ensure global submit is updated
                $row.attr('data-budget-valid', 'true');
                validateGlobalSubmit();
            }
        });

        // 3. Optimized Input Listener with Independent Row Debouncing
        $(document).on('input', '.amount-field', function() {
            const $row = $(this).closest('tr');
            
            // Clear previous timer for THIS row only
            clearTimeout($row.data('typingTimer'));
            
            // Set new timer and save it to the row object
            const timer = setTimeout(function() {
                checkRowBudgetBalance($row);
            }, 400); 
            
            $row.data('typingTimer', timer);
        });

        // 4. Fixed Balance Checking Function with Subfolder Route Resolution
        function checkRowBudgetBalance($row) {
            let sourceId = $row.find('.source-select').val();
            let activityId = $row.find('.activity-select').val();
            let $amountField = $row.find('.amount-field');
            let amount = parseFloat($amountField.val()) || 0;
            
            // Find or Inject the label
            let warningLabel = $row.find('.budget-warning');
            if (warningLabel.length === 0) {
                $row.find('.activity-select').after('<div class="budget-warning mt-1" style="min-height: 18px; font-size: 0.75rem;"></div>');
                warningLabel = $row.find('.budget-warning');
            }

            if (!sourceId || !activityId) {
                warningLabel.html('').hide();
                $amountField.prop('disabled', false); 
                return;
            }

            $.ajax({
                // FIXED: We concatenate the base app subfolder path with the target route endpoint literal
                url: APP_URL + "/funds/check-balance",
                method: "GET",
                data: {
                    source_of_fund_id: sourceId,
                    activity_id: activityId,
                    amount: amount,
                    current_fund_id: $('#edit_fund_id').val()
                },
                success: function(response) {
                    // Ensure the label is visible
                    warningLabel.show().css('display', 'block');
                    
                    // Check if the actual remaining balance is zero
                    const remainingBalance = parseFloat(response.remaining) || 0;

                    if (remainingBalance <= 0) {
                        $amountField.val(0)
                            .prop('readonly', true)
                            .addClass('bg-light text-muted') 
                            .removeClass('is-invalid');
                            
                        $row.attr('data-budget-valid', 'true');
                        warningLabel.html(`
                            <span class="text-danger font-weight-bold">
                                <i class="fas fa-ban"></i> No Balance Available (₱0.00)
                            </span>
                        `);
                    } else {
                        // Normal state
                        $amountField.prop('readonly', false)
                            .removeClass('bg-light text-muted');
                        $row.attr('data-budget-valid', response.is_sufficient ? 'true' : 'false');

                        if (response.is_sufficient) {
                            warningLabel.html(`
                                <span class="text-success">
                                    <i class="fas fa-check-circle"></i> Available: ₱${response.formatted_remaining}
                                </span>
                            `);
                            $amountField.removeClass('is-invalid');
                        } else {
                            warningLabel.html(`
                                <div style="border-left: 3px solid #dc3545; padding-left: 5px; background: #fff5f5;">
                                    <span class="text-danger font-weight-bold" style="font-size: 0.75rem;">
                                        <i class="fas fa-exclamation-triangle"></i> Exceeds! Max: ₱${response.formatted_remaining}
                                    </span>
                                </div>
                            `);
                            $amountField.addClass('is-invalid');
                        }
                    }
                    
                    // Update global submit button
                    if (typeof validateGlobalSubmit === "function") {
                        validateGlobalSubmit();
                    }
                },
                error: function() {
                    warningLabel.html('<span class="text-muted">Error checking balance.</span>');
                }
            });
        }
    });

    /**
     * Manages the Submit button state based on all row statuses
     */
    function validateGlobalSubmit() {
        let hasError = false;
        let grandTotal = 0;
        
        // Loop through each row to check validity and calculate the total
        $('.allocation-row').each(function() {
            let $row = $(this);
            let $amountInput = $row.find('.amount-field');
            
            // Use a helper to get a clean number, treating empty/invalid as 0
            let amount = parseFloat($amountInput.val()) || 0;

            // 1. VALIDATION CHECK
            if ($row.attr('data-budget-valid') === 'false' && !$amountInput.prop('readonly')) {
                hasError = true;
            }

            // 2. GRAND TOTAL CALCULATION
            // Only add to the total if the row is NOT readonly (available balance > 0)
            if (!$amountInput.prop('readonly')) {
                grandTotal += amount;
            }
        });

        // 3. UPDATE UI
        // Update the visual Grand Total (Assuming you have an element with this ID)
        $('#grand-total-display').text('₱ ' + grandTotal.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }));

        // 4. SUBMIT BUTTON STATE
        if (hasError) {
            $('#submit-btn').prop('disabled', true);
            $('#global-budget-error').fadeIn();
        } else {
            $('#submit-btn').prop('disabled', false);
            $('#global-budget-error').fadeOut();
        }
    }


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

    $(document).on('click', '.btn-sync-sheet', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const fundId = $btn.data('id');
        const dtrackNo = $btn.data('dtrack'); 
        
        // Select all buttons sharing the same DTrack
        const $groupButtons = $(`.btn-sync-sheet[data-dtrack="${dtrackNo}"]`);
        const $groupIcons = $groupButtons.find('i');

        // UI Feedback
        $groupButtons.prop('disabled', true);
        $groupIcons.attr('class', 'fas fa-circle-notch fa-spin text-warning');

        $.ajax({
            url: `/funds/${fundId}/sync`,
            method: "GET",
            dataType: "json",
            success: function (response) {
                if (response.success) {
                    const details = response.details;
                    $('#rs-serial').text(details.dtrack_no);
                    
                    const $list = $('#rs-fund-list');
                    $list.empty();
                    
                    details.synced_items.forEach(item => {
                        const statusBadge = item.status === 'Disbursed' ? 'badge-success' : 'badge-info';
                        
                        $list.append(`
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <span class="font-weight-bold text-dark">${item.name}</span>
                                    <span class="badge ${statusBadge}">${item.status}</span>
                                </div>
                                <div class="d-flex justify-content-between text-muted" style="font-size: 0.75rem;">
                                    <span>Serial: ${item.serial}</span>
                                    <span>Amount: ₱${item.amount}</span>
                                </div>
                            </div>
                        `);
                    });

                    $('#rs-amount').text(`${details.count} Record(s)`);
                    $('#rs-status').text('Sync Successful').addClass('badge-success');
                    $('#syncResultModal').modal('show');
                }
            },
            error: function (xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Server Error'));
            },
            complete: function() {
                $groupButtons.prop('disabled', false);
                $groupIcons.attr('class', 'fas fa-sync-alt');
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
            
            let results = { 
                success: 0, 
                failed: 0, 
                duplicateCount: 0,
                successList: [], 
                failedList: [] 
            };

            if (!confirm(`Start Bulk Sync? This will auto-import all new COS Salary rows and sync existing items.`)) return;

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Auto-Importing COS Salaries...');
            $('#sync-progress-container').slideDown();
            $('#sync-progress-bar').css('width', '5%');
            $('#sync-percent-text').text('Reading Google Sheets...');

            function captureTableItems() {
                let items = [];
                if ($.fn.DataTable.isDataTable('#funds-table')) {
                    let table = $('#funds-table').DataTable();
                    table.rows().every(function() {
                        const rowNode = this.node();
                        const syncBtn = $(rowNode).find('.btn-sync-sheet');
                        const statusText = $(rowNode).find('td').eq(6).text().trim().toLowerCase(); 

                        const isExcludedStatus = statusText === 'disbursed' || 
                                                statusText === 'cancelled' || 
                                                statusText.indexOf('disbursed (with savings)') !== -1;

                        if (!isExcludedStatus && syncBtn.length > 0 && !syncBtn.is(':disabled')) {
                            items.push({
                                id: syncBtn.data('id')
                            });
                        }
                    });
                }
                return items;
            }

            function recordFailure(serial, reason) {
                results.failed++;
                results.failedList.push({ serial: serial, reason: reason });
            }

            function updateUI(idx, total) {
                let progress = Math.round(((idx + 1) / total) * 100);
                $('#sync-progress-bar').css('width', progress + '%');
                $('#sync-percent-text').text(`${progress}% (${idx + 1}/${total})`);
            }

            function showSummary(res) {
                let successHtml = '';
                res.successList.forEach(item => {
                    let badgeClass = 'badge-primary';
                    if (item.status === 'Disbursed') {
                        badgeClass = 'badge-success';
                    } else if (item.status && item.status.indexOf('Disbursed') !== -1) {
                        badgeClass = 'badge-info';
                    }

                    successHtml += `
                        <tr>
                            <td><strong>${item.serial}</strong></td>
                            <td><span class="badge ${badgeClass}">${item.status}</span></td>
                            <td>₱${item.amount}</td>
                            <td>${item.duplicates && item.duplicates.length > 0 ? item.duplicates.join(', ') : 'None'}</td>
                        </tr>`;
                });
                $('#list-success-table').html(successHtml || '<tr><td colspan="4" class="text-center">No successful updates.</td></tr>');

                let failedHtml = '';
                res.failedList.forEach(item => {
                    failedHtml += `
                        <li class="list-group-item list-group-item-danger py-1">
                            <strong>${item.serial}</strong>: ${item.reason}
                        </li>`;
                });
                $('#list-failed').html(failedHtml || '<li class="list-group-item text-center">No failed transactions.</li>');

                let totalProcessed = res.success + res.failed;
                $('#sum-total').text(totalProcessed);
                $('#sum-success').text(res.success);
                $('#sum-failed').text(res.failed);
                
                $('#syncSummaryModal').modal('show');
            }

            let csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val();

            // --- STEP 1: Run Backend Auto-Import Job ---
            $.ajax({
                url: '/funds/bulk-sync',
                method: 'POST',
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                data: { _token: csrfToken },
                success: function(response) {
                    if (response && response.imported_items && response.imported_items.length > 0) {
                        response.imported_items.forEach(item => {
                            const exists = item.id ? results.successList.some(s => s.id === item.id) : false;
                            if (!exists) {
                                results.success++;
                                results.successList.push({
                                    id: item.id || null,
                                    serial: item.serial,
                                    status: item.status,
                                    amount: item.amount,
                                    duplicates: []
                                });
                            }
                        });
                    }
                },
                complete: function() {
                    if ($.fn.DataTable.isDataTable('#funds-table')) {
                        let dt = $('#funds-table').DataTable();
                        if (dt.settings()[0].oFeatures.bServerSide || dt.settings()[0].ajax) {
                            dt.ajax.reload(function() {
                                startSequentialSync();
                            }, false);
                            return;
                        }
                    }
                    startSequentialSync();
                }
            });

            // --- STEP 2: Sequential Item Processing Loop ---
            function startSequentialSync() {
                let items = captureTableItems();
                let total = items.length;
                let currentIdx = 0;

                if (total === 0) {
                    btn.prop('disabled', false).html('<i class="fas fa-sync"></i> Bulk Sync');
                    showSummary(results);
                    return;
                }

                btn.html('<i class="fas fa-spinner fa-spin"></i> Syncing Items...');

                function processNext() {
                    if (window.bulkSyncStopSignal || currentIdx >= total) {
                        showSummary(results);
                        btn.prop('disabled', false).html('<i class="fas fa-sync"></i> Bulk Sync');
                        
                        if ($.fn.DataTable.isDataTable('#funds-table')) {
                            let dt = $('#funds-table').DataTable();
                            if (dt.settings()[0].oFeatures.bServerSide || dt.settings()[0].ajax) {
                                dt.ajax.reload(null, false);
                            }
                        }
                        return;
                    }

                    let currentItem = items[currentIdx];
                    
                    $.ajax({
                        url: `/funds/${currentItem.id}/sync`,
                        method: "GET",
                        dataType: "json",
                        success: function(response) {
                            const d = response.details || {};
                            const hasSyncedItems = response.success && d.synced_items && d.synced_items.length > 0;

                            if (hasSyncedItems) {
                                const itemData = d.synced_items[0];
                                const rowDbSerial = response.obligation_serial || itemData.serial || `ID-${currentItem.id}`;
                                let duplicateRows = [...(d.duplicate_ob_rows || []), ...(d.duplicate_disb_rows || [])];
                                
                                let existingIdx = results.successList.findIndex(s => s.id === currentItem.id);
                                
                                if (existingIdx !== -1) {
                                    results.successList[existingIdx] = {
                                        id: currentItem.id,
                                        serial: rowDbSerial,
                                        status: itemData.status,
                                        amount: itemData.amount,
                                        duplicates: duplicateRows
                                    };
                                } else {
                                    results.success++;
                                    results.successList.push({
                                        id: currentItem.id,
                                        serial: rowDbSerial,
                                        status: itemData.status,
                                        amount: itemData.amount,
                                        duplicates: duplicateRows
                                    });
                                }
                            } else {
                                // Correctly Route Missing RAODS Items to Failed List
                                const failSerial = response.obligation_serial || `ID ${currentItem.id}`;
                                const failReason = (response && response.message) ? response.message : "No available data found in RAODS";
                                recordFailure(failSerial, failReason);
                            }
                            finishItem();
                        },
                        error: function(xhr) {
                            let errorMsg = xhr.responseJSON ? xhr.responseJSON.message : "Server Error";
                            recordFailure(`ID ${currentItem.id}`, errorMsg);
                            finishItem();
                        }
                    });
                }

                function finishItem() {
                    updateUI(currentIdx, total);
                    currentIdx++;
                    processNext();
                }

                processNext();
            }
        });

        
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
                            <strong>${item.serial} (${item.status})</strong>
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

        // Prevent the "Invalid JSON" alert from appearing to the user
        $.fn.dataTable.ext.errMode = 'none';

        let table = $('#funds-table').DataTable({
            "destroy": true,
            "processing": true,
            "serverSide": false,
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "ordering": true,
            "stateSave": false, 
            "order": [[0, 'desc']], 
            "dom": '<"row"<"col-md-6"B><"col-md-6"f>>rtip',
            "buttons": ["copy", "excel", "pdf", "print", "colvis"],
            "columnDefs": [
                { "targets": 0, "width": "120px", "className": "text-center", "type": "num" },
                { "targets": 1, "width": "80px" },
                
                // Use a conditional spread or a logic check for the rest
                { "targets": (isAdmin ? 2 : 2), "width": "100px" }, // Section (if admin) or Creditor (if not)
                
                // To be safe, target the LAST column (Action) using -1
                { 
                    "targets": -1, 
                    "width": "80px", 
                    "orderable": false,
                    "className": "text-center" 
                }
            ],
            "language": {
                "searchPlaceholder": "Search transactions...",
                "search": ""
            }
        });



        // Capture and log errors quietly instead of using alerts
        $('#funds-table').on('error.dt', function(e, settings, techNote, message) {
            console.error('DataTables Error:', message);
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
            $(this).removeAttr('aria-hidden');
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

                    // 2. Hide modal immediately
                    $('#addFundModal').modal('hide');

                    // 3. Show Success Toast
                    const message = isEdit ? 'Transaction updated successfully!' : 'Transaction ' + (fundData.dtrack_no || "") + ' logged successfully!';
                    $(document).Toasts('create', { 
                        class: 'bg-success', 
                        title: 'Success', 
                        autohide: true, 
                        delay: 2000, 
                        body: message 
                    });

                    // 4. Refresh the page after a short delay
                    // This ensures the user sees the toast and the backend relationships are perfectly rendered
                    setTimeout(() => { 
                        location.reload(); 
                    }, 1000);

                    // Everything else (UI building, DataTable row.add, etc.) is removed 
                    // because the refresh will handle the data display.
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

        $(document).on('click', '.btn-update-status', function(e) {
            e.preventDefault();
            
            const dtrack = $(this).data('dtrack');
            const id = $(this).data('id');
            $('.modal-title').html('<i class="fas fa-history mr-2 text-info"></i>Update Status: ' + dtrack);

            // 1. Force close and reset to prevent ghosting data
            $('#statusModal').modal('hide');
            $('#display_dtrack').text(dtrack);
            $('#modal_fund_id').val(id);
            
            // 2. Placeholder while loading
            $('#serial_inputs_container').html('<tr><td colspan="3" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');

            $.get(`/funds/group/${dtrack}`)
                .done(function(data) {
                    // Get today's date in YYYY-MM-DD format
                    const today = new Date().toISOString().split('T')[0];
                    let rows = '';

                    if (data.allocations && data.allocations.length > 0) {
                        const firstAlloc = data.allocations[0];
                        
                        $('#modal_status_select').val(firstAlloc.status);
                        $('#modal_remarks_input').val(firstAlloc.remarks || '');
                        
                        // 2. Fix the date formatting
                        if (firstAlloc.status === 'Obligated' && firstAlloc.status_date) {
                            // If the date contains a 'T', split it; otherwise use it as is
                            const cleanDate = firstAlloc.status_date.includes('T') 
                                ? firstAlloc.status_date.split('T')[0] 
                                : firstAlloc.status_date;
                                
                            $('#modal_status_date').val(cleanDate);
                        } else {
                            $('#modal_status_date').val(today);
                        }

                        // Build table rows
                        data.allocations.forEach((alloc, index) => {
                            rows += `
                            <tr>
                                <td><small class="font-weight-bold">${alloc.source_name}</small></td>
                                <td class="text-right"><small>₱${alloc.amount}</small></td>
                                <td>
                                    <input type="hidden" name="serials[${index}][id]" value="${alloc.id}">
                                    <input type="text" name="serials[${index}][serial_no]" 
                                        class="form-control form-control-sm serial-field" 
                                        value="${alloc.obligation_serial || ''}" 
                                        placeholder="Enter Serial No."
                                        ${firstAlloc.status === 'Obligated' ? 'required' : ''}>
                                </td>
                            </tr>`;
                        });
                        
                        $('#serial_inputs_container').html(rows);

                        // Initial visibility check based on loaded status
                        toggleSerialVisibility($('#modal_status_select').val());
                        
                    } else {
                        $('#serial_inputs_container').html('<tr><td colspan="3" class="text-center text-danger">No allocations found.</td></tr>');
                    }

                    $('#statusModal').modal('show');
                });
            });

        /**
         * Helper function to handle showing/hiding serial fields
         * Also linked to the 'change' event of the select dropdown
         */
        function toggleSerialVisibility(status) {
            if (status === 'Obligated') {
                $('#serial_no_section').fadeIn();
                $('.serial-field').attr('required', true);
            } else {
                $('#serial_no_section').hide();
                $('.serial-field').attr('required', false).val(''); // Clear serials if not obligated
            }
        }

        // Attach listener to the dropdown for real-time UI changes
        $('#modal_status_select').on('change', function() {
            toggleSerialVisibility($(this).val());
        });

        // Toggle Serial Section visibility
        $('#modal_status_select').on('change', function() {
            if ($(this).val() === 'Obligated') {
                $('#serial_no_section').fadeIn();
                $('.serial-field').attr('required', true);
            } else {
                $('#serial_no_section').fadeOut();
                $('.serial-field').attr('required', false);
            }
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
        $('.btn-add-new').on('click', function() {
            // 1. Reset the form and hidden fields
            $('#fund-form')[0].reset();
            $('#edit_fund_id').val('');
            $('#form_method').val('POST');
            
            // 2. Clear out any rows left over from an "Edit" session
            $('#allocation-body').empty();
            
            // 3. Reset the Select2 Creditor dropdown
            $('#creditor_select').val(null).trigger('change');

            // 4. Set the Title
            $('.modal-title').html('<i class="fas fa-plus-circle mr-2"></i> New Transaction Log');

            // 5. Add the first blank allocation row (index 0)
            addNewAllocationRow(0);

            // 6. Reset Grand Total display
            updateGrandTotal();

            // 7. Show the modal
            $('#addFundModal').modal('show');
        });

        $(document).on('click', '.btn-edit-transaction', function() {
            const id = $(this).data('id');
            
            // Use a fallback to 'routed' if no status is found to avoid unnecessary locking
            // Ensure we convert to lowercase for a reliable comparison
            const status = ($(this).attr('data-status') || 'routed').trim().toLowerCase();

            // 1. Safety check: Added 'processed' if you consider that editable, 
            // but kept your 'routed' and 'for signature' logic.
            const editableStatuses = ['routed', 'for signature', 'processed'];
            
            if (!editableStatuses.includes(status)) {
                $(document).Toasts('create', { 
                    class: 'bg-warning', 
                    title: '<i class="fas fa-lock mr-2"></i> Transaction Locked', 
                    body: 'This transaction has moved beyond the routing stage (' + status.toUpperCase() + ') and cannot be edited.' 
                });
                return false;
            }

            $.get("/funds/" + id + "/edit", function(data) {
                if (!data.success) return;

                const main = data.main;
                const allocations = data.allocations;

                // 2. Setup Form Basics
                $('#fund-form')[0].reset();
                $('#edit_fund_id').val(main.id);
                $('#form_method').val('PATCH');
                $('.modal-title').html('<i class="fas fa-edit mr-2 text-warning"></i>Edit Transaction: ' + main.dtrack_no);
                
                // 3. Global Fields
                $('#dtrack_input').val(main.dtrack_no);
                
                if (main.transaction_date) {
                    $('#transaction_date').val(main.transaction_date.split('T')[0]);
                }

                $('#particulars_input').val(main.particulars);

                if (data.creditor_ids) {
                    $('#creditor_select').val(data.creditor_ids).trigger('change');
                }

                // 4. Populate Allocations
                $('#allocation-body').empty(); 

                allocations.forEach((alloc, index) => {
                    addNewAllocationRow(index, alloc.id); // This calls your adder function
                    const row = $(`.allocation-row`).last();
                    
                    // If it's the first row being restored, hide its delete button
                    if (index === 0) {
                        row.find('.remove-row')
                        .prop('disabled', true)
                        .attr('title', 'Primary allocation cannot be removed') // Optional: Add a tooltip
                        .css('cursor', 'not-allowed'); // Optional: Visual cue for disabled state
                    }
                    
                    row.find('.source-select').val(alloc.source_of_fund_id).trigger('change');
                    row.find('.amount-field').val(alloc.amount);

                    // 5. Dependent Dropdown Sync
                    let checkExist = setInterval(function() {
                        const activitySelect = row.find('.activity-select');
                        if (activitySelect.find('option[value="' + alloc.transaction_type_id + '"]').length) {
                            activitySelect.val(alloc.transaction_type_id).removeAttr('disabled');
                            clearInterval(checkExist);
                            updateGrandTotal(); 
                        }
                    }, 100);
                    
                    setTimeout(() => clearInterval(checkExist), 3000);
                });

                $('#addFundModal').modal('show');
            }).fail(function() {
                toastr.error('Failed to retrieve transaction data.');
            });
        });

        //  For dynamically adding new allocation rows in the modal for Fund Sources and Amounts
        function addNewAllocationRow(index, rowId = '') {
            const html = `
            <tr class="allocation-row">
                <input type="hidden" name="allocations[${index}][id]" value="${rowId}">
                
                <td>
                    <select name="allocations[${index}][source_id]" class="form-control source-select" required>
                        <option value="">-- Select --</option>
                        @foreach($sources as $source)
                            <option value="{{ $source->id }}">{{ $source->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <select name="allocations[${index}][activity_id]" class="form-control activity-select" required disabled>
                        <option value="">-- Select Source First --</option>
                    </select>
                </td>
                <td>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                        <input type="number" name="allocations[${index}][amount]" class="form-control amount-field" step="0.01" required>
                    </div>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm remove-row" ${index === 0 && rowId === '' ? 'disabled' : ''}>
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
            $('#allocation-body').append(html);
        }

        

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

        // For viewing of transaction info
        $(document).on('click', '.view-dtrack', function (e) {
            e.preventDefault();
            const $link = $(this);
            const modal = $('#viewTransactionModal');

            // 1. Find the parent row
            // If the table is responsive (mobile view), the row might be a 'child' row
            let tr = $link.closest('tr');
            if (tr.hasClass('child')) { 
                tr = tr.prev(); 
            }

            // 2. Extract Data using Classes (NOT Indexes)
            // This ensures that even if 'Section' is missing, we get the right data.
            const dtrackNo = $link.text().trim();
            const date     = tr.find('.col-date').text().trim();
            const creditor = tr.find('.col-creditor').html(); // Using .html() to keep badges
            const source   = tr.find('.col-source').html();
            const activity = tr.find('.col-activity').html();
            const amount   = tr.find('.col-amount').html();
            const status   = tr.find('.col-status').html();

            // 3. Extract Data Attributes for Particulars and Remarks
            // We use .data() to get the 'e()' escaped strings from your Blade file
            const particulars = $link.data('particulars');
            const remarks     = $link.data('remarks');

            // 4. Update Modal Content
            modal.find('#view_dtrack').text(dtrackNo);
            modal.find('#v_date').text(date);
            modal.find('#v_creditors').html(creditor);
            modal.find('#v_source').html(source);
            modal.find('#v_activity').html(activity);
            modal.find('#v_amount').html(amount);
            modal.find('#v_status').html(status);

            // 5. Handle Particulars & Remarks Display
            // Using .text() for particulars to prevent XSS, .html() for remarks to allow <br>
            modal.find('#v_particulars').text(particulars || 'No particulars provided.');
            
            // Replace semicolons with line breaks for better readability in the modal
            const formattedRemarks = remarks ? remarks.replace(/; /g, '<br>') : 'No remarks provided.';
            modal.find('#v_remarks').html(formattedRemarks);

            // 6. Show the Modal
            modal.modal('show');
        });

        $('#statusModal').on('hidden.bs.modal', function () {
            // Reset the form values
            $(this).find('form').trigger('reset');
            // Clear the dynamic table
            $('#serial_inputs_container').empty();
            // Reset the DTrack text
            $('#display_dtrack').text('');
            // Remove modal backdrop if it gets stuck
            $('.modal-backdrop').remove();
            // Fix the body scrolling/padding issue
            $('body').removeClass('modal-open').css('padding-right', '');
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
    });

    $(document).ready(function() {
        let table = $('#funds-table').DataTable();

        $('#sectionFilter').on('change', function() {
            // Trim whitespace from the selected name
            let selectedName = $.trim($(this).val());
            let statusBadge = $('#filterStatus');

            if (selectedName === "") {
                table.column(2).search('').draw();
                
                statusBadge.removeClass('badge-primary').addClass('badge-light')
                        .html('<i class="fas fa-list-ul mr-1"></i> Showing all records');
            } else {
                // REMOVE the '^' and '$' anchors to allow for whitespace or internal HTML
                // We still escape the regex for safety
                let searchPattern = $.fn.dataTable.util.escapeRegex(selectedName);
                
                // Apply the search
                table.column(2)
                    .search(searchPattern, true, false)
                    .draw();

                // Update the UI Badge
                let count = table.page.info().recordsDisplay;
                
                if (count > 0) {
                    statusBadge.removeClass('badge-light badge-danger').addClass('badge-primary text-white')
                            .html(`<i class="fas fa-check-circle mr-1"></i> Found ${count} records for ${selectedName}`);
                } else {
                    // If it still shows 0, let's make the badge show a warning
                    statusBadge.removeClass('badge-light badge-primary').addClass('badge-danger text-white')
                            .html(`<i class="fas fa-exclamation-triangle mr-1"></i> 0 records found for ${selectedName}`);
                }
            }
        });
    });

    $(document).ready(function() {
        function updateCreditorStatus() {
            let isRequiredByAnyRow = false;

            // Loop through every activity select in the table
            $('.activity-select').each(function() {
                const $selectedOption = $(this).find("option:selected");
                
                // Only check if an option is actually selected
                if ($selectedOption.val()) {
                    const activityText = $selectedOption.text().toLowerCase();
                    
                    // Check against keywords
                    if (activityText.includes('salary') || 
                        activityText.includes('provision of tev') || 
                        activityText.includes('provision of plane tickets')) {
                        isRequiredByAnyRow = true;
                        return false; // Break the .each() loop early
                    }
                }
            });

            const $creditorSelect = $('#creditor_select');
            const $label = $creditorSelect.closest('.form-group').find('label');

            if (isRequiredByAnyRow) {
                // Enable and make required if at least one row matches keywords
                $creditorSelect.prop('disabled', false).attr('required', true);
                $label.addClass('required');
                $creditorSelect.closest('.form-group').css('opacity', '1');
            } else {
                // Disable and clear if NO rows match the keywords
                $creditorSelect.val(null).prop('disabled', true).attr('required', false);
                $label.removeClass('required');
                $creditorSelect.closest('.form-group').css('opacity', '0.6');
            }

            // Essential for Select2 to update its visual state
            $creditorSelect.trigger('change');
        }

        // 1. Listen for changes on any activity dropdown (existing or future rows)
        $(document).on('change', '.activity-select', function() {
            updateCreditorStatus();
        });

        // 2. Also trigger check when a row is removed 
        // (If the row requiring a creditor is deleted, we should disable the field)
        $(document).on('click', '.remove-row', function() {
            // Small delay to allow the row to be removed from DOM before checking
            setTimeout(updateCreditorStatus, 50);
        });
    });

    function updateGrandTotal() {
        let grandTotal = 0;

        // 1. Loop through every amount field
        $('.amount-field').each(function() {
            const $input = $(this);
            
            // LOGIC: If the field is readonly (due to zero balance logic), 
            // we exclude it from the grand total.
            if (!$input.prop('readonly')) {
                let value = parseFloat($input.val());
                if (!isNaN(value)) {
                    grandTotal += value;
                }
            }
        });

        // 2. Format and update the display
        $('#grand-total-display').text('₱ ' + grandTotal.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }));

        // 3. Trigger Global Validation
        // This ensures that if the total changes, the submit button checks its state
        if (typeof validateGlobalSubmit === "function") {
            validateGlobalSubmit();
        }
    }

    $(document).ready(function() {
        // 1. Establish the subfolder base path dynamically
        const APP_URL = "{{ url('/') }}";
        let rowCount = 1;

        /**
        * Sync logic to disable already selected activities
        * Only disables the activity if the Source is the same.
        */
        function syncDuplicateActivities() {
            let selectedPairs = [];

            // Map out all currently selected Source + Activity pairs
            $('.allocation-row').each(function() {
                const sourceId = $(this).find('.source-select').val();
                const activityId = $(this).find('.activity-select').val();
                if (sourceId && activityId) {
                    selectedPairs.push({ source: sourceId, activity: activityId });
                }
            });

            // Loop through every activity dropdown to update disabled states
            $('.allocation-row').each(function() {
                const $currentRow = $(this);
                const currentSource = $currentRow.find('.source-select').val();
                const currentActivity = $currentRow.find('.activity-select').val();
                const $activitySelect = $currentRow.find('.activity-select');

                $activitySelect.find('option').each(function() {
                    const $option = $(this);
                    const optionValue = $option.val();

                    if (!optionValue) return;

                    // Check if this specific Activity+Source combo is used in a DIFFERENT row
                    const isUsedElsewhere = selectedPairs.some(pair => 
                        pair.source === currentSource && 
                        pair.activity === optionValue && 
                        optionValue !== currentActivity
                    );

                    if (isUsedElsewhere) {
                        $option.prop('disabled', true).css('color', '#ccc');
                    } else {
                        // Only re-enable if it's not disabled by other logic (like zero balance)
                        if ($option.attr('data-depleted') !== 'true') {
                            $option.prop('disabled', false).css('color', '');
                        }
                    }
                });
            });
        }

        // --- Listeners ---

        $('#add-allocation-row').click(function() {
            // Check if the body is currently empty to determine if this is the "Primary" row
            const isFirstRow = $('#allocation-body .allocation-row').length === 0;
            
            let newRow = `
                <tr class="allocation-row">
                    <td>
                        <select name="allocations[${rowCount}][source_id]" class="form-control source-select" required>
                            <option value="">-- Select --</option>
                            @foreach($sources as $source)
                                <option value="{{ $source->id }}">{{ $source->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select name="allocations[${rowCount}][activity_id]" class="form-control activity-select" required disabled>
                            <option value="">-- Select Source First --</option>
                        </select>
                        <div class="budget-warning mt-1" style="min-height: 18px; font-size: 0.75rem;"></div>
                    </td>
                    <td>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                            <input type="number" name="allocations[${rowCount}][amount]" class="form-control amount-field" step="0.01" placeholder="0.00" required>
                        </div>
                    </td>
                    <td class="text-center">
                        <button type="button" 
                                class="btn btn-danger btn-sm remove-row" 
                                ${isFirstRow ? 'disabled style="cursor: not-allowed;" title="First row cannot be deleted"' : ''}>
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            
            // Append the full row
            $('#allocation-body').append(newRow);
            
            // Increment index and refresh UI states
            rowCount++;
            if (typeof updateGrandTotal === "function") updateGrandTotal();
            
            // Run the duplicate sync to ensure selections are locked correctly
            syncDuplicateActivities();
        });

        $(document).on('click', '.remove-row', function() {
            $(this).closest('tr').remove();
            
            // If only one row remains after deletion, disable its trash button
            if ($('#allocation-body .allocation-row').length === 1) {
                $('#allocation-body .allocation-row').find('.remove-row')
                    .prop('disabled', true)
                    .css('cursor', 'not-allowed');
            }

            if (typeof updateGrandTotal === "function") updateGrandTotal();
            syncDuplicateActivities();
        });

        // Handle Source Change
        $(document).on('change', '.source-select', function() {
            const sourceId = $(this).val();
            const $row = $(this).closest('tr');
            const activitySelect = $row.find('.activity-select');
            const warningLabel = $row.find('.budget-warning');

            warningLabel.html('').hide();
            $row.removeAttr('data-budget-valid');
            $row.find('.amount-field').removeClass('is-invalid');

            if (!sourceId) {
                activitySelect.html('<option value="">-- Select Source First --</option>').prop('disabled', true);
                syncDuplicateActivities();
                return;
            }

            activitySelect.prop('disabled', false).html('<option value="">Loading...</option>');
            
            // FIXED: Prefixed with the dynamic APP_URL variable to target the subfolder correctly
            $.get(`${APP_URL}/api/sources/${sourceId}/activities`, function(data) {
                let options = '<option value="">-- Select Activity --</option>';
                data.forEach(act => {
                    options += `<option value="${act.id}">${act.name}</option>`;
                });
                activitySelect.html(options);
                
                syncDuplicateActivities(); // Sync after new options load
                
                if ($row.find('.amount-field').val() > 0 && typeof checkRowBudgetBalance === "function") {
                    checkRowBudgetBalance($row);
                }
            });
        });

        // Handle Activity Change
        $(document).on('change', '.activity-select', function() {
            syncDuplicateActivities(); // Sync immediately when user picks an activity
            if (typeof checkRowBudgetBalance === "function") {
                checkRowBudgetBalance($(this).closest('tr'));
            }
        });

        // Initial sync call for Edit mode
        syncDuplicateActivities();
    });

    // 1. Get your existing DataTable instance
    var table = $('#funds-table').DataTable();
    var filterDropdown = $('#sectionFilter');
    var statusBadge = $('#filterStatus');

    if (filterDropdown.length) {
        // 2. When the dropdown changes, tell DataTables to filter the Section column (Column index 2)
        filterDropdown.on('change', function() {
            var selectedSection = $(this).val();
            // Search column 2 and redraw the table
            table.column(2).search(selectedSection).draw();
        });

        // 3. Update the badge ONLY when DataTables finishes drawing/filtering
        table.on('draw', function() {
            var info = table.page.info(); // Gets current table stats
            var selectedSection = filterDropdown.val();

            if (!selectedSection) {
                // If "Show All" is selected
                statusBadge.html(`<i class="fas fa-list-ul mr-1"></i> Showing all ${info.recordsTotal} records`);
                statusBadge.attr('class', 'badge badge-pill badge-light border text-muted py-2 px-3');
            } else {
                // If a specific section is selected, use info.recordsDisplay for the exact filtered count
                if (info.recordsDisplay === 0) {
                    statusBadge.html(`<i class="fas fa-exclamation-triangle mr-1"></i> Found 0 records for ${selectedSection}`);
                    statusBadge.attr('class', 'badge badge-pill badge-danger text-white py-2 px-3 shadow-sm');
                } else {
                    statusBadge.html(`<i class="fas fa-check mr-1 text-success"></i> Found ${info.recordsDisplay} records for ${selectedSection}`);
                    statusBadge.attr('class', 'badge badge-pill badge-primary text-white py-2 px-3 shadow-sm');
                }
            }
        });
    }

    $(document).ready(function() {
    
        // 1. Function to Initialize Select2 on missing activity dropdowns
        function initSelect2OnActivities() {
            $('.select2-activity').each(function() {
                if (!$(this).hasClass("select2-hidden-accessible")) {
                    $(this).select2({
                        placeholder: "-- Select COS Position / Activity --",
                        allowClear: true,
                        dropdownAutoWidth: true,
                        width: '100%'
                    });
                }
            });
        }

        // 2. Bind to DataTables Page Redraw Event
        if ($.fn.DataTable.isDataTable('#funds-table')) {
            let table = $('#funds-table').DataTable();
            table.on('draw', function () {
                initSelect2OnActivities();
            });
        }

        // Initial run for Page 1
        initSelect2OnActivities();

        // 3. AJAX Update Listener for Select2 Change
        $(document).on('change', '.select2-activity', function() {
            const select = $(this);
            const fundId = select.data('id');
            const selectedActivityId = select.val();
            const container = $(`.unassigned-container-${fundId}`);
            const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val();

            if (!selectedActivityId) return;

            select.prop('disabled', true);

            $.ajax({
                url: `/funds/${fundId}/update-transaction-type`,
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                data: {
                    _token: csrfToken,
                    transaction_type_id: selectedActivityId
                },
                success: function(response) {
                    if (response && response.success) {
                        const selectedText = select.find('option:selected').text();
                        
                        // Destroy Select2 instance before replacing HTML container
                        if (select.data('select2')) {
                            select.select2('destroy');
                        }

                        container.html(`
                            <span class="font-weight-bold text-dark animate__animated animate__fadeIn">
                                ${selectedText}
                            </span>
                            <i class="fas fa-check-circle text-success ml-1" title="Saved"></i>
                        `);
                    } else {
                        alert(response.message || 'Error assigning activity.');
                        select.prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'Server communication error.';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        errorMsg = Object.values(xhr.responseJSON.errors).flat().join('\n');
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }

                    alert(errorMsg);
                    select.prop('disabled', false);
                }
            });
        });
    });

    $(document).ready(function() {
        $(document).on('click', '.btn-edit-manual-remark', function(e) {
            e.preventDefault();

            const btn = $(this);
            const fundId = btn.data('id');
            const currentRemark = btn.data('current') || '';
            const csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val();

            // Use SweetAlert2 if available, fallback to prompt
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Update Internal Remark',
                    input: 'textarea',
                    inputLabel: 'Enter manual notes/remarks for this transaction:',
                    inputValue: currentRemark,
                    inputPlaceholder: 'Type your remarks here...',
                    showCancelButton: true,
                    confirmButtonText: 'Save Remark',
                    confirmButtonColor: '#001f3f',
                    preConfirm: (text) => {
                        return text;
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        saveManualRemark(fundId, result.value, csrfToken);
                    }
                });
            } else {
                const newRemark = prompt("Enter manual notes/remarks:", currentRemark);
                if (newRemark !== null) {
                    saveManualRemark(fundId, newRemark, csrfToken);
                }
            }
        });

        function saveManualRemark(fundId, remarkText, csrfToken) {
            $.ajax({
                url: `/funds/${fundId}/update-manual-remarks`,
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                data: {
                    _token: csrfToken,
                    manual_remarks: remarkText
                },
                success: function(response) {
                    if (response && response.success) {
                        const wrapper = $(`.manual-remarks-wrapper-${fundId}`);

                        if (response.manual_remarks) {
                            wrapper.html(`
                                <div class="text-dark small border-left border-primary pl-2 bg-light rounded py-1 pr-2 animate__animated animate__fadeIn">
                                    <i class="fas fa-user-edit mr-1 text-primary" style="font-size: 0.7rem;"></i> 
                                    <strong class="text-primary">Internal Remark:</strong> 
                                    <span class="manual-remarks-text-${fundId}">${response.manual_remarks}</span>
                                    <button class="btn btn-link btn-xs text-muted p-0 ml-1 btn-edit-manual-remark" data-id="${fundId}" data-current="${response.manual_remarks}" title="Edit Remark">
                                        <i class="fas fa-pencil-alt" style="font-size: 0.65rem;"></i>
                                    </button>
                                </div>
                            `);
                        } else {
                            wrapper.html(`
                                <button class="btn btn-xs btn-outline-secondary mt-1 btn-edit-manual-remark" data-id="${fundId}" data-current="" title="Add Manual Remark">
                                    <i class="fas fa-plus-circle mr-1"></i> Add Internal Remark
                                </button>
                            `);
                        }
                    } else {
                        alert(response.message || 'Error updating remark.');
                    }
                },
                error: function(xhr) {
                    alert(xhr.responseJSON ? xhr.responseJSON.message : 'Failed to save remark.');
                }
            });
        }
    });

</script>
@endsection