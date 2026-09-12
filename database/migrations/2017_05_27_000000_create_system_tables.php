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
            $table->integer('published')->default(0);
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
            $table->string('status'); // published, draft
            $table->timestamps();
        });

        /**
         *  Misc.
         */
        /*Schema::create('statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('parent_id')->nullable();
            $table->text('content');
            $table->timestamps();
        });*/

        Schema::create('likeable', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('likeable_id');
            $table->string('likeable_type');
            // $table->integer('icon_id');
            $table->timestamps();
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
        Schema::dropIfExists('page_translations');
        Schema::dropIfExists('post_translations');
        Schema::dropIfExists('collection_translations');
        Schema::dropIfExists('relateables');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('statuses');
        Schema::dropIfExists('likeable');
    }
}
