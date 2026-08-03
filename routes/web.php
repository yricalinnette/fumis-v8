<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FundController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ActivityController;

/*
|--------------------------------------------------------------------------
| Web Routes - Security Hardened
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});

// ==========================================================
// GROUP A: ALL STAFF (General Access)
// Apply Global Throttling to prevent automated scraping
// ==========================================================
Route::middleware(['auth', 'verified', 'throttle:60,1'])->group(function () {
    
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Funds & Transactions Controller Group
    Route::controller(FundController::class)->group(function () {
        Route::get('/funds', 'index')->name('funds.index');
        Route::get('/funds/create', 'create')->name('funds.create');
        Route::post('/funds/store', 'store')->name('funds.store');
        Route::get('/funds/{fund}/edit', 'edit')->name('funds.edit');
        Route::put('/funds/{fund}', 'update')->name('funds.update');
        Route::delete('/funds/{id}', 'destroy')->name('funds.destroy');
        
        // Data Sync & API (Internal)
        Route::get('/funds/group/{dtrack}', 'getGroupByDtrack');
        Route::patch('/funds/{id}/status', 'updateStatus')->name('funds.updateStatus');
        Route::get('/funds/awaiting-obligation', 'getAwaitingObligation')->name('funds.awaiting');
        Route::patch('/funds/{id}/update-transaction-type', [FundController::class, 'updateTransactionType'])->name('funds.updateTransactionType');
        Route::patch('/funds/{id}/update-manual-remarks', [FundController::class, 'updateManualRemarks'])
            ->name('funds.update_manual_remarks');

        // RESTORED ROUTE: Required by index.blade.php
        Route::get('/funds/sync-count', function() {
            $count = \App\Models\Fund::where('status', 'Obligated')
                ->whereNull('disbursement_date')
                ->count();
            return response()->json(['count' => $count]);
        })->name('funds.sync-count');

        Route::get('/funds/check-balance', 'checkBalance')->name('funds.check_balance');
        
        // Critical Operations - INCREASED THROTTLING to handle polling and bulk processing
        // We increase this from 10 to 1000 to prevent the "Too Many Attempts" error
        Route::middleware('throttle:1000,1')->group(function () {
            Route::get('funds/{id}/sync', 'syncWithGoogleSheet')->name('funds.sync');
            Route::post('/funds/bulk-sync', 'bulkSync')->name('funds.bulk-sync');
            Route::get('/funds/sync-progress', 'getSyncProgress')->name('funds.sync-progress');
            Route::post('/funds/sync-cancel', 'cancelSync')->name('funds.sync-cancel');
            Route::post('/funds/sync-all', 'syncAllDTrack')->name('funds.sync_all');
            Route::get('/funds/sync-all-dtrack', 'syncAllDTrack');
        });
    });

    // Explicit resource mapping for funds (excluding already defined routes)
    Route::resource('funds', FundController::class)->only(['show', 'edit', 'update']);

    // API for dynamic loading - Throttled
    Route::get('/api/sources/{sourceId}/activities', function ($sourceId) {
        $activities = \App\Models\Activity::where('source_of_fund_id', $sourceId)
            ->select('id', 'name', 'pooled_amount')
            ->get();
        return response()->json($activities);
    })->middleware('throttle:30,1');

    // Reports Controller Group
    Route::controller(ReportController::class)->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/budget-by-source', 'budgetBySource')->name('by_source');
        Route::get('/budget-by-line-item', 'budgetByLineItem')->name('by_line_item');
        Route::get('/by_transactions', 'byTransactions')->name('by_transactions');
        // FIX: Place the export route right here!
        // Because it's inside this group, its full name automatically becomes 'reports.by_source.export'
        Route::get('/budget-by-source/export', 'exportBudgetBySource')->name('by_source.export');
    });

    // Profile Controller Group
    Route::controller(ProfileController::class)->prefix('profile')->name('profile.')->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::patch('/', 'update')->name('update');
        Route::delete('/', 'destroy')->name('destroy');
    });

    // Basic WFP View/Print
    Route::controller(SettingsController::class)->group(function () {
        Route::get('/settings/wfp', 'index')->name('settings.index');
        Route::post('/settings/activities', 'storeWfp')->name('settings.activity.storeWfp');
        Route::get('/settings/print/{id?}', 'printWfp')->name('settings.print');

        Route::prefix('settings')->name('settings.')->group(function () {
            // Signatories
            Route::get('/employees/search', 'searchEmployees')->name('employees.search');
            Route::post('/signatories/save', 'saveSignatory')->name('signatories.save');
            Route::delete('/signatories/delete/{id}', 'deleteSignatory')->name('signatories.delete');

            // Realignment
            Route::post('/realign', 'updateAllocation')->name('realign');
            Route::post('/activities/pool', 'poolFunds')->name('activity.pool');
            Route::get('/activity/{id}/edit', 'editWfp')->name('activity.edit');

            // ==========================================================
            // NEW ADDITION: Self-Service Password Routes (All Users)
            // ==========================================================
            Route::get('/profile/password', 'editPassword')->name('profile.password');
            Route::put('/profile/password/update', 'updatePassword')->name('profile.password.update');
        });
    });
    Route::get('/admin/settings/get-realignment-table/{id}', [SettingsController::class, 'getRealignmentTable']);
});

// ==========================================================
// GROUP B: BUDGET SECTION ONLY
// ==========================================================
Route::middleware(['auth', 'can:budget-section', 'throttle:30,1'])->group(function () {
    
    Route::controller(SettingsController::class)->prefix('settings')->name('settings.')->group(function () {
        // Budget Line Items
        Route::get('/budget_line_items', 'budgetLineItems')->name('budget_line_items');
        Route::post('/budget_line_items/store', 'storeBudgetLineItem')->name('budget_line_items.store');
        Route::put('/budget_line_items/{id}', 'updateBudgetLineItem')->name('budget_line_items.update');
        Route::delete('/budget_line_items/{id}', 'destroyBudgetLineItem')->name('budget_line_items.destroy');

        // Fund Sources
        Route::get('/fund_sources', 'fundSources')->name('fund_sources');
        Route::post('/fund_sources', 'storeSource')->name('fund_sources.store');
        Route::delete('/fund_sources/{id}', 'destroyFundSource')->name('fund_sources.destroy');
        Route::put('/fund_sources/{id}', 'updateSource')->name('fund_sources.update');
        
        // Redundant routes handled by alias or controller
        Route::delete('/source/{id}', 'destroySource')->name('source.destroy');
        Route::put('/source/{id}', 'updateSource')->name('source.update');

        // UACS Codes
        Route::get('/uacs_codes', 'uacsCodes')->name('uacs_codes');
        Route::post('/uacs_codes/store', 'storeUACSCodes')->name('uacs_codes.store');
        Route::put('/uacs_codes/{id}', 'updateUACSCodes')->name('uacs_codes.update');
        Route::delete('/uacs_codes/{id}', 'destroyUACSCodes')->name('uacs_codes.destroy');

        // Advanced Tools
        Route::post('/employee', 'storeEmployee')->name('employee.store');
        Route::post('/activity', 'storeActivity')->name('activity.store');
        Route::match(['get', 'post', 'put'], '/template/{id}', 'updateTemplate')->name('template.update');
        Route::delete('/activity/{id}', 'destroyActivity')->name('activity.destroy');
        
    });

    Route::post('settings/import', [ActivityController::class, 'importWFP'])->name('settings.activity.import');
    Route::get('settings/download-template', [ActivityController::class, 'downloadTemplate'])->name('settings.template.download');
});

// ==========================================================
// GROUP C: ADMIN ONLY
// ==========================================================
Route::middleware(['auth', 'can:admin-only', 'throttle:20,1'])->group(function () {
    
    Route::controller(SettingsController::class)->prefix('settings')->group(function () {
        Route::get('/accounts', 'userIndex')->name('settings.accounts');
        Route::get('/employees/search-ext', 'searchExternal')->name('employees.external.search');
        Route::get('/employees/details/{dbedid}', 'getExternalDetails')->name('employees.details');
        Route::post('/register-employee', 'registerEmployee')->name('register.employee');
        Route::patch('/users/{id}/toggle', 'toggleStatus')->name('users.toggle-status');
        Route::put('/users/{id}', 'updateUser')->name('users.update');
    });

    // Admin User Management
    Route::controller(UserController::class)->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store');
    });
});

require __DIR__.'/auth.php';