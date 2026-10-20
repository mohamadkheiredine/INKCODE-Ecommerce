<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping zones and the places each one covers. A location is a whole country (no city)
 * or one city in a country. A place belongs to at most one zone, so quoting an address
 * never has to choose between two zones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('shipping_zone_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->string('city', 120)->nullable();
            // The city as addresses are matched (case-insensitive), '' for the whole
            // country: unique per country, which a nullable `city` alone cannot be.
            $table->string('city_key', 120)->storedAs("coalesce(lower(trim(`city`)), '')");

            $table->unique(['country_code', 'city_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_locations');
        Schema::dropIfExists('shipping_zones');
    }
};
