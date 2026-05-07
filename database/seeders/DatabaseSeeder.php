<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database for testing.
     */
    public function run(): void
    {
        // Based on your specific table columns: username, username_hash, is_active, is_admin
        User::updateOrCreate(
            ['username_hash' => hash('sha256', 'admin')], // Using a hash for lookup
            [
                'username' => 'admin', 
                'username_hash' => hash('sha256', 'admin'),
                'password' => Hash::make('admin123'), // Securely hashed password
                'is_active' => 1,
                'is_admin' => 1, // Granting admin privileges
                'email_verified_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}