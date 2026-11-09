<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundMultiActivity extends Model
{
    protected $table = 'fund_multi_activities';

    protected $fillable = [
        'fund_id',
        'source_of_fund_id',
        'transaction_type_id',
        'dtrack_no',
        'routed_amount',
        'obligation_serial',
        'creditor',
        'particulars',
        'obligation_amount',
        'disbursement_amount',
        'status',
        'cos_contract_id',
    ];

    public function parentFund()
    {
        return $this->belongsTo(Fund::class, 'fund_id');
    }

    public function cosContract()
    {
        return $this->belongsTo(CosContract::class, 'cos_contract_id');
    }

    public function fund()
    {
        return $this->belongsTo(Fund::class, 'fund_id');
    }

    public function sourceOfFund()
    {
        return $this->belongsTo(SourceOfFund::class, 'source_of_fund_id');
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class, 'transaction_type_id');
    }
}