<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('source_of_funds', function (Blueprint $table) {
            // Source Type (GAA or SAA)
            $table->string('source_type')->default('GAA')->after('budget_line_item_id');
            
            // SAA Specific Fields (Nullable because GAA won't use them)
            $table->string('entity_name')->nullable()->after('source_type'); // CHD8...
            $table->date('saa_date')->nullable()->after('entity_name');
            $table->string('reference_number')->nullable()->after('saa_date');
            $table->string('fund_code')->nullable()->after('reference_number');
            $table->string('approp_code')->nullable()->after('fund_code');
            $table->string('allotment_class')->nullable()->after('approp_code');
        });
    }

    public function down()
    {
        Schema::table('source_of_funds', function (Blueprint $table) {
            $table->dropColumn([
                'source_type', 'entity_name', 'saa_date', 
                'reference_number', 'fund_code', 'approp_code', 'allotment_class'
            ]);
        });
    }
};
