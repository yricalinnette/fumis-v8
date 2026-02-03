<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = ['first_name', 'middle_name', 'last_name', 'suffix', 'position', 'is_active'];

    // This ensures the full_name is included whenever the model is converted to JSON/Array
    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        $middle = $this->middle_name ? " {$this->middle_name} " : " ";
        $suffix = $this->suffix ? ", {$this->suffix}" : "";
        return "{$this->first_name}{$middle}{$this->last_name}{$suffix}";
    }
    public function funds() {
        return $this->belongsToMany(Fund::class, 'employee_fund');
    }
}