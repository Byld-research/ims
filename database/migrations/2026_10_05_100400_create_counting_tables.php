<?php

use App\Enums\ReasonCodeScope;
use App\Enums\StockCountStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reason_codes', function (Blueprint $table) {
            $table->id();
            $table->enum('applies_to', array_column(ReasonCodeScope::cases(), 'value'));
            $table->string('code', 20);
            $table->string('label', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['applies_to', 'code']);
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites');
            $table->string('reference', 20)->unique();
            $table->enum('status', array_column(StockCountStatus::cases(), 'value'))
                ->default(StockCountStatus::Draft->value);
            $table->string('scope_note', 255)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('qty_expected', 14, 3);
            $table->decimal('qty_counted', 14, 3)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['stock_count_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('reason_codes');
    }
};
