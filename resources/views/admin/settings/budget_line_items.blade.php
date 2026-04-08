@extends('layouts.adminlte')

@section('content')
<div class="container-fluid">

    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0 text-dark">Budget Line Item Settings</h1>
        </div>
    </div>

    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header bg-white py-3">
            <h3 class="card-title font-weight-bold">
                <i class="fas fa-list-alt mr-2 text-primary"></i> Budget Line Item Registry
            </h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" data-toggle="modal" data-target="#modalAddLineItem">
                    <i class="fas fa-plus-circle mr-1"></i> Add New Line Item
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th style="width: 10%">ID</th>
                        <th style="width: 60%">Line Item Name</th>
                        <th style="width: 15%" class="text-center">Status</th>
                        <th style="width: 15%" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td class="text-muted">#{{ str_pad($item->id, 3, '0', STR_PAD_LEFT) }}</td>
                            <td><span class="font-weight-bold text-dark">{{ $item->budget_line_item_name }}</span></td>
                            <td class="text-center">
                                <span class="badge {{ $item->is_active ? 'badge-success' : 'badge-secondary' }} px-3">
                                    {{ $item->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-right">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-default border shadow-sm" data-toggle="modal" data-target="#editModal{{ $item->id }}">
                                        <i class="fas fa-edit text-info"></i>
                                    </button>
                                    
                                    <form action="{{ route('settings.budget_line_items.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Delete this item?')" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-default border shadow-sm">
                                            <i class="fas fa-trash text-danger"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <div class="modal fade" id="editModal{{ $item->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header bg-info text-white">
                                        <h5 class="modal-title"><i class="fas fa-edit mr-2"></i> Edit Budget Line Item</h5>
                                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <form action="{{ route('settings.budget_line_items.update', $item->id) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-body text-left">
                                            <div class="form-group">
                                                <label class="font-weight-bold">Budget Line Item Name <span class="text-danger">*</span></label>
                                                <input type="text" name="budget_line_item_name" class="form-control" value="{{ $item->budget_line_item_name }}" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label class="font-weight-bold">Status</label>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" id="switch{{ $item->id }}" name="is_active" value="1" {{ $item->is_active ? 'checked' : '' }}>
                                                    <label class="custom-control-label font-weight-normal" for="switch{{ $item->id }}">Mark as Active</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-light">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-info shadow-sm">
                                                <i class="fas fa-save mr-1"></i> Update Changes
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr><td colspan="4" class="text-center py-4">No items found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- MODAL: ADD NEW BUDGET LINE ITEM --}}
<div class="modal fade" id="modalAddLineItem" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i> Add Budget Line Item</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('settings.budget_line_items.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold">Budget Line Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="budget_line_item_name" class="form-control" placeholder="e.g. HIT, DPC" required>
                        <small class="text-muted text-italic">This name will be used to group various fund sources.</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="font-weight-bold">Status</label>
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" checked value="1">
                            <label class="custom-control-label font-weight-normal" for="is_active">Mark as Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary shadow-sm">
                        <i class="fas fa-save mr-1"></i> Save Line Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // We use $(document).on to ensure the listener works even after page interactions
    $(document).on('click', '.open-edit-modal', function() {
        // 1. Pull data from the clicked button
        var editUrl = $(this).data('url');
        var itemName = $(this).data('name');
        var isActive = $(this).data('active');

        // 2. Select the specific Edit Form and Inputs
        var form = $('#formToEdit');
        var nameInput = $('#input_name_to_edit');
        var activeSwitch = $('#input_active_to_edit');

        // 3. APPLY THE FIX: Update the form action to include the ID
        form.attr('action', editUrl);

        // 4. Fill the data
        nameInput.val(itemName);
        activeSwitch.prop('checked', isActive == 1);
        
        // Debugging: If the modal opens but data is empty, check this console log (F12)
        console.log("Setting URL to: " + editUrl);
    });
});
</script>
@endsection