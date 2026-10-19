<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The store's settings: exactly one row (id 1), created here with the defaults every
 * other module relies on, so a fresh install works before an Admin opens Settings.
 * Each setting is a typed column, so the database itself refuses impossible values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();

            // Store profile
            $table->string('name', 120);
            $table->string('logo_path')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('address', 500)->nullable();

            // Money
            $table->char('currency_code', 3)->default('USD');
            $table->string('currency_symbol', 8)->default('$');
            $table->decimal('vat_rate', 5, 2)->default('5.00');

            // Stock and Returns
            $table->string('deduction_stage', 32)->default('shipped');
            $table->unsignedSmallInteger('return_window_days')->default(14);
            $table->unsignedInteger('low_stock_threshold')->default(5);

            // What a new User starts with
            $table->string('default_locale', 5)->default('en');
            $table->string('default_theme', 10)->default('light');

            $table->timestamps();
        });

        DB::statement('ALTER TABLE store_settings ADD CONSTRAINT store_settings_single_row CHECK (id = 1)');
        DB::statement('ALTER TABLE store_settings ADD CONSTRAINT store_settings_vat_rate_range CHECK (vat_rate BETWEEN 0 AND 100)');

        DB::table('store_settings')->insert([
            'id' => 1,
            'name' => 'Inkcode Store',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
