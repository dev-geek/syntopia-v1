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
            $table->softDeletes();
        });

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('user_licences', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('user_logs', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('user_licences', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('user_logs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
