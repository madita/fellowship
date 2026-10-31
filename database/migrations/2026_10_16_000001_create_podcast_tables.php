<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A show. Everything a podcast directory asks for in a channel
        // lives here, because the RSS feed is built straight off the row.
        Schema::create('podcasts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            //$table->string('author')->nullable();
            $table->string('language', 10)->default('de');
            //$table->string('category')->nullable();
            $table->boolean('explicit')->default(false);
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_published');
        });

        Schema::create('podcast_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('podcast_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('summary')->nullable();
            // The show notes, rich text like a forum post
            $table->longText('show_notes')->nullable();
            $table->string('cover_image')->nullable();
            // Either a file on our own disk or an episode hosted elsewhere —
            // audio_path holds the first, audio_url the second.
            $table->string('audio_path')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('audio_mime')->nullable();
            $table->unsignedBigInteger('audio_size')->nullable();
            $table->unsignedInteger('duration')->nullable(); // seconds
            $table->unsignedInteger('episode_number')->nullable();
            $table->unsignedInteger('season_number')->nullable();
            $table->unsignedBigInteger('plays')->default(0);
            $table->boolean('explicit')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A slug only has to be unique inside its own show
            $table->unique(['podcast_id', 'slug']);
            $table->index(['podcast_id', 'is_published', 'published_at']);
        });

        Schema::create('podcast_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('podcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One row per person per show; the order they were added is
            // the order the feed names them
            $table->unique(['podcast_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('podcast_authors');
        Schema::dropIfExists('podcast_episodes');
        Schema::dropIfExists('podcasts');
    }
};
