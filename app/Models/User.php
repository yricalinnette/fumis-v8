<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'password',
        'is_admin',
        'employee_id',
        'username_hash',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'username',
    ];

    protected $primaryKey = 'id';

    // app/Models/User.php

    public function toArray()
    {
        $attributes = parent::toArray();
        
        // Manually ensure username is NEVER a binary blob in any array conversion
        if (isset($attributes['username']) && !mb_check_encoding($attributes['username'], 'UTF-8')) {
            $attributes['username'] = 'Encrypted_User_' . $this->id;
        }
        
        return $attributes;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function employeeDetail() 
    {
        // Make sure your local table 'employee_details' has 'user_id'
        return $this->hasOne(EmployeeDetail::class, 'user_id', 'id');
    }

    /**
     * Fetches the raw record from db_common
     */
    public function getLiveInfoAttribute()
    {
        if (!$this->employeeDetail) return null;

        return DB::connection('db_common')
            ->table('tbl_emp_details')
            ->where('dbedid', $this->employeeDetail->dbedid)
            ->first();
    }

    /**
     * Returns a formatted name or username fallback
     */
    public function getNameAttribute()
    {
        $emp = $this->employeeDetail?->commonDetail?->employee;
        
        // This calls the getFullNameAttribute() in CommonEmployee
        // which now includes our new decryption logic
        if ($emp && $emp->full_name) {
            return $emp->full_name;
        }

        return $this->username; // Decrypted username fallback
    }

    // This decrypts the username automatically when you call $user->username
    public function getUsernameAttribute($value)
    {
        if (empty($value)) return $value;

        $key = config('app.db_common_key') ?? env('DB_COMMON_ENCRYPTION_KEY');

        // If the value contains non-UTF8 characters, it's definitely encrypted binary
        if (!mb_check_encoding($value, 'UTF-8')) {
            try {
                $result = \DB::selectOne("SELECT CAST(AES_DECRYPT(?, ?) AS CHAR) as un", [$value, $key]);
                return $result->un ?? 'Decryption Failed';
            } catch (\Exception $e) {
                return "Encoding Error";
            }
        }

        return $value;
    }

    // Add Shortcut for Position Name
    public function getPositionNameAttribute()
    {
        return $this->employeeDetail->commonDetail->position->dbposition ?? 'N/A';
    }

    //  Add Shortcut for Section Name
    public function getSectionNameAttribute()
    {
        return $this->employeeDetail->commonDetail->section->secname ?? 'N/A';
    }
}
