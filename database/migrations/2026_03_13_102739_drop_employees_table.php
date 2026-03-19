<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // 1. Disable foreign key checks
        Schema::disableForeignKeyConstraints();

        // 2. Drop the table
        Schema::dropIfExists('employees');

        // 3. Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();
    }

    public function down()
    {
        // Usually, you'd recreate the table here, but since you are 
        // completely changing the architecture, you can leave it simple.
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};
