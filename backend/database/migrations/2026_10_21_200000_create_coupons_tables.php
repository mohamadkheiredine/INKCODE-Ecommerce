<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coupons (ticket 15): the Coupon with its dates, limits, minimum and optional eligible
 * Products and Categories; one redemption per Confirmed Order that used it (released when
 * the Order is fully cancelled); and on each Order a snapshot of the Coupon's code, type
 * and value, with each line marked eligible or not, so re-pricing an Order later never
 * picks up edits to the Coupon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('type', 16);
            $table->decimal('value', 12, 2);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->decimal('minimum_order_amount', 12, 2)->default(0);
            $table->timestamps();
            $table->index('starts_on');
            $table->index('ends_on');
            $table->index('created_at');
        });
        DB::statement('ALTER TABLE coupons ADD CONSTRAINT coupons_amounts_valid CHECK (value > 0 AND minimum_order_amount >= 0)');

        Schema::create('coupon_product', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'product_id']);
        });

        Schema::create('category_coupon', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'category_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('shipping_method_id')->constrained()->restrictOnDelete();
            $table->string('coupon_code', 40)->nullable()->after('coupon_id');
            $table->string('coupon_type', 16)->nullable()->after('coupon_code');
            $table->decimal('coupon_value', 12, 2)->nullable()->after('coupon_type');
        });

        Schema::table('order_lines', function (Blueprint $table) {
            $table->boolean('coupon_eligible')->default(false)->after('discount_amount');
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->timestamp('redeemed_at');
            $table->timestamp('released_at')->nullable();
            // Limits count the redemptions not released, per Coupon and per Customer.
            $table->index(['coupon_id', 'released_at']);
            $table->index(['coupon_id', 'customer_id', 'released_at']);
            $table->index(['coupon_id', 'redeemed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropColumn('coupon_eligible');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_code', 'coupon_type', 'coupon_value']);
        });
        Schema::dropIfExists('category_coupon');
        Schema::dropIfExists('coupon_product');
        Schema::dropIfExists('coupons');
    }
};
