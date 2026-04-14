<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FundController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ActivityController;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Admin Only Routes
Route::middleware(['auth', 'can:admin-access'])->group(function () {
    //settings
    // Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/accounts', [SettingsController::class, 'userIndex'])->name('settings.accounts');
    // BUDGET LINE ITEM ROUTES
    Route::get('/settings/budget_line_items', [SettingsController::class, 'budgetLineItems'])->name('settings.budget_line_items');
    Route::post('/settings/budget_line_items/store', [SettingsController::class, 'storeBudgetLineItem'])->name('settings.budget_line_items.store');
    Route::put('/settings/budget_line_items/{id}', [SettingsController::class, 'updateBudgetLineItem'])->name('settings.budget_line_items.update');
    Route::delete('/settings/budget_line_items/{id}', [SettingsController::class, 'destroyBudgetLineItem'])->name('settings.budget_line_items.destroy');
    // FUND SOURCE ROUTES
    Route::get('/settings/fund_sources', [SettingsController::class, 'fundSources'])->name('settings.fund_sources');
    Route::post('/settings/fund_sources', [SettingsController::class, 'storeSource'])->name('settings.fund_sources.store');
    Route::delete('/settings/fund_sources/{id}', [SettingsController::class, 'destroyFundSource'])->name('settings.fund_sources.destroy');
    Route::put('/settings/fund_sources/{id}', [SettingsController::class, 'updateSource'])->name('settings.fund_sources.update');
    //UACS CODE ROUTES
    Route::get('/settings/uacs_codes', [SettingsController::class, 'uacsCodes'])->name('settings.uacs_codes');
    Route::post('/settings/uacs_codes/store', [SettingsController::class, 'storeUACSCodes'])->name('settings.uacs_codes.store');
    Route::put('/settings/uacs_codes/{id}', [SettingsController::class, 'updateUACSCodes'])->name('settings.uacs_codes.update');
    Route::delete('/settings/uacs_codes/{id}', [SettingsController::class, 'destroyUACSCodes'])->name('settings.uacs_codes.destroy');
    //signatories
    Route::get('/settings/employees/search', [SettingsController::class, 'searchEmployees'])->name('settings.employees.search');
    Route::post('/settings/signatories/save', [SettingsController::class, 'saveSignatory'])->name('settings.signatories.save');
    Route::delete('/settings/signatories/delete/{id}', [SettingsController::class, 'deleteSignatory'])->name('settings.signatories.delete');

    Route::get('/settings/wfp', [SettingsController::class, 'index'])->name('settings.index');
    Route::delete('/settings/source/{id}', [SettingsController::class, 'destroySource'])->name('settings.source.destroy');
    Route::post('/settings/employee', [SettingsController::class, 'storeEmployee'])->name('settings.employee.store');
    Route::post('/settings/activity', [SettingsController::class, 'storeActivity'])->name('settings.activity.store');
    Route::get('/settings/activity/{id}/edit', [SettingsController::class, 'editWfp'])->name('settings.activity.edit');
    Route::put('/settings/source/{id}', [SettingsController::class, 'updateSource'])->name('settings.source.update');
    Route::post('settings/import', [ActivityController::class, 'importWFP'])->name('settings.activity.import');
    // Route::get('/settings/source/{id}/test-connection', [SettingsController::class, 'testConnection'])->name('settings.source.test');
    Route::match(['get', 'post', 'put'], '/settings/template/{id}', [SettingsController::class, 'updateTemplate'])->name('settings.template.update');
    Route::delete('/settings/activity/{id}', [SettingsController::class, 'destroyActivity'])->name('settings.activity.destroy');
    Route::get('settings/download-template', [ActivityController::class, 'downloadTemplate'])->name('settings.template.download');
    Route::post('/settings/realign', [SettingsController::class, 'updateAllocation'])->name('settings.realign');
    Route::get('/admin/settings/get-realignment-table/{id}', [App\Http\Controllers\SettingsController::class, 'getRealignmentTable']);
    Route::post('/settings/activities/pool', [SettingsController::class, 'poolFunds'])->name('settings.activity.pool');
    Route::post('/settings/activities', [SettingsController::class, 'storeWfp'])->name('settings.activity.storeWfp');
    Route::get('/settings/print/{id?}', [SettingsController::class, 'printWfp'])->name('settings.print');
    
    Route::get('/settings/employees/search', [SettingsController::class, 'searchExternal'])->name('employees.external.search');
    Route::get('/settings/employees/details/{dbedid}', [SettingsController::class, 'getExternalDetails'])->name('employees.details');
    Route::post('/settings/register-employee', [SettingsController::class, 'registerEmployee'])->name('register.employee');
    Route::patch('/settings/users/{id}/toggle', [SettingsController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::put('/settings/users/{id}', [SettingsController::class, 'updateUser'])->name('users.update');

    //funds transactions
    Route::get('funds/group/{dtrack}', [FundController::class, 'getGroupByDtrack']);
    Route::patch('/funds/{id}/status', [FundController::class, 'updateStatus'])->name('funds.updateStatus');
    Route::get('funds/{id}/sync', [App\Http\Controllers\FundController::class, 'syncWithGoogleSheet'])->name('funds.sync');
    Route::post('/funds/bulk-sync', [FundController::class, 'bulkSync'])->name('funds.bulk-sync');
    Route::get('/funds/sync-progress', [FundController::class, 'getSyncProgress'])->name('funds.sync-progress');
    Route::get('/funds/sync-count', function() {
                $count = \App\Models\Fund::where('status', 'Obligated')
                                        ->whereNull('disbursement_date')
                                        ->count();
                return response()->json(['count' => $count]);
            })->name('funds.sync-count');
    Route::post('/funds/sync-cancel', [FundController::class, 'cancelSync'])->name('funds.sync-cancel');
    Route::delete('/funds/{id}', [FundController::class, 'destroy'])->name('funds.destroy');
    Route::post('/funds/sync-all', [FundController::class, 'syncAllDTrack'])->name('funds.sync_all');
    Route::get('/funds/sync-all-dtrack', [FundController::class, 'syncAllDTrack']);
    Route::get('/funds/check-balance', [FundController::class, 'checkBalance'])->name('funds.check_balance');
    Route::get('/funds/create', [FundController::class, 'create'])->name('funds.create');
    Route::post('/funds/store', [FundController::class, 'store'])->name('funds.store');
    Route::get('/funds', [FundController::class, 'index'])->name('funds.index'); // Table View
    Route::get('/funds/awaiting-obligation', [FundController::class, 'getAwaitingObligation'])->name('funds.awaiting');
    // Route for dynamic activity loading
    Route::get('/api/sources/{sourceId}/activities', function ($sourceId) {
        $activities = \App\Models\Activity::where('source_of_fund_id', $sourceId)
            ->select('id', 'name', 'pooled_amount') // only fetch what we need
            ->get();
            return response()->json($activities);});

    //dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    //for user registration or access
    Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.users.create');
    Route::post('/admin/users/store', [UserController::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');

    //REPORT
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/budget-by-source', [ReportController::class, 'budgetBySource'])->name('reports.by_source');
    Route::get('reports/budget-by-line-item', [App\Http\Controllers\ReportController::class, 'budgetByLineItem'])
    ->name('reports.by_line_item');
});

Route::middleware('auth')->group(function () {
    Route::resource('funds', FundController::class);
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/funds', [FundController::class, 'store'])->name('funds.store');
    Route::get('/funds/create', [FundController::class, 'create'])->name('funds.create');
    Route::post('/funds/store', [FundController::class, 'store'])->name('funds.store');
    Route::get('/funds', [FundController::class, 'index'])->name('funds.index'); // Table View
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/auth.php';
