<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundCancellation extends Model
{
    protected $fillable = [
        'fund_id',
        'fund_multi_activity_id',
        'source_of_fund_id',
        'dtrack_no',
        'original_obligation_serial',
        'cancellation_obligation_serial',
        'cancellation_date',
        'cancelled_amount',
        'reason',
        'cancelled_by',
    ];

    protected $casts = [
        'cancellation_date' => 'date',
        'cancelled_amount'  => 'decimal:2',
    ];

    public function fund()
    {
        return $this->belongsTo(Fund::class);
    }

    public function fundSource()
    {
        return $this->belongsTo(SourceOfFund::class, 'source_of_fund_id');
    }
}