<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One table for every vote cast anywhere.
 *
 * There were two: poll_votes, keyed on (poll, option, user), and
 * ticket_votes, keyed on (ticket, user). Two tables and two names for the
 * same act, and "poll_votes" next to "ticket_votes" told you nothing about
 * which held what.
 *
 * The morph points at *what was voted for*. A poll vote is a vote for one
 * of its options, so its voteable is the PollOption; a ticket up-vote is a
 * vote for the ticket itself. That keeps one row per vote with no nullable
 * option column, and the unique key below gives exactly the guarantee both
 * old tables gave.
 *
 * Named for the `likeable` table this follows, not for Laravel's plural.
 *
 * Old rows are not carried over, as agreed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voteable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // A morph cannot cascade, so the Voteable trait clears these
            // rows itself when the thing voted for is really deleted.
            $table->morphs('voteable');
            $table->timestamps();

            // One vote per member per thing. For a poll that means one vote
            // per option; whether a member may pick more than one option at
            // all is the poll's own `type`, enforced in PollVoteController.
            $table->unique(['user_id', 'voteable_type', 'voteable_id'], 'voteable_unique');
        });

        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('ticket_votes');
    }

    public function down(): void
    {
        /*Schema::create('poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['poll_id', 'poll_option_id', 'user_id']);
            $table->index(['poll_id', 'user_id']);
        });

        Schema::create('ticket_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ticket_id', 'user_id']);
        });*/

        Schema::dropIfExists('voteable');
    }
};
