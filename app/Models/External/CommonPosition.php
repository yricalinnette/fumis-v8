<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Model;

class CommonPosition extends Model
{
    // Use the cross-database connection defined in config/database.php
    protected $connection = 'db_common';

    // Point to the exact table name from your screenshot
    protected $table = 'tbl_position';

    // Set the primary key as shown in the dbpid column
    protected $primaryKey = 'dbpid';

    // Disable timestamps as this external table likely doesn't have Laravel's created_at/updated_at
    public $timestamps = false;

    /**
     * Get the details associated with this position.
     */
    public function employeeDetails()
    {
        return $this->hasMany(CommonEmpDetail::class, 'dbpid', 'dbpid');
    }
}