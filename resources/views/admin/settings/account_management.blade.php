@extends('layouts.adminlte')

@section('content')
<div class="container-fluid">
    {{-- Global Feedback Section --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <h5><i class="icon fas fa-check-circle"></i> Success!</h5>
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('error') || $errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <h5><i class="icon fas fa-exclamation-triangle"></i> System Notice</h5>
            <ul class="mb-0">
                @if(session('error'))
                    <li>{{ session('error') }}</li>
                @endif
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
    <div class="card card-primary card-outline card-tabs">
        <div class="card-header p-0 pt-1 border-bottom-0">
            <ul class="nav nav-tabs" id="settingsCustomTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="tabs-users-tab" data-toggle="pill" href="#tabs-users" role="tab">
                        <i class="fas fa-users mr-1"></i> Existing Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tabs-register-tab" data-toggle="pill" href="#tabs-register" role="tab">
                        <i class="fas fa-user-plus mr-1"></i> Register New Users
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="card-body">
            <div class="tab-content" id="settingsCustomTabContent">
                
                {{-- TAB 1: EXISTING USERS TABLE --}}
                <div class="tab-pane fade show active" id="tabs-users" role="tabpanel">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Position</th>
                                <th>Section</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                            <tr>
                                <td>
                                    {{ $user->name }}
                                </td>
                                <td>
                                    <code class="text-primary">{{ $user->username }}</code>
                                </td>
                                <td>
                                    {{-- Accesses db_common via the model shortcuts --}}
                                    {{ $user->position_name }}
                                </td>
                                <td>
                                    {{ $user->section_name }}
                                </td>
                                <td class="text-center">
                                    @if($user->is_active == 1)
                                        <span class="badge badge-success px-2">Active</span>
                                    @else
                                        <span class="badge badge-danger px-2">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        {{-- Edit Button (Triggers Modal) --}}
                                        <button type="button" class="btn btn-sm btn-info edit-user-btn" 
                                                data-id="{{ $user->id }}" 
                                                data-username="{{ $user->username }}"
                                                title="Edit User">
                                            <i class="fas fa-user-edit"></i>
                                        </button>

                                        {{-- Toggle Status Button --}}
                                        <form action="{{ route('users.toggle-status', $user->id) }}" 
                                            method="POST" 
                                            class="d-inline status-toggle-form" 
                                            id="status-form-{{ $user->id }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="button" 
                                                    class="btn btn-sm {{ $user->is_active == 1 ? 'btn-warning' : 'btn-success' }} btn-toggle-status" 
                                                    data-id="{{ $user->id }}"
                                                    data-name="{{ $user->name }}"
                                                    data-status="{{ $user->is_active == 1 ? 'deactivate' : 'activate' }}"
                                                    title="{{ $user->is_active == 1 ? 'Deactivate' : 'Activate' }}">
                                                <i class="fas {{ $user->is_active == 1 ? 'fa-user-slash' : 'fa-user-check' }}"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                    
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- TAB 2: REGISTRATION FORM --}}
                <div class="tab-pane fade" id="tabs-register" role="tabpanel">
                    <div class="row">
                        <div class="col-md-7">
                            <form action="{{ route('register.employee') }}" method="POST">
                                @csrf
                                <div class="form-group">
                                    <label><i class="fas fa-search mr-1 text-primary"></i> Search Employee</label>
                                    <select name="empid" id="employee_select" class="form-control select2" required style="width: 100%;">
                                        <option value="">-- Start typing name --</option>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Username</label>
                                            <input type="text" name="username" 
                                                class="form-control @error('username') is-invalid @enderror" 
                                                value="{{ old('username') }}" id="username" readonly>
                                            @error('username')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Password</label>
                                            <div class="input-group">
                                                <input type="password" name="password" 
                                                    class="form-control @error('password') is-invalid @enderror" 
                                                    id="password" required>
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary" type="button" id="toggle_password">
                                                        <i class="fas fa-eye" id="password_icon"></i>
                                                    </button>
                                                </div>
                                                @error('password')
                                                    <span class="invalid-feedback">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="custom-control custom-checkbox mb-3">
                                    <input type="checkbox" class="custom-control-input" id="use_default_password">
                                    <label class="custom-control-label" for="use_default_password">Use Default (Zaq12wsx)</label>
                                </div>

                                <div class="form-group">
                                    <label><i class="fas fa-user-shield mr-1 text-primary"></i> Access Role</label>
                                    <select name="role" id="role" class="form-control" required style="width: 100%;">
                                        <option value="staff" selected>Section Staff (Default)</option>
                                        <option value="division">Division Access (Views connected sections under Division)</option>
                                        <option value="budget">Budget Unit Access</option>
                                        <option value="admin">System Administrator</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-primary">Create Account</button>
                            </form>
                        </div>

                        {{-- PREVIEW CARD --}}
                        <div class="col-md-5">
                            <div id="preview_placeholder" class="text-center p-5 border" style="border-style: dashed !important;">
                                <i class="fas fa-id-card fa-4x mb-3 text-muted"></i>
                                <p class="text-muted">Employee preview will appear here</p>
                            </div>

                            <div id="preview_card" class="card card-widget widget-user-2 shadow-sm" style="display: none;">
                                <div class="widget-user-header bg-info">
                                    <div class="widget-user-image">
                                        <div id="preview_initials" class="img-circle elevation-2 d-flex align-items-center justify-content-center bg-white text-info font-weight-bold" style="width: 65px; height: 65px; font-size: 24px; float: left;">--</div>
                                    </div>
                                    <div class="ml-5 pl-4">
                                        <h3 id="preview_name" class="widget-user-username font-weight-bold" style="margin-left: 15px;"></h3>
                                        <h5 id="preview_position" class="widget-user-desc" style="margin-left: 15px;"></h5>
                                    </div>
                                </div>
                                <div class="card-footer p-0">
                                    <ul class="nav flex-column">
                                        <li class="nav-item"><span class="nav-link">Section: <span id="preview_section" class="float-right badge bg-primary"></span></span></li>
                                        <li class="nav-item"><span class="nav-link">Division: <span id="preview_division" class="float-right badge bg-success"></span></span></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> {{-- End Tab 2 --}}

            </div>
        </div>
    </div>
