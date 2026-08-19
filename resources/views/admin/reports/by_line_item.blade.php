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
    .border-left-danger { border-left: 3px solid #dc3545 !important; }
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
                    @elseif(!empty($quarters))
                        | Quarters: <strong>Q{{ implode(', Q', (array)$quarters) }} (Cumulative)</strong>
                    @endif
                </p>
                <div class="mt-1 d-flex align-items-center text-xs text-muted">
                    <i class="fas fa-info-circle mr-1 text-info"></i>
                    <span>Obligations and Disbursements are strictly filtered based on transaction dates in the selected period.</span>
                </div>
            </div>
            
            {{-- Toolbar Buttons --}}
            <div class="btn-group shadow-sm">
                <a href="{{ route('reports.by_line_item.export', request()->all()) }}" class="btn btn-sm btn-success">
                    <i class="fas fa-file-excel mr-1"></i> Export Excel
                </a>
                <button class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#filterCard">
                    <i class="fas fa-filter mr-1"></i> Filter Options
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="$('.card').CardWidget('expand')" title="Expand All Cards">
                    <i class="fas fa-expand-alt"></i> Expand All
                </button>
            </div>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="collapse show filter-section" id="filterCard">
        <div class="card shadow-sm mb-4 border-navy">
            <div class="card-body py-3 bg-white">
                <form action="{{ route('reports.by_line_item') }}" method="GET" class="row align-items-end">
                    
                    {{-- Cumulative Quarters Checkboxes --}}
                    <div class="col-md-5">
                        <label class="text-xs font-weight-bold text-muted text-uppercase d-block mb-1">
                            Cumulative Quarters <small class="text-primary font-weight-normal">(Select multiple for cumulative report)</small>
                        </label>
                        <div class="d-flex align-items-center bg-light p-1.5 rounded border border-navy">
                            @foreach([1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4'] as $qVal => $qLabel)
                                <div class="custom-control custom-checkbox custom-control-inline mx-2">
                                    <input type="checkbox" name="quarters[]" value="{{ $qVal }}" id="q_{{ $qVal }}" class="custom-control-input quarter-checkbox"
                                        {{ in_array($qVal, (array) $quarters) ? 'checked' : '' }}>
                                    <label class="custom-control-label text-sm font-weight-bold cursor-pointer" for="q_{{ $qVal }}">{{ $qLabel }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Specific Month --}}
                    <div class="col-md-3">
                        <label class="text-xs font-weight-bold text-muted text-uppercase">Specific Month</label>
                        <select name="month" id="monthSelect" class="form-control form-control-sm border-navy">
                            <option value="">All Months</option>
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>
                                    {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Fiscal Year --}}
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

                    {{-- Action Buttons --}}
                    <div class="col-md-2 text-right">
                        <button type="submit" class="btn btn-sm btn-navy px-3 shadow-sm text-white" style="background-color: #001f3f;">
                            <i class="fas fa-sync-alt mr-1"></i> Apply Filter
                        </button>
                        <a href="{{ route('reports.by_line_item') }}" class="btn btn-sm btn-light border ml-1 text-muted">
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
                    $netSourceBudget     = $source['total_activity_budget']; 
                    $totalGrossObligated = $source['total_gross_obligated'] ?? 0;
                    $totalNorsa          = $source['total_norsa'] ?? 0;
                    $totalObligated      = $source['total_obligated'] ?? 0; // Net Obligation
                    $totalDisbursed      = $source['total_disbursed'] ?? 0;
                    $totalPending        = $source['total_pending'] ?? 0; 
                    $totalSavings        = $source['total_savings'] ?? 0; 
                    
                    $totalUnpaidObligations = max(0, $totalObligated - $totalDisbursed); 

                    $totalUntouched    = $source['total_untouched'] ?? 0;
                    $unassignedBalance = $source['unassigned_balance'] ?? 0;

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
                                <span class="text-uppercase text-muted mr-2" style="font-size: 0.65rem; font-weight: 700;">Total Alloted Fund:</span>
                                <span class="text-dark font-weight-bold financial-number" style="font-size: 0.95rem;">
                                    ₱{{ number_format($source['source_total'] ?? 0, 2) }}
                                </span>
                            </div>
                        </div>

                        <div class="card-tools d-flex align-items-center">
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
                                <span class="text-uppercase text-xs text-muted d-block font-weight-bold">Alloted Budget</span>
                                <h6 class="font-weight-bold mb-0 text-navy financial-number">₱{{ number_format($netSourceBudget, 2) }}</h6>
                                <small class="text-muted" style="font-size: 0.6rem;">Alloted budget Less Pooled</small>
                            </div>

                            <div class="col-md-2 text-center border-right">
                                <span class="text-uppercase text-xs text-muted d-block font-weight-bold text-info">Net Obligations</span>
                                <h6 class="font-weight-bold mb-0 text-info financial-number">₱{{ number_format($totalObligated, 2) }}</h6>
                                <small class="text-muted" style="font-size: 0.6rem;">Gross (₱{{ number_format($totalGrossObligated, 2) }}) - NORSA</small>
                            </div>

                            <div class="col-md-2 text-center border-right">
                                <span class="text-uppercase text-xs text-muted d-block font-weight-bold text-danger">NORSA</span>
                                <h6 class="font-weight-bold mb-0 text-danger financial-number">₱{{ number_format($totalNorsa, 2) }}</h6>
                                <small class="text-muted" style="font-size: 0.6rem;">Obligation Reductions</small>
                            </div>

                            <div class="col-md-2 text-center border-right">
                                <span class="text-uppercase text-xs text-muted d-block font-weight-bold text-success">Disbursements</span>
                                <h6 class="font-weight-bold mb-0 text-success financial-number">₱{{ number_format($totalDisbursed, 2) }}</h6>
                                <small class="text-muted" style="font-size: 0.6rem;">Disb. Rate: {{ number_format($overallDisbRate, 1) }}%</small>
                            </div>

                            <div class="col-md-2 text-center border-right">
                                <span class="text-uppercase text-xs text-muted d-block font-weight-bold text-warning">Pending Transactions</span>
                                <h6 class="font-weight-bold mb-0 text-warning financial-number">₱{{ number_format($totalPending, 2) }}</h6>
                                <small class="text-muted" style="font-size: 0.6rem;">Routed from Unit/Section</small>
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
                                    <th class="pl-4 py-2" style="width: 20%;">Activity Details</th>
                                    <th class="text-right py-2">Allotted Budget</th>
                                    <th class="text-right py-2 bg-light border-left-info">Gross Obligated</th>
                                    <th class="text-right py-2 text-danger border-left-danger">NORSA</th>
                                    <th class="text-right py-2 bg-light border-left-info text-info">Net Obligated</th>
                                    <th class="text-center py-2 bg-light">Oblig. %</th>
                                    <th class="text-right py-2 border-left-success">Disbursed</th>
                                    <th class="text-center py-2">Disb. %</th>
                                    <th class="text-right py-2 border-left-success text-success">Unpaid Oblig.</th>
                                    <th class="text-right py-2 border-left-teal text-teal">Savings (COS)</th>
                                    <th class="text-right py-2 border-left-warning bg-light text-warning">Pending Trans.</th>
                                    <th class="text-right py-2 border-left-primary bg-light text-primary">Unobligated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $footerGrossOb   = 0;
                                    $footerNorsa     = 0;
                                    $footerNetOb     = 0;
                                    $footerDisbursed = 0;
                                    $footerPending   = 0;
                                    $footerUnpaid    = 0;
                                    $footerSavings   = 0;
                                    $footerUntouched = 0;
                                @endphp

                                @forelse($source['line_items'] as $item)
                                    @php
                                        $netBudget   = $item['net_budget'];
                                        $grossOb     = $item['gross_obligated'] ?? $item['obligated_amount'];
                                        $norsa       = $item['norsa_amount'] ?? 0;
                                        $netOb       = $item['obligated_amount'];
                                        $disbursed   = $item['disbursed_amount'];
                                        $pending     = $item['pending_amount']; 
                                        $savings     = $item['savings_amount'] ?? 0;
                                        
                                        $rowUnpaid   = max(0, $netOb - $disbursed);
                                        $pooled      = $item['pooled_amount'] ?? 0; 
                                        
                                        $untouched   = $item['untouched_amount'] ?? ($netBudget - ($netOb + $pending));
                                        $untouched   = $untouched > 0 ? $untouched : 0;

                                        $footerGrossOb   += $grossOb;
                                        $footerNorsa     += $norsa;
                                        $footerNetOb     += $netOb;
                                        $footerDisbursed += $disbursed;
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

                                        {{-- GROSS OBLIGATED --}}
                                        <td class="text-right align-middle font-weight-bold border-left-info financial-number bg-soft-light">
                                            ₱{{ number_format($grossOb, 2) }}
                                        </td>

                                        {{-- NORSA SAVINGS --}}
                                        <td class="text-right align-middle text-danger font-weight-bold border-left-danger financial-number">
                                            @if($norsa > 0)
                                                (₱{{ number_format($norsa, 2) }})
                                            @else
                                                <span class="text-muted font-weight-normal">-</span>
                                            @endif
                                        </td>

                                        {{-- NET OBLIGATED --}}
                                        <td class="text-right align-middle text-info font-weight-bold border-left-info financial-number bg-soft-light">
                                            ₱{{ number_format($netOb, 2) }}
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
                                        <td colspan="12" class="text-center py-4 text-muted">
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
                                        <td class="text-right border-left-info financial-number">₱{{ number_format($footerGrossOb, 2) }}</td>
                                        <td class="text-right text-danger border-left-danger financial-number">
                                            {{ $footerNorsa > 0 ? '(₱' . number_format($footerNorsa, 2) . ')' : '-' }}
                                        </td>
                                        <td class="text-right text-info border-left-info financial-number">₱{{ number_format($footerNetOb, 2) }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-success">{{ number_format($overallObligRate, 1) }}%</span>
                                        </td>
                                        <td class="text-right text-success border-left-success financial-number">₱{{ number_format($footerDisbursed, 2) }}</td>
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

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
    $(document).ready(function() {
        // Auto Reset: Clear month when quarter checkboxes are picked & vice versa
        $('.quarter-checkbox').on('change', function() {
            if ($(this).is(':checked')) {
                $('#monthSelect').val('');
            }
        });

        $('#monthSelect').on('change', function() {
            if ($(this).val()) {
                $('.quarter-checkbox').prop('checked', false);
            }
        });

        // Fixed Copy Card Snapshot Feature
        $('.btn-copy-card').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const btn = $(this);
            const originalHtml = btn.html();
            
            // Correctly locate card container
            const element = btn.closest('.card-outline')[0] || btn.closest('.card')[0]; 

            if (!element) {
                alert("Could not find card container.");
                return;
            }

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Copying Report...');

            html2canvas(element, {
                scale: 2, 
                useCORS: true, 
                logging: false,
                backgroundColor: "#ffffff",
                onclone: (clonedDoc) => {
                    // Hide action buttons in snapshot
                    const clonedButtons = clonedDoc.querySelectorAll('.btn-copy-card, .btn-tool');
                    clonedButtons.forEach(b => b.style.display = 'none');

                    // Expand table container for full snapshot
                    const scrollContainer = clonedDoc.querySelector('.table-responsive');
                    if (scrollContainer) {
                        scrollContainer.style.maxHeight = 'none'; 
                        scrollContainer.style.overflow = 'visible';
                        scrollContainer.style.height = 'auto';
                    }

                    // Force card body visible in clone if collapsed
                    const cardBody = clonedDoc.querySelector('.card-body');
                    if (cardBody) {
                        cardBody.style.display = 'block';
                    }
                }
            }).then(canvas => {
                canvas.toBlob(blob => {
                    if (!blob) {
                        alert("Could not generate image blob.");
                        btn.prop('disabled', false).html(originalHtml);
                        return;
                    }

                    // Modern Clipboard API attempt
                    if (navigator.clipboard && window.ClipboardItem) {
                        const item = new ClipboardItem({ "image/png": blob });
                        navigator.clipboard.write([item]).then(() => {
                            btn.removeClass('btn-outline-primary').addClass('btn-success').html('<i class="fas fa-check mr-1"></i> Copied!');
                            setTimeout(() => {
                                btn.prop('disabled', false).removeClass('btn-success').addClass('btn-outline-primary').html(originalHtml);
                            }, 2000);
                        }).catch(err => {
                            console.warn("Clipboard API rejected, falling back to download:", err);
                            fallbackDownload(blob);
                        });
                    } else {
                        fallbackDownload(blob);
                    }

                    function fallbackDownload(imageBlob) {
                        const link = document.createElement('a');
                        link.download = 'budget-report-summary.png';
                        link.href = URL.createObjectURL(imageBlob);
                        link.click();
                        URL.revokeObjectURL(link.href);

                        btn.removeClass('btn-outline-primary').addClass('btn-info').html('<i class="fas fa-download mr-1"></i> Downloaded!');
                        setTimeout(() => {
                            btn.prop('disabled', false).removeClass('btn-info').addClass('btn-outline-primary').html(originalHtml);
                        }, 2000);
                    }
                }, 'image/png');
            }).catch(err => {
                console.error("html2canvas error:", err);
                alert("Error generating image snapshot.");
                btn.prop('disabled', false).html(originalHtml);
            });
        });
    });
</script>
@endsection