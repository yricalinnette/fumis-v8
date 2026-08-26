<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Activity;
use App\Models\ProcurementTransaction;
use App\Models\ProcurementTransactionItem;
use App\Models\SourceOfFund;
use App\Models\Fund;
use Illuminate\Support\Facades\DB;

class BudgetProcurementController extends Controller
{
    // 1. List fund transactions whose activity is flagged as procurable
    public function index(Request $request)
    {
        $isAdminOrBudget = \Illuminate\Support\Facades\Gate::allows('budget-section');
        if (!$isAdminOrBudget) {
            abort(403, 'Unauthorized action. Procurement and CAF tracking are restricted to the Budget Unit and Admin.');
        }
        
        $search = $request->get('search');
        $sectionId = $request->get('section_id');
        $cafStatus = $request->get('caf_status');

        $procurableFunds = \App\Models\Fund::whereHas('activity', function ($query) {
                $query->where('is_for_procurement', 1);
            })
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('dtrack_no', 'LIKE', "%{$search}%")
                    ->orWhere('particulars', 'LIKE', "%{$search}%")
                    ->orWhere('creditor', 'LIKE', "%{$search}%");
                });
            })
            ->when($sectionId, function ($query, $sectionId) {
                $query->where('secid', $sectionId);
            })
            ->when($cafStatus, function ($query, $cafStatus) {
                $query->whereHas('prTransactions', function ($prQuery) use ($cafStatus) {
                    $prQuery->where('caf_status', $cafStatus);
                });
            })
            ->with([
                'activity.budgetLineItem', 
                'fundSource', 
                'prTransactions.items' => function($q) {
                    $q->orderBy('created_at', 'desc');
                }
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->appends($request->all());

        // Fetch active sections from db_common connection
        try {
            $allSections = DB::connection('db_common')
                ->table('tbl_section')
                ->where('isactive', 1)
                ->orderBy('secname', 'asc')
                ->pluck('secname', 'secid')
                ->toArray();
        } catch (\Exception $e) {
            $allSections = [];
        }

        return view('admin.budget.procurable_index', compact('procurableFunds', 'allSections', 'search', 'sectionId', 'cafStatus'));
    }

    // 2. Store PR Transaction & line items
    public function storeTransaction(Request $request, $activityId)
    {
        $request->validate([
            'source_of_fund_id'   => 'required|exists:source_of_funds,id',
            'item_name'           => 'required|string|max:255',
            'abc_allotment'       => 'required|numeric|min:0',
            'pr_number'           => 'nullable|string|max:255',
            'caf_date'            => 'required|date',
            'fund_transaction_id' => 'required|exists:funds,id', 
            'items'               => 'nullable|array',
        ]);

        $transaction = \App\Models\ProcurementTransaction::create([
            'activity_id'         => $activityId,
            'fund_transaction_id' => $request->fund_transaction_id, 
            'source_of_fund_id'   => $request->source_of_fund_id,
            'item_name'           => $request->item_name,
            'end_user'            => $request->end_user,
            'abc_allotment'       => $request->abc_allotment,
            'pr_number'           => $request->pr_number,
            'caf_date'            => $request->caf_date,
            'remarks'             => $request->remarks,
            'caf_status'          => 'Tagged as CAF',
        ]);

        // Save optional item breakdown...
        if ($request->has('items') && is_array($request->items)) {
            $validItems = array_filter($request->items, function ($item) {
                return !empty($item['name']) && isset($item['amount']);
            });

            if (count($validItems) > 0) {
                $itemsSum = array_sum(array_column($validItems, 'amount'));
                $abcAllotment = (float) $request->abc_allotment;

                // Check if items total matches ABC Allotment
                if (abs($itemsSum - $abcAllotment) >= 0.01) {
                    return redirect()->back()
                        ->withInput()
                        ->withErrors([
                            'items' => 'The total sum of items breakdown (₱' . number_format($itemsSum, 2) . 
                                        ') does not match the ABC Allotment amount (₱' . number_format($abcAllotment, 2) . ').'
                        ]);
                }

                // --- ADDED: Loop through and save the items to the database ---
                foreach ($validItems as $item) {
                    $transaction->items()->create([
                        'pr_item_name' => $item['name'],
                        'pr_number'    => $item['pr_number'] ?? $request->pr_number,
                        'item_amount'  => $item['amount'],
                    ]);
                }
            }
        }

        return redirect()->back()->with('success', 'PR Transaction successfully tagged as CAF.');
    }

    public function updatePoDetails(Request $request, $id)
    {
        $request->validate([
            'po_number' => 'nullable|string|max:255',
            'po_date' => 'nullable|date',
            'po_dtrack_no' => 'nullable|string|max:255',
            'po_amount' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.po_number' => 'nullable|string|max:255',
            'items.*.po_date' => 'nullable|date',
            'items.*.po_dtrack_no' => 'nullable|string|max:255',
            'items.*.po_amount' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
        ]);

        $transaction = \App\Models\ProcurementTransaction::findOrFail($id);
        $totalPoAmount = 0;

        if ($request->has('items') && is_array($request->items)) {
            foreach ($request->items as $itemId => $itemData) {
                $item = \App\Models\ProcurementTransactionItem::find($itemId);
                if ($item) {
                    $itemPoAmt = (isset($itemData['po_amount']) && $itemData['po_amount'] !== '') 
                        ? (float)$itemData['po_amount'] 
                        : null;

                    // Saves po_number, po_date, po_dtrack_no, and po_amount to each item
                    $item->update([
                        'po_number'    => $itemData['po_number'] ?? null,
                        'po_date'      => $itemData['po_date'] ?? null,
                        'po_dtrack_no' => $itemData['po_dtrack_no'] ?? null,
                        'po_amount'    => $itemPoAmt,
                    ]);

                    if ($itemPoAmt !== null) {
                        $totalPoAmount += $itemPoAmt;
                    }
                }
            }
        } else {
            $totalPoAmount = $request->po_amount ?? 0;
        }

        $transaction->update([
            'po_number'    => $request->po_number ?? $transaction->po_number,
            'po_date'      => $request->po_date ?? $transaction->po_date,
            'po_dtrack_no' => $request->po_dtrack_no ?? $transaction->po_dtrack_no,
            'po_amount'    => $totalPoAmount,
            'remarks'      => $request->remarks,
            'caf_status'   => 'Tagged as PO',
        ]);

        return redirect()->back()->with('success', 'PO details, dates, and item obligations updated successfully.');
    }

    public function updateTransaction(Request $request, $id)
    {
        $request->validate([
            'caf_date'            => 'required|date',
            'end_user'            => 'nullable|string|max:255',
            'abc_allotment'       => 'required|numeric|min:0',
            'pr_number'           => 'nullable|string|max:255', 
            'fund_transaction_id' => 'nullable|exists:funds,id',
            'remarks'             => 'nullable|string',
            'items'               => 'nullable|array',
        ]);

        $transaction = \App\Models\ProcurementTransaction::findOrFail($id);

        // Prevent overwriting with a wrong ID: Prefer the submitted valid ID, 
        // but fallback strictly to its existing database link if none is provided.
        $fundTransactionId = $request->filled('fund_transaction_id') 
            ? $request->fund_transaction_id 
            : $transaction->fund_transaction_id;

        // Update parent transaction details
        $transaction->update([
            'caf_date'            => $request->caf_date,
            'end_user'            => $request->end_user,
            'abc_allotment'       => $request->abc_allotment,
            'pr_number'           => $request->pr_number, 
            'fund_transaction_id' => $fundTransactionId,
            'remarks'             => $request->remarks,
        ]);

        // Item sync loop...
        $submittedItemIds = [];
        if ($request->has('items') && is_array($request->items)) {
            foreach ($request->items as $key => $itemData) {
                $itemName = $itemData['name'] ?? null;
                $itemAmount = $itemData['amount'] ?? null;

                if (!empty($itemName) && isset($itemAmount)) {
                    $itemId = $itemData['id'] ?? (is_numeric($key) ? $key : null);

                    if ($itemId) {
                        $existingItem = \App\Models\ProcurementTransactionItem::where('id', $itemId)
                            ->where('procurement_transaction_id', $transaction->id)
                            ->first();

                        if ($existingItem) {
                            $existingItem->update([
                                'pr_item_name' => $itemName,
                                'pr_number'    => $itemData['pr_number'] ?? $request->pr_number,
                                'item_amount'  => $itemAmount,
                            ]);
                            $submittedItemIds[] = $existingItem->id;
                            continue;
                        }
                    }

                    $newItem = $transaction->items()->create([
                        'pr_item_name' => $itemName,
                        'pr_number'    => $itemData['pr_number'] ?? $request->pr_number, 
                        'item_amount'  => $itemAmount,
                    ]);
                    $submittedItemIds[] = $newItem->id;
                }
            }
        }

        if (count($submittedItemIds) > 0) {
            $transaction->items()->whereNotIn('id', $submittedItemIds)->delete();
        } else {
            $transaction->items()->delete();
        }

        return redirect()->back()->with('success', 'CAF Transaction updated successfully.');
    }

    // 3. CAF Monitoring and Savings Summary
    public function cafTrackingIndex(Request $request)
    {
        $isAdminOrBudget = \Illuminate\Support\Facades\Gate::allows('budget-section');
        if (!$isAdminOrBudget) {
            abort(403, 'Unauthorized action. Procurement and CAF tracking are restricted to the Budget Unit and Admin.');
        }

        $transactions = \App\Models\ProcurementTransaction::with(['items', 'fundSource', 'activity'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate total amount for items / transactions considered "Tagged as CAF" (where po_amount is blank/null)
        $unobligatedCafTotal = 0;
        foreach ($transactions as $trx) {
            if ($trx->items && $trx->items->count() > 0) {
                foreach ($trx->items as $item) {
                    if (is_null($item->po_amount)) {
                        $unobligatedCafTotal += (float) $item->item_amount;
                    }
                }
            } else {
                if (is_null($trx->po_amount)) {
                    $unobligatedCafTotal += (float) $trx->abc_allotment;
                }
            }
        }

        $grandTotalSavings = $transactions->sum(function($trx) {
            if ($trx->items && $trx->items->count() > 0) {
                return $trx->items->sum(function($i) {
                    return !is_null($i->po_amount) ? max(0, $i->item_amount - $i->po_amount) : 0;
                });
            }
            return !is_null($trx->po_amount) ? max(0, $trx->abc_allotment - $trx->po_amount) : 0;
        });

        return view('admin.budget.caf_tracking', compact('transactions', 'unobligatedCafTotal', 'grandTotalSavings'));
    }
}