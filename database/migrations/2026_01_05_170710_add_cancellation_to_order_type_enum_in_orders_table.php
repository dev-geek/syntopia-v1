<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_type ENUM('new', 'upgrade', 'downgrade', 'addon', 'cancellation') NOT NULL DEFAULT 'new'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_type ENUM('new', 'upgrade', 'downgrade', 'addon') NOT NULL DEFAULT 'new'");
    }
};
