<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Command
{
    /**
     * Display the form to grant access to an employee.
     */
    public function create()
    {
        // 1. Get IDs of employees who already have accounts in the users table
        $existingUserIds = User::pluck('employee_id')->filter()->toArray();

        // 2. Fetch employees from the external database who don't have access yet
        // We use the 'db_common' connection defined in your config
        $employees = DB::connection('db_common')
            ->table('tbl_emp_details')
            ->whereNotIn('empid', $existingUserIds)
            ->select('empid', 'dbdesignation', 'dbstatustype')
            ->get();

        return view('admin.users.create', compact('employees'));
    }

    /**
     * Store the new user in the local 'users' table.
     */
    public function store(Request $request)
    {
        // Validate the incoming data
        $request->validate([
            'employee_id' => 'required',
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'password'    => 'required|min:6',
            'is_admin'    => 'required|in:0,1',
        ]);

        // Create the user in your 'fund_monitoring_db'
        User::create([
            'employee_id' => $request->employee_id,
            'name'        => $request->name,
            'email'       => $request->email,
            'password'    => Hash::make($request->password), // Encrypt the password
            'is_admin'    => $request->is_admin, // 1 for Admin, 0 for User
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User access granted successfully!');
    }
}