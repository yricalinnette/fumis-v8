@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow border-0">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-user-plus mr-2"></i> Grant System Access</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.users.store') }}" method="POST">
                        @csrf

                        <div class="form-group mb-4">
                            <label class="font-weight-bold">Select Employee (from HR Database)</label>
                            <select name="employee_id" id="employee_select" class="form-control select2" required>
                                <option value="">-- Search by Name or Designation --</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->empid }}" data-email="{{ $emp->email }}">
                                        {{ $emp->empid }} - {{ $emp->dbdesignation }} ({{ $emp->dbstatustype }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Only showing employees who do not have an account yet.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold">Full Name</label>
                                <input type="text" name="name" class="form-control" placeholder="Enter display name" required>
                            </div>

                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold">Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="admin@example.com" required>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>

                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold">Access Level</label>
                                <select name="is_admin" class="form-control">
                                    <option value="0">Standard User</option>
                                    <option value="1">Administrator</option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.users.index') }}" class="btn btn-light border">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save mr-1"></i> Create User Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // Initialize Select2 for the searchable dropdown
        $('.select2').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Search for an employee...'
        });
    });
</script>
@endsection