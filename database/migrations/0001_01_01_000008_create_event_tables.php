<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Events, and the shapes an event can have.
 *
 * An event type decides which fields an event asks for and whether
 * joining one has to be approved; an event profile is the set of
 * questions a guest answers when they join. Both are seeded - see
 * EventTypeTableSeeder and EventProfileTableSeeder.
 *
 * The camelCase column names are what the client has always sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->string('slug')->unique();
            $table->string('image')->nullable();
            $table->time('startTime')->nullable();
            $table->time('endTime')->nullable();
            $table->date('startDate')->nullable();
            $table->date('endDate')->nullable();
            $table->tinyInteger('allDay')->nullable();
            $table->tinyInteger('hasMedia')->nullable();
            $table->integer('event_type_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'locale']);
        });

        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->nullable();
            $table->integer('event_profile_id')->nullable();
            $table->string('color', 45)->nullable();
            $table->text('options')->nullable();
            $table->tinyInteger('approval')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Where it is
        Schema::create('event_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('event_id');
            $table->string('lat', 45)->nullable();
            $table->string('lng', 45)->nullable();
            $table->text('city')->nullable();
            $table->text('country')->nullable();
            $table->longText('options')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Who is coming, and what they answered
        Schema::create('event_guests', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->integer('event_id')->nullable();
            // yes / no / maybe, or master / player / guest
            $table->string('type')->nullable();
            $table->text('profile')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // The set of questions a guest of this kind of event answers
        Schema::create('event_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->nullable();
            $table->text('options')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_profiles');
        Schema::dropIfExists('event_guests');
        Schema::dropIfExists('event_details');
        Schema::dropIfExists('event_types');
        Schema::dropIfExists('event_translations');
        Schema::dropIfExists('events');
    }
};
