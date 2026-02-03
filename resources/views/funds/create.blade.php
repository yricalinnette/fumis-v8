@extends('layouts.adminlte')

@section('header')
    <h1>Log New Transaction</h1>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Fund Details</h3>
            </div>
            <form action="{{ route('funds.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label>DTRACK NO.</label>
                            <input type="text" name="dtrack_no" class="form-control" placeholder="Enter Tracking No.">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Source of Fund</label>
                            <input type="text" name="source_of_fund" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Amount</label>
                            <input type="number" step="0.01" name="amount" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>Creditors</label>
                            <input type="text" name="creditor" class="form-control">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Transaction Type</label>
                            <select name="transaction_type" class="form-control">
                                <option>Cash</option>
                                <option>Check</option>
                                <option>Bank Transfer</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Particulars</label>
                        <textarea name="particulars" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="transaction_date" class="form-control">
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Submit Record</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection