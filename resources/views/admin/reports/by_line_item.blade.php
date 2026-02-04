@extends('layouts.adminlte')

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="text-navy m-0"><i class="fas fa-list-ol mr-2"></i>Line Item Budget Tracking</h4>
            <div>
                <button class="btn btn-sm btn-outline-navy mr-1" onclick="$('.card').CardWidget('expand')">
                    <i class="fas fa-expand-arrows-alt"></i> Expand All
                </button>
                <button class="btn btn-sm btn-outline-navy mr-1" onclick="$('.card').CardWidget('collapse')">
                    <i class="fas fa-compress-arrows-alt"></i> Collapse All
                </button>
                <!-- <button class="btn btn-sm btn-default border shadow-sm" onclick="window.print()">
                    <i class="fas fa-print"></i>
                </button> -->
            </div>
        </div>
        <div class="col-12"><hr></div>
    </div>

    @foreach($reportData as $source)
    <div class="card card-outline card-navy shadow-sm mb-4">
        <div class="card-header bg-info" style="cursor: pointer;" data-card-widget="collapse">
            <h3 class="card-title">
                <i class="fas fa-file-invoice-dollar mr-2 text-info"></i>
                <strong>{{ $source['source_name'] }}</strong>
            </h3>
            <div class="card-tools">
                <span class="badge badge-light mr-2">Total Fund: ₱{{ number_format($source['source_total'], 2) }}</span>
                <button type="button" class="btn btn-tool text-white" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 30%">Activity</th>
                            <th class="text-right">Activity Budget</th>
                            <th class="text-right">Obligated Amount</th>
                            <th class="text-center">Obligation Rate</th>
                            <th class="text-right">Disbursed Amount</th>
                            <th class="text-center">Disbursement Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($source['line_items'] as $item)
                        <tr>
                            <td>{{ $item['name'] }}</td>
                            <td class="text-right">₱{{ number_format($item['activity_budget'], 2) }}</td>
                            <td class="text-right text-primary">₱{{ number_format($item['obligated_amount'], 2) }}</td>
                            <td class="text-center">
                                <span class="badge badge-info shadow-sm">{{ number_format($item['obligation_rate'], 1) }}%</span>
                            </td>
                            <td class="text-right text-success">₱{{ number_format($item['disbursed_amount'], 2) }}</td>
                            <td class="text-center">
                                <span class="badge {{ $item['disbursement_rate'] >= 90 ? 'badge-success' : ($item['disbursement_rate'] > 0 ? 'badge-warning' : 'badge-danger') }} shadow-sm">
                                    {{ number_format($item['disbursement_rate'], 1) }}%
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No activities recorded for this source.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    
                    @if(count($source['line_items']) > 0)
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <td class="text-center text-uppercase">SUMMARY FOR {{ $source['source_name'] }}</td>
                            <td class="text-right">₱{{ number_format($source['total_activity_budget'], 2) }}</td>
                            <td class="text-right text-primary">₱{{ number_format($source['total_obligated'], 2) }}</td>
                            <td class="text-center">
                                <div class="progress progress-xs mb-1 mx-auto" style="width: 60px;">
                                    <div class="progress-bar bg-info" style="width: {{ $source['overall_oblig_rate'] }}%"></div>
                                </div>
                                <span class="badge badge-dark">{{ number_format($source['overall_oblig_rate'], 1) }}%</span>
                            </td>
                            <td class="text-right text-success">₱{{ number_format($source['total_disbursed'], 2) }}</td>
                            <td class="text-center">
                                <div class="progress progress-xs mb-1 mx-auto" style="width: 60px;">
                                    <div class="progress-bar bg-success" style="width: {{ $source['overall_disb_rate'] }}%"></div>
                                </div>
                                <span class="badge badge-dark">{{ number_format($source['overall_disb_rate'], 1) }}%</span>
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