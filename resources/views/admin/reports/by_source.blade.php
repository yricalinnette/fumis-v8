@extends('layouts.adminlte')

@section('content')
<style>
    /* Professional Financial Table Styles */
    .financial-number { font-family: 'Consolas', 'Courier New', Courier, monospace; font-weight: 600; letter-spacing: -0.3px; }
    .card-navy.card-outline { border-top: 3px solid #001f3f; }
    .bg-navy-light { background-color: #f1f4f8; color: #001f3f; }
    
    /* Column Accent Borders */
    .border-left-info { border-left: 3px solid #17a2b8 !important; }
    .border-left-success { border-left: 3px solid #28a745 !important; }
    .border-left-warning { border-left: 3px solid #ffc107 !important; }
    .border-left-teal { border-left: 3px solid #20c997 !important; }
    .border-left-primary { border-left: 3px solid #007bff !important; }
    
    .section-header-row { background-color: #e9ecef !important; color: #343a40; font-weight: 700; }
    .table-sticky thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; box-shadow: inset 0 -2px 0 #dee2e6; font-size: 0.7rem; letter-spacing: 0.5px; }
    .bg-soft-light { background-color: #fafbfc; }
    
    @media print { .filter-section { display: none !important; } }
</style>

<div class="container-fluid py-3">
    {{-- Header Section --}}
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-end">
            <div>
                <h4 class="text-navy mb-1 font-weight-bold">
                    <i class="fas fa-layer-group mr-2 text-primary"></i>By Source Budget Tracking
                </h4>
                <p class="text-muted small mb-0">
                    Financial Summary for Fiscal Year <strong>{{ request('year', date('Y')) }}</strong> 
                    @if(request('source_type')) | Type: <span class="text-uppercase text-info font-weight-bold">{{ request('source_type') }}</span> @endif
                    @if(request('month')) | Month: <strong>{{ date('F', mktime(0, 0, 0, request('month'), 1)) }}</strong>
                    @elseif(request('quarter')) | Quarter: <strong>Q{{ request('quarter') }}</strong> @endif
                </p>
            </div>
            <div class="btn-group shadow-sm">
                <a href="{{ route('reports.by_source.export', request()->all()) }}" class="btn btn-sm btn-success">
                    <i class="fas fa-file-excel mr-1"></i> Export Excel
                </a>
                <button class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#filterCard">
                    <i class="fas fa-filter mr-1"></i> Filters
                </button>
            </div>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="collapse show filter-section" id="filterCard">
        <div class="card shadow-sm mb-4 border-navy">
            <div class="card-body py-3 bg-white">
                <form action="{{ route('reports.by_source') }}" method="GET" class="row align-items-end">
                    <div class="col-md-2">
                        <label class="text-xs font-weight-bold text-muted text-uppercase">Source Type</label>
                        <select name="source_type" class="form-control form-control-sm border-navy text-uppercase">
                            <option value="">All Types</option>
                            @foreach($sourceTypes ?? ['saa', 'regular', 'continuing'] as $type)
                                <option value="{{ $type }}" {{ request('source_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="text-xs font-weight-bold text-muted text-uppercase">Financial Quarter</label>
                        <select name="quarter" class="form-control form-control-sm border-navy">
                            <option value="">Full Year</option>
                            <option value="1" {{ request('quarter') == '1' ? 'selected' : '' }}>Q1 (Jan - Mar)</option>
                            <option value="2" {{ request('quarter') == '2' ? 'selected' : '' }}>Q2 (Apr - Jun)</option>
                            <option value="3" {{ request('quarter') == '3' ? 'selected' : '' }}>Q3 (Jul - Sep)</option>
                            <option value="4" {{ request('quarter') == '4' ? 'selected' : '' }}>Q4 (Oct - Dec)</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="text-xs font-weight-bold text-muted text-uppercase">Specific Month</label>
                        <select name="month" class="form-control form-control-sm border-navy">
                            <option value="">All Months</option>
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="text-xs font-weight-bold text-muted text-uppercase">Fiscal Year</label>
                        <select name="year" class="form-control form-control-sm border-navy">
                            @php $currentYear = date('Y'); @endphp
                            @foreach(range($currentYear, $currentYear - 4) as $yearOption)
                                <option value="{{ $yearOption }}" {{ request('year', $currentYear) == $yearOption ? 'selected' : '' }}>{{ $yearOption }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <button type="submit" class="btn btn-sm text-white px-4 shadow-sm" style="background-color: #001f3f;">
                            <i class="fas fa-sync-alt mr-1"></i> Apply Filter
                        </button>
                        <a href="{{ route('reports.by_source') }}" class="btn btn-sm btn-light border ml-2 text-muted">Reset</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card card-navy card-outline shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 650px;">
                <table class="table table-sm table-hover table-sticky mb-0 border-0">
                    <thead>
                        <tr class="text-muted text-uppercase">
                            <th class="pl-4 py-2" style="width: 18%;">Fund Source</th>
                            <th class="text-center py-2">Procurable</th>
                            <th class="text-center py-2">Non-Procurable</th>
                            <th class="text-right py-2">Total Allotment</th>
                            <th class="text-right py-2 bg-light border-left-info">Obligated</th>
                            <th class="text-center py-2 bg-light">Oblig. %</th>
                            <th class="text-right py-2 border-left-success">Disbursed</th>
                            <th class="text-center py-2">Disb. %</th>
                            <th class="text-right py-2 border-left-teal text-teal">Savings (COS)</th>
                            <th class="text-right py-2 border-left-warning text-warning">Pending Trans.</th>
                            <th class="text-right py-2 bg-light">Unpaid Obligations</th>
                            <th class="text-right py-2 pr-4 border-left-primary bg-light text-primary">Unobligated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $grandTotals = [
                                'allotted' => 0, 'obligated' => 0, 'disbursed' => 0, 
                                'pending' => 0, 'savings' => 0, 'procurable' => 0, 'non_procurable' => 0
                            ];
                        @endphp

                        @forelse($groupedReport as $sectionName => $sources)
                            @php
                                $sectionTotals = [
                                    'allotted' => 0, 'obligated' => 0, 'disbursed' => 0, 
                                    'pending' => 0, 'savings' => 0, 'procurable' => 0, 'non_procurable' => 0
                                ];
                            @endphp

                            <tr class="section-header-row">
                                <td colspan="12" class="pl-4 py-2">
                                    <i class="fas fa-building mr-2 text-secondary"></i>
                                    <span>{{ $sectionName }}</span>
                                </td>
                            </tr>

                            @foreach($sources as $data)
                                @php
                                    $currentAllotted  = $data['source_total'];
                                    $currentObligated = $data['total_obligated'];
                                    $currentDisbursed = $data['total_disbursed'];
                                    $currentPending   = $data['total_pending'];
                                    $currentSavings   = $data['total_savings'] ?? 0;
                                    $budgetP          = $data['procurable_budget_total']; 
                                    $budgetNP         = $data['non_procurable_budget_total'];
                                    
                                    $percP  = $currentAllotted > 0 ? ($budgetP / $currentAllotted) * 100 : 0;
                                    $percNP = $currentAllotted > 0 ? ($budgetNP / $currentAllotted) * 100 : 0;

                                    $unpaid      = $currentObligated - $currentDisbursed;
                                    $unobligated = $data['total_unobligated'];

                                    // Update Section Totals
                                    $sectionTotals['allotted']       += $currentAllotted;
                                    $sectionTotals['obligated']      += $currentObligated;
                                    $sectionTotals['disbursed']      += $currentDisbursed;
                                    $sectionTotals['pending']        += $currentPending;
                                    $sectionTotals['savings']        += $currentSavings;
                                    $sectionTotals['procurable']     += $budgetP;
                                    $sectionTotals['non_procurable'] += $budgetNP;

                                    // Update Grand Totals
                                    $grandTotals['allotted']       += $currentAllotted;
                                    $grandTotals['obligated']      += $currentObligated;
                                    $grandTotals['disbursed']      += $currentDisbursed;
                                    $grandTotals['pending']        += $currentPending;
                                    $grandTotals['savings']        += $currentSavings;
                                    $grandTotals['procurable']     += $budgetP;
                                    $grandTotals['non_procurable'] += $budgetNP;

                                    $obligClass = $data['overall_oblig_rate'] >= 90 ? 'badge-success' : ($data['overall_oblig_rate'] >= 50 ? 'badge-info' : 'badge-warning');
                                @endphp
                                <tr>
                                    <td class="pl-4 align-middle">
                                        <span class="text-navy font-weight-bold text-uppercase small d-block">{{ $data['source_name'] }}</span>
                                    </td>

                                    <td class="text-center align-middle">
                                        <span class="text-xs font-weight-bold text-info">{{ number_format($percP, 1) }}%</span>
                                        <small class="d-block text-muted financial-number" style="font-size: 0.65rem;">₱{{ number_format($budgetP, 2) }}</small>
                                    </td>

                                    <td class="text-center align-middle">
                                        <span class="text-xs font-weight-bold text-secondary">{{ number_format($percNP, 1) }}%</span>
                                        <small class="d-block text-muted financial-number" style="font-size: 0.65rem;">₱{{ number_format($budgetNP, 2) }}</small>
                                    </td>

                                    <td class="text-right align-middle financial-number">₱{{ number_format($currentAllotted, 2) }}</td>
                                    
                                    <td class="text-right align-middle financial-number text-info font-weight-bold border-left-info bg-soft-light">
                                        ₱{{ number_format($currentObligated, 2) }}
                                    </td>

                                    <td class="text-center align-middle bg-soft-light">
                                        <span class="badge {{ $obligClass }}">{{ number_format($data['overall_oblig_rate'], 1) }}%</span>
                                    </td>

                                    <td class="text-right align-middle financial-number text-success font-weight-bold border-left-success">
                                        ₱{{ number_format($currentDisbursed, 2) }}
                                    </td>

                                    <td class="text-center align-middle">
                                        <span class="badge badge-light border">{{ number_format($data['overall_disb_rate'], 1) }}%</span>
                                    </td>

                                    {{-- REALIZED COS SAVINGS COLUMN --}}
                                    <td class="text-right align-middle border-left-teal financial-number {{ $currentSavings > 0 ? 'text-teal font-weight-bold' : 'text-muted' }}">
                                        @if($currentSavings > 0)
                                            ₱{{ number_format($currentSavings, 2) }}
                                        @else
                                            <span class="text-muted font-weight-normal">-</span>
                                        @endif
                                    </td>

                                    <td class="text-right align-middle financial-number text-warning border-left-warning">
                                        ₱{{ number_format($currentPending, 2) }}
                                    </td>

                                    <td class="text-right align-middle financial-number bg-soft-light">
                                        ₱{{ number_format($unpaid, 2) }}
                                    </td>

                                    <td class="text-right align-middle financial-number pr-4 border-left-primary bg-soft-light {{ $unobligated < 0 ? 'text-danger' : 'text-primary' }}">
                                        ₱{{ number_format($unobligated, 2) }}
                                    </td>
                                </tr>
                            @endforeach

                            {{-- Section Subtotal Row --}}
                            @php
                                $secObligRate = $sectionTotals['allotted'] > 0 ? ($sectionTotals['obligated'] / $sectionTotals['allotted']) * 100 : 0;
                                $secDisbRate  = $sectionTotals['obligated'] > 0 ? ($sectionTotals['disbursed'] / $sectionTotals['obligated']) * 100 : 0;
                            @endphp
                            <tr class="bg-navy-light shadow-sm">
                                <td class="pl-4 py-2 align-middle text-uppercase small"><b>Total: {{ $sectionName }}</b></td>
                                <td class="text-center align-middle text-xs font-weight-bold">{{ number_format($sectionTotals['allotted'] > 0 ? ($sectionTotals['procurable'] / $sectionTotals['allotted']) * 100 : 0, 1) }}%</td>
                                <td class="text-center align-middle text-xs font-weight-bold">{{ number_format($sectionTotals['allotted'] > 0 ? ($sectionTotals['non_procurable'] / $sectionTotals['allotted']) * 100 : 0, 1) }}%</td>
                                <td class="text-right align-middle financial-number">₱{{ number_format($sectionTotals['allotted'], 2) }}</td>
                                <td class="text-right align-middle financial-number border-left-info text-info">₱{{ number_format($sectionTotals['obligated'], 2) }}</td>
                                <td class="text-center align-middle"><span class="badge badge-info">{{ number_format($secObligRate, 1) }}%</span></td>
                                <td class="text-right align-middle financial-number border-left-success text-success">₱{{ number_format($sectionTotals['disbursed'], 2) }}</td>
                                <td class="text-center align-middle"><span class="badge badge-light border">{{ number_format($secDisbRate, 1) }}%</span></td>
                                <td class="text-right align-middle financial-number border-left-teal text-teal">₱{{ number_format($sectionTotals['savings'], 2) }}</td>
                                <td class="text-right align-middle financial-number border-left-warning text-warning">₱{{ number_format($sectionTotals['pending'], 2) }}</td>
                                <td class="text-right align-middle financial-number">₱{{ number_format($sectionTotals['obligated'] - $sectionTotals['disbursed'], 2) }}</td>
                                <td class="text-right align-middle financial-number pr-4 border-left-primary text-primary">₱{{ number_format($sectionTotals['allotted'] - $sectionTotals['obligated'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-2x mb-2 d-block"></i>
                                    No records found matching the active filter configuration.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    
                    @if($grandTotals['allotted'] > 0)
                        <tfoot class="bg-navy-light shadow-sm border-top" style="border-top: 2px solid #001f3f !important;">
                            @php
                                $gtObligRate = $grandTotals['allotted'] > 0 ? ($grandTotals['obligated'] / $grandTotals['allotted']) * 100 : 0;
                                $gtDisbRate  = $grandTotals['obligated'] > 0 ? ($grandTotals['disbursed'] / $grandTotals['obligated']) * 100 : 0;
                            @endphp
                            <tr class="font-weight-bold" style="color: #001f3f;">
                                <td class="pl-4 py-3 align-middle text-uppercase small"><b>GRAND TOTAL</b></td>
                                <td class="text-center align-middle text-xs">{{ number_format($grandTotals['allotted'] > 0 ? ($grandTotals['procurable'] / $grandTotals['allotted']) * 100 : 0, 1) }}%</td>
                                <td class="text-center align-middle text-xs">{{ number_format($grandTotals['allotted'] > 0 ? ($grandTotals['non_procurable'] / $grandTotals['allotted']) * 100 : 0, 1) }}%</td>
                                <td class="text-right align-middle financial-number">₱{{ number_format($grandTotals['allotted'], 2) }}</td>
                                <td class="text-right text-info align-middle financial-number border-left-info">₱{{ number_format($grandTotals['obligated'], 2) }}</td>
                                <td class="text-center align-middle bg-light"><span class="badge badge-success">{{ number_format($gtObligRate, 1) }}%</span></td>
                                <td class="text-right text-success align-middle financial-number border-left-success">₱{{ number_format($grandTotals['disbursed'], 2) }}</td>
                                <td class="text-center align-middle"><span class="badge badge-success">{{ number_format($gtDisbRate, 1) }}%</span></td>
                                <td class="text-right text-teal align-middle financial-number border-left-teal">₱{{ number_format($grandTotals['savings'], 2) }}</td>
                                <td class="text-right text-warning align-middle financial-number border-left-warning">₱{{ number_format($grandTotals['pending'], 2) }}</td>
                                <td class="text-right align-middle financial-number">₱{{ number_format($grandTotals['obligated'] - $grandTotals['disbursed'], 2) }}</td>
                                <td class="text-right align-middle financial-number pr-4 border-left-primary text-primary">₱{{ number_format($grandTotals['allotted'] - $grandTotals['obligated'], 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>  
@endsection