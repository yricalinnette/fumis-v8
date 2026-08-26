<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementTransactionItem extends Model
{
    protected $fillable = [
        'procurement_transaction_id',
        'pr_item_name',
        'item_amount',
        'pr_number',
        'po_number',
        'po_dtrack_no',
        'po_amount',
        'po_date',
    ];

    public function transaction()
    {
        return $this->belongsTo(ProcurementTransaction::class, 'procurement_transaction_id');
    }

    /**
     * Calculate item-level savings: ABC Amount - PO Amount
     */
    public function getSavingsAttribute()
    {
        if (is_null($this->po_amount)) {
            return 0;
        }
        return max(0, $this->item_amount - $this->po_amount);
    }
}