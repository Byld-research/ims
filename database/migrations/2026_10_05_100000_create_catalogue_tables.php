<?php

use App\Enums\Criticality;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_types', function (Blueprint $table) {
            $table->id();
            $table->char('code', 1)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            // Highest serial issued, including machines outside the register (SPEC 5.8).
            $table->unsignedSmallInteger('last_serial')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 10)->unique();
            $table->foreignId('machine_type_id')->constrained('machine_types');
            $table->string('name', 150);
            $table->string('revision', 10);
            $table->foreignId('site_id')->constrained('sites');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['site_id', 'is_active']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories');
            $table->string('name', 100);
            $table->boolean('is_structural')->default(false);
            $table->string('default_bin', 40)->nullable();
            $table->timestamps();

            $table->unique(['parent_id', 'name']);
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 40)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('categories');
            $table->string('uom', 20);
            $table->string('manufacturer', 100)->nullable();
            $table->string('mpn', 80)->nullable();
            $table->string('drawing_no', 80)->nullable();
            $table->enum('criticality', array_column(Criticality::cases(), 'value'))->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('machine_type_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_type_id')->constrained('machine_types');
            $table->foreignId('item_id')->constrained('items');
            // Null: the line applies to every revision of the type.
            $table->string('revision', 10)->nullable();
            $table->string('reference', 80)->nullable();
            $table->decimal('qty_per_machine', 14, 3)->nullable();
            $table->boolean('is_consumable')->default(false);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['machine_type_id', 'item_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_type_items');
        Schema::dropIfExists('items');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('machines');
        Schema::dropIfExists('machine_types');
    }
};
