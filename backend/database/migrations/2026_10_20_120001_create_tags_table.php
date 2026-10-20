<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags: free labels Users put on Customers, created the first time someone types them.
 * The name is unique ignoring case (the column's collation), so "VIP" and "vip" are one Tag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->timestamps();
        });

        Schema::create('customer_tag', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            // One row per pair; the second index serves "Customers with this Tag".
            $table->primary(['customer_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tag');
        Schema::dropIfExists('tags');
    }
};
