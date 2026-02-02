<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('provider'); // google, discord, github, facebook
            $table->string('provider_id'); // OAuth user ID from provider
            $table->string('provider_token', 500)->nullable(); // OAuth access token
            $table->string('provider_refresh_token', 500)->nullable(); // OAuth refresh token
            $table->timestamp('token_expires_at')->nullable();
            $table->text('avatar')->nullable(); // User avatar URL from provider
            $table->timestamps();

            // Indexes for performance
            $table->unique(['provider', 'provider_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
