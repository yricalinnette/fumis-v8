<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CosSalaryDisbursement extends Model
{
    protected $table = 'cos_salary_disbursements';

    protected $fillable = [
        'fund_id',
        'amount',
        'disbursement_date',
        'column_index',
    ];

    public function fund()
    {
        return $this->belongsTo(Fund::class, 'fund_id');
    }
}