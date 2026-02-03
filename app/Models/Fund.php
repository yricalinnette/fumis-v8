<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fund extends Model
{
    protected $fillable = [
        'dtrack_no',
        'source_of_fund_id',
        'transaction_type',
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

    // Relationship: A fund belongs to the user who created it
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creditors() {
        return $this->belongsToMany(Employee::class, 'employee_fund');
    }
    public function activities() {
        return $this->hasMany(Activity::class);
    }

    // Accessor to get total spent
    public function getTotalAllocatedAttribute() {
        return $this->activities()->sum('allocated_amount');
    }

    protected $casts = [
        'transaction_date' => 'date',
        'status_date' => 'date',
        'obligation_date' => 'date',
        'disbursement_date' => 'date',
    ];

    public function fundSource()
    {
        // Points to the source_of_fund_id foreign key
        return $this->belongsTo(SourceOfFund::class, 'source_of_fund_id');
    }
}
