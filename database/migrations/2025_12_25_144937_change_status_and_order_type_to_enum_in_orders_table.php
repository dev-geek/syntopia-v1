<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->where('status', 'cancellation_scheduled')
            ->update(['status' => 'cancelled']);

        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'completed', 'scheduled_downgrade', 'cancelled') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_type ENUM('new', 'upgrade', 'downgrade', 'addon') NOT NULL DEFAULT 'new'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status VARCHAR(255) NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_type VARCHAR(255) NOT NULL DEFAULT 'new'");
    }
};
