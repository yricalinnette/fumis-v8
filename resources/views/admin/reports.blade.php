@extends('layouts.adminlte')

@section('header')
    <h1>Financial Reports</h1>
@endsection

@section('content')
<div class="card card-outline card-info">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-filter"></i> Report Filters</h3>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('reports.index') }}" class="row">
            <div class="col-md-3">
                <select name="month" class="form-control">
                    <option value="">-- Select Month (Monthly) --</option>
                    @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="quarter" class="form-control">
                    <option value="">-- Select Quarter --</option>
                    <option value="1">Q1 (Jan-Mar)</option>
                    <option value="2">Q2 (Apr-Jun)</option>
                    <option value="3">Q3 (Jul-Sep)</option>
                    <option value="4">Q4 (Oct-Dec)</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-info w-100">Generate</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-striped table-bordered">
            <thead class="bg-primary">
                <tr>
                    <th>DTRACK NO.</th>
                    <th>Source</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Updates</th>
                </tr>
            </thead>
            <tbody>
                @foreach($funds as $fund)
                <tr>
                    <td>{{ $fund->dtrack_no }}</td>
                    <td>{{ $fund->source_of_fund }}</td>
                    <td>{{ $fund->transaction_date }}</td>
                    <td class="text-right">₱{{ number_format($fund->amount, 2) }}</td>
                    <td><span class="badge badge-info">{{ $fund->document_updates }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection