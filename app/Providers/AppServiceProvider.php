<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Make sure the namespace is correct for User
        \Illuminate\Support\Facades\Gate::define('admin-access', function (\App\Models\User $user) {
            // Use === true to ensure it's a strict boolean check
            return $user->is_admin === true || $user->is_admin === 1;
        });
    }
}