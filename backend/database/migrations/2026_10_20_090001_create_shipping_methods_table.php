<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flat-price Shipping methods, each belonging to one Shipping zone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('min_delivery_days');
            $table->unsignedSmallInteger('max_delivery_days');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['shipping_zone_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
