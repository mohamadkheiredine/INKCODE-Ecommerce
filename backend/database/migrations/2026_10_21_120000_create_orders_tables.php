<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orders, their lines and their status history, plus the per-year Order number sequence.
 *
 * An Order keeps a snapshot of everything it was priced and addressed with (addresses,
 * Shipping method name and price, VAT rate, Store currency), and each line keeps the
 * Variant's name, SKU, options, unit price, VAT rate and VAT amount, so later edits to the
 * catalog, the Customer or the settings never change an Order. Orders restrict deleting
 * their Customer, Warehouse and Shipping method, and their lines restrict deleting the
 * Variant: a Product on any Order can only be Archived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // ORD-2026-000123, from order_number_sequences.
            $table->string('number', 32)->unique();
            $table->string('status', 24);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('shipping_method_id')->constrained()->restrictOnDelete();
            $table->json('billing_address');
            $table->json('shipping_address');
            $table->string('shipping_method_name', 120);
            $table->char('currency_code', 3);
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('items_total', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('vat_total', 12, 2);
            $table->decimal('shipping_total', 12, 2);
            $table->decimal('total', 12, 2);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('placed_at');
            $table->timestamps();
            // The list filters by status, Warehouse and date, and sorts by date and total.
            $table->index(['status', 'placed_at']);
            $table->index(['warehouse_id', 'placed_at']);
            $table->index(['customer_id', 'placed_at']);
            $table->index('placed_at');
            $table->index('total');
        });
        DB::statement('ALTER TABLE orders
            ADD CONSTRAINT orders_amounts_not_negative CHECK (items_total >= 0 AND discount_total >= 0 AND vat_total >= 0 AND shipping_total >= 0 AND total >= 0)');

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained()->restrictOnDelete();
            $table->string('product_name', 200);
            $table->string('sku', 64);
            $table->json('options');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->boolean('taxable');
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->unique(['order_id', 'variant_id']);
            $table->index('variant_id');
        });
        DB::statement('ALTER TABLE order_lines ADD CONSTRAINT order_lines_quantity_positive CHECK (quantity > 0)');

        Schema::create('order_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_changes');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('order_number_sequences');
    }
};
