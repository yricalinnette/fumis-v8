<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CosContract extends Model
{
    protected $fillable = [
        'creditor_name',
        'start_date',
        'end_date',
        'total_months',
        'monthly_remuneration',
        'premium_amount',
        'total_contract_amount',
        'status',
    ];

    // Relationship to Funds
    public function funds()
    {
        return $this->hasMany(Fund::class, 'cos_contract_id');
    }
}