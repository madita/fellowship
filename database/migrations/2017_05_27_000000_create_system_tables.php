<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSystemTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /**
         * Pages.
         */
        Schema::create('pages', function (Blueprint $table) {
            $table->integer('id', true);
            $table->timestamp('published_at')->nullable()->index();
            $table->integer('sign_in_only')->default(0);
            // $table->string('title'); // Moved to page_translations
            $table->string('slug')->unique();
            $table->string('type')->default('page'); // page, wiki
            // $table->longText('content')->nullable(); // Moved to page_translations
            $table->integer('user_id')->unsigned()->index('pages_user_id_index');
            $table->integer('parent_id')->unsigned()->default(0);
            $table->timestamps();
        });

        /**
         * Blog.
         */
        Schema::create('posts', function (Blueprint $table) {
            $table->integer('id', true);
            // $table->string('title'); // Moved to post_translations
            $table->string('slug')->unique();
            // $table->text('content')->nullable(); // Moved to post_translations
            $table->integer('user_id')->unsigned()->index('posts_user_id_index');
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        /**
         *  Misc.
         */
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('content');
            $table->json('media')->nullable(); // Array of media attachments
            $table->string('feeling')->nullable();
            $table->integer('likes_count')->default(0);
            $table->integer('comments_count')->default(0);
            $table->integer('shares_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('likeable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // A morph cannot cascade, so the Likeable trait clears these
            // rows itself when the liked thing is really deleted.
            $table->morphs('likeable');
            $table->timestamps();

            // What both old tables guaranteed: one like per member per thing
            $table->unique(['user_id', 'likeable_type', 'likeable_id'], 'likeable_unique');
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            // $table->string('name'); // Moved to collection_translations
            $table->string('slug')->unique();
            //            $table->string('type'); // album collectio, page collections (epic)
            //            $table->string('cover_image');
            $table->integer('taxonomy_id')->nullable();
            $table->integer('user_id');
            //            $table->foreignId('taxonomy_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('commentable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // A morph cannot cascade, so the Commentable trait clears these
            // rows itself when the commented-on thing is really deleted.
            $table->morphs('commentable');
            // A reply. One level deep is all any caller renders, but the
            // column does not enforce that — see Comment::isReply().
            $table->foreignId('parent_id')->nullable()->constrained('commentable')->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
            $table->softDeletes();

            // Reading a discussion in order, which is the only read pattern
            $table->index(['commentable_type', 'commentable_id', 'created_at'], 'commentable_subject_index');
            $table->index(['parent_id', 'created_at']);
        });

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

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //Schema::dropIfExists('page_translations');
        //Schema::dropIfExists('post_translations');
        Schema::dropIfExists('commentable');
        Schema::dropIfExists('collection_translations');
        Schema::dropIfExists('relateables');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('statuses');
        Schema::dropIfExists('likeable');
    }
}
