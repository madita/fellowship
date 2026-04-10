<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated migration for all translation tables.
 * 
 * This replaces the following individual migrations:
 * - 2026_02_09_000001_create_page_translations_table.php
 * - 2026_02_09_000002_create_post_translations_table.php
 * - 2026_02_09_000003_create_wiki_translations_table.php
 * - 2026_02_09_000004_create_event_translations_table.php
 * - 2026_02_09_000005_create_collection_translations_table.php
 * - 2026_02_09_000006_create_term_translations_table.php
 * - 2026_02_09_000007_create_taxonomy_translations_table.php
 * - 2026_12_10_232752_create_settings_translations.php (partial)
 */
return new class() extends Migration {
    public function up(): void
    {
        // Page translations
        Schema::create('page_translations', function (Blueprint $table) {
            $table->id();
            $table->integer('page_id');
            $table->string('locale')->index();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->unique(['page_id', 'locale']);
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
        });

        // Post translations
        Schema::create('post_translations', function (Blueprint $table) {
            $table->id();
            $table->integer('post_id');
            $table->string('locale')->index();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->unique(['post_id', 'locale']);
            $table->timestamps();

            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
        });

        // Wiki translations
        Schema::create('wiki_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('wiki_id');
            $table->string('locale')->index();
            $table->string('title');
            $table->unique(['wiki_id', 'locale']);
            $table->timestamps();

            $table->foreign('wiki_id')->references('id')->on('wikiables')->cascadeOnDelete();
        });

        // Event translations
        Schema::create('event_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unique(['event_id', 'locale']);
            $table->timestamps();
        });

        // Collection translations
        Schema::create('collection_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('name');
            $table->unique(['collection_id', 'locale']);
            $table->timestamps();
        });

        // Term translations
        Schema::create('term_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('term_id');
            $table->string('locale')->index();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->text('lead')->nullable();
            $table->unique(['term_id', 'locale']);
            $table->timestamps();

            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
        });

        // Taxonomy translations
        Schema::create('taxonomy_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('taxonomy_id');
            $table->string('locale')->index();
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->text('lead')->nullable();
            $table->text('meta_desc')->nullable();
            $table->unique(['taxonomy_id', 'locale']);
            $table->timestamps();

            $table->foreign('taxonomy_id')->references('id')->on('taxonomies')->cascadeOnDelete();
        });

        // Section translations
        Schema::create('section_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('title')->nullable();
            $table->unique(['section_id', 'locale']);
            $table->timestamps();
        });

        // Widget translations
        Schema::create('widget_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('widget_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('title')->nullable();
            $table->json('content')->nullable();
            $table->unique(['widget_id', 'locale']);
            $table->timestamps();
        });

        // Homepage menu item translations
        Schema::create('homepage_menu_item_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homepage_menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('label');
            $table->unique(['homepage_menu_item_id', 'locale'], 'homepage_menu_item_trans_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_menu_item_translations');
        Schema::dropIfExists('widget_translations');
        Schema::dropIfExists('section_translations');
        Schema::dropIfExists('taxonomy_translations');
        Schema::dropIfExists('term_translations');
        Schema::dropIfExists('collection_translations');
        Schema::dropIfExists('event_translations');
        Schema::dropIfExists('wiki_translations');
        Schema::dropIfExists('post_translations');
        Schema::dropIfExists('page_translations');
    }
};
