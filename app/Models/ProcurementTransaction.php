<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcurementTransaction extends Model
{
    use HasFactory;

    protected $table = 'procurement_transactions';

    protected $fillable = [
        'fund_transaction_id',
        'source_of_fund_id',
        'activity_id',
        'item_name',
        'end_user',
        'abc_allotment',
        'pr_number',
        'po_particulars',
        'po_number',
        'po_dtrack_no',
        'po_amount',
        'po_date',
        'obligation_serial',
        'obligation_amount',
        'obligation_date',
        'disbursement_amount',
        'disbursement_date',
        'status',
        'remarks',
        'caf_status',
        'caf_date',
    ];

    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function fundSource()
    {
        return $this->belongsTo(SourceOfFund::class, 'source_of_fund_id');
    }

    public function items()
    {
        return $this->hasMany(ProcurementTransactionItem::class, 'procurement_transaction_id');
    }

    /**
     * Calculate total savings for the transaction
     */
    public function getTotalSavingsAttribute()
    {
        if ($this->items && $this->items->count() > 0) {
            return $this->items->sum(function ($item) {
                return $item->savings;
            });
        }

        if (!is_null($this->po_amount)) {
            return max(0, $this->abc_allotment - $this->po_amount);
        }

        return 0;
    }

    public function fund()
    {
        return $this->belongsTo(\App\Models\Fund::class, 'fund_transaction_id');
    }

    /**
     * Accessor: Collect all unique PO numbers from parent or item breakdown.
     */
    public function getUniquePoNumbersAttribute()
    {
        $poNumbers = collect();
        if (!empty($this->po_number)) {
            $poNumbers->push($this->po_number);
        }
        if ($this->items) {
            foreach ($this->items as $item) {
                if (!empty($item->po_number)) {
                    $poNumbers->push($item->po_number);
                }
            }
        }
        return $poNumbers->unique()->filter()->values();
    }

    /**
     * Accessor: Collect all unique PO DTrack numbers from parent or item breakdown.
     */
    public function getUniquePoDtracksAttribute()
    {
        $dtracks = collect();
        if (!empty($this->po_dtrack_no)) {
            $dtracks->push($this->po_dtrack_no);
        }
        if ($this->items) {
            foreach ($this->items as $item) {
                if (!empty($item->po_dtrack_no)) {
                    $dtracks->push($item->po_dtrack_no);
                }
            }
        }
        return $dtracks->unique()->filter()->values();
    }

    /**
     * Accessor: Check if all items (or single transaction) have an assigned PO amount.
     */
    public function getIsFullyObligatedAttribute()
    {
        if ($this->items && $this->items->count() > 0) {
            // Returns true only if every single item has a non-null po_amount
            return $this->items->every(fn($item) => !is_null($item->po_amount));
        }
        return !is_null($this->po_amount);
    }
}