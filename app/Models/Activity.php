<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model {
    protected $fillable = [
    'budget_line_item_id', 
    'objective', 
    'name', 
    'budget', 
    'source_of_fund_id',
    'uacs_code',
    'budget_adjusted',
    'pooled_amount',
    'pooled_remarks',
    'start_date',
    'end_date',
    'target_quarters',
    'uacs_code_id',
    'physical_targets',
    'classification',
    'user_id',
    'section_id'
];

    public function source() {
        return $this->belongsTo(SourceOfFund::class, 'source_of_fund_id');
    }

    public function funds()
    {
        return $this->hasMany(Fund::class, 'source_of_fund_id');
    }

    public function transactions()
    {
        return $this->hasMany(Fund::class, 'transaction_type_id');
    }

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'target_quarters' => 'array', 
        'physical_targets' => 'array',
    ];

    public function uacsCode()
    {
        return $this->belongsTo(UacsCode::class, 'uacs_code_id');
    }

    public function budgetLineItem()
    {
        // Ensure the foreign key matches your DB (budget_line_item_id)
        return $this->belongsTo(BudgetLineItem::class, 'budget_line_item_id');
    }
}
