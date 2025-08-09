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
        Schema::table('free_plan_attempts', function (Blueprint $table) {
            $table->string('fingerprint_id', 64)->nullable()->after('device_fingerprint');
            $table->json('data')->nullable()->after('fingerprint_id');
            
            // Add index for faster lookups
            $table->index('fingerprint_id');
            $table->index('ip_address');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('free_plan_attempts', function (Blueprint $table) {
            $table->dropIndex(['fingerprint_id']);
            $table->dropIndex(['ip_address']);
            $table->dropIndex(['email']);
            
            $table->dropColumn(['fingerprint_id', 'data']);
        });
    }
};
