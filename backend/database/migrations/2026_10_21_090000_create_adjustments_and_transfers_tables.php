<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adjustments (one Variant's On-hand corrected in one Warehouse, always with a reason),
 * two-step Transfers between Warehouses, and the low-stock alert flag on each balance so
 * an alert is sent once until stock recovers above the threshold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjustments', function (Blueprint $table) {
            $table->id();
            // ADJ-2026-000123, set from the id once the row exists.
            $table->string('number', 32)->nullable()->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->constrained()->restrictOnDelete();
            $table->integer('quantity_delta');
            $table->string('reason', 24);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['warehouse_id', 'created_at']);
            $table->index('created_at');
        });

        DB::statement('ALTER TABLE adjustments ADD CONSTRAINT adjustments_quantity_not_zero CHECK (quantity_delta <> 0)');

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            // TRF-2026-000123, set from the id once the row exists.
            $table->string('number', 32)->nullable()->unique();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('status', 16)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });

        DB::statement('ALTER TABLE transfers ADD CONSTRAINT transfers_distinct_warehouses CHECK (source_warehouse_id <> destination_warehouse_id)');

        Schema::create('transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');

            $table->unique(['transfer_id', 'variant_id']);
        });

        DB::statement('ALTER TABLE transfer_lines ADD CONSTRAINT transfer_lines_quantity_positive CHECK (quantity > 0)');

        Schema::table('stock_balances', function (Blueprint $table) {
            // Set when a low-stock alert went out; cleared once Available is above the threshold again.
            $table->timestamp('low_stock_alerted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropColumn('low_stock_alerted_at');
        });
        Schema::dropIfExists('transfer_lines');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('adjustments');
    }
};