</div>

{{-- edit Modal --}}
<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editUserForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header bg-info">
                    <h5 class="modal-title">Edit User Account</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" id="display_username" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label>New Password (Leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter new password">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="password_confirmation" class="form-control" placeholder="Confirm new password">
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
        
        const defaultPass = "Zaq12wsx";

        // --- ADD THIS FUNCTION HERE ---
        function clean(str) {
            if (!str) return "";
            return str.toString()
                .trim()
                .normalize('NFD')               // Break characters into base + accent (e.g., ñ -> n + ~)
                .replace(/[\u0300-\u036f]/g, "") // Remove the accent marks
                .replace(/[^\w\s-]/g, "");       // Remove any other non-alphanumeric characters
        }

        // Initialize Select2
        $('#employee_select').select2({
            theme: 'bootstrap4',
            ajax: {
                url: "{{ route('employees.external.search') }}",
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term }),
                processResults: data => ({ results: data })
            }
        });

        // Handle Selection
        $('#employee_select').on('select2:select', function (e) {
            let data = e.params.data;
            
            // Use the clean function to prevent the ReferenceError
            let cleanFname = clean(data.fname);
            let cleanLname = clean(data.lname);
            let cleanMname = clean(data.mname);

            // --- IMPROVED USERNAME GENERATION ---
            // Takes first letter of each word in first name (e.g., "Juan Carlo" -> "jc")
            let fInitials = cleanFname.split(/\s+/).map(n => n[0]).join('').toLowerCase();
            
            // Takes middle initial if it exists
            let mInitial = cleanMname ? cleanMname[0].toLowerCase() : '';
            
            // Removes spaces from last name (e.g., "Dela Cruz" -> "delacruz")
            let lName = cleanLname.replace(/\s+/g, '').toLowerCase();

            let generatedUsername = fInitials + mInitial + lName;
            $('#username').val(generatedUsername); // Target ID directly

            // Fetch extra details for the Preview Card
            $.get("{{ url('settings/employees/details') }}/" + data.id, function(res) {
                $('#preview_placeholder').hide();
                $('#preview_card').fadeIn();
                $('#preview_name').text(res.name);
                $('#preview_position').text(res.position);
                $('#preview_section').text(res.section);
                $('#preview_division').text(res.division);
                
                // Set initials for the circle icon
                let initials = (cleanFname.charAt(0) + cleanLname.charAt(0)).toUpperCase();
                $('#preview_initials').text(initials);
            });
        });

        // Password Toggle Logic
        $('#use_default_password').on('change', function() {
            let isChecked = $(this).is(':checked');
            $('#password').val(isChecked ? defaultPass : '').prop('readonly', isChecked);
            $('#password').attr('type', isChecked ? 'text' : 'password');
        });

        // View/Hide Password Eye Icon Logic
        $('#toggle_password').on('click', function() {
            let input = $('#password');
            let icon = $('#password_icon');
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                input.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });

        $(document).on('select2:open', function() {
            setTimeout(() => {
                document.querySelector('.select2-search__field').focus();
            }, 10);
        });

        $('.edit-user-btn').on('click', function() {
            const id = $(this).data('id');
            const username = $(this).data('username');

            // Set values in modal
            $('#display_username').val(username);
            
            // Update form action URL dynamically
            const url = "{{ url('settings/users') }}/" + id;
            $('#editUserForm').attr('action', url);

            $('#editUserModal').modal('show');
        });

        $('.btn-toggle-status').on('click', function(e) {
            e.preventDefault();
            
            let userId = $(this).data('id');
            let userName = $(this).data('name');
            let action = $(this).data('status'); // 'activate' or 'deactivate'
            let form = $('#status-form-' + userId);
            
            let confirmButtonColor = action === 'deactivate' ? '#ffc107' : '#28a745';
            let actionText = action === 'deactivate' ? 'Deactivate' : 'Activate';

            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to ${action} the account for ${userName}.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: confirmButtonColor,
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Yes, ${actionText} it!`,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit(); // Only submits if user clicks the confirm button
                }
            });
        });
        
    });
</script>
@endsection