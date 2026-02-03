<div class="modal fade" id="addFundModal" role="dialog" aria-labelledby="addFundModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-success">
        <h5 class="modal-title">New Transaction Log</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="fund-form">
        @csrf
        <input type="hidden" name="_method" id="form_method" value="POST">
        <input type="hidden" name="fund_id" id="edit_fund_id">
          <div class="modal-body">
                <div class="row">
                  <div class="col-md-6 form-group">
                    <label class="required">DTRACK NO.</label>
                    <input type="text" name="dtrack_no" id="dtrack_input" class="form-control" value="{{ date('Y') }}-" maxlength="11" required>
                    <div class="invalid-feedback font-weight-bold">
                        This DTRACK number is already registered in the system.
                    </div>
                  </div>
                  <div class="col-md-6 form-group">
                    <label class="required">Date</label>
                    <input type="date" name="transaction_date" id="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                  </div>

                  <div class="col-md-6 form-group">
                    <label class="required">Source of Fund</label>
                    <select name="source_of_fund_id" id="modal_source_select" class="form-control" required>
                        <option value="">-- Select Source --</option>
                        @foreach($sources as $source)
                            <option value="{{ $source->id }}">{{ $source->name }}</option>
                        @endforeach
                    </select>
                  </div>

                  <div class="col-md-6 form-group">
                    <label class="required">Transaction Type (Activity)</label>
                    <select name="activity_id" id="modal_activity_select" class="form-control" required disabled>
                        <option value="">-- Select Source First --</option>
                        @if(isset($activities))
                            @foreach($activities as $activity)
                                <option value="{{ $activity->id }}" data-source="{{ $activity->source_of_fund_id }}">
                                    {{ $activity->name }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                  </div>

                  <div class="col-md-6 form-group">
                      <label for="amount_display" class="required">Amount</label>
                      <div class="input-group">
                          <div class="input-group-prepend">
                              <span class="input-group-text">₱</span>
                          </div>
                          <input type="text" id="amount_display" class="form-control font-weight-bold" placeholder="0.00" inputmode="decimal" required>
                          
                          <input type="hidden" name="amount" id="amount_input">
                      </div>
                      <small id="budget-warning" class="form-text text-muted"></small> 
                  </div>
                  <div class="col-md-6 form-group">
                        <label ><i class="fas fa-users mr-1"></i> Creditors</label>
                        <select name="creditor_ids[]" id="creditor_select" class="form-control select2" multiple="multiple" style="width: 100%;">
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">You can select multiple staff members.</small>
                  </div>
                  <div class="col-12 form-group">
                      <label class="required">Particulars</label>
                      <textarea name="particulars" id="particulars_input" class="form-control" rows="2"></textarea>
                      <span class="invalid-feedback">Please provide transaction particulars.</span>
                  </div>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-success">Save Transaction</button>
          </div>
      </form>
    </div>
  </div>
</div>