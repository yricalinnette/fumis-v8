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

    public function funds() {
        return $this->hasMany(Fund::class);
    }
}
