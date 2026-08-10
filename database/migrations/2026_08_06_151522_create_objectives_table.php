<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objectives', function (Blueprint $table) {
            $table->id();
            $table->text('title');                          // Stores the objective text
            $table->string('source')->default('local');     // 'local', 'spms', or 'user_added'
            $table->boolean('is_active')->default(true);    // Controls visibility in dropdown
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('objectives');
    }
};