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
         * Pages
         */
        Schema::create('pages', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('published')->default(0);
            $table->integer('sign_in_only')->default(0);
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('type')->default('page'); //page, wiki
            $table->longText('body')->nullable();
            $table->integer('user_id')->unsigned()->index('user_id');
            $table->integer('parent_id')->unsigned()->default(0);
            $table->timestamps();
        });

        /**
         * Blog
         */

        Schema::create('posts', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('body')->nullable();
            $table->integer('user_id')->unsigned()->index('user_id');
            $table->string('status'); //published, draft
            $table->timestamps();
        });


        /**
         *  Misc
         */
        Schema::create('statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('parent_id')->nullable();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('likeable', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('likeable_id');
            $table->string('likeable_type');
            //$table->integer('icon_id');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::drop('likeable');
        Schema::drop('statuses');
        Schema::drop('pages');
        Schema::drop('posts');

    }
}
