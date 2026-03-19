<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserUsernameEncryptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $key = config('app.db_common_key') ?? env('DB_COMMON_ENCRYPTION_KEY');
        
        // Get users where the hash is missing
        $users = \DB::table('users')->whereNull('username_hash')->get();

        foreach ($users as $user) {
            // Use the raw column from the DB
            $plain = $user->username; 
            $hash = (string) hash('sha256', strtolower($plain));

            \DB::table('users')->where('id', $user->id)->update([
                'username_hash' => $hash,
                // We use DB::raw for the encryption function
                'username'      => \DB::raw("AES_ENCRYPT('{$plain}', '{$key}')"),
                'updated_at'    => now()
            ]);
        }
    }
}
