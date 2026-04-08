<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetLineItem extends Model
{
    use HasFactory;

    // ADD THIS SECTION:
    protected $fillable = [
        'budget_line_item_name', 
        'is_active'
    ];

    /**
     * Relationship: One Budget Line Item has many Source of Funds.
     */
    public function fundSources()
    {
        return $this->hasMany(SourceOfFund::class, 'budget_line_item_id');
    }
}