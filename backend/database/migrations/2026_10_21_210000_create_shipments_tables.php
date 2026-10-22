<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shipments and Partial fulfillment (ticket 13). Each Order line counts the units that left
 * On-hand (`deducted_quantity`) and the units shipped (`shipped_quantity`), so stock is
 * deducted exactly once whichever Deduction stage is in force when it happens:
 * shipped ≤ deducted ≤ quantity. Orders already past the Deduction stage are backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_lines', function (Blueprint $table) {
            $table->unsignedInteger('deducted_quantity')->default(0)->after('cancelled_quantity');
            $table->unsignedInteger('shipped_quantity')->default(0)->after('deducted_quantity');
        });
        DB::statement('ALTER TABLE order_lines ADD CONSTRAINT order_lines_fulfillment_bounds CHECK (shipped_quantity <= deducted_quantity AND deducted_quantity <= quantity)');

        $stage = DB::table('store_settings')->value('deduction_stage') ?? 'shipped';
        $flow = ['pending', 'confirmed', 'preparing', 'ready_for_shipping', 'shipped', 'delivered'];
        $deducted = array_slice($flow, (int) array_search($stage, $flow, true));
        DB::table('order_lines')
            ->whereIn('order_id', DB::table('orders')->select('id')->whereIn('status', $deducted))
            ->update(['deducted_quantity' => DB::raw('quantity')]);
        DB::table('order_lines')
            ->whereIn('order_id', DB::table('orders')->select('id')->whereIn('status', ['shipped', 'delivered']))
            ->update(['shipped_quantity' => DB::raw('quantity')]);

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('carrier_id')->constrained()->restrictOnDelete();
            $table->string('tracking_number', 100);
            $table->string('status', 20)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('shipped_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['carrier_id', 'tracking_number']);
            $table->index(['order_id', 'id']);
        });

        Schema::create('shipment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_line_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unique(['shipment_id', 'order_line_id']);
        });
        DB::statement('ALTER TABLE shipment_lines ADD CONSTRAINT shipment_lines_quantity_positive CHECK (quantity > 0)');

        Schema::create('shipment_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['shipment_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_status_changes');
        Schema::dropIfExists('shipment_lines');
        Schema::dropIfExists('shipments');
        DB::statement('ALTER TABLE order_lines DROP CONSTRAINT order_lines_fulfillment_bounds');
        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropColumn(['deducted_quantity', 'shipped_quantity']);
        });
    }
};
