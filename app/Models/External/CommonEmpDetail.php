<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Model;

class CommonEmpDetail extends Model
{
    protected $connection = 'db_common';
    protected $table = 'tbl_emp_details';
    protected $primaryKey = 'dbedid';

    public function position() {
        return $this->belongsTo(CommonPosition::class, 'dbpid', 'dbpid');
    }

    public function section() {
        return $this->belongsTo(CommonSection::class, 'secid', 'secid');
    }

    public function division() {
        return $this->belongsTo(CommonDivision::class, 'divid', 'divid');
    }

    public function employee()
    {
        return $this->belongsTo(CommonEmployee::class, 'empid', 'empid');
    }
}