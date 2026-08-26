<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FundDtrackLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'fund_id',
        'dtrack_no',
        'obligation_serial',
        'obligation_amount',
        'obligation_date',
        'disbursement_amount',
        'disbursement_date',
    ];

    public function fund()
    {
        return $this->belongsTo(Fund::class);
    }
}