<div class="modal fade" id="addFundModal" role="dialog" aria-labelledby="addFundModalLabel">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-file-invoice-dollar mr-2"></i>New Transaction Log</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="fund-form">
                @csrf
                <input type="hidden" name="_method" id="form_method" value="POST">
                <input type="hidden" name="fund_id" id="edit_fund_id">
                
                <div class="modal-body">
                    {{-- Global Transaction Details --}}
                    <div class="row border-bottom pb-3 mb-3">
                        <div class="col-md-3 form-group">
                            <label class="required font-weight-bold">DTRACK NO.</label>
                            <input type="text" name="dtrack_no" id="dtrack_input" class="form-control" value="{{ date('Y') }}-" maxlength="11" required>
                            <div class="invalid-feedback font-weight-bold">This DTRACK number is already registered.</div>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="required font-weight-bold">Transaction Date</label>
                            <input type="date" name="transaction_date" id="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">
                                <i class="fas fa-users mr-1 text-primary"></i> Creditors / Payees 
                                <small class="text-muted ml-1">
                                    @if(auth()->user()->is_admin)
                                        (All Personnel)
                                    @else
                                        {{-- This shows the section name we joined in the controller --}}
                                        (Section: {{ $employees->first()->secname ?? 'My Section' }})
                                    @endif
                                </small>
                            </label>
                            
                            <select name="creditor_ids[]" id="creditor_select" class="form-control select2" multiple="multiple" style="width: 100%;" data-placeholder="Select creditors...">
                                @foreach($employees as $emp)
                                    {{-- We use the 'fullname' attribute created in the controller map --}}
                                    <option value="{{ $emp->dbedid }}">
                                        {{ $emp->fullname }} {{ $emp->suffix }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 form-group">
                            <label class="required font-weight-bold">Particulars</label>
                            <textarea name="particulars" id="particulars_input" class="form-control" rows="2" placeholder="Describe the purpose of this transaction..." required></textarea>
                        </div>
                    </div>

                    {{-- Dynamic Funding Allocation --}}
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="text-primary font-weight-bold mb-0"><i class="fas fa-layer-group mr-1"></i> Fund Allocations</h6>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-allocation-row">
                            <i class="fas fa-plus-circle"></i> Add Funding Source
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered bg-light" id="allocation-table">
                            <thead class="thead-dark">
                                <tr>
                                    <th style="width: 25%">Source of Fund</th>
                                    <th style="width: 40%">Activity / Line Item</th>
                                    <th style="width: 25%">Amount Charged</th>
                                    <th style="width: 10%" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="allocation-body">
                                <tr class="allocation-row">
                                    <td>
                                        </td>
                                    <td>
                                        <select name="allocations[0][activity_id]" class="form-control activity-select" required disabled>
                                            <option value="">-- Select Source First --</option>
                                        </select>
                                        <div class="budget-warning mt-1" style="min-height: 18px; font-size: 0.75rem;"></div>
                                    </td>
                                    <td>
                                        </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm remove-row" disabled><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-white">
                                    <th colspan="2" class="text-right">Grand Total:</th>
                                    <th class="text-primary h5 font-weight-bold" id="grand-total-display">₱ 0.00</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <div class="mt-2 px-2">
                        <div class="alert alert-light border-0 small text-muted py-1" style="background: #f8f9fa;">
                            <i class="fas fa-info-circle mr-1"></i> 
                            <strong>Note:</strong> Available balance is calculated based on obligated amounts if available; otherwise, the original processed amount is used.
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" id="submit-btn" class="btn btn-success shadow-sm">
                        <i class="fas fa-save mr-1"></i> Submit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>