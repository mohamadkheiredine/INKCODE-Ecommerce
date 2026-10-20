<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Customer's addresses. Each Customer with addresses has exactly one default billing
 * and one default shipping address: the actions keep that true, and the two generated
 * columns let MySQL itself refuse a second default (they hold the Customer's id only on
 * the default row, and NULLs never collide in a unique index).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50);
            $table->string('recipient_name', 120);
            $table->string('phone', 30)->nullable();
            $table->char('country_code', 2);
            $table->string('city', 100);
            $table->string('line1', 150);
            $table->string('line2', 150)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->boolean('is_default_billing')->default(false);
            $table->boolean('is_default_shipping')->default(false);
            $table->timestamps();

            $table->unsignedBigInteger('default_billing_customer_id')
                ->nullable()
                ->virtualAs('if(is_default_billing, customer_id, null)')
                ->unique();
            $table->unsignedBigInteger('default_shipping_customer_id')
                ->nullable()
                ->virtualAs('if(is_default_shipping, customer_id, null)')
                ->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
