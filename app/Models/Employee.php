<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = ['first_name', 'middle_name', 'last_name', 'suffix', 'position', 'is_active', 'section_name', 'division_name'];

    // This ensures the full_name is included whenever the model is converted to JSON/Array
    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        return "{$this->fname} {$this->lname}";
    }
    public function funds() {
        return $this->belongsToMany(Fund::class, 'employee_fund');
    }
}