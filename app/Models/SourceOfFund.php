<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceOfFund extends Model
{
    protected $table = 'source_of_funds';
    protected $fillable = [
        'budget_line_item_id',
        'source_type',
        'name',
        'entity_name',
        'saa_date',
        'reference_number',
        'fund_code',
        'approp_code',
        'allotment_class',
        'fiscal_year',
        'total_amount',
        'spreadsheet_id',
        'sheet_name',
        'is_active'
    ];
    
    protected $casts = [
        'saa_date' => 'date',
        'total_amount' => 'decimal:2'
    ];

    /**
     * Get the activities for the fund source.
     */
    public function activities()
    {
        // One Source can have Many Activities
        return $this->hasMany(Activity::class, 'source_of_fund_id');
    }

    public function funds()
    {
        // This assumes your 'funds' table has a 'source_of_fund_id' column
        return $this->hasMany(Fund::class, 'source_of_fund_id');
    }

    public function budgetLineItem()
    {
        return $this->belongsTo(BudgetLineItem::class, 'budget_line_item_id');
    }

}
