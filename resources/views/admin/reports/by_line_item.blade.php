@extends('layouts.adminlte')

@section('content')
<style>
    .table-sticky thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; box-shadow: inset 0 -1px 0 #dee2e6; }
    .financial-number { font-family: 'Courier New', Courier, monospace; font-weight: 600; }
    .card-navy.card-outline { border-top: 3px solid #001f3f; }
    .bg-navy-light { background-color: #f4f6f9; color: #001f3f; }
    .border-left-info { border-left: 4px solid #17a2b8 !important; }
    .border-left-success { border-left: 4px solid #28a745 !important; }
    .border-left-primary { border-left: 4px solid #007bff !important; } 
    @media print { .filter-section { display: none; } }
</style>

<div class="container-fluid">
    <div class="row pt-3 mb-2">
        <div class="col-12 d-flex justify-content-between align-items-end">
            <div>
                <h4 class="text-navy mb-0 font-weight-bold">
                    <i class="fas fa-chart-line mr-2 text-muted"></i>Line Item Budget Report
                </h4>
                <p class="text-muted small mb-0">
                    Financial Performance for Fiscal Year <strong>{{ request('year', date('Y')) }}</strong>
                    @if(request('month'))
                        | Month: <strong>{{ date('F', mktime(0, 0, 0, request('month'), 1)) }}</strong>
                    @elseif(request('quarter'))
                        | Quarter: <strong>Q{{ request('quarter') }}</strong>
                    @endif
                </p>
                
                <div class="mt-2 d-flex align-items-center text-xs text-muted">
                    <i class="fas fa-info-circle mr-1 text-info"></i>
                    <span>
                        Filtering is based on <strong>Obligation Date</strong>. 
                        Pending transactions are consolidated for the current FY.
                    </span>
                </div>
            </div>
            <div class="btn-group shadow-sm">
                <button class="btn btn-sm btn-default" data-toggle="collapse" data-target="#filterCard">
                    <i class="fas fa-filter mr-1"></i> Filters
                </button>
                <button class="btn btn-sm btn-default" onclick="$('.card').CardWidget('expand')">
                    <i class="fas fa-expand-alt"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="collapse show filter-section" id="filterCard">
        <div class="card shadow-sm mb-4 border-navy filter-section">
            <div class="card-body py-3">
                <form action="{{ route('reports.by_line_item') }}" method="GET" class="row align-items-end">
                    <div class="col-md-3">
                        <label class="text-xs font-weight-bold text-muted text-uppercase">Financial Quarter</label>
                        <select name="quarter" class="form-control form-control-sm border-navy">
                            <option value="">Full Year</option>
                            <option value="1" {{ request('quarter') == '1' ? 'selected' : '' }}>Q1 (Jan - Mar)</option>
                            <option value="2" {{ request('quarter') == '2' ? 'selected' : '' }}>Q2 (Apr - Jun)</option>
                            <option value="3" {{ request('quarter') == '3' ? 'selected' : '' }}>Q3 (Jul - Sep)</option>
                            <option value="4" {{ request('quarter') == '4' ? 'selected' : '' }}>Q4 (Oct - Dec)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="text-xs font-weight-bold text-muted text-uppercase">Specific Month</label>
                        <select name="month" class="form-control form-control-sm border-navy">
                            <option value="">All Months</option>
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>
                                    {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="text-xs font-weight-bold text-muted text-uppercase">Fiscal Year</label>
                        <select name="year" class="form-control form-control-sm border-navy">
                            @php
                                $currentYear = date('Y');
                                $selectedYear = request('year', $currentYear);
                            @endphp
                            @foreach(range($currentYear, $currentYear - 4) as $y)
                                <option value="{{ $y }}" {{ $selectedYear == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <button type="submit" class="btn btn-sm btn-primary px-4 shadow-sm">
                            <i class="fas fa-sync-alt mr-1"></i> Update Report
                        </button>
                        <a href="{{ route('reports.by_line_item') }}" class="btn btn-sm btn-warning border ml-2">
                            <i class="fas fa-undo mr-1"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <hr class="mt-2 mb-4">

    @foreach($reportData as $source)
    <div class="card card-navy card-outline shadow-sm mb-5">
        @php
            $currentYear = $year ?? date('Y'); // Uses the year from controller or current

            // 1. Calculations from the Source Array
            $netSourceBudget = $source['total_activity_budget']; 
            $totalObligated  = $source['total_obligated'] ?? 0;
            $totalDisbursed  = $source['total_disbursed'] ?? 0;
            $totalPending    = $source['total_pending'] ?? 0; // Calculated in Controller
            $totalSavings    = $source['total_savings'] ?? 0; // Calculated in Controller
            $totalUntouched  = $source['total_untouched'] ?? 0;
            
            // 2. Unassigned/Unobligated Balance 
            // This is money not yet given to activities + savings from activities
            $unassignedBalance = $source['unassigned_balance'] ?? 0;

            $totalUnobligated = $totalUntouched + $unassignedBalance;

            // 3. Overall Obligation Rate
            $overallObligRate = $netSourceBudget > 0 
                ? ($totalObligated / $netSourceBudget) * 100 
                : 0;

            $overallDisbRate = $totalObligated > 0 
                ? ($totalDisbursed / $totalObligated) * 100 
                : 0;
        @endphp

        <div class="d-flex justify-content-end p-2">
            <button type="button" class="btn btn-sm btn-outline-primary btn-copy-card">
                <i class="fas fa-camera mr-1"></i> Copy
            </button>
        </div>

        <div class="card-header bg-white py-3" style="cursor: pointer;" data-card-widget="collapse">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="card-title text-navy font-weight-bold">
                    <i class="fas fa-folder-open mr-2 text-secondary"></i>
                    {{ $source['source_name'] }}
                </h3>
                <div class="card-tools d-flex align-items-center">
                    <div class="text-right mr-3">
                        <span class="text-uppercase text-xs text-muted d-block">Source Total</span>
                        <span class="badge badge-navy px-3 py-2 font-weight-bold">
                            ₱{{ number_format($source['source_total'], 2) }}
                        </span>
                    </div>
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="p-4 bg-light border-bottom">
                <div class="p-4 bg-light border-bottom">
                    <div class="row align-items-center no-gutters">
                        <div class="col-md-2 text-center">
                            <span class="text-uppercase text-xs text-muted d-block">Allotted Budget</span>
                            <h5 class="font-weight-bold mb-0 text-navy">₱{{ number_format($netSourceBudget, 2) }}</h5>
                            <small class="text-muted" style="font-size: 0.65rem;">Total Alloted Budget - Pooled Funds</small>
                        </div>

                        <div class="col-md-2 border-left text-center">
                            <span class="text-uppercase text-xs text-muted d-block">Obligation Rate</span>
                            <h5 class="font-weight-bold mb-0 text-navy">{{ number_format($overallObligRate, 1) }}%</h5>
                            <small class="text-muted" style="font-size: 0.65rem;">Obligation amount / Total Alloted Budget</small>
                        </div>

                        <div class="col-md-2 border-left text-center">
                            <span class="text-uppercase text-xs text-muted d-block">Disbursement Rate</span>
                            <h5 class="font-weight-bold mb-0 text-navy">{{ number_format($overallDisbRate, 1) }}%</h5>
                            <small class="text-muted" style="font-size: 0.65rem;">Disbursement amount / Obligation amount</small>
                        </div>

                        <div class="col-md-2 border-left text-center">
                            <span class="text-uppercase text-xs text-muted d-block text-warning">Pending</span>
                            <h5 class="font-weight-bold mb-0 text-warning">₱{{ number_format($totalPending, 2) }}</h5>
                            <small class="text-muted" style="font-size: 0.65rem;">In-Process (Locked)</small>
                        </div>

                        <div class="col-md-2 border-left text-center">
                            <span class="text-uppercase text-xs text-muted d-block text-success">Actual Savings</span>
                            <h5 class="font-weight-bold mb-0 text-success">₱{{ number_format($totalSavings, 2) }}</h5>
                            <small class="text-muted" style="font-size: 0.65rem;">(Oblig - Disb)</small>
                        </div>

                        <div class="col-md-2 border-left text-center">
                            <span class="text-uppercase text-xs text-muted d-block text-primary">Unobligated</span>
                            <h5 class="font-weight-bold mb-0 text-primary">₱{{ number_format($totalUnobligated, 2) }}</h5>
                            <small class="text-muted" style="font-size: 0.65rem;">Untouched Activity Budget</small>
                        </div>
                    </div> 
                </div>
            </div>
            
            <div class="table-responsive" style="max-height: 500px;">
                <table class="table table-sm table-hover table-sticky mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase text-xs">
                            <th class="pl-4 py-3" style="width: 25%">Activity Details</th>
                            <th class="text-right py-3">Alloted Budget</th>
                            <th class="text-right py-3 bg-light border-left-info">Obligated</th>
                            <th class="text-center py-3 bg-light">Obligation %</th>
                            <th class="text-right py-3 border-left-success">Disbursed</th>
                            <th class="text-right py-3 b">Disbursement %</th>
                            <th class="text-right py-3 border-left-success text-success">Actual Savings</th>
                            <th class="text-right py-3 border-left-warning bg-light text-warning">Pending Transactions</th>
                            <th class="text-right py-3 bg-light text-primary">Unobligated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            // Initialize local sums to verify against header
                            $footerPending = 0;
                            $footerSavings = 0;
                            $footerUntouched = 0;
                        @endphp

                        @forelse($source['line_items'] as $item)
                            @php
                                $netBudget = $item['net_budget'];
                                $obligated = $item['obligated_amount'];
                                $disbursed = $item['disbursed_amount'];
                                $pending   = $item['pending_amount']; // From Controller logic
                                $savings   = $item['savings'];        // From Controller logic

                                // Untouched: Money in the budget that isn't obligated AND isn't even pending
                                $untouched = $netBudget - ($obligated + $pending);
                                $untouched = $untouched > 0 ? $untouched : 0;

                                $footerPending += $pending;
                                $footerSavings += $savings;
                                $footerUntouched += $untouched;

                                $rowObligRate = $item['obligation_rate'];
                                $rowDisbRate = $item['disbursement_rate'];
                            @endphp
                            <tr>
                                <td class="pl-4 align-middle">
                                    <span class="font-weight-600 text-dark d-block">{{ $item['name'] }}</span>
                                </td>
                                <td class="text-right align-middle font-weight-bold">₱{{ number_format($netBudget, 2) }}</td>
                                <td class="text-right align-middle text-info font-weight-bold border-left-info">₱{{ number_format($obligated, 2) }}</td>
                                <td class="text-center align-middle bg-light">
                                    <span class="badge {{ $rowObligRate >= 90 ? 'badge-success' : 'badge-warning' }}">
                                        {{ number_format($rowObligRate, 1) }}%
                                    </span>
                                </td>
                                <td class="text-right align-middle text-success font-weight-bold border-left-success">₱{{ number_format($disbursed, 2) }}</td>
                                
                                <td class="text-center align-middle bg-light">
                                    <span class="badge {{ $rowDisbRate >= 90 ? 'badge-success' : 'badge-warning' }}">
                                        {{ number_format($rowDisbRate, 1) }}%
                                    </span>
                                </td>

                                <td class="text-right align-middle border-left-success {{ $savings > 0 ? 'text-success font-weight-bold' : 'text-muted' }}">
                                    ₱{{ number_format($savings, 2) }}
                                </td>
                                <td class="text-right align-middle border-left-warning bg-light {{ $pending > 0 ? 'text-warning font-weight-bold' : 'text-muted' }}">
                                    ₱{{ number_format($pending, 2) }}
                                </td>
                                <td class="text-right align-middle {{ $untouched > 0 ? 'text-primary font-weight-bold' : 'text-muted' }}">
                                    ₱{{ number_format($untouched, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-4">No Data Available</td></tr>
                        @endforelse
                    </tbody>
                    
                    @if(count($source['line_items']) > 0)
                        <tfoot class="bg-navy-light font-weight-bold">
                            <tr>
                                <td class="text-center text-navy py-3">CONSOLIDATED TOTALS</td>
                                <td class="text-right">₱{{ number_format($netSourceBudget, 2) }}</td>
                                <td class="text-right text-info border-left-info">₱{{ number_format($totalObligated, 2) }}</td>
                                <td class="text-center">
                                    <span class=" text-success">{{ number_format($overallObligRate, 1) }}%</span>
                                </td>
                                <td class="text-right text-success border-left-success">₱{{ number_format($source['total_disbursed'], 2) }}</td>
                                
                                <td class="text-center ">
                                    <span class="text-success ">{{ number_format($source['overall_disb_rate'], 1) }}%</span>
                                </td>

                                <td class="text-right text-success border-left-success">₱{{ number_format($footerSavings, 2) }}</td>
                                <td class="text-right text-warning border-left-warning bg-light">₱{{ number_format($footerPending, 2) }}</td>
                                <td class="text-right text-primary bg-light">₱{{ number_format($footerUntouched, 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
    $(document).ready(function() {
        $('.btn-copy-card').on('click', function() {
            const btn = $(this);
            const originalHtml = btn.html();
            
            // Find the parent card relative to the clicked button
            const element = btn.closest('.card')[0]; 

            if (!element) {
                console.error("Could not find the card container to capture.");
                return;
            }

            // UI Feedback
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing Full Report...');

            html2canvas(element, {
                scale: 2, 
                useCORS: true, 
                logging: false,
                backgroundColor: "#ffffff",
                onclone: (clonedDoc) => {
                    // 1. Hide ALL copy buttons in the captured image
                    const clonedButtons = clonedDoc.querySelectorAll('.btn-copy-card');
                    clonedButtons.forEach(b => b.style.visibility = 'hidden');

                    // 2. FORCE the table-responsive container to show all rows
                    // This targets the div that usually has the scrollbar
                    const scrollContainer = clonedDoc.querySelector('.table-responsive');
                    if (scrollContainer) {
                        scrollContainer.style.maxHeight = 'none'; 
                        scrollContainer.style.overflow = 'visible';
                        scrollContainer.style.height = 'auto';
                    }

                    // 3. Ensure the table itself isn't constricted
                    const table = clonedDoc.querySelector('table');
                    if (table) {
                        table.style.marginBottom = '0';
                    }
                }
            }).then(canvas => {
                canvas.toBlob(blob => {
                    try {
                        const item = new ClipboardItem({ "image/png": blob });
                        navigator.clipboard.write([item]).then(() => {
                            // Success Feedback
                            btn.removeClass('btn-outline-primary').addClass('btn-success').html('<i class="fas fa-check"></i> Full Report Copied!');
                            
                            setTimeout(() => {
                                btn.prop('disabled', false).removeClass('btn-success').addClass('btn-outline-primary').html(originalHtml);
                            }, 2000);
                        });
                    } catch (err) {
                        console.error("Clipboard API failed: ", err);
                        alert("Browser error: Could not copy image to clipboard.");
                        btn.prop('disabled', false).html(originalHtml);
                    }
                }, 'image/png');
            });
        });
    });
</script>