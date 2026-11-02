<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sandboxes: a document several people write at once.
 *
 * `yjs_state` is the live CRDT document - the collaborative editor's own
 * record of who typed what - and `content` is the readable text rendered
 * out of it, so a sandbox can be shown without loading the editor. A
 * version is a snapshot of both.
 *
 * Comments sit in threads of their own rather than in the shared
 * `commentable` table, because each one is anchored to a quote inside the
 * document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sandboxes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('visibility', ['private', 'members', 'public'])->default('private');
            $table->longText('content')->nullable();
            $table->binary('yjs_state')->nullable();
            // Editor settings, permissions and the like
            $table->json('settings')->nullable();
            $table->timestamp('last_edited_at')->nullable();
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'visibility']);
            $table->index('last_edited_at');
        });

        Schema::create('sandbox_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sandbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['viewer', 'editor', 'admin'])->default('editor');
            $table->timestamp('invited_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['sandbox_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('sandbox_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sandbox_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->longText('content')->nullable();
            $table->binary('yjs_state')->nullable();
            $table->timestamps();

            $table->index(['sandbox_id', 'created_at']);
        });

        Schema::create('sandbox_comment_threads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sandbox_id')->constrained('sandboxes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            // The passage the thread is about
            $table->text('quote')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['sandbox_id', 'created_at']);
        });

        Schema::create('sandbox_thread_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('sandbox_comment_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('content');
            $table->timestamps();

            $table->index(['thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_thread_comments');
        Schema::dropIfExists('sandbox_comment_threads');
        Schema::dropIfExists('sandbox_versions');
        Schema::dropIfExists('sandbox_collaborators');
        Schema::dropIfExists('sandboxes');
    }
};
