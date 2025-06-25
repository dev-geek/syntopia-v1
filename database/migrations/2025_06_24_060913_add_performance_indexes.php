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
        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index(['email', 'is_subscribed']);
            $table->index(['package_id', 'subscription_starts_at']);
            $table->index('payment_gateway_id');
            $table->index('paddle_customer_id');
        });

        // Orders table indexes
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['transaction_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['payment_gateway_id', 'created_at']);
        });

        // Packages table indexes
        Schema::table('packages', function (Blueprint $table) {
            $table->index('name');
            $table->index(['price', 'duration']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email', 'is_subscribed']);
            $table->dropIndex(['package_id', 'subscription_starts_at']);
            $table->dropIndex(['payment_gateway_id']);
            $table->dropIndex(['paddle_customer_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['transaction_id', 'status']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['payment_gateway_id', 'created_at']);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['price', 'duration']);
        });
    }
};
