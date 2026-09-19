<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discord webhooks: an admin pastes the webhook address of a channel and
 * picks which events are announced there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Encrypted: the address is the credential, anyone holding it can post
            $table->text('url');
            $table->json('events');
            $table->boolean('is_active')->default(true);

            // What happened the last time this webhook was used
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->string('last_error')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_webhooks');
    }
};
