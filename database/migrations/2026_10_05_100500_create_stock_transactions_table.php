<?php

use App\Enums\TransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', array_column(TransactionType::cases(), 'value'));
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('site_id')->constrained('sites');
            $table->decimal('qty_delta', 14, 3);
            $table->decimal('unit_cost', 14, 4);
            $table->decimal('value', 14, 4);
            $table->decimal('qty_after', 14, 3);
            $table->decimal('avg_cost_after', 14, 4);
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers');
            $table->foreignId('counter_site_id')->nullable()->constrained('sites');
            $table->foreignId('purchase_order_line_id')->nullable()->constrained('purchase_order_lines');
            $table->foreignId('stock_count_id')->nullable()->constrained('stock_counts');
            $table->uuid('transfer_group')->nullable()->index();
            $table->foreignId('reason_code_id')->nullable()->constrained('reason_codes');
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['item_id', 'site_id', 'created_at']);
            $table->index(['work_center_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        DB::statement('ALTER TABLE stock_transactions ADD CONSTRAINT stock_txn_qty_delta_non_zero CHECK (qty_delta <> 0)');
        DB::statement('ALTER TABLE stock_transactions ADD CONSTRAINT stock_txn_qty_after_non_negative CHECK (qty_after >= 0)');

        // The ledger is append-only (SPEC 3.11, 5.2.6). Enforced in the database as well as in the model.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER stock_transactions_block_update BEFORE UPDATE ON stock_transactions
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stock_transactions is append-only: updates are not allowed'
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER stock_transactions_block_delete BEFORE DELETE ON stock_transactions
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stock_transactions is append-only: deletes are not allowed'
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
