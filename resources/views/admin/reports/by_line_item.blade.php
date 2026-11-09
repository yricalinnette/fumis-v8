@extends('layouts.adminlte')

@section('content')
<style>
    .financial-number { font-family: 'Consolas', 'Courier New', Courier, monospace; font-weight: 600; letter-spacing: -0.3px; }
    .card-navy.card-outline { border-top: 3px solid #001f3f; }
    .bg-navy-light { background-color: #f1f4f8; color: #001f3f; }
    .bg-soft-light { background-color: #f8f9fa; }
    
    .section-banner {
        background: linear-gradient(135deg, #001f3f 0%, #003366 100%);
        color: #ffffff;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    }
    @media print { .filter-section { display: none !important; } }
</style>

<div class="container-fluid py-3">
    {{-- Top Header Strip --}}
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <h4 class="text-navy mb-1 font-weight-bold">
                <i class="fas fa-file-invoice-dollar mr-2 text-primary"></i>Line Item Budget Report
            </h4>
            <p class="text-muted small mb-0">
                Financial Performance Analysis for Fiscal Year <strong>{{ request('year', $year ?? date('Y')) }}</strong>
                @if(request('month'))
                    | Month: <strong>{{ date('F', mktime(0, 0, 0, request('month'), 1)) }}</strong>
                @elseif(!empty($quarters))
                    | Quarters: <strong>Q{{ implode(', Q', (array)$quarters) }} (Cumulative)</strong>
                @endif
            </p>
        </div>
        
        <div class="btn-group shadow-sm">
            <a href="{{ route('reports.by_line_item.export', request()->all()) }}" class="btn btn-sm btn-success">
                <i class="fas fa-file-excel mr-1"></i> Export Excel
            </a>
            <button class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#filterCard">
                <i class="fas fa-filter mr-1"></i> Filter Options
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="expandAllBtn">
                <i class="fas fa-expand-arrows-alt mr-1"></i> Expand All
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="fas fa-print mr-1"></i> Print
            </button>
        </div>
    </div>
    <p class="text-muted small mb-4">
        <i class="fas fa-info-circle text-info mr-1"></i> Obligations and Disbursements are strictly filtered based on transaction dates in the selected period.
    </p>

    {{-- FILTER & SEARCH TOOLBAR --}}
    <div class="card card-default filter-section collapse show shadow-sm mb-4" id="filterCard">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.by_line_item') }}" id="filterForm">
                <div class="row align-items-center">
                    <div class="col-md-5 col-12 mb-2 mb-md-0">
                        <label class="small font-weight-bold mb-1 d-block">
                            CUMULATIVE QUARTERS <span class="text-muted font-weight-normal" style="font-size: 0.65rem;">(SELECT MULTIPLE FOR CUMULATIVE REPORT)</span>
                        </label>
                        <div class="d-flex align-items-center flex-wrap bg-white border rounded px-2 py-1" style="gap: 12px; height: 31px;">
                            @foreach([1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4'] as $qVal => $qLabel)
                                <div class="custom-control custom-checkbox custom-control-inline m-0">
                                    <input type="checkbox" name="quarters[]" value="{{ $qVal }}" id="q_{{ $qVal }}" class="custom-control-input quarter-checkbox"
                                        {{ in_array($qVal, (array) $quarters) ? 'checked' : '' }}>
                                    <label class="custom-control-label small font-weight-bold text-dark cursor-pointer" for="q_{{ $qVal }}">{{ $qLabel }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-3 col-12 mb-2 mb-md-0">
                        <label class="small font-weight-bold mb-1">SPECIFIC MONTH</label>
                        <select name="month" id="monthSelect" class="form-control form-control-sm">
                            <option value="">All Months</option>
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>
                                    {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 col-6 mb-2 mb-md-0">
                        <label class="small font-weight-bold mb-1">FISCAL YEAR</label>
                        <select name="year" class="form-control form-control-sm">
                            @php
                                $currentYear = date('Y');
                                $selectedYear = request('year', $currentYear);
                            @endphp
                            @foreach(range($currentYear, $currentYear - 4) as $y)
                                <option value="{{ $y }}" {{ $selectedYear == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 col-6 text-right">
                        <label class="small font-weight-bold mb-1 d-block">&nbsp;</label>
                        <button type="submit" class="btn btn-sm btn-navy font-weight-bold px-3 mr-1" style="background-color: #001f3f; color: #fff;">
                            <i class="fas fa-sync-alt mr-1"></i> Apply Filter
                        </button>
                        <a href="{{ route('reports.by_line_item') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                            <i class="fas fa-undo mr-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- CLEAN MASTER TABLE LAYOUT --}}
    @foreach($reportData as $sectionName => $sources)
        <div class="mb-5">
            <div class="section-banner py-2 px-3 mb-2 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <i class="fas fa-university mr-2 text-warning"></i>
                    <h5 class="font-weight-bold mb-0 text-uppercase" style="font-size: 1rem;">{{ $sectionName }}</h5>
                </div>
                <span class="badge badge-light text-navy font-weight-bold px-2 py-1">{{ count($sources) }} Fund Sources</span>
            </div>

            @foreach($sources as $source)
                <div class="card card-navy card-outline mb-4 shadow-sm">
                    @php
                        $netSourceBudget     = $source['total_activity_budget']; 
                        $totalGrossObligated = $source['total_gross_obligated'] ?? 0;
                        $totalNorsa          = $source['total_norsa'] ?? 0;
                        $totalObligated      = $source['total_obligated'] ?? 0; 
                        $totalDisbursed      = $source['total_disbursed'] ?? 0;
                        $totalPending        = $source['total_pending'] ?? 0; 
                        $totalSavings        = $source['total_savings'] ?? 0; 
                        
                        $overallObligRate = $netSourceBudget > 0 ? ($totalObligated / $netSourceBudget) * 100 : 0;
                        $overallDisbRate  = $totalObligated > 0 ? ($totalDisbursed / $totalObligated) * 100 : 0;

                        // Calculate total pooled amount across line items for this fund source summary
                        $totalPooledSource = 0;
                        foreach($source['line_items'] as $li) {
                            $totalPooledSource += ($li['pooled_amount'] ?? 0);
                        }
                    @endphp

                    {{-- Card Header --}}
                    <div class="card-header bg-white py-2 px-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="card-title text-navy font-weight-bold mb-0" style="font-size: 0.95rem;">
                                <i class="fas fa-folder text-warning mr-2"></i>{{ $source['source_name'] }} 
                                <span class="text-muted ml-3 font-weight-normal">TOTAL ALLOTTED FUND: <strong class="text-dark financial-number">₱{{ number_format($source['source_total'] ?? 0, 2) }}</strong></span>
                            </h6>
                            <div>
                                <button type="button" class="btn btn-xs btn-outline-primary btn-copy-card px-2 font-weight-bold mr-1" title="Capture table as image for PowerPoint">
                                    <i class="fas fa-camera mr-1"></i> Copy for PPT
                                </button>
                                <button type="button" class="btn btn-tool btn-xs" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Executive Summary Strip --}}
                    <div class="card-body p-0">
                        <div class="py-3 px-3 bg-soft-light border-bottom">
                            <div class="row align-items-center text-center no-gutters" style="font-size: 0.72rem;">
                                <div class="col border-right">
                                    <span class="text-uppercase text-muted d-block font-weight-bold" style="font-size: 0.6rem;">Allotted Budget</span>
                                    <span class="font-weight-bold text-navy financial-number" style="font-size: 0.85rem;">₱{{ number_format($netSourceBudget, 2) }}</span>
                                    <div class="text-muted" style="font-size: 0.52rem;">Allotted budget Less Pooled</div>
                                </div>
                                <div class="col border-right">
                                    <span class="text-uppercase text-muted d-block font-weight-bold text-warning" style="font-size: 0.6rem;">Total Pooled</span>
                                    <span class="font-weight-bold text-warning financial-number" style="font-size: 0.85rem;">₱{{ number_format($totalPooledSource, 2) }}</span>
                                    <div class="text-muted" style="font-size: 0.52rem;">Pooled Allocations</div>
                                </div>
                                <div class="col border-right">
                                    <span class="text-uppercase text-muted d-block font-weight-bold text-info" style="font-size: 0.6rem;">Net Obligations</span>
                                    <span class="font-weight-bold text-info financial-number" style="font-size: 0.85rem;">₱{{ number_format($totalObligated, 2) }}</span>
                                    <div class="text-muted" style="font-size: 0.52rem;">Gross (₱{{ number_format($totalGrossObligated, 2) }}) - NORSA</div>
                                </div>
                                <div class="col border-right">
                                    <span class="text-uppercase text-muted d-block font-weight-bold text-danger" style="font-size: 0.6rem;">NORSA</span>
                                    <span class="font-weight-bold text-danger financial-number" style="font-size: 0.85rem;">₱{{ number_format($totalNorsa, 2) }}</span>
                                    <div class="text-muted" style="font-size: 0.52rem;">Obligation Reductions</div>
                                </div>
                                <div class="col border-right">
                                    <span class="text-uppercase text-muted d-block font-weight-bold text-success" style="font-size: 0.6rem;">Disbursements</span>
                                    <span class="font-weight-bold text-success financial-number" style="font-size: 0.85rem;">₱{{ number_format($totalDisbursed, 2) }}</span>
                                    <div class="text-muted" style="font-size: 0.52rem;">Disb. Rate: {{ number_format($overallDisbRate, 1) }}%</div>
                                </div>
                                <div class="col border-right">
                                    <span class="text-uppercase text-muted d-block font-weight-bold text-warning" style="font-size: 0.6rem;">Pending Transactions</span>
                                    <span class="font-weight-bold text-warning financial-number" style="font-size: 0.85rem;">₱{{ number_format($totalPending, 2) }}</span>
                                    <div class="text-muted" style="font-size: 0.52rem;">Routed from Unit/Section</div>
                                </div>
                                <div class="col">
                                    <span class="text-uppercase text-muted d-block font-weight-bold text-teal" style="font-size: 0.6rem;">Realized Savings</span>
                                    <span class="font-weight-bold text-teal financial-number" style="font-size: 0.85rem;">₱{{ number_format($totalSavings, 2) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0 text-xs">
                                <thead class="bg-navy-light text-uppercase">
                                    <tr>
                                        <th style="width: 20%;">Activity / Line Item</th>
                                        <th class="text-right" style="width: 8%;">Allotted Budget</th>
                                        <th class="text-right" style="width: 8%;">Gross Oblig.</th>
                                        <th class="text-right" style="width: 7%;">NORSA</th>
                                        <th class="text-right" style="width: 8%;">Net Oblig.</th>
                                        <th class="text-center" style="width: 6%;">Oblig %</th>
                                        <th class="text-right" style="width: 8%;">Disbursed</th>
                                        <th class="text-center" style="width: 6%;">Disb %</th>
                                        <th class="text-right" style="width: 8%;">Unpaid Oblig.</th>
                                        <th class="text-right" style="width: 7%;">Savings</th>
                                        <th class="text-right" style="width: 7%;">Pending</th>
                                        <th class="text-right" style="width: 7%;">Unobligated</th>
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
                                            $rowDisbRate  = $netOb > 0 ? ($disbursed / $netOb) * 100 : 0;
                                        @endphp
                                        <tr>
                                            <td class="align-middle font-weight-bold text-dark">
                                                {{ $item['name'] }}
                                                @if($pooled > 0)
                                                    <span class="badge badge-warning text-dark ml-1 d-block mt-1" style="font-size: 0.65rem; width: fit-content;">
                                                        POOLED AMOUNT: ₱{{ number_format($pooled, 2) }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-right align-middle financial-number">₱{{ number_format($netBudget, 2) }}</td>
                                            <td class="text-right align-middle financial-number text-info">₱{{ number_format($grossOb, 2) }}</td>
                                            <td class="text-right align-middle financial-number text-danger">
                                                {{ $norsa > 0 ? '(₱'.number_format($norsa, 2).')' : '-' }}
                                            </td>
                                            <td class="text-right align-middle financial-number text-info">₱{{ number_format($netOb, 2) }}</td>
                                            <td class="text-center align-middle">
                                                <span class="badge {{ $rowObligRate >= 90 ? 'badge-success' : ($rowObligRate >= 50 ? 'badge-info' : 'badge-warning') }}" style="font-size: 0.68rem;">
                                                    {{ number_format($rowObligRate, 1) }}%
                                                </span>
                                            </td>
                                            <td class="text-right align-middle financial-number text-success">₱{{ number_format($disbursed, 2) }}</td>
                                            <td class="text-center align-middle">
                                                <span class="badge {{ $rowDisbRate >= 90 ? 'badge-success' : ($rowDisbRate >= 50 ? 'badge-info' : 'badge-warning') }}" style="font-size: 0.68rem;">
                                                    {{ number_format($rowDisbRate, 1) }}%
                                                </span>
                                            </td>
                                            <td class="text-right align-middle financial-number text-success">₱{{ number_format($rowUnpaid, 2) }}</td>
                                            <td class="text-right align-middle financial-number text-teal">
                                                {{ $savings > 0 ? '₱'.number_format($savings, 2) : '-' }}
                                            </td>
                                            <td class="text-right align-middle financial-number text-warning">₱{{ number_format($pending, 2) }}</td>
                                            <td class="text-right align-middle financial-number text-primary">₱{{ number_format($untouched, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="12" class="text-center text-muted py-2">No line item activities available for this fund source.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                
                                @if(count($source['line_items']) > 0)
                                    <tfoot class="bg-navy-light font-weight-bold text-uppercase" style="font-size: 0.72rem;">
                                        <tr>
                                            <td class="text-center text-navy py-2">Summary Totals</td>
                                            <td class="text-right financial-number">₱{{ number_format($netSourceBudget, 2) }}</td>
                                            <td class="text-right financial-number text-info">₱{{ number_format($footerGrossOb, 2) }}</td>
                                            <td class="text-right financial-number text-danger">{{ $footerNorsa > 0 ? '(₱'.number_format($footerNorsa, 2).')' : '-' }}</td>
                                            <td class="text-right financial-number text-info">₱{{ number_format($footerNetOb, 2) }}</td>
                                            <td class="text-center"><span class="badge badge-success" style="font-size: 0.68rem;">{{ number_format($overallObligRate, 1) }}%</span></td>
                                            <td class="text-right financial-number text-success">₱{{ number_format($footerDisbursed, 2) }}</td>
                                            <td class="text-center"><span class="badge badge-success" style="font-size: 0.68rem;">{{ number_format($overallDisbRate, 1) }}%</span></td>
                                            <td class="text-right financial-number text-success">₱{{ number_format($footerUnpaid, 2) }}</td>
                                            <td class="text-right financial-number text-teal">₱{{ number_format($footerSavings, 2) }}</td>
                                            <td class="text-right financial-number text-warning">₱{{ number_format($footerPending, 2) }}</td>
                                            <td class="text-right financial-number text-primary">₱{{ number_format($footerUntouched, 2) }}</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
    $(document).ready(function() {
        // Auto Reset: Clear month when quarter checkboxes are picked & vice versa
        $(document).on('change', '.quarter-checkbox', function() {
            if ($(this).is(':checked')) {
                $('#monthSelect').val('');
            }
        });

        $(document).on('change', '#monthSelect', function() {
            if ($(this).val()) {
                $('.quarter-checkbox').prop('checked', false);
            }
        });

        // Expand All / Collapse All Toggle functionality
        let isExpanded = true;
        $('#expandAllBtn').on('click', function() {
            isExpanded = !isExpanded;
            if (isExpanded) {
                $('.card').each(function() {
                    if ($(this).hasClass('collapsed-card')) {
                        $(this).CardWidget('Expand');
                    }
                });
                $(this).html('<i class="fas fa-compress-arrows-alt mr-1"></i> Collapse All');
            } else {
                $('.card').each(function() {
                    if (!$(this).hasClass('collapsed-card')) {
                        $(this).CardWidget('Collapse');
                    }
                });
                $(this).html('<i class="fas fa-expand-arrows-alt mr-1"></i> Expand All');
            }
        });

        // Copy Card Snapshot Feature for PowerPoint
        $(document).on('click', '.btn-copy-card', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const btn = $(this);
            const originalHtml = btn.html();
            const cardContainer = btn.closest('.card');

            if (cardContainer.length === 0) {
                alert("Card container not found.");
                return;
            }

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Copying Image...');

            html2canvas(cardContainer[0], {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                logging: false,
                backgroundColor: "#ffffff",
                onclone: function(clonedDoc) {
                    $(clonedDoc).find('.btn-copy-card, .btn-tool').hide();
                    $(clonedDoc).find('.table-responsive').css({
                        'max-height': 'none',
                        'overflow': 'visible',
                        'height': 'auto'
                    });
                }
            }).then(function(canvas) {
                canvas.toBlob(function(blob) {
                    if (!blob) {
                        alert("Failed to generate image.");
                        btn.prop('disabled', false).html(originalHtml);
                        return;
                    }

                    if (navigator.clipboard && window.ClipboardItem && window.isSecureContext) {
                        try {
                            const item = new ClipboardItem({ "image/png": blob });
                            navigator.clipboard.write([item]).then(function() {
                                btn.removeClass('btn-outline-primary').addClass('btn-success').html('<i class="fas fa-check mr-1"></i> Copied as Image!');
                                setTimeout(function() {
                                    btn.prop('disabled', false).removeClass('btn-success').addClass('btn-outline-primary').html(originalHtml);
                                }, 2500);
                            }).catch(function(err) {
                                console.warn("Clipboard write error, falling back to download:", err);
                                fallbackDownload(blob, btn, originalHtml);
                            });
                        } catch (ex) {
                            console.warn("ClipboardItem exception, falling back to download:", ex);
                            fallbackDownload(blob, btn, originalHtml);
                        }
                    } else {
                        fallbackDownload(blob, btn, originalHtml);
                    }
                }, 'image/png');
            }).catch(function(err) {
                console.error("html2canvas error:", err);
                alert("An error occurred while generating the image snapshot.");
                btn.prop('disabled', false).html(originalHtml);
            });
        });

        function fallbackDownload(blob, btn, originalHtml) {
            const link = document.createElement('a');
            link.download = 'line-item-report.png';
            link.href = URL.createObjectURL(blob);
            link.click();
            URL.revokeObjectURL(link.href);

            btn.removeClass('btn-outline-primary').addClass('btn-info').html('<i class="fas fa-download mr-1"></i> Downloaded Image!');
            setTimeout(function() {
                btn.prop('disabled', false).removeClass('btn-info').addClass('btn-outline-primary').html(originalHtml);
            }, 2500);
        }
    });
</script>
@endsection