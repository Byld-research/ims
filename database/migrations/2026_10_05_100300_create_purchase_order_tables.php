<?php

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('site_id')->constrained('sites');
            $table->enum('status', array_column(PurchaseOrderStatus::cases(), 'value'))
                ->default(PurchaseOrderStatus::Draft->value);
            $table->date('ordered_at')->nullable();
            $table->date('confirmed_at')->nullable();
            $table->date('eta')->nullable();
            $table->date('shipped_at')->nullable();
            $table->string('tracking_ref', 200)->nullable();
            $table->date('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            // Cascade is only ever exercised while the order is DRAFT (SPEC 4.11, 5.3.7).
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('qty_ordered', 14, 3);
            $table->decimal('qty_received', 14, 3)->default(0);
            $table->decimal('unit_price', 14, 4);
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE purchase_order_lines ADD CONSTRAINT po_lines_qty_ordered_positive CHECK (qty_ordered > 0)');
        DB::statement('ALTER TABLE purchase_order_lines ADD CONSTRAINT po_lines_qty_received_non_negative CHECK (qty_received >= 0)');
        DB::statement('ALTER TABLE purchase_order_lines ADD CONSTRAINT po_lines_unit_price_non_negative CHECK (unit_price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
