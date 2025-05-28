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
            $table->dropColumn('payment_gateway');
            $table->unsignedBigInteger('payment_gateway_id')->nullable()->after('id');

            $table->foreign('payment_gateway_id')
                  ->references('id')
                  ->on('payment_gateways')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['payment_gateway_id']);
            $table->dropColumn('payment_gateway_id');

            // Re-add the old string column
            $table->string('payment_gateway')->nullable();
        });
    }
};
