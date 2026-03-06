<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sandboxes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Owner
            $table->enum('visibility', ['private', 'members', 'public'])->default('private');
            $table->binary('yjs_state')->nullable(); // Y.js document state for persistence
            $table->json('settings')->nullable(); // Editor settings, permissions, etc.
            $table->timestamp('last_edited_at')->nullable();
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'visibility']);
            $table->index('last_edited_at');
        });

        Schema::create('sandbox_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sandbox_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['viewer', 'editor', 'admin'])->default('editor');
            $table->timestamp('invited_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['sandbox_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('sandbox_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sandbox_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable(); // Version name/description
            $table->binary('yjs_state'); // Snapshot of Y.js state
            $table->timestamps();

            $table->index(['sandbox_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_versions');
        Schema::dropIfExists('sandbox_collaborators');
        Schema::dropIfExists('sandboxes');
    }
};
