<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fund extends Model
{
    protected $fillable = [
        'dtrack_no',
        'source_of_fund_id',
        'transaction_type_id',
        'particulars',
        'transaction_date',
        'amount',
        'remarks',
        'document_updates',
        'user_id',
        'secid',
        'status',
        'status_date',
        'obligation_serial',
        'obligation_date',
        'obligation_amount',
        'disbursement_date',
        'disbursement_amount',
    ];

    protected $casts = [
        'transaction_date' => 'date:Y-m-d',
        'status_date' => 'date',
        'obligation_date' => 'date',
        'disbursement_date' => 'date',
    ];

    /**
     * Relationship: The specific Activity/Transaction Type linked to this fund record
     */
    public function activity()
    {
        // This links transaction_type_id to the id on the activities table
        return $this->belongsTo(Activity::class, 'transaction_type_id');
    }

    /**
     * Relationship: The Library source of the fund (GAA, HIT, etc.)
     */
    public function fundSource()
    {
        return $this->belongsTo(SourceOfFund::class, 'source_of_fund_id');
    }

    /**
     * Relationship: A fund belongs to the user who created it
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Many-to-Many with Employees (Creditors)
     */
    public function creditors()
    {
        // Change from Employee::class to User::class
        // We specify 'employee_fund' as the table and 'user_id' as the new foreign key
        return $this->belongsToMany(User::class, 'employee_fund', 'fund_id', 'user_id');
    }

    public function groupAllocations()
    {
        // Fetches all funds sharing the same DTrack Number
        return $this->hasMany(Fund::class, 'dtrack_no', 'dtrack_no');
    }

    public function fund_sources() {
        return $this->belongsToMany(FundSource::class, 'fund_fund_source');
    }

    public function users()
    {
        // Change from Employee::class to User::class
        return $this->belongsToMany(User::class, 'employee_fund', 'fund_id', 'user_id');
    }
  
}