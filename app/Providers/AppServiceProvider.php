<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 1. High-level Admin: Only is_admin = 1
        Gate::define('admin-only', function (User $user) {
            return (int)$user->is_admin === 1; 
        });

        // 2. Budget Section: Admin OR Staff belonging to "Budget Unit"
        Gate::define('budget-section', function (User $user) {
        if ((int)$user->is_admin === 1) return true;

            return DB::table('employee_details as local_emp')
                ->join('db_common.tbl_emp_details as common_emp', 'local_emp.dbedid', '=', 'common_emp.dbedid')
                ->join('db_common.tbl_section as sections', 'common_emp.secid', '=', 'sections.secid')
                ->where('local_emp.user_id', $user->id)
                ->where('sections.secname', 'Budget Unit')
                ->exists();
        });
    }
}