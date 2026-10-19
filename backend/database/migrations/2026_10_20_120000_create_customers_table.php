<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers: a name and at least one way to reach them (email or phone; the rule lives in
 * the Form Request). An email belongs to one Customer; several may have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email')->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->timestamps();

            // The list searches by phone and sorts by name and by date.
            $table->index('name');
            $table->index('phone');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
