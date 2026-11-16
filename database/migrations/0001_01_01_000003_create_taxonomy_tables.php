<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Terms and taxonomies: the one way this site names and groups things.
 *
 * A term is a word - its wording lives in term_translations. A taxonomy
 * row says what that word is being used as: a forum category, a gallery
 * album, a kind of achievement. `taxables` hangs a taxonomy on anything.
 *
 * The forum has no categories table of its own for this reason; a forum
 * category is a taxonomy of type `forum_cat`, with the forum-only flags
 * in its `properties`.
 *
 * Both keys are unsigned integers rather than bigints, because that is
 * what they have always been and several tables point at them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->increments('id');
            // title, content and lead live in term_translations
            $table->string('slug')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('term_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('term_id');
            $table->string('locale')->index();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->text('lead')->nullable();
            $table->timestamps();

            $table->unique(['term_id', 'locale']);
            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
        });

        Schema::create('taxonomies', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->nullable();
            $table->unsignedInteger('parent_id')->nullable()->index();
            // No constraint, as before: a taxonomy may be written before
            // its term in an import, and Taxonomy::deleting clears up
            $table->unsignedInteger('term_id')->nullable();

            $table->string('taxonomy')->default('default');
            $table->text('color')->nullable();
            $table->unsignedInteger('parent')->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('visible')->default(true);
            $table->boolean('searchable')->default(true);
            // Feature-specific flags, e.g. a forum category's is_locked
            $table->longText('properties')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['term_id', 'taxonomy']);
            $table->foreign('parent_id')->references('id')->on('taxonomies')->cascadeOnDelete();
        });

        Schema::create('taxonomy_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('taxonomy_id');
            $table->string('locale')->index();
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->text('lead')->nullable();
            $table->text('meta_desc')->nullable();
            $table->timestamps();

            $table->unique(['taxonomy_id', 'locale']);
            $table->foreign('taxonomy_id')->references('id')->on('taxonomies')->cascadeOnDelete();
        });

        Schema::create('taxables', function (Blueprint $table) {
            $table->unsignedInteger('taxonomy_id');
            $table->timestamps();
            // morphs() rather than nullableMorphs(): both halves are part
            // of the primary key below
            $table->morphs('taxable');

            $table->primary(['taxonomy_id', 'taxable_type', 'taxable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxables');
        Schema::dropIfExists('taxonomy_translations');
        Schema::dropIfExists('taxonomies');
        Schema::dropIfExists('term_translations');
        Schema::dropIfExists('terms');
    }
};
