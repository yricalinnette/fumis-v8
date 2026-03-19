<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CommonEmployee extends Model
{
    protected $connection = 'db_common';
    protected $table = 'tbl_employee';
    protected $primaryKey = 'empid';

    /**
     * Decrypt First Name
     */
    public function getFnameAttribute($value)
    {
        return $this->decryptValue($value);
    }

    /**
     * Decrypt Last Name
     */
    public function getLnameAttribute($value)
    {
        return $this->decryptValue($value);
    }

    /**
     * Helper logic for MySQL AES_DECRYPT
     */
    private function decryptValue($value)
    {
        if (empty($value) || mb_check_encoding($value, 'UTF-8')) {
            return $value; // Return as is if already plain text
        }

        // Use the same key you used for the User Model
        $key = config('app.db_common_key') ?? env('DB_COMMON_ENCRYPTION_KEY');

        try {
            $result = DB::connection('db_common')->selectOne(
                "SELECT CAST(AES_DECRYPT(?, ?) AS CHAR) as decrypted", 
                [$value, $key]
            );
            return $result->decrypted ?? 'Decryption Failed';
        } catch (\Exception $e) {
            return "Encoding Error";
        }
    }

    /**
     * Full Name Shortcut
     */
    public function getFullNameAttribute()
    {
        return trim("{$this->fname} {$this->lname}");
    }
}