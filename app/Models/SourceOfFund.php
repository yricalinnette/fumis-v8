<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceOfFund extends Model
{
    protected $table = 'source_of_funds';
    protected $fillable = [
        'name', 
        'fiscal_year',
        'total_amount', 
        'spreadsheet_id', 
        'sheet_name',
        'budget_line_item_id', 
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
