<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a member does to something somebody else made.
 *
 * Each of these is one table for the whole site rather than one per
 * feature: there were forum_post_likes beside status likes, poll_votes
 * beside ticket_votes, forum_thread_subscriptions beside ticket_watchers
 * - two tables and two names for the same act every time. The traits in
 * app/Traits (Likeable, Commentable, Voteable, Watchable, Approvable)
 * hang them on any model.
 *
 * A morph cannot cascade, so each trait clears its own rows when the
 * thing they point at is really deleted.
 *
 * The singular names (`likeable`, `commentable`, `voteable`) are the ones
 * these tables have always had, so they stayed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('likeable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('likeable');
            $table->timestamps();

            // One like per member per thing
            $table->unique(['user_id', 'likeable_type', 'likeable_id'], 'likeable_unique');
        });

        Schema::create('commentable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('commentable');
            // A reply. One level deep is all any caller renders, but the
            // column does not enforce that - see Comment::isReply().
            $table->foreignId('parent_id')->nullable()->constrained('commentable')->cascadeOnDelete();
            $table->text('content');

            // The comment that answered the question, if one did, and who
            // said so. A nullable timestamp rather than a flag, so there
            // is no second column to disagree with it - see the Helpful
            // trait.
            $table->timestamp('helpful_at')->nullable();
            $table->foreignId('helpful_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Reading a discussion in order, which is the only read pattern
            $table->index(['commentable_type', 'commentable_id', 'created_at'], 'commentable_subject_index');
            $table->index(['parent_id', 'created_at']);
        });

        Schema::create('voteable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The morph points at *what was voted for*: a poll vote is a
            // vote for one of its options, a ticket up-vote is a vote for
            // the ticket. One row per vote, no nullable option column.
            $table->morphs('voteable');
            $table->timestamps();

            // One vote per member per thing. Whether a member may pick
            // more than one option of a poll at all is the poll's own
            // `type`, enforced in PollVoteController.
            $table->unique(['user_id', 'voteable_type', 'voteable_id'], 'voteable_unique');
        });

        Schema::create('watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('watchable');
            $table->timestamps();

            // morphs() already indexes (watchable_type, watchable_id),
            // which is the lookup the counts and the notification fan-out
            // use.
            $table->unique(['user_id', 'watchable_type', 'watchable_id'], 'watches_unique');
        });

        // Wiki pages and podcast shows wait here until somebody says yes
        Schema::create('approvables', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->timestamp('approved_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['approvable_type', 'approvable_id']);
        });

        // A poll can hang off anything: a status, a forum thread, a page
        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->morphs('pollable');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['single', 'multiple'])->default('single');
            $table->boolean('anonymous')->default(false);
            $table->timestamp('closes_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->string('option_text');
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index('poll_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('polls');
        Schema::dropIfExists('approvables');
        Schema::dropIfExists('watches');
        Schema::dropIfExists('voteable');
        Schema::dropIfExists('commentable');
        Schema::dropIfExists('likeable');
    }
};
