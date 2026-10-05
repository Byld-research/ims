<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('site_id')->constrained('sites');
            $table->decimal('qty', 14, 3)->default(0);
            $table->decimal('avg_cost', 14, 4)->default(0);
            $table->decimal('min_level', 14, 3)->default(0);
            $table->string('bin', 40)->nullable();
            $table->boolean('is_kanban')->default(false);
            $table->decimal('bin_qty', 14, 3)->nullable();
            $table->timestamp('last_counted_at')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'site_id']);
            $table->index(['site_id', 'qty']);
        });

        // Last line of defence behind StockService (SPEC 3.12, 5.5).
        DB::statement('ALTER TABLE stocks ADD CONSTRAINT stocks_qty_non_negative CHECK (qty >= 0)');
        DB::statement('ALTER TABLE stocks ADD CONSTRAINT stocks_min_level_non_negative CHECK (min_level >= 0)');
        DB::statement('ALTER TABLE stocks ADD CONSTRAINT stocks_kanban_bin_qty CHECK (is_kanban = 0 OR (bin_qty IS NOT NULL AND bin_qty > 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
