<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Order flow (ticket 12): each Order's Assignee, the notes Users add to its timeline,
 * and Partial cancellation. A cancelled quantity moves from `quantity` to
 * `cancelled_quantity`, so a fully cancelled line stays on the Order (and its Invoice
 * history) with no units left.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('assignee_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->index(['assignee_id', 'placed_at']);
        });

        Schema::table('order_lines', function (Blueprint $table) {
            $table->unsignedInteger('cancelled_quantity')->default(0)->after('quantity');
        });
        DB::statement('ALTER TABLE order_lines DROP CONSTRAINT order_lines_quantity_positive');
        DB::statement('ALTER TABLE order_lines ADD CONSTRAINT order_lines_quantity_positive CHECK (quantity + cancelled_quantity > 0)');

        Schema::create('order_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_notes');
        DB::statement('ALTER TABLE order_lines DROP CONSTRAINT order_lines_quantity_positive');
        DB::statement('ALTER TABLE order_lines ADD CONSTRAINT order_lines_quantity_positive CHECK (quantity > 0)');
        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropColumn('cancelled_quantity');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignee_id');
        });
    }
};
