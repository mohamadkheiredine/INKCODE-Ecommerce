<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products, their images and their Variants. Money is DECIMAL: prices to the cent,
 * Average cost to a hundredth of a cent so re-weighting on each Receipt does not drift.
 *
 * Categories and Brands restrict deletes (ADR 0007). Deleting a Product removes its
 * Variants; once Order lines reference Variants (restricting, #16) a Product that was
 * ever ordered can no longer be deleted, only Archived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('base_price', 12, 2);
            $table->boolean('taxable')->default(true);
            $table->json('tags');
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 160)->nullable();
            $table->string('status', 16);
            $table->timestamps();

            // The list filters by status, Category, Brand and price, and sorts by name,
            // price and date.
            $table->index('name');
            $table->index(['status', 'created_at']);
            $table->index('base_price');
            $table->index('created_at');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            // 0 is the main image.
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['product_id', 'position']);
        });

        Schema::create('variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            // The sorted ids of the Variant's Attribute values ("4-9"; empty for a Product
            // without Attributes), so a combination can exist only once per Product.
            $table->string('combination_key', 191);
            $table->decimal('price', 12, 2);
            $table->decimal('average_cost', 14, 4)->default(0);
            // Null follows the store's default Low-stock threshold (Settings).
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['product_id', 'combination_key']);
            $table->index(['product_id', 'price']);
        });

        Schema::create('attribute_value_variant', function (Blueprint $table) {
            $table->foreignId('variant_id')->constrained()->cascadeOnDelete();
            // A value some Variant uses cannot be removed from its Attribute.
            $table->foreignId('attribute_value_id')->constrained()->restrictOnDelete();

            $table->primary(['variant_id', 'attribute_value_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_value_variant');
        Schema::dropIfExists('variants');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
    }
};
