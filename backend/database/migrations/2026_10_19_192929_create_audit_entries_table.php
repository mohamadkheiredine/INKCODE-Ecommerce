<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit trail: one append-only row per change a User makes to a record. It keeps
 * the record's label as it was, so an entry still reads well after the record is gone,
 * and only the values that changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_entries', function (Blueprint $table) {
            $table->id();
            // Null for changes made by the system (seeders, scheduled jobs). Users are
            // deactivated, never deleted, so their history always keeps its author.
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action', 32);
            $table->string('record_type', 64);
            $table->unsignedBigInteger('record_id');
            $table->string('record_label');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // The audit log (#26) filters by record, User, action and date, newest first.
            $table->index(['record_type', 'record_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
    }
};
