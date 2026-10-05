<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('supplier_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('item_id')->constrained('items');
            $table->string('supplier_sku', 80)->nullable();
            $table->decimal('last_price', 14, 4)->nullable();
            $table->decimal('pack_size', 14, 3)->default(1);
            $table->timestamps();

            $table->unique(['supplier_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_items');
        Schema::dropIfExists('suppliers');
    }
};
