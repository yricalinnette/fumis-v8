<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NorsaAdjustment extends Model
{
    protected $fillable = [
        'fund_id',
        'dtrack_no',
        'obligation_serial',
        'creditor',
        'particulars',
        'entry_date',
        'amount',
        'source_of_fund_id',
    ];

    /**
     * Relationship: A NORSA adjustment belongs to a parent Fund record.
     */
    public function fund()
    {
        return $this->belongsTo(Fund::class, 'fund_id');
    }

    /**
     * Relationship: Belongs to a Source of Fund.
     */
    public function sourceOfFund()
    {
        return $this->belongsTo(SourceOfFund::class, 'source_of_fund_id');
    }
}