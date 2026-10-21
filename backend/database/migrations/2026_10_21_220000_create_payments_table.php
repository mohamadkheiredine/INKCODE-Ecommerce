<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Payment ledger (ticket 14): every payment and Refund recorded against an Order, in
 * the order they were made, failed card charges included. An Order keeps its Payment
 * status, Captured amount and Refunds total, recomputed from its ledger after every write
 * (App\Services\PaymentLedger), so lists and filters never sum the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16);
            $table->string('method', 24);
            $table->decimal('amount', 12, 2);
            $table->string('status', 16);
            $table->string('gateway_reference', 64)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key')->nullable();
            $table->timestamp('created_at')->useCurrent();
            // A request's key records at most one row per Order.
            $table->unique(['order_id', 'idempotency_key']);
            $table->index(['order_id', 'status', 'kind']);
            $table->index('created_at');
            $table->index(['kind', 'created_at']);
            $table->index(['method', 'created_at']);
        });
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount > 0)');

        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status', 24)->default('unpaid')->after('status');
            $table->decimal('captured_amount', 12, 2)->default(0)->after('total');
            $table->decimal('refunded_amount', 12, 2)->default(0)->after('captured_amount');
            $table->index(['payment_status', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status', 'placed_at']);
            $table->dropColumn(['payment_status', 'captured_amount', 'refunded_amount']);
        });
        Schema::dropIfExists('payments');
    }
};
