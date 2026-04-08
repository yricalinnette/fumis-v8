<form action="{{ route('settings.realign') }}" method="POST" id="realignForm">
    @csrf
    <input type="hidden" name="source_id" value="{{ $source->id }}">
    
    <div class="card card-outline card-navy shadow-sm">
        <div class="card-header bg-light">
            <h3 class="card-title text-navy font-weight-bold">
                <i class="fas fa-edit mr-2"></i> Adjusting: {{ $source->name }}
            </h3>
            <div class="card-tools">
                <span class="badge badge-info p-2">Total Fund: ₱{{ number_format($source->total_amount, 2) }}</span>
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-valign-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="pl-4">Activity</th>
                            <th class="text-right">Current Allotted</th>
                            <th class="text-right text-orange">Pending Transactions (Routed)</th>
                            <th class="text-right text-danger">Obligated</th>
                            <th class="text-right text-info">Pooled</th>
                            <th class="text-right">Available</th>
                            <th class="text-right" width="200">New Adjusted Budget</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($source->activities as $activity)
                        @php
                            // 1. Transactions already officially obligated
                            $obligatedSum = $activity->transactions()->sum('obligation_amount') ?? 0;
                            
                            // 2. Transactions routed/processed but NOT YET obligated
                            // We use 'amount' from the funds table for these
                            $pendingSum = $activity->transactions()
                                ->where(function($q) {
                                    $q->whereNull('obligation_amount')
                                      ->orWhere('obligation_amount', 0);
                                })
                                ->sum('amount') ?? 0;

                            // 3. Savings/Pooled
                            $pooledAmount = $activity->pooled_amount ?? 0;

                            // 4. THE ABSOLUTE FLOOR: 
                            // Current Budget cannot go below (Obligated + Pending + Pooled)
                            $totalLocked = $obligatedSum + $pendingSum + $pooledAmount;

                            $currentBudget = $activity->budget_adjusted ?? $activity->budget;
                            $availableForRealignment = $currentBudget - $totalLocked;
                        @endphp
                        <tr>
                            <td class="pl-4">
                                <span class="font-weight-bold d-block">{{ $activity->name }}</span>
                                <small class="text-muted">Original: ₱{{ number_format($activity->budget, 2) }}</small>
                            </td>
                            
                            <td class="text-right text-muted">
                                ₱{{ number_format($currentBudget, 2) }}
                            </td>

                            <td class="text-right text-orange">
                                <span class="font-weight-bold">₱{{ number_format($pendingSum, 2) }}</span>
                                <br><small class="text-xs">Unobligated</small>
                            </td>

                            <td class="text-right text-danger">
                                <span class="font-weight-bold">₱{{ number_format($obligatedSum, 2) }}</span>
                            </td>

                            <td class="text-right text-info">
                                <span class="font-weight-bold">₱{{ number_format($pooledAmount, 2) }}</span>
                            </td>

                            <td class="text-right {{ $availableForRealignment > 0 ? 'text-success' : 'text-muted' }} font-weight-bold">
                                ₱{{ number_format($availableForRealignment, 2) }}
                            </td>

                            <td class="pr-4">
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white">₱</span>
                                    </div>
                                    <input type="text" 
                                        name="adjustments[{{ $activity->id }}]" 
                                        class="form-control text-right realign-input font-weight-bold {{ $availableForRealignment < 0 ? 'is-invalid' : '' }}" 
                                        value="{{ number_format($currentBudget, 2, '.', ',') }}" 
                                        data-min="{{ $totalLocked }}" 
                                        placeholder="0.00">
                                </div>
                                <small class="text-xs float-right text-muted mt-1">
                                    Min. Limit: ₱{{ number_format($totalLocked, 2) }}
                                </small>
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
                        <span class="text-uppercase small d-block opacity-75">Budget Balance Status</span>
                        <span id="realign-status-text" class="h5 font-weight-bold text-warning">Initializing...</span>
                    </div>
                </div>
                <div class="col-sm-6 text-right">
                    <button type="submit" id="realign-save-btn" class="btn btn-success px-4" disabled>
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
    
    // Helper function to add commas
    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // Helper to strip commas for calculation
    function cleanValue(val) {
        return parseFloat(val.replace(/,/g, '')) || 0;
    }

    function validateRealignment() {
        let currentSum = 0;
        let hasError = false;

        $('.realign-input').each(function() {
            let rawVal = $(this).val();
            let val = cleanValue(rawVal);
            let min = parseFloat($(this).data('min'));

            if (val < min) {
                $(this).addClass('is-invalid');
                hasError = true;
            } else {
                $(this).removeClass('is-invalid');
            }
            currentSum += val;
        });

        let difference = (sourceTotal - currentSum).toFixed(2);

        if (hasError) {
            $('#realign-status-text').html('<i class="fas fa-times-circle text-danger"></i> Below Obligation Limit');
            $('#realign-save-btn').prop('disabled', true);
        } else if (Math.abs(difference) < 0.01) { // Handing float precision
            $('#realign-status-text').html('<i class="fas fa-check-circle text-success"></i> Balanced');
            $('#realign-save-btn').prop('disabled', false);
        } else if (difference > 0) {
            $('#realign-status-text').html('<i class="fas fa-exclamation-circle text-warning"></i> Under by ₱' + numberWithCommas(parseFloat(difference).toFixed(2)));
            $('#realign-save-btn').prop('disabled', true);
        } else {
            $('#realign-status-text').html('<i class="fas fa-exclamation-triangle text-danger"></i> Over by ₱' + numberWithCommas(Math.abs(difference).toFixed(2)));
            $('#realign-save-btn').prop('disabled', true);
        }
    }

    // Handle typing and formatting
    $(document).on('input', '.realign-input', function() {
        // Allow only numbers and decimal point while typing
        let cursorPosition = this.selectionStart;
        let originalLength = this.value.length;
        
        let value = this.value.replace(/[^0-9.]/g, '');
        let parts = value.split('.');
        
        // Prevent multiple decimal points
        if (parts.length > 2) value = parts[0] + '.' + parts.slice(1).join('');
        
        this.value = value;
        validateRealignment();
    });

    // Format with commas when user finishes typing (leaves the field)
    $(document).on('blur', '.realign-input', function() {
        let val = cleanValue($(this).val());
        $(this).val(val.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    });

    // Strip commas when user clicks back into the field to edit
    $(document).on('focus', '.realign-input', function() {
        let val = $(this).val().replace(/,/g, '');
        if(parseFloat(val) === 0) val = '';
        $(this).val(val);
    });

    validateRealignment();
});
</script>