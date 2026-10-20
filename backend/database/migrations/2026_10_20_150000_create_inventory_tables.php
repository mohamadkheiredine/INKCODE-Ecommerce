<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Warehouses, one stock balance per Variant × Warehouse, the Stock movement ledger,
 * Receipts and the Idempotency-Key store (ADR 0008).
 *
 * Balances can never hold negative On-hand or Reserved, nor reserve more than is on hand:
 * CHECK constraints refuse it even if a code path forgot the service's own checks.
 * Movements and Receipts restrict deleting their Variant or Warehouse, so stock history
 * is never lost; Variants without history can still be deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('code', 16)->unique();
            $table->string('address', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->integer('on_hand')->default(0);
            $table->integer('reserved')->default(0);
            $table->integer('available')->storedAs('on_hand - reserved');
            $table->timestamps();

            $table->unique(['variant_id', 'warehouse_id']);
            $table->index(['warehouse_id', 'available']);
        });

        DB::statement('ALTER TABLE stock_balances
            ADD CONSTRAINT stock_balances_on_hand_not_negative CHECK (on_hand >= 0),
            ADD CONSTRAINT stock_balances_reserved_not_negative CHECK (reserved >= 0),
            ADD CONSTRAINT stock_balances_reserved_within_on_hand CHECK (reserved <= on_hand)');

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            // RCV-2026-000123, set from the id once the row exists.
            $table->string('number', 32)->nullable()->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('supplier_reference', 100);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['warehouse_id', 'created_at']);
            $table->index('created_at');
            $table->index('supplier_reference');
        });

        Schema::create('receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 14, 4);

            $table->unique(['receipt_id', 'variant_id']);
        });

        DB::statement('ALTER TABLE receipt_lines ADD CONSTRAINT receipt_lines_quantity_positive CHECK (quantity > 0)');

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('type', 24);
            $table->integer('on_hand_delta');
            $table->integer('reserved_delta');
            // The balance right after this movement, so history reads without replaying.
            $table->integer('on_hand_after');
            $table->integer('reserved_after');
            $table->string('reason', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // The record that caused it: a Receipt, an Adjustment, a Transfer, an Order…
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['variant_id', 'warehouse_id', 'id']);
            $table->index(['warehouse_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index('created_at');
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 255);
            // SHA-256 of the method, path and body, so a key reused for another request is refused.
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->longText('response_body');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('receipt_lines');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('warehouses');
    }
};
