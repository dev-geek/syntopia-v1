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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2)->nullable(); // null for "Enterprise"
            $table->string('duration')->nullable(); // e.g. "monthly", "60hrs/month"
            $table->json('features'); // Store features as JSON array
            $table->string('paddle_product_id')->nullable();
            $table->string('fastspring_product_id')->nullable();
            $table->string('payproglobal_product_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
