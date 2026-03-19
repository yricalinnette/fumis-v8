<?php

namespace App\Models\External;
use Illuminate\Database\Eloquent\Model;

class CommonSection extends Model {
    protected $connection = 'db_common';
    protected $table = 'tbl_section';
    protected $primaryKey = 'secid';
    public $timestamps = false;
}