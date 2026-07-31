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
        // 1. Admin Gate
        Gate::define('admin-only', function (User $user) {
            if ((int)$user->is_admin === 1) return true;

            return DB::table('employee_details')
                ->where('user_id', $user->id)
                ->where('role', 'admin')
                ->exists();
        });

        // 2. Budget Unit Gate
        Gate::define('budget-section', function (User $user) {
            if ((int)$user->is_admin === 1) return true;

            $empDetail = DB::table('employee_details')->where('user_id', $user->id)->first();
            if (!$empDetail) return false;

            if ($empDetail->role === 'budget' || $empDetail->role === 'admin') return true;

            return DB::connection('db_common')
                ->table('tbl_emp_details as common_emp')
                ->join('tbl_section as sections', 'common_emp.secid', '=', 'sections.secid')
                ->where('common_emp.dbedid', $empDetail->dbedid)
                ->where('sections.secname', 'Budget Unit')
                ->exists();
        });

        // 3. Division Access Gate
        Gate::define('division-access', function (User $user) {
            if ((int)$user->is_admin === 1) return true;

            return DB::table('employee_details')
                ->where('user_id', $user->id)
                ->where('role', 'division')
                ->exists();
        });
    }
}