@extends('layouts.adminlte')

@section('header')
    <div class="row mb-3">
        <div class="col-sm-6">
            <h1 class="m-0 font-weight-bold text-dark">Financial Overview</h1>
            <p class="text-muted small">Real-time status of processed, obligated, and disbursed funds.</p>
        </div>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        {{-- <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="btn btn-primary btn-lg rounded-circle mr-3" style="cursor: default;">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 font-weight-bold">New Entry</h5>
                            <p class="text-muted mb-0 small">Log Transaction</p>
                        </div>
                    </div>
                </div>
                <a href="{{ route('funds.create') }}" class="card-footer bg-primary text-white text-center py-1 small">
                    Create Entry <i class="fas fa-chevron-right ml-1"></i>
                </a>
            </div>
        </div> --}}

        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="btn btn-info btn-lg rounded-circle mr-3" style="cursor: default;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 font-weight-bold">Analytics</h5>
                            <p class="text-muted mb-0 small">System Reports</p>
                        </div>
                    </div>
                </div>
                <a href="{{ route('reports.index') }}" class="card-footer bg-info text-white text-center py-1 small">
                    View Detailed <i class="fas fa-chevron-right ml-1"></i>
                </a>
            </div>
        </div>
    </div>

    <h6 class="text-uppercase text-muted font-weight-bold mb-3" style="letter-spacing: 1px;">Fund Source Distribution</h6>

    <div class="row">
        @foreach($chartData as $index => $data)
        <div class="col-xl-6 mb-4 fund-card-container">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <div class="d-flex align-items-center">
                        <div class="mr-3">
                            <i class="fas fa-wallet text-primary fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="m-0 font-weight-bold text-primary text-uppercase" style="letter-spacing: 0.5px;">
                                {{ $data['name'] }}
                            </h6>
                            <div class="mt-1">
                                <span class="text-muted small">Total Allotted:</span>
                                <span class="text-dark font-weight-bold ml-1">₱{{ number_format($data['total_allotted'], 2) }}</span>
                            </div>
                        </div>
                        <div class="ml-auto" data-html2canvas-ignore>
                            <button class="btn btn-sm btn-outline-secondary copy-card-btn" 
                                    type="button"
                                    title="Copy as Image">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>

                        <div data-html2canvas-ignore>
                            <button class="btn btn-tool" onclick="syncData()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        
                        {{-- <div class="ml-auto">
                            <span class="badge {{ $data['ob_rate'] > 0 ? 'badge-success' : 'badge-light' }} border px-2">
                                <i class="fas fa-sync-alt mr-1"></i> Active
                            </span>
                        </div> --}}
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-5 border-right text-center">
                            <div style="height: 150px; position: relative;">
                                <canvas id="utilization-{{ $index }}"></canvas>
                            </div>
                            <div class="mt-2">
                                <h4 class="mb-0 font-weight-bold">{{ $data['percent'] }}%</h4>
                                <small class="text-muted text-uppercase">Utilization</small>
                            </div>
                        </div>

                        <div class="col-sm-7">
                            <div style="height: 150px;">
                                <canvas id="performance-{{ $index }}"></canvas>
                            </div>
                            <div class="row mt-3">
                                <div class="col-6 text-center">
                                    <span class="d-block font-weight-bold text-warning">{{ $data['ob_rate'] }}%</span>
                                    <small class="text-muted">Obligation</small>
                                </div>
                                <div class="col-6 text-center">
                                    <span class="d-block font-weight-bold text-success">{{ $data['disb_rate'] }}%</span>
                                    <small class="text-muted">Disbursement</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light py-2">
                    <div class="row text-center small">
                        <div class="col-4">
                            <span class="text-muted">Processed by the unit</span><br>
                            <div class="text-bold">₱{{ number_format($data['processed_total'], 2) }}</div>
                        </div>
                        <div class="col-4 border-left">
                            <span class="text-muted">Remaining</span><br>
                            <div class="text-danger" style="font-weight: 700;">
                                ₱{{ number_format($data['remaining_budget'], 2) }}
                            </div>
                        </div>
                        <div class="col-4 border-left">
                            <span class="text-muted">Last Updated</span><br>
                            {{-- Display the dynamic date from our controller --}}
                            <strong class="text-dark">{{ $data['last_updated'] }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const chartData = {!! json_encode($chartData) !!};

        chartData.forEach((source, index) => {
            // Chart A: Utilization (Doughnut)
            new Chart(document.getElementById(`utilization-${index}`), {
                type: 'doughnut',
                data: {
                    labels: ['Processed', 'Remaining'],
                    datasets: [{
                        data: [source.processed_total, source.remaining_budget],
                        backgroundColor: ['#007bff', '#e9ecef'],
                        borderWidth: 0
                    }]
                },
                options: { maintainAspectRatio: false, cutout: '75%', plugins: { legend: { display: false } } }
            });

            // Chart B: Accounting Performance
            new Chart(document.getElementById(`performance-${index}`), {
                type: 'bar',
                data: {
                    labels: ['Obligation', 'Disbursement'],
                    datasets: [{
                        label: 'Amount (₱)',
                        data: [source.obligated_total, source.disbursed_total],
                        backgroundColor: ['#ffc107', '#28a745'],
                        borderRadius: 5
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        // Adding a custom plugin to show percentages on top of bars
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let val = context.raw.toLocaleString();
                                    let rate = context.dataIndex === 0 ? source.ob_rate : source.disb_rate;
                                    let contextLabel = context.dataIndex === 0 ? 'of Total Allotted' : 'of Total Obligated';
                                    return ` ₱${val} (${rate}% ${contextLabel})`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: (val) => '₱' + val.toLocaleString() }
                        }
                    }
                }
            });
        });

        // Copy to Clipboard logic
        document.querySelectorAll('.copy-card-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const btn = this;
                
                // Find the parent container relative to the clicked button
                const targetElement = btn.closest('.fund-card-container');

                if (!targetElement) {
                    console.error("Copy failed: Could not find .fund-card-container");
                    return;
                }

                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                btn.disabled = true;

                // html2canvas needs a small delay to ensure Chart.js is fully rendered
                setTimeout(() => {
                    html2canvas(targetElement, {
                        backgroundColor: "#f4f6f9",
                        scale: 2, 
                        logging: false,
                        useCORS: true,
                        allowTaint: true
                    }).then(canvas => {
                        canvas.toBlob(blob => {
                            if (!blob) {
                                throw new Error("Canvas to Blob failed");
                            }
                            const item = new ClipboardItem({ "image/png": blob });
                            navigator.clipboard.write([item]).then(() => {
                                btn.innerHTML = '<i class="fas fa-check text-success"></i> Copied!';
                                setTimeout(() => {
                                    btn.innerHTML = originalHtml;
                                    btn.disabled = false;
                                }, 2000);
                            });
                        });
                    }).catch(err => {
                        console.error("Copy failed:", err);
                        btn.innerHTML = '<i class="fas fa-times text-danger"></i> Error';
                        btn.disabled = false;
                    });
                }, 100); 
            });
        });

    });
</script>
