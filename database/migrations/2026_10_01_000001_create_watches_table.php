<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One table for everything a member can follow.
 *
 * Forum threads and tickets each had their own table of the same shape —
 * parent id, user id, a unique pair — and their own API, model helpers and
 * button. Anything watchable now shares this one, which is also what makes
 * a single "what am I watching" list possible.
 *
 * The old rows are not carried over: the two features were young, and the
 * member lists are short enough to rebuild by watching things again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // No foreign key is possible across a morph, so the Watchable
            // trait clears these rows itself when a watched thing is really
            // deleted.
            $table->morphs('watchable');
            $table->timestamps();

            // morphs() already indexes (watchable_type, watchable_id), which
            // is the lookup the counts and the notification fan-out use.
            $table->unique(['user_id', 'watchable_type', 'watchable_id'], 'watches_unique');
        });

        Schema::dropIfExists('forum_thread_subscriptions');
        Schema::dropIfExists('ticket_watchers');
    }

    public function down(): void
    {
        Schema::create('forum_thread_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('forum_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['thread_id', 'user_id']);
        });

        Schema::create('ticket_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ticket_id', 'user_id']);
        });

        Schema::dropIfExists('watches');
    }
};
