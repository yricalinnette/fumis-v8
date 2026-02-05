<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model {
    protected $fillable = [
    'budget_line_item', 
    'objective', 
    'name', 
    'budget', 
    'source_of_fund_id'
];

    public function source() {
        return $this->belongsTo(SourceOfFund::class, 'source_of_fund_id');
    }

    public function funds()
    {
        // Replace 'your_actual_foreign_key' with the real column name in your funds table
        // It is likely 'source_of_fund_id' or 'activity_details_id'
        return $this->hasMany(Fund::class, 'source_of_fund_id');
    }
}
