<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 1. Change username to TEXT (Encrypted strings are much longer than plain text)
            $table->binary('username')->change();

            // 2. Add a Hash column for Login lookups (Deterministic & Unique)
            $table->string('username_hash')->unique()->after('username')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 255)->change();
            $table->dropColumn('username_hash');
        });
    }
};
