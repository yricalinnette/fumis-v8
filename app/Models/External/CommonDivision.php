<?php

namespace App\Models\External;
use Illuminate\Database\Eloquent\Model;

class CommonDivision extends Model {
    protected $connection = 'db_common';
    protected $table = 'tbl_division';
    protected $primaryKey = 'divid';
    public $timestamps = false;
}