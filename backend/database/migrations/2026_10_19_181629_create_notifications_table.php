<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's database notifications: one row per notification per User. `type` is the
 * stable notification type (App\Enums\NotificationType), not a class name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 64);
            // Not morphs(): its own index would duplicate the leading columns of the two below.
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The bell lists a User's notifications newest first and counts the unread ones.
            $table->index(['notifiable_type', 'notifiable_id', 'created_at']);
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
