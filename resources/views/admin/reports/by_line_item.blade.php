@extends('layouts.adminlte')

@section('content')
<style>
    /* Table & Font Enhancements for Financial Auditing */
    .table-sticky thead th { 
        position: sticky; 
        top: 0; 
        z-index: 10; 
        background: #f8f9fa; 
        box-shadow: inset 0 -2px 0 #dee2e6; 
        font-size: 0.72rem;
        letter-spacing: 0.5px;
    }
    .financial-number { 
        font-family: 'Consolas', 'Courier New', Courier, monospace; 
        font-weight: 600; 
        letter-spacing: -0.3px;
    }
    .card-navy.card-outline { border-top: 3px solid #001f3f; }
    .bg-navy-light { background-color: #f1f4f8; color: #001f3f; }
    
    /* Column Grouping Visual Dividers */
    .border-left-info { border-left: 3px solid #17a2b8 !important; }
    .border-left-success { border-left: 3px solid #28a745 !important; }
    .border-left-warning { border-left: 3px solid #ffc107 !important; }
    .border-left-teal { border-left: 3px solid #20c997 !important; }
    .border-left-primary { border-left: 3px solid #007bff !important; }

    .bg-soft-light { background-color: #fafbfc; }
    .badge-percent { font-size: 0.75rem; padding: 0.35em 0.65em; }

    @media print { .filter-section { display: none !important; } }
</style>

<div class="container-fluid py-3">
    {{-- Page Header --}}
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-end">
            <div>
                <h4 class="text-navy mb-1 font-weight-bold">
                    <i class="fas fa-file-invoice-dollar mr-2 text-primary"></i>Line Item Budget Report
                </h4>
                <p class="text-muted small mb-0">
                    Financial Performance Analysis for Fiscal Year <strong>{{ request('year', date('Y')) }}</strong>
                    @if(request('month'))
                        | Month: <strong>{{ date('F', mktime(0, 0, 0, request('month'), 1)) }}</strong>
                    @elseif(request('quarter'))
                        | Quarter: <strong>Q{{ request('quarter') }}</strong>
                    @endif
                </p>
                <div class="mt-1 d-flex align-items-center text-xs text-muted">
                    <i class="fas fa-info-circle mr-1 text-info"></i>
                    <span>Filtering based on <strong>Obligation Date</strong>. Pending transactions are consolidated for current FY.</span>
                </div>
            </div>
            <div class="btn-group shadow-sm">
                <button class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#filterCard">
                    <i class="fas fa-filter mr-1"></i> Filter Options
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="$('.card').CardWidget('expand')" title="Expand All Cards">
                    <i class="fas fa-expand-alt"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="collapse show filter-section" id="filterCard">
        <div class="card shadow-sm mb-4 border-navy">
            <div class="card-body py-3 bg-white">
                <form action="{{ route('reports.by_line_item') }}" method="GET" class="row align-items-end">
                    <div class="col-md-3">
                        <label class="text-xs font-weight-bold text-muted text-uppercase">Financial Quarter</label>
                        <select name="quarter" class="form-control form-control-sm border-navy">
                            <option value="">Full Fiscal Year</option>
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
                        <button type="submit" class="btn btn-sm btn-navy px-4 shadow-sm text-white" style="background-color: #001f3f;">
                            <i class="fas fa-sync-alt mr-1"></i> Apply Filter
                        </button>
                        <a href="{{ route('reports.by_line_item') }}" class="btn btn-sm btn-light border ml-2 text-muted">
                            <i class="fas fa-undo mr-1"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach($reportData as $sectionName => $sources)
        {{-- Section Group Title --}}
        <div class="row mt-4 mb-2">
            <div class="col-12">
                <h5 class="text-navy font-weight-bold border-bottom pb-2 d-flex align-items-center">
                    <i class="fas fa-university text-secondary mr-2"></i> {{ $sectionName }}
                </h5>
            </div>
        </div>

        @foreach($sources as $source)
            <div class="card card-navy card-outline shadow-sm mb-4">
                @php
                    $currentYear = $year ?? date('Y'); 

                    $netSourceBudget = $source['total_activity_budget']; 
                    $totalObligated  = $source['total_obligated'] ?? 0;
                    $totalDisbursed  = $source['total_disbursed'] ?? 0;
                    $totalPending    = $source['total_pending'] ?? 0; 
                    $totalSavings    = $source['total_savings'] ?? 0; 
                    
                    $totalUnpaidObligations = $totalObligated - $totalDisbursed; 

                    $totalUntouched    = $source['total_untouched'] ?? 0;
                    $unassignedBalance = $source['unassigned_balance'] ?? 0;
                    $totalUnobligated  = $totalUntouched + $unassignedBalance;

                    $overallObligRate = $netSourceBudget > 0 ? ($totalObligated / $netSourceBudget) * 100 : 0;
                    $overallDisbRate  = $totalObligated > 0 ? ($totalDisbursed / $totalObligated) * 100 : 0;
                @endphp

                {{-- Source Card Header --}}
                <div class="card-header bg-white py-2 px-3 border-bottom" style="cursor: pointer;" data-card-widget="collapse">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-folder text-warning mr-2"></i>
                            <h6 class="card-title text-navy font-weight-bold mb-0 mr-3" style="font-size: 0.95rem;">
                                {{ $source['source_name'] }}
                            </h6>
                            <div class="d-flex align-items-center border-left pl-3" style="height: 16px; border-color: #dee2e6 !important;">
                                <span class="text-uppercase text-muted mr-2" style="font-size: 0.65rem; font-weight: 700;">Total Allocation:</span>
                                <span class="text-dark font-weight-bold financial-number" style="font-size: 0.95rem;">
                                    ₱{{ number_format($source['source_total'] ?? 0, 2) }}
                                </span>
                            </div>
                        </div>

                        <div class="card-tools d-flex align-items-center">
                            <button type="button" class="btn btn-xs btn-outline-primary mr-2 btn-copy-card">
                                <i class="fas fa-camera mr-1"></i> Copy Summary
                            </button>
                            <button type="button" class="btn btn-tool btn-xs" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Executive KPI Metrics Summary Strip --}}
                <div class="card-body p-0">
                    <div class="py-3 px-4 bg-soft-light border-bottom">
                        <div class="row align-items-center no-gutters">
                            <div class="col-md-2 text-center border-right">
                                <span class="text-uppercase text-xs text-muted d-block font-weight-bold">Net Activity Budget</span>
                                <h6 class="font-weight-bold mb-0 text-navy financial-number">₱{{ number_format($netSourceBudget, 2) }}</h6>
                                <small class="text-muted" style="font-size: 0.6rem;">Budget Less Pooled</small>
                            </div>

                            <div class="col-md-2 text-center border-right">
                                <span class="text-uppercase text-xs text-muted d-block font-weight-bold">Obligation Rate</span>
                                <h6 class="font-weight-bold mb-0 text-navy">{{ number_format($overallObligRate, 1) }}%</h6>
                                <small class="text-muted" style="font-size: 0.6rem;">Obligations / Net Budget</small>
                            </div>

                            <div class="col-md-2 text-center border-right">
                                <span class="text-uppercase text-xs text-muted d-block font-weight-bold">Disbursement Rate</span>
                                <h6 class="font-weight-bold mb-0 text-navy">{{ number_format($overallDisbRate, 1) }}%</h6>
                                <small class="text-muted" style="font-size: 0.6rem;">Disbursements / Obligations</small>
                            </div>

                            <div class="col-md-2 text-center border-right">
                                <span class="text-uppercase text-xs text-muted d-block font-weight-bold text-warning">Pending Transactions</span>
                                <h6 class="font-weight-bold mb-0 text-warning financial-number">₱{{ number_format($totalPending, 2) }}</h6>
                                <small class="text-muted" style="font-size: 0.6rem;">In-Process / Encumbered</small>
                            </div>

                            <div class="col-md-2 text-center border-right">
                                <span class="text-uppercase text-xs text-muted d-block font-weight-bold text-success">Unpaid Obligations</span>
                                <h6 class="font-weight-bold mb-0 text-success financial-number">₱{{ number_format($totalUnpaidObligations, 2) }}</h6>
                                <small class="text-muted" style="font-size: 0.6rem;">Obligations - Disbursements</small>
                            </div>

                            <div class="col-md-2 text-center">
                                <span class="text-uppercase text-xs text-muted d-block font-weight-bold text-teal">Realized Savings (COS)</span>
                                <h6 class="font-weight-bold mb-0 text-teal financial-number">₱{{ number_format($totalSavings, 2) }}</h6>
                                <small class="text-muted" style="font-size: 0.6rem;">Completed COS Contracts</small>
                            </div>
                        </div> 
                    </div>
                    
                    {{-- Financial Line Item Table --}}
                    <div class="table-responsive" style="max-height: 550px;">
                        <table class="table table-sm table-hover table-sticky mb-0 border-0">
                            <thead>
                                <tr class="text-muted text-uppercase">
                                    <th class="pl-4 py-2" style="width: 22%;">Activity Details</th>
                                    <th class="text-right py-2">Allotted Budget</th>
                                    <th class="text-right py-2 bg-light border-left-info">Obligated</th>
                                    <th class="text-center py-2 bg-light">Oblig. %</th>
                                    <th class="text-right py-2 border-left-success">Disbursed</th>
                                    <th class="text-center py-2">Disb. %</th>
                                    <th class="text-right py-2 border-left-success text-success">Unpaid Obligations</th>
                                    <th class="text-right py-2 border-left-teal text-teal">Savings (COS)</th>
                                    <th class="text-right py-2 border-left-warning bg-light text-warning">Pending Trans.</th>
                                    <th class="text-right py-2 border-left-primary bg-light text-primary">Unobligated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $footerPending   = 0;
                                    $footerUnpaid    = 0;
                                    $footerSavings   = 0;
                                    $footerUntouched = 0;
                                @endphp

                                @forelse($source['line_items'] as $item)
                                    @php
                                        $netBudget = $item['net_budget'];
                                        $obligated = $item['obligated_amount'];
                                        $disbursed = $item['disbursed_amount'];
                                        $pending   = $item['pending_amount']; 
                                        $savings   = $item['savings_amount'] ?? 0;
                                        
                                        $rowUnpaid = $obligated - $disbursed;
                                        $pooled    = $item['pooled_amount'] ?? 0; 
                                        
                                        $untouched = $item['untouched_amount'] ?? ($netBudget - ($obligated + $pending));
                                        $untouched = $untouched > 0 ? $untouched : 0;

                                        $footerPending   += $pending;
                                        $footerUnpaid    += $rowUnpaid;
                                        $footerSavings   += $savings;
                                        $footerUntouched += $untouched;

                                        $rowObligRate = $item['obligation_rate'];
                                        $rowDisbRate  = $item['disbursement_rate'];
                                    @endphp
                                    <tr>
                                        <td class="pl-4 align-middle">
                                            <span class="font-weight-600 text-dark d-block">{{ $item['name'] }}</span>
                                        </td>

                                        <td class="text-right align-middle financial-number">
                                            ₱{{ number_format($netBudget, 2) }}
                                            @if($pooled > 0)
                                                <small class="d-block text-danger font-weight-normal" style="font-size: 0.65rem;">
                                                    (Pooled: ₱{{ number_format($pooled, 2) }})
                                                </small>
                                            @endif
                                        </td>

                                        <td class="text-right align-middle text-info font-weight-bold border-left-info financial-number bg-soft-light">
                                            ₱{{ number_format($obligated, 2) }}
                                        </td>

                                        <td class="text-center align-middle bg-soft-light">
                                            <span class="badge badge-percent {{ $rowObligRate >= 90 ? 'badge-success' : ($rowObligRate >= 50 ? 'badge-info' : 'badge-warning') }}">
                                                {{ number_format($rowObligRate, 1) }}%
                                            </span>
                                        </td>

                                        <td class="text-right align-middle text-success font-weight-bold border-left-success financial-number">
                                            ₱{{ number_format($disbursed, 2) }}
                                        </td>
                                        
                                        <td class="text-center align-middle">
                                            <span class="badge badge-percent {{ $rowDisbRate >= 90 ? 'badge-success' : ($rowDisbRate >= 50 ? 'badge-info' : 'badge-warning') }}">
                                                {{ number_format($rowDisbRate, 1) }}%
                                            </span>
                                        </td>

                                        <td class="text-right align-middle border-left-success financial-number {{ $rowUnpaid > 0 ? 'text-success font-weight-bold' : 'text-muted' }}">
                                            ₱{{ number_format($rowUnpaid, 2) }}
                                        </td>

                                        {{-- SAVINGS COLUMN --}}
                                        <td class="text-right align-middle border-left-teal financial-number {{ $savings > 0 ? 'text-teal font-weight-bold' : 'text-muted' }}">
                                            @if($item['has_cos_salary'] && $savings > 0)
                                                ₱{{ number_format($savings, 2) }}
                                            @else
                                                <span class="text-muted font-weight-normal">-</span>
                                            @endif
                                        </td>

                                        <td class="text-right align-middle border-left-warning bg-soft-light financial-number {{ $pending > 0 ? 'text-warning font-weight-bold' : 'text-muted' }}">
                                            ₱{{ number_format($pending, 2) }}
                                        </td>

                                        <td class="text-right align-middle border-left-primary bg-soft-light financial-number {{ $untouched > 0 ? 'text-primary font-weight-bold' : 'text-muted' }}">
                                            ₱{{ number_format($untouched, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center py-4 text-muted">
                                            <i class="fas fa-folder-open mr-1"></i> No Line Item Activities Available
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            
                            @if(count($source['line_items']) > 0)
                                <tfoot class="bg-navy-light font-weight-bold text-medium">
                                    <tr>
                                        <td class="text-center text-navy py-3">SUMMARY TOTALS</td>
                                        <td class="text-right financial-number">₱{{ number_format($netSourceBudget, 2) }}</td>
                                        <td class="text-right text-info border-left-info financial-number">₱{{ number_format($totalObligated, 2) }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-success">{{ number_format($overallObligRate, 1) }}%</span>
                                        </td>
                                        <td class="text-right text-success border-left-success financial-number">₱{{ number_format($totalDisbursed, 2) }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-success">{{ number_format($overallDisbRate, 1) }}%</span>
                                        </td>
                                        <td class="text-right text-success border-left-success financial-number">₱{{ number_format($footerUnpaid, 2) }}</td>
                                        <td class="text-right text-teal border-left-teal financial-number">₱{{ number_format($footerSavings, 2) }}</td>
                                        <td class="text-right text-warning border-left-warning bg-light financial-number">₱{{ number_format($footerPending, 2) }}</td>
                                        <td class="text-right text-primary border-left-primary bg-light financial-number">₱{{ number_format($footerUntouched, 2) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
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