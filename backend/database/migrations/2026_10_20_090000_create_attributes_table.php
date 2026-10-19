<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shared Attribute library (Size, Color, Material…) and each Attribute's values.
 * A value's code ("RED", "M") is what generated SKUs are made of. Values are unique
 * within their Attribute, by name and by code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('code', 20);
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['attribute_id', 'name']);
            $table->unique(['attribute_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
