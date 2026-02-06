<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_name',
        'header_row',
        'budget_line_col',
        'objective_col',
        'activity_col',
        'budget_col',
        'source_col',
        'uacs_col',
    ];
}