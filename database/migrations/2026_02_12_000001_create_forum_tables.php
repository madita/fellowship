<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forums', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('forums')->onDelete('cascade');
            $table->integer('position')->default(0);
            $table->boolean('is_private')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->integer('threads_count')->default(0);
            $table->integer('posts_count')->default(0);
            $table->unsignedBigInteger('last_post_id')->nullable();
            $table->timestamp('last_post_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'position']);
            $table->index('last_post_at');
        });

        Schema::create('forum_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('taxonomy_id');
            $table->foreign('taxonomy_id')->references('id')->on('taxonomies')->onDelete('cascade');

            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body');
            $table->json('meta')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->integer('view_count')->default(0);
            $table->integer('reply_count')->default(0);
            $table->unsignedBigInteger('last_post_id')->nullable();
            $table->foreignId('last_post_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('last_post_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            //$table->index(['forum_id', 'is_pinned', 'last_post_at']);
            $table->index(['taxonomy_id', 'is_pinned', 'last_post_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('last_post_at');
            $table->index('created_at');
        });

        Schema::create('forum_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('forum_threads')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('forum_posts')->onDelete('cascade');
            $table->longText('body');
            $table->json('meta')->nullable();
            $table->boolean('is_solution')->default(false);
            $table->integer('like_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['thread_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('parent_id');
        });

        Schema::create('forum_thread_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('forum_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['thread_id', 'user_id']);
        });

        Schema::create('forum_post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('forum_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
        });

        Schema::create('forum_thread_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_id')->constrained('forum_threads')->cascadeOnDelete();
            $table->datetime('read_at');
            $table->timestamps();

            $table->unique(['user_id', 'thread_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('forum_thread_reads');
        Schema::dropIfExists('forum_post_likes');
        Schema::dropIfExists('forum_thread_subscriptions');
        Schema::dropIfExists('forum_posts');
        Schema::dropIfExists('forum_threads');
        Schema::dropIfExists('forums');
    }
};
