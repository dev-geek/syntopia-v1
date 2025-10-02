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
        Schema::table('user_licences', function (Blueprint $table) {
            $table->index('package_id');
            $table->index('payment_gateway_id');
            $table->index('expires_at');
        });

        Schema::table('user_logs', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('activity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_licences', function (Blueprint $table) {
            $table->dropIndex(['package_id']);
            $table->dropIndex(['payment_gateway_id']);
            $table->dropIndex(['expires_at']);
        });

        Schema::table('user_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['activity']);
        });
    }
};
