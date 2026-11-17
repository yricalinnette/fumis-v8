@extends('layouts.adminlte')

@section('header')
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            {{-- Left Side: Dashboard Titles --}}
            <div class="col-md-5">
                <h1 class="m-0 font-weight-bold text-navy" style="font-size: 1.55rem;">
                    <i class="fas fa-chart-line text-primary mr-2"></i>Financial Overview
                </h1>
                <p class="text-muted small mb-0">
                    Budget Utilization &amp; NORSA-Adjusted Performance — Fiscal Year <strong>{{ $selectedYear }}</strong>
                </p>
            </div>

            {{-- Right Side: Section & Year Filters --}}
            <div class="col-md-7 mt-2 mt-md-0">
                <form action="{{ route('dashboard') }}" method="GET" class="form-inline justify-content-md-end" style="gap: 8px;">
                    @if($availableSections->count() > 1)
                        <div class="input-group input-group-sm shadow-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white text-muted font-weight-bold">Section</span>
                            </div>
                            <select name="section" class="form-control border-left-0" onchange="this.form.submit()">
                                <option value="">All Sections ({{ $availableSections->count() }})</option>
                                @foreach($availableSections as $secId => $secName)
                                    <option value="{{ $secId }}" {{ (string) $selectedSection === (string) $secId ? 'selected' : '' }}>
                                        {{ $secName }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="input-group input-group-sm shadow-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white text-muted font-weight-bold">Fiscal Year</span>
                        </div>
                        <select name="year" class="form-control border-left-0 font-weight-bold text-navy" onchange="this.form.submit()">
                            @php $currentYear = (int) date('Y'); @endphp
                            @foreach(range($currentYear + 1, $currentYear - 3) as $year)
                                <option value="{{ $year }}" {{ $selectedYear == $year ? 'selected' : '' }}>
                                    FY {{ $year }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('content')
<style>
    .tabular-num {
        font-family: 'Consolas', 'Courier New', Courier, monospace;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.3px;
    }
    .kpi-card {
        border: 0;
        border-radius: 0.5rem;
        border-top: 3px solid transparent;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 31, 63, 0.08) !important;
    }
    .kpi-blue   { border-top-color: #007bff; }
    .kpi-navy   { border-top-color: #001f3f; }
    .kpi-amber  { border-top-color: #f39c12; }
    .kpi-green  { border-top-color: #28a745; }
    .kpi-slate  { border-top-color: #6c757d; }

    .section-header-bar {
        background: linear-gradient(135deg, #001f3f 0%, #003366 100%);
        color: #fff;
        border-radius: 6px;
        padding: 0.65rem 1rem;
        box-shadow: 0 2px 5px rgba(0, 31, 63, 0.12);
    }
    .fund-card {
        border: 1px solid #e3e8ee;
        border-top: 3px solid #001f3f;
        border-radius: 0.5rem;
        transition: box-shadow 0.15s ease;
    }
    .fund-card:hover {
        box-shadow: 0 0.45rem 1.1rem rgba(0, 0, 0, 0.07) !important;
    }
    .doughnut-wrapper {
        position: relative;
        height: 155px;
        width: 100%;
    }
    .doughnut-wrapper canvas {
        position: relative;
        z-index: 2;
    }
    .doughnut-center-label {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        pointer-events: none;
        z-index: 1;
    }
    .metric-pill {
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 6px 8px;
    }
    /* Crisp, high-contrast floating tooltip that never gets overlapped or clipped */
    #chartjs-floating-tooltip {
        position: fixed;
        z-index: 99999;
        pointer-events: none;
        background: #0f172a;
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 0.78rem;
        line-height: 1.4;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.28);
        white-space: nowrap;
        opacity: 0;
        transition: opacity 0.12s ease;
    }
</style>

<div class="container-fluid pb-4">
    {{-- EXECUTIVE KPI SUMMARY ROW (5 CARDS) --}}
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-5 mb-4">
        {{-- Card 1: Total Allotted (source_of_funds.total_amount) --}}
        <div class="col mb-3 mb-xl-0">
            <div class="card kpi-card kpi-blue shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-uppercase text-muted font-weight-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                Total Allotted
                            </div>
                            <h4 class="font-weight-bold text-primary tabular-num mt-1 mb-1">
                                ₱{{ number_format($summaryTotals['gross_allotted'], 2) }}
                            </h4>
                        </div>
                        <span class="badge badge-primary p-2 rounded">
                            <i class="fas fa-wallet fa-lg"></i>
                        </span>
                    </div>
                    <div class="small text-muted">
                        @if($summaryTotals['pooled'] > 0)
                            Pooled: <strong class="tabular-num text-warning">-₱{{ number_format($summaryTotals['pooled'], 2) }}</strong>
                        @else
                            Gross Fund Source Total
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Adjusted Allotment --}}
        <div class="col mb-3 mb-xl-0">
            <div class="card kpi-card kpi-navy shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-uppercase text-muted font-weight-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                Adjusted Allotment
                            </div>
                            <h4 class="font-weight-bold text-navy tabular-num mt-1 mb-1">
                                ₱{{ number_format($summaryTotals['allotted'], 2) }}
                            </h4>
                        </div>
                        <span class="badge badge-light text-navy p-2 rounded">
                            <i class="fas fa-landmark fa-lg"></i>
                        </span>
                    </div>
                    <div class="small text-muted">
                        Pending / Routed: <strong class="tabular-num text-dark">₱{{ number_format($summaryTotals['pending'], 2) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 3: Obligated --}}
        <div class="col mb-3 mb-xl-0">
            <div class="card kpi-card kpi-amber shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-uppercase text-muted font-weight-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                Obligated ({{ $summaryTotals['ob_rate'] }}%)
                            </div>
                            <h4 class="font-weight-bold text-dark tabular-num mt-1 mb-1">
                                ₱{{ number_format($summaryTotals['net_obligated'], 2) }}
                            </h4>
                        </div>
                        <span class="badge badge-warning text-white p-2 rounded">
                            <i class="fas fa-file-signature fa-lg"></i>
                        </span>
                    </div>
                    <div class="small text-muted d-flex flex-wrap align-items-center" style="gap: 6px;">
                        @if($summaryTotals['norsa'] > 0)
                            <span class="badge badge-danger tabular-num" title="Total NORSA / Cancelled Deductions">
                                NORSA -₱{{ number_format($summaryTotals['norsa'], 2) }}
                            </span>
                        @else
                            <span>No NORSA adjustments</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 4: Total Disbursed --}}
        <div class="col mb-3 mb-lg-0">
            <div class="card kpi-card kpi-green shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-uppercase text-muted font-weight-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                Total Disbursed ({{ $summaryTotals['disb_rate'] }}%)
                            </div>
                            <h4 class="font-weight-bold text-success tabular-num mt-1 mb-1">
                                ₱{{ number_format($summaryTotals['disbursed'], 2) }}
                            </h4>
                        </div>
                        <span class="badge badge-success p-2 rounded">
                            <i class="fas fa-check-circle fa-lg"></i>
                        </span>
                    </div>
                    <div class="small text-muted">
                        Unpaid Obligation:
                        <strong class="tabular-num text-dark">
                            ₱{{ number_format(max(0, $summaryTotals['net_obligated'] - $summaryTotals['disbursed']), 2) }}
                        </strong>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 5: Unobligated Balance --}}
        <div class="col">
            <div class="card kpi-card kpi-slate shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-uppercase text-muted font-weight-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                Unobligated Balance
                            </div>
                            <h4 class="font-weight-bold text-danger tabular-num mt-1 mb-1">
                                ₱{{ number_format($summaryTotals['remaining'], 2) }}
                            </h4>
                        </div>
                        <span class="badge badge-secondary p-2 rounded">
                            <i class="fas fa-coins fa-lg"></i>
                        </span>
                    </div>
                    <div class="small text-muted">
                        Available after Oblig. &amp; Pending
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION & FUND SOURCE CARDS --}}
    @forelse($groupedData as $sectionId => $sectionFunds)
        <div class="mb-4">
            {{-- Navy Section Banner --}}
            <div class="section-header-bar d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center">
                    <i class="fas fa-university text-warning mr-2"></i>
                    <h6 class="m-0 font-weight-bold text-uppercase" style="letter-spacing: 0.5px;">
                        {{ $sectionFunds->first()['section_name'] }}
                    </h6>
                </div>
                <div class="d-flex align-items-center" style="gap: 10px;">
                    <span class="small text-light d-none d-sm-inline">
                        Section Allotment: <strong class="tabular-num">₱{{ number_format($sectionFunds->sum('total_allotted'), 2) }}</strong>
                    </span>
                    <span class="badge badge-light text-navy font-weight-bold px-2 py-1">
                        {{ $sectionFunds->count() }} {{ \Illuminate\Support\Str::plural('Fund Source', $sectionFunds->count()) }}
                    </span>
                </div>
            </div>

            <div class="row">
                @foreach($sectionFunds as $index => $data)
                    @php $uniqueId = $sectionId . '-' . $loop->index; @endphp

                    <div class="col-xl-6 mb-4 fund-card-container">
                        <div class="card fund-card shadow-sm h-100">
                            {{-- Card Header --}}
                            <div class="card-header bg-white py-3 border-bottom">
                                <div class="d-flex align-items-start justify-content-between">
                                    <div class="d-flex align-items-center pr-2">
                                        <div class="bg-light text-navy rounded p-2 mr-3 border">
                                            <i class="fas fa-folder-open fa-lg text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="m-0 font-weight-bold text-navy text-uppercase" style="font-size: 0.9rem;">
                                                {{ $data['name'] }}
                                            </h6>
                                            <div class="mt-1 d-flex flex-wrap align-items-center" style="gap: 8px;">
                                                <span class="small text-muted">
                                                    Adjusted Allotted:
                                                    <strong class="text-dark tabular-num">₱{{ number_format($data['total_allotted'], 2) }}</strong>
                                                </span>
                                                @if($data['norsa_total'] > 0)
                                                    <span class="badge badge-danger tabular-num" style="font-size: 0.68rem;" title="NORSA Reductions Applied">
                                                        <i class="fas fa-minus-circle mr-1"></i>NORSA: ₱{{ number_format($data['norsa_total'], 2) }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div data-html2canvas-ignore>
                                        <button class="btn btn-xs btn-outline-secondary copy-card-btn px-2 py-1" type="button" title="Copy Card Snapshot">
                                            <i class="fas fa-camera mr-1"></i> Copy
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Card Body: Dual Charts --}}
                            <div class="card-body py-3">
                                <div class="row align-items-center">
                                    {{-- Left: Utilization Doughnut (Disbursed, Unpaid Oblig., Pending, Unobligated) --}}
                                    <div class="col-sm-5 border-right text-center mb-3 mb-sm-0">
                                        <div class="doughnut-wrapper">
                                            <canvas id="utilization-{{ $uniqueId }}"></canvas>
                                            <div class="doughnut-center-label">
                                                <div class="font-weight-bold text-navy tabular-num" style="font-size: 1.25rem; line-height: 1.1;">
                                                    {{ $data['percent'] }}%
                                                </div>
                                                <small class="text-muted text-uppercase" style="font-size: 0.62rem; letter-spacing: 0.4px;">
                                                    Utilized
                                                </small>
                                            </div>
                                        </div>
                                        <div class="mt-2 d-flex justify-content-center flex-wrap small" style="gap: 6px; font-size: 0.68rem;">
                                            <span><i class="fas fa-circle text-success mr-1"></i>Disb.</span>
                                            <span><i class="fas fa-circle text-primary mr-1"></i>Unpaid Oblig.</span>
                                            <span><i class="fas fa-circle text-info mr-1"></i>Pending</span>
                                            <span><i class="fas fa-circle text-secondary mr-1" style="opacity: 0.35;"></i>Unoblig.</span>
                                        </div>
                                    </div>

                                    {{-- Right: Obligation & Disbursement Bar Chart --}}
                                    <div class="col-sm-7">
                                        <div style="height: 155px;">
                                            <canvas id="performance-{{ $uniqueId }}"></canvas>
                                        </div>
                                        <div class="row mt-2 no-gutters" style="gap: 6px;">
                                            <div class="col metric-pill text-center">
                                                <span class="d-block font-weight-bold text-primary tabular-num" style="font-size: 0.85rem;">
                                                    {{ $data['ob_rate'] }}%
                                                </span>
                                                <small class="text-muted text-uppercase" style="font-size: 0.64rem;">Oblig. Rate</small>
                                            </div>
                                            <div class="col metric-pill text-center">
                                                <span class="d-block font-weight-bold text-success tabular-num" style="font-size: 0.85rem;">
                                                    {{ $data['disb_rate'] }}%
                                                </span>
                                                <small class="text-muted text-uppercase" style="font-size: 0.64rem;">Disb. vs Oblig.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Card Footer: Exact Breakdown --}}
                            <div class="card-footer bg-light py-2 px-3 border-top">
                                <div class="row text-center" style="font-size: 0.74rem;">
                                    <div class="col-3 px-1">
                                        <span class="text-muted d-block">Obligated</span>
                                        <strong class="text-primary tabular-num">₱{{ number_format($data['obligated_total'], 2) }}</strong>
                                    </div>
                                    <div class="col-3 px-1 border-left">
                                        <span class="text-muted d-block">Disbursed</span>
                                        <strong class="text-success tabular-num">₱{{ number_format($data['disbursed_total'], 2) }}</strong>
                                    </div>
                                    <div class="col-3 px-1 border-left">
                                        <span class="text-muted d-block">Unobligated</span>
                                        <strong class="text-danger tabular-num">₱{{ number_format($data['remaining_budget'], 2) }}</strong>
                                        @if($data['pending_total'] > 0)
                                            <span class="d-block text-info tabular-num" style="font-size: 0.65rem;">
                                                Pend: ₱{{ number_format($data['pending_total'], 2) }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="col-3 px-1 border-left">
                                        <span class="text-muted d-block">Last Updated</span>
                                        <strong class="text-dark">{{ $data['last_updated'] }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="text-center p-5 bg-white shadow-sm rounded border">
            <i class="fas fa-folder-open fa-2x text-muted mb-2"></i>
            <h5 class="text-muted mb-1">No Fund Sources Found</h5>
            <p class="small text-muted mb-0">No allotment or transaction records match Fiscal Year {{ $selectedYear }}.</p>
        </div>
    @endforelse
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const groupedData = {!! json_encode($groupedData) !!};

        const formatPeso = (val) => '₱' + Number(val || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        // External floating tooltip handler so tooltips always sit above all card elements and never get clipped
        const externalTooltipHandler = (context) => {
            const { chart, tooltip } = context;
            let tooltipEl = document.getElementById('chartjs-floating-tooltip');

            if (!tooltipEl) {
                tooltipEl = document.createElement('div');
                tooltipEl.id = 'chartjs-floating-tooltip';
                document.body.appendChild(tooltipEl);
            }

            if (tooltip.opacity === 0) {
                tooltipEl.style.opacity = 0;
                return;
            }

            const dataIndex = tooltip.dataPoints?.[0]?.dataIndex ?? 0;
            const dataset   = chart.data.datasets[0];
            const label     = chart.data.labels[dataIndex] || '';
            const rawValue  = Number(dataset.data[dataIndex] || 0);
            const total     = dataset.data.reduce((sum, v) => sum + (Number(v) || 0), 0);
            const pct       = total > 0 ? ((rawValue / total) * 100).toFixed(1) : '0.0';
            const color     = Array.isArray(dataset.backgroundColor)
                ? dataset.backgroundColor[dataIndex]
                : dataset.backgroundColor;

            tooltipEl.innerHTML = `
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;font-weight:700;">
                    <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:${color};border:1px solid #fff;"></span>
                    <span>${label}</span>
                </div>
                <div style="font-family:'Consolas',monospace;font-size:0.82rem;">
                    ${formatPeso(rawValue)} <span style="color:#94a3b8;">(${pct}%)</span>
                </div>
            `;

            const rect = chart.canvas.getBoundingClientRect();
            const left = rect.left + tooltip.caretX;
            const top  = rect.top + tooltip.caretY;

            tooltipEl.style.opacity = 1;
            tooltipEl.style.left = Math.min(window.innerWidth - tooltipEl.offsetWidth - 12, Math.max(12, left - tooltipEl.offsetWidth / 2)) + 'px';
            tooltipEl.style.top  = Math.max(12, top - tooltipEl.offsetHeight - 10) + 'px';
        };

        Object.keys(groupedData).forEach(sectionId => {
            const sectionFunds = groupedData[sectionId];

            sectionFunds.forEach((source, index) => {
                const uniqueId = `${sectionId}-${index}`;

                const obligatedTotal = parseFloat(source.obligated_total) || 0;
                const disbursedTotal = parseFloat(source.disbursed_total) || 0;
                const unpaidOblig    = Math.max(0, obligatedTotal - disbursedTotal);
                const pendingTotal   = parseFloat(source.pending_total) || 0;
                const remainingBudg  = parseFloat(source.remaining_budget) || 0;

                // --- Chart A: Utilization Doughnut (Disbursed, Unpaid Obligated, Pending, Unobligated) ---
                const utilCtx = document.getElementById(`utilization-${uniqueId}`);
                if (utilCtx) {
                    new Chart(utilCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Disbursed', 'Unpaid Obligation', 'Pending / Routed', 'Unobligated Balance'],
                            datasets: [{
                                data: [
                                    disbursedTotal,
                                    unpaidOblig,
                                    pendingTotal,
                                    remainingBudg
                                ],
                                backgroundColor: ['#28a745', '#007bff', '#17a2b8', '#e9ecef'],
                                borderWidth: 1,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            maintainAspectRatio: false,
                            cutout: '74%',
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    enabled: false,
                                    external: externalTooltipHandler
                                }
                            }
                        }
                    });
                }

                // --- Chart B: Performance Bar (Obligated vs Disbursed) ---
                const perfCtx = document.getElementById(`performance-${uniqueId}`);
                if (perfCtx) {
                    new Chart(perfCtx, {
                        type: 'bar',
                        data: {
                            labels: ['Obligated', 'Disbursed'],
                            datasets: [{
                                label: 'Amount',
                                data: [
                                    obligatedTotal,
                                    disbursedTotal
                                ],
                                backgroundColor: ['#007bff', '#28a745'],
                                borderRadius: 5,
                                maxBarThickness: 48
                            }]
                        },
                        options: {
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: '#f1f3f5' },
                                    ticks: {
                                        font: { size: 10 },
                                        callback: function(value) {
                                            if (value >= 1000000) return '₱' + (value / 1000000).toFixed(1) + 'M';
                                            if (value >= 1000) return '₱' + (value / 1000).toLocaleString() + 'k';
                                            return '₱' + value.toLocaleString();
                                        }
                                    }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { font: { size: 10, weight: 'bold' } }
                                }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#0f172a',
                                    padding: 10,
                                    cornerRadius: 6,
                                    callbacks: {
                                        label: (ctx) => ` ${ctx.label}: ${formatPeso(ctx.raw)}`
                                    }
                                }
                            }
                        }
                    });
                }
            });
        });

        // --- Copy Card Snapshot to Clipboard ---
        document.querySelectorAll('.copy-card-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const btn = this;
                const targetElement = btn.closest('.fund-card-container');
                const originalHtml = btn.innerHTML;

                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                btn.disabled = true;

                setTimeout(() => {
                    html2canvas(targetElement, {
                        backgroundColor: "#f4f6f9",
                        scale: 2,
                        useCORS: true
                    }).then(canvas => {
                        canvas.toBlob(blob => {
                            const item = new ClipboardItem({ "image/png": blob });
                            navigator.clipboard.write([item]).then(() => {
                                btn.innerHTML = '<i class="fas fa-check text-success"></i> Copied';
                                setTimeout(() => {
                                    btn.innerHTML = originalHtml;
                                    btn.disabled = false;
                                }, 2000);
                            });
                        });
                    });
                }, 100);
            });
        });
    });
</script>
@endsection