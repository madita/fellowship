<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The things members write: pages, blog posts, wiki pages, gallery
 * collections and status updates.
 *
 * Every title and body sits in a `*_translations` table next to its row,
 * because this site is read in more than one language - see the
 * Astrotomic translatable trait on each model. The translation table is
 * created directly after the table it belongs to, so a feature's schema
 * can be read in one place.
 *
 * `pages`, `posts` and `wikiables` keep integer keys: they are the oldest
 * tables here and their translation tables, revisions and imports all
 * point at them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            // A signed int, as it has always been: the translation table,
            // the revisions and every import point at it
            $table->integer('id', true);
            $table->timestamp('published_at')->nullable()->index();
            $table->integer('sign_in_only')->default(0);
            $table->string('slug')->unique();
            // page or wiki
            $table->string('type')->default('page');
            $table->unsignedInteger('user_id')->index('pages_user_id_index');
            $table->unsignedInteger('parent_id')->default(0);
            $table->timestamps();
        });

        Schema::create('page_translations', function (Blueprint $table) {
            $table->id();
            $table->integer('page_id');
            $table->string('locale')->index();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'locale']);
            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('slug')->unique();
            $table->unsignedInteger('user_id')->index('posts_user_id_index');
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('post_translations', function (Blueprint $table) {
            $table->id();
            $table->integer('post_id');
            $table->string('locale')->index();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'locale']);
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
        });

        /**
         * A wiki page is a wrapper: the text lives on whatever it wraps
         * (a Page, usually), and this row gives it a slug, a place in the
         * tree and an approval status.
         */
        Schema::create('wikiables', function (Blueprint $table) {
            $table->increments('id');
            $table->string('slug');
            $table->string('wikiable_type');
            $table->unsignedInteger('wikiable_id');
            $table->string('status')->nullable();
            $table->unsignedInteger('parent_id')->default(0);
            $table->nullableTimestamps();

            $table->unique(['wikiable_id', 'wikiable_type'], 'wikiables_unique');
        });

        Schema::create('wiki_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('wiki_id');
            $table->string('locale')->index();
            $table->string('title');
            $table->timestamps();

            $table->unique(['wiki_id', 'locale']);
            $table->foreign('wiki_id')->references('id')->on('wikiables')->cascadeOnDelete();
        });

        // A gallery album, or any other named set of media
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->integer('taxonomy_id')->nullable();
            $table->integer('user_id');
            $table->timestamps();
        });

        Schema::create('collection_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('name');
            $table->timestamps();

            $table->unique(['collection_id', 'locale']);
        });

        // The timeline. Short, of the moment, and never translated.
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->json('media')->nullable();
            $table->string('feeling')->nullable();
            $table->integer('likes_count')->default(0);
            $table->integer('comments_count')->default(0);
            $table->integer('shares_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        /**
         * "See also": any model pointed at any other, both ways round.
         * No foreign keys are possible across two morphs, so
         * RelateableHelper clears these rows when something is deleted.
         */
        Schema::create('relateables', function (Blueprint $table) {
            $table->string('source_type');
            $table->unsignedInteger('source_id');
            $table->string('related_type');
            $table->unsignedInteger('related_id');
            $table->timestamps();

            $table->unique(
                ['source_id', 'source_type', 'related_id', 'related_type'],
                'relatables_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relateables');
        Schema::dropIfExists('statuses');
        Schema::dropIfExists('collection_translations');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('wiki_translations');
        Schema::dropIfExists('wikiables');
        Schema::dropIfExists('post_translations');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('page_translations');
        Schema::dropIfExists('pages');
    }
};
