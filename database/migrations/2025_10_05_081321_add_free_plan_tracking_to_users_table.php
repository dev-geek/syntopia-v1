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
            $table->string('last_ip', 45)->nullable()->after('user_license_id');
            $table->string('device_id', 64)->nullable()->after('last_ip');
            $table->string('last_device_fingerprint', 64)->nullable()->after('device_id');
            $table->timestamp('last_login_at')->nullable()->after('last_device_fingerprint');
            $table->boolean('has_used_free_plan')->default(false)->after('last_login_at');
            $table->timestamp('free_plan_used_at')->nullable()->after('has_used_free_plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'last_ip',
                'device_id',
                'last_device_fingerprint',
                'last_login_at',
                'has_used_free_plan',
                'free_plan_used_at'
            ]);
        });
    }
};
