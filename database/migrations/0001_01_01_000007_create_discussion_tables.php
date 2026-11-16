<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Places people talk: the forum, the shoutbox and private conversations.
 *
 * A forum category is a taxonomy of type `forum_cat`, not a table of its
 * own, so a thread points at `taxonomies` - see the taxonomy migration.
 * There was a `forums` table once; nothing has read it since the
 * categories moved, so it is not created here.
 *
 * A reply that answered the question carries `helpful_at` and the member
 * who said so, the same two columns a comment carries. See the Helpful
 * trait, which both models use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_threads', function (Blueprint $table) {
            $table->id();
            // The category: a forum_cat taxonomy
            $table->unsignedInteger('taxonomy_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body');
            $table->json('meta')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->integer('view_count')->default(0);
            $table->integer('reply_count')->default(0);
            $table->unsignedBigInteger('last_post_id')->nullable();
            $table->foreignId('last_post_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_post_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('taxonomy_id')->references('id')->on('taxonomies')->cascadeOnDelete();

            $table->index(['taxonomy_id', 'is_pinned', 'last_post_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('last_post_at');
            $table->index('created_at');
        });

        Schema::create('forum_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('forum_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('forum_posts')->cascadeOnDelete();
            $table->longText('body');
            $table->json('meta')->nullable();

            // The reply that answered the thread, and who decided it did
            $table->timestamp('helpful_at')->nullable();
            $table->foreignId('helpful_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->integer('like_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['thread_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('parent_id');
            // The forum index filters threads down to the answered ones
            $table->index('helpful_at');
        });

        // How far a member has read, so "new since your last visit" works
        Schema::create('forum_thread_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_id')->constrained('forum_threads')->cascadeOnDelete();
            $table->datetime('read_at');
            $table->timestamps();

            $table->unique(['user_id', 'thread_id']);
        });

        // The shoutbox: one line, everybody, no thread
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at');
            $table->timestamps();
        });

        Schema::create('conversation_user', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->timestamp('read_at')->nullable();

            $table->primary(['conversation_id', 'user_id']);
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversation_user');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('forum_thread_reads');
        Schema::dropIfExists('forum_posts');
        Schema::dropIfExists('forum_threads');
    }
};
