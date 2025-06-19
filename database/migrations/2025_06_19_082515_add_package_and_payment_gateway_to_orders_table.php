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
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('package_id')->nullable()->after('id');
            $table->unsignedBigInteger('payment_gateway_id')->nullable()->after('package_id');

            $table->foreign('package_id')
                ->references('id')
                ->on('packages')
                ->onDelete('set null');

            $table->foreign('payment_gateway_id')
                ->references('id')
                ->on('payment_gateways')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropForeign(['payment_gateway_id']);

            $table->dropColumn('package_id');
            $table->dropColumn('payment_gateway_id');
        });
    }
};