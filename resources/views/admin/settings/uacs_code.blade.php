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
    .action-btn-group { display: flex; justify-content: center; gap: 8px; }
    .btn-action { background-color: #ffffff; border: 1.5px solid; border-radius: 6px; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease-in-out; padding: 0; cursor: pointer; }
    .btn-edit { color: #17a2b8; border-color: #17a2b8; }
    .btn-edit:hover { background-color: #17a2b8; color: #ffffff; box-shadow: 0 4px 8px rgba(23, 162, 184, 0.2); transform: translateY(-1px); }
    .btn-delete { color: #dc3545; border-color: #dc3545; }
    .btn-delete:hover { background-color: #dc3545; color: #ffffff; box-shadow: 0 4px 8px rgba(220, 53, 69, 0.2); transform: translateY(-1px); }

    /* Ensure the form doesn't add bottom margin */
    .card-tools form {
        margin-bottom: 0;
    }

    /* Perfecting the Search Bar Group */
    .uacs-search-group {
        width: 220px;
        height: 31px; /* Matches the button height */
    }

    .uacs-search-group .form-control {
        height: 31px !important;
        font-size: 0.85rem;
        border-radius: 4px 0 0 4px !important;
        border-right: none;
    }

    .btn-search-icon {
        background: #fff;
        border: 1px solid #ced4da;
        border-left: none;
        color: #333;
        padding: 0 10px;
        height: 31px;
        display: flex;
        align-items: center;
        border-radius: 0 4px 4px 0 !important;
    }

    .btn-search-icon:hover {
        background: #f8f9fa;
        color: #007bff;
    }

    /* Vertical fix for the "Add New Code" button text */
    .btn-primary.rounded-pill {
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
</style>

<div class="container-fluid">
    {{-- Notifications --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible shadow-sm">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <i class="icon fas fa-check"></i> {{ session('success') }}
        </div>
    @endif
    
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible shadow-sm">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <ul class="mb-0">@foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
        </div>
    @endif

    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0 text-dark">UACS Code Settings</h1>
        </div>
    </div>

    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="card-title font-weight-bold text-dark mb-0">UACS Code Registry</h3>
                
                <div class="card-tools d-flex align-items-center">
                    {{-- Search Bar --}}
                    <form action="{{ route('settings.uacs_codes') }}" method="GET" class="mb-0 mr-2">
                        <div class="input-group uacs-search-group">
                            <input type="text" name="search" class="form-control" placeholder="Search Code/Title..." value="{{ request('search') }}">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-search-icon">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>

                    {{-- Add Button --}}
                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm d-flex align-items-center" data-toggle="modal" data-target="#modal-add-uacs" style="height: 31px;">
                        <i class="fas fa-plus-circle mr-1"></i> Add New Code
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="uacsTable" class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th>UACS Code</th>
                            <th>Account Title</th>
                            <th class="text-center">Allotment Class</th> 
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($uacs as $item)
                            <tr>
                                <td class="font-weight-bold">{{ $item->uacs_code }}</td>
                                <td>{{ $item->account_title }}</td>
                                <td class="text-center">
                                    @php
                                        $classColors = [
                                            'PS' => 'badge-primary',
                                            'MOOE' => 'badge-success',
                                            'CO' => 'badge-danger',
                                            'FinEx' => 'badge-warning'
                                        ];
                                        $color = $classColors[$item->allotment_class] ?? 'badge-secondary';
                                    @endphp
                                    <span class="badge {{ $color }}">{{ $item->allotment_class ?? 'N/A' }}</span>
                                </td>
                                <td class="text-center align-middle">
                                    <div class="action-btn-group">
                                        <button type="button" class="btn-action btn-edit edit-uacs-btn" 
                                            data-id="{{ $item->id }}"
                                            data-code="{{ $item->uacs_code }}"
                                            data-title="{{ $item->account_title }}"
                                            data-class="{{ $item->allotment_class }}"
                                            data-toggle="tooltip" title="Edit UACS">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn-action btn-delete delete-uacs-btn" 
                                            data-id="{{ $item->id }}"
                                            data-title="{{ $item->account_title }}"
                                            data-toggle="tooltip" title="Delete UACS">
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

        <div class="card-footer bg-white border-top">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $uacs->firstItem() }} to {{ $uacs->lastItem() }} of {{ $uacs->total() }} entries
                </div>
                <div class="pagination-sm">
                    {{ $uacs->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODALS --}}

{{-- Add Modal --}}
<div class="modal fade" id="modal-add-uacs" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold">Add New UACS Code</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('settings.uacs_codes.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label>Allotment Classification</label>
                        <select name="allotment_class" class="form-control" required>
                            <option value="" selected disabled>Select Class...</option>
                            <option value="PS">Personnel Services (PS)</option>
                            <option value="MOOE">Maintenance and Other Operating Expenses (MOOE)</option>
                            <option value="CO">Capital Outlay (CO)</option>
                            <option value="FinEx">Financial Expenses (FinEx)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>UACS Code</label>
                        <input type="text" name="uacs_code" class="form-control uacs-mask" placeholder="e.g. 5020301000" required>
                    </div>
                    <div class="form-group">
                        <label>Account Title</label>
                        <input type="text" name="account_title" class="form-control" placeholder="e.g. Office Supplies Expenses" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Save UACS</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Modal --}}
<div class="modal fade" id="modal-edit-uacs" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title font-weight-bold">Edit UACS Code</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="edit-uacs-form" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="form-group">
                        <label>Allotment Classification</label>
                        <select name="allotment_class" id="edit_allotment_class" class="form-control" required>
                            <option value="" selected disabled>Select Class...</option>
                            <option value="PS">Personnel Services (PS)</option>
                            <option value="MOOE">Maintenance and Other Operating Expenses (MOOE)</option>
                            <option value="CO">Capital Outlay (CO)</option>
                            <option value="FinEx">Financial Expenses (FinEx)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>UACS Code</label>
                        <input type="text" id="edit_uacs_code" name="uacs_code" class="form-control uacs-mask" required>
                    </div>
                    <div class="form-group">
                        <label>Account Title</label>
                        <input type="text" id="edit_account_title" name="account_title" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info px-4 text-white">Update Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Delete Modal --}}
<div class="modal fade" id="modal-delete-uacs" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title font-weight-bold">Confirm Deletion</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="delete-uacs-form" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-body text-center py-4">
                    <i class="fas fa-trash-alt text-danger fa-3x mb-3"></i>
                    <p>Are you sure you want to delete <br><strong id="uacsTitleDisplay" class="text-danger"></strong>?</p>
                </div>
                <div class="modal-footer bg-light justify-content-between">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">Delete Permanently</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();

        // 1. Edit UACS Handler
        $(document).on('click', '.edit-uacs-btn', function() {
            const btn = $(this);
            const id = btn.data('id');
            
            $('#edit-uacs-form').attr('action', `/settings/uacs_codes/${id}`);
            $('#edit_uacs_code').val(btn.data('code'));
            $('#edit_account_title').val(btn.data('title'));
            $('#edit_allotment_class').val(btn.data('class')).trigger('change');
            
            $('#modal-edit-uacs').modal('show');
        });

        // 2. Delete UACS Handler
        $(document).on('click', '.delete-uacs-btn', function() {
            const btn = $(this);
            const id = btn.data('id');
            
            $('#uacsTitleDisplay').text(btn.data('title'));
            $('#delete-uacs-form').attr('action', `/settings/uacs_codes/${id}`);
            
            $('#modal-delete-uacs').modal('show');
        });

        // 3. Auto-hide alerts
        window.setTimeout(function() {
            $(".alert").fadeTo(500, 0).slideUp(500, function(){
                $(this).remove(); 
            });
        }, 5000);

        // 4. Numeric only for UACS code
        $(document).on('input', '.uacs-mask', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        $('input[name="search"]').on('keyup', function() {
            if($(this).val().length > 2 || $(this).val().length == 0) {
                $(this).closest('form').submit();
            }
        });
    });
</script>