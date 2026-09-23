<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rest of what a member can tell us about themselves.
 *
 * Kept apart from the users table because most of it is optional and none
 * of it is needed to sign somebody in. Split by who may see it: a street
 * address and a phone number are for the site's own records and never
 * leave it, while the rest is the member's to show or keep — see the
 * visibility column and UserProfile::SHAREABLE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // The member's to show if they want to
            $table->text('bio')->nullable();
            $table->string('pronouns', 40)->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('website')->nullable();
            $table->date('birthday')->nullable();
            // { "discord": "...", "github": "..." }
            $table->json('socials')->nullable();

            // Never shown to anybody else, whatever the member picks
            $table->string('phone', 40)->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('state')->nullable();

            // Which of the shareable fields the member lets others see
            $table->json('visibility')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
