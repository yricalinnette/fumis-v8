
                
<form action="{{ route('settings.realign') }}" method="POST" id="realignForm">
    @csrf
    <input type="hidden" name="source_id" value="{{ $source->id }}">
    
    <div class="card card-outline card-navy shadow-sm">
        <div class="card-header bg-light">
            <h3 class="card-title text-navy font-weight-bold">
                <i class="fas fa-edit mr-2"></i> Adjusting: {{ $source->name }}
            </h3>
            <div class="card-tools">
                <span class="badge badge-info">Total Fund: ₱{{ number_format($source->total_amount, 2) }}</span>
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-valign-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="pl-4">Activity</th>
                            <th class="text-right">Current Allotted</th>
                            <th class="text-right">Obligated (Locked)</th>
                            <th class="text-right">Savings/Unobligated</th>
                            <th class="text-right" width="250">New Adjusted Budget</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($source->activities as $activity)
                        @php
                            $obligated = $activity->funds()->sum('obligation_amount');
                            $currentBudget = $activity->budget_adjusted ?? $activity->budget;
                            $savings = $currentBudget - $obligated;
                        @endphp
                        <tr>
                            <td class="pl-4">
                                <span class="font-weight-bold d-block">{{ $activity->name }}</span>
                                <small class="text-muted">Original: ₱{{ number_format($activity->budget, 2) }}</small>
                            </td>
                            <td class="text-right text-muted">₱{{ number_format($currentBudget, 2) }}</td>
                            <td class="text-right text-danger font-italic">₱{{ number_format($obligated, 2) }}</td>
                            <td class="text-right text-success font-weight-bold">₱{{ number_format($savings, 2) }}</td>
                            <td class="pr-4">
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white">₱</span>
                                    </div>
                                    <input type="number" 
                                        name="adjustments[{{ $activity->id }}]" 
                                        class="form-control text-right realign-input font-weight-bold" 
                                        value="{{ $currentBudget }}" 
                                        data-min="{{ $obligated }}" 
                                        step="0.01">
                                </div>
                                <small class="text-xs float-right text-muted mt-1">Cannot be less than ₱{{ number_format($obligated, 2) }}</small>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-dark">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <div id="realign-calc-info">
                        <span class="text-uppercase small d-block opacity-75">Status</span>
                        <span id="realign-status-text" class="h5 font-weight-bold text-warning">Initializing...</span>
                    </div>
                </div>
                <div class="col-sm-6 text-right">
                    <button type="submit" id="realign-save-btn" class="btn btn-success" disabled>
                        <i class="fas fa-check-circle mr-1"></i> Apply Realignment
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
$(document).ready(function() {
    const sourceTotal = {{ $source->total_amount }};
    
    function validateRealignment() {
        let currentSum = 0;
        let hasError = false;

        $('.realign-input').each(function() {
            let val = parseFloat($(this).val()) || 0;
            let min = parseFloat($(this).data('min'));

            // Check if user tried to go below obligations
            if (val < min) {
                $(this).addClass('is-invalid');
                hasError = true;
            } else {
                $(this).removeClass('is-invalid');
            }
            currentSum += val;
        });

        // Round to 2 decimal places to prevent float precision issues
        let difference = (sourceTotal - currentSum).toFixed(2);

        if (hasError) {
            $('#realign-status-text').html('<i class="fas fa-times-circle text-danger"></i> Below Obligation Limit');
            $('#realign-save-btn').prop('disabled', true);
        } else if (difference == 0) {
            $('#realign-status-text').html('<i class="fas fa-check-circle text-success"></i> Balanced');
            $('#realign-save-btn').prop('disabled', false);
        } else if (difference > 0) {
            $('#realign-status-text').html('<i class="fas fa-exclamation-circle text-warning"></i> Under by ₱' + parseFloat(difference).toLocaleString());
            $('#realign-save-btn').prop('disabled', true);
        } else {
            $('#realign-status-text').html('<i class="fas fa-exclamation-triangle text-danger"></i> Over by ₱' + Math.abs(difference).toLocaleString());
            $('#realign-save-btn').prop('disabled', true);
        }
    }

    $('.realign-input').on('input change', validateRealignment);
    
    // Trigger initial calculation
    validateRealignment();
});
</script>