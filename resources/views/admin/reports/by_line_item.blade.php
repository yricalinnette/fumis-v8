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
            // 1. Determine the Net Working Budget for the entire Source
            $sourceTotalPooled = collect($source['line_items'])->sum('pooled_amount');
            $netSourceBudget = $source['total_activity_budget']; 
            // If your total_activity_budget isn't already net, use: 
            // $netSourceBudget = $source['source_total'] - $sourceTotalPooled;

            // 2. Calculation based on Net Fund Source (Requirement)
            $overallObligRate = $netSourceBudget > 0 
                ? ($source['total_obligated'] / $netSourceBudget) * 100 
                : 0;

            // 3. Overall Efficiency: Disbursement over Net Fund Source
            $overallEfficiency = $netSourceBudget > 0 
                ? ($source['total_disbursed'] / $netSourceBudget) * 100 
                : 0;

            $netTotalUnobligated = $netSourceBudget - $source['total_obligated'];
        @endphp
        
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
            <div class="p-4 bg-light border-bottom">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <label class="text-xs text-muted text-uppercase font-weight-bold mb-3 d-block">Source Performance Bullet Chart</label>
                        
                        <div class="bullet-chart-container position-relative mb-2" style="height: 40px;">
                            <div class="position-absolute w-100 h-100 shadow-sm" style="background: #e9ecef; border-radius: 4px; top:0; left:0;"></div>
                            
                            <div class="position-absolute h-100" 
                                style="width: {{ $overallObligRate }}%; background: #bee5eb; border-radius: 4px; top:0; left:0; transition: width 0.6s ease;"
                                title="Total Obligated: ₱{{ number_format($source['total_obligated'] , 1) }}"></div>
                            
                            <div class="position-absolute" 
                                style="width: {{ $overallEfficiency }}%; background: #28a745; height: 16px; top: 12px; left:0; z-index: 2; transition: width 0.8s ease;"
                                title="Total Disbursed: ₱{{ number_format($source['total_disbursed'] , 1) }}"></div>
                            
                            <div class="position-absolute h-100" style="width: 2px; background: #343a40; right: 0; top: 0; z-index: 3;"></div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <small class="text-muted"><i class="fas fa-square text-success mr-1"></i> Disbursed ({{ number_format($overallEfficiency, 1) }}%)</small>
                            <small class="text-muted"><i class="fas fa-square" style="color: #bee5eb;"></i> Obligated ({{ number_format($overallObligRate, 1) }}%)</small>
                            <small class="text-muted font-weight-bold">Total Budget: ₱{{ number_format($netSourceBudget, 0) }}</small>
                        </div>
                    </div>
                    
                    <div class="col-md-3 border-left text-center">
                        <span class="text-uppercase text-xs text-muted d-block">Efficiency</span>
                        <h2 class="font-weight-bold mb-0 text-navy">{{ number_format($overallEfficiency, 1) }}%</h2>
                        <small class="text-muted d-block mt-1" style="font-size: 0.7rem; line-height: 1;">
                            <i class="fas fa-calculator mr-1"></i> 
                            ₱{{ number_format($source['total_disbursed'], 0) }} / ₱{{ number_format($netSourceBudget, 0) }}
                        </small>
                    </div>
                    
                    <div class="col-md-3 border-left text-center">
                        <span class="text-uppercase text-xs text-muted d-block">Savings</span>
                        <h2 class="font-weight-bold mb-0 text-primary">₱{{ number_format($netTotalUnobligated, 0) }}</h2>
                        <small class="text-muted" style="font-size: 0.7rem;">Unobligated Balance</small>
                    </div>
                </div>
            </div>
            
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
                        @php
                            // 1. Calculate the Net Working Budget
                            $pooledAmount = $item['pooled_amount'] ?? 0;
                            $netBudget = $item['activity_budget'] - $pooledAmount;
                            
                            $obligated = $item['obligated_amount'] ?? 0;
                            $disbursed = $item['disbursed_amount'] ?? 0;

                            // 2. Dynamic Savings/Unobligated Calculation
                            // Logic: Use Disbursement if available (>0), otherwise use Obligation.
                            $isDisbursed = $disbursed > 0;
                            $unobligatedBalance = $isDisbursed 
                                ? ($netBudget - $disbursed) 
                                : ($netBudget - $obligated);
                            
                            // 3. Recalculate rates based on Net Budget
                            $currentObligRate = $netBudget > 0 ? ($obligated / $netBudget) * 100 : 0;
                            $currentDisbRate = $obligated > 0 ? ($disbursed / $obligated) * 100 : 0;

                            // 4. Identify if "Actual Savings" occurred (Price drop during payment)
                            $extraSavingsRealized = $isDisbursed && ($disbursed < $obligated);
                        @endphp
                        <tr>
                            <td class="pl-4 align-middle">
                                <span class="font-weight-600 text-dark d-block">{{ $item['name'] }}</span>
                                
                                {{-- Badge for Pooled Funds --}}
                                @if($pooledAmount > 0)
                                    <div class="mt-1">
                                        <span class="badge badge-danger text-xs px-2 shadow-sm" 
                                            title="{{ $item['pooled_remarks'] ?? 'No remarks provided' }}">
                                            <i class="fas fa-university mr-1"></i>Pooled: ₱{{ number_format($pooledAmount, 2) }}
                                        </span>
                                    </div>
                                @endif
                            </td>

                            <td class="text-right align-middle financial-number">
                                {{-- Primary Display: The Net Budget --}}
                                <div class="text-dark font-weight-bold">
                                    ₱{{ number_format($netBudget, 2) }}
                                </div>

                                {{-- Reference: The Original Allotment --}}
                                @if($pooledAmount > 0)
                                    <div class="text-xs text-muted" style="text-decoration: line-through;">
                                        Allotted: ₱{{ number_format($item['activity_budget'], 2) }}
                                    </div>
                                @endif

                                {{-- Realignment History --}}
                                @if(round($item['original_budget'], 2) != round($item['activity_budget'], 2))
                                    <div class="text-xs text-primary" title="Original Budget before realignment">
                                        <i class="fas fa-history mr-1"></i>Orig: ₱{{ number_format($item['original_budget'], 2) }}
                                    </div>
                                @endif
                            </td>

                            <td class="text-right align-middle financial-number text-info font-weight-bold border-left-info">
                                ₱{{ number_format($obligated, 2) }}
                            </td>
                            
                            <td class="text-center align-middle bg-light">
                                <span class="badge {{ $currentObligRate >= 90 ? 'badge-success' : ($currentObligRate > 0 ? 'badge-warning' : 'badge-danger') }} shadow-none" style="width: 50px;">
                                    {{ number_format($currentObligRate, 1) }}%
                                </span>
                            </td>

                            <td class="text-right align-middle financial-number text-success font-weight-bold border-left-success">
                                ₱{{ number_format($disbursed, 2) }}
                            </td>

                            <td class="text-center align-middle">
                                <span class="badge {{ $currentDisbRate >= 90 ? 'badge-success' : ($currentDisbRate > 0 ? 'badge-warning' : 'badge-danger') }} shadow-none" style="width: 50px;">
                                    {{ number_format($currentDisbRate, 1) }}%
                                </span>
                            </td>

                            <td class="text-right align-middle financial-number text-primary font-weight-bold border-left-primary">
                                <div>₱{{ number_format($unobligatedBalance, 2) }}</div>
                                
                                {{-- Visual indicator for actual savings --}}
                                @if($extraSavingsRealized)
                                    <span class="badge badge-success mt-1 shadow-sm" style="font-size: 10px;" title="Savings increased due to lower actual cost during disbursement">
                                        <i class="fas fa-arrow-up mr-1"></i>Actual Savings
                                    </span>
                                @endif
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
                        @php
                            // 1. Calculate the aggregate pooled amount for this source
                            $sourceTotalPooled = collect($source['line_items'])->sum('pooled_amount');
                            
                            // 2. Subtract it from the gross budget to get the NET budget for the summary
                            $netSourceBudget = $source['total_activity_budget'];

                            // 3. Recalculate overall rates based on the NET budget
                            $overallObligRate = $netSourceBudget > 0 ? ($source['total_obligated'] / $netSourceBudget) * 100 : 0;
                            $overallDisbRate = ($source['total_obligated'] > 0) 
                                ? ($source['total_disbursed'] / $source['total_obligated']) * 100 
                                : 0;
                            
                            // 4. Recalculate total unobligated (Net - Obligated)
                            $netTotalUnobligated = $netSourceBudget - $source['total_obligated'];
                        @endphp

                        <tfoot class="bg-navy-light shadow-sm">
                            <tr class="font-weight-bold">
                                <td class="text-center text-uppercase small align-middle py-3">
                                    <span class="text-navy">Consolidated Summary</span>
                                    @if($sourceTotalPooled > 0)
                                        <div class="text-xs text-danger mt-1">
                                            (₱{{ number_format($sourceTotalPooled, 2) }} Pooled)
                                        </div>
                                    @endif
                                </td>
                                
                                {{-- NET TOTAL BUDGET --}}
                                <td class="text-right align-middle financial-number">
                                    <div class="text-navy">₱{{ number_format($netSourceBudget, 2) }}</div>
                                    @if($sourceTotalPooled > 0)
                                        <small class="text-muted d-block" style="text-decoration: line-through; font-weight: normal;">
                                            ₱{{ number_format($source['original_source_total'], 2) }}
                                        </small>
                                    @endif
                                </td>

                                <td class="text-right text-info align-middle financial-number border-left-info">
                                    ₱{{ number_format($source['total_obligated'], 2) }}
                                </td>
                                
                                <td class="text-center align-middle">
                                    <div class="progress progress-xxs mb-1 mx-auto" style="width: 60px; background: #dee2e6;">
                                        <div class="progress-bar bg-info" style="width: {{ $overallObligRate }}%"></div>
                                    </div>
                                    <span class="text-xs text-navy">{{ number_format($overallObligRate, 1) }}%</span>
                                </td>

                                <td class="text-right text-success align-middle financial-number border-left-success">
                                    ₱{{ number_format($source['total_disbursed'], 2) }}
                                </td>

                                <td class="text-center align-middle">
                                    <div class="progress progress-xxs mb-1 mx-auto" style="width: 60px; background: #dee2e6;">
                                        <div class="progress-bar bg-success" style="width: {{ $overallDisbRate }}%"></div>
                                    </div>
                                    <span class="text-xs text-navy">{{ number_format($overallDisbRate, 1) }}%</span>
                                </td>

                                {{-- NET UNOBLIGATED --}}
                                <td class="text-right text-primary align-middle financial-number border-left-primary">
                                    ₱{{ number_format($netTotalUnobligated, 2) }}
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