<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wfp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_code')->unique(); // e.g. WFP-2026-X8F9A2K1
            $table->unsignedBigInteger('source_of_fund_id');
            $table->unsignedBigInteger('section_id');
            $table->string('wfp_type'); // 'indicative' or 'final'
            $table->unsignedBigInteger('generated_by_user_id');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index('tracking_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wfp_verifications');
    }
};
