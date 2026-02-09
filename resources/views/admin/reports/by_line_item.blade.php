@extends('layouts.adminlte')

@section('content')
<style>
    .table-sticky thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; box-shadow: inset 0 -1px 0 #dee2e6; }
    .financial-number { font-family: 'Courier New', Courier, monospace; font-weight: 600; }
    .card-navy.card-outline { border-top: 3px solid #001f3f; }
    .bg-navy-light { background-color: #f4f6f9; color: #001f3f; }
    .border-left-info { border-left: 4px solid #17a2b8 !important; }
    .border-left-success { border-left: 4px solid #28a745 !important; }
    .border-left-primary { border-left: 4px solid #007bff !important; } {{-- Added for Savings --}}
    @media print { .filter-section { display: none; } }
</style>

<div class="container-fluid">
    {{-- ... Header and Filter Section remain the same as your provided code ... --}}
    <div class="row pt-3 mb-2">
        <div class="col-12 d-flex justify-content-between align-items-end">
            <div>
                <h4 class="text-navy mb-0 font-weight-bold">
                    <i class="fas fa-chart-line mr-2 text-muted"></i>Line Item Budget Tracking
                </h4>
                <p class="text-muted small mb-0">
                    Financial Performance for Fiscal Year <strong>{{ request('year', date('Y')) }}</strong>
                    @if(request('month'))
                        | Month: <strong>{{ date('F', mktime(0, 0, 0, request('month'), 1)) }}</strong>
                    @elseif(request('quarter'))
                        | Quarter: <strong>Q{{ request('quarter') }}</strong>
                    @endif
                </p>
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
                        <button type="submit" class="btn btn-sm btn-navy px-4 shadow-sm">
                            <i class="fas fa-sync-alt mr-1"></i> Update Report
                        </button>
                        <a href="{{ route('reports.by_line_item') }}" class="btn btn-sm btn-default border ml-2">
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
        <div class="card-header bg-white py-3" style="cursor: pointer;" data-card-widget="collapse">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="card-title text-navy font-weight-bold">
                    <i class="fas fa-folder-open mr-2 text-secondary"></i>
                    {{ $source['source_name'] }}
                </h3>
                <div class="card-tools d-flex align-items-center">
                    <div class="text-right mr-3">
                        <span class="text-uppercase text-xs text-muted d-block">Total Allocation</span>
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
            <div class="table-responsive" style="max-height: 500px;">
                <table class="table table-sm table-hover table-sticky mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase text-xs">
                            <th class="pl-4 py-3" style="width: 25%">Activity Details</th>
                            <th class="text-right py-3">Allocated Budget</th>
                            <th class="text-right py-3 bg-light border-left-info">Obligated</th>
                            <th class="text-center py-3 bg-light">Oblig. Rate(%)</th>
                            <th class="text-right py-3 border-left-success">Disbursed</th>
                            <th class="text-center py-3">Disb. Rate(%)</th>
                            <th class="text-right py-3 border-left-primary">Unobligated/Savings</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($source['line_items'] as $item)
                        <tr>
                            <td class="pl-4 align-middle">
                                <span class="font-weight-600 text-dark">{{ $item['name'] }}</span>
                            </td>
                            <td class="text-right align-middle financial-number text-muted">
                                {{-- Show current working budget --}}
                                ₱{{ number_format($item['activity_budget'], 2) }}

                                {{-- If there was a realignment, show the original budget as a small note --}}
                                @if(round($item['original_budget'], 2) != round($item['activity_budget'], 2))
                                    <div class="text-xs text-primary" title="Original Budget before realignment">
                                        <i class="fas fa-history mr-1"></i>Orig: ₱{{ number_format($item['original_budget'], 2) }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-right align-middle financial-number text-info font-weight-bold border-left-info">
                                ₱{{ number_format($item['obligated_amount'], 2) }}
                            </td>
                            
                            <td class="text-center align-middle bg-light">
                                <span class="badge {{ $item['obligation_rate'] >= 90 ? 'badge-success' : ($item['obligation_rate'] > 0 ? 'badge-warning' : 'badge-danger') }} shadow-none" style="width: 50px;">
                                    {{ number_format($item['obligation_rate'], 1) }}%
                                </span>
                            </td>
                            <td class="text-right align-middle financial-number text-success font-weight-bold border-left-success">
                                ₱{{ number_format($item['disbursed_amount'], 2) }}
                            </td>
                            <td class="text-center align-middle">
                                <span class="badge {{ $item['disbursement_rate'] >= 90 ? 'badge-success' : ($item['disbursement_rate'] > 0 ? 'badge-warning' : 'badge-danger') }} shadow-none" style="width: 50px;">
                                    {{ number_format($item['disbursement_rate'], 1) }}%
                                </span>
                            </td>
                            <td class="text-right align-middle financial-number text-primary font-weight-bold border-left-primary">
                                ₱{{ number_format($item['unobligated'], 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                                No activities recorded for this source.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    
                    @if(count($source['line_items']) > 0)
                    <tfoot class="bg-navy-light shadow-sm">
                        <tr class="font-weight-bold">
                            <td class="text-center text-uppercase small align-middle py-3">
                                <span class="text-navy">Consolidated Summary</span>
                            </td>
                            <td class="text-right align-middle financial-number">
                                ₱{{ number_format($source['total_activity_budget'], 2) }}
                            </td>
                            <td class="text-right text-info align-middle financial-number border-left-info">
                                ₱{{ number_format($source['total_obligated'], 2) }}
                            </td>
                            
                            <td class="text-center align-middle">
                                <div class="progress progress-xxs mb-1 mx-auto" style="width: 60px; background: #dee2e6;">
                                    <div class="progress-bar bg-info" style="width: {{ $source['overall_oblig_rate'] }}%"></div>
                                </div>
                                <span class="text-xs text-navy">{{ number_format($source['overall_oblig_rate'], 1) }}%</span>
                            </td>
                            <td class="text-right text-success align-middle financial-number border-left-success">
                                ₱{{ number_format($source['total_disbursed'], 2) }}
                            </td>
                            <td class="text-center align-middle">
                                <div class="progress progress-xxs mb-1 mx-auto" style="width: 60px; background: #dee2e6;">
                                    <div class="progress-bar bg-success" style="width: {{ $source['overall_disb_rate'] }}%"></div>
                                </div>
                                <span class="text-xs text-navy">{{ number_format($source['overall_disb_rate'], 1) }}%</span>
                            </td>
                            <td class="text-right text-primary align-middle financial-number border-left-primary">
                                ₱{{ number_format($source['total_unobligated'], 2) }}
                            </td>
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