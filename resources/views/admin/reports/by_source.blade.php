@extends('layouts.adminlte')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h4 class="text-navy m-0"><i class="fas fa-list-ol mr-2"></i>By Source Budget Tracking</h4>
            <hr>
            <div class="card card-outline card-info shadow">
                <!-- <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-file-invoice-dollar mr-2 text-navy"></i>
                        <strong>Budget Tracking by Source</strong>
                    </h3>
                </div> -->
                
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="bg-info">
                                <tr>
                                    <th style="width: 18%">Fund Source</th>
                                    <th class="text-right">Total Amount (Allotted)</th>
                                    <th class="text-right">Obligated</th>
                                    <th class="text-center">Obligation Rate</th>
                                    <th class="text-right">Disbursed</th>
                                    <th class="text-center">Disbursement Rate</th>
                                    <th class="text-right">Unobligated Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $totals = ['allotted' => 0, 'obligated' => 0, 'disbursed' => 0];
                                @endphp

                                @foreach($reportData as $data)
                                    @php
                                        $totals['allotted'] += $data['allotted'];
                                        $totals['obligated'] += $data['obligated'];
                                        $totals['disbursed'] += $data['disbursed'];
                                    @endphp
                                    <tr>
                                        <td><span class="text-navy font-weight-bold">{{ $data['name'] }}</span></td>
                                        <td class="text-right">₱{{ number_format($data['allotted'], 2) }}</td>
                                        <td class="text-right text-primary">₱{{ number_format($data['obligated'], 2) }}</td>
                                        <td class="text-center">
                                            <div class="progress progress-xs mb-1">
                                                <div class="progress-bar bg-primary" style="width: {{ min($data['obligation_rate'], 100) }}%"></div>
                                            </div>
                                            <span class="badge badge-primary">{{ number_format($data['obligation_rate'], 1) }}%</span>
                                        </td>
                                        <td class="text-right text-success">₱{{ number_format($data['disbursed'], 2) }}</td>
                                        <td class="text-center">
                                            <div class="progress progress-xs mb-1">
                                                <div class="progress-bar bg-success" style="width: {{ min($data['disbursement_rate'], 100) }}%"></div>
                                            </div>
                                            <span class="badge badge-success">{{ number_format($data['disbursement_rate'], 1) }}%</span>
                                        </td>
                                        <td class="text-right font-weight-bold {{ $data['balance'] < 0 ? 'text-danger' : '' }}">
                                            ₱{{ number_format($data['balance'], 2) }}
                                        </td>
                                        
                                        
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-light font-weight-bold">
                                <tr>
                                    <td>GRAND TOTAL</td>
                                    <td class="text-right">₱{{ number_format($totals['allotted'], 2) }}</td>
                                    <td class="text-right text-primary">₱{{ number_format($totals['obligated'], 2) }}</td>
                                    <td class="text-center">
                                        {{ $totals['allotted'] > 0 ? number_format(($totals['obligated'] / $totals['allotted']) * 100, 1) : 0 }}%
                                    </td>
                                    <td class="text-right text-success">₱{{ number_format($totals['disbursed'], 2) }}</td>
                                    <td class="text-center">
                                        {{ $totals['obligated'] > 0 ? number_format(($totals['disbursed'] / $totals['obligated']) * 100, 1) : 0 }}%
                                    </td>
                                    
                                    <td class="text-right">₱{{ number_format($totals['allotted'] - $totals['obligated'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection