@extends('layouts.adminlte')

@section('header')
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            {{-- Left Side: Dashboard Titles --}}
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold text-dark">Financial Overview</h1>
                <p class="text-muted small mb-0">
                    Viewing data for Fiscal Year <strong>{{ $selectedYear }}</strong>
                </p>
            </div>

            {{-- Right Side: Year Selection Filter --}}
            <div class="col-sm-6">
                <form action="{{ route('dashboard') }}" method="GET" class="form-inline justify-content-end">
                    <div class="form-group mb-0">
                        <label class="mr-2 small font-weight-bold text-uppercase text-muted">Select Year:</label>
                        <select name="year" class="form-control form-control-sm border-primary shadow-sm" onchange="this.form.submit()">
                            @php
                                // Dynamically get the current system year
                                $currentYear = date('Y'); 
                            @endphp
                            
                            {{-- Range from current year back to 3 years ago --}}
                            @foreach(range($currentYear, $currentYear - 3) as $year)
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
<div class="container-fluid">
    <h6 class="text-uppercase text-muted font-weight-bold mb-3" style="letter-spacing: 1px;">Fund Source Distribution</h6>

    @forelse($groupedData as $sectionId => $sectionFunds)
        {{-- Section Header --}}
        <div class="row mb-3 mt-4">
            <div class="col-12">
                <h5 class="text-secondary font-weight-bold text-uppercase border-bottom pb-2">
                    <i class="fas fa-layer-group mr-2 text-primary"></i>
                    {{ $sectionFunds->first()['section_name'] }}
                </h5>
            </div>
        </div>

        <div class="row">
            @foreach($sectionFunds as $index => $data)
                {{-- Generate a unique ID for this specific card's charts --}}
                @php $uniqueId = $sectionId . '-' . $loop->index; @endphp
                
                <div class="col-xl-6 mb-4 fund-card-container">
                    <div class="card shadow-sm h-100 border-left-primary">
                        <div class="card-header bg-white py-3">
                            <div class="d-flex align-items-center">
                                <div class="mr-3">
                                    <i class="fas fa-wallet text-primary fa-2x"></i>
                                </div>
                                <div>
                                    <h6 class="m-0 font-weight-bold text-primary text-uppercase">
                                        {{ $data['name'] }}
                                    </h6>
                                    <div class="mt-1">
                                        <span class="text-muted small">Total Allotted:</span>
                                        <span class="text-dark font-weight-bold ml-1">₱{{ number_format($data['total_allotted'], 2) }}</span>
                                    </div>
                                </div>
                                <div class="ml-auto" data-html2canvas-ignore>
                                    <button class="btn btn-sm btn-outline-secondary copy-card-btn" type="button">
                                        <i class="fas fa-copy"></i> Copy
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-5 border-right text-center">
                                    <div style="height: 150px; position: relative;">
                                        <canvas id="utilization-{{ $uniqueId }}"></canvas>
                                    </div>
                                    <div class="mt-2">
                                        <h4 class="mb-0 font-weight-bold">{{ $data['percent'] }}%</h4>
                                        <small class="text-muted text-uppercase small">All Transactions</small>
                                    </div>
                                </div>

                                <div class="col-sm-7">
                                    <div style="height: 150px;">
                                        <canvas id="performance-{{ $uniqueId }}"></canvas>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-6 text-center">
                                            <span class="d-block font-weight-bold text-warning">{{ $data['ob_rate'] }}%</span>
                                            <small class="text-muted">Obligation</small>
                                        </div>
                                        <div class="col-6 text-center border-left">
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
                                    <span class="text-muted">All Transactions from the unit</span><br>
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
                                    <strong class="text-dark">{{ $data['last_updated'] }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="text-center p-5 bg-white shadow-sm rounded">
            <h4 class="text-muted">No data found for {{ $selectedYear }}</h4>
        </div>
    @endforelse
</div>
@endsection


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Pass the grouped data from PHP to JS
        const groupedData = {!! json_encode($groupedData) !!};

        // 1. Initialize Charts
        Object.keys(groupedData).forEach(sectionId => {
            const sectionFunds = groupedData[sectionId];
            
            sectionFunds.forEach((source, index) => {
                const uniqueId = `${sectionId}-${index}`;

                // --- Chart A: Utilization (Doughnut) ---
                const utilCtx = document.getElementById(`utilization-${uniqueId}`);
                if (utilCtx) {
                    new Chart(utilCtx, {
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
                }

                // --- Chart B: Performance (Bar) ---
                const perfCtx = document.getElementById(`performance-${uniqueId}`);
                if (perfCtx) {
                    new Chart(perfCtx, {
                        type: 'bar',
                        data: {
                            labels: ['Obligation', 'Disbursement'],
                            datasets: [{
                                label: 'Amount (₱)',
                                // Crucial: Use source.obligated_total and disbursed_total from your Controller map
                                data: [
                                    parseFloat(source.obligated_total) || 0, 
                                    parseFloat(source.disbursed_total) || 0
                                ],
                                backgroundColor: ['#ffc107', '#28a745'],
                                borderRadius: 5
                            }]
                        },
                        options: {
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true, // This allows the chart to scale to 15,000+
                                    ticks: {
                                        // Formats the Y-axis to show currency instead of tiny decimals
                                        callback: function(value) {
                                            if (value >= 1000) return '₱' + (value/1000).toLocaleString() + 'k';
                                            return '₱' + value.toLocaleString();
                                        }
                                    }
                                }
                            },
                            plugins: {
                                legend: { display: false }
                            }
                        }
                    });
                }
            });
        });

        // 2. Copy to Clipboard logic (remains mostly the same but targets the container)
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
                                btn.innerHTML = '<i class="fas fa-check text-success"></i> Copied!';
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
