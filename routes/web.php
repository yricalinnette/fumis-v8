<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FundController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;

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
    Route::get('funds/check-balance', [FundController::class, 'checkBalance'])->name('funds.check_balance');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/funds/create', [FundController::class, 'create'])->name('funds.create');
    Route::post('/funds/store', [FundController::class, 'store'])->name('funds.store');
    Route::get('/funds', [FundController::class, 'index'])->name('funds.index'); // Table View
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/source', [SettingsController::class, 'storeSource'])->name('settings.source.store');
    Route::delete('settings/source/{id}', [SettingsController::class, 'destroySource'])->name('settings.source.destroy');
    Route::post('/settings/employee', [SettingsController::class, 'storeEmployee'])->name('settings.employee.store');
    // Ensure these match your form actions and controller methods
    Route::post('/settings/activity', [SettingsController::class, 'storeActivity'])->name('settings.activity.store');
    Route::delete('/settings/activity/{id}', [SettingsController::class, 'destroyActivity'])->name('settings.activity.destroy');
    Route::patch('/funds/{id}/status', [FundController::class, 'updateStatus'])->name('funds.updateStatus');
    // Route for syncing a specific fund with Google Sheets
    Route::get('funds/{id}/sync', [App\Http\Controllers\FundController::class, 'syncWithGoogleSheet'])->name('funds.sync');
    Route::put('/settings/source/{id}', [SettingsController::class, 'updateSource'])
    ->name('settings.source.update');
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
    Route::get('/settings/source/{id}/test-connection', [SettingsController::class, 'testConnection'])->name('settings.source.test');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/funds/sync-all', [FundController::class, 'syncAllRouted'])->name('funds.sync_all');
    Route::get('/funds/sync-all-dtrack', [FundController::class, 'syncAllDTrack']);

    //for user registration or access
    Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.users.create');
    Route::post('/admin/users/store', [UserController::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
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
});

require __DIR__.'/auth.php';
