<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UacsCode extends Model
{
    protected $fillable = [
        'uacs_code',
        'account_title',
        'is_active',
        'allotment_class',
    ];

    public function activities()
    {
        return $this->hasMany(Activity::class, 'uacs_code_id');
    }
}
