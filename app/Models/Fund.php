<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fund extends Model
{
    protected $fillable = [
        'dtrack_no',
        'source_of_fund_id',
        'transaction_type_id', // Now properly mapped to the activities table
        'particulars',
        'transaction_date',
        'amount',
        'remarks',
        'document_updates',
        'user_id',
        'status',
        'status_date',
        'obligation_serial',
        'obligation_date',
        'obligation_amount',
        'disbursement_date',
        'disbursement_amount',
    ];

    protected $casts = [
        'transaction_date' => 'date',
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
        return $this->belongsToMany(Employee::class, 'employee_fund');
    }

    // --- Cleaned up the incorrect hasMany relationship ---
}