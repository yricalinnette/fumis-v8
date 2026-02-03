<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model {
    protected $fillable = ['source_of_fund_id', 'name', 'budget'];

    public function source() {
        return $this->belongsTo(SourceOfFund::class, 'source_of_fund_id');
    }

    public function funds() {
        return $this->hasMany(Fund::class);
    }
}
