<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FundController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ActivityController;

Route::get('/', function () {
    return redirect()->route('login');
});

// ==========================================================
// GROUP A: ALL STAFF (General Access)
// Dashboard, Transactions, Reports, and WFP Configuration
// ==========================================================
Route::middleware(['auth', 'verified'])->group(function () {
    
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Funds / Transactions
    Route::get('funds/group/{dtrack}', [FundController::class, 'getGroupByDtrack']);
    Route::patch('/funds/{id}/status', [FundController::class, 'updateStatus'])->name('funds.updateStatus');
    Route::get('funds/{id}/sync', [FundController::class, 'syncWithGoogleSheet'])->name('funds.sync');
    Route::post('/funds/bulk-sync', [FundController::class, 'bulkSync'])->name('funds.bulk-sync');
    Route::get('/funds/sync-progress', [FundController::class, 'getSyncProgress'])->name('funds.sync-progress');
    Route::get('/funds/sync-count', function() {
        $count = \App\Models\Fund::where('status', 'Obligated')->whereNull('disbursement_date')->count();
        return response()->json(['count' => $count]);
    })->name('funds.sync-count');
    Route::post('/funds/sync-cancel', [FundController::class, 'cancelSync'])->name('funds.sync-cancel');
    Route::delete('/funds/{id}', [FundController::class, 'destroy'])->name('funds.destroy');
    Route::post('/funds/sync-all', [FundController::class, 'syncAllDTrack'])->name('funds.sync_all');
    Route::get('/funds/sync-all-dtrack', [FundController::class, 'syncAllDTrack']);
    Route::get('/funds/check-balance', [FundController::class, 'checkBalance'])->name('funds.check_balance');
    Route::get('/funds/create', [FundController::class, 'create'])->name('funds.create');
    Route::post('/funds/store', [FundController::class, 'store'])->name('funds.store');
    Route::get('/funds', [FundController::class, 'index'])->name('funds.index');
    Route::get('/funds/awaiting-obligation', [FundController::class, 'getAwaitingObligation'])->name('funds.awaiting');
    Route::resource('funds', FundController::class);

    // API for dynamic loading
    Route::get('/api/sources/{sourceId}/activities', function ($sourceId) {
        $activities = \App\Models\Activity::where('source_of_fund_id', $sourceId)->select('id', 'name', 'pooled_amount')->get();
        return response()->json($activities);
    });

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/budget-by-source', [ReportController::class, 'budgetBySource'])->name('reports.by_source');
    Route::get('reports/budget-by-line-item', [ReportController::class, 'budgetByLineItem'])->name('reports.by_line_item');
    Route::get('reports/by_transactions', [ReportController::class, 'byTransactions'])->name('reports.by_transactions');

    // Basic WFP View/Print
    Route::get('/settings/wfp', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/activities', [SettingsController::class, 'storeWfp'])->name('settings.activity.storeWfp');
    Route::get('/settings/print/{id?}', [SettingsController::class, 'printWfp'])->name('settings.print');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ==========================================================
// GROUP B: BUDGET SECTION ONLY (Budget Staff + Admin)
// Access to Master Data (UACS, Fund Sources, Realign, Import)
// ==========================================================
Route::middleware(['auth', 'can:budget-section'])->group(function () {
    
    // Budget Line Items
    Route::get('/settings/budget_line_items', [SettingsController::class, 'budgetLineItems'])->name('settings.budget_line_items');
    Route::post('/settings/budget_line_items/store', [SettingsController::class, 'storeBudgetLineItem'])->name('settings.budget_line_items.store');
    Route::put('/settings/budget_line_items/{id}', [SettingsController::class, 'updateBudgetLineItem'])->name('settings.budget_line_items.update');
    Route::delete('/settings/budget_line_items/{id}', [SettingsController::class, 'destroyBudgetLineItem'])->name('settings.budget_line_items.destroy');

    // Fund Sources
    Route::get('/settings/fund_sources', [SettingsController::class, 'fundSources'])->name('settings.fund_sources');
    Route::post('/settings/fund_sources', [SettingsController::class, 'storeSource'])->name('settings.fund_sources.store');
    Route::delete('/settings/fund_sources/{id}', [SettingsController::class, 'destroyFundSource'])->name('settings.fund_sources.destroy');
    Route::put('/settings/fund_sources/{id}', [SettingsController::class, 'updateSource'])->name('settings.fund_sources.update');
    Route::delete('/settings/source/{id}', [SettingsController::class, 'destroySource'])->name('settings.source.destroy');
    Route::put('/settings/source/{id}', [SettingsController::class, 'updateSource'])->name('settings.source.update');

    // UACS Codes
    Route::get('/settings/uacs_codes', [SettingsController::class, 'uacsCodes'])->name('settings.uacs_codes');
    Route::post('/settings/uacs_codes/store', [SettingsController::class, 'storeUACSCodes'])->name('settings.uacs_codes.store');
    Route::put('/settings/uacs_codes/{id}', [SettingsController::class, 'updateUACSCodes'])->name('settings.uacs_codes.update');
    Route::delete('/settings/uacs_codes/{id}', [SettingsController::class, 'destroyUACSCodes'])->name('settings.uacs_codes.destroy');

    // Advanced WFP Tools
    Route::post('/settings/employee', [SettingsController::class, 'storeEmployee'])->name('settings.employee.store');
    Route::post('/settings/activity', [SettingsController::class, 'storeActivity'])->name('settings.activity.store');
    Route::get('/settings/activity/{id}/edit', [SettingsController::class, 'editWfp'])->name('settings.activity.edit');
    Route::post('settings/import', [ActivityController::class, 'importWFP'])->name('settings.activity.import');
    Route::match(['get', 'post', 'put'], '/settings/template/{id}', [SettingsController::class, 'updateTemplate'])->name('settings.template.update');
    Route::delete('/settings/activity/{id}', [SettingsController::class, 'destroyActivity'])->name('settings.activity.destroy');
    Route::get('settings/download-template', [ActivityController::class, 'downloadTemplate'])->name('settings.template.download');
    Route::post('/settings/realign', [SettingsController::class, 'updateAllocation'])->name('settings.realign');
    Route::get('/admin/settings/get-realignment-table/{id}', [SettingsController::class, 'getRealignmentTable']);
    Route::post('/settings/activities/pool', [SettingsController::class, 'poolFunds'])->name('settings.activity.pool');

    // Signatories
    Route::get('/settings/employees/search', [SettingsController::class, 'searchEmployees'])->name('settings.employees.search');
    Route::post('/settings/signatories/save', [SettingsController::class, 'saveSignatory'])->name('settings.signatories.save');
    Route::delete('/settings/signatories/delete/{id}', [SettingsController::class, 'deleteSignatory'])->name('settings.signatories.delete');
});

// ==========================================================
// GROUP C: ADMIN ONLY (Super Admin Access)
// Solely for Account Management and User Control
// ==========================================================
Route::middleware(['auth', 'can:admin-only'])->group(function () {
    
    // Account Management
    Route::get('/settings/accounts', [SettingsController::class, 'userIndex'])->name('settings.accounts');
    Route::get('/settings/employees/search-ext', [SettingsController::class, 'searchExternal'])->name('employees.external.search');
    Route::get('/settings/employees/details/{dbedid}', [SettingsController::class, 'getExternalDetails'])->name('employees.details');
    Route::post('/settings/register-employee', [SettingsController::class, 'registerEmployee'])->name('register.employee');
    Route::patch('/settings/users/{id}/toggle', [SettingsController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::put('/settings/users/{id}', [SettingsController::class, 'updateUser'])->name('users.update');

    // Admin User CRUD
    Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.users.create');
    Route::post('/admin/users/store', [UserController::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
});

require __DIR__.'/auth.php';