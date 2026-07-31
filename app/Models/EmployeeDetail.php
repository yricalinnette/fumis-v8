<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// Import the external model here
use App\Models\External\CommonEmpDetail;

class EmployeeDetail extends Model
{
    protected $fillable = ['user_id', 'dbedid', 'role'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function commonDetail()
    {
        // This links your local dbedid to the primary key in db_common
        return $this->belongsTo(CommonEmpDetail::class, 'dbedid', 'dbedid');
    }
}