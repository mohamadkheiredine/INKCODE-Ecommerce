<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Returns (ticket 16): a Customer sending back some units of a Delivered Order's lines.
 * Each Return line gets a Restock decision once the Return is Received; the Refund, when
 * there is one, is a row of the Order's Payment ledger linked here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number');
        });

        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            // RET-2026-000045, from return_number_sequences.
            $table->string('number', 32)->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('status', 16);
            $table->string('reason', 24);
            $table->string('note', 1000)->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->boolean('refund_shipping')->default(false);
            $table->foreignId('refund_id')->nullable()->unique()->constrained('payments')->restrictOnDelete();
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'requested_at']);
            $table->index('requested_at');
            $table->index(['reason', 'requested_at']);
        });

        Schema::create('return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('order_line_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('restock_decision', 16)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unique(['return_id', 'order_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_lines');
        Schema::dropIfExists('returns');
        Schema::dropIfExists('return_number_sequences');
    }
};
