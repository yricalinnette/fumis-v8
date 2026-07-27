@extends('layouts.adminlte')

@section('content')
<div class="container-fluid pt-3">
    
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

    <div class="row">
        <div class="col-md-6">
            <div class="card card-info card-outline">
                <div class="card-header bg-white py-3">
                    <h3 class="card-title text-bold mb-0">
                        <i class="fas fa-user-shield mr-2 text-info"></i> Account Security Settings
                    </h3>
                </div>
                
                <form action="{{ route('settings.profile.password.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="card-body">
                        <div class="form-group">
                            <label>Account Username</label>
                            <input type="text" class="form-control" value="{{ auth()->user()->username }}" readonly style="background-color: #e9ecef;">
                            <small class="form-text text-muted">Usernames are locked to match payroll specifications.</small>
                        </div>
                        
                        <hr>

                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" name="current_password" id="current_password" class="form-control" placeholder="Verify your current identity password" required>
                        </div>

                        <div class="form-group">
                            <label for="password">New Strong Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="password" class="form-control" placeholder="Minimum 8 characters with letters/numbers" required>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" id="toggle_password">
                                        <i class="fas fa-eye" id="password_icon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password_confirmation">Confirm New Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" placeholder="Retype new password" required>
                        </div>
                    </div>

                    <div class="card-footer bg-white text-right">
                        <button type="submit" class="btn btn-info font-weight-bold px-4">
                            <i class="fas fa-save mr-2"></i> Update Password Credentials
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card bg-light shadow-none border">
                <div class="card-body">
                    <h5><i class="fas fa-info-circle text-info mr-1"></i> Data Integrity Policy</h5>
                    <p class="text-muted small">
                        Updating your local portal credentials changes verification sequences immediately across active operations. 
                    </p>
                    <ul class="text-muted small pl-3">
                        <li>Passwords require alphanumeric composition matching schema metrics.</li>
                        <li>Session context states reset gracefully post alteration across devices.</li>
                        <li>If you forget your active keys, approach an authorized <strong>Account Management</strong> Administrator.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    $(document).ready(function() {
        // Auto-hide alert handlers matching core settings pattern
        window.setTimeout(function() {
            $(".alert-success").fadeTo(500, 0).slideUp(500, function(){
                $(this).remove(); 
            });
        }, 5000);

        // View/Hide New Password String Toggle Logic
        $('#toggle_password').on('click', function() {
            let input = $('#password');
            let confirmInput = $('#password_confirmation');
            let icon = $('#password_icon');
            
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                confirmInput.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                input.attr('type', 'password');
                confirmInput.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });
    });
</script>
@endsection